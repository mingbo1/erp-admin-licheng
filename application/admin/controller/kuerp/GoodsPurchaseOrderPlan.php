<?php

namespace app\admin\controller\kuerp;

use app\admin\model\kuerp\GoodsSalesOrderLog;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;

/**
 * 计划购货订单
 * 继承自购货订单，供技术部使用 - 不显示金额、不显示审核相关操作按钮
 * 与购货订单共用 fa_kuerp_goods_purchase_order 表和同一 Model
 *
 * @icon fa fa-calendar-check-o
 */
class GoodsPurchaseOrderPlan extends GoodsPurchaseOrder
{
    protected $noNeedRight = ['checkexchange','checkpurchase','checksuppliercount','batchgeneratepurchase','generatebysupplier'];

    /**
     * GoodsPurchaseOrder模型对象
     * @var \app\admin\model\kuerp\GoodsPurchaseOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 单据来源标记：计划购货订单 = plan（仓库子类改为 warehouse）
     * @return string
     */
    protected function orderSource()
    {
        return 'plan';
    }

    /**
     * 列表来源过滤：计划购货订单只显示计划部门录入的单据
     * @return array|null
     */
    protected function orderSourceListFilter()
    {
        return ['plan'];
    }

    /**
     * 单据名称（用于提示文案，仓库子类覆盖）
     * @return string
     */
    protected function docTitle()
    {
        return '计划购货订单';
    }

    /**
     * 校验单据是否属于当前部门（编辑/删除前的隔离守卫）
     * @param array $row
     */
    protected function checkOrderSource($row)
    {
        $filter = $this->orderSourceListFilter();
        if ($filter !== null && !in_array($row['order_source'], (array)$filter)) {
            $this->error('单据[' . $row['purchase_code'] . ']不属于当前部门，不能操作');
        }
    }

    /**
     * 计划购货订单不支持手动审核操作
     */
    public function examine($ids = null, $examine = 1)
    {
        $examine_set = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');
        // 程序内部调用（add/edit中的自动审核）直接返回成功
        if ($examine_set != 1) {
            return ['status' => 1, 'msg' => '操作成功'];
        }
        // 手动调用 - 拒绝
        $this->error($this->docTitle() . '不支持审核操作');
    }

    /**
     * 计划购货订单不支持二次审核操作
     */
    public function secondExamine($ids = null)
    {
        $this->error($this->docTitle() . '不支持二次审核操作');
    }

