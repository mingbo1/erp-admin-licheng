<?php

namespace app\admin\controller\kuerp;

use app\admin\model\kuerp\GoodsSalesOrderLog;
use think\Db;
use think\Session;

/**
 * 采购单入库
 *
 * 基于采购单(GoodsPurchase)，复用同一数据库表 fa_kuerp_goods_purchase。
 * 主要供仓库员使用，用于入库审核和对单操作，前端页面不显示金额相关字段。
 *
 * @icon fa fa-industry
 */
class GoodsPurchaseStorage extends GoodsPurchase
{
    protected $noNeedRight = ['checkpurchase','getsuppliers','getunit','formatinfo','formatinfo2','getbranch','gettime','printpage','detail','printstorage','storage','uploadenclosure','delenclosure','enclosurepicker','enclosurelist'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 列表页 - 隐藏金额相关字段
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            // 排序：同一采购订单(GHDD)的采购单排在一起，分组按采购订单创建时间倒序；
            // 没有关联采购订单的采购单按自身创建时间参与排序（规则见模型方法）
            $mainAlias = \think\Loader::parseName(basename(str_replace('\\', '/', get_class($this->model))));
            $list = $this->model
                ->with(['suppliers','preparation','examine','secondExamine'])
                ->where($where)
                ->orderRaw(\app\admin\model\kuerp\GoodsPurchase::orderSqlByPurchaseOrder($mainAlias))
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('suppliers')->visible(['supplyer_code','supplyer_name']);
                $row->getRelation('preparation')->visible(['nickname']);
                $row->getRelation('examine')->visible(['nickname']);
                if ($row->getRelation('secondExamine')) {
                    $row->getRelation('secondExamine')->visible(['nickname']);
                }
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                $purchase = Db::name('kuerp_goods_exchange')->where(['id'=>['in',$purchase_id]])->column('purchase_code');
                $list[$list_k]['returns_order_id'] = implode(',',$purchase);
                // 隐藏金额相关字段（交货日期供仓库查看，不隐藏）
                $list[$list_k]->hidden([
                    'all_money','real_money','tax_money','preferential_rate',
                    'preferential_money','pay_money','arrears','all_arrears',
                    'purchase_money','account','cost_data'
                ]);
            }
            //入库状态：未入库/部分入库/已入库（退货单不适用，返回空串由前端显示 —）
            $storageStatusMap = \app\admin\model\kuerp\GoodsPurchase::storageStatusMap(array_column($list->items(), 'purchase_code'));
            foreach ($list as $list_k=>$list_v){
                if($list_v['purchase_type']!=1){
                    $list[$list_k]['storage_status'] = '';
                    continue;
                }
                $list[$list_k]['storage_status'] = isset($storageStatusMap[$list_v['purchase_code']]) ? $storageStatusMap[$list_v['purchase_code']] : 0;
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 查看详情（不显示金额）
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 供应商名称
        $supplyer_name = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->value('supplyer_name');
        $row['supplyer_name'] = $supplyer_name ? $supplyer_name : '';
        // 制单人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname');

        // 采购单详情（含采购数量/已入库/待入库/库位）
        $purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code' => $row['purchase_code']])->select();
        // 预加载关联的订单明细
        $orderInfoIds = array_filter(array_column($purchase_info, 'order_info_id'));
        $orderInfoMap = [];
        if (!empty($orderInfoIds)) {
            $orderInfos = Db::name('kuerp_goods_purchase_order_info')->where(['id' => ['in', $orderInfoIds]])->select();
            foreach ($orderInfos as $oi) {
                $orderInfoMap[$oi['id']] = $oi;
            }
        }
        // 预加载商品信息和库位
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsMap = []; $locationMap = [];
        if (!empty($goodsCodes)) {
            $goodsList = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->field('goods_code,goods_name,location_id,goods_specification_id,vendor_name,packaging')->select();
            $locIds = [];
            foreach ($goodsList as $g) {
                $goodsMap[$g['goods_code']] = $g;
                if (!empty($g['location_id'])) $locIds[] = $g['location_id'];
            }
            if (!empty($locIds)) {
                $locIds = array_unique($locIds);
                $locList = Db::name('kuerp_goods_location')->where(['id' => ['in', $locIds], 'status' => '1'])->field('id,location_name,path')->select();
                // 收集祖先ID，批量查询后构建完整层级名（如"A-1库"）
                $allPids = [];
                foreach ($locList as $loc) {
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            if ((int)$pid > 0) $allPids[] = (int)$pid;
                        }
                    }
                }
                $ancestorMap = [];
                if ($allPids) {
                    $ancestorMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($allPids)]])->column('location_name', 'id');
                }
                $locationMap = [];
                foreach ($locList as $loc) {
                    $names = [];
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            $pid = (int)$pid;
                            if ($pid > 0 && isset($ancestorMap[$pid])) $names[] = $ancestorMap[$pid];
                        }
                    }
                    $names[] = $loc['location_name'];
                    $locationMap[$loc['id']] = implode('-', $names);
                }
            }
        }
        foreach ($purchase_info as $k => $v) {
            $goods = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : null;
            $purchase_info[$k]['goods_name']    = $goods ? $goods['goods_name'] : '';
            $purchase_info[$k]['goods_specification_id'] = $goods ? $goods['goods_specification_id'] : '';
            $purchase_info[$k]['vendor_name']           = $goods ? $goods['vendor_name'] : '';
            $purchase_info[$k]['packaging']             = $goods ? $goods['packaging'] : '';
            $purchase_info[$k]['location_name'] = ($goods && !empty($goods['location_id']) && isset($locationMap[$goods['location_id']])) ? $locationMap[$goods['location_id']] : '';
            $purchase_info[$k]['stores_name']   = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $purchase_info[$k]['unit_name']     = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
            // 采购数量/已入库/待入库
            $oi = isset($v['order_info_id'], $orderInfoMap[$v['order_info_id']]) ? $orderInfoMap[$v['order_info_id']] : null;
            $purchase_info[$k]['order_number']   = $oi ? (float)$oi['number'] : (float)$v['number'];
            $purchase_info[$k]['storage_number'] = isset($v['storage_number']) ? (float)$v['storage_number'] : 0;
            $purchase_info[$k]['remain_number']  = $purchase_info[$k]['order_number'] - $purchase_info[$k]['storage_number'];
        }
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);

        return $this->view->fetch();
    }

    /**
     * 打印入库单（不显示金额）
     */
    public function printstorage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 供应商名称
        $supplyer_name = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->value('supplyer_name');
        $row['supplyer_name'] = $supplyer_name ? $supplyer_name : '';
        // 制单人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname');

        // 采购单详情（含采购数量/已入库/待入库/库位）
        $purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code' => $row['purchase_code']])->select();
        $orderInfoIds = array_filter(array_column($purchase_info, 'order_info_id'));
        $orderInfoMap = [];
        if (!empty($orderInfoIds)) {
            $orderInfos = Db::name('kuerp_goods_purchase_order_info')->where(['id' => ['in', $orderInfoIds]])->select();
            foreach ($orderInfos as $oi) {
                $orderInfoMap[$oi['id']] = $oi;
            }
        }
        // 预加载商品信息和库位
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsMap = []; $locationMap = [];
        if (!empty($goodsCodes)) {
            $goodsList = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->field('goods_code,goods_name,location_id,goods_specification_id,vendor_name,packaging')->select();
            $locIds = [];
            foreach ($goodsList as $g) {
                $goodsMap[$g['goods_code']] = $g;
                if (!empty($g['location_id'])) $locIds[] = $g['location_id'];
            }
            if (!empty($locIds)) {
                $locIds = array_unique($locIds);
                $locList = Db::name('kuerp_goods_location')->where(['id' => ['in', $locIds], 'status' => '1'])->field('id,location_name,path')->select();
                // 收集祖先ID，批量查询后构建完整层级名（如"A-1库"）
                $allPids = [];
                foreach ($locList as $loc) {
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            if ((int)$pid > 0) $allPids[] = (int)$pid;
                        }
                    }
                }
                $ancestorMap = [];
                if ($allPids) {
                    $ancestorMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($allPids)]])->column('location_name', 'id');
                }
                $locationMap = [];
                foreach ($locList as $loc) {
                    $names = [];
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            $pid = (int)$pid;
                            if ($pid > 0 && isset($ancestorMap[$pid])) $names[] = $ancestorMap[$pid];
                        }
                    }
                    $names[] = $loc['location_name'];
                    $locationMap[$loc['id']] = implode('-', $names);
                }
            }
        }
        foreach ($purchase_info as $k => $v) {
            $goods = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : null;
            $purchase_info[$k]['goods_name']    = $goods ? $goods['goods_name'] : '';
            $purchase_info[$k]['goods_specification_id'] = $goods ? $goods['goods_specification_id'] : '';
            $purchase_info[$k]['vendor_name']           = $goods ? $goods['vendor_name'] : '';
            $purchase_info[$k]['packaging']             = $goods ? $goods['packaging'] : '';
            $purchase_info[$k]['location_name'] = ($goods && !empty($goods['location_id']) && isset($locationMap[$goods['location_id']])) ? $locationMap[$goods['location_id']] : '';
            $purchase_info[$k]['stores_name']   = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $purchase_info[$k]['unit_name']     = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
            // 采购数量/已入库/待入库
            $oi = isset($v['order_info_id'], $orderInfoMap[$v['order_info_id']]) ? $orderInfoMap[$v['order_info_id']] : null;
            $purchase_info[$k]['order_number']   = $oi ? (float)$oi['number'] : (float)$v['number'];
            $purchase_info[$k]['storage_number'] = isset($v['storage_number']) ? (float)$v['storage_number'] : 0;
            $purchase_info[$k]['remain_number']  = $purchase_info[$k]['order_number'] - $purchase_info[$k]['storage_number'];
        }
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);

        // 增加打印次数
        $this->model->where(['id' => $ids])->setInc('print_num');

        return $this->view->fetch();
    }

    /**
     * 入库操作页面
     * GET: 显示入库表单（可输入每次入库数量）
     * POST: 处理入库（审核并更新库存）
     */
    public function storage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            // 本次入库前选择的原始单据ID（逗号分隔，可空）
            $enclosureIds = $this->request->post('enclosure_ids');
            $enclosureIds = is_array($enclosureIds) ? $enclosureIds : explode(',', (string)$enclosureIds);
            $enclosureIds = array_values(array_unique(array_filter(array_map('intval', $enclosureIds))));
            if ($params) {
                $info_list = Db::name('kuerp_goods_purchase_info')
                    ->where(['purchase_code' => $row['purchase_code']])
                    ->select();
                $info_by_id = [];
                foreach ($info_list as $info) {
                    $info_by_id[$info['id']] = $info;
                }

                // 预加载关联的订单明细
                $orderInfoIds = array_filter(array_column($info_list, 'order_info_id'), function($v) {
                    return !empty($v) && $v > 0;
                });
                $orderInfoMap = [];
                if (!empty($orderInfoIds)) {
                    $orderInfos = Db::name('kuerp_goods_purchase_order_info')
                        ->where(['id' => ['in', $orderInfoIds]])
                        ->select();
                    foreach ($orderInfos as $oi) {
                        $orderInfoMap[$oi['id']] = $oi;
                    }
                }

                // 预加载 purord_info 桥接表，用于 order_info_id 为0时的回退查找
                $purordInfoMap = [];
                $needFallbackIds = [];
                foreach ($info_list as $info) {
                    if (empty($info['order_info_id']) || $info['order_info_id'] == 0) {
                        $needFallbackIds[] = $info['id'];
                    }
                }
                if (!empty($needFallbackIds)) {
                    $purordInfos = Db::name('kuerp_goods_purord_info')
                        ->where(['purchase_id' => ['in', $needFallbackIds]])
                        ->select();
                    foreach ($purordInfos as $pi) {
                        $purordInfoMap[$pi['purchase_id']] = $pi;
                    }
                    // 通过 purord_info 桥接表加载缺失的订单明细
                    $fallbackOrderIds = array_filter(array_column($purordInfos, 'order_id'));
                    if (!empty($fallbackOrderIds)) {
                        $fallbackOrderInfos = Db::name('kuerp_goods_purchase_order_info')
                            ->where(['id' => ['in', $fallbackOrderIds]])
                            ->select();
                        foreach ($fallbackOrderInfos as $foi) {
                            if (!isset($orderInfoMap[$foi['id']])) {
                                $orderInfoMap[$foi['id']] = $foi;
                            }
                        }
                    }
                }

                // 对于仍未找到订单明细的，通过订单号+商品编码模糊匹配预加载
                $stillMissing = [];
                foreach ($info_list as $info) {
                    $oi_id = $info['order_info_id'];
                    if ((empty($oi_id) || $oi_id == 0 || !isset($orderInfoMap[$oi_id]))
                        && !empty($row['purchase_order_id'])) {
                        // 检查 purord_info 桥接表是否已命中
                        $found = false;
                        if (isset($purordInfoMap[$info['id']])) {
                            $bridgeOrderId = $purordInfoMap[$info['id']]['order_id'];
                            if (isset($orderInfoMap[$bridgeOrderId])) {
                                $found = true;
                            }
                        }
                        if (!$found) {
                            $stillMissing[] = $info;
                        }
                    }
                }
                if (!empty($stillMissing)) {
                    $fallbackByCode = Db::name('kuerp_goods_purchase_order_info')
                        ->where(['purchase_code' => $row['purchase_order_id']])
                        ->select();
                    // 按 (goods_code, goods_attr) 索引
                    $fallbackByCodeMap = [];
                    foreach ($fallbackByCode as $fbc) {
                        $key = $fbc['goods_code'] . '_' . $fbc['goods_attr'];
                        $fallbackByCodeMap[$key] = $fbc;
                    }
                    foreach ($stillMissing as $info) {
                        $key = $info['goods_code'] . '_' . $info['goods_attr'];
                        if (isset($fallbackByCodeMap[$key])) {
                            $foi = $fallbackByCodeMap[$key];
                            if (!isset($orderInfoMap[$foi['id']])) {
                                $orderInfoMap[$foi['id']] = $foi;
                            }
                        }
                    }
                }

                $storage_items = []; // 记录每个商品的入库信息
                $total_store = 0;

                foreach ($params['storage_number'] as $k => $storage_input) {
                    $info_id = $params['info_id'][$k];
                    $storage_input = (float)$storage_input;

                    if ($storage_input <= 0) {
                        continue;
                    }
                    if (!isset($info_by_id[$info_id])) {
                        continue;
                    }

                    $info   = $info_by_id[$info_id];
                    $oi_id  = $info['order_info_id'];
                    $need_backfill = false;
                    $oi = null;

                    // 方法1: 优先从直接关联的 order_info_id 查找
                    if (!empty($oi_id) && $oi_id > 0 && isset($orderInfoMap[$oi_id])) {
                        $oi = $orderInfoMap[$oi_id];
                    }
                    // 方法2: 通过 purord_info 桥接表查找
                    if (!$oi && isset($purordInfoMap[$info_id])) {
                        $bridgeOrderId = $purordInfoMap[$info_id]['order_id'];
                        if (isset($orderInfoMap[$bridgeOrderId])) {
                            $oi = $orderInfoMap[$bridgeOrderId];
                            $oi_id = $bridgeOrderId;
                            $need_backfill = true;
                        }
                    }
                    // 方法3: 通过订单号+商品编码+属性反向查找（已在预加载阶段处理）
                    if (!$oi && !empty($row['purchase_order_id'])) {
                        $key = $info['goods_code'] . '_' . $info['goods_attr'];
                        foreach ($orderInfoMap as $mappedId => $mappedOi) {
                            $mappedKey = $mappedOi['goods_code'] . '_' . $mappedOi['goods_attr'];
                            if ($mappedKey === $key && $mappedOi['purchase_code'] === $row['purchase_order_id']) {
                                $oi = $mappedOi;
                                $oi_id = $mappedId;
                                $need_backfill = true;
                                break;
                            }
                        }
                    }
                    if (!$oi) {
                        $this->error('商品 ' . $info['goods_code'] . ' 未找到关联的采购订单明细');
                    }

                    // 已入库量（基本单位）
                    $stored = isset($info['storage_number']) ? (float)$info['storage_number'] : 0;
                    // 采购数量（来自订单明细）
                    $orderNum  = (float)$oi['number'];
                    // 本次入库后总入库量
                    $newStored = $stored + $storage_input;

                    if ($newStored > $orderNum) {
                        $this->error('商品 ' . $info['goods_code'] . ' 累计入库(' . $newStored . ')不能超过采购数量(' . $orderNum . ')');
                    }

                    $storage_items[] = [
                        'info_id'        => $info_id,
                        'goods_id'       => Db::name('kuerp_goods')->where(['goods_code' => $info['goods_code']])->value('id'),
                        'goods_code'     => $info['goods_code'],
                        'stores_id'      => $info['stores_id'],
                        'order_info_id'  => $oi_id,
                        'storage_input'  => $storage_input,     // 本次入库量
                        'storage_number' => $newStored,         // 累计入库量
                        'library_remain' => $orderNum - $newStored, // 剩余未入库
                        'need_backfill'  => $need_backfill,     // 是否需要回填 order_info_id
                    ];
                    $total_store += $storage_input;
                }

                if (empty($storage_items)) {
                    $this->error('入库数量不能全部为0');
                }

                // 本次入库明细（写「操作记录」用）：补上商品名称/仓库名/单位名
                $detailGoodsMap = Db::name('kuerp_goods')
                    ->where(['goods_code' => ['in', array_unique(array_column($storage_items, 'goods_code'))]])
                    ->column('goods_name', 'goods_code');
                $detailStoresMap = Db::name('kuerp_stores')
                    ->where(['id' => ['in', array_unique(array_column($storage_items, 'stores_id'))]])
                    ->column('name', 'id');
                $detailUnitMap = Db::name('kuerp_unit')->column('name', 'id');
                $logDetail = [];
                foreach ($storage_items as $item) {
                    $info = isset($info_by_id[$item['info_id']]) ? $info_by_id[$item['info_id']] : [];
                    $logDetail[] = [
                        'goods_code'  => $item['goods_code'],
                        'goods_name'  => isset($detailGoodsMap[$item['goods_code']]) ? $detailGoodsMap[$item['goods_code']] : '',
                        'number'      => $item['storage_input'],
                        'stores_name' => isset($detailStoresMap[$item['stores_id']]) ? $detailStoresMap[$item['stores_id']] : '',
                        'unit_name'   => (!empty($info['goods_unit']) && isset($detailUnitMap[$info['goods_unit']])) ? $detailUnitMap[$info['goods_unit']] : '',
                    ];
                }

                Db::startTrans();
                try {
                    // 逐商品更新库存和入库记录
                    foreach ($storage_items as $item) {
                        // 1. 更新 goods_stock 表（库存增加）
                        $stockWhere = [
                            'goods_id'  => $item['goods_id'],
                            'stores_id' => $item['stores_id'],
                        ];
                        $goodsStock = Db::name('kuerp_goods_stock')->where($stockWhere)->find();
                        if ($goodsStock) {
                            Db::name('kuerp_goods_stock')->where($stockWhere)->update([
                                'stock' => round($goodsStock['stock'] + $item['storage_input'], 4),
                            ]);
                        } else {
                            Db::name('kuerp_goods_stock')->insert([
                                'goods_id'      => $item['goods_id'],
                                'goods_code'    => $item['goods_code'],
                                'stores_id'     => $item['stores_id'],
                                'stock'         => $item['storage_input'],
                                'cost_money'    => 0,
                                'createtime'    => time(),
                                'updatetime'    => time(),
                            ]);
                        }

                        // 2. 更新 goods 表库存汇总
                        Db::name('kuerp_goods')->where(['id' => $item['goods_id']])->setInc('inventory', $item['storage_input']);

                        // 3. 更新 purchase_info 的 storage_number（累计已入库）
                        $updateData = ['storage_number' => $item['storage_number']];
                        // 如果 order_info_id 之前缺失（通过回退查找获得），回填到 purchase_info
                        if (!empty($item['need_backfill'])) {
                            $updateData['order_info_id'] = $item['order_info_id'];
                        }
                        Db::name('kuerp_goods_purchase_info')
                            ->where(['id' => $item['info_id']])
                            ->update($updateData);

                        // 4. 同步 purord_info（桥接表不存在则创建）
                        $existPurord = Db::name('kuerp_goods_purord_info')
                            ->where(['purchase_id' => $item['info_id']])
                            ->find();
                        if ($existPurord) {
                            Db::name('kuerp_goods_purord_info')
                                ->where(['purchase_id' => $item['info_id']])
                                ->update(['number' => $item['storage_number']]);
                        } elseif (!empty($item['order_info_id']) && $item['order_info_id'] > 0) {
                            // 桥接记录缺失时自动创建，确保后续流程正常
                            $info = isset($info_by_id[$item['info_id']]) ? $info_by_id[$item['info_id']] : null;
                            if ($info) {
                                Db::name('kuerp_goods_purord_info')->insert([
                                    'order_id'         => $item['order_info_id'],
                                    'purchase_id'      => $item['info_id'],
                                    'number'           => $item['storage_number'],
                                    'money'            => $info['pay_money'] ?? 0,
                                    'all_money'        => $info['all_money'] ?? 0,
                                    'tax_rate'         => $info['tax_rate'] ?? 0,
                                    'tax_money'        => $info['tax_money'] ?? 0,
                                    'money_tax_price'  => $info['money_tax_price'] ?? 0,
                                ]);
                            }
                        }

                        // 5. 更新 order_info 的 library_number（未入库数量）和 status
                        $oiStatus = ($item['library_remain'] <= 0) ? '1' : '2'; // 1=全部入库,2=部分入库
                        Db::name('kuerp_goods_purchase_order_info')
                            ->where(['id' => $item['order_info_id']])
                            ->update([
                                'library_number' => $item['library_remain'],
                                'status'         => $oiStatus,
                            ]);
                    }

                    // 6. 更新订单主表 store_number
                    if (!empty($row['purchase_order_id'])) {
                        Db::name('kuerp_goods_purchase_order')
                            ->where(['purchase_code' => $row['purchase_order_id']])
                            ->setInc('store_number', $total_store);
                    }

                    // 7. 更新采购单时间戳
                    $this->model->where(['id' => $ids])->update(['updatetime' => time()]);

                    // 8. 写入本次入库的「操作记录」（入库页底部展示，原始单据挂到这条记录上）
                    $admin = Session::get('admin');
                    $logId = Db::name('kuerp_goods_purchase_storage_log')->insertGetId([
                        'purchase_id'   => $row['id'],
                        'purchase_code' => $row['purchase_code'],
                        'total_number'  => $total_store,
                        'detail'        => json_encode($logDetail, JSON_UNESCAPED_UNICODE),
                        'admin_id'      => $admin ? (int)$admin['id'] : 0,
                        'createtime'    => time(),
                    ]);

                    // 9. 关联本次入库前选择的原始单据（type=7，order_id=入库记录ID）
                    if (!empty($enclosureIds)) {
                        $validIds = Db::name('kuerp_enclosure')
                            ->where(['id' => ['in', $enclosureIds], 'type' => 1])
                            ->column('id');
                        $enclosureLog = [];
                        foreach ($validIds as $encId) {
                            $enclosureLog[] = [
                                'enclosure_id'  => $encId,
                                'order_id'      => $logId,
                                'purchase_code' => $row['purchase_code'],
                                'type'          => '7',
                            ];
                        }
                        if (!empty($enclosureLog)) {
                            Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                        }
                    }

                    Db::commit();
                    // 写入销货订单日志：零件入库
                    if (!empty($row['purchase_order_id'])) {
                        $salesOrderCode = Db::name('kuerp_goods_purchase_order')->where(['purchase_code' => $row['purchase_order_id']])->value('sales_order_id');
                        if (!empty($salesOrderCode)) {
                            GoodsSalesOrderLog::writeLogByCode($salesOrderCode, '零件入库', '采购单入库，关联采购单：' . $row['purchase_code'] . '，本次入库' . $total_store . '件', 'purchase', $ids);
                        }
                    }
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }

                $this->success('入库成功，本次入库' . $total_store . '件', null, ['referer' => true]);
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        // GET: 显示入库表单（含采购数量/已入库/待入库）
        $supplyer_name = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->value('supplyer_name');
        $row['supplyer_name'] = $supplyer_name ? $supplyer_name : '';
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');

        $purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code' => $row['purchase_code']])->select();
        // 预加载商品信息和库位
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsMap = []; $locationMap = [];
        if (!empty($goodsCodes)) {
            $goodsList = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->field('goods_code,goods_name,location_id,goods_specification_id,vendor_name,packaging')->select();
            $locIds = [];
            foreach ($goodsList as $g) {
                $goodsMap[$g['goods_code']] = $g;
                if (!empty($g['location_id'])) $locIds[] = $g['location_id'];
            }
            if (!empty($locIds)) {
                $locIds = array_unique($locIds);
                $locList = Db::name('kuerp_goods_location')->where(['id' => ['in', $locIds], 'status' => '1'])->field('id,location_name,path')->select();
                // 收集祖先ID，批量查询后构建完整层级名（如"A-1库"）
                $allPids = [];
                foreach ($locList as $loc) {
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            if ((int)$pid > 0) $allPids[] = (int)$pid;
                        }
                    }
                }
                $ancestorMap = [];
                if ($allPids) {
                    $ancestorMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($allPids)]])->column('location_name', 'id');
                }
                $locationMap = [];
                foreach ($locList as $loc) {
                    $names = [];
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            $pid = (int)$pid;
                            if ($pid > 0 && isset($ancestorMap[$pid])) $names[] = $ancestorMap[$pid];
                        }
                    }
                    $names[] = $loc['location_name'];
                    $locationMap[$loc['id']] = implode('-', $names);
                }
            }
        }
        foreach ($purchase_info as $k => $v) {
            $goods = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : null;
            $purchase_info[$k]['goods_name']    = $goods ? $goods['goods_name'] : '';
            $purchase_info[$k]['goods_specification_id'] = $goods ? $goods['goods_specification_id'] : '';
            $purchase_info[$k]['vendor_name']           = $goods ? $goods['vendor_name'] : '';
            $purchase_info[$k]['packaging']             = $goods ? $goods['packaging'] : '';
            $purchase_info[$k]['location_name'] = ($goods && !empty($goods['location_id']) && isset($locationMap[$goods['location_id']])) ? $locationMap[$goods['location_id']] : '';
            $purchase_info[$k]['stores_name']   = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $purchase_info[$k]['unit_name']     = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');

            // 从订单明细获取原始采购数量和未入库数量
            $purchase_info[$k]['order_number']   = (float)$v['number']; // 采购数量(number 保留原值)
            $purchase_info[$k]['storage_number'] = isset($v['storage_number']) ? (float)$v['storage_number'] : 0; // 已入库
            $purchase_info[$k]['remain_number']  = $purchase_info[$k]['order_number'] - $purchase_info[$k]['storage_number']; // 待入库

            // 如果 order_info_id 存在，优先从订单明细获取采购数量
            if (!empty($v['order_info_id'])) {
                $orderInfo = Db::name('kuerp_goods_purchase_order_info')->where(['id' => $v['order_info_id']])->find();
                if ($orderInfo) {
                    $purchase_info[$k]['order_number']  = (float)$orderInfo['number'];
                    $purchase_info[$k]['remain_number'] = (float)$orderInfo['library_number']; // 未入库数量
                    // 已入库 = 采购数量 - 未入库数量
                    $purchase_info[$k]['storage_number'] = $purchase_info[$k]['order_number'] - $purchase_info[$k]['remain_number'];
                }
            }
        }

        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);
        $this->view->assign("log_list", $this->buildStorageLogs($row['id']));

        return $this->view->fetch();
    }

    /**
     * 入库弹窗里「选择原始单据」的候选列表（AJAX）
     *
     * 只返回采购侧原始单据（type=1）。放在本控制器且 $noNeedRight 内，
     * 仓库角色无需额外分配 kuerp/enclosure/* 权限即可选单据。
     *
     * 参数：
     *   ids   逗号分隔的原始单据ID（弹窗内回显已选单据时用，传了就不分页）
     *   name  文件名称模糊搜索
     *   page / limit  layui table 分页参数
     */
    public function enclosurelist()
    {
        if (!$this->request->isAjax()) {
            $this->error(__('Invalid parameters'));
        }
        $model = new \app\admin\model\kuerp\Enclosure;

        // 回显已选单据
        $ids = $this->request->request('ids');
        if (!empty($ids)) {
            $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            $rows = $ids ? $model->where(['id' => ['in', $ids], 'type' => '1'])->order('id', 'desc')->select() : [];
            foreach ($rows as $row) {
                \app\admin\model\kuerp\Enclosure::fillFileInfo($row);
            }
            return json(['total' => count($rows), 'rows' => $rows]);
        }

        $name = trim((string)$this->request->request('name', ''));
        $limit = (int)$this->request->request('limit', 10);
        if ($limit <= 0) {
            $limit = 10;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $query = $model->where('type', '1');
        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }
        $paginator = $query->order('id', 'desc')->paginate($limit);
        $rows = $paginator->items();
        foreach ($rows as $row) {
            \app\admin\model\kuerp\Enclosure::fillFileInfo($row);
        }
        return json(['total' => $paginator->total(), 'rows' => $rows]);
    }

    /**
     * 入库弹窗里「选择原始单据」的弹窗页面
     */
    public function enclosurepicker()
    {
        return $this->view->fetch();
    }

    /**
     * 组装入库「操作记录」：每次入库一条，每条后面挂该次入库的原始单据
     *
     * @param int $purchaseId 采购单ID
     * @return array
     */
    protected function buildStorageLogs($purchaseId)
    {
        $logList = Db::name('kuerp_goods_purchase_storage_log')
            ->where(['purchase_id' => $purchaseId])
            ->order('id', 'desc')
            ->select();
        if (empty($logList)) {
            return [];
        }
        // 操作人
        $adminIds = array_filter(array_unique(array_column($logList, 'admin_id')));
        $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];
        // 各次入库的原始单据（enclosure_log type=7，order_id = 入库记录ID）
        $encLogs = Db::name('kuerp_enclosure_log')
            ->where(['type' => '7', 'order_id' => ['in', array_column($logList, 'id')]])
            ->order('id', 'asc')
            ->select();
        $enclosureMap = [];
        if ($encLogs) {
            $encIds = array_filter(array_unique(array_column($encLogs, 'enclosure_id')));
            $encList = $encIds ? \app\admin\model\kuerp\Enclosure::where(['id' => ['in', $encIds]])->select() : [];
            $encById = [];
            foreach ($encList as $enc) {
                \app\admin\model\kuerp\Enclosure::fillFileInfo($enc);
                $encById[$enc['id']] = $enc;
            }
            foreach ($encLogs as $encLog) {
                if (!isset($encById[$encLog['enclosure_id']])) {
                    continue;
                }
                $enclosureMap[$encLog['order_id']][] = $encById[$encLog['enclosure_id']];
            }
        }
        foreach ($logList as $k => $log) {
            $logList[$k]['admin_name']  = isset($adminMap[$log['admin_id']]) ? $adminMap[$log['admin_id']] : '';
            $logList[$k]['enclosures']  = isset($enclosureMap[$log['id']]) ? $enclosureMap[$log['id']] : [];
            $logList[$k]['detail_list'] = $log['detail'] ? json_decode($log['detail'], true) : [];
            // 明细摘要：商品编号 商品名称 × 数量单位
            // （带编号是因为同名不同编号的商品很常见，只显示名称会看不出是哪一个）
            $texts = [];
            foreach ((array)$logList[$k]['detail_list'] as $d) {
                $label = !empty($d['goods_name']) ? $d['goods_code'] . ' ' . $d['goods_name'] : $d['goods_code'];
                $texts[] = $label . ' × ' . $d['number'] . (!empty($d['unit_name']) ? $d['unit_name'] : '');
            }
            $logList[$k]['detail_text'] = implode('、', $texts);
        }
        return $logList;
    }

    /**
     * 上传原始单据附件
     * - 传 $ids（入库记录ID）：挂在某条入库操作记录下（入库页底部「操作记录」每条记录后的 [上传原始单据]）
     * - 不传 $ids：仅创建原始单据（入库前「原始单据：… [+ 添加单据附件]」用），
     *   其 id 由前端放进 enclosure_ids，提交入库时统一关联到本次入库记录（type=7）
     *
     * @param int $ids 入库记录ID (kuerp_goods_purchase_storage_log.id)，可空
     */
    public function uploadenclosure($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $log = null;
        if (!empty($ids)) {
            $log = Db::name('kuerp_goods_purchase_storage_log')->where(['id' => $ids])->find();
            if (!$log) {
                $this->error('入库记录不存在');
            }
        }
        $file = $this->request->file('file');
        if (!$file) {
            $this->error('请选择要上传的文件');
        }
        try {
            $upload = new \app\common\library\Upload($file);
            $attachment = $upload->upload();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $fileName = $file->getInfo('name');
        $name = $fileName ? pathinfo($fileName, PATHINFO_FILENAME) : '';
        Db::startTrans();
        try {
            // 原始单据（type=1 采购侧，与采购单页面上传的附件同一张表/同一个列表）
            $enclosureId = Db::name('kuerp_enclosure')->insertGetId([
                'image'       => $attachment->url,
                'name'        => mb_substr($name, 0, 200),
                'type'        => 1,
                'status'      => 1,
                'upload_date' => date('Y-m-d'),
                'createtime'  => time(),
                'updatetime'  => time(),
            ]);
            // 关联到本次入库操作记录（type=7）；Enclosure::del() 的「已关联业务单据」守卫同样覆盖这里
            // 入库前上传的附件此时没有入库记录，先不建关联，由提交入库时的 enclosure_ids 统一挂到新记录上
            if ($log) {
                Db::name('kuerp_enclosure_log')->insert([
                    'enclosure_id'  => $enclosureId,
                    'order_id'      => $log['id'],
                    'purchase_code' => $log['purchase_code'],
                    'type'          => '7',
                ]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $enclosure = \app\admin\model\kuerp\Enclosure::get($enclosureId);
        \app\admin\model\kuerp\Enclosure::fillFileInfo($enclosure);
        $this->success('上传成功', null, [
            'id'          => $enclosureId,
            'name'        => $enclosure['name'],
            'url'         => $enclosure['url'],
            'is_image'    => $enclosure['is_image'],
            'file_suffix' => $enclosure['file_suffix'],
        ]);
    }

    /**
     * 删除某条入库操作记录下的原始单据
     *
     * @param int $ids 原始单据ID (kuerp_enclosure.id)
     */
    public function delenclosure($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $log = Db::name('kuerp_enclosure_log')->where(['enclosure_id' => $ids, 'type' => '7'])->find();
        if (!$log) {
            $this->error('该单据不属于入库操作记录，不能在此删除');
        }
        Db::startTrans();
        try {
            Db::name('kuerp_enclosure_log')->where(['id' => $log['id']])->delete();
            Db::name('kuerp_enclosure')->where(['id' => $ids])->delete();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

}
