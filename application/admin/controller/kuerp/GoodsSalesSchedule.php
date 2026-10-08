<?php

namespace app\admin\controller\kuerp;

use app\admin\model\Admin;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 销货排期
 *
 * @icon fa fa-calendar
 */
class GoodsSalesSchedule extends Backend
{
    protected $noNeedRight = ['getunit','formatinfo','getbranch','gettime','printpage','getschedulegoods','counter','examine','getbomrequirement','lockgoods','unlockgoods','getlockstatus','getCustomerSaleUser'];
    /**
     * GoodsSalesSchedule模型对象
     * @var \app\admin\model\kuerp\GoodsSalesSchedule
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesSchedule;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("customersList", $this->model->getCustomersList());
        $this->assignconfig("customersList", $this->model->getCustomersList());
        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("saleList", $this->model->getSaleList());
    }

    /**
     * 查看销货排期列表（与销货单共用同一张表，仅隐藏金额字段）
     * 供采购计划部门使用
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'purchase_time';$order='DESC';
            $list = $this->model
                ->with(['customers','preparation','examine','staffmember'])
                ->where($where)
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            // 金额字段列表（采购计划部门不可见）
            $hiddenFields = ['all_money','preferential_rate','preferential_money','real_money','tax_money','money_tax_price','purchase_money','customers_money','pay_money','arrears','all_arrears','cost_data'];

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                $row->getRelation('preparation')->visible(['nickname']);
                $row->getRelation('examine')->visible(['nickname']);
                if ($row->getRelation('staffmember')) {
                    $row->getRelation('staffmember')->visible(['name']);
                }
                // 隐藏所有金额字段
                $row->hidden($hiddenFields);
            }
            // 收集交货时间为空的销货订单号，批量查询订单的交货时间
            $emptyDeliveryCodes = [];
            foreach ($list as $list_v) {
                if (empty($list_v['delivery_time']) && !empty($list_v['purchase_order_id'])) {
                    $emptyDeliveryCodes[] = $list_v['purchase_order_id'];
                }
            }
            $orderDeliveryMap = [];
            if (!empty($emptyDeliveryCodes)) {
                $orderDeliveryMap = Db::name('kuerp_goods_sales_order')
                    ->where('purchase_code', 'in', array_unique($emptyDeliveryCodes))
                    ->column('delivery_time', 'purchase_code');
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                // 如果交货时间为空，从关联的销货订单获取
                if (empty($list_v['delivery_time']) && !empty($list_v['purchase_order_id']) && isset($orderDeliveryMap[$list_v['purchase_order_id']])) {
                    $list_v['delivery_time'] = $orderDeliveryMap[$list_v['purchase_order_id']];
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加销货排期（仅录入客户、商品和数量，不含金额字段）
     */
    public function add($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$goods_serial_all = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){
                        $number += (float)$params['number'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            if(empty($params['manufacture'][$k])){
                                $this->error('请输入生产日期');
                            }
                            if($params['guarantee'][$k]===''){
                                $this->error('请输入保质期');
                            }
                            if ($params['guarantee'][$k]<0){
                                $this->error('保质期天数不可小于0');
                            }
                            if(empty($params['validity'][$k])){
                                $this->error('请输入过期时间');
                            }
                        }

                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $period_period = unserialize($params['period_period'][$k]);
                            $serial_code = [];
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                if(in_array($period_period_v['serial'],$goods_serial_all)){
                                    $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                }else{
                                    $goods_serial_all[] = $period_period_v['serial'];
                                }
                                $serial_code[] = $period_period_v['serial'];
                            }
                            $status = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code]])->column('serial');
                            if($status){
                                $this->error('商品序列号'.$status[0].'已占用');
                            }
                        }

                        // 销货排期明细（只含业务字段，不含金额）
                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],
                            'sale_user'         =>  (int)$params['sale_user'],

                            'goods_code'        =>  $v,
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],

                            'stores_id'         =>  $params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],

                            'batch_id'          => $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'batch_name'        => $params['batch'][$k]===''?null:$params['batch'][$k],
                            'manufacture'       => strtotime($params['manufacture'][$k]),
                            'guarantee'         => $params['guarantee'][$k],
                            'validity'          => strtotime($params['validity'][$k]),

                            'createtime'        => time(),
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                        ];
                    }
                }
                if(!empty($data)){
                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  '1',
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'customers_id'      =>  (int)$params['customers_id'],
                        'sale_user'         =>  (int)$params['sale_user'],
                        'receiving_address' =>  $params['receiving_address'],
                        'number'            =>  $number,
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

                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->insertGetId($row);
                    }
                    foreach ($data as $data_k=>$data_v){
                        Db::name('kuerp_goods_sales_info')->insertGetId($data_v);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $code = \addons\kuerp\library\Goods::getCodes('',8);
        $this->view->assign("code", $code);

        // 如果来自销货排期，预填商品信息
        $schedule_id = $this->request->param('schedule_id');
        $schedule_goods = [];
        if ($schedule_id) {
            $schedule_goods = $this->getScheduleGoodsData($schedule_id);
        }

        $this->view->assign("row", []);
        $this->view->assign("schedule_goods", $schedule_goods);
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
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);
                $info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$old_purchase_code])->column('id');

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$goods_serial_all=[];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){
                        $number+=$params['number'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            if(empty($params['manufacture'][$k])){
                                $this->error('请输入生产日期');
                            }
                            if($params['guarantee'][$k]===''){
                                $this->error('请输入保质期');
                            }
                            if ($params['guarantee'][$k]<0){
                                $this->error('保质期天数不可小于0');
                            }
                            if(empty($params['validity'][$k])){
                                $this->error('请输入过期时间');
                            }
                        }

                        if(isset($params['period_period'][$k]) && !empty($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            $period_period = unserialize($params['period_period'][$k]);
                            $serial_code = [];
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                if(in_array($period_period_v['serial'],$goods_serial_all)){
                                    $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                }else{
                                    $goods_serial_all[] = $period_period_v['serial'];
                                }
                                $serial_code[] = $period_period_v['serial'];
                            }
                            $status = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code],'purchase_id'=>['not in',$info_id]])->find();
                            if($status){
                                $this->error('商品序列号已占用');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],
                            'sale_user'         =>  (int)$params['sale_user'],

                            'goods_code'        =>  $v,
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],

                            'batch_id'          => $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'batch_name'        => $params['batch'][$k]===''?null:$params['batch'][$k],
                            'manufacture'       => strtotime($params['manufacture'][$k]),
                            'guarantee'         => $params['guarantee'][$k],
                            'validity'          => strtotime($params['validity'][$k]),

                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                        ];
                    }
                }
                if(!empty($data)){
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  '1',
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'customers_id'      =>  (int)$params['customers_id'],
                        'sale_user'         =>  (int)$params['sale_user'],
                        'receiving_address' =>  $params['receiving_address'],
                        'number'            =>  $number,
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    Db::name('kuerp_goods_sales_info')->where(['id'=>['in',$info_id]])->delete();
                    foreach ($data as $data_k=>$data_v){
                        Db::name('kuerp_goods_sales_info')->insertGetId($data_v);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);

        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->select();
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
            $purchase_info[$purchase_info_k]['unit'] = json_decode($this->getUnit($purchase_info_v['goods_code'], $purchase_info_v['stores_id'], $purchase_info_v['goods_attr']),true);
            $purchase_info[$purchase_info_k]['serial'] = $purchase_info_v['period'];
            $gid = isset($goodsIdMap[$purchase_info_v['goods_code']]) ? $goodsIdMap[$purchase_info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$purchase_info_v['stores_id'] . '-' . (int)$purchase_info_v['goods_attr'];
            $purchase_info[$purchase_info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
        }
        $this->view->assign("purchase_info", $purchase_info);

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

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
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

    /**
     * 获取商品单位/库存/属性（不含金额，供采购计划部门使用）
     */
    public function getUnit($goods_code='',$store='',$attr=''){
        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,goods_code,manager_warehouse_id,shelf_life,warehousing,warehouse,goods_specification_id,vendor_name,packaging')->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:0;
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])->field('batch,serial,auxiliary,unit_id,more_unit,more_unit_id,goods_code,manager_warehouse_id,shelf_life,warehousing,warehouse,goods_specification_id,vendor_name,packaging')->find();
        }
        if(empty($row)){
            return [];
        }

        $auxiliary = Db::name('kuerp_auxiliary')->where(['status'=>1])->column('name','id');
        $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id])->select();
        foreach ($goods_attr as $goods_attr_k=>$goods_attr_v){
            $aux_combination = Db::name('kuerp_aux_combination')->where(['id'=>$goods_attr_v['attribute_id']])->value('attr_ids');
            $goods_attr[$goods_attr_k]['attr_ids'] = [];
            $goods_attr[$goods_attr_k]['attr_name'] = '';
            if($aux_combination){
                $goods_attr[$goods_attr_k]['attr_id'] = $aux_combination;
                $aux_combination = explode('&',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_ids'] = $aux_combination;
                foreach ($aux_combination as $aux_combination_k=>$aux_combination_v){
                    $aux_combination[$aux_combination_k] = isset($auxiliary[$aux_combination_v])?$auxiliary[$aux_combination_v]:'';
                }
                $aux_combination = implode('/',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_name'] = $aux_combination;
            }
        }

        $where = [];
        $where['goods_id'] = $goods_id;
        if($store){
            $where['stores_id'] = $store;
        }else{
            $where['stores_id'] = $row['manager_warehouse_id'];
        }
        if($attr){
            $where['attribute_id'] = $attr;
        }
        $stock = Db::name('kuerp_goods_stock')->where($where)->value('stock');

        // 收集所有单位ID和换算率（不查询价格表，采购计划部门无需看到金额）
        $unit_ids = [$row['unit_id']];
        $rateMap = []; // vice_unit_id => advance_rate

        if($row['more_unit']==1){
            $unitConversions = Db::name('kuerp_unit_conversion')->alias('uc')
                ->join('kuerp_unit u','u.id=uc.vice_unit')
                ->where(['uc.main_unit'=>$row['unit_id']])
                ->order('uc.advance_rate','ASC')
                ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                ->select();
            foreach ($unitConversions as $v){
                $unit_ids[] = $v['id'];
                $rateMap[$v['id']] = $v['advance_rate'];
            }
        }

        $units = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();

        // 构建返回数据（只有单位、换算率、库存，不含任何金额字段）
        $data = [];
        foreach ($unit_ids as $uid){
            foreach ($units as $u){
                if($uid == $u['id']){
                    $isDefault = ($u['id'] == $row['unit_id']) ? 1 : 0;
                    $basicNmber = $isDefault ? 1 : (isset($rateMap[$u['id']]) ? $rateMap[$u['id']] : 1);
                    $stockVal = $isDefault ? (is_null($stock)?0:$stock) : round($stock/$basicNmber, 2);
                    $data[] = [
                        'id'           => $u['id'],
                        'name'         => $u['name'],
                        'is_default'   => $isDefault,
                        'basic_nmber'  => $basicNmber,
                        'stock'        => $stockVal,
                    ];
                    break;
                }
            }
        }

        return json_encode(['unit'=>$data,'attr'=>$goods_attr,'goods'=>$row]);
    }

    public function formatInfo(){
        $data = input();
        $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        $number_decimal = $setList['number_decimal']['value'];

        $format = [
            'number'=>$number_decimal,
            'allow_stock'=>$number_decimal,
            'basic_nmber'=>$number_decimal,
        ];
        foreach ($data as $k=>$v){
            if(array_key_exists($k,$format)){
                $data[$k] = (float)$data[$k];
            }
        }

        $data['basic_nmber'] = $data['number']*$data['basic_nmber'];

        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
            }
        }

        $arr = [];
        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
                if($format[$data_k]>0){
                    $price = explode('.',$data[$data_k]);
                    if(isset($price[1])){
                        $cha = $format[$data_k]-strlen($price[1]);
                    }else{
                        $cha = $format[$data_k];
                        $data[$data_k] = $data[$data_k].'.';
                    }
                    $str = '';
                    for ($a=0;$a<$cha;$a++){
                        $str .= '0';
                    }
                    $data[$data_k] .= $str;
                }
            }
            $arr[] = ['key'=>$data_k,'value'=>$data[$data_k]];
        }
        return json_encode(['data'=>$arr]);
    }

    //获取批次
    public function getBranch($goods_code=''){
        $data = input();
        $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('batch,shelf_life')->find();

        $batch_id = Db::name('kuerp_goods_batch_info')->where(['goods_code'=>$data['goods_code']])->column('batch_id');
        $batch_ids = Db::name('kuerp_goods_batch_stock')->where(['goods_code'=>$data['goods_code']])->column('batch_id');
        foreach ($batch_id as $batch_id_k=>$batch_id_v){
            if(in_array($batch_id_v,$batch_ids)){
                unset($batch_id[$batch_id_k]);
            }
        }
        if(!empty($batch_id)){
            Db::name('kuerp_goods_batch')->where(['goods_code'=>$data['goods_code'],'id'=>['not in',$batch_id]])->delete();
        }

        $where = [];
        $batch_id = Db::name('kuerp_goods_batch_stock')->where(['goods_code'=>$data['goods_code']])->where(['stock'=>['<>',0]])->group('batch_id')->column('batch_id');
        $where['id'] = ['in',$batch_id];
        $where['goods_code'] = $data['goods_code'];

        $goods_batch = Db::name('kuerp_goods_batch')->where($where)->field('id,batch_name,manufacture,guarantee,validity')->select();
        foreach ($goods_batch as $goods_batch_k=>$goods_batch_v){
            $goods_batch[$goods_batch_k]['manufacture'] = date('Y-m-d',$goods_batch_v['manufacture']);
            $goods_batch[$goods_batch_k]['validity'] = date('Y-m-d',$goods_batch_v['validity']);
        }
        return json_encode(['goods'=>$row,'batch'=>$goods_batch]);
    }

    public function getTime($manufacture='',$shelf_life='',$validity=''){
        $shelf_life = (float)$shelf_life;
        if(!empty($manufacture) && !empty($shelf_life)){
            $validity = date('Y-m-d',strtotime($manufacture)+$shelf_life*86400);
        }elseif(!empty($validity) && !empty($shelf_life)){
            $manufacture = date('Y-m-d',strtotime($validity)-$shelf_life*86400);
        }elseif(!empty($validity) && !empty($manufacture)){
            $shelf_life = ceil((strtotime($validity)-strtotime($manufacture))/86400);
        }
        return json_encode(['manufacture'=>$manufacture,'shelf_life'=>$shelf_life,'validity'=>$validity]);
    }

    /**
     * 获取排期商品数据（供其他页面调用预填）
     */
    public function getScheduleGoods($schedule_id = null)
    {
        if (empty($schedule_id)) {
            $schedule_id = $this->request->param('schedule_id');
        }
        if (empty($schedule_id)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $row = $this->model->where(['id' => $schedule_id])->find();
        if (!$row) {
            return json(['code' => 0, 'msg' => '排期不存在']);
        }

        $info_list = Db::name('kuerp_goods_sales_info')
            ->where(['purchase_code' => $row['purchase_code']])
            ->select();

        $goods_list = [];
        foreach ($info_list as $v) {
            $goods = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->field('goods_name,note')->find();
            $goods_list[] = [
                'goods_code'   => $v['goods_code'],
                'goods_name'   => $goods ? $goods['goods_name'] : '',
                'goods_note'   => $goods ? $goods['note'] : '',
                'goods_unit'   => $v['goods_unit'],
                'goods_attr'   => $v['goods_attr'],
                'stores_id'    => $v['stores_id'],
                'number'       => $v['number'],
                'default_unit' => $v['default_unit'],
                'basic_nmber'  => $v['basic_nmber'],
                'remark'       => $v['remark'],
            ];
        }

        return json([
            'code' => 1,
            'msg' => [
                'schedule' => [
                    'customers_id'      => $row['customers_id'],
                    'sale_user'         => $row['sale_user'],
                    'receiving_address' => $row['receiving_address'],
                    'note'              => $row['note'],
                ],
                'goods' => $goods_list,
            ]
        ]);
    }

    /**
     * 内部获取排期商品数据
     */
    private function getScheduleGoodsData($schedule_id)
    {
        $row = $this->model->where(['id' => $schedule_id])->find();
        if (!$row) return [];

        $info_list = Db::name('kuerp_goods_sales_info')
            ->where(['purchase_code' => $row['purchase_code']])
            ->select();

        $goods_list = [];
        foreach ($info_list as $v) {
            $goods = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->field('goods_name,note')->find();
            $goods_list[] = [
                'goods_code'   => $v['goods_code'],
                'goods_name'   => $goods ? $goods['goods_name'] : '',
                'goods_note'   => $goods ? $goods['note'] : '',
                'goods_unit'   => $v['goods_unit'],
                'goods_attr'   => $v['goods_attr'],
                'stores_id'    => $v['stores_id'],
                'number'       => $v['number'],
                'default_unit' => $v['default_unit'],
                'basic_nmber'  => $v['basic_nmber'],
                'remark'       => $v['remark'],
            ];
        }
        return $goods_list;
    }

    // 打印销货排期详情
    public function printpage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $customer_name = Db::name('kuerp_customers')->where(['id' => $row['customers_id']])->value('customer_name');
        $row['customer_name'] = $customer_name ? $customer_name : '';
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname');

        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code' => $row['purchase_code']])->select();
        foreach ($purchase_info as $k => $v) {
            $purchase_info[$k]['goods_name'] = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->value('goods_name');
            $purchase_info[$k]['goods_note'] = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->value('note');
            $purchase_info[$k]['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $purchase_info[$k]['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
        }
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);
        $this->model->where(['id' => $ids])->setInc('print_num');
        return $this->view->fetch();
    }

    /**
     * 对单/反对单
     */
    public function counter($ids = null, $counter = 1)
    {
        if (empty($ids)) {
            $this->error('请选择要操作的数据');
        }
        if ($counter == 1) {
            $list = $this->model->where(['id' => ['in', $ids], 'check_status' => '0'])->select();
            foreach ($list as $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
            }
            if (empty($list)) {
                $this->error('请选择需要对单的数据');
            }
            $admin = Session::get('admin');
            $result = $this->model->where(['id' => ['in', $ids], 'check_status' => '0'])->update(['check_status' => 1, 'operation_id' => $admin['id']]);
            if ($result !== false) {
                $this->success(sizeof($list) . '条数据对单成功');
            } else {
                $this->error('对单失败');
            }
        } else {
            $list = $this->model->where(['id' => ['in', $ids], 'check_status' => '1'])->select();
            foreach ($list as $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
            }
            if (empty($list)) {
                $this->error('请选择需要反对单的数据');
            }
            $admin = Session::get('admin');
            $result = $this->model->where(['id' => ['in', $ids], 'check_status' => '1'])->update(['check_status' => 0, 'operation_id' => $admin['id']]);
            if ($result !== false) {
                $this->success(sizeof($list) . '条数据反对单成功');
            } else {
                $this->error('反对单失败');
            }
        }
    }

    /**
     * 审核/反审核
     */
    public function examine($ids = null, $examine = 1)
    {
        if (empty($ids)) {
            return ['status' => 0, 'msg' => '请选择要操作的数据'];
        }
        if ($examine == 1) {
            $list = $this->model->where(['id' => ['in', $ids], 'examine_status' => '0'])->select();
            foreach ($list as $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if ($checkout !== true) {
                    return ['status' => 0, 'msg' => $checkout];
                }
            }
            if (empty($list)) {
                return ['status' => 0, 'msg' => '请选择需要审核的数据'];
            }
            $admin = Session::get('admin');
            $time = time();
            $examine_ids = [];
            $arrears = [];
            foreach ($list as $list_v) {
                $examine_ids[] = $list_v['id'];
                if ($list_v['arrears'] > 0) {
                    $suppliers = Db::name('kuerp_customers')->where(['id' => $list_v['customers_id']])->field('id,need_receive_account')->find();
                    if ($suppliers) {
                        $status = false;
                        foreach ($arrears as $arrears_k => $arrears_v) {
                            if ($arrears_v['id'] == $suppliers['id']) {
                                $arrears[$arrears_k]['need_receive_account'] = $list_v['arrears'] + $arrears_v['need_receive_account'];
                                $status = true;
                            }
                        }
                        if (!$status) {
                            $arrears[] = [
                                'id' => $suppliers['id'],
                                'need_receive_account' => $list_v['arrears'] + $suppliers['need_receive_account'],
                            ];
                        }
                    }
                }
            }
            Db::startTrans();
            try {
                $result = $this->model->where(['id' => ['in', $examine_ids], 'examine_status' => '0'])->update([
                    'examine_status' => 1,
                    'examine_id' => $admin['id'],
                    'examine_time' => $time,
                    'operation_id' => $admin['id'],
                    'updatetime' => $time,
                ]);
                if (!empty($arrears)) {
                    foreach ($arrears as $v) {
                        Db::name('kuerp_customers')->where(['id' => $v['id']])->update(['need_receive_account' => $v['need_receive_account']]);
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                return ['status' => 0, 'msg' => $e->getMessage()];
            }
            if ($result !== false) {
                $this->success(sizeof($list) . '条数据审核成功');
            } else {
                return ['status' => 0, 'msg' => '审核失败'];
            }
        } else {
            $list = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->select();
            foreach ($list as $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if ($checkout !== true) {
                    return ['status' => 0, 'msg' => $checkout];
                }
            }
            if (empty($list)) {
                return ['status' => 0, 'msg' => '请选择需要反审核的数据'];
            }
            $admin = Session::get('admin');
            $time = time();
            $examine_ids = [];
            foreach ($list as $list_v) {
                if ($list_v['check_status'] == 1) {
                    return ['status' => 0, 'msg' => '不能反审核已对单的数据'];
                }
                $examine_ids[] = $list_v['id'];
            }
            Db::startTrans();
            try {
                $result = $this->model->where(['id' => ['in', $examine_ids], 'examine_status' => '1'])->update([
                    'examine_status' => 0,
                    'examine_id' => 0,
                    'examine_time' => 0,
                    'operation_id' => $admin['id'],
                    'updatetime' => $time,
                ]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                return ['status' => 0, 'msg' => $e->getMessage()];
            }
            if ($result !== false) {
                $this->success(sizeof($list) . '条数据反审核成功');
            } else {
                return ['status' => 0, 'msg' => '反审核失败'];
            }
        }
    }

    /**
     * 获取BOM零件需求计算（销货排期编辑页BOM联动）
     * 根据排期中的所有商品，通过BOM模板计算所需子商品零件数量，
     * 并显示当前零件库存和差值
     */
    public function getBomRequirement($schedule_id = null)
    {
        if (empty($schedule_id)) {
            $schedule_id = $this->request->param('schedule_id');
        }
        if (empty($schedule_id)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $numberDecimal = 2;
        $setData = Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
        if ($setData) {
            $numberDecimal = (int)$setData;
        }

        // 优先使用前端传入的实时商品数据（编辑页未保存的数据）
        $goodsData = $this->request->post('goods_data/a');

        if (!empty($goodsData)) {
            // 使用前端实时数据
            $infoList = [];
            foreach ($goodsData as $gd) {
                if (!empty($gd['goods_code']) && (float)$gd['basic_nmber'] > 0) {
                    $infoList[] = [
                        'goods_code' => $gd['goods_code'],
                        'number' => (float)$gd['number'],
                        'stores_id' => (int)$gd['stores_id'],
                        'default_unit' => (float)$gd['default_unit'],
                        'basic_nmber' => (float)$gd['basic_nmber'],
                    ];
                }
            }
        } else {
            // 从数据库读取
            $row = $this->model->where(['id' => $schedule_id])->find();
            if (!$row) {
                return json(['code' => 0, 'msg' => '排期不存在']);
            }
            $infoList = Db::name('kuerp_goods_sales_info')
                ->where(['purchase_code' => $row['purchase_code']])
                ->select();
        }

        if (empty($infoList)) {
            return json(['code' => 0, 'msg' => '排期无商品数据']);
        }

        // 获取排期的销售订单号（用于查询锁定状态）
        $scheduleRow = $this->model->where(['id' => $schedule_id])->find();
        $salesOrderId = $scheduleRow ? ($scheduleRow['purchase_order_id'] ?: '') : '';

        // 查询该销售订单下所有锁定记录
        $lockMap = []; // "goods_id-stores_id" => {lock_quantity, used_quantity, status}
        if (!empty($salesOrderId)) {
            $lockList = Db::name('kuerp_goods_lock')
                ->where(['sales_order_id' => $salesOrderId, 'status' => '1'])
                ->select();
            foreach ($lockList as $lk) {
                $key = $lk['goods_id'] . '-' . $lk['stores_id'];
                $lockMap[$key] = [
                    'id'            => $lk['id'],
                    'lock_quantity' => (float)$lk['lock_quantity'],
                    'used_quantity' => (float)$lk['used_quantity'],
                    'status'        => $lk['status'],
                ];
            }
        }

        // 汇总每个零件：goods_id => ['required' => 累计需求, 'stores' => [store=>需求], 'info' => 商品信息]
        $partMap = [];

        foreach ($infoList as $info) {
            $goodsCode = $info['goods_code'];
            $scheduleQty = (float)$info['number'];
            $storesId = (int)$info['stores_id'];
            $defaultUnit = (float)$info['default_unit'];      // 换算率(基本单位)
            $basicNmber = (float)$info['basic_nmber'];         // 基本单位数量

            // 找到该商品对应的goods_id
            $goodsId = Db::name('kuerp_goods')->where(['goods_code' => $goodsCode])->value('id');
            if (!$goodsId) continue;

            // 查找该商品的BOM模板
            $bomList = Db::name('kuerp_goods_bom')->where(['goods_id' => $goodsId])->select();
            if (empty($bomList)) continue;

            foreach ($bomList as $bom) {
                // 获取BOM子商品（type=2），取零件和半成品类型
                $children = Db::name('kuerp_goods_bom_info')->alias('a')
                    ->join('kuerp_goods g', 'g.id=a.goods_id')
                    ->where([
                        'a.bom_id' => $bom['id'],
                        'a.type' => '2',
                        'g.product_type' => ['in', ['part', 'semi_finished']],
                    ])
                    ->field('a.goods_id, a.goods_code, a.number as bom_number, g.goods_name, g.unit_id')
                    ->select();

                foreach ($children as $child) {
                    $childGoodsId = $child['goods_id'];
                    // BOM中的子商品数量是"1个父商品需要多少子商品"
                    // 排期中的数量是父商品的排期数量
                    // 需求 = BOM子商品数量 × 排期数量(基本单位)
                    $childBomQty = (float)$child['bom_number'];
                    $requiredQty = $childBomQty * $basicNmber;

                    if (!isset($partMap[$childGoodsId])) {
                        $partMap[$childGoodsId] = [
                            'goods_id' => $childGoodsId,
                            'goods_code' => $child['goods_code'],
                            'goods_name' => $child['goods_name'],
                            'unit_id' => $child['unit_id'],
                            'required_total' => 0,
                            'stores' => [],
                        ];
                    }
                    $partMap[$childGoodsId]['required_total'] += $requiredQty;

                    // 按仓库累计需求
                    $storeKey = (string)$storesId;
                    if (!isset($partMap[$childGoodsId]['stores'][$storeKey])) {
                        $partMap[$childGoodsId]['stores'][$storeKey] = 0;
                    }
                    $partMap[$childGoodsId]['stores'][$storeKey] += $requiredQty;
                }
            }
        }

        if (empty($partMap)) {
            return json(['code' => 0, 'msg' => '未找到BOM零件数据']);
        }

        // 查询每个零件的当前库存（仅查询零件仓库，按仓库汇总）
        $partGoodsIds = array_keys($partMap);
        $partStoreIds = Db::name('kuerp_stores')->where(['store_type' => 'part'])->column('id');
        $stockData = Db::name('kuerp_goods_stock')
            ->where(['goods_id' => ['in', $partGoodsIds]])
            ->where(['stores_id' => ['in', $partStoreIds]])
            ->field('goods_id, stores_id, stock')
            ->select();

        // 仓库名称映射（查询所有零件仓库，不限于有库存的）
        $storeNames = [];
        if (!empty($partStoreIds)) {
            $storeNames = Db::name('kuerp_stores')
                ->where(['id' => ['in', $partStoreIds]])
                ->column('name', 'id');
        }

        // 组装每个零件的仓库库存
        $goodsStockMap = []; // goods_id => [stores_id => stock]
        foreach ($stockData as $sd) {
            $gid = $sd['goods_id'];
            $sid = $sd['stores_id'];
            if (!isset($goodsStockMap[$gid])) {
                $goodsStockMap[$gid] = [];
            }
            $goodsStockMap[$gid][$sid] = (float)$sd['stock'];
        }

        // 构建返回数据
        $result = [];
        foreach ($partMap as $goodsId => $part) {
            $totalRequired = $part['required_total'];
            $stockDetails = []; // 按仓库明细: [{stores_name, stock, required, diff}]

            // 该零件所有有库存的仓库
            $allStores = [];
            if (isset($goodsStockMap[$goodsId])) {
                foreach ($goodsStockMap[$goodsId] as $sid => $stk) {
                    $allStores[$sid] = $stk;
                }
            }

            // 需求按仓库（来自前端商品数据，仅用于补齐仓库列表）
            $reqByStore = $part['stores'];

            // 合并所有仓库（有库存或有需求的）
            $allStoreIds = array_unique(array_merge(array_keys($allStores), array_keys($reqByStore)));
            // 过滤：只保留零件仓库 (store_type='part')，排除父商品排期带入的成品/半成品仓库
            $allStoreIds = array_values(array_intersect($allStoreIds, $partStoreIds));

            // 构建仓库-库存映射，按库存降序排列
            $storeStockMap = [];
            foreach ($allStoreIds as $sid) {
                $storeStockMap[$sid] = isset($allStores[$sid]) ? $allStores[$sid] : 0;
            }
            // 按库存降序：有库存的仓库排在前面
            arsort($storeStockMap);

            // 分配需求：采购时只考虑一个仓库，优先考虑有库存的仓库
            // 将需求全部分配给库存最多的仓库，stock 保留实际库存（锁定弹窗需要）
            $remainingRequired = $totalRequired;
            $isFirst = true;

            foreach ($storeStockMap as $sid => $stk) {
                if ($isFirst) {
                    $isFirst = false;
                    // 最佳仓库：需求全部分配到这个仓库
                    $req = $remainingRequired;
                    $diff = $stk - $req;
                } else {
                    // 其他仓库：需求已被最佳仓库吸收，不再分配
                    $req = 0;
                    $diff = $stk;
                }

                // 跳过既无库存也无需求的仓库
                if ($stk == 0 && $req == 0) {
                    continue;
                }

                $stockDetails[] = [
                    'stores_id' => (int)$sid,
                    'stores_name' => isset($storeNames[$sid]) ? $storeNames[$sid] : '仓库'.$sid,
                    'stock' => round($stk, $numberDecimal),
                    'required' => round($req, $numberDecimal),
                    'diff' => round($diff, $numberDecimal),
                ];
            }

            // 极端情况：所有零件仓库都已过滤（无库存 + 父商品排期仓库非零件仓）
            if (empty($stockDetails) && $totalRequired > 0) {
                // 优先使用第一个零件仓库作为默认仓库
                if (!empty($partStoreIds)) {
                    $defaultSid = (int)reset($partStoreIds);
                    $stockDetails[] = [
                        'stores_id' => $defaultSid,
                        'stores_name' => isset($storeNames[$defaultSid]) ? $storeNames[$defaultSid] : '仓库'.$defaultSid,
                        'stock' => 0,
                        'required' => round($totalRequired, $numberDecimal),
                        'diff' => round(-$totalRequired, $numberDecimal),
                    ];
                } else {
                    $stockDetails[] = [
                        'stores_id' => 0,
                        'stores_name' => '暂无仓库',
                        'stock' => 0,
                        'required' => round($totalRequired, $numberDecimal),
                        'diff' => round(-$totalRequired, $numberDecimal),
                    ];
                }
            }

            // 计算合计：total_stock 为实际库存之和
            $totalStock = 0;
            foreach ($stockDetails as $sd) {
                $totalStock += $sd['stock'];
            }
            $totalDiff = $totalStock - $totalRequired;

            // 查询该零件在各仓库的锁定信息
            $lockInfo = []; // stores_id => {locked, used, remaining}
            if (!empty($salesOrderId)) {
                foreach ($allStoreIds as $sid) {
                    $key = $goodsId . '-' . $sid;
                    if (isset($lockMap[$key])) {
                        $lk = $lockMap[$key];
                        $lockInfo[(int)$sid] = [
                            'lock_quantity' => round($lk['lock_quantity'], $numberDecimal),
                            'used_quantity' => round($lk['used_quantity'], $numberDecimal),
                            'remaining'     => round($lk['lock_quantity'] - $lk['used_quantity'], $numberDecimal),
                        ];
                    }
                }
            }
            $hasLock = !empty($lockInfo);

            $result[] = [
                'goods_id'      => $goodsId,
                'goods_code'    => $part['goods_code'],
                'goods_name'    => $part['goods_name'],
                'required_total'=> round($part['required_total'], $numberDecimal),
                'total_stock'   => round($totalStock, $numberDecimal),
                'total_diff'    => round($totalDiff, $numberDecimal),
                'stock_details' => $stockDetails,
                'lock_info'     => $lockInfo,
                'has_lock'      => $hasLock,
                'sales_order_id'=> $salesOrderId,
            ];
        }

        // 按差值排序（差值小的在前，即最缺货的在前）
        usort($result, function ($a, $b) {
            return $a['total_diff'] <=> $b['total_diff'];
        });

        return json([
            'code' => 1,
            'msg' => $result,
        ]);
    }

    /**
     * 获取客户绑定的销售员ID
     */
    public function getCustomerSaleUser()
    {
        $customers_id = isset($_POST['customers_id']) ? trim($_POST['customers_id']) : 0;
        if (empty($customers_id)) {
            return json_encode(['staff_member_id' => 0]);
        }
        $staff_member_id = Db::name('kuerp_customers')->where(['id' => $customers_id])->value('staff_member_id');
        return json_encode(['staff_member_id' => $staff_member_id ? (int)$staff_member_id : 0]);
    }

    /**
     * 锁定零件库存
     * POST: schedule_id, goods_code, goods_id, stores_id, lock_quantity
     */
    public function lockGoods()
    {
        $scheduleId   = $this->request->post('schedule_id');
        $goodsCode    = $this->request->post('goods_code');
        $goodsId      = $this->request->post('goods_id');
        $storesId     = $this->request->post('stores_id');
        $lockQuantity = (float)$this->request->post('lock_quantity');

        if (empty($scheduleId) || empty($goodsCode) || empty($goodsId) || empty($storesId) || $lockQuantity <= 0) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        // 获取销售订单号
        $schedule = $this->model->where(['id' => $scheduleId])->find();
        if (empty($schedule) || empty($schedule['purchase_order_id'])) {
            return json(['code' => 0, 'msg' => '排期未关联销售订单']);
        }
        $salesOrderId = $schedule['purchase_order_id'];

        // 校验：锁定数量 ≤ 物理库存 - 其他订单已锁定
        $available = \addons\kuerp\library\Goods::getAvailableStock($goodsId, $storesId, 0, $salesOrderId);
        if ($lockQuantity > $available) {
            return json(['code' => 0, 'msg' => "锁定数量({$lockQuantity})超过可用库存({$available})"]);
        }

        $admin = Session::get('admin');
        $now = time();

        // 检查是否已有该商品+仓库的锁定（覆盖更新）
        $existLock = Db::name('kuerp_goods_lock')
            ->where([
                'sales_order_id' => $salesOrderId,
                'goods_id'       => $goodsId,
                'stores_id'      => $storesId,
            ])
            ->find();

        if ($existLock) {
            // 覆盖更新
            Db::name('kuerp_goods_lock')->where(['id' => $existLock['id']])->update([
                'lock_quantity' => $lockQuantity,
                'used_quantity' => 0,
                'status'        => '1',
                'admin_id'      => $admin['id'],
                'updatetime'    => $now,
            ]);
        } else {
            // 新增
            Db::name('kuerp_goods_lock')->insert([
                'admin_id'        => $admin['id'],
                'sales_order_id'  => $salesOrderId,
                'goods_id'        => $goodsId,
                'goods_code'      => $goodsCode,
                'attribute_id'    => 0,
                'stores_id'       => $storesId,
                'lock_quantity'   => $lockQuantity,
                'used_quantity'   => 0,
                'status'          => '1',
                'createtime'      => $now,
                'updatetime'      => $now,
            ]);
        }

        return json(['code' => 1, 'msg' => '锁定成功']);
    }

    /**
     * 解锁零件库存
     * POST: schedule_id, goods_code, goods_id, stores_id
     */
    public function unlockGoods()
    {
        $scheduleId = $this->request->post('schedule_id');
        $goodsCode  = $this->request->post('goods_code');
        $goodsId    = $this->request->post('goods_id');
        $storesId   = $this->request->post('stores_id');

        if (empty($scheduleId) || empty($goodsId) || empty($storesId)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $schedule = $this->model->where(['id' => $scheduleId])->find();
        if (empty($schedule) || empty($schedule['purchase_order_id'])) {
            return json(['code' => 0, 'msg' => '排期未关联销售订单']);
        }
        $salesOrderId = $schedule['purchase_order_id'];

        $result = Db::name('kuerp_goods_lock')
            ->where([
                'sales_order_id' => $salesOrderId,
                'goods_id'       => $goodsId,
                'stores_id'      => $storesId,
            ])
            ->update([
                'status'     => '0',
                'updatetime' => time(),
            ]);

        if ($result !== false) {
            return json(['code' => 1, 'msg' => '解锁成功']);
        }
        return json(['code' => 0, 'msg' => '解锁失败']);
    }

    /**
     * 查询锁定状态
     * POST: schedule_id
     */
    public function getLockStatus()
    {
        $scheduleId = $this->request->post('schedule_id');
        if (empty($scheduleId)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $schedule = $this->model->where(['id' => $scheduleId])->find();
        if (empty($schedule) || empty($schedule['purchase_order_id'])) {
            return json(['code' => 1, 'msg' => []]);
        }
        $salesOrderId = $schedule['purchase_order_id'];

        $locks = Db::name('kuerp_goods_lock')
            ->where(['sales_order_id' => $salesOrderId, 'status' => '1'])
            ->select();

        return json(['code' => 1, 'msg' => $locks]);
    }
}
