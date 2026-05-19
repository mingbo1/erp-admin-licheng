<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 销货退货单
 *
 * @icon fa fa-circle-o
 */
class GoodsSalesExchange extends Backend
{
    protected $noNeedRight = ['getsuppliers'];
    /**
     * GoodsSalesExchange模型对象
     * @var \app\admin\model\kuerp\GoodsSalesExchange
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesExchange;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("retreatStatusList", $this->model->getRetreatStatusList());

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
                ->with(['customers','preparation','examine'])
                ->where($where)
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                $row->getRelation('preparation')->visible(['username']);
                $row->getRelation('examine')->visible(['username']);
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($ids = null,$type=null)
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
                if($params['retreat_money']>0){
                    if(empty($params['account'])){
                        $this->error('请选择结算账户');
                    }else{
                        $account = Db::name('kuerp_accounts')->where(['id'=>$params['account']])->find();
                        if(empty($account)){
                            $this->error('结算账户不存在');
                        }
                    }
                    $all_now_pay_money = $now_pay_money = trim($params['retreat_money']);
                }else{
                    $all_now_pay_money = $now_pay_money = 0;
                }

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$all_money = 0;$retreat_number = 0;$tax_money=0;$money_tax_price=0;
                $purchase_order_id = '';$purchase_id = '';
                $purchase_type = 0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            if(empty($batch_data)){
                                $this->error('生产批次不存在');
                            }
                        }

                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                        }

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $purchase_type = 0;
                        if(!empty($params['purchase_order_id'][$k])){
                            $purchase_order_id = $params['purchase_order_id'][$k];
                            $purchase_type = 1;
                        }
                        if(!empty($params['purchase_id'][$k])){
                            $purchase_id = $params['purchase_id'][$k];
                            $purchase_type = 2;
                        }

                        //有关联销货单需对比销货单 单位，属性，仓库等信息是否一致
                        if(!empty($params['info_id'][$k]) && $purchase_type==2){
                            //有关联购货单需对比 单位，属性，仓库等信息是否一致
                            $goods_sales_info = Db::name('kuerp_goods_sales_info')->where(['id'=>$params['info_id'][$k]])->find();
                            if(empty($goods_sales_info)){
                                $this->error('原销货单信息查询失败');
                            }elseif ($goods_sales_info['goods_attr']>0 && $params['goods_attr'][$k]!=$goods_sales_info['goods_attr']){
                                $this->error('商品属性与原销货单商品属性不一致，退货失败');
                            }elseif ($goods_sales_info['goods_unit']>0 && $params['goods_unit'][$k]!=$goods_sales_info['goods_unit']){
                                $this->error('商品单位与原销货单商品单位不一致，退货失败');
                            }elseif ($goods_sales_info['stores_id']>0 && $params['stores_id'][$k]!=$goods_sales_info['stores_id']){
                                $this->error('商品仓库与原销货单商品仓库不一致，退货失败');
                            }elseif ($goods_sales_info['unit_price']>0 && $params['unit_price'][$k]!=$goods_sales_info['unit_price']){
                                $this->error('商品单价与原销货单商品单价不一致，退货失败');
                            }elseif ($goods_sales_info['tax_included']>0 && $params['tax_included'][$k]!=$goods_sales_info['tax_included']){
                                $this->error('商品含税单价与原销货单商品含税单价不一致，退货失败');
                            }
                        }

                        $number += (float)$params['number'][$k];
                        $retreat_number += (float)$params['retreat_number'][$k];
                        $all_money += (float)$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        $money_tax_price += (float)$params['money_tax_price'][$k];

                        if(!empty($params['purchase_order_id'][$k])){
                            $purchase_order_id = $params['purchase_order_id'][$k];
                        }
                        if(!empty($params['purchase_id'][$k])){
                            $purchase_id = $params['purchase_id'][$k];
                        }

                        unset($pay_money);unset($pay_status);
                        if($now_pay_money>0){
                            if($now_pay_money-(float)$params['money_tax_price'][$k]>=0){
                                $pay_status = 1;
                                $pay_money = (float)$params['money_tax_price'][$k];
                                $now_pay_money -= $pay_money;
                            }else{
                                $pay_status = 2;
                                $pay_money = $now_pay_money;
                                $now_pay_money -= $now_pay_money;
                            }
                        }

                        $goods_batch_info = [];
                        if($goods['batch']==1 && $batch==1){
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
                            $period_period = unserialize($params['period_period'][$k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                $serial_code[] = $period_period_v['serial'];
                            }
                            $where = [];
                            $where['serial'] = ['in',$serial_code];
                            if(!empty($params['purchase_id'][$k])){
                                $where['sale_code'] = $params['purchase_id'][$k];
                            }
                            $goods_serial = Db::name('kuerp_goods_serial')->where($where)->column('serial');
                            if(empty($goods_serial)){
                                $this->error('商品序列号不可用');
                            }
                            foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                if(!in_array($serial_code_v,$goods_serial)){
                                    $this->error('商品序列号不可用');
                                }
                            }
                            $goods_serials = Db::name('kuerp_goods_serial')->where(['status'=>'4','serial'=>['in',$serial_code]])->column('serial');
                            if(!empty($goods_serials)){
                                $goods_serials = implode(',',$goods_serials);
                                $this->error('商品序列号'.$goods_serials.'已经待入库，不可重复入库');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $purchase_type,
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],//客户
                            'sale_user'         =>  (int)$params['sale_user'],//销售员
                            'receiving_address' =>  $params['receiving_address'],

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  $params['goods_unit'][$k],

                            'stores_id'         =>  $params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
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
                            'purchase_info_id'  =>  (float)$params['info_id'][$k],

                            'purchase_order_id' =>  $params['purchase_order_id'][$k],
                            'purchase_id'       =>  $params['purchase_id'][$k],
                            'retreat_number'    =>  (float)$params['retreat_number'][$k],
                            'retreat_status'    =>  isset($pay_status)?$pay_status:'0',
                            'retreat_money'     =>  isset($pay_money)?$pay_money:0,
                            'account'           =>  isset($pay_status)?$params['account']:0,

                            'createtime'        => time(),
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                            'serial_code'       => $serial_code,
                            'goods_batch_info'  => $goods_batch_info
                        ];
                    }
                }
                $preferential_money = (float)$params['preferential_money'];
                if(!empty($data)){
                    if($all_now_pay_money>0){
                        if($all_now_pay_money+$preferential_money-$money_tax_price>=0){
                            $pay_status = 1;
                            $pay_money = $money_tax_price-$preferential_money;
                        }else{
                            $pay_status = 2;
                            $pay_money = $all_now_pay_money;
                        }
                    }

                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $purchase_type,
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'customers_id'      =>  (int)$params['customers_id'],//客户
                        'sale_user'         =>  (int)$params['sale_user'],//销售员
                        'receiving_address' =>  $params['receiving_address'],
                        'customers_money'   =>  isset($params['customers_money'])?(float)$params['customers_money']:0,

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
                        'all_arrears'       =>  isset($params['add_arrears'])?$params['add_arrears']:0,

                        'purchase_order_id' =>  $purchase_order_id,
                        'purchase_id'       =>  $purchase_id,

                        'retreat_number'    => $retreat_number,
                        'retreat_status'    =>  isset($pay_status)?$pay_status:'0',
                        'retreat_money'     =>  isset($pay_money)?$pay_money:0,
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
                    $order_id =  Db::name('kuerp_goods_sales_order')->where(['purchase_code'=>$purchase_order_id])->value('id');
                }
                if($purchase_id){
                    $order_id =  Db::name('kuerp_goods_sales')->where(['purchase_code'=>$purchase_id])->value('id');
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
                                'type'          => '6',
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

                    Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$params['purchase_code']])->delete();
                    foreach ($data as $data_k=>$data_v){
                        $goods_serial = $data_v['serial_code'];
                        unset($data_v['serial_code']);

                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);

                        $purchase_id = Db::name('kuerp_goods_sales_exchange_info')->insertGetId($data_v);

                        if($goods_batch_info){
                            $goods_batch_info['purchase_info_id'] = $purchase_id;
                            Db::name('kuerp_goods_batch_salee_info')->insert($goods_batch_info);
                        }

                        if(!empty($data_v['purchase_info_id'])){
                            Db::name('kuerp_goods_saleexc_info')->insert([
                                'purchase_id'=>$data_v['purchase_info_id'],
                                'exchange_id'=>$purchase_id,
                                'type' => $data_v['purchase_type'],
                                'number'=>$data_v['retreat_number'],
                                'money'=>$data_v['retreat_money'],
                                'all_money'=>$data_v['all_money'],
                                'tax_rate'=>$data_v['tax_rate'],
                                'tax_money'=>$data_v['tax_money'],
                                'money_tax_price'=>$data_v['money_tax_price']
                            ]);
                        }

                        if(!empty($goods_serial)){
                            Db::name('kuerp_goods_serial')->where(['serial'=>['in',$goods_serial]])->update([
                                'status'=>'4',
                                'purchase_exchange_id' => $purchase_id,
                                'purchase_exchange_code' => $data_v['purchase_code']
                            ]);
                        }
                    }
                    if(!empty($order_id)){
                        if($result){
                            Db::name('kuerp_goods_saleexc')->insert(['purchase_id'=>$order_id,'exchange_id'=>$result,'type'=>$purchase_type]);
                        }
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
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
        $code = \addons\kuerp\library\Goods::getCodes('',12);
        $this->view->assign("code", $code);

        $last_customer = Db::name('kuerp_sets')->where(['name'=>'customer'])->value('is_checked');
        $set_customer = '';
        if($last_customer){
            $set_customer = Db::name('kuerp_goods_sales_exchange')->order('id','DESC')->value('customers_id');
        }
        $address = Db::name('kuerp_customers')->where(['id'=>$set_customer])->value('getgoods_address');
        $last_read = Db::name('kuerp_sets')->where(['name'=>'last_read'])->value('is_checked');
        $sale_user = '';
        if($last_read){
            $sale_user = Db::name('kuerp_goods_sales_exchange')->order('id','DESC')->value('sale_user');
        }

        if($type==1){
            //销货单退货
            $row = Db::name('kuerp_goods_sales_order')->where(['id'=>$ids])->find();
            $give_account = 0;$real_money = 0;$arrears = 0;
            if($row){
                $purchase_info = Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                    //查询最新可用库存
                    $where = [];
                    $where['goods_code'] = $purchase_info_v['goods_code'];
                    $where['stores_id'] = $purchase_info_v['stores_id'];
                    if($purchase_info_v['goods_attr']>0){
                        //查询是否有属性区分
                        $attr_id = $purchase_info_v['goods_attr'];
                        if($attr_id){
                            $where['attribute_id'] = $attr_id;
                        }
                    }
                    //查询库存
                    $stock = (float)Db::name('kuerp_goods_stock')->where($where)->value('stock');
                    $purchase_info[$purchase_info_k]['allow_stock'] = $stock;
                    //查询最新可用库存结束

                    $purord_info = Db::name('kuerp_goods_saleexc_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'1'])->field('sum(number) as number,sum(money) as money')->select();
                    $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
                    $real_money += isset($purord_info[0]['money'])?$purord_info[0]['money']:0;
                    if($purchase_info_v['number']-$gp_number<=0){
                        unset($purchase_info[$purchase_info_k]);
                    }else{
                        $unit = json_decode(\addons\kuerp\library\GoodsSales::getUnit($purchase_info_v['goods_code']),true);
                        foreach ($unit['unit'] as $unit_k=>$unit_v){
                            if($unit_v['id']==$purchase_info_v['goods_unit']){
                                $purchase_info[$purchase_info_k]['basic_nmber'] = ($purchase_info_v['number']-$gp_number)*$unit_v['basic_nmber'];
                            }
                        }
                        $purchase_info[$purchase_info_k]['unit'] = $unit;
                        $arrears += $purchase_info[$purchase_info_k]['arrears'] = ($purchase_info_v['number']-$gp_number)*$purchase_info_v['tax_included']-$purchase_info_v['preferential_money'];
                        $purchase_info[$purchase_info_k]['dis_money'] = $purchase_info[$purchase_info_k]['all_money']*($purchase_info_v['discount'])/100;
                        $purchase_number = ($purchase_info_v['number']-$gp_number);
                        $purchase_info[$purchase_info_k]['purchase_number'] = $purchase_number;
                        $purchase_info[$purchase_info_k]['all_money'] = round(($purchase_number*$purchase_info_v['unit_price']),2);
                        $purchase_info[$purchase_info_k]['tax_money'] = round(($purchase_info[$purchase_info_k]['all_money']*$purchase_info_v['tax_rate']/100),2);
                        $purchase_info[$purchase_info_k]['money_tax_price'] = round(($purchase_number*$purchase_info_v['tax_included']),2);

                        $purchase_info[$purchase_info_k]['order_code'] = Db::name('kuerp_goods_sales_order_info')->alias('oi')
                            ->join('kuerp_goods_saleord_info pi','pi.order_id=oi.id')
                            ->where(['pi.purchase_id'=>$purchase_info_v['id']])->value('oi.purchase_code');
                        $serial = Db::name('kuerp_goods_serial')->where(['sale_id'=>$purchase_info_v['id'],'status'=>'3'])->field('serial,remark')->select();
                        $purchase_info[$purchase_info_k]['serial'] = $serial;
                        $purchase_info[$purchase_info_k]['order_code'] = $purchase_info_v['purchase_code'];
                        $purchase_info[$purchase_info_k]['purchase_code'] = '';
                    }
                }
                $row['real_money'] = $arrears; $row['arrears'] = $arrears;
                $give_account = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->value('need_receive_account');

                //查询原始单据
                $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'4'])->column('enclosure_id');
                if(!empty($enclosure_id)){
                    $this->assign('enclosure_count',sizeof($enclosure_id));
                    $enclosure_id = implode(',',$enclosure_id);
                }else{
                    $enclosure_id = '';
                }
                $this->assign('enclosure_ids',$enclosure_id);
            }else{
                $row['customers_id'] = $set_customer;
                $row['receiving_address'] = $address;
                $row['sale_user'] = $sale_user;
            }
            $purchase_info = isset($purchase_info)?$purchase_info:[];
            $this->assignconfig("give_account", $give_account-$real_money-$arrears);
            $this->view->assign("row", $row);
            if($row){
                $this->view->assign("arrears", $row['real_money']);
            }
            $this->view->assign("purchase_info", $purchase_info);
            $this->view->assign("give_account", $give_account);
        }else{
            $row = Db::name('kuerp_goods_sales')->where(['id'=>$ids])->find();
            $give_account = 0;$real_money = 0;$arrears = 0;
            if($row){
                $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->select();
                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                    //查询最新可用库存
                    $where = [];
                    $where['goods_code'] = $purchase_info_v['goods_code'];
                    $where['stores_id'] = $purchase_info_v['stores_id'];
                    if($purchase_info_v['goods_attr']>0){
                        //查询是否有属性区分
                        $attr_id = $purchase_info_v['goods_attr'];
                        if($attr_id){
                            $where['attribute_id'] = $attr_id;
                        }
                    }
                    //查询库存
                    $stock = (float)Db::name('kuerp_goods_stock')->where($where)->value('stock');
                    $purchase_info[$purchase_info_k]['allow_stock'] = $stock;
                    //查询最新可用库存结束

                    $purord_info = Db::name('kuerp_goods_saleexc_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'2'])->field('sum(number) as number,sum(money) as money')->select();
                    $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
                    $real_money += isset($purord_info[0]['money'])?$purord_info[0]['money']:0;
                    if($purchase_info_v['number']-$gp_number<=0){
                        unset($purchase_info[$purchase_info_k]);
                    }else{
                        $unit = json_decode(\addons\kuerp\library\GoodsSales::getUnit($purchase_info_v['goods_code']),true);
                        foreach ($unit['unit'] as $unit_k=>$unit_v){
                            if($unit_v['id']==$purchase_info_v['goods_unit']){
                                $purchase_info[$purchase_info_k]['basic_nmber'] = ($purchase_info_v['number']-$gp_number)*$unit_v['basic_nmber'];
                            }
                        }
                        $purchase_info[$purchase_info_k]['unit'] = $unit;
                        $arrears += $purchase_info[$purchase_info_k]['arrears'] = ($purchase_info_v['number']-$gp_number)*$purchase_info_v['tax_included'];
                        $purchase_info[$purchase_info_k]['dis_money'] = $purchase_info[$purchase_info_k]['all_money']*($purchase_info_v['discount'])/100;
                        $purchase_number = ($purchase_info_v['number']-$gp_number);
                        $purchase_info[$purchase_info_k]['purchase_number'] = $purchase_number;
                        $purchase_info[$purchase_info_k]['all_money'] = round(($purchase_number*$purchase_info_v['unit_price']),2);
                        $purchase_info[$purchase_info_k]['tax_money'] = round(($purchase_info[$purchase_info_k]['all_money']*$purchase_info_v['tax_rate']/100),2);
                        $purchase_info[$purchase_info_k]['money_tax_price'] = round(($purchase_number*$purchase_info_v['tax_included']),2);

                        $purchase_info[$purchase_info_k]['order_code'] = Db::name('kuerp_goods_sales_order_info')->alias('oi')
                            ->join('kuerp_goods_saleord_info pi','pi.order_id=oi.id')
                            ->where(['pi.purchase_id'=>$purchase_info_v['id']])->value('oi.purchase_code');
                        $serial = Db::name('kuerp_goods_serial')->where(['sale_id'=>$purchase_info_v['id'],'status'=>'3'])->field('serial,remark')->select();
                        $purchase_info[$purchase_info_k]['serial'] = $serial;
                    }
                }
                $row['real_money'] = $arrears; $row['arrears'] = $arrears;
                $give_account = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->value('need_receive_account');

                //查询原始单据
                $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'5'])->column('enclosure_id');
                if(!empty($enclosure_id)){
                    $this->assign('enclosure_count',sizeof($enclosure_id));
                    $enclosure_id = implode(',',$enclosure_id);
                }else{
                    $enclosure_id = '';
                }
                $this->assign('enclosure_ids',$enclosure_id);
            }else{
                $row['customers_id'] = $set_customer;
                $row['receiving_address'] = $address;
                $row['sale_user'] = $sale_user;
            }
            $purchase_info = isset($purchase_info)?$purchase_info:[];
            $this->assignconfig("give_account", $give_account-$real_money-$arrears);
            $this->view->assign("row", $row);
            if($row){
                $row['real_money'] = isset($row['real_money'])?$row['real_money']:0;
                $row['customers_money'] = isset($row['customers_money'])?$row['customers_money']:0;
                $this->view->assign("arrears", $row['real_money']+$row['customers_money']);
            }
            $this->view->assign("purchase_info", $purchase_info);
            $this->view->assign("give_account", $give_account);
        }

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
                $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');

                $params = $this->preExcludeFields($params);
                $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);
                $info_id = Db::name('kuerp_goods_exchange_info')->where(['purchase_code'=>$row['purchase_code']])->column('id');

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');
                if($params['retreat_money']>0){
                    if(empty($params['account'])){
                        $this->error('请选择结算账户');
                    }else{
                        $account = Db::name('kuerp_accounts')->where(['id'=>$params['account']])->find();
                        if(empty($account)){
                            $this->error('结算账户不存在');
                        }
                    }
                    $all_now_pay_money = $now_pay_money = trim($params['retreat_money']);
                }else{
                    $all_now_pay_money = $now_pay_money = 0;
                }

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$all_money = 0;$retreat_number = 0;$tax_money=0;$money_tax_price=0;
                $purchase_order_id = '';$purchase_id = '';
                $purchase_type = 0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            if(empty($batch_data)){
                                $this->error('生产批次不存在');
                            }
                        }

                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                        }

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $purchase_type = 0;
                        if(!empty($params['purchase_order_id'][$k])){
                            $purchase_order_id = $params['purchase_order_id'][$k];
                            $purchase_type = 1;
                        }
                        if(!empty($params['purchase_id'][$k])){
                            $purchase_id = $params['purchase_id'][$k];
                            $purchase_type = 2;
                        }

                        //有关联销货单需对比销货单 单位，属性，仓库等信息是否一致
                        if(!empty($params['info_id'][$k])){
                            //有关联购货单需对比 单位，属性，仓库等信息是否一致
                            $goods_sales_info = Db::name('kuerp_goods_sales_info')->where(['id'=>$params['info_id'][$k]])->find();
                            if(empty($goods_sales_info)){
                                $this->error('原销货单信息查询失败');
                            }elseif ($goods_sales_info['goods_attr']>0 && $params['goods_attr'][$k]!=$goods_sales_info['goods_attr']){
                                $this->error('商品属性与原销货单商品属性不一致，退货失败');
                            }elseif ($goods_sales_info['goods_unit']>0 && $params['goods_unit'][$k]!=$goods_sales_info['goods_unit']){
                                $this->error('商品单位与原销货单商品单位不一致，退货失败');
                            }elseif ($goods_sales_info['stores_id']>0 && $params['stores_id'][$k]!=$goods_sales_info['stores_id']){
                                $this->error('商品仓库与原销货单商品仓库不一致，退货失败');
                            }elseif ($goods_sales_info['unit_price']>0 && $params['unit_price'][$k]!=$goods_sales_info['unit_price']){
                                $this->error('商品单价与原销货单商品单价不一致，退货失败');
                            }elseif ($goods_sales_info['tax_included']>0 && $params['tax_included'][$k]!=$goods_sales_info['tax_included']){
                                $this->error('商品含税单价与原销货单商品含税单价不一致，退货失败');
                            }
                        }

                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $retreat_number += (float)$params['retreat_number'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        $money_tax_price += (float)$params['money_tax_price'][$k];

                        if(!empty($params['purchase_order_id'][$k])){
                            $purchase_order_id = $params['purchase_order_id'][$k];
                        }
                        if(!empty($params['purchase_id'][$k])){
                            $purchase_id = $params['purchase_id'][$k];
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

                        $goods_batch_info = [];
                        if($goods['batch']==1 && $batch==1){
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
                            $period_period = unserialize($params['period_period'][$k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                $serial_code[] = $period_period_v['serial'];
                            }
                            $where = [];
                            $where['serial'] = ['in',$serial_code];
                            if(!empty($params['purchase_id'][$k])){
                                $where['sale_code'] = $params['purchase_id'][$k];
                            }
                            $goods_serial = Db::name('kuerp_goods_serial')->where($where)->column('serial');
                            if(empty($goods_serial)){
                                $this->error('商品序列号不可用');
                            }
                            foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                if(!in_array($serial_code_v,$goods_serial)){
                                    $this->error('商品序列号不可用');
                                }
                            }
                            $goods_serials = Db::name('kuerp_goods_serial')->where(['status'=>'4','serial'=>['in',$serial_code],'sale_exchange_id'=>['not in',$info_id]])->column('serial');
                            if(!empty($goods_serials)){
                                $goods_serials = implode(',',$goods_serials);
                                $this->error('商品序列号'.$goods_serials.'已经待入库，不可重复入库');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $purchase_type,
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],//客户
                            'sale_user'         =>  (int)$params['sale_user'],//销售员
                            'receiving_address' =>  $params['receiving_address'],

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'cost_price'        =>  0,
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'purchase_info_id'  =>  (float)$params['info_id'][$k],

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

                            'purchase_order_id' =>  $params['purchase_order_id'][$k],
                            'purchase_id'       =>  $params['purchase_id'][$k],
                            'retreat_number'    =>  (float)$params['retreat_number'][$k],
                            'retreat_status'    =>  isset($pay_status)?$pay_status:'0',
                            'retreat_money'     =>  isset($pay_money)?$pay_money:0,
                            'account'           =>  isset($pay_status)?$params['account']:0,

                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                            'serial_code'       => $serial_code,
                            'goods_batch_info'  => $goods_batch_info
                        ];
                    }
                }
                $preferential_money = (float)$params['preferential_money'];
                if(!empty($data)){
                    if($all_now_pay_money>0){//当次支付金额
                        if($all_now_pay_money+$preferential_money-$money_tax_price>=0){
                            $pay_status = 1;
                            $pay_money = $money_tax_price-$preferential_money;
                        }else{
                            $pay_status = 2;
                            $pay_money = $all_now_pay_money;
                        }
                    }
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $purchase_type,
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'customers_id'      =>  (int)$params['customers_id'],//客户
                        'sale_user'         =>  (int)$params['sale_user'],//销售员
                        'receiving_address' =>  $params['receiving_address'],
                        'customers_money'   =>  isset($params['customers_money'])?(float)$params['customers_money']:0,

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
                        'all_arrears'       =>  isset($params['add_arrears'])?$params['add_arrears']:0,

                        'purchase_order_id' =>  $purchase_order_id,
                        'purchase_id'       =>  $purchase_id,

                        'retreat_number'    => $retreat_number,
                        'retreat_status'    =>  isset($pay_status)?$pay_status:'0',
                        'retreat_money'     =>  isset($pay_money)?$pay_money:0,
                        'account'           =>  isset($pay_status)?$params['account']:0,
                        'cost_data'         =>  isset($params['cost_data'])?$params['cost_data']:null,
                        'updatetime'        => time(),
                    ];
                }
                if($this->model->where(['purchase_code'=>$rowdata['purchase_code'],'id'=>['<>',$ids]])->find()){
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
                                'type'          => '6',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $info_id = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$row['purchase_code']])->column('id');
                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }

                    //添加附件记录
                    Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'6'])->delete();
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    if(!empty($info_id)){
                        Db::name('kuerp_goods_saleexc_info')->where(['exchange_id'=>['in',$info_id]])->delete();
                    }
                    $purchase_exchange_id = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$old_purchase_code])->column('id');
                    Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$old_purchase_code])->delete();
                    Db::name('kuerp_goods_batch_salee_info')->where(['purchase_info_id'=>['in',$purchase_exchange_id]])->delete();
                    Db::name('kuerp_goods_serial')->where(['sale_exchange_id'=>['in',$purchase_exchange_id],'status'=>'4'])->update([
                        'status'=>'3',
                    ]);

                    foreach ($data as $data_k=>$data_v){
                        $goods_serial = $data_v['serial_code'];
                        unset($data_v['serial_code']);

                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);

                        $purchase_id = Db::name('kuerp_goods_sales_exchange_info')->insertGetId($data_v);

                        if($goods_batch_info){
                            $goods_batch_info['purchase_info_id'] = $purchase_id;
                            Db::name('kuerp_goods_batch_salee_info')->insert($goods_batch_info);
                        }

                        if(!empty($data_v['purchase_info_id'])){
                            Db::name('kuerp_goods_saleexc_info')->insert([
                                'purchase_id'=>$data_v['purchase_info_id'],
                                'exchange_id'=>$purchase_id,
                                'type' => $data_v['purchase_type'],
                                'number'=>$data_v['retreat_number'],
                                'money'=>$data_v['retreat_money'],
                                'all_money'=>$data_v['all_money'],
                                'tax_rate'=>$data_v['tax_rate'],
                                'tax_money'=>$data_v['tax_money'],
                                'money_tax_price'=>$data_v['money_tax_price']
                            ]);
                        }

                        if(!empty($goods_serial)){
                            Db::name('kuerp_goods_serial')->where(['serial'=>['in',$goods_serial]])->update([
                                'status'=>'4',
                                'purchase_exchange_id' => $purchase_id,
                                'purchase_exchange_code' => $data_v['purchase_code']
                            ]);
                        }
                    }
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

        $purchase_info = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsSales::getUnit($purchase_info_v['goods_code']),true);
            $purchase_info[$purchase_info_k]['serial'] = $purchase_info_v['period'];
            $purord_info = Db::name('kuerp_goods_saleexc_info')->where(['exchange_id'=>$purchase_info_v['id']])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            $purchase_number = ($purchase_info_v['number']-$gp_number);
            $purchase_info[$purchase_info_k]['purchase_number'] = $purchase_number;
        }
        $this->view->assign("purchase_info", $purchase_info);

        $goods_purchase = Db::name('kuerp_goods_sales')->where(['purchase_code'=>$row['purchase_id']])->find();
        $this->view->assign("goods_purchase", $goods_purchase);

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'6'])->column('enclosure_id');
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
            $goods_purchase_id = [];$goods_purchase_code = [];
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');

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

            $goods_purchase_info = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>['in',$goods_purchase_code]])->column('id');
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    $exchange_info = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$v['purchase_code']])->column('id');
                    Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
                    Db::name('kuerp_goods_serial')->where(['purchase_exchange_id'=>['in',$exchange_info]])->update(['status'=>'3']);

                    if(!empty($goods_purchase_id)){//销货订单关联
                        Db::name('kuerp_goods_saleexc')->where(['exchange_id'=>['in',$v['id']],'type'=>$v['purchase_type']])->delete();
                        Db::name('kuerp_goods_saleexc_info')->where(['exchange_id'=>['in',$exchange_info],'type'=>$v['purchase_type']])->delete();
                    }
                }
                if(!empty($goods_purchase_info)){//销货订单详情关联
                    Db::name('kuerp_goods_batch_salee_info')->where(['purchase_info_id'=>['in',$goods_purchase_info]])->delete();
                }

                //删除附件记录
                Db::name('kuerp_enclosure_log')->where(['purchase_code'=>$v['purchase_code'],'type'=>'6'])->delete();
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

            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }
            $account_list_obj = Db::name('kuerp_accounts')->field('id,account_coding,account_name,balance')->select();
            $account_list = [];
            foreach ($account_list_obj as $account_list_obj_k=>$account_list_obj_v){
                $account_list[$account_list_obj_v['id']] = $account_list_obj_v;
            }

            $examine_ids = [];
            $accountData = [];
            $log = [];
            $suppliers = [];
            $payment_log = [];$invoicing = [];
            $editstock = [];$goods_stock = [];$goods_exchange_info = [];$exchange_info = [];
            $goods_stock_log = [];//收发详情
            $serial_log = [];//序列号跟踪

            $order_count = [];
            foreach ($list as $list_k=>$list_v){

                $info_list = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$list_v['purchase_code']])->select();
                foreach ($info_list as $info_list_k=>$info_list_v){

                    if($list_v['purchase_type']==1 && !empty($info_list_v['purchase_order_id'])){
                        $status = false;
                        foreach ($order_count as $order_count_k=>$order_count_v){
                            if($order_count_v['purchase_code']==$info_list_v['purchase_order_id']){
                                $order_count[$order_count_k]['store_number'] += $info_list_v['retreat_number'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $order_count[] = [
                                'purchase_code'  => $info_list_v['purchase_order_id'],
                                'store_number'  => $info_list_v['retreat_number'],
                            ];
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
                    }

                    //序列号跟踪
                    $goods_serial = Db::name('kuerp_goods_serial')->where(['sale_id'=>$info_list_v['id']])->field('goods_id,serial,stores_id,attribute_id')->select();
                    foreach ($goods_serial as $goods_serial_k=>$goods_serial_v){
                        $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'=>$goods_serial_v['goods_id'],
                            'stores_id'=>$goods_serial_v['stores_id'],
                            'attribute_id'=>$goods_serial_v['attribute_id'],
                            'serial'=>$goods_serial_v['serial'],
                            'business_type'=>'5',
                            'store_status'=>'1',
                            'remarks' => $info_list_v['remark'],
                            'purchase_id'=>$info_list_v['id'],
                            'customer_id'=>$info_list_v['customers_id'],
                            'purchase_code'=>$info_list_v['purchase_code'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                    }
                    //序列号跟踪

                    $goods_exchange_info[] = $info_list_v;
                    //商品总库存
                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$info_list_v['goods_code']])->field('id,attribute_id,inventory,unit_id')->find();
                    //换算单位为基础单位
                    $now_unit = Db::name('kuerp_unit')->where(['id'=>$info_list_v['goods_unit']])->find();
                    if(empty($now_unit)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品单位错误'];
                        }
                        $this->error('商品单位错误');
                    }else if($now_unit['type']==2 && $now_unit['is_main']!=1){
                        $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$now_unit['id']])->find();
                        if(empty($unit_conversion)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品单位信息获取错误错误'];
                            }
                            $this->error('商品单位信息获取错误错误');
                        }else{
                            $info_list_v['retreat_number'] = $info_list_v['retreat_number']*$unit_conversion['advance_rate'];
                            $goods_exchange[] = $info_list_v;
                        }
                    }
                    $status = false;
                    foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                        if($goods_stock_v['id']==$goods['id']){
                            $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']+$info_list_v['retreat_number'];
                            $status = true;
                        }
                    }
                    if(!$status){
                        $goods_stock[] = [
                            'id'=>$goods['id'],
                            'inventory'=>$goods['inventory']+$info_list_v['retreat_number'],
                        ];
                    }

                    //获取成本单价
                    $balance_cost=0;$balance_unit=0;
                    $goodsInfo = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($info_list_v['goods_code'],$info_list_v['stores_id'],$info_list_v['goods_attr']),true);
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

                    //商品库存
                    $where = [];
                    $where['goods_code'] = $info_list_v['goods_code'];
                    $where['stores_id'] = $info_list_v['stores_id'];
                    if($info_list_v['goods_attr']>0){
                        $goods_attr = $info_list_v['goods_attr'];
                        if($goods_attr>0){
                            $where['attribute_id'] = $goods_attr;
                        }
                    }
                    $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id')->find();
                    if(empty($stock)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$info_list_v['goods_code'].'库存不足'];
                        }
                        $this->error('商品'.$info_list_v['goods_code'].'库存不足');
                    }
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] += $info_list_v['retreat_number'];
                            $status = true;
                            $balance_cost   = (round($balance_unit*$info_list_v['retreat_number'],4));
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']+$info_list_v['retreat_number'],2))
                        ];
                        $balance_cost   = (round($balance_unit*$info_list_v['retreat_number'],4));
                    }

                    //收发详情
                    if($goods){
                        $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code' => $info_list_v['purchase_code'],
                            'purchase_id'   => $info_list_v['id'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'business_type' => '5',
                            'customer_id'   => $info_list_v['customers_id'],
                            'goods_id'      => $goods['id'],
                            'goods_code'    => $info_list_v['goods_code'],
                            'attribute_id'  => (float)$info_list_v['goods_attr'],
                            'goods_unit'    => (float)$info_list_v['goods_unit'],
                            'stores_id'     => (float)$info_list_v['stores_id'],
                            'remark'        => $info_list_v['remark'],
                            'enter_number'  => (float)$info_list_v['retreat_number'],
                            'enter_basics'  => (float)$info_list_v['basic_nmber'],
                            'enter_unit'    => $balance_unit,
                            'enter_cost'    => $balance_cost,
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                    }
                    //收发详情结束
                }

                if ($list_v['retreat_money']>0){
                    $status = false;
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        if($accountData_v['id']==$list_v['account']){
                            $accountData[$accountData_k]['balance'] += $list_v['retreat_money'];
                            $status = true;
                        }
                    }
                    if(!$status){
                        $accountData[] = [
                            'balance'  => $list_v['retreat_money'],
                            'id'  => $list_v['account'],
                        ];
                    }
                }
                $need_give_account = $list_v['arrears'];
                if ($need_give_account>0){
                    $status = false;
                    foreach ($suppliers as $suppliers_k=>$suppliers_v){
                        if($suppliers_v['id']==$list_v['customers_id']){
                            $suppliers[$suppliers_k]['need_receive_account'] += $need_give_account;
                            $status = true;
                        }
                    }
                    if(!$status){
                        $suppliers[] = [
                            'need_receive_account'  => $need_give_account,
                            'id'  => $list_v['customers_id'],
                        ];
                    }
                }

                $examine_ids[] = $list_v['id'];
                if($list_v['retreat_money']>0){
                    $log[] = [
                        'account_coding'    => $account_list[$list_v['account']]['account_coding'],
                        'account_name'      => $account_list[$list_v['account']]['account_name'],
                        'balance'           => $account_list[$list_v['account']]['balance']-$list_v['retreat_money'],
                        'expenditure'       => $list_v['retreat_money'],
                        'pay_time'          => $list_v['purchase_time'],
                        'bill_code'         => $list_v['purchase_code'],
                        'business_type'     => 6,
                        'customers_id'      => $list_v['customers_id'],
                        'remark'            => $list_v['note'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }

                $payment_log[] = [
                    'customers_id'   => $list_v['customers_id'],
                    'sale_user'      => $list_v['sale_user'],
                    'business_type'  => 2,
                    'bill_time'      => $list_v['purchase_time'],
                    'bill_code'      => $list_v['purchase_code'],
                    'bill_codes'     => $list_v['purchase_code'],
                    'purchase_money' => -$list_v['all_money'],
                    'discount_money' => $list_v['preferential_money'],
                    'real_money'     => -$list_v['real_money'],
                    'money_tax_price'=> -$list_v['money_tax_price'],
                    'pay_money'      => -$list_v['retreat_money'],
                    'give_money'     => -$list_v['arrears'],
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
                    'status'         => $list_v['retreat_status']+3,
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

            $info_list_sum = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>['in',$purchase_code]])->select();
            foreach ($info_list_sum as $list_k=>$list_v){
                $info_list_sum[$list_k]['returns_order_id'] = $list_v['id'];
                unset($info_list_sum[$list_k]['id']);
                $info_list_sum[$list_k]['createtime']        = time();
                $info_list_sum[$list_k]['updatetime']        = time();
            }
            $info_list_id = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>['in',$purchase_code]])->column('id');

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
            $goods_serial = Db::name('kuerp_goods_sales_exchange_info')->where(['id'=>['in',$info_list_id]])->select();
            $goods_batch_add = [];$goods_batch_edit = [];$goods_batch_log = [];
            foreach ($goods_serial as $k=>$v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v['goods_code']])->field('id,goods_name,attribute_id,inventory,unit_id,batch')->find();
                //先通过单位计算需修改多单位商品的库存数量
                if($goods['unit_id']==$v['goods_unit']){
                    $stock_number = $v['retreat_number'];
                }else{
                    $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$v['goods_unit']])->value('advance_rate');
                    if(empty($unit_conversion)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                        }
                        $this->error('商品单位信息查询失败');
                    }else{
                        $stock_number = round(($v['retreat_number']*$unit_conversion),$number_decimal);
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
                        if($goods_batch_add_v['batch_id']==$v['batch_id'] && $goods_batch_add_v['goods_code']==$v['goods_code'] && $goods_batch_add_v['attribute_id']==$v['goods_attr'] && $goods_batch_add_v['stores_id']==$v['stores_id']){
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
                    'number'        => (float)$v['retreat_number'],
                    'purchase_code' => $v['purchase_code'],
                    'purchase_time' => $v['purchase_time'],
                    'business_type' => '5',
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
                    'business_type'     => '7',
                    'receivable'        => -$ex_list_v['arrears'],
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
                            'business_type'     => '4',
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
                $amounts = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$list_v['purchase_code']])->sum('dis_money');
                if($amounts>0){
                    $amount = $amounts;
                }
                $profit[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_type' => '2',
                    'purchase_code' => $list_v['purchase_code'],
                    'purchase_time' => $list_v['purchase_time'],
                    'customers_id'  => $list_v['customers_id'],
                    'sale_user'     => $list_v['sale_user'],
                    'preparation_id'=> $list_v['preparation_id'],
                    'examine_id'    => $admin['id'],
                    'number'        => -$list_v['retreat_number'],
                    'amount'        => -$amount,
                    'all_money'     => -$list_v['all_money'],
                    'tax_money'     => -$list_v['tax_money'],
                    'money_tax_price'=> -$list_v['money_tax_price'],
                    'preferential_money'=> -$list_v['preferential_money'],
                    'purchase_money'=> -$list_v['purchase_money'],
                    'real_money'    => -$list_v['real_money'],
                    'need_money'    => -$list_v['real_money']-$list_v['customers_money'],
                    'pay_money'     => -$list_v['pay_money'],
                    'remark'        => $list_v['note'],
                ]);
            }

            //核销单源单
            $writeoff_source = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['arrears']>0){
                    $writeoff_source[] = [
                        'purchase_type' => 3,
                        'type' => 2,
                        'customers_id' => $list_v['customers_id'],
                        'purchase_code' => $list_v['purchase_code'],
                        'purchase_time' => $list_v['purchase_time'],
                        'price' => -$list_v['real_money'],
                        'money' => -$list_v['pay_money'],
                        'write_off' => -$list_v['arrears'],
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
                //销货订单修改状态
                if(!empty($order_count)){
                    $goodsSalesOrder = (new \app\admin\model\kuerp\GoodsSalesOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsSalesOrder->where(['id'=>$order_count_v['id']])->inc('store_number',$order_count_v['store_number'])->update();
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsSalesOrder->saveAll($order_count);
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

                if(!empty($info_list_id)){
                    Db::name('kuerp_goods_batch_salee_info')->where(['purchase_info_id'=>['in',$info_list_id]])->update(['status'=>'1']);
                    Db::name('kuerp_goods_serial')->where(['sale_exchange_id'=>['in',$info_list_id]])->update(['store_status'=>'1']);
                    Db::name('kuerp_goods_saleexc_info')->where(['exchange_id'=>['in',$info_list_id]])->update(['status'=>'1','times'=>time()]);
                }

                //销售记录
                $info_list = $goods_exchange_info;
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $info_list[$info_list_k]['number'] = $info_list_v['retreat_number'];
                    unset($info_list[$info_list_k]['id']);
                    unset($info_list[$info_list_k]['purchase_id']);
                    unset($info_list[$info_list_k]['purchase_info_id']);
                    unset($info_list[$info_list_k]['retreat_status']);
                    unset($info_list[$info_list_k]['retreat_number']);
                    unset($info_list[$info_list_k]['retreat_money']);
                    $info_list[$info_list_k]['createtime'] = time();
                    $info_list[$info_list_k]['updatetime'] = time();
                }
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $info_list[$info_list_k]['purchase_type'] = 2;
                }
                Db::name('kuerp_goods_sales_info')->insertAll($info_list);

                if(!empty($info_list_sum)){
                    foreach ($info_list_sum as $info_list_k=>$info_list_v){
                        unset($info_list_sum[$info_list_k]['purchase_id']);
                        unset($info_list_sum[$info_list_k]['purchase_info_id']);
                        unset($info_list_sum[$info_list_k]['retreat_status']);
                        unset($info_list_sum[$info_list_k]['retreat_number']);
                        unset($info_list_sum[$info_list_k]['retreat_money']);
                        $info_list_sum[$info_list_k]['purchase_type'] = 2;
                        $info_list_sum[$info_list_k]['number'] = -$info_list_v['retreat_number'];
                        $info_list_sum[$info_list_k]['basic_nmber'] = -$info_list_v['basic_nmber'];
                        $info_list_sum[$info_list_k]['all_money'] = -$info_list_v['all_money'];
                        $info_list_sum[$info_list_k]['tax_money'] = -$info_list_v['tax_money'];
                        $info_list_sum[$info_list_k]['money_tax_price'] = -$info_list_v['money_tax_price'];
                        $info_list_sum[$info_list_k]['real_money'] = -$info_list_v['real_money'];
                        $info_list_sum[$info_list_k]['pay_money'] = -$info_list_v['pay_money'];
                    }
                    Db::name('kuerp_goods_sales_summary')->insertAll($info_list_sum);
                }

                //销售记录
                foreach ($exchange_info as $exchange_info_k=>$exchange_info_v){
                    $info_ids = Db::name('kuerp_goods_saleexc_info')->where(['exchange_id'=>$exchange_info_v['id']])->value('purchase_id');
                    if($info_ids){
                        $where = [];
                        $where['id'] = $info_ids;
                        $purchase_summary = Db::name('kuerp_goods_sales_summary')->where($where)->find();
                        if($purchase_summary){
                            Db::name('kuerp_goods_sales_summary')->where($where)->update([
                                'number' => ($purchase_summary['number']-$exchange_info_v['retreat_number']),
                                'basic_nmber' => ($purchase_summary['basic_nmber']-$exchange_info_v['retreat_number']),
                                'all_money' => ($purchase_summary['all_money']-$exchange_info_v['all_money']),
                                'money_tax_price' => ($purchase_summary['money_tax_price']-$exchange_info_v['money_tax_price']),
                                'tax_money' => ($purchase_summary['tax_money']-$exchange_info_v['tax_money']),
                                'real_money' => ($purchase_summary['real_money']-$exchange_info_v['real_money']),
                            ]);
                        }
                    }
                }
                Db::name('kuerp_goods_excpur_info')->where(['exchange_id'=>['in',$info_list_id]])->update(['status'=>'1','times'=>time()]);
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                if(!empty($log)){
                    Db::name('kuerp_accounts_log')->insertAll($log);
                }
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['id'=>$accountData_v['id']])->dec('balance',$accountData_v['balance'])->update();
                    }
                }

                if(!empty($suppliers)){
                    foreach ($suppliers as $suppliers_k=>$suppliers_v){
                        Db::name('kuerp_customers')->where(['id'=>$suppliers_v['id']])->dec('need_receive_account',$suppliers_v['need_receive_account'])->update();
                    }
                }
                if(!empty($payment_log)){
                    (new \app\admin\model\kuerp\SalesPaymentLog())->saveAll($payment_log);
                }
                if(!empty($invoicing)){
                    (new \app\admin\model\kuerp\SalesInvoicing())->saveAll($invoicing);
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
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
            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $account_list_obj = Db::name('kuerp_accounts')->field('id,account_coding,account_name,balance')->select();
            $account_list = [];
            foreach ($account_list_obj as $account_list_obj_k=>$account_list_obj_v){
                $account_list[$account_list_obj_v['id']] = $account_list_obj_v;
            }

            $accountData = []; $suppliers = [];$editstock = [];$goods_stock = [];$exchange_info = [];$exchange_info_code = [];
            $order_count = [];
            foreach ($list as $list_k=>$list_v){

                $info_list = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$list_v['purchase_code']])->select();
                foreach ($info_list as $info_list_k=>$info_list_v){

                    if($list_v['purchase_type']==1 && !empty($info_list_v['purchase_order_id'])){
                        $status = false;
                        foreach ($order_count as $order_count_k=>$order_count_v){
                            if($order_count_v['purchase_code']==$info_list_v['purchase_order_id']){
                                $order_count[$order_count_k]['store_number'] -= $info_list_v['retreat_number'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $order_count[] = [
                                'purchase_code'  => $info_list_v['purchase_order_id'],
                                'store_number'  => $info_list_v['retreat_number'],
                            ];
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
                    }

                    if(!in_array($info_list_v['purchase_code'],$exchange_info_code)){
                        $exchange_info_code[] = $info_list_v['purchase_code'];
                    }
                    //商品总库存
                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$info_list_v['goods_code']])->field('id,attribute_id,inventory,unit_id')->find();
                    //换算单位为基础单位
                    $now_unit = Db::name('kuerp_unit')->where(['id'=>$info_list_v['goods_unit']])->find();
                    if(empty($now_unit)){
                        if($examine_set!=1){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品单位错误'];
                            }
                            return ['status'=>0,'msg'=>'商品单位错误'];
                        }
                        $this->error('商品单位错误');
                    }else if($now_unit['type']==2 && $now_unit['is_main']!=1){
                        $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$now_unit['id']])->find();
                        if(empty($unit_conversion)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品单位信息获取错误错误'];
                            }
                            $this->error('商品单位信息获取错误错误');
                        }else{
                            $info_list_v['retreat_number'] = $info_list_v['retreat_number']*$unit_conversion['advance_rate'];
                            $exchange_info[] = $info_list_v;
                        }
                    }
                    $status = false;
                    foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                        if($goods_stock_v['id']==$goods['id']){
                            $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']-$info_list_v['retreat_number'];
                            $status = true;
                        }
                    }
                    if(!$status){
                        $goods_stock[] = [
                            'id'=>$goods['id'],
                            'inventory'=>$goods['inventory']-$info_list_v['retreat_number'],
                        ];
                    }
                    //商品库存
                    $where = [];
                    $where['goods_code'] = $info_list_v['goods_code'];
                    $where['stores_id'] = $info_list_v['stores_id'];
                    if($info_list_v['goods_attr']>0){
                        $goods_attr = $info_list_v['goods_attr'];
                        if($goods_attr>0){
                            $where['attribute_id'] = $goods_attr;
                        }
                    }
                    $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id')->find();
//                    if($stock['stock']<$info_list_v['retreat_number']){
//                        if($examine_set!=1){
//                            return ['status'=>0,'msg'=>$info_list_v['goods_code'].'库存不足'];
//                        }
//                        $this->error($info_list_v['goods_code'].'库存不足');
//                    }

                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] -= $info_list_v['retreat_number'];
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']-$info_list_v['retreat_number'],2))
                        ];
                    }
                }

                if ($list_v['retreat_money']>0){
                    $status = false;
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        if($accountData_v['id']==$list_v['account']){
                            $accountData[$accountData_k]['balance'] += $list_v['retreat_money'];
                            $status = true;
                        }
                    }
                    if(!$status){
                        $accountData[] = [
                            'balance'  => $list_v['retreat_money'],
                            'id'  => $list_v['account'],
                        ];
                    }
                }
                $need_give_account = $list_v['arrears'];
                if ($need_give_account>0){
                    $status = false;
                    foreach ($suppliers as $suppliers_k=>$suppliers_v){
                        if($suppliers_v['id']==$list_v['customers_id']){
                            $suppliers[$suppliers_k]['need_receive_account'] += $need_give_account;
                            $status = true;
                        }
                    }
                    if(!$status){
                        $suppliers[] = [
                            'need_receive_account'  => $need_give_account,
                            'id'  => $list_v['customers_id'],
                        ];
                    }
                }
            }
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');
            $info_list_id = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>['in',$purchase_code]])->column('id');

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
            $goods_serial = Db::name('kuerp_goods_sales_exchange_info')->where(['id'=>['in',$info_list_id]])->select();

            $goods_batch_add = [];$goods_batch_edit = [];
            foreach ($goods_serial as $k=>$v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v['goods_code']])->field('id,goods_name,attribute_id,inventory,unit_id,batch')->find();
                //先通过单位计算需修改多单位商品的库存数量
                if($goods['unit_id']==$v['goods_unit']){
                    $stock_number = $v['retreat_number'];
                }else{
                    $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$v['goods_unit']])->value('advance_rate');
                    if(empty($unit_conversion)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                        }
                        $this->error('商品单位信息查询失败');
                    }else{
                        $stock_number = round(($v['retreat_number']*$unit_conversion),$number_decimal);
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
                        if($goods_batch_add_v['batch_id']==$v['batch_id'] && $goods_batch_add_v['goods_code']==$v['goods_code'] && $goods_batch_add_v['attribute_id']==$v['goods_attr'] && $goods_batch_add_v['stores_id']==$v['stores_id']){
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
//                foreach ($goods_batch_edit as $k2=>$v2){
//                    if($v2<0){
//                        if($examine_set!=1){
//                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
//                        }
//                        $this->error('商品'.$goods['goods_name'].'库存不足');
//                    }
//                }
            }
            //批次库存数据结束

            //应收账款明细表
            $ex_list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $admin = Session::get('admin');
            Db::startTrans();
            try {

                if(!empty($order_count)){
                    $goodsSalesOrder = (new \app\admin\model\kuerp\GoodsSalesOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsSalesOrder->where(['id'=>$order_count_v['id']])->dec('store_number',$order_count_v['store_number'])->update();
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsSalesOrder->saveAll($order_count);
                }

                Db::name('kuerp_writeoff_source')->where(['purchase_code'=>['in',$list],'purchase_type'=>'3','type'=>'2'])->delete();

                //汇总表
                if(!empty($info_list_id)){
                    Db::name('kuerp_goods_sales_summary')->where(['purchase_type'=>'2','purchase_code'=>['in',$list]])->delete();
                }

                //销售利润表
                foreach ($plist as $plist_k=>$plist_v){
                    Db::name('kuerp_goods_sales_profit')->where(['purchase_type'=>'2','purchase_code'=>$list_v['purchase_code']])->delete();
                }
                Db::name('kuerp_cost')->where(['business_type'=>'4','purchase_number'=>['in',$ex_list]])->delete();
                Db::name('kuerp_accounts_receivable')->where(['business_type'=>'7','purchase_code'=>['in',$ex_list]])->delete();

                //序列号跟踪
                if(!empty($info_list_id)){
                    Db::name('kuerp_goods_serial_log')->where(['business_type'=>'5','purchase_id'=>['in',$info_list_id]])->delete();
                }
                //收发详情
                if(!empty($info_list_id)){
                    Db::name('kuerp_goods_log')->where(['business_type'=>'5','purchase_id'=>['in',$info_list_id]])->delete();
                }
                //批次库存数据
                //删除批次跟踪
                if(!empty($info_list_id)){
                    Db::name('kuerp_goods_batch_log')->where(['business_type'=>'5','order_id'=>['in',$info_list_id]])->delete();
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                //批次库存数据结束

                if(!empty($info_list_id)){
                    Db::name('kuerp_goods_batch_salee_info')->where(['purchase_info_id'=>['in',$info_list_id]])->update(['status'=>'0']);
                    Db::name('kuerp_goods_serial')->where(['sale_exchange_id'=>['in',$info_list_id]])->update(['store_status'=>'2']);
                    Db::name('kuerp_goods_saleexc_info')->where(['exchange_id'=>['in',$info_list_id]])->update(['status'=>'0','times'=>'']);
                }
                if(!empty($exchange_info_code)){
                    Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$exchange_info_code]])->delete();
                }
                foreach ($exchange_info as $exchange_info_k=>$exchange_info_v){
                    $info_ids = Db::name('kuerp_goods_saleexc_info')->where(['exchange_id'=>$exchange_info_v['id']])->value('purchase_id');
                    if($info_ids){
                        $where = [];
                        $where['id'] = $info_ids;
                        $purchase_summary = Db::name('kuerp_goods_sales_summary')->where($where)->find();
                        if($purchase_summary){
                            Db::name('kuerp_goods_sales_summary')->where($where)->update([
                                'number' => ($purchase_summary['number']+$exchange_info_v['retreat_number']),
                                'basic_nmber' => ($purchase_summary['basic_nmber']+$exchange_info_v['retreat_number']),
                                'all_money' => ($purchase_summary['all_money']+$exchange_info_v['all_money']),
                                'tax_money' => ($purchase_summary['tax_money']+$exchange_info_v['tax_money']),
                                'money_tax_price' => ($purchase_summary['money_tax_price']+$exchange_info_v['money_tax_price']),
                                'real_money' => ($purchase_summary['real_money']+$exchange_info_v['real_money'])
                            ]);
                        }
                    }
                }
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>0,'examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$list],'business_type'=>'6'])->delete();
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['id'=>$accountData_v['id']])->inc('balance',$accountData_v['balance'])->update();
                    }
                }
                if(!empty($suppliers)){
                    foreach ($suppliers as $suppliers_k=>$suppliers_v){
                        Db::name('kuerp_customers')->where(['id'=>$suppliers_v['id']])->inc('need_receive_account',$suppliers_v['need_receive_account'])->update();
                    }
                }
                Db::name('kuerp_sales_payment_log')->where(['bill_codes'=>['in',$list]])->delete();
                Db::name('kuerp_sales_invoicing')->where(['bill_codes'=>['in',$list]])->delete();
                Db::commit();
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
                    return ['status'=>0,'msg'=>'反审核失败'];
                }
                $this->error('反审核失败');
            }
        }
    }
    //获取应付款余额
    public function getSuppliers($supplyer_code){
        if(empty($supplyer_code)){
            return 0;
        }else{
            $give_account = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$supplyer_code])->value('need_give_account');
            return (float)$give_account;
        }
    }
}
