<?php

namespace app\admin\controller\kuerp;

use app\admin\model\kuerp\GoodsSalesOrderLog;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 销货单
 *
 * @icon fa fa-circle-o
 */
class GoodsSales extends Backend
{
    protected $noNeedRight = ['checkpurchase','getsuppliers','getunits','getunit','formatinfo2','getbranch','gettime','getreceivingaddress','printpage','dunning','deldunning'];
    /**
     * GoodsSales模型对象
     * @var \app\admin\model\kuerp\GoodsSales
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSales;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("suppliersList", $this->model->getSuppliersList());
        $this->assignconfig("suppliersList", $this->model->getSuppliersList());
        $this->assignconfig("expendTypeList", $this->model->getExpendTypeList());
        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("accountsList", $this->model->getAccountsList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("saleList", $this->model->getSaleList());

        $accountsList = $this->model->getAccountsList();
        foreach ($accountsList as $accountsList_k=>$accountsList_v){
            if($accountsList_v['is_default']==1){
                $this->view->assign("accountsDefault", $accountsList_v['id']);
                $this->assignconfig("accountsDefault", $accountsList_v['id']);
            }
        }

        $this->view->assign("secondExamineStatusList", $this->model->getSecondExamineStatusList());

        // 二次审核金额阈值
        $sales_order_examine_money = Db::name('kuerp_sets')->where(['name'=>'sales_order_examine_money'])->value('value');
        $this->assignconfig('sales_order_examine_money', $sales_order_examine_money ? $sales_order_examine_money : '0');
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
                ->with(['customers','preparation','examine','secondExamine','dunning'])
                ->where($where)
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                $row->getRelation('preparation')->visible(['username']);
                $row->getRelation('examine')->visible(['username']);
                if ($row->getRelation('secondExamine')) {
                    $row->getRelation('secondExamine')->visible(['username']);
                }
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
                $purchase_id = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                $purchase = Db::name('kuerp_goods_sales_exchange')->where(['id'=>['in',$purchase_id]])->column('purchase_code');
                $list[$list_k]['returns_order_id'] = implode(',',$purchase);
                // 催收记录数量
                $dunningList = $list_v->getRelation('dunning');
                $list_v['dunning_count'] = ($dunningList && count($dunningList) > 0) ? count($dunningList) : 0;
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

    public function add($ids = null,$order_id=null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
                $params = $this->preExcludeFields($params);
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');
                if($params['now_pay_money']>0){
                    if(empty($params['account'])){
                        $this->error('请选择结算账户');
                    }
                    $all_now_pay_money = $now_pay_money = trim($params['now_pay_money']);
                }else{
                    $all_now_pay_money = $now_pay_money = 0;
                }

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$all_money = 0;$purchase_order_id = '';$tax_money=0;$money_tax_price=0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number += (float)$params['number'][$k];
                        $all_money += (float)$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        $money_tax_price += (float)$params['money_tax_price'][$k];

                        if(!empty($params['purchase_order_id'][$k])){
                            $purchase_order_id = $params['purchase_order_id'][$k];
                        }

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        unset($pay_money);unset($pay_status);
                        if($now_pay_money>0){
                            if($now_pay_money-$params['money_tax_price'][$k]>=0){
                                $pay_status = 1;
                                $pay_money = $params['money_tax_price'][$k];
                                $now_pay_money -= $pay_money;
                            }else{
                                $pay_status = 2;
                                $pay_money = $now_pay_money;
                                $now_pay_money -= $now_pay_money;
                            }
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        $goods_batch_info = [];
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            if(empty($batch_data)){
                                $this->error('生产批次不存在');
                            }
                            $goods_batch_info = [
                                'batch_id'    => isset($batch_data['id'])?$batch_data['id']:'',
                                'goods_id'    => $goods['id'],
                                'goods_code'  => $params['goods_code'][$k],
                                'attribute_id'=>isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                'goods_unit'  =>  (int)$params['goods_unit'][$k],
                                'stores_id'   => $params['stores_id'][$k],
                                'stock'       => (float)$params['number'][$k],
                                'number'      => (float)$params['number'][$k],
                                'batch_name'  => isset($batch_data['batch_name'])?$batch_data['batch_name']:'',
                                'manufacture' => strtotime($params['manufacture'][$k]),
                                'guarantee'   => $params['guarantee'][$k],
                                'validity'    => strtotime($params['validity'][$k]),
                                'status'      => '0',
                                'type'        => 2,
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                        }

                        $serial_code = [];
                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $period_period = unserialize($params['period_period'][$k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                $serial_code[] = $period_period_v['serial'];
                            }
                            $where = [];
                            $where['serial'] = ['in',$serial_code];
                            if(!empty($params['purchase_id'][$k])){
                                $where['purchase_code'] = $params['purchase_id'][$k];
                            }
                            $goods_serial = Db::name('kuerp_goods_serial')->where($where)->column('serial');
                            if(empty($goods_serial)){
                                $this->error('商品序列号不存在');
                            }
                            foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                if(!in_array($serial_code_v,$goods_serial)){
                                    $this->error($serial_code_v.'商品序列号不存在');
                                }
                            }
                            $goods_serials = Db::name('kuerp_goods_serial')->where(['status'=>['in',[2,3]],'serial'=>['in',$serial_code]])->column('serial');
                            if(!empty($goods_serials)){
                                $goods_serials = implode(',',$goods_serials);
                                $this->error('商品序列号'.$goods_serials.'已经待出库，不可重复出库');
                            }
                        }

                        if(!empty($params['info_id'][$k])){
                            $unit_id_order = Db::name('kuerp_goods_sales_order_info')->where(['id'=>$params['info_id'][$k]])->value('goods_unit');
                            if(!empty($unit_id_order) && $unit_id_order!=$params['goods_unit'][$k]){
                                $this->error('选择的商品单位与源销货订单不一致，保存失败');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],//客户
                            'sale_user'         =>  (int)$params['sale_user'],//销售员
                            'receiving_address' =>  $params['receiving_address'],

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],

                            'stores_id'         =>  $params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'order_info_id'     =>  (float)$params['info_id'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'cost_price'        =>  0,
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'batch_id'          => isset($batch_data['id'])?$batch_data['id']:'',
                            'batch_name'        => isset($batch_data['batch_name'])?$batch_data['batch_name']:'',
                            'manufacture'       => strtotime($params['manufacture'][$k]),
                            'guarantee'         => $params['guarantee'][$k],
                            'validity'          => strtotime($params['validity'][$k]),

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],
                            'purchase_money'    =>  (float)$params['purchase_money'][$k],
                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],

                            'pay_status'        =>  isset($pay_status)?$pay_status:'0',
                            'pay_money'         =>  isset($pay_money)?$pay_money:0,
                            'account'           =>  isset($pay_status)?$params['account']:0,

                            'purchase_order_id' =>  $params['purchase_order_id'][$k],

                            'createtime'        => time(),
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                            'serial_code'       => $serial_code,
                            'goods_batch_info'  => $goods_batch_info,
                        ];
                    }
                }
                $preferential_money = (float)$params['preferential_money'];
                if(!empty($data)){
                    if($all_now_pay_money>0){
                        $more_money = $all_now_pay_money+$preferential_money;
                        $need_money = $money_tax_price+(float)$params['customers_money'];
                        $more_money = round($more_money,4);
                        $need_money = round($need_money,4);
                        if($more_money==$need_money){
                            $pay_status = 1;
                            $pay_money = ($money_tax_price+(float)$params['customers_money'])-$preferential_money;
                        }else{
                            $pay_status = 2;
                            $pay_money = $all_now_pay_money;
                        }
                    }

                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  isset($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                        'customers_id'      =>  (int)$params['customers_id'],//客户
                        'sale_user'         =>  (int)$params['sale_user'],//销售员
                        'receiving_address' =>  $params['receiving_address'],
                        'customers_money'   =>  (float)$params['customers_money'],

                        'number'            =>  $number,
                        'all_money'         =>  $all_money,

                        'tax_money'         =>  $tax_money,//税
                        'money_tax_price'   =>  $money_tax_price,//价税合计

                        'preferential_rate' =>  (float)$params['preferential_rate'],
                        'preferential_money'=>  (float)$params['preferential_money'],
                        'real_money'        =>  (float)$params['real_money'],
                        'purchase_money'    =>  (float)$params['costs'],
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],

                        'arrears'           =>  $params['arrears'],
                        'all_arrears'       =>  $params['add_arrears'],

                        'purchase_order_id' =>  $purchase_order_id,

                        'pay_status'        =>  isset($pay_status)?$pay_status:'0',
                        'pay_money'         =>  isset($pay_money)?$pay_money:0,
                        'account'           =>  isset($pay_status)?$params['account']:0,
                        'cost_data'         =>  isset($params['cost_data'])?$params['cost_data']:null,
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code'])])->find()){
                    $this->error('单据编号已占用');
                }
                if($purchase_order_id){
                    $order_id = Db::name('kuerp_goods_sales_order')->where(['purchase_code'=>$purchase_order_id])->value('id');
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
                                'type'          => '5',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $info_id = Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$params['purchase_code']])->column('id');
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

                    if(!empty($info_id)){
                        Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$info_id]])->delete();
                    }
                    Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$params['purchase_code']])->delete();
                    foreach ($data as $data_k=>$data_v){
                        $goods_serial = $data_v['serial_code'];
                        unset($data_v['serial_code']);

                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);

                        $purchase_id = Db::name('kuerp_goods_sales_info')->insertGetId($data_v);

                        //批次
                        if($goods_batch_info){
                            $goods_batch_info['purchase_info_id'] = $purchase_id;
                            Db::name('kuerp_goods_batch_sale_info')->insert($goods_batch_info);
                        }

                        if(!empty($goods_serial)){
                            Db::name('kuerp_goods_serial')->where(['serial'=>['in',$goods_serial]])->update([
                                'status'=>'3',
                                'sale_id' => $purchase_id,
                                'sale_code' => $data_v['purchase_code']
                            ]);
                        }

                        if(!empty($data_v['order_info_id'])){
                            Db::name('kuerp_goods_saleord_info')->insert([
                                'order_id'=>$data_v['order_info_id'],
                                'purchase_id'=>$purchase_id,
                                'number'=>$data_v['number'],
                                'money'=>$data_v['pay_money'],
                                'all_money'=>$data_v['all_money'],
                                'tax_rate'=>$data_v['tax_rate'],
                                'tax_money'=>$data_v['tax_money'],
                                'money_tax_price'=>$data_v['money_tax_price']
                            ]);
                        }
                    }
                    if(!empty($purchase_order_id)){
                        if($result){
                            Db::name('kuerp_goods_saleord')->insert(['order_id'=>$order_id,'purchase_id'=>$result]);
                            // 在订单上记录"生成销货单"日志（统一使用 GoodsSalesOrderLog）
                            GoodsSalesOrderLog::writeLog($order_id, '生成销货单', '生成销货单：' . $params['purchase_code'], 'sales', $result);
                        }
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    // 写入操作日志
                    $this->writeSalesLog($result, '创建', '创建销货单');

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
        $code = \addons\kuerp\library\Goods::getCodes('',11);
        $this->view->assign("code", $code);

        $row = Db::name('kuerp_goods_sales_order')->where(['id'=>$ids])->find();

        $last_customer = Db::name('kuerp_sets')->where(['name'=>'customer'])->value('is_checked');
        $set_customer = '';
        if($last_customer){
            $set_customer = Db::name('kuerp_goods_sales')->order('id','DESC')->value('customers_id');
        }
        $address = Db::name('kuerp_customers')->where(['id'=>$set_customer])->value('getgoods_address');
        $last_read = Db::name('kuerp_sets')->where(['name'=>'last_read'])->value('is_checked');
        $sale_user = '';
        if($last_read){
            $sale_user = Db::name('kuerp_goods_sales')->order('id','DESC')->value('sale_user');
        }

        $give_account = 0;$real_money = 0;$arrears = 0;$real_moneys = 0;
        if($row){
            $purchase_info = Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                $purord_info = Db::name('kuerp_goods_saleord_info')->where(['order_id'=>$purchase_info_v['id']])->field('sum(number) as number,sum(money) as money')->select();
                $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
                $real_money += isset($purord_info[0]['money'])?$purord_info[0]['money']:0;
                $real_moneys += $purchase_info_v['money_tax_price']-$purchase_info_v['preferential_money']-($gp_number)*$purchase_info_v['tax_included'];
                if($purchase_info_v['number']-$gp_number<=0){
                    unset($purchase_info[$purchase_info_k]);
                }else{
                    $unit = json_decode($this->getUnit($purchase_info_v['goods_code']),true);
                    foreach ($unit['unit'] as $unit_k=>$unit_v){
                        if($unit_v['id']==$purchase_info_v['goods_unit']){
                            $purchase_info[$purchase_info_k]['basic_nmber'] = ($purchase_info_v['number']-$gp_number)*$unit_v['basic_nmber'];
                        }
                    }
                    $purchase_info[$purchase_info_k]['unit'] = $unit;
                    $purchase_info[$purchase_info_k]['all_money'] = ($purchase_info_v['number']-$gp_number)*$purchase_info_v['unit_price'];
                    $purchase_info[$purchase_info_k]['money_tax_price'] = ($purchase_info_v['number']-$gp_number)*$purchase_info_v['tax_included'];
                    $purchase_info[$purchase_info_k]['tax_money'] = ($purchase_info_v['number']-$gp_number)*$purchase_info_v['tax_money']/$purchase_info_v['number'];
                    $purchase_info[$purchase_info_k]['dis_money'] = $purchase_info[$purchase_info_k]['all_money']*($purchase_info_v['discount'])/100;
                    $arrears += $purchase_info[$purchase_info_k]['all_money'] -= $purchase_info[$purchase_info_k]['dis_money'];
                    $purchase_info[$purchase_info_k]['purchase_number'] = $purchase_info_v['number']-$gp_number;
                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$purchase_info_v['goods_code']])->field('batch,serial')->find();
                    $purchase_info[$purchase_info_k]['goods'] = $goods;
                }
            }
            $give_account = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->value('need_receive_account');
        }else{
            $row['customers_id'] = $set_customer;
            $row['receiving_address'] = $address;
            $row['sale_user'] = $sale_user;
        }
        $row['real_money'] = $real_moneys;
        $purchase_info = isset($purchase_info)?$purchase_info:[];
        $this->assignconfig("give_account", $give_account);
        $give_account = $give_account+$real_money+$row['real_money'];

        // 来自销货排期：预填商品列表
        $schedule_id = $this->request->param('schedule_id');
        if ($schedule_id && empty($purchase_info)) {
            $schedule = Db::name('kuerp_goods_sales')->where(['id' => $schedule_id])->find();
            if ($schedule) {
                $info_list = Db::name('kuerp_goods_sales_info')
                    ->where(['purchase_code' => $schedule['purchase_code']])
                    ->select();
                $sche_goods = [];
                foreach ($info_list as $v) {
                    $unit = json_decode($this->getUnit($v['goods_code']), true);
                    $sche_goods[] = [
                        'id' => '',
                        'unit' => $unit,
                        'goods_code' => $v['goods_code'],
                        'goods_note' => $v['goods_note'],
                        'goods_attr' => $v['goods_attr'],
                        'goods_unit' => $v['goods_unit'],
                        'stores_id' => $v['stores_id'],
                        'allow_stock' => $v['allow_stock'],
                        'purchase_number' => $v['number'],
                        'default_unit' => $v['default_unit'],
                        'basic_nmber' => $v['basic_nmber'],
                        'unit_price' => 0,
                        'tax_included' => 0,
                        'all_money' => 0,
                        'tax_rate' => 0,
                        'tax_money' => 0,
                        'money_tax_price' => 0,
                        'discount' => 0,
                        'dis_money' => 0,
                        'purchase_money' => 0,
                        'remark' => $v['remark'],
                        'purchase_code' => '',
                        'order_info_id' => 0,
                    ];
                }
                $purchase_info = $sche_goods;
                $row['customers_id'] = $schedule['customers_id'];
                $row['sale_user'] = $schedule['sale_user'];
                $row['receiving_address'] = $schedule['receiving_address'];
                $row['real_money'] = 0;
            }
        }

        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);
        $this->view->assign("give_account", $give_account??0);

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'4'])->column('enclosure_id');
        if(!empty($enclosure_id)){
            $this->assign('enclosure_count',sizeof($enclosure_id));
            $enclosure_id = implode(',',$enclosure_id);
        }else{
            $enclosure_id = '';
        }
        $this->assign('enclosure_ids',$enclosure_id);

        $this->assignconfig("suppliersList", $this->model->getSuppliersList());
        $this->assignconfig("expendTypeList", $this->model->getExpendTypeList());
        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 如果销货单没有交货时间，从关联的销货订单获取
        if (empty($row['delivery_time']) && !empty($row['purchase_order_id'])) {
            $orderDeliveryTime = Db::name('kuerp_goods_sales_order')
                ->where('purchase_code', $row['purchase_order_id'])
                ->value('delivery_time');
            if ($orderDeliveryTime) {
                $row['delivery_time'] = $orderDeliveryTime;
            }
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
            if($row['check_status']==1){
                $this->error('不能编辑已对单的数据');
            }
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);
                $info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$old_purchase_code])->column('id');

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');
                if($params['now_pay_money']>0){
                    if(empty($params['account'])){
                        $this->error('请选择结算账户');
                    }
                    $all_now_pay_money = $now_pay_money = trim($params['now_pay_money']);
                }else{
                    $all_now_pay_money = $now_pay_money = 0;
                }

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;$money_tax_price=0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        $money_tax_price += (float)$params['money_tax_price'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        unset($pay_money);unset($pay_status);
                        if($now_pay_money>0){
                            if($now_pay_money-$params['money_tax_price'][$k]>=0){
                                $pay_status = 1;
                                $pay_money = $params['money_tax_price'][$k];
                                $now_pay_money -= $pay_money;
                            }else{
                                $pay_status = 2;
                                $pay_money = $now_pay_money;
                                $now_pay_money -= $now_pay_money;
                            }
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        $goods_batch_info = [];
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            if(empty($batch_data)){
                                $this->error('生产批次不存在');
                            }
                            $goods_batch_info = [
                                'batch_id'    => isset($batch_data['id'])?$batch_data['id']:'',
                                'goods_id'    => $goods['id'],
                                'goods_code'  => $params['goods_code'][$k],
                                'attribute_id'=>isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                'goods_unit'  =>  (int)$params['goods_unit'][$k],
                                'stores_id'   => $params['stores_id'][$k],
                                'stock'       => (float)$params['number'][$k],
                                'number'      => (float)$params['number'][$k],
                                'batch_name'  => isset($batch_data['batch_name'])?$batch_data['batch_name']:'',
                                'manufacture' => strtotime($params['manufacture'][$k]),
                                'guarantee'   => $params['guarantee'][$k],
                                'validity'    => strtotime($params['validity'][$k]),
                                'status'      => '0',
                                'type'        => 2,
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                        }

                        $serial_code = [];
                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $period_period = unserialize($params['period_period'][$k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                $serial_code[] = $period_period_v['serial'];
                            }
                            $where = [];
                            $where['serial'] = ['in',$serial_code];
                            if(!empty($params['purchase_id'][$k])){
                                $where['purchase_code'] = $params['purchase_id'][$k];
                            }
                            $goods_serial = Db::name('kuerp_goods_serial')->where($where)->column('serial');
                            if(empty($goods_serial)){
                                $this->error('商品序列号不存在');
                            }
                            foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                if(!in_array($serial_code_v,$goods_serial)){
                                    $this->error($serial_code_v.'商品序列号不存在');
                                }
                            }
                            $goods_serials = Db::name('kuerp_goods_serial')->where(['status'=>['in',[2,3]],'serial'=>['in',$serial_code],'purchase_exchange_id'=>['not in',$info_id]])->column('serial');
                            if(!empty($goods_serials)){
                                $goods_serials = implode(',',$goods_serials);
                                $this->error('商品序列号'.$goods_serials.'已经待出库，不可重复出库');
                            }
                        }

                        if(!empty($params['info_id'][$k])){
                            $unit_id_order = Db::name('kuerp_goods_sales_order_info')->where(['id'=>$params['info_id'][$k]])->value('goods_unit');
                            if(!empty($unit_id_order) && $unit_id_order!=$params['goods_unit'][$k]){
                                $this->error('选择的商品单位与源购货订单不一致，保存失败');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],//客户
                            'sale_user'         =>  (int)$params['sale_user'],//销售员
                            'receiving_address' =>  $params['receiving_address'],

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'order_info_id'     =>  (float)$params['info_id'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'cost_price'        =>  0,
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'batch_id'          => isset($batch_data['id'])?$batch_data['id']:'',
                            'batch_name'        => isset($batch_data['batch_name'])?$batch_data['batch_name']:'',
                            'manufacture'       => strtotime($params['manufacture'][$k]),
                            'guarantee'         => $params['guarantee'][$k],
                            'validity'          => strtotime($params['validity'][$k]),

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],
                            'purchase_money'    =>  (float)$params['purchase_money'][$k],
                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],

                            'pay_status'        =>  isset($pay_status)?$pay_status:'0',
                            'pay_money'         =>  isset($pay_money)?$pay_money:0,
                            'account'           =>  isset($pay_status)?$params['account']:0,

                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                            'serial_code'       => $serial_code,
                            'goods_batch_info'  => $goods_batch_info,
                        ];
                    }
                }
                $preferential_money = (float)$params['preferential_money'];
                if(!empty($data)){
                    if($all_now_pay_money>0){//当次支付金额
                        $more_money = $all_now_pay_money+$preferential_money;
                        $need_money = $money_tax_price+(float)$params['customers_money'];
                        $more_money = round($more_money,4);
                        $need_money = round($need_money,4);
                        if($more_money==$need_money){
                            $pay_status = 1;
                            $pay_money = ($money_tax_price+(float)$params['customers_money'])-$preferential_money;
                        }else{
                            $pay_status = 2;
                            $pay_money = $all_now_pay_money;
                        }
                    }
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  isset($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                        'customers_id'      =>  (int)$params['customers_id'],//客户
                        'sale_user'         =>  (int)$params['sale_user'],//销售员
                        'receiving_address' =>  $params['receiving_address'],
                        'customers_money'   =>  (float)$params['customers_money'],

                        'number'            =>  $number,
                        'all_money'         =>  $all_money,

                        'tax_money'         =>  $tax_money,//税
                        'money_tax_price'   =>  $money_tax_price,//价税合计

                        'preferential_rate' =>  (float)$params['preferential_rate'],
                        'preferential_money'=>  (float)$params['preferential_money'],
                        'real_money'        =>  (float)$params['real_money'],
                        'purchase_money'    =>  (float)$params['costs'],
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],

                        'arrears'           =>  $params['arrears'],
                        'all_arrears'       =>  $params['add_arrears'],

                        'pay_status'        =>  isset($pay_status)?$pay_status:'0',
                        'pay_money'         =>  isset($pay_money)?$pay_money:0,
                        'account'           =>  isset($pay_status)?$params['account']:0,
                        'cost_data'         =>  isset($params['cost_data'])?$params['cost_data']:null,
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }
                $info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->column('id');

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
                                'type'          => '5',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }

                    //添加附件记录
                    Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'5'])->delete();
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    if(!empty($info_id)){
                        Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$info_id]])->delete();
                    }

                    $purchase_exchange_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$old_purchase_code])->column('id');
                    Db::name('kuerp_goods_serial')->where(['sale_id'=>['in',$purchase_exchange_id],'status'=>'3'])->update([
                        'status'=>'1',
                    ]);
                    Db::name('kuerp_goods_batch_sale_info')->where(['purchase_info_id'=>['in',$purchase_exchange_id]])->delete();

                    Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->delete();

                    foreach ($data as $data_k=>$data_v){
                        $goods_serial = $data_v['serial_code'];
                        unset($data_v['serial_code']);

                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);

                        $purchase_id = Db::name('kuerp_goods_sales_info')->insertGetId($data_v);

                        //批次
                        if($goods_batch_info){
                            $goods_batch_info['purchase_info_id'] = $purchase_id;
                            Db::name('kuerp_goods_batch_sale_info')->insert($goods_batch_info);
                        }

                        if(!empty($goods_serial)){
                            Db::name('kuerp_goods_serial')->where(['serial'=>['in',$goods_serial]])->update([
                                'status'=>'3',
                                'sale_id' => $purchase_id,
                                'sale_code' => $data_v['purchase_code']
                            ]);
                        }

                        if(!empty($data_v['order_info_id'])){
                            Db::name('kuerp_goods_saleord_info')->insert([
                                'order_id'=>$data_v['order_info_id'],
                                'purchase_id'=>$purchase_id,
                                'number'=>$data_v['number'],
                                'money'=>$data_v['pay_money'],
                                'all_money'=>$data_v['all_money'],
                                'tax_rate'=>$data_v['tax_rate'],
                                'tax_money'=>$data_v['tax_money'],
                                'money_tax_price'=>$data_v['money_tax_price']
                            ]);
                        }
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    // 写入操作日志
                    $this->writeSalesLog($ids, '编辑', '编辑销货单');

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

        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['serial'] = $purchase_info_v['period'];
            $purchase_info[$purchase_info_k]['unit'] = json_decode($this->getUnit($purchase_info_v['goods_code']),true);
        }
        $this->view->assign("purchase_info", $purchase_info);

        $give_account = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->value('need_receive_account');
        if($row['examine_status']==1){
            $this->assignconfig("give_account", $give_account-$row['arrears']);
            $this->view->assign("give_account", (float)$give_account);
        }else{
            $this->assignconfig("give_account", $give_account);
            $this->view->assign("give_account", (float)$give_account+$row['arrears']);
        }

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'5'])->column('enclosure_id');
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
        $this->assignconfig("suppliersList", $this->model->getSuppliersList());
        $this->assignconfig("expendTypeList", $this->model->getExpendTypeList());

        // 查询催收记录
        $dunningList = Db::name('kuerp_goods_sales_dunning')
            ->where('sales_id', $ids)
            ->order('id', 'desc')
            ->select();
        foreach ($dunningList as $dk => $dv) {
            $admin = Db::name('admin')->where('id', $dv['admin_id'])->value('username');
            $dunningList[$dk]['admin_username'] = $admin ?: '';
        }
        $this->view->assign('dunningList', $dunningList);

        // 查询操作日志
        $logList = Db::name('kuerp_goods_sales_log')
            ->where('sales_id', $ids)
            ->order('id', 'desc')
            ->select();
        foreach ($logList as $lk => $lv) {
            $admin = Db::name('admin')->where('id', $lv['admin_id'])->value('username');
            $logList[$lk]['admin_username'] = $admin ?: '';
        }
        $this->view->assign('logList', $logList);

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
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $goods_purchase_id = [];$goods_purchase_code = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['examine_status']==1){
                    $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                    if($checkout!==true){
                        $this->error($checkout);
                    }
                }
                if($list_v['check_status']==1){
                    $this->error('不能删除已对单的数据');
                }
                if($list_v['examine_status']==1 && $examine==1){
                    $this->error('不能删除已审核的数据');
                }elseif ($list_v['examine_status']==1 && $examine!=1){
                    try {
                        $this->examine($list_v['id'],2);
                    } catch (Exception $e) {
                    }
                }
                if(!in_array($list_v['id'],$goods_purchase_id)){
                    $goods_purchase_id[] = $list_v['id'];
                }
                if(!in_array($list_v['purchase_code'],$goods_purchase_code)){
                    $goods_purchase_code[] = $list_v['purchase_code'];
                }
            }
            $goods_purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$goods_purchase_code]])->column('id');

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    $exchange_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$v['purchase_code']])->column('id');
                    Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
                    Db::name('kuerp_goods_serial')->where(['sale_id'=>['in',$exchange_info]])->update(['status'=>'1']);
                    // 写入操作日志
                    $this->writeSalesLog($v['id'], '删除', '删除销货单');
                }
                Db::name('kuerp_goods_sales_summary')->where(['purchase_code'=>['in',$goods_purchase_code]])->delete();
                if(!empty($goods_purchase_id)){//销货订单关联
                    Db::name('kuerp_goods_saleord')->where(['purchase_id'=>['in',$goods_purchase_id]])->delete();
                    Db::name('kuerp_goods_batch_sale_info')->where(['purchase_id'=>['in',$goods_purchase_id]])->delete();
                }
                if(!empty($goods_purchase_info)){//销货订单详情关联
                    Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$goods_purchase_info]])->delete();
                }

                //删除附件记录
                Db::name('kuerp_enclosure_log')->where(['purchase_code'=>$v['purchase_code'],'type'=>'5'])->delete();
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

    //对单/反对单
    public function counter($ids = null,$counter=1){
        if($counter==1){
            $list = $this->model->where(['id'=>['in',$ids],'check_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                $this->error('请选择需要对单的数据');
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>['in',$ids],'check_status'=>'0'])->update(['check_status'=>1,'operation_id'=>$admin['id']]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                // 写入操作日志
                $logIds = is_array($ids) ? $ids : explode(',', trim($ids, ','));
                foreach ($logIds as $logId) {
                    $this->writeSalesLog((int)$logId, '对单', '对单');
                }
                $this->success(sizeof($list).'条数据对单成功');
            }else{
                $this->error('对单失败');
            }
        }else{
            $list = $this->model->where(['id'=>['in',$ids],'check_status'=>'1'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                $this->error('请选择需要反对单的数据');
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>['in',$ids],'check_status'=>'1'])->update(['check_status'=>'0','operation_id'=>$admin['id']]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                // 写入操作日志
                $logIds = is_array($ids) ? $ids : explode(',', trim($ids, ','));
                foreach ($logIds as $logId) {
                    $this->writeSalesLog((int)$logId, '反对单', '反对单');
                }
                $this->success(sizeof($list).'条数据反对单成功');
            }else{
                $this->error('反对单失败');
            }
        }
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
            $set_discount = Db::name('kuerp_sets')->where(['name'=>'discount'])->value('is_checked');

            foreach ($list as $list_k=>$list_v){
                if($list_v['check_status']==1){
                    $this->error('不能反审核已对单的数据');
                }
            }
            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }
            // 审核仅设置审核状态，不做库存变动（库存变动由销货出库操作完成）
            $admin = Session::get('admin');
            $examine_ids = [];
            foreach ($list as $list_v){
                $examine_ids[] = $list_v['id'];
            }
            Db::startTrans();
            try {
                $this->model->where(['id'=>['in',$examine_ids],'examine_status'=>'0'])->update([
                    'examine_status' => 1,
                    'examine_id'     => $admin['id'],
                    'examine_time'   => time(),
                    'operation_id'   => $admin['id'],
                ]);
                Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$purchase_code]])->update([
                    'examine_status' => 1,
                    'examine_id'     => $admin['id'],
                    'examine_time'   => time(),
                ]);
                foreach ($list as $list_v){
                    $this->writeSalesLog($list_v['id'], '审核', '审核通过，库存变动由出库操作完成');
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>$e->getMessage()];
                }
                $this->error($e->getMessage());
            }
            if($examine_set!=1){
                return ['status'=>1,'msg'=>'审核成功'];
            }
            $this->success('审核成功（库存变动请通过销货出库操作完成）');
            $account_list = [];
            foreach ($account_list_obj as $account_list_obj_k=>$account_list_obj_v){
                $account_list[$account_list_obj_v['id']] = $account_list_obj_v;
            }
            $examine_ids = [];
            $accountData = [];
            $arrears = [];
            $log = [];
            $payment_log = [];$invoicing = [];
            foreach ($list as $list_k=>$list_v){
                $examine_ids[] = $list_v['id'];
                if($list_v['arrears']>0){
                    $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->field('id,need_receive_account')->find();
                    if($suppliers){
                        $status = false;
                        foreach ($arrears as $arrears_k=>$arrears_v){
                            if($arrears_v['id']==$suppliers['id']){
                                $arrears[$arrears_k]['need_receive_account'] = $list_v['arrears']+$arrears_v['need_receive_account'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $arrears[] = [
                                'id'  => $suppliers['id'],
                                'need_receive_account'  => $list_v['arrears']+$suppliers['need_receive_account'],
                            ];
                        }
                    }
                }
                if($list_v['pay_money']>0){
                    $accountData[] = [
                        'balance'  => $list_v['pay_money'],
                        'account'  => $list_v['account'],
                    ];
                    $log[] = [
                        'account_coding'    => $account_list[$list_v['account']]['account_coding'],
                        'account_name'      => $account_list[$list_v['account']]['account_name'],
                        'balance'           => $account_list[$list_v['account']]['balance']-$list_v['pay_money'],
                        'income'            => $list_v['pay_money'],
                        'pay_time'          => $list_v['purchase_time'],
                        'bill_code'         => $list_v['purchase_code'],
                        'business_type'     => 5,
                        'customers_id'      => $list_v['customers_id'],
                        'remark'            => $list_v['note'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }
                $payment_log[] = [
                    'customers_id'   => $list_v['customers_id'],
                    'sale_user'      => $list_v['sale_user'],
                    'business_type'  => 1,
                    'bill_time'      => $list_v['purchase_time'],
                    'bill_code'      => $list_v['purchase_code'],
                    'bill_codes'     => $list_v['purchase_code'],
                    'purchase_money' => $list_v['all_money'],
                    'discount_money' => $list_v['preferential_money'],
                    'real_money'     => $list_v['real_money'],
                    'money_tax_price'=> $list_v['money_tax_price'],
                    'pay_money'      => $list_v['pay_money'],
                    'give_money'     => $list_v['arrears'],
                    'reamrk'         => $list_v['note'],
                    'createtime'     => time(),
                ];
                if($set_discount==1){
                    $money = $list_v['real_money'];
                }else{
                    $money = $list_v['money_tax_price'];
                }
                $invoicing[] = [
                    'customers_id'   => $list_v['customers_id'],
                    'status'         => $list_v['pay_status'],
                    'bill_time'      => $list_v['purchase_time'],
                    'bill_code'      => $list_v['purchase_code'],
                    'bill_codes'     => $list_v['purchase_code'],
                    'money'          => $list_v['real_money'],
                    'tax'            => $list_v['tax_money'],
                    'real_money'     => $money,
                    'pay_money'      => 0,
                    'give_money'     => $money,
                    'createtime'     => time(),
                ];
            }
            $editstock = [];$addstock = [];$goods_stock = [];
            $info_list = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$purchase_code]])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                try {
                    unset($info_list[$info_list_k]['period']);
                } catch (Exception $e) {
                }
            }

            $purchase_info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$purchase_code]])->column('id');

            $purchase_info = [];
            $goods_stock_log = [];//收发详情
            $serial_log = [];//序列号跟踪
            foreach ($info_list as $list_k=>$list_v){
                //序列号跟踪
                $goods_serial = Db::name('kuerp_goods_serial')->where(['sale_id'=>$list_v['id']])->field('goods_id,serial,stores_id,attribute_id')->select();
                foreach ($goods_serial as $goods_serial_k=>$goods_serial_v){
                    $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'=>$goods_serial_v['goods_id'],
                        'stores_id'=>$goods_serial_v['stores_id'],
                        'attribute_id'=>$goods_serial_v['attribute_id'],
                        'serial'=>$goods_serial_v['serial'],
                        'business_type'=>'4',
                        'store_status'=>'2',
                        'remarks' => $list_v['remark'],
                        'purchase_id'=>$list_v['id'],
                        'customer_id'=>$list_v['customers_id'],
                        'purchase_code'=>$list_v['purchase_code'],
                        'purchase_time' => $list_v['purchase_time'],
                        'createtime'  => time(),
                        'updatetime'  => time(),
                    ]);
                }
                //序列号跟踪

                $info_list[$list_k]['sales_order_id']    = $list_v['id'];
                $info_list[$list_k]['createtime']        = time();
                $info_list[$list_k]['updatetime']        = time();
                $stock_number = $list_v['basic_nmber'];
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('id,attribute_id,inventory,unit_id')->find();
                $status = false;
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['id']==$goods['id']){
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']-$stock_number;
                        $status = true;
                    }
                }
                if(!$status){
                    $goods_stock[] = [
                        'id'=>$goods['id'],
                        'inventory'=>$goods['inventory']-$stock_number,
                    ];
                }
                //商品库存
                $where = [];
                $where['goods_code'] = $list_v['goods_code'];
                $where['stores_id'] = $list_v['stores_id'];
                if($list_v['goods_attr']>0){
                    $goods_attr = $list_v['goods_attr'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }

                //获取成本单价
                $balance_cost=0;$balance_unit=0;
                $goodsInfo = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($list_v['goods_code'],$list_v['stores_id'],$list_v['goods_attr']),true);
                $unitData = isset($goodsInfo['unit'])?$goodsInfo['unit']:[];
                if(empty($unitData)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品信息获取错误'];
                    }
                    $this->error('商品信息获取错误');
                }else{
                    foreach ($unitData as $unitData_k=>$unitData_v){
                        if($unitData_v['id']==$goods['unit_id']){
                            $balance_unit = $unitData_v['purchase'];
                        }
                    }
                }
                //获取成本单价结束

                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] -= $stock_number;
                            $status = true;
                            $balance_cost   = (round($balance_unit*$stock_number,4));
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']-$stock_number,4))
                        ];
                        $balance_cost   = (round($balance_unit*$stock_number,4));
                    }
                }else{
                    $store = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->value('name');
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品'.$store.'库存不足'];
                    }
                    $this->error('商品'.$store.'库存不足');
                }
                $purchase_info[] = $list_v['id'];

                //收发详情
                if($goods){
                    $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code' => $list_v['purchase_code'],
                        'purchase_id'   => $list_v['id'],
                        'purchase_time' => $list_v['purchase_time'],
                        'business_type' => '4',
                        'customer_id'   => $list_v['customers_id'],
                        'goods_id'      => $goods['id'],
                        'goods_code'    => $list_v['goods_code'],
                        'attribute_id'  => (float)$list_v['goods_attr'],
                        'goods_unit'    => (float)$list_v['goods_unit'],
                        'stores_id'     => (float)$list_v['stores_id'],
                        'remark'        => $list_v['remark'],
                        'out_number'    => (float)$list_v['number'],
                        'out_basics'    => (float)$list_v['basic_nmber'],
                        'out_unit'      => $balance_unit,
                        'out_cost'      => $balance_cost,
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }
                //收发详情结束
            }
            foreach ($editstock as $editstock_k=>$editstock_v){
                if($editstock_v['stock']<0){
                    $store = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->value('name');
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足,操作失败'];
                    }
                    $this->error($store.'库存不足,操作失败');
                }
            }

            $order_count = [];
            $code_id = Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->column('order_id');
            $purchase_order_info = Db::name('kuerp_goods_sales_order_info')
                ->field('id,purchase_code')
                ->where(['id'=>['in',$code_id]])
                ->select();
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $purord_info = Db::name('kuerp_goods_saleord_info')
                    ->where(['order_id'=>$purchase_order_info_v['id'],'purchase_id'=>['in',$purchase_info]])->field('sum(number) as number,sum(money) as money,sum(all_money) as all_money')->find();
                $purchase_order_info[$purchase_order_info_k]['pay_count'] = isset($purord_info['number'])?$purord_info['number']:0;
                $purchase_order_info[$purchase_order_info_k]['pay_money'] = isset($purord_info['money'])?$purord_info['money']:0;
                $purchase_order_info[$purchase_order_info_k]['all_money'] = isset($purord_info['all_money'])?$purord_info['all_money']:0;
            }
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $status = false;
                foreach ($order_count as $order_count_k=>$order_count_v){
                    if($order_count_v['purchase_code']==$purchase_order_info_v['purchase_code']){
                        $order_count[$order_count_k]['store_number'] += $purchase_order_info_v['pay_count'];
                        $status = true;
                    }
                }
                if(!$status){
                    $order_count[] = [
                        'purchase_code'  => $purchase_order_info_v['purchase_code'],
                        'store_number'  => $purchase_order_info_v['pay_count'],
                    ];
                }
            }
            //修改状态
            if(!empty($order_count)){
                foreach ($order_count as $order_count_k=>$order_count_v){
                    $number = Db::name('kuerp_goods_sales_order')->where(['purchase_code'=>$order_count_v['purchase_code']])->field('id,number,store_number')->find();
                    if(($number['number']-$number['store_number'])<=$order_count_v['store_number']){
                        $order_count[$order_count_k]['status'] = 1;
                    }else{
                        $order_count[$order_count_k]['status'] = 2;
                    }
                    $order_count[$order_count_k]['id'] = $number['id'];
                }
            }

            //批次库存数据
            //获取设置
            $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
            $setList = [];
            foreach ($set as $set_k=>$set_v){
                if(!empty($set_v['set'])){
                    $set_v['set'] = unserialize($set_v['set']);
                }
                $setList[$set_v['name']] = $set_v;
            }
            //数量价格格式化
            $number_decimal = $setList['number_decimal']['value'];
            $goods_serial = Db::name('kuerp_goods_sales_info')->where(['id'=>['in',$purchase_info_id]])->select();

            $goods_batch_add = [];$goods_batch_edit = [];$goods_batch_log = [];
            foreach ($goods_serial as $k=>$v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v['goods_code']])->field('id,goods_name,attribute_id,inventory,unit_id,batch')->find();
                if($goods['batch']==1){
                    //先通过单位计算需修改多单位商品的库存数量
                    if($goods['unit_id']==$v['goods_unit']){
                        $stock_number = $v['number'];
                    }else{
                        $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$v['goods_unit']])->value('advance_rate');
                        if(empty($unit_conversion)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                            }
                            $this->error('商品单位信息查询失败');
                        }else{
                            $stock_number = round(($v['number']*$unit_conversion),$number_decimal);
                        }
                    }

                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if($v['goods_attr']){
                        $where['attribute_id'] = $v['goods_attr'];
                    }
                    $where['stores_id'] = $v['stores_id'];
                    $where['batch_id'] = $v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $status = false;
                        foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                            if($goods_batch_add_v['batch_id']==$v['batch_id'] && $goods_batch_add_v['goods_id']==$v['goods_id'] && $goods_batch_add_v['attribute_id']==$v['attribute_id'] && $goods_batch_add_v['stores_id']==$v['stores_id']){
                                $goods_batch_add[$goods_batch_add_k]['stock'] -= $stock_number;
                                $status = true;
                            }
                        }
                        if(!$status){
                            $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                                'batch_id' => $v['batch_id'],
                                'goods_id' => $goods['id'],
                                'goods_code' => $v['goods_code'],
                                'attribute_id' => $v['goods_attr'],
                                'stores_id' => $v['stores_id'],
                                'stock' => $stock_number,
                                'createtime' => time(),
                                'updatetime' => time(),
                            ]);
                        }
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                    }
                    foreach ($goods_batch_edit as $k2=>$v2){
                        if($v2<0){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                            }
                            $this->error('商品'.$goods['goods_name'].'库存不足');
                        }
                    }

                    //商品批次跟踪
                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'goods_unit'    => $v['goods_unit'],
                        'stores_id'     => $v['stores_id'],
                        'batch_id'      => (float)$v['batch_id'],
                        'number'        => (float)$v['number'],
                        'purchase_code' => $v['purchase_code'],
                        'purchase_time' => $v['purchase_time'],
                        'business_type' => '4',
                        'remarks'       => $v['remark'],
                        'order_id'      => $v['id'],
                        'unit_price'    => $v['unit_price'],
                        'tax_included'  => $v['tax_included'],
                        'all_money'     => $v['all_money'],
                        'tax_money'     => $v['tax_money'],
                        'money_tax_price' => $v['money_tax_price'],
                        'customer_id'   => $v['customers_id'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }
                //商品批次跟踪结束
            }
            //批次库存数据结束

            //应收账款明细表
            $ex_list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            $accounts_receivable = [];
            foreach ($ex_list as $ex_list_k=>$ex_list_v){
                $accounts_receivable[] = \addons\kuerp\library\Goods::setDataInfo([
                    'customers_id'      => $ex_list_v['customers_id'],
                    'purchase_time'     => $ex_list_v['purchase_time'],
                    'purchase_code'     => $ex_list_v['purchase_code'],
                    'business_type'     => '6',
                    'receivable'        => $ex_list_v['arrears'],
                    'staff_member_id'   => $ex_list_v['sale_user'],
                    'remark'            => $ex_list_v['note'],
                    'sale_money'        => $ex_list_v['money_tax_price'],
                    'receivable_money'  => round(($ex_list_v['real_money']+$ex_list_v['customers_money']),4),
                    'amount'            => $ex_list_v['preferential_money'],
                    'cost'              => $ex_list_v['customers_money'],
                    'real_money'        => $ex_list_v['pay_money'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);
            }

            $admin = Session::get('admin');

            //采购销售费用
            $cost_data = [];
            foreach ($list as $list_k=>$list_v){
                if(!empty($list_v['cost_data'])){
                    $cost_datas = unserialize($list_v['cost_data']);
                    foreach ($cost_datas as $cost_datas_k=>$cost_datas_v){
                        $list_v['supplyer_code'] = isset($list_v['supplyer_code'])?$list_v['supplyer_code']:'';
                        $cost_data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_time'     => time(),
                            'purchase_code'     => \addons\kuerp\library\Goods::getCodes('',27),
                            'business_type'     => '2',
                            'suppliers_ids'     => isset($list_v['customers_id'])?$list_v['customers_id']:null,
                            'suppliers_id'      => $cost_datas_v['suppliers_id'],
                            'expend_type_id'    => $cost_datas_v['expend_type_id'],
                            'purchase_number'   => $list_v['purchase_code'],
                            'purchase_times'    => $list_v['purchase_time'],
                            'money'             => $cost_datas_v['money'],
                            'pay_money'         => $cost_datas_v['money'],
                            'remark'            => $cost_datas_v['remark'],
                            'preparation_id'    => $admin['id'],
                            'operation_id'      => $admin['id'],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                    }
                }
            }

            //销售利润表
            $profit = [];
            foreach ($list as $list_k=>$list_v){
                $amount = null;
                $amounts = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$list_v['purchase_code']])->sum('dis_money');
                if($amounts>0){
                    $amount = $amounts;
                }
                $profit[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_type' => '1',
                    'purchase_code' => $list_v['purchase_code'],
                    'purchase_time' => $list_v['purchase_time'],
                    'customers_id'  => $list_v['customers_id'],
                    'sale_user'     => $list_v['sale_user'],
                    'preparation_id'=> $list_v['preparation_id'],
                    'examine_id'    => $admin['id'],
                    'number'        => $list_v['number'],
                    'amount'        => $amount,
                    'all_money'     => $list_v['all_money'],
                    'tax_money'     => $list_v['tax_money'],
                    'money_tax_price'=> $list_v['money_tax_price'],
                    'preferential_money'=> $list_v['preferential_money'],
                    'purchase_money'=> $list_v['purchase_money'],
                    'real_money'    => $list_v['real_money'],
                    'customers_money'=> $list_v['customers_money'],
                    'need_money'    => $list_v['real_money']+$list_v['customers_money'],
                    'pay_money'     => $list_v['pay_money'],
                    'remark'        => $list_v['note'],
                ]);
            }

            //核销单源单
            $writeoff_source = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['arrears']>0){
                    $writeoff_source[] = [
                        'purchase_type' => 3,
                        'type' => 1,
                        'customers_id' => $list_v['customers_id'],
                        'purchase_code' => $list_v['purchase_code'],
                        'purchase_time' => $list_v['purchase_time'],
                        'price' => $list_v['real_money'],
                        'money' => $list_v['pay_money'],
                        'write_off' => $list_v['arrears'],
                        'remark' => $list_v['note'],
                    ];
                }
            }

            Db::startTrans();
            try {
                if(!empty($writeoff_source)){
                    Db::name('kuerp_writeoff_source')->insertAll($writeoff_source);
                }

                if(!empty($profit)){
                    Db::name('kuerp_goods_sales_profit')->insertAll($profit);
                }
                if(!empty($cost_data)){
                    Db::name('kuerp_cost')->insertAll($cost_data);
                }
                if(!empty($accounts_receivable)){
                    Db::name('kuerp_accounts_receivable')->insertAll($accounts_receivable);
                }

                //序列号跟踪
                if(!empty($serial_log)){
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                }
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                //批次库存数据
                if(!empty($goods_batch_log)){
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
                }
                if(!empty($goods_batch_add)){
                    foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                        $where = [];
                        $where['goods_code'] = $goods_batch_add_v['goods_code'];
                        $where['stores_id'] = $goods_batch_add_v['stores_id'];
                        $where['batch_id'] = $goods_batch_add_v['batch_id'];
                        if($goods_batch_add_v['attribute_id']>0){
                            $where['attribute_id'] = $goods_batch_add_v['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if($stock){
                            Db::name('kuerp_goods_batch_stock')->where(['id'=>$stock['id']])->inc('stock',$goods_batch_add_v['stock'])->update();
                        }else{
                            Db::name('kuerp_goods_batch_stock')->insert($goods_batch_add_v);
                        }
                    }
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                //批次库存数据结束

                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_serial')->where(['sale_id'=>['in',$purchase_info_id]])->update(['store_status'=>'2']);
                    Db::name('kuerp_goods_batch_sale_info')->where(['purchase_info_id'=>['in',$purchase_info_id]])->update(['status'=>'1']);
                }
                if(!empty($order_count)){
                    $goodsSalesOrder = (new \app\admin\model\kuerp\GoodsSalesOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsSalesOrder->where(['id'=>$order_count_v['id']])->inc('store_number',$order_count_v['store_number'])->update();
                        // 在订单上记录"销货出库"日志（统一使用 GoodsSalesOrderLog）
                        GoodsSalesOrderLog::writeLog($order_count_v['id'], '销货出库', '销售出库，关联销货单：' . implode(',', $purchase_code), 'sales', $ids);
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsSalesOrder->saveAll($order_count);
                }
                if(!empty($payment_log)){
                    (new \app\admin\model\kuerp\SalesPaymentLog())->saveAll($payment_log);
                }
                if(!empty($invoicing)){
                    (new \app\admin\model\kuerp\SalesInvoicing())->saveAll($invoicing);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                if(!empty($log)){
                    Db::name('kuerp_accounts_log')->insertAll($log);
                }
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($arrears)){
                    (new \app\admin\model\kuerp\Customers())->saveAll($arrears);
                }
                Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->update(['status'=>'1','times'=>time()]);
                foreach ($info_list as $info_list_k=>$info_list_v){
                    if(isset($info_list_v['id'])){
                        unset($info_list[$info_list_k]['id']);
                    }
                    if(isset($info_list_v['out_number'])){
                        unset($info_list[$info_list_k]['out_number']);
                    }
                }
                Db::name('kuerp_goods_sales_summary')->insertAll($info_list);
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['id'=>$accountData_v['account']])->inc('balance',$accountData_v['balance'])->update();
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                // 写入操作日志
                $logIds = is_array($ids) ? $ids : explode(',', trim($ids, ','));
                foreach ($logIds as $logId) {
                    $this->writeSalesLog((int)$logId, '审核', '审核通过');
                }
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('审核失败');
            }
        }else{
            $plist = $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('请选择需要反审核的数据');
            }
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                if($list_v['check_status']==1){
                    $this->error('不能反审核已对单的数据');
                }
                $purchase_id = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$list_v['id'],'type'=>'2'])->column('exchange_id');
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成退货单的数据');
                }
                $purchase_id = Db::name('kuerp_collection_sale')->where(['purchase_number'=>$list_v['purchase_code']])->find();
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成收款单的数据'];
                    }
                    $this->error('不能反审核已生成收款单的数据');
                }
                $purchase_id = Db::name('kuerp_writeoff_info')->where(['purchase_number'=>$list_v['purchase_code']])->find();
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成核销单的数据'];
                    }
                    $this->error('不能反审核已生成核销单的数据');
                }
            }
            $arrears = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['arrears']>0){
                    $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->field('id,need_receive_account')->find();
                    if($suppliers){
                        $status = false;
                        foreach ($arrears as $arrears_k=>$arrears_v){
                            if($arrears_v['id']==$suppliers['id']){
                                $arrears[$arrears_k]['need_receive_account'] = $arrears_v['need_receive_account']-$list_v['arrears'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $arrears[] = [
                                'id'  => $suppliers['id'],
                                'need_receive_account'  => $suppliers['need_receive_account']-$list_v['arrears'],
                            ];
                        }
                    }
                }
            }
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $account_list_obj = Db::name('kuerp_accounts')->field('id,account_coding,account_name,balance')->select();
            $account_list = [];
            foreach ($account_list_obj as $account_list_obj_k=>$account_list_obj_v){
                $account_list[$account_list_obj_v['id']] = $account_list_obj_v;
            }

            $log = Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$list],'business_type'=>'5'])->select();
            $accountData = [];
            foreach ($log as $list_k=>$list_v){
                $accountData[] = [
                    'balance'  => $list_v['income'],
                    'account'  => $list_v['account_coding'],
                ];
            }

            $editstock = [];$goods_stock = [];
            $info_list = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$list]])->select();

            $purchase_info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$list]])->column('id');

            $purchase_info = [];
            foreach ($info_list as $list_k=>$list_v){
                $info_list[$list_k]['createtime']        = time();
                $info_list[$list_k]['updatetime']        = time();
                $stock_number = $list_v['basic_nmber'];
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('id,attribute_id,inventory,goods_name')->find();
                $status = false;
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['id']==$goods['id']){
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']+$stock_number;
                        $status = true;
                    }
                }
                if(!$status){
                    $goods_stock[] = [
                        'id'=>$goods['id'],
                        'inventory'=>$goods['inventory']+$stock_number,
                    ];
                }
                //商品库存
                $where = [];
                $where['goods_code'] = $list_v['goods_code'];
                $where['stores_id'] = $list_v['stores_id'];
                if($list_v['goods_attr']>0){
                    $goods_attr = $list_v['goods_attr'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] += $stock_number;
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']+$stock_number,2))
                        ];
                    }
                }else{
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'系统数据错误，操作失败'];
                    }
                    $this->error('系统数据错误,反审核失败');
                }
                $purchase_info[] = $list_v['id'];
            }

            $order_count = [];
            $code_id = Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->column('order_id');
            $purchase_order_info = Db::name('kuerp_goods_sales_order_info')
                ->field('id,purchase_code')
                ->where(['id'=>['in',$code_id]])
                ->select();
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $purord_info = Db::name('kuerp_goods_saleord_info')
                    ->where(['order_id'=>$purchase_order_info_v['id'],'purchase_id'=>['in',$purchase_info]])->field('sum(number) as number,sum(money) as money,sum(all_money) as all_money')->find();
                $purchase_order_info[$purchase_order_info_k]['pay_count'] = isset($purord_info['number'])?$purord_info['number']:0;
                $purchase_order_info[$purchase_order_info_k]['pay_money'] = isset($purord_info['money'])?$purord_info['money']:0;
                $purchase_order_info[$purchase_order_info_k]['all_money'] = isset($purord_info['all_money'])?$purord_info['all_money']:0;
            }
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $status = false;
                foreach ($order_count as $order_count_k=>$order_count_v){
                    if($order_count_v['purchase_code']==$purchase_order_info_v['purchase_code']){
                        $order_count[$order_count_k]['store_number'] += $purchase_order_info_v['pay_count'];
                        $status = true;
                    }
                }
                if(!$status){
                    $order_count[] = [
                        'purchase_code'  => $purchase_order_info_v['purchase_code'],
                        'store_number'  => $purchase_order_info_v['pay_count'],
                    ];
                }
            }
            //修改状态
            if(!empty($order_count)){
                foreach ($order_count as $order_count_k=>$order_count_v){
                    $number = Db::name('kuerp_goods_sales_order')->where(['purchase_code'=>$order_count_v['purchase_code']])->field('id,number,store_number')->find();
                    if($number['store_number']<=$order_count_v['store_number']){
                        $order_count[$order_count_k]['status'] = 0;
                    }else{
                        $order_count[$order_count_k]['status'] = 2;
                    }
                    $order_count[$order_count_k]['id'] = $number['id'];
                }
            }

            //批次库存数据
            //获取设置
            $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
            $setList = [];
            foreach ($set as $set_k=>$set_v){
                if(!empty($set_v['set'])){
                    $set_v['set'] = unserialize($set_v['set']);
                }
                $setList[$set_v['name']] = $set_v;
            }
            //数量价格格式化
            $number_decimal = $setList['number_decimal']['value'];
            $goods_serial = Db::name('kuerp_goods_sales_info')->where(['id'=>['in',$purchase_info_id]])->select();

            $goods_batch_add = [];$goods_batch_edit = [];
            foreach ($goods_serial as $k=>$v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v['goods_code']])->field('id,goods_name,attribute_id,inventory,unit_id,batch')->find();
                if($goods['batch']==1){
                    //先通过单位计算需修改多单位商品的库存数量
                    if($goods['unit_id']==$v['goods_unit']){
                        $stock_number = $v['number'];
                    }else{
                        $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$v['goods_unit']])->value('advance_rate');
                        if(empty($unit_conversion)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                            }
                            $this->error('商品单位信息查询失败');
                        }else{
                            $stock_number = round(($v['number']*$unit_conversion),$number_decimal);
                        }
                    }

                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if($v['goods_attr']){
                        $where['attribute_id'] = $v['goods_attr'];
                    }
                    $where['stores_id'] = $v['stores_id'];
                    $where['batch_id'] = $v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $status = false;
                        foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                            if($goods_batch_add_v['batch_id']==$v['batch_id'] && $goods_batch_add_v['goods_id']==$goods['id'] && $goods_batch_add_v['attribute_id']==$v['attribute_id'] && $goods_batch_add_v['stores_id']==$v['stores_id']){
                                $goods_batch_add[$goods_batch_add_k]['stock'] += $stock_number;
                                $status = true;
                            }
                        }
                        if(!$status){
                            $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                                'batch_id' => $v['batch_id'],
                                'goods_id' => $goods['id'],
                                'goods_code' => $v['goods_code'],
                                'attribute_id' => $v['goods_attr'],
                                'stores_id' => $v['stores_id'],
                                'stock' => $stock_number,
                                'createtime' => time(),
                                'updatetime' => time(),
                            ]);
                        }
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] += $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']+$stock_number;
                        }
                    }
                }
            }
            //批次库存数据结束

            //应收账款明细表
            $ex_list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                //销售利润表
                Db::name('kuerp_writeoff_source')->where(['purchase_code'=>['in',$list],'purchase_type'=>'3','type'=>'1'])->delete();

                foreach ($plist as $plist_k=>$plist_v){
                    Db::name('kuerp_goods_sales_profit')->where(['purchase_type'=>'1','purchase_code'=>$plist_v['purchase_code']])->delete();
                }
                Db::name('kuerp_cost')->where(['business_type'=>'2','purchase_number'=>['in',$ex_list]])->delete();
                Db::name('kuerp_accounts_receivable')->where(['business_type'=>'6','purchase_code'=>['in',$ex_list]])->delete();

                //序列号跟踪
                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_serial_log')->where(['business_type'=>'4','purchase_id'=>['in',$purchase_info_id]])->delete();
                }
                //收发详情
                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_log')->where(['business_type'=>'4','purchase_id'=>['in',$purchase_info_id]])->delete();
                }
                //批次库存数据
                //删除批次跟踪
                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_batch_log')->where(['business_type'=>'4','order_id'=>['in',$purchase_info_id]])->delete();
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                //批次库存数据结束

                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_serial')->where(['sale_id'=>['in',$purchase_info_id]])->update(['store_status'=>'1']);
                    Db::name('kuerp_goods_batch_sale_info')->where(['purchase_info_id'=>['in',$purchase_info_id]])->update(['status'=>'0']);
                }
                if(!empty($order_count)){
                    $goodsSalesOrder = (new \app\admin\model\kuerp\GoodsSalesOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsSalesOrder->where(['id'=>$order_count_v['id']])->dec('store_number',$order_count_v['store_number'])->update();
                        // 在订单上记录"取消出库"日志（统一使用 GoodsSalesOrderLog）
                        GoodsSalesOrderLog::writeLog($order_count_v['id'], '取消出库', '反审核销货单，取消出库', 'sales', $ids);
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsSalesOrder->saveAll($order_count);
                }
                (new \app\admin\model\kuerp\SalesPaymentLog())->where(['bill_codes'=>['in',$list]])->delete();
                (new \app\admin\model\kuerp\SalesInvoicing())->where(['bill_codes'=>['in',$list]])->delete();

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>0,'examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$list],'business_type'=>5])->delete();
                Db::name('kuerp_goods_sales_summary')->where(['purchase_code'=>['in',$list]])->delete();
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['account_coding'=>$accountData_v['account']])->dec('balance',$accountData_v['balance'])->update();
                    }
                }
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($arrears)){
                    (new \app\admin\model\kuerp\Customers())->saveAll($arrears);
                }
                Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->update(['status'=>'0']);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                // 写入操作日志
                $logIds = is_array($ids) ? $ids : explode(',', trim($ids, ','));
                foreach ($logIds as $logId) {
                    $this->writeSalesLog((int)$logId, '反审核', '反审核');
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

    /**
     * 催收
     */
    public function dunning($ids = null)
    {
        $ids = $ids ? $ids : $this->request->param('ids');
        if (!$ids) {
            $this->error(__('参数错误'));
        }

        $row = $this->model->find($ids);
        if (!$row) {
            $this->error(__('销货单不存在'));
        }

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $note = $params['note'] ?? '';
            $admin = Session::get('admin');

            Db::name('kuerp_goods_sales_dunning')->insert([
                'sales_id'   => $ids,
                'admin_id'   => $admin['id'],
                'note'       => $note,
                'createtime' => time(),
                'updatetime' => time(),
            ]);

            // 写入操作日志
            $this->writeSalesLog($ids, '催收', $note ?: '催收');
            // 同步写入销货订单日志
            GoodsSalesOrderLog::writeLogByCode($row['purchase_order_id'], '催收', '销货单催收：' . $row['purchase_code'] . ' - ' . ($note ?: '催收'), 'sales', $ids);

            $this->success(__('催收记录已保存'));
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 删除催收记录
     */
    public function delDunning($ids = null)
    {
        $ids = $ids ? $ids : $this->request->param('ids');
        if (!$ids) {
            $this->error(__('参数错误'));
        }
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        // 查询关联的销货单ID用于写日志
        $dunningInfo = Db::name('kuerp_goods_sales_dunning')->where('id', 'in', $ids)->field('sales_id,note')->select();
        $count = Db::name('kuerp_goods_sales_dunning')->where('id', 'in', $ids)->delete();
        if ($count) {
            foreach ($dunningInfo as $dv) {
                $this->writeSalesLog($dv['sales_id'], '删除催收', '删除催收记录：' . ($dv['note'] ?: '无备注'));
            }
            $this->success(__('删除成功'));
        } else {
            $this->error(__('删除失败'));
        }
    }

    //检测可生成退货单
    /**
     * 写入操作日志
     */
    private function writeSalesLog($sales_id, $action, $note = '')
    {
        $admin = Session::get('admin');
        Db::name('kuerp_goods_sales_log')->insert([
            'sales_id'   => $sales_id,
            'action'     => $action,
            'note'       => $note,
            'admin_id'   => $admin['id'],
            'createtime' => time(),
        ]);
    }

    public function checkPurchase(){
        $order_id = isset($_POST['exchange'])?trim($_POST['exchange']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_sales')->where(['id'=>$order_id])
            ->field('examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        if($row['examine_status']!=1){
            $this->error('此购货单未审核，不可生成退货单');
        }
        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_saleexc_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'2'])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此销货单已全部生成退货单');
        }
        $this->success();
    }
    //获取应付款余额
    public function getSuppliers($customers_id){
        if(empty($customers_id)){
            return 0;
        }else{
            $give_account = Db::name('kuerp_customers')->where(['id'=>$customers_id])->value('need_receive_account');
            return (float)$give_account;
        }
    }
    //获取单位信息
    public function getUnits($goods_code='',$store=''){
        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('id,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,warehousing,warehouse')->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = $_POST['goods_id'];
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])->field('unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,warehousing,warehouse')->find();
        }
        if(empty($row)){
            return [];
        }
        if($store){
            $stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$row['goods_code'],'stores_id'=>$store])->value('stock');
            $storesId = (int)$store;
        }else{
            $stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$row['goods_code'],'stores_id'=>$row['manager_warehouse_id']])->value('stock');
            $storesId = (int)$row['manager_warehouse_id'];
        }
        // 锁定库存调整：销货订单新增时不关联特定销售订单，扣除所有锁定库存
        $stock = \addons\kuerp\library\Goods::getAvailableStock($goods_id, $storesId, 0, '');
        if($row['more_unit']==1){
            $unit_ids_arr = Db::name('kuerp_unit_conversion')->where(['id'=>$row['more_unit_id']])->field('main_unit,vice_unit,advance_rate')->find();
            $unit_ids = [$unit_ids_arr['main_unit'],$unit_ids_arr['vice_unit']];
            $goods_price = Db::name('kuerp_goods_price')->where(['unit'=>['in',$unit_ids],'goods_id'=>$goods_id])->select();
        }else{
            $unit_ids = [$row['unit_id']];
        }
        $unit = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($goods_price)){
                foreach ($goods_price as $goods_price_k=>$goods_price_v){
                    if($unit_v['id']==$goods_price_v['unit']){
                        $unit[$unit_k]['is_default'] = $goods_price_v['is_default'];
                        if($unit_v['id']==$unit_ids_arr['vice_unit']){
                            $unit[$unit_k]['purchase'] = round(($goods_price_v['purchase']/$unit_ids_arr['advance_rate']),2);
                            $unit[$unit_k]['basic_nmber'] = $unit_ids_arr['advance_rate'];
                        }else{
                            $unit[$unit_k]['purchase'] = $goods_price_v['purchase'];
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $row['purchase'];
                }
            }
        }
        $data = [];
        foreach ($unit_ids as $unit_ids_k=>$unit_ids_v){
            foreach ($unit as $unit_k=>$unit_v){
                if($unit_ids_v==$unit_v['id']){
                    if($unit_v['is_default']==1){
                        $unit_v['stock'] = is_null($stock)?0:$stock;
                    }else{
                        $unit_v['stock'] = round($stock/$unit_v['basic_nmber'],2);
                    }
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode($data);
    }

    //获取单位价格信息
    public function getUnit($goods_code='',$store='',$attr='',$customers_id=''){
        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');

        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])
                ->field('market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')
                ->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:0;
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])
                ->field('market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')
                ->find();
        }
        if(empty($row)){
            return [];
        }

        //获取设置
        $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        //数量价格格式化
        $number_decimal = $setList['number_decimal']['value'];
        $price_decimal  = $setList['price_decimal']['value'];

        $field = 'market_sell_price';
        if($customers_id){
            $customers = Db::name('kuerp_customers')->where(['id'=>$customers_id])->find();
            if($customers){
                switch ($customers['customerlevel_id']){
                    case 1:
                        $field = 'market_sell_price';
                        break;
                    case 2:
                        $field = 'wholesale_price';
                        break;
                    case 3:
                        $field = 'vip_price';
                        break;
                    case 4:
                        $field = 'discount';
                        break;
                    case 5:
                        $field = 'discount2';
                        break;
                    default:
                        break;
                }
            }
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
                    $aux_combination[$aux_combination_k] = $auxiliary[$aux_combination_v];
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
            //$attribute_id = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->value('attribute_id');
            $where['attribute_id'] = $attr;
        }
        $stock = Db::name('kuerp_goods_stock')->where($where)->value('stock');

        // 锁定库存调整：销货订单新增时不关联特定销售订单，扣除所有锁定库存
        $attrId = isset($attr) ? (int)$attr : 0;
        $storesId = isset($where['stores_id']) ? (int)$where['stores_id'] : 0;
        $stock = \addons\kuerp\library\Goods::getAvailableStock($goods_id, $storesId, $attrId, '');

        if($row['more_unit']==1){
            $unit = Db::name('kuerp_unit_conversion')->alias('uc')
                ->join('kuerp_unit u','u.id=uc.vice_unit')
                ->where(['uc.main_unit'=>$row['unit_id']])
                ->order('uc.advance_rate','ASC')
                ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                ->select();
            $unit_ids = [$row['unit_id']];
            foreach ($unit as $unit_k=>$unit_v){
                $unit_ids[] = $unit_v['id'];
            }
            $goods_price = Db::name('kuerp_goods_price')->where(['unit'=>['in',$unit_ids],'goods_id'=>$goods_id])->select();
            foreach ($goods_price as $goods_price_k=>$goods_price_v){
                foreach ($unit as $unit_k=>$unit_v){
                    if($goods_price_v['unit']==$row['unit_id']){
                        $goods_price[$goods_price_k]['advance_rate'] = 1;
                    }else if($goods_price_v['unit']==$unit_v['id']){
                        $goods_price[$goods_price_k]['advance_rate'] = $unit_v['advance_rate'];
                    }
                }
            }
        }else{
            $unit_ids = [$row['unit_id']];
        }

        $unit = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($goods_price)){
                foreach ($goods_price as $goods_price_k=>$goods_price_v){
                    if($unit_v['id']==$goods_price_v['unit']){
                        $unit[$unit_k]['is_default'] = $goods_price_v['is_default'];
                        if($field=='discount' || $field=='discount2'){
                            $unit[$unit_k]['purchase'] = round( ($goods_price_v['market_sell_price']*$goods_price_v[$field]/100),$price_decimal );
                        }else{
                            $unit[$unit_k]['purchase'] = $goods_price_v[$field];
                        }
                        if($goods_price_v['is_default']!=1){
                            $unit[$unit_k]['basic_nmber'] = $goods_price_v['advance_rate'];
                        }else{
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($row['auxiliary']==1 && !empty($attr)){
                    $purchase_data = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->find();
                    if($field=='discount' || $field=='discount2'){
                        $purchase = round( ($purchase_data['market_sell_price']*$purchase_data[$field]/100),$price_decimal );
                    }else{
                        $purchase = $purchase_data[$field];
                    }
                }else{
                    if($field=='discount' || $field=='discount2'){
                        $purchase = round( ($row['market_sell_price']*$row[$field]/100),$price_decimal );
                    }else{
                        $purchase = $row[$field];
                    }
                }
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $purchase;
                }
            }
            $unit[$unit_k]['purchase'] = isset($unit[$unit_k]['purchase'])?$unit[$unit_k]['purchase']:0;
            $unit[$unit_k]['basic_nmber'] = isset($unit[$unit_k]['basic_nmber'])?$unit[$unit_k]['basic_nmber']:1;
            $unit[$unit_k]['is_default'] = isset($unit[$unit_k]['is_default'])?$unit[$unit_k]['is_default']:2;
        }

        $set= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        foreach ($unit as $unit_k=>$unit_v){
            $tax = $set['value'];
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1){
                $unit[$unit_k]['tax'] = 1;
                $unit[$unit_k]['tax_rate'] = $tax;
            }else{
                $unit[$unit_k]['tax'] = 0;
                $unit[$unit_k]['tax_rate'] = 0;
            }
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes==1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']/(100+$tax)*100),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']),4);
            }elseif(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes!=1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*(100+$tax)/100),4);
            }else{
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']),4);
            }
        }
        $data = [];
        foreach ($unit_ids as $unit_ids_k=>$unit_ids_v){
            foreach ($unit as $unit_k=>$unit_v){
                if($unit_ids_v==$unit_v['id']){
                    if($unit_v['is_default']==1){
                        $unit_v['stock'] = is_null($stock)?0:$stock;
                    }else{
                        $unit_v['stock'] = round($stock/$unit_v['basic_nmber'],2);
                    }
                    $unit_v['tax_money'] = round(($unit_v['tax_included']-$unit_v['purchase']),4);
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode(['unit'=>$data,'attr'=>$goods_attr,'goods'=>$row]);
    }

    public function formatInfo2(){
        $data = input();

        $row = Db::name('kuerp_goods')->where(['goods_code'=>$data['goods_code']])->field('batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id')->find();
        if(empty($row)){
            return [];
        }else{
            $goods_unit = $data['goods_unit'];
            if($row['more_unit']==1){//多单位，多属性以单位为准
                $goods_price = Db::name('kuerp_goods_price')->where(['goods_code'=>$data['goods_code'],'unit'=>$goods_unit])->find();
            }elseif ($row['more_unit']!=1 && $row['auxiliary']==1){//单一单位，多属性以属性价格为准
                $goods_price = Db::name('kuerp_goods_attr')->where(['id'=>$data['attr']])->find();
            }
        }

        //获取设置
        $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        //数量价格格式化
        $number_decimal = $setList['number_decimal']['value'];
        $price_decimal  = $setList['price_decimal']['value'];

        //税率
        $taxes= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        if(!isset($data['tax_rate']) || (isset($data['tax_rate']) && $data['tax_rate']==='')){
            $data['tax_rate'] = $taxes['value'];
        }
        $taxes_status = Db::name('kuerp_sets')->where(['name'=>'taxes'])->value('is_checked');//是否启用税金
        if($taxes_status!=1){
            $data['tax_rate'] = 0;
            $data['tax_money'] = 0;
            $data['tax_included'] = $data['unit_price'];
            $data['money_tax_price'] = $data['all_money'];
        }

        //需格式化字段数组
        $format = [
            'number'=>$number_decimal,
            'allow_stock'=>$number_decimal,
            'basic_nmber'=>$number_decimal,
            'unit_price'=>$price_decimal,
            'tax_included'=>$price_decimal,
            'discount'=>$price_decimal,
            'dis_money'=>$price_decimal,
            'all_money'=>$price_decimal,
            'tax_rate'=>$price_decimal,
            'tax_money'=>$price_decimal,
            'money_tax_price'=>$price_decimal,
            'advance_rate'=>$number_decimal,
        ];
        foreach ($data as $k=>$v){
            if(array_key_exists($k,$format)){
                $data[$k] = (float)$data[$k];
            }
        }

        $field = 'market_sell_price';
        $customers_id = isset($data['customers_id'])?$data['customers_id']:'';
        if($customers_id){
            $customers = Db::name('kuerp_customers')->where(['id'=>$customers_id])->find();
            if($customers){
                switch ($customers['customerlevel_id']){
                    case 1:
                        $field = 'market_sell_price';
                        break;
                    case 2:
                        $field = 'wholesale_price';
                        break;
                    case 3:
                        $field = 'vip_price';
                        break;
                    case 4:
                        $field = 'discount';
                        break;
                    case 5:
                        $field = 'discount2';
                        break;
                    default:
                        break;
                }
            }
        }

        $data['tax_included'] = isset($data['tax_included'])?$data['tax_included']:$data['unit_price']*(100+$data['tax_rate'])/100;
        $data['tax_rate'] = isset($data['tax_rate'])?$data['tax_rate']:0;
        $data['discount'] = isset($data['discount'])?$data['discount']:0;
        $data['dis_money'] = isset($data['dis_money'])?$data['dis_money']:0;

        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');//价格含税
        $now_field = $data['now_field'];
        switch ($now_field){
            case 'goods_unit':
                //商品单位
                if($taxes){
                    $data['tax_included'] = isset($goods_price[$field])?$goods_price[$field]:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price[$field])?$goods_price[$field]:$data['unit_price'];//单价
                    $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                }
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'attr':
                //商品属性
                if($taxes){
                    $data['tax_included'] = isset($goods_price[$field])?$goods_price[$field]:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price[$field])?$goods_price[$field]:$data['unit_price'];//单价
                    $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                }
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'number':
                //数量
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'unit_price':
                //单价
                $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_included':
                //含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_rate':
                //税率
                $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_money':
                //税额
                $data['money_tax_price'] = $data['all_money'] + $data['tax_money'];//价税合计减折扣额
                break;
            case 'discount':
                //折扣率
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'dis_money':
                //折扣额
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['discount'] = (100*$data['dis_money']+$data['dis_money']*$data['tax_rate'])/$data['money_tax_price'];//折扣率
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'all_money':
                //购货金额
                $data['money_tax_price'] = $data['all_money']*(100+$data['tax_rate'])/100;//价税合计
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                $money_tax_price = $data['money_tax_price']*(100+$data['discount'])/100;//价税合计未减折扣额
                $data['tax_included'] = $money_tax_price/$data['number'];//含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                break;
            case 'money_tax_price':
                //价税合计
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                $money_tax_price = $data['money_tax_price']*(100+$data['discount'])/100;//价税合计未减折扣额
                $data['tax_included'] = $money_tax_price/$data['number'];//含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                break;
            default:
                return [];
                break;
        }

        $data['advance_rate'] = 1;
        if($row['more_unit']==1){
            $advance_rate = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$row['more_unit_id'],'vice_unit'=>$goods_price['unit']])->value('advance_rate');
            if(!empty($advance_rate)){
                $data['advance_rate'] = $advance_rate;
            }
        }
        $data['basic_nmber'] = $data['number']*$data['advance_rate'];
        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
            }
        }

        if(isset($data['tax_rate'])){
            $data['tax_money'] = $data['money_tax_price']-$data['all_money'];
        }else{
            $data['tax_money'] = 0;
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
        $goods_batch = Db::name('kuerp_goods_batch')->where(['goods_code'=>$data['goods_code']])->field('id,batch_name,manufacture,guarantee,validity')->select();
        foreach ($goods_batch as $goods_batch_k=>$goods_batch_v){
            $goods_batch[$goods_batch_k]['manufacture'] = date('Y-m-d',$goods_batch_v['manufacture']);
            $goods_batch[$goods_batch_k]['validity'] = date('Y-m-d',$goods_batch_v['validity']);
        }
        return json_encode(['goods'=>$row,'batch'=>$goods_batch]);
    }
    public function getTime($manufacture='',$shelf_life='',$validity=''){
        if(!empty($manufacture) && !empty($shelf_life)){
            $validity = date('Y-m-d',strtotime($manufacture)+$shelf_life*86400);
        }elseif(!empty($validity) && !empty($shelf_life)){
            $manufacture = date('Y-m-d',strtotime($validity)-$shelf_life*86400);
        }elseif(!empty($validity) && !empty($manufacture)){
            $shelf_life = ceil((strtotime($validity)-strtotime($manufacture))/86400);
        }
        return json_encode(['manufacture'=>$manufacture,'shelf_life'=>$shelf_life,'validity'=>$validity]);
    }
    //获取客户收货地址
    public function getReceivingAddress(){
        $customers_id = isset($_POST['customers_id'])?trim($_POST['customers_id']):0;
        if(empty($customers_id)){
            return '';
        }
        $address = Db::name('kuerp_customers')->where(['id'=>$customers_id])->value('getgoods_address');
        return (empty($address)?'':$address);
    }

    // 打印销货单详情
    public function printpage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 客户名称
        $customer_name = Db::name('kuerp_customers')->where(['id' => $row['customers_id']])->value('customer_name');
        $row['customer_name'] = $customer_name ? $customer_name : '';
        // 制单人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('username');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('username');
        // 销售员
        $row['sale_user_name'] = Db::name('kuerp_staff_member')->where(['id' => $row['sale_user']])->value('name');

        // 销货单详情
        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code' => $row['purchase_code']])->select();
        foreach ($purchase_info as $k => $v) {
            $purchase_info[$k]['goods_name'] = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->value('goods_name');
            $purchase_info[$k]['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $purchase_info[$k]['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
        }
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);

        // 增加打印次数
        $this->model->where(['id' => $ids])->setInc('print_num');

        return $this->view->fetch();
    }

    // 董事长二次审核
    public function secondExamine($ids = null){
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
            // 写入操作日志
            $logIds = is_array($ids) ? $ids : explode(',', trim($ids, ','));
            foreach ($logIds as $logId) {
                $this->writeSalesLog((int)$logId, '二审', '董事长二次审核通过');
            }
            $this->success(sizeof($list).'条数据二次审核成功');
        }else{
            $this->error('二次审核失败');
        }
    }
}
