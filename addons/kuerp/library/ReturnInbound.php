<?php

namespace addons\kuerp\library;

use think\Db;
use think\Session;

/**
 * 销货退货「退货入库」
 *
 * 背景：销货退货单【审核】只做账务（应收/退款/台账/开票/订单状态），
 * 库存、成本、收发明细、序列号、批次在仓库执行「退货入库」时才增加。
 *
 * 关联关系：
 *   销货单(fa_kuerp_goods_sales) ← fa_kuerp_goods_saleexc.purchase_id (type=2, 表头级)
 *                                ← fa_kuerp_goods_saleexc_info.purchase_id
 *                                     = 销货单明细行ID(type=2) / 销货订单明细行ID(type=1)
 *                                     其中 type=1 需经 fa_kuerp_goods_saleord_info 桥接回销货单明细行
 *   退货单明细 = fa_kuerp_goods_sales_exchange_info，累计已入库数量 = out_number
 */
class ReturnInbound
{
    /** 浮点误差容差 */
    const EPS = 0.00005;

    /**
     * 关联到某销货单的退货单明细行ID
     *
     * @param int    $salesId   销货单ID
     * @param string $salesCode 销货单号
     * @return array
     */
    public static function relatedInfoIds($salesId, $salesCode)
    {
        $ids = [];

        // 销货单明细行
        $salesInfoIds = Db::name('kuerp_goods_sales_info')
            ->where(['purchase_code' => $salesCode])
            ->column('id');

        if (!empty($salesInfoIds)) {
            // 1) 行级关联：从销货单生成的退货单（type=2，purchase_id = 销货单明细行ID）
            $ids = array_merge($ids, (array)Db::name('kuerp_goods_saleexc_info')
                ->where(['purchase_id' => ['in', $salesInfoIds], 'type' => '2'])
                ->column('exchange_id'));

            // 2) 行级关联：从销货订单生成的退货单（type=1，purchase_id = 销货订单明细行ID）
            //    经 saleord_info 桥接回销货单明细行，避免退货单被算到同一订单的其它销货单上
            $orderInfoIds = Db::name('kuerp_goods_saleord_info')
                ->where(['purchase_id' => ['in', $salesInfoIds]])
                ->column('order_id');
            if (!empty($orderInfoIds)) {
                $ids = array_merge($ids, (array)Db::name('kuerp_goods_saleexc_info')
                    ->where(['purchase_id' => ['in', $orderInfoIds], 'type' => '1'])
                    ->column('exchange_id'));
            }
        }

        // 3) 表头级关联：从销货单生成的退货单（saleexc.type = 2，purchase_id = 销货单ID）
        $exchangeIds = Db::name('kuerp_goods_saleexc')
            ->where(['purchase_id' => (int)$salesId, 'type' => '2'])
            ->column('exchange_id');
        if (!empty($exchangeIds)) {
            $codes = Db::name('kuerp_goods_sales_exchange')
                ->where(['id' => ['in', $exchangeIds]])
                ->column('purchase_code');
            if (!empty($codes)) {
                $ids = array_merge($ids, (array)Db::name('kuerp_goods_sales_exchange_info')
                    ->where(['purchase_code' => ['in', $codes]])
                    ->column('id'));
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * 待入库明细（已审核且尚未入完的退货单明细行）
     *
     * @param int    $salesId
     * @param string $salesCode
     * @return array
     */
    public static function pendingLines($salesId, $salesCode)
    {
        $infoIds = self::relatedInfoIds($salesId, $salesCode);
        if (empty($infoIds)) {
            return [];
        }

        $rows = Db::name('kuerp_goods_sales_exchange_info')->alias('i')
            ->join('kuerp_goods_sales_exchange e', 'e.purchase_code = i.purchase_code')
            ->where(['i.id' => ['in', $infoIds], 'e.examine_status' => '1'])
            ->field('i.id,i.purchase_code as exchange_code,i.goods_code,i.goods_attr,i.goods_unit,i.stores_id,'
                . 'i.number,i.basic_nmber,i.retreat_number,i.out_number,i.unit_price,i.all_money,'
                . 'i.money_tax_price,i.tax_money,i.remark,i.batch_id,i.batch_name,'
                . 'e.purchase_time as exchange_time,e.customers_id')
            ->order('e.purchase_time asc,i.id asc')
            ->select();
        if (empty($rows)) {
            return [];
        }

        $storesMap = Db::name('kuerp_stores')->column('name', 'id');
        $unitMap   = Db::name('kuerp_unit')->column('name', 'id');
        $attrMap   = self::attrNames(array_column($rows, 'goods_attr'));
        $goodsList = Db::name('kuerp_goods')
            ->where(['goods_code' => ['in', array_unique(array_column($rows, 'goods_code'))]])
            ->field('goods_code,goods_name,goods_specification_id,vendor_name,packaging')
            ->select();
        $goodsMap = [];
        foreach ($goodsList as $g) {
            $goodsMap[$g['goods_code']] = $g;
        }

        $list = [];
        foreach ($rows as $r) {
            $remain = round((float)$r['retreat_number'] - (float)$r['out_number'], 4);
            if ($remain <= self::EPS) {
                continue;
            }
            $r['remain_number'] = $remain;
            $r['out_number']    = (float)$r['out_number'];
            $g = isset($goodsMap[$r['goods_code']]) ? $goodsMap[$r['goods_code']] : null;
            $r['goods_name']             = $g ? $g['goods_name'] : '';
            $r['goods_specification_id'] = $g ? $g['goods_specification_id'] : '';
            $r['vendor_name']            = $g ? $g['vendor_name'] : '';
            $r['packaging']              = $g ? $g['packaging'] : '';
            $r['stores_name']            = isset($storesMap[$r['stores_id']]) ? $storesMap[$r['stores_id']] : '';
            $r['unit_name']              = isset($unitMap[$r['goods_unit']]) ? $unitMap[$r['goods_unit']] : '';
            $r['attr_name']              = (!empty($r['goods_attr']) && isset($attrMap[$r['goods_attr']])) ? $attrMap[$r['goods_attr']] : '';
            $list[] = $r;
        }

        return $list;
    }

    /**
     * 辅助属性ID => 属性名称（如「红色/XL」）
     *
     * @param array $attrIds kuerp_goods_attr.id 列表
     * @return array
     */
    private static function attrNames($attrIds)
    {
        $attrIds = array_filter(array_unique(array_map('intval', (array)$attrIds)));
        if (empty($attrIds)) {
            return [];
        }
        $combos   = Db::name('kuerp_goods_attr')->where(['id' => ['in', $attrIds]])->column('attribute_id', 'id');
        $comboIds = array_filter(array_unique(array_values($combos)));
        $auxMap   = Db::name('kuerp_auxiliary')->column('name', 'id');
        $comboMap = $comboIds
            ? Db::name('kuerp_aux_combination')->where(['id' => ['in', $comboIds]])->column('attr_ids', 'id')
            : [];
        $result = [];
        foreach ($attrIds as $id) {
            $comboId = isset($combos[$id]) ? $combos[$id] : 0;
            $ids     = isset($comboMap[$comboId]) ? (string)$comboMap[$comboId] : '';
            $names   = [];
            if ($ids !== '') {
                foreach (explode('&', $ids) as $aid) {
                    if (isset($auxMap[$aid])) {
                        $names[] = $auxMap[$aid];
                    }
                }
            }
            $result[$id] = implode('/', $names);
        }
        return $result;
    }

    /**
     * 某销货单待入库的退货明细行数（列表按钮显隐用，0 = 显示「退货入库」按钮）
     *
     * @param int    $salesId
     * @param string $salesCode
     * @return int
     */
    public static function pendingCount($salesId, $salesCode)
    {
        $infoIds = self::relatedInfoIds($salesId, $salesCode);
        if (empty($infoIds)) {
            return 0;
        }
        $rows = Db::name('kuerp_goods_sales_exchange_info')->alias('i')
            ->join('kuerp_goods_sales_exchange e', 'e.purchase_code = i.purchase_code')
            ->where(['i.id' => ['in', $infoIds], 'e.examine_status' => '1'])
            ->field('i.retreat_number,i.out_number')
            ->select();
        $count = 0;
        foreach ($rows as $r) {
            if (round((float)$r['retreat_number'] - (float)$r['out_number'], 4) > self::EPS) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 某销货单是否有待入库的退货单
     *
     * @param int    $salesId
     * @param string $salesCode
     * @return bool
     */
    public static function hasPending($salesId, $salesCode)
    {
        return !empty(self::pendingLines($salesId, $salesCode));
    }

    /**
     * 执行退货入库
     *
     * @param array $salesRow 销货单主表行（需含 id / purchase_code）
     * @param array $inData   [退货单明细行ID => 本次入库数量]
     * @return array ['total_number'=>..., 'total_basics'=>..., 'remaining'=>...]
     * @throws \Exception
     */
    public static function inbound($salesRow, array $inData)
    {
        $salesId   = (int)$salesRow['id'];
        $salesCode = (string)$salesRow['purchase_code'];

        $pending = self::pendingLines($salesId, $salesCode);
        if (empty($pending)) {
            throw new \Exception('该销货单没有待入库的退货单');
        }
        $pendingMap = [];
        foreach ($pending as $p) {
            $pendingMap[(int)$p['id']] = $p;
        }

        $admin   = Session::get('admin');
        $adminId = $admin ? (int)$admin['id'] : 0;

        $items = [];
        foreach ($inData as $infoId => $qty) {
            $infoId = (int)$infoId;
            $qty    = round((float)$qty, 4);
            if ($qty <= 0) {
                continue;
            }
            if (!isset($pendingMap[$infoId])) {
                throw new \Exception('退货单明细不存在或已全部入库，请刷新后重试');
            }
            $line   = $pendingMap[$infoId];
            $remain = (float)$line['remain_number'];
            if ($qty - $remain > self::EPS) {
                throw new \Exception('商品' . $line['goods_code'] . '本次入库数量(' . $qty . ')超过剩余可入库数量(' . $remain . ')');
            }

            $goods = Db::name('kuerp_goods')
                ->where(['goods_code' => $line['goods_code']])
                ->field('id,goods_name,goods_code,unit_id,inventory,batch')
                ->find();
            if (empty($goods)) {
                throw new \Exception('商品' . $line['goods_code'] . '不存在');
            }

            // 换算为基础单位数量
            $unit = Db::name('kuerp_unit')->where(['id' => $line['goods_unit']])->find();
            if (empty($unit)) {
                throw new \Exception('商品' . $line['goods_code'] . '的单位信息错误');
            }
            $basic = $qty;
            if ((int)$unit['type'] === 2 && (int)$unit['is_main'] !== 1) {
                $advance = Db::name('kuerp_unit_conversion')
                    ->where(['main_unit' => $goods['unit_id'], 'vice_unit' => $unit['id']])
                    ->value('advance_rate');
                if (empty($advance)) {
                    throw new \Exception('商品' . $line['goods_code'] . '的单位换算信息错误');
                }
                $basic = round($qty * $advance, 4);
            }

            // 入库成本单价：与「审核时入库」口径一致（商品基本单位的采购价）
            $balanceUnit = 0;
            $goodsInfo   = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($line['goods_code'], $line['stores_id'], $line['goods_attr']), true);
            $unitData    = isset($goodsInfo['unit']) ? $goodsInfo['unit'] : [];
            if (empty($unitData)) {
                throw new \Exception('商品' . $line['goods_code'] . '商品信息获取错误');
            }
            foreach ($unitData as $u) {
                if (isset($u['id']) && $u['id'] == $goods['unit_id']) {
                    $balanceUnit = (float)$u['purchase'];
                }
            }
            $cost = round($balanceUnit * $basic, 4);

            // 库存行（不存在则新建）
            $stockWhere = ['goods_id' => $goods['id'], 'stores_id' => $line['stores_id']];
            if ((int)$line['goods_attr'] > 0) {
                $stockWhere['attribute_id'] = $line['goods_attr'];
            }
            $stock = Db::name('kuerp_goods_stock')->where($stockWhere)->field('id,stock,cost_money')->find();

            $willDone = ((float)$line['out_number'] + $qty) >= ((float)$line['retreat_number'] - self::EPS);

            $items[] = [
                'info_id'        => $infoId,
                'exchange_code'  => $line['exchange_code'],
                'exchange_time'  => $line['exchange_time'],
                'customers_id'   => (int)$line['customers_id'],
                'goods_id'       => $goods['id'],
                'goods_code'     => $line['goods_code'],
                'goods_name'     => $line['goods_name'] ?: $goods['goods_name'],
                'goods_attr'     => (int)$line['goods_attr'],
                'goods_unit'     => (int)$line['goods_unit'],
                'stores_id'      => (int)$line['stores_id'],
                'stores_name'    => $line['stores_name'],
                'unit_name'      => $line['unit_name'],
                'attr_name'      => isset($line['attr_name']) ? $line['attr_name'] : '',
                'remark'         => $line['remark'],
                'batch_id'       => (int)$line['batch_id'],
                'batch_name'     => $line['batch_name'],
                'qty'            => $qty,
                'basic'          => $basic,
                'cost'           => $cost,
                'unit_cost'      => $balanceUnit,
                'stock_id'       => $stock ? (int)$stock['id'] : 0,
                'stock_row'      => $stock ?: null,
                'stock_where'    => $stockWhere,
                'will_done'      => $willDone,
                'retreat_number' => (float)$line['retreat_number'],
                'out_number'     => (float)$line['out_number'],
            ];
        }

        if (empty($items)) {
            throw new \Exception('请填写本次退货入库数量');
        }

        $totalNumber = 0;
        $totalBasics = 0;
        foreach ($items as $it) {
            $totalNumber += $it['qty'];
            $totalBasics += $it['basic'];
        }

        $batchSet = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');
        $serialSet = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');

        Db::startTrans();
        try {
            // 库存行余额（按出现顺序累计，写收发明细的余额字段）
            $balance = [];
            $createdStock = []; // 本次新建的库存行：key => stock_id（同一 商品+仓库+属性 多行明细只建一条）
            $detail  = [];
            foreach ($items as $it) {
                // 1) 仓库库存 + 成本（库存行不存在则新建）
                if ($it['stock_id'] > 0) {
                    if (!isset($balance[$it['stock_id']])) {
                        $s = Db::name('kuerp_goods_stock')->where(['id' => $it['stock_id']])->field('stock,cost_money')->find();
                        $balance[$it['stock_id']] = [
                            'stock' => (float)$s['stock'],
                            'cost'  => (float)$s['cost_money'],
                        ];
                    }
                    $balance[$it['stock_id']]['stock'] = round($balance[$it['stock_id']]['stock'] + $it['basic'], 4);
                    $balance[$it['stock_id']]['cost']  = round($balance[$it['stock_id']]['cost'] + $it['cost'], 4);
                    Db::name('kuerp_goods_stock')->where(['id' => $it['stock_id']])->update([
                        'stock'      => $balance[$it['stock_id']]['stock'],
                        'cost_money' => $balance[$it['stock_id']]['cost'],
                    ]);
                    $b = $balance[$it['stock_id']];
                } else {
                    $stockKey = $it['goods_id'] . '|' . $it['stores_id'] . '|' . $it['goods_attr'];
                    if (isset($createdStock[$stockKey])) {
                        $it['stock_id'] = $createdStock[$stockKey];
                        $balance[$it['stock_id']]['stock'] = round($balance[$it['stock_id']]['stock'] + $it['basic'], 4);
                        $balance[$it['stock_id']]['cost']  = round($balance[$it['stock_id']]['cost'] + $it['cost'], 4);
                        Db::name('kuerp_goods_stock')->where(['id' => $it['stock_id']])->update([
                            'stock'      => $balance[$it['stock_id']]['stock'],
                            'cost_money' => $balance[$it['stock_id']]['cost'],
                        ]);
                        $b = $balance[$it['stock_id']];
                    } else {
                        $b = ['stock' => round($it['basic'], 4), 'cost' => round($it['cost'], 4)];
                        $it['stock_id'] = (int)Db::name('kuerp_goods_stock')->insertGetId(array_merge($it['stock_where'], [
                            'goods_code' => $it['goods_code'],
                            'stock'      => $b['stock'],
                            'cost_money' => $b['cost'],
                            'status'     => '1',
                            'createtime' => time(),
                            'updatetime' => time(),
                        ]));
                        $createdStock[$stockKey] = $it['stock_id'];
                        $balance[$it['stock_id']] = $b;
                    }
                }

                // 2) 商品总库存
                Db::name('kuerp_goods')->where(['id' => $it['goods_id']])->setInc('inventory', $it['basic']);
                // 3) 退货单明细累计已入库
                Db::name('kuerp_goods_sales_exchange_info')->where(['id' => $it['info_id']])->setInc('out_number', $it['qty']);

                // 4) 收发明细
                Db::name('kuerp_goods_log')->insert(Goods::setDataInfo([
                    'purchase_code'  => $it['exchange_code'],
                    'purchase_id'    => $it['info_id'],
                    'purchase_time'  => $it['exchange_time'],
                    'business_type'  => '5',
                    'customer_id'    => $it['customers_id'],
                    'goods_id'       => $it['goods_id'],
                    'goods_code'     => $it['goods_code'],
                    'attribute_id'   => $it['goods_attr'],
                    'goods_unit'     => $it['goods_unit'],
                    'stores_id'      => $it['stores_id'],
                    'remark'         => $it['remark'],
                    'enter_number'   => $it['qty'],
                    'enter_basics'   => $it['basic'],
                    'enter_unit'     => $it['unit_cost'],
                    'enter_cost'     => $it['cost'],
                    'balance_basics' => $b['stock'],
                    'balance_unit'   => $b['stock'] > 0 ? round($b['cost'] / $b['stock'], 4) : 0,
                    'balance_cost'   => $b['cost'],
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]));

                // 5) 批次库存（启用批次且有批次的商品）
                if ($batchSet == 1 && !empty($it['batch_id'])) {
                    $batchWhere = [
                        'goods_id'  => $it['goods_id'],
                        'stores_id' => $it['stores_id'],
                        'batch_id'  => $it['batch_id'],
                    ];
                    if ($it['goods_attr'] > 0) {
                        $batchWhere['attribute_id'] = $it['goods_attr'];
                    }
                    $batchStock = Db::name('kuerp_goods_batch_stock')->where($batchWhere)->find();
                    if ($batchStock) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $batchStock['id']])->setInc('stock', $it['basic']);
                    } else {
                        Db::name('kuerp_goods_batch_stock')->insert(array_merge($batchWhere, [
                            'goods_code' => $it['goods_code'],
                            'stock'      => $it['basic'],
                            'createtime' => time(),
                            'updatetime' => time(),
                        ]));
                    }
                    Db::name('kuerp_goods_batch_log')->insert(Goods::setDataInfo([
                        'goods_id'        => $it['goods_id'],
                        'goods_unit'      => $it['goods_unit'],
                        'stores_id'       => $it['stores_id'],
                        'batch_id'        => $it['batch_id'],
                        'number'          => $it['qty'],
                        'purchase_code'   => $it['exchange_code'],
                        'purchase_time'   => $it['exchange_time'],
                        'business_type'   => '5',
                        'remarks'         => $it['remark'],
                        'order_id'        => $it['info_id'],
                        'customer_id'     => $it['customers_id'],
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]));
                    if ($it['will_done']) {
                        Db::name('kuerp_goods_batch_salee_info')
                            ->where(['purchase_info_id' => $it['info_id']])
                            ->update(['status' => '1']);
                    }
                }

                // 6) 序列号：整行入完后标记为在库
                if ($serialSet == 1 && $it['will_done']) {
                    $serials = Db::name('kuerp_goods_serial')
                        ->where(function ($q) use ($it) {
                            $q->where('sale_exchange_id', $it['info_id'])->whereOr('sale_id', $it['info_id']);
                        })
                        ->field('id,goods_id,serial,stores_id,attribute_id')
                        ->select();
                    if (!empty($serials)) {
                        $serialLogs = [];
                        foreach ($serials as $s) {
                            $serialLogs[] = Goods::setDataInfo([
                                'goods_id'      => $s['goods_id'],
                                'stores_id'     => $s['stores_id'],
                                'attribute_id'  => $s['attribute_id'],
                                'serial'        => $s['serial'],
                                'business_type' => '5',
                                'store_status'  => '1',
                                'remarks'       => $it['remark'],
                                'purchase_id'   => $it['info_id'],
                                'customer_id'   => $it['customers_id'],
                                'purchase_code' => $it['exchange_code'],
                                'purchase_time' => $it['exchange_time'],
                                'createtime'    => time(),
                                'updatetime'    => time(),
                            ]);
                        }
                        Db::name('kuerp_goods_serial_log')->insertAll($serialLogs);
                        Db::name('kuerp_goods_serial')
                            ->where(['id' => ['in', array_column($serials, 'id')]])
                            ->update(['store_status' => '1']);
                    }
                }

                $detail[] = [
                    'goods_code'    => $it['goods_code'],
                    'goods_name'    => $it['goods_name'],
                    'number'        => $it['qty'],
                    'basic_number'  => $it['basic'],
                    'stores_name'   => $it['stores_name'],
                    'unit_name'     => $it['unit_name'],
                    'exchange_code' => $it['exchange_code'],
                ];
            }

            // 7) 本次入库操作记录
            $exchangeCodes = array_values(array_unique(array_column($items, 'exchange_code')));
            Db::name('kuerp_goods_return_inbound_log')->insert([
                'sales_id'       => $salesId,
                'sales_code'     => $salesCode,
                'exchange_codes' => implode(',', $exchangeCodes),
                'total_number'   => round($totalNumber, 4),
                'total_basics'   => round($totalBasics, 4),
                'detail'         => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'admin_id'       => $adminId,
                'createtime'     => time(),
            ]);

            // 8) 销货单更新时间
            Db::name('kuerp_goods_sales')->where(['id' => $salesId])->update(['updatetime' => time()]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new \Exception($e->getMessage());
        }

        return [
            'total_number' => round($totalNumber, 4),
            'total_basics' => round($totalBasics, 4),
            //剩余待入库明细行数（0 = 该销货单的退货单已全部入库，弹窗可直接关闭）
            'remaining'    => self::pendingCount($salesId, $salesCode),
        ];
    }

    /**
     * 某销货单的退货入库操作记录（最新在前）
     *
     * @param int $salesId
     * @return array
     */
    public static function logs($salesId)
    {
        $logList = Db::name('kuerp_goods_return_inbound_log')
            ->where(['sales_id' => (int)$salesId])
            ->order('id', 'desc')
            ->select();
        if (empty($logList)) {
            return [];
        }
        $adminIds = array_filter(array_unique(array_column($logList, 'admin_id')));
        $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];

        foreach ($logList as $k => $log) {
            $logList[$k]['admin_name']  = isset($adminMap[$log['admin_id']]) ? $adminMap[$log['admin_id']] : '';
            $logList[$k]['detail_list'] = $log['detail'] ? json_decode($log['detail'], true) : [];
            $texts = [];
            foreach ((array)$logList[$k]['detail_list'] as $d) {
                $label = !empty($d['goods_name']) ? $d['goods_code'] . ' ' . $d['goods_name'] : $d['goods_code'];
                $texts[] = $label . ' × ' . $d['number'] . (!empty($d['unit_name']) ? $d['unit_name'] : '')
                    . (!empty($d['stores_name']) ? '（' . $d['stores_name'] . '）' : '');
            }
            $logList[$k]['detail_text'] = implode('、', $texts);
        }
        return $logList;
    }
}