    /**
     * 计划购货订单添加 - 不自动审核
     */
    public function add($type = '')
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }

                $params = $this->preExcludeFields($params);
                $this->checkFormTruncated($params);
                $admin = Session::get('admin');

                if (empty($params['purchase_code'])) {
                    $this->error('请填写单据编号');
                }

                $data = [];
                $number = 0;
                $all_money = 0;
                $tax_money = 0;
                $rowSupplierCodes = [];
                foreach ($params['goods_code'] as $k => $v) {
                    if (!empty($v)) {
                        $number += $params['number'][$k];
                        $all_money += $params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        if (!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])) {
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code' => $v])->find();
                        if (empty($goods)) {
                            $this->error('商品不存在');
                        } elseif ($goods['auxiliary'] == 1 && empty($params['goods_attr'][$k])) {
                            $this->error('请选择商品属性');
                        }

                        // 行级供应商
                        $lineSupplier = isset($params['row_supplyer_code'][$k]) ? $params['row_supplyer_code'][$k] : '';
                        if (empty($lineSupplier)) {
                            $lineSupplier = $params['supplyer_code'] ?? '';
                        }
                        $rowSupplierCodes[] = $lineSupplier;

                        $data[] = [
                            'purchase_code'     => $params['purchase_code'],
                            'purchase_type'     => $params['purchase_type'],
                            'purchase_time'     => strtotime($params['purchase_time']),
                            'delivery_time'     => !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                            'supplyer_code'     => $lineSupplier,
                            'goods_code'        => $v,
                            'goods_note'        => $params['goods_note'][$k],
                            'goods_unit'        => (int)$params['goods_unit'][$k],
                            'stores_id'         => (int)$params['stores_id'][$k],
                            'allow_stock'       => (float)$params['allow_stock'][$k],
                            'number'            => (float)$params['number'][$k],
                            'purchase_number'   => (float)$params['number'][$k],
                            'default_unit'      => (float)$params['default_unit'][$k],
                            'basic_nmber'       => (float)$params['basic_nmber'][$k],
                            'unit_price'        => (float)$params['unit_price'][$k],
                            'discount'          => (float)$params['discount'][$k],
                            'dis_money'         => (float)$params['dis_money'][$k],
                            'all_money'         => (float)$params['all_money'][$k],
                            'remark'            => $params['remark'][$k],
                            'sales_order_id'    => $params['sales_order_id'][$k],
                            'preferential_rate' => (float)$params['preferential_rate'],
                            'preferential_money'=> (float)$params['preferential_money'],
                            'real_money'        => (float)$params['real_money'],
                            'goods_attr'        => (float)$params['goods_attr'][$k],
                            'tax_included'      => (float)$params['tax_included'][$k],
                            'tax_rate'          => (float)$params['tax_rate'][$k],
                            'tax_money'         => (float)$params['tax_money'][$k],
                            'money_tax_price'   => (float)$params['money_tax_price'][$k],
                            'preparation_id'    => $admin['id'],
                            'operation_id'      => $admin['id'],
                            'library_number'    => (float)$params['number'][$k],
                            'library_money'     => (float)$params['all_money'][$k],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ];
                    }
                }
                if (!empty($data)) {
                    $mainSalesOrderId = '';
                    foreach ($data as $d) {
                        if (!empty($d['sales_order_id'])) {
                            $mainSalesOrderId = $d['sales_order_id'];
                            break;
                        }
                    }
                    $uniqueSuppliers = array_unique(array_filter($rowSupplierCodes));
                    $headerSupplierCode = count($uniqueSuppliers) === 1 ? reset($uniqueSuppliers) : '';
                    $row = [
                        'purchase_code'     => $params['purchase_code'],
                        'purchase_type'     => $params['purchase_type'],
                        'order_source'      => $this->orderSource(),
                        'purchase_time'     => strtotime($params['purchase_time']),
                        'delivery_time'     => !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                        'supplyer_code'     => $headerSupplierCode,
                        'supplier_count'    => count($uniqueSuppliers),
                        'sales_order_id'    => $mainSalesOrderId,
                        'number'            => $number,
                        'all_money'         => $all_money,
                        'tax_money'         => $tax_money,
                        'preferential_rate' => (float)$params['preferential_rate'],
                        'preferential_money'=> (float)$params['preferential_money'],
                        'real_money'        => (float)$params['real_money'],
                        'note'              => $params['note'],
                        'preparation_id'    => $admin['id'],
                        'operation_id'      => $admin['id'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }

                if ($this->model->where(['purchase_code' => trim($params['purchase_code'])])->find()) {
                    $this->error('单据编号已占用');
                }

                // 附件记录
                $enclosureLog = [];
                if (isset($params['enclosure']) && !empty($params['enclosure'])) {
                    $enclosure = explode(',', $params['enclosure']);
                    $enclosurelist = Db::name('kuerp_enclosure')->where(['id' => ['in', $params['enclosure']]])->select();
                    if (sizeof($enclosurelist) != sizeof($enclosure)) {
                        $this->error('选择的原始单据有误，请重新选择');
                    } else {
                        foreach ($enclosure as $k => $v) {
                            $enclosureLog[] = [
                                'enclosure_id'  => $v,
                                'purchase_code' => $params['purchase_code'],
                                'type'          => '1',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }

                $result = false;
                Db::startTrans();
                try {
                    if (!empty($row)) {
                        $result = $this->model->insertGetId($row);
                    }

                    if (!empty($enclosureLog)) {
                        foreach ($enclosureLog as $enclosureLog_k => $enclosureLog_v) {
                            $enclosureLog[$enclosureLog_k]['order_id'] = $result;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }

                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code' => $params['purchase_code']])->delete();
                    Db::name('kuerp_goods_purchase_order_info')->insertAll($data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    // 写入销货订单日志
                    if (!empty($mainSalesOrderId)) {
                        GoodsSalesOrderLog::writeLogByCode($mainSalesOrderId, '生成' . $this->docTitle(), $this->docTitle() . '：' . $params['purchase_code'], 'purchase_order', $result);
                    }
                    // 计划购货订单不自动审核
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $code = \addons\kuerp\library\Goods::getCodes('', 7);
        $this->view->assign("code", $code);

        $GoodsPurchaseOrder = [];
        if ($type == 1) {
            $GoodsPurchaseOrder = Session::get('GoodsPurchaseOrder');
            if ($GoodsPurchaseOrder) {
                $GoodsPurchaseOrder = unserialize($GoodsPurchaseOrder);
                foreach ($GoodsPurchaseOrder as $GoodsPurchaseOrder_k => $GoodsPurchaseOrder_v) {
                    $GoodsPurchaseOrder[$GoodsPurchaseOrder_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($GoodsPurchaseOrder_v['goods_code']), true);
                    $GoodsPurchaseOrder[$GoodsPurchaseOrder_k]['goods'] = Db::name('kuerp_goods')->where(['goods_code' => $GoodsPurchaseOrder_v['goods_code']])->find();
                }
            }
        }

        // BOM预填（从销货排期生成）
        $scheduleBomGoods = [];
        $scheduleSalesOrderId = '';
        $schedule_id = $this->request->param('schedule_id');
        if (!empty($schedule_id)) {
            $schedule = Db::name('kuerp_goods_sales')->where(['id' => $schedule_id, 'purchase_type' => '1'])->find();
            if ($schedule) {
                $salesOrderCode = $schedule['purchase_order_id'];
                $scheduleInfo = Db::name('kuerp_goods_sales_info')
                    ->where(['purchase_code' => $schedule['purchase_code']])
                    ->select();

                $numberDecimal = 2;
                $setData = Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
                if ($setData) $numberDecimal = (int)$setData;

                $partMap = [];
                $noBomMap = []; // 无BOM模板的排期商品（成品/半成品整件采购）：自身缺货检测
                foreach ($scheduleInfo as $info) {
                    $goodsCode = $info['goods_code'];
                    $basicNmber = (float)$info['basic_nmber'];
                    $storesId = (int)$info['stores_id'];

                    $goods = Db::name('kuerp_goods')->where(['goods_code' => $goodsCode])->field('id, goods_name, unit_id')->find();
                    if (empty($goods)) continue;
                    $goodsId = $goods['id'];

                    $bomList = Db::name('kuerp_goods_bom')->where(['goods_id' => $goodsId])->select();
                    if (empty($bomList)) {
                        // 无BOM则无法组装，只能整件采购：记录自身需求，后面按库存缺口生成采购明细
                        if (!isset($noBomMap[$goodsId])) {
                            $noBomMap[$goodsId] = [
                                'goods_code' => $goodsCode,
                                'goods_name' => $goods['goods_name'],
                                'number' => 0,
                                'stores_id' => $storesId,
                                'unit_id' => $goods['unit_id'],
                            ];
                        }
                        $noBomMap[$goodsId]['number'] += $basicNmber;
                        continue;
                    }

                    foreach ($bomList as $bom) {
                        // 子件检测扩展到零件+半成品+成品
                        $children = Db::name('kuerp_goods_bom_info')->alias('a')
                            ->join('kuerp_goods g', 'g.id=a.goods_id')
                            ->where(['a.bom_id' => $bom['id'], 'a.type' => '2', 'g.product_type' => ['in', ['part', 'semi_finished', 'finished']]])
                            ->field('a.goods_id, a.goods_code, a.number as bom_number, g.goods_name, g.unit_id')
                            ->select();

                        foreach ($children as $child) {
                            $childGoodsId = $child['goods_id'];
                            $requiredQty = (float)$child['bom_number'] * $basicNmber;

                            if (!isset($partMap[$childGoodsId])) {
                                $partMap[$childGoodsId] = [
                                    'goods_code' => $child['goods_code'],
                                    'goods_name' => $child['goods_name'],
                                    'number' => 0,
                                    'stores_id' => $storesId,
                                    'unit_id' => $child['unit_id'],
                                ];
                            }
                            $partMap[$childGoodsId]['number'] += $requiredQty;
                        }
                    }
                }

                if (!empty($partMap)) {
                    $partGoodsIds = array_keys($partMap);
                    $partStoreIds = Db::name('kuerp_stores')->where(['store_type' => 'part'])->column('id');
                    $stockMap = []; // 零件现有库存映射 goods_id => [stores_id => stock]
                    if (!empty($partStoreIds)) {
                        $stockData = Db::name('kuerp_goods_stock')
                            ->where(['goods_id' => ['in', $partGoodsIds]])
                            ->where(['stores_id' => ['in', $partStoreIds]])
                            ->field('goods_id, stores_id, stock')
                            ->select();
                        $stockMap = [];
                        foreach ($stockData as $sd) {
                            $gid = $sd['goods_id'];
                            if (!isset($stockMap[$gid])) $stockMap[$gid] = [];
                            $stockMap[$gid][$sd['stores_id']] = (float)$sd['stock'];
                        }
                        foreach ($partMap as $goodsId => &$p) {
                            if (isset($stockMap[$goodsId]) && !empty($stockMap[$goodsId])) {
                                arsort($stockMap[$goodsId]);
                                $bestKeys = array_keys($stockMap[$goodsId]);
                                $p['stores_id'] = reset($bestKeys);
                            } elseif (!empty($partStoreIds)) {
                                $p['stores_id'] = (int)reset($partStoreIds);
                            }
                        }
                        unset($p);
                    }
                }

                $partGoodsCodes = array_unique(array_merge(array_column($partMap, 'goods_code'), array_column($noBomMap, 'goods_code')));
                $goodsSupplierMap = [];
                if (!empty($partGoodsCodes)) {
                    $goodsData = Db::name('kuerp_goods')
                        ->where(['goods_code' => ['in', $partGoodsCodes]])
                        ->field('goods_code, supplier_id')
                        ->select();
                    $supplierIds = array_unique(array_filter(array_column($goodsData, 'supplier_id')));
                    $supplierCodeMap = [];
                    if (!empty($supplierIds)) {
                        $suppliers = Db::name('kuerp_suppliers')
                            ->where(['id' => ['in', $supplierIds]])
                            ->field('id, supplyer_code')
                            ->select();
                        foreach ($suppliers as $s) {
                            $supplierCodeMap[$s['id']] = $s['supplyer_code'];
                        }
                    }
                    foreach ($goodsData as $gd) {
                        if (!empty($gd['supplier_id']) && isset($supplierCodeMap[$gd['supplier_id']])) {
                            $goodsSupplierMap[$gd['goods_code']] = $supplierCodeMap[$gd['supplier_id']];
                        }
                    }
                }

                foreach ($partMap as $goodsId => $p) {
                    // 计算缺口数量：需求 - 各零件仓库现有库存之和，库存足够则无需采购
                    $totalStock = 0;
                    if (isset($stockMap[$goodsId])) {
                        foreach ($stockMap[$goodsId] as $stk) {
                            $totalStock += $stk;
                        }
                    }
                    $shortage = round($p['number'], $numberDecimal) - round($totalStock, $numberDecimal);
                    if ($shortage <= 0) {
                        continue; // 现有库存已满足需求，不生成采购明细
                    }
                    $scheduleBomGoods[] = [
                        'goods_code' => $p['goods_code'],
                        'goods_name' => $p['goods_name'],
                        'number' => round($shortage, $numberDecimal),
                        'stores_id' => $p['stores_id'],
                        'unit_id' => $p['unit_id'],
                        'supplyer_code' => isset($goodsSupplierMap[$p['goods_code']]) ? $goodsSupplierMap[$p['goods_code']] : '',
                    ];
                }

                // 无BOM模板的排期商品：自身库存缺口（全部仓库合计）生成整件采购明细
                if (!empty($noBomMap)) {
                    $noBomIds = array_keys($noBomMap);
                    $selfStockData = Db::name('kuerp_goods_stock')
                        ->where(['goods_id' => ['in', $noBomIds]])
                        ->field('goods_id, stock')
                        ->select();
                    $selfStockMap = [];
                    foreach ($selfStockData as $sd) {
                        $sgid = $sd['goods_id'];
                        if (!isset($selfStockMap[$sgid])) $selfStockMap[$sgid] = 0;
                        $selfStockMap[$sgid] += (float)$sd['stock'];
                    }
                    foreach ($noBomMap as $gid => $p) {
                        $totalStock = isset($selfStockMap[$gid]) ? $selfStockMap[$gid] : 0;
                        $shortage = round($p['number'], $numberDecimal) - round($totalStock, $numberDecimal);
                        if ($shortage <= 0) {
                            continue; // 现有库存已满足需求，不生成采购明细
                        }
                        $scheduleBomGoods[] = [
                            'goods_code' => $p['goods_code'],
                            'goods_name' => $p['goods_name'],
                            'number' => round($shortage, $numberDecimal),
                            'stores_id' => $p['stores_id'],
                            'unit_id' => $p['unit_id'],
                            'supplyer_code' => isset($goodsSupplierMap[$p['goods_code']]) ? $goodsSupplierMap[$p['goods_code']] : '',
                        ];
                    }
                }

                if (!empty($salesOrderCode)) {
                    $scheduleSalesOrderId = $salesOrderCode;
                }
            }
        }

        $last_customer = Db::name('kuerp_sets')->where(['name' => 'customer'])->value('is_checked');
        $set_customer = '';
        if ($last_customer) {
            $set_customer = Db::name('kuerp_goods_purchase_order')->order('id', 'DESC')->value('supplyer_code');
        }
        $this->view->assign("set_customer", $set_customer);
        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code' => $set_customer])->field('supplyer_code,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);

        $this->view->assign("list", $GoodsPurchaseOrder);
        $this->assignconfig("list", $GoodsPurchaseOrder);

        $this->view->assign("scheduleBomGoods", $scheduleBomGoods);
        $this->assignconfig("scheduleBomGoods", $scheduleBomGoods);
        $this->view->assign("scheduleSalesOrderId", $scheduleSalesOrderId);
        $this->assignconfig("scheduleSalesOrderId", $scheduleSalesOrderId);

        return $this->view->fetch();
    }

    /**
     * 计划购货订单编辑 - 不自动审核
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 部门隔离：只能编辑本部门录入的预订单
        $this->checkOrderSource($row);
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
            if ($checkout !== true) {
                $this->error($checkout);
            }
            if ($row['purchase_type'] == 1) {
                $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id' => $ids])->column('purchase_id');
                if (!empty($purchase_id)) {
                    $this->error('不能编辑已生成采购单的数据');
                }
            } else {
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id' => $ids])->column('exchange_id');
                if (!empty($purchase_id)) {
                    $this->error('不能编辑已生成退货单的数据');
                }
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $this->checkFormTruncated($params);

                if (empty($params['purchase_code'])) {
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');

                $data = [];
                $number = 0;
                $all_money = 0;
                $tax_money = 0;
                $rowSupplierCodes = [];
                foreach ($params['goods_code'] as $k => $v) {
                    if (!empty($v)) {
                        $number += $params['number'][$k];
                        $all_money += $params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];

                        if (!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])) {
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code' => $v])->find();
                        if (empty($goods)) {
                            $this->error('商品不存在');
                        } elseif ($goods['auxiliary'] == 1 && empty($params['goods_attr'][$k])) {
                            $this->error('请选择商品属性');
                        }

                        $lineSupplier = isset($params['row_supplyer_code'][$k]) ? $params['row_supplyer_code'][$k] : '';
                        if (empty($lineSupplier)) {
                            $lineSupplier = $params['supplyer_code'] ?? '';
                        }
                        $rowSupplierCodes[] = $lineSupplier;

                        $data[] = [
                            'purchase_code'     => $params['purchase_code'],
                            'purchase_type'     => $params['purchase_type'],
                            'purchase_time'     => strtotime($params['purchase_time']),
                            'delivery_time'     => !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                            'supplyer_code'     => $lineSupplier,
                            'goods_code'        => $v,
                            'goods_note'        => $params['goods_note'][$k],
                            'goods_unit'        => (float)$params['goods_unit'][$k],
                            'stores_id'         => (float)$params['stores_id'][$k],
                            'allow_stock'       => (float)$params['allow_stock'][$k],
                            'number'            => (float)$params['number'][$k],
                            'purchase_number'   => (float)$params['number'][$k],
                            'default_unit'      => (float)$params['default_unit'][$k],
                            'basic_nmber'       => (float)$params['basic_nmber'][$k],
                            'unit_price'        => (float)$params['unit_price'][$k],
                            'discount'          => (float)$params['discount'][$k],
                            'dis_money'         => (float)$params['dis_money'][$k],
                            'all_money'         => (float)$params['all_money'][$k],
                            'remark'            => $params['remark'][$k],
                            'library_number'    => (float)$params['number'][$k],
                            'library_money'     => (float)$params['all_money'][$k],
                            'preferential_rate' => (float)$params['preferential_rate'],
                            'preferential_money'=> (float)$params['preferential_money'],
                            'real_money'        => (float)$params['real_money'],
                            'goods_attr'        => (float)$params['goods_attr'][$k],
                            'tax_included'      => (float)$params['tax_included'][$k],
                            'tax_rate'          => (float)$params['tax_rate'][$k],
                            'tax_money'         => (float)$params['tax_money'][$k],
                            'money_tax_price'   => (float)$params['money_tax_price'][$k],
                            'preparation_id'    => $admin['id'],
                            'operation_id'      => $admin['id'],
                            'sales_order_id'    => $params['sales_order_id'][$k],
                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                        ];
                    }
                }
                if (!empty($data)) {
                    $mainSalesOrderId = '';
                    foreach ($data as $d) {
                        if (!empty($d['sales_order_id'])) {
                            $mainSalesOrderId = $d['sales_order_id'];
                            break;
                        }
                    }
                    $uniqueSuppliers = array_unique(array_filter($rowSupplierCodes));
                    $headerSupplierCode = count($uniqueSuppliers) === 1 ? reset($uniqueSuppliers) : '';
                    $rowdata = [
                        'purchase_code'     => $params['purchase_code'],
                        'purchase_type'     => $params['purchase_type'],
                        'purchase_time'     => strtotime($params['purchase_time']),
                        'delivery_time'     => !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                        'supplyer_code'     => $headerSupplierCode,
                        'supplier_count'    => count($uniqueSuppliers),
                        'sales_order_id'    => $mainSalesOrderId,
                        'number'            => $number,
                        'all_money'         => $all_money,
                        'tax_money'         => $tax_money,
                        'preferential_rate' => $params['preferential_rate'],
                        'preferential_money'=> $params['preferential_money'],
                        'real_money'        => $params['real_money'],
                        'note'              => $params['note'],
                        'preparation_id'    => $admin['id'],
                        'operation_id'      => $admin['id'],
                        'updatetime'        => time(),
                    ];
                }

                if ($this->model->where(['purchase_code' => trim($params['purchase_code']), 'id' => ['<>', $ids]])->find()) {
                    $this->error('单据编号已占用');
                }

                // 附件记录
                $enclosureLog = [];
                if (isset($params['enclosure']) && !empty($params['enclosure'])) {
                    $enclosure = explode(',', $params['enclosure']);
                    $enclosurelist = Db::name('kuerp_enclosure')->where(['id' => ['in', $params['enclosure']]])->select();
                    if (sizeof($enclosurelist) != sizeof($enclosure)) {
                        $this->error('选择的原始单据有误，请重新选择');
                    } else {
                        foreach ($enclosure as $k => $v) {
                            $enclosureLog[] = [
                                'enclosure_id'  => $v,
                                'purchase_code' => $params['purchase_code'],
                                'type'          => '1',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }

                $result = false;
                Db::startTrans();
                try {
                    // 计划购货订单不执行反审核
                    Db::name('kuerp_enclosure_log')->where(['order_id' => $ids, 'type' => '1'])->delete();
                    if (!empty($enclosureLog)) {
                        foreach ($enclosureLog as $enclosureLog_k => $enclosureLog_v) {
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }

                    $result = $this->model->where(['id' => $ids])->update($rowdata);
                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code' => $row['purchase_code']])->delete();
                    Db::name('kuerp_goods_purchase_order_info')->insertAll($data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    // 计划购货订单不自动审核
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);

        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code' => $row['purchase_code']])->select();
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsIdMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id', 'goods_code') : [];
        $stockItems = [];
        foreach ($purchase_info as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? $goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        foreach ($purchase_info as $purchase_info_k => $purchase_info_v) {
            $purchase_info[$purchase_info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($purchase_info_v['goods_code'], $purchase_info_v['stores_id'], $purchase_info_v['goods_attr']), true);
            $gid = isset($goodsIdMap[$purchase_info_v['goods_code']]) ? $goodsIdMap[$purchase_info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$purchase_info_v['stores_id'] . '-' . (int)$purchase_info_v['goods_attr'];
            $purchase_info[$purchase_info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
        }
        $this->view->assign("purchase_info", $purchase_info);
        $this->assignconfig("purchase_id", $row['id']);

        // 供应商分组
        $supplierGroups = [];
        foreach ($purchase_info as $info) {
            $sc = !empty($info['supplyer_code']) ? $info['supplyer_code'] : '未指定供应商';
            if (!isset($supplierGroups[$sc])) {
                $supplierName = '';
                if (!empty($info['supplyer_code'])) {
                    $supplierName = Db::name('kuerp_suppliers')
                        ->where(['supplyer_code' => $info['supplyer_code']])
                        ->value('supplyer_name');
                }
                $supplierGroups[$sc] = [
                    'supplyer_code' => $info['supplyer_code'],
                    'supplyer_name' => $supplierName ?: $sc,
                    'goods_count' => 0,
                    'generated' => false,
                    'purchase_code' => '',
                    'purchase_id' => 0,
                    'total_money'  => 0,
                    'items'        => [],
                ];
            }
            $supplierGroups[$sc]['goods_count']++;
            $supplierGroups[$sc]['total_money'] += (float)($info['money_tax_price'] ?? 0);
            $supplierGroups[$sc]['items'][] = $info;

            $purordInfo = Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $info['id']])
                ->find();
            if (!empty($purordInfo)) {
                $purchaseInfo = Db::name('kuerp_goods_purchase_info')
                    ->where(['id' => $purordInfo['purchase_id']])
                    ->field('purchase_code')
                    ->find();
                if (!empty($purchaseInfo)) {
                    $supplierGroups[$sc]['generated'] = true;
                    $supplierGroups[$sc]['purchase_code'] = $purchaseInfo['purchase_code'];
                    $purchaseHeaderId = Db::name('kuerp_goods_purchase')
                        ->where(['purchase_code' => $purchaseInfo['purchase_code']])
                        ->value('id');
                    $supplierGroups[$sc]['purchase_id'] = $purchaseHeaderId ?: 0;
                }
            }
        }
        $this->view->assign("supplierGroups", $supplierGroups);
        $this->assignconfig("supplierGroups", $supplierGroups);

        // 原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id' => $ids, 'type' => '1'])->column('enclosure_id');
        if (!empty($enclosure_id)) {
            $this->assign('enclosure_count', sizeof($enclosure_id));
            $enclosure_id = implode(',', $enclosure_id);
        } else {
            $enclosure_id = '';
        }
        $this->assign('enclosure_ids', $enclosure_id);

        $this->assignconfig("examine_status", $row['examine_status']);
        $this->assignconfig("purchase_type", $row['purchase_type']);

        return $this->view->fetch();
    }

    /**
     * 计划购货订单检测可生成采购单 - 跳过审核检查
     */
    public function checkPurchase()
    {
        $order_id = isset($_POST['order_id']) ? trim($_POST['order_id']) : 0;
        if (empty($order_id)) {
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if (empty($row)) {
            $this->error('参数错误');
        }
        // 计划购货订单跳过审核/二审检查
        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code' => $row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k => $purchase_info_v) {
            $purord_info = Db::name('kuerp_goods_purord_info')->where(['order_id' => $purchase_info_v['id']])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number']) ? $purord_info[0]['number'] : 0;
            if ($purchase_info_v['number'] - $gp_number <= 0) {
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if (empty($purchase_info)) {
            $this->error('此计划购货订单已全部生成采购单');
        }
        $this->success();
    }

    /**
     * 计划购货订单检测可生成退货单 - 跳过审核检查
     */
    public function checkExchange()
    {
        $order_id = isset($_POST['order_id']) ? trim($_POST['order_id']) : 0;
        if (empty($order_id)) {
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if (empty($row)) {
            $this->error('参数错误');
        }
        // 计划购货订单跳过审核/二审检查
        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code' => $row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k => $purchase_info_v) {
            $purord_info = Db::name('kuerp_goods_excpur_info')->where(['purchase_id' => $purchase_info_v['id'], 'type' => '1'])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number']) ? $purord_info[0]['number'] : 0;
            if ($purchase_info_v['number'] - $gp_number <= 0) {
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if (empty($purchase_info)) {
            $this->error('此计划购货订单已全部生成退货单');
        }
        $this->success();
    }

    /**
     * 计划购货订单按供应商分组批量生成采购单 - 跳过审核检查
     */
    public function batchGeneratePurchase()
    {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (empty($order_id)) {
            $this->error('参数错误');
        }

        $order = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        // 部门隔离：只能对本部门录入的预订单生成采购单
        $this->checkOrderSource($order);
        // 计划购货订单跳过审核检查

        $lines = Db::name('kuerp_goods_purchase_order_info')
            ->where(['purchase_code' => $order['purchase_code']])
            ->select();

        $lines = array_filter($lines, function ($line) {
            $converted = Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $line['id']])
                ->sum('number');
            return ($line['number'] - $converted) > 0;
        });

        if (empty($lines)) {
            $this->error('此计划购货订单已全部生成采购单');
        }

        $groups = [];
        foreach ($lines as $line) {
            $sc = !empty($line['supplyer_code']) ? $line['supplyer_code'] : '未指定供应商';
            $groups[$sc][] = $line;
        }

        if (count($groups) <= 1) {
            $this->success('', ['redirect' => true, 'url' => "kuerp/goods_purchase/add?ids={$order_id}"]);
            return;
        }

        $results = [];
        Db::startTrans();
        try {
            foreach ($groups as $supplierCode => $items) {
                $purchaseCode = \addons\kuerp\library\Goods::getCodes('', 8);

                $totalNumber = 0;
                $totalAllMoney = 0;
                $totalTaxMoney = 0;
                $totalMoneyTaxPrice = 0;

                $detailData = [];
                foreach ($items as $item) {
                    $remaining = $item['number'] - Db::name('kuerp_goods_purord_info')
                        ->where(['order_id' => $item['id']])
                        ->sum('number');
                    if ($remaining <= 0) continue;

                    $totalNumber += $remaining;
                    $lineAllMoney = round($remaining * (float)$item['unit_price'], 2);
                    $totalAllMoney += $lineAllMoney;
                    $lineTaxMoney = round($remaining * (float)$item['tax_money'], 2);
                    $totalTaxMoney += $lineTaxMoney;
                    $lineMoneyTaxPrice = round($remaining * (float)$item['tax_included'], 2);
                    $totalMoneyTaxPrice += $lineMoneyTaxPrice;

                    $detailData[] = [
                        'purchase_code'     => $purchaseCode,
                        'supplyer_code'     => $supplierCode,
                        'goods_code'        => $item['goods_code'],
                        'goods_unit'        => $item['goods_unit'],
                        'stores_id'         => $item['stores_id'],
                        'number'            => $remaining,
                        'default_unit'      => $item['default_unit'],
                        'basic_nmber'       => $item['basic_nmber'],
                        'unit_price'        => $item['unit_price'],
                        'tax_included'      => $item['tax_included'],
                        'discount'          => $item['discount'],
                        'dis_money'         => $item['dis_money'],
                        'all_money'         => $lineAllMoney,
                        'tax_rate'          => $item['tax_rate'],
                        'tax_money'         => $lineTaxMoney,
                        'money_tax_price'   => $lineMoneyTaxPrice,
                        'preferential_rate' => $item['preferential_rate'],
                        'preferential_money'=> $item['preferential_money'],
                        'order_info_id'     => $item['id'],
                        'purchase_order_id' => $order['purchase_code'],
                        'sales_order_id'    => $item['sales_order_id'],
                        'goods_attr'        => $item['goods_attr'],
                        'goods_note'        => $item['goods_note'],
                        'allow_stock'       => $item['allow_stock'],
                        'purchase_time'     => $item['purchase_time'],
                        'delivery_time'     => $item['delivery_time'],
                        'remark'            => $item['remark'],
                        'preparation_id'    => $this->auth->id,
                        'operation_id'      => $this->auth->id,
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }

                $realSupplierCode = $supplierCode === '未指定供应商' ? '' : $supplierCode;

                $purchaseMainId = Db::name('kuerp_goods_purchase')->insertGetId([
                    'purchase_code'     => $purchaseCode,
                    'purchase_type'     => $order['purchase_type'],
                    'purchase_time'     => time(),
                    'delivery_time'     => $order['delivery_time'],
                    'supplyer_code'     => $realSupplierCode,
                    'number'            => $totalNumber,
                    'all_money'         => $totalAllMoney,
                    'tax_money'         => $totalTaxMoney,
                    'real_money'        => $totalMoneyTaxPrice,
                    'purchase_order_id' => $order['purchase_code'],
                    'sales_order_id'    => $order['sales_order_id'],
                    'preparation_id'    => $this->auth->id,
                    'operation_id'      => $this->auth->id,
                    'pay_status'        => '0',
                    'examine_status'    => '0',
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);

                foreach ($detailData as $dv) {
                    $orderInfoId = $dv['order_info_id'];
                    unset($dv['order_info_id']);
                    $detailId = Db::name('kuerp_goods_purchase_info')->insertGetId($dv);
                    if (!empty($orderInfoId)) {
                        Db::name('kuerp_goods_purord_info')->insert([
                            'order_id'        => $orderInfoId,
                            'purchase_id'     => $detailId,
                            'number'          => $dv['number'],
                            'all_money'       => $dv['all_money'],
                            'tax_rate'        => $dv['tax_rate'],
                            'tax_money'       => $dv['tax_money'],
                            'money_tax_price' => $dv['money_tax_price'],
                            'status'          => '0',
                        ]);
                    }
                }

                Db::name('kuerp_goods_purord')->insert([
                    'order_id'    => $order_id,
                    'purchase_id' => $purchaseMainId,
                ]);

                if (!empty($order['sales_order_id'])) {
                    \app\admin\model\kuerp\GoodsSalesOrderLog::writeLogByCode(
                        $order['sales_order_id'],
                        '生成采购单',
                        "生成采购单：{$purchaseCode}（供应商：{$realSupplierCode}）",
                        'purchase',
                        $purchaseMainId
                    );
                }

                $results[] = [
                    'purchase_code' => $purchaseCode,
                    'supplier_code' => $realSupplierCode,
                    'item_count'    => count($detailData),
                ];
            }

            Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->update([
                'supplier_count' => count($groups),
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('生成采购单失败：' . $e->getMessage());
        }

        $this->success('成功生成' . count($results) . '个采购单', ['results' => $results]);
    }

    /**
     * 计划购货订单按单个供应商生成采购单 - 跳过审核检查
     */
    public function generateBySupplier()
    {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $supplyer_code = isset($_POST['supplyer_code']) ? trim($_POST['supplyer_code']) : '';

        if (empty($order_id)) {
            $this->error('参数错误');
        }

        $order = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        // 部门隔离：只能对本部门录入的预订单生成采购单
        $this->checkOrderSource($order);
        // 计划购货订单跳过审核检查

        $lines = Db::name('kuerp_goods_purchase_order_info')
            ->where(['purchase_code' => $order['purchase_code']])
            ->where(function ($query) use ($supplyer_code) {
                if (!empty($supplyer_code)) {
                    $query->where(['supplyer_code' => $supplyer_code]);
                } else {
                    $query->where('supplyer_code', '=', '')->whereOr('supplyer_code', 'is', null);
                }
            })
            ->select();

        if (empty($lines)) {
            $this->error('该供应商没有可生成采购单的商品');
        }

        $lines = array_filter($lines, function ($line) {
            $converted = Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $line['id']])
                ->sum('number');
            return ($line['number'] - $converted) > 0;
        });

        if (empty($lines)) {
            $this->error('该供应商的商品已全部生成采购单');
        }

        $purchaseCode = \addons\kuerp\library\Goods::getCodes('', 8);
        $totalNumber = 0;
        $totalAllMoney = 0;
        $totalTaxMoney = 0;
        $totalMoneyTaxPrice = 0;

        $detailData = [];
        foreach ($lines as $item) {
            $remaining = $item['number'] - Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $item['id']])
                ->sum('number');
            if ($remaining <= 0) continue;

            $totalNumber += $remaining;
            $lineAllMoney = round($remaining * (float)$item['unit_price'], 2);
            $totalAllMoney += $lineAllMoney;
            $lineTaxMoney = round($remaining * (float)$item['tax_money'], 2);
            $totalTaxMoney += $lineTaxMoney;
            $lineMoneyTaxPrice = round($remaining * (float)$item['tax_included'], 2);
            $totalMoneyTaxPrice += $lineMoneyTaxPrice;

            $detailData[] = [
                'purchase_code'     => $purchaseCode,
                'supplyer_code'     => $supplyer_code,
                'goods_code'        => $item['goods_code'],
                'goods_unit'        => $item['goods_unit'],
                'stores_id'         => $item['stores_id'],
                'number'            => $remaining,
                'default_unit'      => $item['default_unit'],
                'basic_nmber'       => $item['basic_nmber'],
                'unit_price'        => $item['unit_price'],
                'tax_included'      => $item['tax_included'],
                'discount'          => $item['discount'],
                'dis_money'         => $item['dis_money'],
                'all_money'         => $lineAllMoney,
                'tax_rate'          => $item['tax_rate'],
                'tax_money'         => $lineTaxMoney,
                'money_tax_price'   => $lineMoneyTaxPrice,
                'preferential_rate' => $item['preferential_rate'],
                'preferential_money'=> $item['preferential_money'],
                'order_info_id'     => $item['id'],
                'purchase_order_id' => $order['purchase_code'],
                'sales_order_id'    => $item['sales_order_id'],
                'goods_attr'        => $item['goods_attr'],
                'goods_note'        => $item['goods_note'],
                'allow_stock'       => $item['allow_stock'],
                'purchase_time'     => $item['purchase_time'],
                'delivery_time'     => $item['delivery_time'],
                'remark'            => $item['remark'],
                'preparation_id'    => $this->auth->id,
                'operation_id'      => $this->auth->id,
                'createtime'        => time(),
                'updatetime'        => time(),
            ];
        }

        if (empty($detailData)) {
            $this->error('没有可生成的数据');
        }

        Db::startTrans();
        try {
            $purchaseMainId = Db::name('kuerp_goods_purchase')->insertGetId([
                'purchase_code'     => $purchaseCode,
                'purchase_type'     => $order['purchase_type'],
                'purchase_time'     => time(),
                'delivery_time'     => $order['delivery_time'],
                'supplyer_code'     => $supplyer_code,
                'number'            => $totalNumber,
                'all_money'         => $totalAllMoney,
                'tax_money'         => $totalTaxMoney,
                'real_money'        => $totalMoneyTaxPrice,
                'purchase_order_id' => $order['purchase_code'],
                'sales_order_id'    => $order['sales_order_id'],
                'preparation_id'    => $this->auth->id,
                'operation_id'      => $this->auth->id,
                'pay_status'        => '0',
                'examine_status'    => '0',
                'createtime'        => time(),
                'updatetime'        => time(),
            ]);

            foreach ($detailData as $dv) {
                $orderInfoId = $dv['order_info_id'];
                unset($dv['order_info_id']);
                $detailId = Db::name('kuerp_goods_purchase_info')->insertGetId($dv);
                if (!empty($orderInfoId)) {
                    Db::name('kuerp_goods_purord_info')->insert([
                        'order_id'        => $orderInfoId,
                        'purchase_id'     => $detailId,
                        'number'          => $dv['number'],
                        'all_money'       => $dv['all_money'],
                        'tax_rate'        => $dv['tax_rate'],
                        'tax_money'       => $dv['tax_money'],
                        'money_tax_price' => $dv['money_tax_price'],
                        'status'          => '0',
                    ]);
                }
            }

            Db::name('kuerp_goods_purord')->insert([
                'order_id'    => $order_id,
                'purchase_id' => $purchaseMainId,
            ]);

            $allSuppliers = Db::name('kuerp_goods_purchase_order_info')
                ->where(['purchase_code' => $order['purchase_code']])
                ->where('supplyer_code', '<>', '')
                ->distinct('supplyer_code')
                ->column('supplyer_code');
            Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->update([
                'supplier_count' => count($allSuppliers) ?: 1,
            ]);

            if (!empty($order['sales_order_id'])) {
                \app\admin\model\kuerp\GoodsSalesOrderLog::writeLogByCode(
                    $order['sales_order_id'],
                    '生成采购单',
                    "生成采购单：{$purchaseCode}（供应商：{$supplyer_code}）",
                    'purchase',
                    $purchaseMainId
                );
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('生成采购单失败：' . $e->getMessage());
        }

        $this->success('成功生成采购单：' . $purchaseCode);
    }
}
