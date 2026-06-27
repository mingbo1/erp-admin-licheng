<?php

namespace app\admin\controller\kuerp;

use think\Db;
use think\Session;

/**
 * 采购单入库
 *
 * 基于购货单(GoodsPurchase)，复用同一数据库表 fa_kuerp_goods_purchase。
 * 主要供仓库员使用，用于入库审核和对单操作，前端页面不显示金额相关字段。
 *
 * @icon fa fa-industry
 */
class GoodsPurchaseStorage extends GoodsPurchase
{
    protected $noNeedRight = ['checkpurchase','getsuppliers','getunit','formatinfo','formatinfo2','getbranch','gettime','printpage','detail','printstorage','storage'];

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
            $sort = 'purchase_time';$order='DESC';
            $list = $this->model
                ->with(['suppliers','preparation','examine','secondExamine'])
                ->where($where)
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('suppliers')->visible(['supplyer_code','supplyer_name']);
                $row->getRelation('preparation')->visible(['username']);
                $row->getRelation('examine')->visible(['username']);
                if ($row->getRelation('secondExamine')) {
                    $row->getRelation('secondExamine')->visible(['username']);
                }
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                $purchase = Db::name('kuerp_goods_exchange')->where(['id'=>['in',$purchase_id]])->column('purchase_code');
                $list[$list_k]['returns_order_id'] = implode(',',$purchase);
                // 隐藏金额相关字段
                $list[$list_k]->hidden([
                    'all_money','real_money','tax_money','preferential_rate',
                    'preferential_money','pay_money','arrears','all_arrears',
                    'purchase_money','account','cost_data','delivery_time'
                ]);
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
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('username');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('username');

        // 购货单详情（含采购数量/已入库/待入库/库位）
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
            $goodsList = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->field('goods_code,goods_name,location_id')->select();
            $locIds = [];
            foreach ($goodsList as $g) {
                $goodsMap[$g['goods_code']] = $g;
                if (!empty($g['location_id'])) $locIds[] = $g['location_id'];
            }
            if (!empty($locIds)) {
                $locationMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($locIds)], 'status' => '1'])->column('location_name', 'id');
            }
        }
        foreach ($purchase_info as $k => $v) {
            $goods = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : null;
            $purchase_info[$k]['goods_name']    = $goods ? $goods['goods_name'] : '';
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
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('username');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('username');

        // 购货单详情（含采购数量/已入库/待入库/库位）
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
            $goodsList = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->field('goods_code,goods_name,location_id')->select();
            $locIds = [];
            foreach ($goodsList as $g) {
                $goodsMap[$g['goods_code']] = $g;
                if (!empty($g['location_id'])) $locIds[] = $g['location_id'];
            }
            if (!empty($locIds)) {
                $locationMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($locIds)], 'status' => '1'])->column('location_name', 'id');
            }
        }
        foreach ($purchase_info as $k => $v) {
            $goods = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : null;
            $purchase_info[$k]['goods_name']    = $goods ? $goods['goods_name'] : '';
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
            if ($params) {
                $info_list = Db::name('kuerp_goods_purchase_info')
                    ->where(['purchase_code' => $row['purchase_code']])
                    ->select();
                $info_by_id = [];
                foreach ($info_list as $info) {
                    $info_by_id[$info['id']] = $info;
                }

                // 预加载关联的订单明细
                $orderInfoIds = array_column($info_list, 'order_info_id');
                $orderInfoMap = [];
                if (!empty($orderInfoIds)) {
                    $orderInfos = Db::name('kuerp_goods_purchase_order_info')
                        ->where(['id' => ['in', $orderInfoIds]])
                        ->select();
                    foreach ($orderInfos as $oi) {
                        $orderInfoMap[$oi['id']] = $oi;
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
                    $oi     = isset($orderInfoMap[$oi_id]) ? $orderInfoMap[$oi_id] : null;
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
                    ];
                    $total_store += $storage_input;
                }

                if (empty($storage_items)) {
                    $this->error('入库数量不能全部为0');
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
                        Db::name('kuerp_goods_purchase_info')
                            ->where(['id' => $item['info_id']])
                            ->update(['storage_number' => $item['storage_number']]);

                        // 4. 同步 purord_info
                        Db::name('kuerp_goods_purord_info')
                            ->where(['purchase_id' => $item['info_id']])
                            ->update(['number' => $item['storage_number']]);

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

                    // 7. 更新购货单时间戳
                    $this->model->where(['id' => $ids])->update(['updatetime' => time()]);

                    Db::commit();
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
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('username');

        $purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code' => $row['purchase_code']])->select();
        // 预加载商品信息和库位
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsMap = []; $locationMap = [];
        if (!empty($goodsCodes)) {
            $goodsList = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->field('goods_code,goods_name,location_id')->select();
            $locIds = [];
            foreach ($goodsList as $g) {
                $goodsMap[$g['goods_code']] = $g;
                if (!empty($g['location_id'])) $locIds[] = $g['location_id'];
            }
            if (!empty($locIds)) {
                $locationMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($locIds)], 'status' => '1'])->column('location_name', 'id');
            }
        }
        foreach ($purchase_info as $k => $v) {
            $goods = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : null;
            $purchase_info[$k]['goods_name']    = $goods ? $goods['goods_name'] : '';
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

        return $this->view->fetch();
    }

}
