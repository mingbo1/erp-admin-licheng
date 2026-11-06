<?php

namespace addons\kuerp\library;

use think\Db;
use think\Session;

/**
 * 采购退货「退货出库」
 *
 * 背景：购货退货单【审核】只做账务（应付/收付款/台账/开票/订单状态），
 * 库存、成本、收发明细、序列号、批次在仓库执行「退货出库」时才扣减。
 *
 * 关联关系：
 *   采购单(fa_kuerp_goods_purchase) ← fa_kuerp_goods_excpur.purchase_id (type=2, 表头级)
 *                                   ← fa_kuerp_goods_excpur_info.purchase_id (= 采购单明细行ID, 行级)
 *   退货单明细 = fa_kuerp_goods_exchange_info，累计已出库数量 = out_number
 */
class ReturnOutbound
{
    /** 浮点误差容差 */
    const EPS = 0.00005;

    /**
     * 关联到某采购单的退货单明细行ID
     *
     * @param int    $purchaseId   采购单ID
     * @param string $purchaseCode 采购单号
     * @return array
     */
    public static function relatedInfoIds($purchaseId, $purchaseCode)
    {
        $ids = [];

        // 1) 行级关联：退货单明细 → 采购单明细行
        $purchaseInfoIds = Db::name('kuerp_goods_purchase_info')
            ->where(['purchase_code' => $purchaseCode])
            ->column('id');
        if (!empty($purchaseInfoIds)) {
            // type = 2：从采购单生成的退货单（避免与「从采购订单生成」的 type=1 行号串号）
            $ids = array_merge($ids, (array)Db::name('kuerp_goods_excpur_info')
                ->where(['purchase_id' => ['in', $purchaseInfoIds], 'type' => '2'])
                ->column('exchange_id'));
        }

        // 2) 表头级关联：从采购单生成的退货单（excpur.type = 2，purchase_id = 采购单ID）
        $exchangeIds = Db::name('kuerp_goods_excpur')
            ->where(['purchase_id' => (int)$purchaseId, 'type' => '2'])
            ->column('exchange_id');
        if (!empty($exchangeIds)) {
            $codes = Db::name('kuerp_goods_exchange')
                ->where(['id' => ['in', $exchangeIds]])
                ->column('purchase_code');
            if (!empty($codes)) {
                $ids = array_merge($ids, (array)Db::name('kuerp_goods_exchange_info')
                    ->where(['purchase_code' => ['in', $codes]])
                    ->column('id'));
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * 待出库明细（已审核且尚未出完的退货单明细行）
     *
     * @param int    $purchaseId
     * @param string $purchaseCode
     * @return array
     */
    public static function pendingLines($purchaseId, $purchaseCode)
    {
        $infoIds = self::relatedInfoIds($purchaseId, $purchaseCode);
        if (empty($infoIds)) {
            return [];
        }

        $rows = Db::name('kuerp_goods_exchange_info')->alias('i')
            ->join('kuerp_goods_exchange e', 'e.purchase_code = i.purchase_code')
            ->where(['i.id' => ['in', $infoIds], 'e.examine_status' => '1'])
            ->field('i.id,i.purchase_code as exchange_code,i.goods_code,i.goods_attr,i.goods_unit,i.stores_id,'
                . 'i.number,i.basic_nmber,i.retreat_number,i.out_number,i.unit_price,i.all_money,'
                . 'i.money_tax_price,i.tax_money,i.remark,i.batch_id,i.batch_name,'
                . 'e.purchase_time as exchange_time,e.supplyer_code')
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
     * 某采购单是否有待出库的退货单
     *
     * @param int    $purchaseId
     * @param string $purchaseCode
     * @return bool
     */
    public static function hasPending($purchaseId, $purchaseCode)
    {
        return !empty(self::pendingLines($purchaseId, $purchaseCode));
    }

    /**
     * 执行退货出库
     *
     * @param array $purchaseRow 采购单主表行（需含 id / purchase_code）
     * @param array $outData     [退货单明细行ID => 本次出库数量]
     * @return array ['total_number'=>..., 'total_basics'=>...]
     * @throws \Exception
     */
    public static function outbound($purchaseRow, array $outData)
    {
        $purchaseId   = (int)$purchaseRow['id'];
        $purchaseCode = (string)$purchaseRow['purchase_code'];

        $pending = self::pendingLines($purchaseId, $purchaseCode);
        if (empty($pending)) {
            throw new \Exception('该采购单没有待出库的退货单');
        }
        $pendingMap = [];
        foreach ($pending as $p) {
            $pendingMap[(int)$p['id']] = $p;
        }

        $admin   = Session::get('admin');
        $adminId = $admin ? (int)$admin['id'] : 0;

        $items = [];
        foreach ($outData as $infoId => $qty) {
            $infoId = (int)$infoId;
            $qty    = round((float)$qty, 4);
            if ($qty <= 0) {
                continue;
            }
            if (!isset($pendingMap[$infoId])) {
                throw new \Exception('退货单明细不存在或已全部出库，请刷新后重试');
            }
            $line   = $pendingMap[$infoId];
            $remain = (float)$line['remain_number'];
            if ($qty - $remain > self::EPS) {
                throw new \Exception('商品' . $line['goods_code'] . '本次出库数量(' . $qty . ')超过剩余可出库数量(' . $remain . ')');
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

            // 库存行
            $stockWhere = ['goods_id' => $goods['id'], 'stores_id' => $line['stores_id']];
            if ((int)$line['goods_attr'] > 0) {
                $stockWhere['attribute_id'] = $line['goods_attr'];
            }
            $stock = Db::name('kuerp_goods_stock')->where($stockWhere)->field('id,stock,cost_money')->find();
            if (empty($stock)) {
                throw new \Exception('未查询到商品' . $line['goods_code'] . '在仓库[' . $line['stores_name'] . ']的库存，不能退货出库');
            }

            // 成本：按比例分摊，最后一批吃掉尾差
            $alreadyCost = (float)Db::name('kuerp_goods_log')
                ->where(['business_type' => '3', 'purchase_id' => $line['id']])
                ->sum('out_cost');
            $totalCost   = (float)$line['all_money'];
            $willDone    = ((float)$line['out_number'] + $qty) >= ((float)$line['retreat_number'] - self::EPS);
            $cost        = $willDone
                ? round($totalCost - $alreadyCost, 4)
                : round($totalCost * $qty / max((float)$line['retreat_number'], 0.0001), 4);
            $unitCost    = $qty > 0 ? round($cost / $qty, 4) : 0;

            $items[] = [
                'info_id'     => $infoId,
                'exchange_code' => $line['exchange_code'],
                'exchange_time' => $line['exchange_time'],
                'supplyer_code' => $line['supplyer_code'],
                'goods_id'    => $goods['id'],
                'goods_code'  => $line['goods_code'],
                'goods_name'  => $line['goods_name'] ?: $goods['goods_name'],
                'goods_attr'  => (int)$line['goods_attr'],
                'goods_unit'  => (int)$line['goods_unit'],
                'stores_id'   => (int)$line['stores_id'],
                'stores_name' => $line['stores_name'],
                'unit_name'   => $line['unit_name'],
                'attr_name'   => isset($line['attr_name']) ? $line['attr_name'] : '',
                'remark'      => $line['remark'],
                'batch_id'    => (int)$line['batch_id'],
                'qty'         => $qty,
                'basic'       => $basic,
                'cost'        => $cost,
                'unit_cost'   => $unitCost,
                'stock_id'    => (int)$stock['id'],
                'will_done'   => $willDone,
                'retreat_number' => (float)$line['retreat_number'],
                'out_number'  => (float)$line['out_number'],
            ];
        }

        if (empty($items)) {
            throw new \Exception('请填写本次退货出库数量');
        }

        // 按库存行汇总校验可用库存（同一库存行多行明细合并判断）
        $needByStock = [];
        foreach ($items as $it) {
            $needByStock[$it['stock_id']] = (isset($needByStock[$it['stock_id']]) ? $needByStock[$it['stock_id']] : 0) + $it['basic'];
        }
        foreach ($needByStock as $stockId => $need) {
            $stock = Db::name('kuerp_goods_stock')->where(['id' => $stockId])->field('stock')->find();
            if (!$stock || (float)$stock['stock'] < $need - self::EPS) {
                $cur = $stock ? (float)$stock['stock'] : 0;
                throw new \Exception('库存不足：本次需出库 ' . $need . '，当前可用库存 ' . $cur);
            }
        }

        $totalNumber = 0;
        $totalBasics = 0;
        foreach ($items as $it) {
            $totalNumber += $it['qty'];
            $totalBasics += $it['basic'];
        }

        $batchSet = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');

        Db::startTrans();
        try {
            // 库存行余额（按出现顺序累计，写收发明细的余额字段）
            $balance = [];
            $detail  = [];
            foreach ($items as $it) {
                if (!isset($balance[$it['stock_id']])) {
                    $s = Db::name('kuerp_goods_stock')->where(['id' => $it['stock_id']])->field('stock,cost_money')->find();
                    $balance[$it['stock_id']] = [
                        'stock' => (float)$s['stock'],
                        'cost'  => (float)$s['cost_money'],
                    ];
                }
                $balance[$it['stock_id']]['stock'] = round($balance[$it['stock_id']]['stock'] - $it['basic'], 4);
                $balance[$it['stock_id']]['cost']  = round($balance[$it['stock_id']]['cost'] - $it['cost'], 4);
                $b = $balance[$it['stock_id']];

                // 1) 仓库库存 + 成本
                Db::name('kuerp_goods_stock')->where(['id' => $it['stock_id']])->update([
                    'stock'      => $b['stock'],
                    'cost_money' => $b['cost'],
                ]);
                // 2) 商品总库存
                Db::name('kuerp_goods')->where(['id' => $it['goods_id']])->setDec('inventory', $it['basic']);
                // 3) 退货单明细累计已出库
                Db::name('kuerp_goods_exchange_info')->where(['id' => $it['info_id']])->setInc('out_number', $it['qty']);

                // 4) 收发明细
                $supplierId = Db::name('kuerp_suppliers')->where(['supplyer_code' => $it['supplyer_code']])->value('id');
                Db::name('kuerp_goods_log')->insert(Goods::setDataInfo([
                    'purchase_code'  => $it['exchange_code'],
                    'purchase_id'    => $it['info_id'],
                    'purchase_time'  => $it['exchange_time'],
                    'business_type'  => '3',
                    'customer_id'    => $supplierId ? $supplierId : 0,
                    'goods_id'       => $it['goods_id'],
                    'goods_code'     => $it['goods_code'],
                    'attribute_id'   => $it['goods_attr'],
                    'goods_unit'     => $it['goods_unit'],
                    'stores_id'      => $it['stores_id'],
                    'remark'         => $it['remark'],
                    'out_number'     => $it['qty'],
                    'out_basics'     => $it['basic'],
                    'out_unit'       => $it['unit_cost'],
                    'out_cost'       => $it['cost'],
                    'balance_basics' => $b['stock'],
                    'balance_unit'   => $b['stock'] > 0 ? round($b['cost'] / $b['stock'], 4) : 0,
                    'balance_cost'   => $b['cost'],
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]));

                // 5) 批次库存（启用批次且有批次的商品）
                if ($batchSet == 1 && !empty($it['batch_id'])) {
                    $batchWhere = [
                        'goods_id'   => $it['goods_id'],
                        'stores_id'  => $it['stores_id'],
                        'batch_id'   => $it['batch_id'],
                    ];
                    if ($it['goods_attr'] > 0) {
                        $batchWhere['attribute_id'] = $it['goods_attr'];
                    }
                    $batchStock = Db::name('kuerp_goods_batch_stock')->where($batchWhere)->find();
                    if ($batchStock) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $batchStock['id']])->setDec('stock', $it['basic']);
                    }
                    Db::name('kuerp_goods_batch_log')->insert(Goods::setDataInfo([
                        'goods_id'        => $it['goods_id'],
                        'goods_unit'      => $it['goods_unit'],
                        'stores_id'       => $it['stores_id'],
                        'batch_id'        => $it['batch_id'],
                        'number'          => $it['qty'],
                        'purchase_code'   => $it['exchange_code'],
                        'purchase_time'   => $it['exchange_time'],
                        'business_type'   => '3',
                        'remarks'         => $it['remark'],
                        'order_id'        => $it['info_id'],
                        'customer_id'     => $supplierId ? $supplierId : 0,
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]));
                    if ($it['will_done']) {
                        Db::name('kuerp_goods_batch_exchange_info')
                            ->where(['purchase_info_id' => $it['info_id']])
                            ->update(['status' => '1']);
                    }
                }

                // 6) 序列号：整行出完后标记为已出库
                if ($it['will_done']) {
                    $serials = Db::name('kuerp_goods_serial')
                        ->where(['purchase_exchange_id' => $it['info_id']])
                        ->field('goods_id,serial,stores_id,attribute_id')
                        ->select();
                    if (!empty($serials)) {
                        $serialLogs = [];
                        foreach ($serials as $s) {
                            $serialLogs[] = Goods::setDataInfo([
                                'goods_id'      => $s['goods_id'],
                                'stores_id'     => $s['stores_id'],
                                'attribute_id'  => $s['attribute_id'],
                                'serial'        => $s['serial'],
                                'business_type' => '3',
                                'store_status'  => '2',
                                'remarks'       => $it['remark'],
                                'purchase_id'   => $it['info_id'],
                                'customer_id'   => $supplierId ? $supplierId : 0,
                                'purchase_code' => $it['exchange_code'],
                                'purchase_time' => $it['exchange_time'],
                                'createtime'    => time(),
                                'updatetime'    => time(),
                            ]);
                        }
                        Db::name('kuerp_goods_serial_log')->insertAll($serialLogs);
                        Db::name('kuerp_goods_serial')
                            ->where(['purchase_exchange_id' => $it['info_id']])
                            ->update(['store_status' => '2']);
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

            // 7) 本次出库操作记录
            $exchangeCodes = array_values(array_unique(array_column($items, 'exchange_code')));
            Db::name('kuerp_goods_return_outbound_log')->insert([
                'purchase_id'    => $purchaseId,
                'purchase_code'  => $purchaseCode,
                'exchange_codes' => implode(',', $exchangeCodes),
                'total_number'   => round($totalNumber, 4),
                'total_basics'   => round($totalBasics, 4),
                'detail'         => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'admin_id'       => $adminId,
                'createtime'     => time(),
            ]);

            // 8) 采购单更新时间
            Db::name('kuerp_goods_purchase')->where(['id' => $purchaseId])->update(['updatetime' => time()]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new \Exception($e->getMessage());
        }

        return [
            'total_number' => round($totalNumber, 4),
            'total_basics' => round($totalBasics, 4),
            //剩余待出库明细行数（0 = 该采购单的退货单已全部出库，弹窗可直接关闭）
            'remaining'    => count(self::pendingLines($purchaseId, $purchaseCode)),
        ];
    }

    /**
     * 某采购单的退货出库操作记录（最新在前）
     *
     * @param int $purchaseId
     * @return array
     */
    public static function logs($purchaseId)
    {
        $logList = Db::name('kuerp_goods_return_outbound_log')
            ->where(['purchase_id' => (int)$purchaseId])
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
