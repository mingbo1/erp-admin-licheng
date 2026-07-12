<?php

namespace app\admin\controller\kuerp;

use app\admin\model\kuerp\GoodsSalesOrderLog;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 购货订单
 *
 * @icon fa fa-circle-o
 */
class GoodsPurchaseOrder extends Backend
{
    protected $noNeedRight = ['checkexchange','checkpurchase'];
    /**
     * GoodsPurchaseOrder模型对象
     * @var \app\admin\model\kuerp\GoodsPurchaseOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsPurchaseOrder;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("suppliersList", $this->model->getSuppliersList());
        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("accountsList", $this->model->getAccountsList());

        $this->view->assign("secondExamineStatusList", $this->model->getSecondExamineStatusList());

        // 二次审核金额阈值
        $purchase_order_examine_money = Db::name('kuerp_sets')->where(['name'=>'purchase_order_examine_money'])->value('value');
        $this->assignconfig('purchase_order_examine_money', $purchase_order_examine_money ? $purchase_order_examine_money : '0');
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
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
                $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                // 获取关联购货单的完整状态信息
                $purchase_list = Db::name('kuerp_goods_purchase')
                    ->where(['id'=>['in',$purchase_id]])
                    ->field('purchase_code,examine_status,pay_status,check_status')
                    ->select();
                $purchase_codes = [];
                foreach ($purchase_list as $p) {
                    $purchase_codes[] = $p['purchase_code'];
                }
                $list[$list_k]['purchase_id'] = implode(',',$purchase_codes);
                $list[$list_k]['purchase_list'] = $purchase_list;
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($type='')
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');

                $params = $this->preExcludeFields($params);
                $admin = Session::get('admin');

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }elseif ($goods['auxiliary']==1 && empty($params['goods_attr'][$k])){
                            $this->error('请选择商品属性');
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'delivery_time'     =>  strtotime($params['delivery_time']),
                            'supplyer_code'     =>  $params['supplyer_code'],//供应商

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'purchase_number'   =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'sales_order_id'    =>  $params['sales_order_id'][$k],

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],

                            'library_number'    =>  (float)$params['number'][$k],
                            'library_money'     =>  (float)$params['all_money'][$k],

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ];
                    }
                }
                if(!empty($data)){
                    // 合并所有行的sales_order_id（取第一个非空的）
                    $mainSalesOrderId = '';
                    foreach ($data as $d) {
                        if (!empty($d['sales_order_id'])) {
                            $mainSalesOrderId = $d['sales_order_id'];
                            break;
                        }
                    }
                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  strtotime($params['delivery_time']),
                        'supplyer_code'     =>  $params['supplyer_code'],//供应商
                        'sales_order_id'    =>  $mainSalesOrderId,
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,
                        'tax_money'         =>  $tax_money,
                        'preferential_rate' =>  (float)$params['preferential_rate'],
                        'preferential_money'=>  (float)$params['preferential_money'],
                        'real_money'        =>  (float)$params['real_money'],
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code'])])->find()){
                    $this->error('单据编号已占用');
                }

                //附件记录开始
                $enclosureLog = [];
                if(isset($params['enclosure']) && !empty($params['enclosure'])){//选择了原始单据
                    $enclosure = explode(',',$params['enclosure']);
                    $enclosurelist = Db::name('kuerp_enclosure')->where(['id'=>['in',$params['enclosure']]])->select();
                    if(sizeof($enclosurelist)!=sizeof($enclosure)){
                        $this->error('选择的原始单据有误，请重新选择');
                    }else{
                        foreach ($enclosure as $k=>$v){
                            $enclosureLog[] = [
                                'enclosure_id'  => $v,
                                'purchase_code' => $params['purchase_code'],
                                'type'          => '1',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->insertGetId($row);
                    }

                    //添加附件记录
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $result;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$params['purchase_code']])->delete();
                    Db::name('kuerp_goods_purchase_order_info')->insertAll($data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    // 写入销货订单日志：生成采购订单
                    if (!empty($mainSalesOrderId)) {
                        GoodsSalesOrderLog::writeLogByCode($mainSalesOrderId, '生成采购订单', '生成采购订单：' . $params['purchase_code'], 'purchase_order', $result);
                    }
                    if($examine!=1){
                        try {
                            $exa = $this->examine($result,1);
                        } catch (Exception $e) {
                        }
                        if(!isset($exa) || $exa['status']!=1){
                            $exa = $this->examine($result,1);
                            try {
                                $this->del($result);
                            } catch (Exception $e) {
                            }
                            $this->error($exa['msg']);
                        }
                    }
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $code = \addons\kuerp\library\Goods::getCodes('',7);
        $this->view->assign("code", $code);

        $GoodsPurchaseOrder = [];
        if($type==1){
            $GoodsPurchaseOrder = Session::get('GoodsPurchaseOrder');
            if($GoodsPurchaseOrder){
                $GoodsPurchaseOrder = unserialize($GoodsPurchaseOrder);
                foreach ($GoodsPurchaseOrder as $GoodsPurchaseOrder_k=>$GoodsPurchaseOrder_v){
                    $GoodsPurchaseOrder[$GoodsPurchaseOrder_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($GoodsPurchaseOrder_v['goods_code']),true);
                    $GoodsPurchaseOrder[$GoodsPurchaseOrder_k]['goods'] = Db::name('kuerp_goods')->where(['goods_code'=>$GoodsPurchaseOrder_v['goods_code']])->find();
                }
            }
        }

        // 从销货排期生成：预填BOM零件商品
        $scheduleBomGoods = [];
        $scheduleSalesOrderId = ''; // 关联的销货订单号
        $schedule_id = $this->request->param('schedule_id');
        if (!empty($schedule_id)) {
            $schedule = Db::name('kuerp_goods_sales')->where(['id' => $schedule_id, 'purchase_type' => '1'])->find();
            if ($schedule) {
                // 获取关联的销货订单号
                $salesOrderCode = $schedule['purchase_order_id']; // purchase_order_id 存储的是销货订单编号

                // 获取排期商品明细
                $scheduleInfo = Db::name('kuerp_goods_sales_info')
                    ->where(['purchase_code' => $schedule['purchase_code']])
                    ->select();

                $numberDecimal = 2;
                $setData = Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
                if ($setData) $numberDecimal = (int)$setData;

                $partMap = [];
                foreach ($scheduleInfo as $info) {
                    $goodsCode = $info['goods_code'];
                    $basicNmber = (float)$info['basic_nmber'];
                    $storesId = (int)$info['stores_id'];

                    $goodsId = Db::name('kuerp_goods')->where(['goods_code' => $goodsCode])->value('id');
                    if (!$goodsId) continue;

                    $bomList = Db::name('kuerp_goods_bom')->where(['goods_id' => $goodsId])->select();
                    if (empty($bomList)) continue;

                    foreach ($bomList as $bom) {
                        $children = Db::name('kuerp_goods_bom_info')->alias('a')
                            ->join('kuerp_goods g', 'g.id=a.goods_id')
                            ->where(['a.bom_id' => $bom['id'], 'a.type' => '2', 'g.product_type' => 'part'])
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

                // 采购时只考虑一个仓库，优先选择有库存的零件仓库
                // 查询各零件在各零件仓库的库存，为每个零件选择库存最多的仓库
                if (!empty($partMap)) {
                    $partGoodsIds = array_keys($partMap);
                    $partStoreIds = Db::name('kuerp_stores')->where(['store_type' => 'part'])->column('id');
                    if (!empty($partStoreIds)) {
                        $stockData = Db::name('kuerp_goods_stock')
                            ->where(['goods_id' => ['in', $partGoodsIds]])
                            ->where(['stores_id' => ['in', $partStoreIds]])
                            ->field('goods_id, stores_id, stock')
                            ->select();
                        $stockMap = []; // goods_id => [stores_id => stock]
                        foreach ($stockData as $sd) {
                            $gid = $sd['goods_id'];
                            if (!isset($stockMap[$gid])) $stockMap[$gid] = [];
                            $stockMap[$gid][$sd['stores_id']] = (float)$sd['stock'];
                        }
                        // 为每个零件选择库存最多的仓库
                        foreach ($partMap as $goodsId => &$p) {
                            if (isset($stockMap[$goodsId]) && !empty($stockMap[$goodsId])) {
                                // 有库存数据：选库存最多的零件仓库
                                arsort($stockMap[$goodsId]);
                                $bestKeys = array_keys($stockMap[$goodsId]);
                                $p['stores_id'] = reset($bestKeys);
                            } elseif (!empty($partStoreIds)) {
                                // 无库存数据：兜底使用第一个零件仓库，而非父商品排期的非零件仓库
                                $p['stores_id'] = (int)reset($partStoreIds);
                            }
                        }
                        unset($p);
                    }
                }

                foreach ($partMap as $p) {
                    $scheduleBomGoods[] = [
                        'goods_code' => $p['goods_code'],
                        'goods_name' => $p['goods_name'],
                        'number' => round($p['number'], $numberDecimal),
                        'stores_id' => $p['stores_id'],
                        'unit_id' => $p['unit_id'],
                    ];
                }

                // 默认供应商从排期客户的反推？不，购货订单要选供应商，这里留空让用户选
                // 传递销货订单号用于关联
                if (!empty($salesOrderCode)) {
                    $scheduleSalesOrderId = $salesOrderCode;
                }
            }
        }

        $last_customer = Db::name('kuerp_sets')->where(['name'=>'customer'])->value('is_checked');
        $set_customer = '';
        if($last_customer){
            $set_customer = Db::name('kuerp_goods_purchase_order')->order('id','DESC')->value('supplyer_code');
        }
        $this->view->assign("set_customer", $set_customer);
        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$set_customer])->field('supplyer_code,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);

        $this->view->assign("list", $GoodsPurchaseOrder);
        $this->assignconfig("list", $GoodsPurchaseOrder);

        // 传递BOM预填数据和销货订单关联信息到前端
        $this->view->assign("scheduleBomGoods", $scheduleBomGoods);
        $this->assignconfig("scheduleBomGoods", $scheduleBomGoods);
        $this->view->assign("scheduleSalesOrderId", $scheduleSalesOrderId);
        $this->assignconfig("scheduleSalesOrderId", $scheduleSalesOrderId);

        return $this->view->fetch();
    }

    public function edit($ids = null)
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
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
            if($checkout!==true){
                $this->error($checkout);
            }
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if($row['examine_status']==1 && $examine==1){
                $this->error('不能编辑已审核的数据');
            }
            if($row['purchase_type']==1){
                $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$ids])->column('purchase_id');
                if(!empty($purchase_id)){
                    $this->error('不能编辑已生成购货单的数据');
                }
            }else{
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$ids])->column('exchange_id');
                if(!empty($purchase_id)){
                    $this->error('不能编辑已生成退货单的数据');
                }
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');

                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }elseif ($goods['auxiliary']==1 && empty($params['goods_attr'][$k])){
                            $this->error('请选择商品属性');
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'delivery_time'     =>  strtotime($params['delivery_time']),
                            'supplyer_code'     =>  $params['supplyer_code'],//供应商

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],

                            'stores_id'         =>  (float)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'purchase_number'   =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'library_number'    =>  (float)$params['number'][$k],
                            'library_money'     =>  (float)$params['all_money'][$k],

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],

                            'sales_order_id'    => $params['sales_order_id'][$k],

                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                        ];
                    }
                }
                if(!empty($data)){
                    // 合并所有行的sales_order_id（取第一个非空的）
                    $mainSalesOrderId = '';
                    foreach ($data as $d) {
                        if (!empty($d['sales_order_id'])) {
                            $mainSalesOrderId = $d['sales_order_id'];
                            break;
                        }
                    }
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  strtotime($params['delivery_time']),
                        'supplyer_code'     =>  $params['supplyer_code'],//供应商
                        'sales_order_id'    =>  $mainSalesOrderId,
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,
                        'tax_money'         =>  $tax_money,
                        'preferential_rate' =>  $params['preferential_rate'],
                        'preferential_money'=>  $params['preferential_money'],
                        'real_money'        =>  $params['real_money'],
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                //附件记录开始
                $enclosureLog = [];
                if(isset($params['enclosure']) && !empty($params['enclosure'])){//选择了原始单据
                    $enclosure = explode(',',$params['enclosure']);
                    $enclosurelist = Db::name('kuerp_enclosure')->where(['id'=>['in',$params['enclosure']]])->select();
                    if(sizeof($enclosurelist)!=sizeof($enclosure)){
                        $this->error('选择的原始单据有误，请重新选择');
                    }else{
                        foreach ($enclosure as $k=>$v){
                            $enclosureLog[] = [
                                'enclosure_id'  => $v,
                                'purchase_code' => $params['purchase_code'],
                                'type'          => '1',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $result = false;
                Db::startTrans();
                try {
                    //关闭审核功能需先反审核一次已审核数据
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }

                    //添加附件记录
                    Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'1'])->delete();
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->delete();
                    Db::name('kuerp_goods_purchase_order_info')->insertAll($data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    if($examine!=1){
                        try {
                            $exa = $this->examine($ids,1);
                        } catch (Exception $e) {
                        }
                        if(!isset($exa) || $exa['status']!=1){
                            $this->error(__('No rows were inserted'));
                        }
                    }
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);

        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        // 批量查询动态库存
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsIdMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id', 'goods_code') : [];
        $stockItems = [];
        foreach ($purchase_info as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? $goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($purchase_info_v['goods_code'], $purchase_info_v['stores_id'], $purchase_info_v['goods_attr']),true);
            $gid = isset($goodsIdMap[$purchase_info_v['goods_code']]) ? $goodsIdMap[$purchase_info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$purchase_info_v['stores_id'] . '-' . (int)$purchase_info_v['goods_attr'];
            $purchase_info[$purchase_info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
        }
        $this->view->assign("purchase_info", $purchase_info);
        $this->assignconfig("purchase_id", $row['id']);

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'1'])->column('enclosure_id');
        if(!empty($enclosure_id)){
            $this->assign('enclosure_count',sizeof($enclosure_id));
            $enclosure_id = implode(',',$enclosure_id);
        }else{
            $enclosure_id = '';
        }
        $this->assign('enclosure_ids',$enclosure_id);

        //审核禁止输入
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine_set==1 && $row['examine_status']==1){
            $this->assign('exclass','hiddens');
        }
        $this->assignconfig("examine_status", $row['examine_status']);
        $this->assignconfig("purchase_type", $row['purchase_type']);

        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');

            foreach ($list as $list_k=>$list_v){
                if($list_v['examine_status']==1){
                    $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                    if($checkout!==true){
                        $this->error($checkout);
                    }
                }
                if($list_v['examine_status']==1 && $examine==1){
                    $this->error('不能删除已审核的数据');
                }elseif ($list_v['examine_status']==1 && $examine!=1){
                    try {
                        $this->examine($list_v['id'],2);
                    } catch (Exception $e) {
                    }
                }
            }
            foreach ($list as $list_k=>$list_v){
                if($list_v['purchase_type']==1){
                    $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                    if(!empty($purchase_id)){
                        $this->error('不能删除已生成购货单的数据');
                    }
                }else{
                    $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                    if(!empty($purchase_id)){
                        $this->error('不能删除已生成退货单的数据');
                    }
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$v['purchase_code']])->delete();

                    //删除附件记录
                    Db::name('kuerp_enclosure_log')->where(['purchase_code'=>$v['purchase_code'],'type'=>'1'])->delete();
                }
                Db::commit();
            }catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    //审核/反审核
    public function examine($ids = null,$examine=1){
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            if($examine_set!=1){
                return ['status'=>0,'msg'=>'系统错误'];
            }
            $this->error('请选择要操作的数据');
        }
        if($examine==1){
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }
            $admin = Session::get('admin');

            // 获取二次审核金额阈值
            $threshold = Db::name('kuerp_sets')->where(['name'=>'purchase_order_examine_money'])->value('value');
            $threshold = floatval($threshold);

            // 分组：低于/等于阈值的自动完成二次审核，高于阈值的需要董事长二次审核
            $belowIds = [];
            $aboveIds = [];
            foreach ($list as $list_v){
                if ($threshold > 0 && floatval($list_v['all_money']) > $threshold){
                    $aboveIds[] = $list_v['id'];
                } else {
                    $belowIds[] = $list_v['id'];
                }
            }

            Db::startTrans();
            try {
                $result = 0;
                // 低于阈值的：审核同时自动完成二次审核
                if (!empty($belowIds)){
                    $result += $this->model->where(['id'=>['in',$belowIds],'examine_status'=>'0'])->update([
                        'examine_status'        => 1,
                        'examine_id'            => $admin['id'],
                        'examine_time'          => time(),
                        'second_examine_status' => 1,
                        'second_examine_id'     => $admin['id'],
                        'second_examine_time'   => time(),
                        'operation_id'          => $admin['id']
                    ]);
                }
                // 高于阈值的：只做一审，需要董事长二次审核
                if (!empty($aboveIds)){
                    $result += $this->model->where(['id'=>['in',$aboveIds],'examine_status'=>'0'])->update([
                        'examine_status'        => 1,
                        'examine_id'            => $admin['id'],
                        'examine_time'          => time(),
                        'operation_id'          => $admin['id']
                    ]);
                }
                Db::commit();

                // 审核通过后：向关联的销货订单写入操作日志
                foreach ($list as $examinedOrder) {
                    if (!empty($examinedOrder['sales_order_id'])) {
                        GoodsSalesOrderLog::writeLogByCode($examinedOrder['sales_order_id'], '采购订单审核通过', '购货订单[' . $examinedOrder['purchase_code'] . ']审核通过', 'purchase_order', $examinedOrder['id']);
                    }
                }
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $msg = sizeof($list).'条数据审核成功';
                if (!empty($aboveIds)){
                    $msg .= '，其中'.sizeof($aboveIds).'条金额超过阈值，需董事长二次审核';
                }
                $this->success($msg);
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('审核失败');
            }
        }else{
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->field('id,purchase_code,sales_order_id,purchase_id,purchase_time')->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('请选择需要反审核的数据');
            }
            foreach ($list as $list_k=>$list_v){
                $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成购货单的数据');
                }
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id'],'type'=>'1'])->column('exchange_id');
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成退货单的数据');
                }
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update([
                    'examine_status'        => 0,
                    'examine_id'            => 0,
                    'examine_time'          => '',
                    'second_examine_status' => 0,
                    'second_examine_id'     => 0,
                    'second_examine_time'   => '',
                    'operation_id'          => $admin['id']
                ]);
                Db::commit();

                // 反审核后：向关联的销货订单写入操作日志
                foreach ($list as $unexaminedOrder) {
                    if (!empty($unexaminedOrder['sales_order_id'])) {
                        GoodsSalesOrderLog::writeLogByCode($unexaminedOrder['sales_order_id'], '采购订单反审核', '购货订单[' . $unexaminedOrder['purchase_code'] . ']反审核', 'purchase_order', $unexaminedOrder['id']);
                    }
                }
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据反审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('反审核失败');
            }
        }
    }

    // 董事长二次审核
    public function secondExamine($ids = null){
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            $this->error('请选择要操作的数据');
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if(empty($ids)){
            $this->error('请选择要操作的数据');
        }

        $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1','second_examine_status'=>'0'])->select();
        if(empty($list)){
            $this->error('请选择已一审通过且未二审的数据');
        }

        $admin = Session::get('admin');
        Db::startTrans();
        try {
            $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1','second_examine_status'=>'0'])->update([
                'second_examine_status' => 1,
                'second_examine_id'     => $admin['id'],
                'second_examine_time'   => time(),
                'operation_id'          => $admin['id']
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if(isset($result) && $result !== false){
            $this->success(sizeof($list).'条数据二次审核成功');
        }else{
            $this->error('二次审核失败');
        }
    }

    //检测可生成购货单
    public function checkPurchase(){
        $order_id = isset($_POST['order_id'])?trim($_POST['order_id']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_purchase_order')->where(['id'=>$order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine==1){
            if($row['examine_status']!=1){
                $this->error('此购货订单未审核，不可生成购货单');
            }
            if($row['second_examine_status']!=1){
                $this->error('此购货订单未通过二次审核，不可生成购货单');
            }
        }
        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_purord_info')->where(['order_id'=>$purchase_info_v['id']])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此购货订单已全部生成购货单');
        }
        $this->success();
    }
    public function checkExchange(){
        $order_id = isset($_POST['order_id'])?trim($_POST['order_id']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_purchase_order')->where(['id'=>$order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine==1){
            if($row['examine_status']!=1){
                $this->error('此购货订单未审核，不可生成退货单');
            }
            if($row['second_examine_status']!=1){
                $this->error('此购货订单未通过二次审核，不可生成退货单');
            }
        }
        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_excpur_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'1'])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此购货订单已全部生成退货单');
        }
        $this->success();
    }
}
