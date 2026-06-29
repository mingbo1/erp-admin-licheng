<?php

namespace app\admin\controller\kuerp;

use app\admin\model\Admin;
use app\admin\model\kuerp\GoodsSalesOrderLog;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 购货单
 *
 * @icon fa fa-circle-o
 */
class GoodsPurchase extends Backend
{
    protected $noNeedRight = ['checkpurchase','getsuppliers','getunit','formatinfo','formatinfo2','getbranch','gettime','printpage','summary'];
    /**
     * GoodsPurchase模型对象
     * @var \app\admin\model\kuerp\GoodsPurchase
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsPurchase;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("suppliersList", $this->model->getSuppliersList());
        $this->assignconfig("suppliersLists", $this->model->getSuppliersList());
        $this->assignconfig("expendTypeLists", $this->model->getExpendTypeList());
        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("accountsList", $this->model->getAccountsList());

        $accountsList = $this->model->getAccountsList();
        foreach ($accountsList as $accountsList_k=>$accountsList_v){
            if($accountsList_v['is_default']==1){
                $this->view->assign("accountsDefault", $accountsList_v['id']);
                $this->assignconfig("accountsDefault", $accountsList_v['id']);
            }
        }

        $this->view->assign("secondExamineStatusList", $this->model->getSecondExamineStatusList());

        // 二次审核金额阈值
        $purchase_order_examine_money = Db::name('kuerp_sets')->where(['name'=>'purchase_order_examine_money'])->value('value');
        $this->assignconfig('purchase_order_examine_money', $purchase_order_examine_money ? $purchase_order_examine_money : '0');
    }

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
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function add($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
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
                $number = 0;$all_money = 0;$purchase_order_id = '';$tax_money=0;$goods_serial_all = [];$money_tax_price=0;
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

                        if(!empty($params['info_id'][$k])){
                            $unit_id_order = Db::name('kuerp_goods_purchase_order_info')->where(['id'=>$params['info_id'][$k]])->value('goods_unit');
                            if(!empty($unit_id_order) && $unit_id_order!=$params['goods_unit'][$k]){
                                $this->error('选择的商品单位与源购货订单不一致，保存失败');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'supplyer_code'     =>  $params['supplyer_code'],//供应商

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
                            // 'discount'          =>  (float)$params['discount'][$k],
                            // 'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'batch_id'          => $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'batch_name'        => $params['batch'][$k]===''?null:$params['batch'][$k],
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
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'supplyer_code'     =>  $params['supplyer_code'],//供应商
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,
                        'tax_money'         =>  $tax_money,
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
                    $order_id = Db::name('kuerp_goods_purchase_order')->where(['purchase_code'=>$purchase_order_id])->value('id');
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
                                'type'          => '2',
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

                    foreach ($data as $data_k=>$data_v){
                        $purchase_id = Db::name('kuerp_goods_purchase_info')->insertGetId($data_v);
                        if(!empty($data_v['order_info_id'])){
                            Db::name('kuerp_goods_purord_info')->insert([
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
                            Db::name('kuerp_goods_purord')->insert(['order_id'=>$order_id,'purchase_id'=>$result]);
                            // 写入销货订单日志：生成购货单
                            $salesOrderCode = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->value('sales_order_id');
                            if (!empty($salesOrderCode)) {
                                GoodsSalesOrderLog::writeLogByCode($salesOrderCode, '生成购货单', '生成购货单：' . $params['purchase_code'], 'purchase', $result);
                            }
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
                            $exa = $this->_examine($result,1);
                        } catch (Exception $e) {
                        }
                        if(!isset($exa) || $exa['status']!=1){
                            $exa = $this->_examine($result,1);
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
        $code = \addons\kuerp\library\Goods::getCodes('',8);
        $this->view->assign("code", $code);

        $last_customer = Db::name('kuerp_sets')->where(['name'=>'customer'])->value('is_checked');
        $set_customer = '';
        if($last_customer){
            $set_customer = Db::name('kuerp_goods_purchase')->order('id','DESC')->value('supplyer_code');
        }

        $row = Db::name('kuerp_goods_purchase_order')->where(['id'=>$ids])->find();
        $give_account = 0;$real_money = 0;$arrears = 0;$real_moneys = 0;
        if($row){
            $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                $purord_info = Db::name('kuerp_goods_purord_info')->where(['order_id'=>$purchase_info_v['id']])->field('sum(number) as number,sum(money) as money')->select();
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
            $give_account = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$row['supplyer_code']])->value('need_give_account');
        }else{
            $row['supplyer_code'] = $set_customer;
        }
        $row['real_money'] = $real_moneys;
        $purchase_info = isset($purchase_info)?$purchase_info:[];
        $this->assignconfig("give_account", $give_account);
        $give_account = $give_account+$real_money+$row['real_money'];
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);
        $this->view->assign("give_account", $give_account);

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'1'])->column('enclosure_id');
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
            $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$ids])->column('exchange_id');
            if(!empty($purchase_id)){
                $this->error('不能编辑已生成退货单的数据');
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);
                $info_id = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>$old_purchase_code])->column('id');

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
                $number = 0;$all_money = 0;$tax_money=0;$goods_serial_all=[];$money_tax_price=0;
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

                        if(!empty($params['info_id'][$k])){
                            $unit_id_order = Db::name('kuerp_goods_purchase_order_info')->where(['id'=>$params['info_id'][$k]])->value('goods_unit');
                            if(!empty($unit_id_order) && $unit_id_order!=$params['goods_unit'][$k]){
                                $this->error('选择的商品单位与源购货订单不一致，保存失败');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'supplyer_code'     =>  $params['supplyer_code'],//供应商

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
                            // 'discount'          =>  (float)$params['discount'][$k],
                            // 'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'batch_id'          => $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'batch_name'        => $params['batch'][$k]===''?null:$params['batch'][$k],
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
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'supplyer_code'     =>  $params['supplyer_code'],//供应商
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,
                        'tax_money'         =>  $tax_money,
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
                                'type'          => '2',
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
                        $this->_examine($ids,2);
                    }

                    //添加附件记录
                    Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'2'])->delete();
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    if(!empty($info_id)){
                        Db::name('kuerp_goods_purord_info')->where(['purchase_id'=>['in',$info_id]])->delete();
                    }
                    Db::name('kuerp_goods_purchase_info')->where(['id'=>['in',$info_id]])->delete();

                    foreach ($data as $data_k=>$data_v){
                        $purchase_id = Db::name('kuerp_goods_purchase_info')->insertGetId($data_v);
                        if(!empty($data_v['order_info_id'])){
                            Db::name('kuerp_goods_purord_info')->insert([
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
                    if($examine!=1){
                        try {
                            $exa = $this->_examine($ids,1);
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

        $purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['unit'] = json_decode($this->getUnit($purchase_info_v['goods_code']),true);
            $purchase_info[$purchase_info_k]['serial'] = $purchase_info_v['period'];
        }
        $this->view->assign("purchase_info", $purchase_info);

        $give_account = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$row['supplyer_code']])->value('need_give_account');
        if($row['examine_status']==1){
            $this->assignconfig("give_account", $give_account-$row['arrears']);
            $this->view->assign("give_account", (float)$give_account);
        }else{
            $this->assignconfig("give_account", $give_account);
            $this->view->assign("give_account", (float)$give_account+$row['arrears']);
        }

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'2'])->column('enclosure_id');
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
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');

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
                        $this->_examine($list_v['id'],2);
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
            $goods_purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>['in',$goods_purchase_code]])->column('id');

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
                }
                if(!empty($goods_purchase_id)){//购货订单关联
                    Db::name('kuerp_goods_purord')->where(['purchase_id'=>['in',$goods_purchase_id]])->delete();
                }
                if(!empty($goods_purchase_info)){//购货订单详情关联
                    Db::name('kuerp_goods_purord_info')->where(['purchase_id'=>['in',$goods_purchase_info]])->delete();
                }

                //删除附件记录
                Db::name('kuerp_enclosure_log')->where(['purchase_code'=>$v['purchase_code'],'type'=>'2'])->delete();
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

    //审核/反审核 (直接访问请使用"采购单入库"模块)
    public function examine($ids = null,$examine=1){
       
        return $this->_examine($ids, $examine);
    }

    //审核/反审核 (内部逻辑)
    protected function _examine($ids = null,$examine=1){
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            if($examine_set!=1){
                return ['status'=>0,'msg'=>'系统错误'];
            }
            $this->error('请选择要操作的数据');
        }

        $serial_set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');//序列号设置
        $batch_set= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');//批次设置

        if($examine==1){
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
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
            $arrears = [];
            $log = [];
            $payment_log = [];$invoicing = [];
            foreach ($list as $list_k=>$list_v){
                $examine_ids[] = $list_v['id'];
                if($list_v['arrears']>0){
                    $suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$list_v['supplyer_code']])->field('id,need_give_account')->find();
                    if($suppliers){
                        $status = false;
                        foreach ($arrears as $arrears_k=>$arrears_v){
                            if($arrears_v['id']==$suppliers['id']){
                                $arrears[$arrears_k]['need_give_account'] = $list_v['arrears']+$arrears_v['need_give_account'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $arrears[] = [
                                'id'  => $suppliers['id'],
                                'need_give_account'  => $list_v['arrears']+$suppliers['need_give_account'],
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
                        'expenditure'       => $list_v['pay_money'],
                        'pay_time'          => $list_v['purchase_time'],
                        'bill_code'         => $list_v['purchase_code'],
                        'business_type'     => 2,
                        'supplyer_code'     => $list_v['supplyer_code'],
                        'remark'            => $list_v['note'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }
                $payment_log[] = [
                    'suppliers_code' => $list_v['supplyer_code'],
                    'business_type'  => 1,
                    'bill_time'      => $list_v['purchase_time'],
                    'bill_code'      => $list_v['purchase_code'],
                    'bill_codes'     => $list_v['purchase_code'],
                    'purchase_money' => $list_v['all_money'],
                    'money_tax_price'=> $list_v['all_money']-$list_v['preferential_money'],
                    'discount_money' => $list_v['preferential_money'],
                    'real_money'     => $list_v['real_money'],
                    'pay_money'      => $list_v['pay_money'],
                    'give_money'     => $list_v['arrears'],
                    'reamrk'         => $list_v['note'],
                    'createtime'     => time(),
                ];
                $invoicing[] = [
                    'suppliers_code' => $list_v['supplyer_code'],
                    'status'         => $list_v['pay_status'],
                    'bill_time'      => $list_v['purchase_time'],
                    'bill_code'      => $list_v['purchase_code'],
                    'bill_codes'     => $list_v['purchase_code'],
                    'money'          => ($list_v['real_money']-$list_v['tax_money']),
                    'tax'            => $list_v['tax_money'],
                    'real_money'     => $list_v['real_money'],//价税
                    'pay_money'      => 0,
                    'give_money'     => $list_v['real_money'],
                    'createtime'     => time(),
                ];
            }

            $info_list = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>['in',$purchase_code]])->select();
            $purchase_info_id = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>['in',$purchase_code]])->column('id');
            $purchase_info = [];
            $editstock = [];//库存表修改库存
            $addstock = [];//库存表添加库存
            $goods_stock = [];//商品列表库存
            $goods_stock_log = [];//商品收发明细
            $goods_batch_add = [];//批次库存新增
            $goods_batch_edit = [];//批次库存更新
            $goods_batch_log = [];//批次记录
            $per_code = [];//序列号
            $goods_serial = [];//序列号添加
            $edit_serial = [];//序列号更新
            $serial_log = [];//序列号跟踪
            foreach ($info_list as $info_list_k=>$info_list_v){
                $purchase_info[] = $info_list_v['id'];
                $info_list[$list_k]['sales_order_id']    = $info_list_v['id'];
                $info_list[$list_k]['createtime']        = time();
                $info_list[$list_k]['updatetime']        = time();

                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$info_list_v['goods_code']])->find();
                $stock_number = $info_list_v['basic_nmber'];//基础数量
                //商品表库存
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
                //商品表库存结束

                //商品库存表库存
                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                $goods_attr = null;
                if($info_list_v['goods_attr']>0){
                    $goods_attr = $info_list_v['goods_attr'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] += $stock_number;
                            $editstock[$editstock_k]['cost_money'] = (round($editstock_v['cost_money']+$info_list_v['money_tax_price'],4));
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']+$stock_number,4)),
                            'cost_money'=>(round($stock['cost_money']+$info_list_v['money_tax_price'],4))
                        ];
                    }
                }else{
                    $status = false;
                    foreach ($addstock as $addstock_k=>$addstock_v){
                        if($addstock_v['goods_code']==$info_list_v['goods_code'] && $addstock_v['attribute_id']==$goods_attr && $addstock_v['stores_id']==$info_list_v['stores_id']){
                            $addstock[$addstock_k]['stock'] += $stock_number;
                            $addstock[$addstock_k]['cost_money'] = (round($addstock_v['cost_money']+$info_list_v['money_tax_price'],4));
                            $status = true;
                        }
                    }
                    if(!$status){
                        $addstock[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'  => isset($goods['id'])?$goods['id']:'',
                            'goods_code'=> $info_list_v['goods_code'],
                            'attribute_id'  => isset($goods_attr)?$goods_attr:0,
                            'stores_id'  => $info_list_v['stores_id'],
                            'stock'  => $stock_number,
                            'cost_money'=>(round($info_list_v['money_tax_price'],4)),
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                    }
                }
                //商品库存表库存结束

                //收发详情
                $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info_list_v['purchase_code'],
                    'purchase_id'   => $info_list_v['id'],
                    'purchase_time' => $info_list_v['purchase_time'],
                    'business_type' => '2',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info_list_v['goods_attr'],
                    'goods_unit'    => (float)$info_list_v['goods_unit'],
                    'stores_id'     => (float)$info_list_v['stores_id'],
                    'remark'        => $info_list_v['remark'],
                    'enter_number'  => (float)$info_list_v['number'],
                    'enter_basics'  => (float)$stock_number,
                    'enter_unit'    => round($info_list_v['tax_included'],4),
                    'enter_cost'    => (float)$info_list_v['money_tax_price'],
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);
                //收发详情结束

                //批次跟踪
                if($batch_set==1 && $goods['batch']==1){
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];

                    if(empty($info_list_v['batch_id'])){
                        $info_list_v['batch_id'] = Db::name('kuerp_goods_batch')->where(
                            [
                                'goods_id' => $goods['id'],
                                'attribute_id' => isset($goods_attr)?$goods_attr:0,
                                'stores_id' => $info_list_v['stores_id'],
                                'batch_name' => $info_list_v['batch_name'],
                            ]
                        )->value('id');
                        if(!empty($info_list_v['batch_id'])){
                            $edit_batch[] = ['id'=>$info_list_v['id'],'batch_id'=>$info_list_v['batch_id']];
                        }
                    }

                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $status = false;
                        foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                            if($goods_batch_add_v['batch_id']==$info_list_v['batch_id'] && $goods_batch_add_v['goods_id']==$info_list_v['goods_id'] && $goods_batch_add_v['attribute_id']==$goods_attr && $goods_batch_add_v['stores_id']==$info_list_v['stores_id']){
                                $goods_batch_add[$goods_batch_add_k]['stock'] += $stock_number;
                                $status = true;
                            }
                        }
                        if(!$status){
                            $batch_id = Db::name('kuerp_goods_batch')->where(
                                [
                                    'goods_id' => $goods['id'],
                                    'attribute_id' => isset($goods_attr)?$goods_attr:0,
                                    'stores_id' => $info_list_v['stores_id'],
                                    'batch_name' => $info_list_v['batch_name'],
                                ]
                            )->value('id');
                            if(empty($batch_id)){
                                $batch_id = Db::name('kuerp_goods_batch')->insertGetId(\addons\kuerp\library\Goods::setDataInfo([
                                    'goods_id' => $goods['id'],
                                    'goods_code' => $goods['goods_code'],
                                    'attribute_id' => isset($goods_attr)?$goods_attr:0,
                                    'stores_id' => $info_list_v['stores_id'],
                                    'batch_name' => $info_list_v['batch_name'],
                                    'manufacture' => $info_list_v['manufacture'],
                                    'guarantee' => $info_list_v['guarantee'],
                                    'validity' => $info_list_v['validity'],
                                    'createtime' => time(),
                                    'updatetime' => time(),
                                ]));
                            }
                            $info_list_v['batch_id'] = $batch_id;
                            $edit_batch[] = ['id'=>$info_list_v['id'],'batch_id'=>$batch_id];
                            $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                                'batch_id' => $batch_id,
                                'goods_id' => $goods['id'],
                                'goods_code' => $goods['goods_code'],
                                'attribute_id' => $goods_attr,
                                'stores_id' => $info_list_v['stores_id'],
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
                    //商品批次跟踪
                    $suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$info_list_v['supplyer_code']])->value('id');
                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'goods_unit'    => $info_list_v['goods_unit'],
                        'stores_id'     => $info_list_v['stores_id'],
                        'batch_id'      => (float)$info_list_v['batch_id'],
                        'number'        => (float)$info_list_v['basic_nmber'],
                        'purchase_code' => $info_list_v['purchase_code'],
                        'purchase_time' => $info_list_v['purchase_time'],
                        'business_type' => '2',
                        'remarks'       => $info_list_v['remark'],
                        'order_id'      => $info_list_v['id'],
                        'unit_price'    => $info_list_v['unit_price'],
                        'tax_included'  => $info_list_v['tax_included'],
                        'all_money'     => $info_list_v['all_money'],
                        'tax_money'     => $info_list_v['tax_money'],
                        'money_tax_price' => $info_list_v['money_tax_price'],
                        'customer_id'   => $suppliers,
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                    //商品批次跟踪结束
                }

                //序列号跟踪
                if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                    $period = unserialize($info_list_v['period']);
                    foreach ($period as $period_k=>$period_v){
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->find();
                        if($per_s){
                            return ['status'=>500,'msg'=>'商品序列号已被使用'];
                        }
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->field('serial,status,store_status')->find();//序列号
                        if($per_s){//仓库有记录
                            $edit_serial[] = ['serial'=>$per_s['serial'],'assemble_id'=>$info_list_v['id'],'type'=>'1','store_status'=>1,'old_status'=>$per_s['status'],'old_store'=>$per_s['store_status']];
                        }else{
                            if(!in_array($period_v['serial'],$per_code)){
                                $per_code[] = $period_v['serial'];
                            }
                            $goods_serial[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_id' => $info_list_v['id'],
                                'purchase_code'=>$info_list_v['purchase_code'],
                                'goods_id'=>$goods['id'],
                                'goods_code'=>$goods['goods_code'],
                                'stores_id'=>$info_list_v['stores_id'],
                                'attribute_id'=>isset($goods_attr)?$goods_attr:null,
                                'serial'=>$period_v['serial'],
                                'remark'=>$period_v['remark'],
                                'status'=> '1',
                                'store_status'=>'1',
                                'order_id'      => $info_list_v['id'],
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ]);
                        }
                        $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'=>$goods['id'],
                            'stores_id'=>$info_list_v['stores_id'],
                            'attribute_id'=>isset($goods_attr)?$goods_attr:0,
                            'serial'=>$period_v['serial'],
                            'business_type'=>'2',
                            'store_status'=>'1',
                            'remarks' => $period_v['remark'],
                            'purchase_id'=>$info_list_v['id'],
                            'purchase_code'=>$info_list_v['purchase_code'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'order_id'      => $info_list_v['id'],
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                    }
                }
                //序列号跟踪
            }

            $order_count = [];
            $code_id = Db::name('kuerp_goods_purord_info')->where(['purchase_id'=>['in',$purchase_info]])->column('order_id');
            $purchase_order_info = Db::name('kuerp_goods_purchase_order_info')
                ->field('id,purchase_code')
                ->where(['id'=>['in',$code_id]])
                ->select();
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $purord_info = Db::name('kuerp_goods_purord_info')
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
                    $number = Db::name('kuerp_goods_purchase_order')->where(['purchase_code'=>$order_count_v['purchase_code']])->field('id,number,store_number')->find();
                    if(($number['number']-$number['store_number'])<=$order_count_v['store_number']){
                        $order_count[$order_count_k]['status'] = 1;
                    }else{
                        $order_count[$order_count_k]['status'] = 2;
                    }
                    $order_count[$order_count_k]['id'] = $number['id'];
                }
            }

            //应付账款明细表
            $ex_list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            $accounts_copewith = [];
            foreach ($ex_list as $ex_list_k=>$ex_list_v){
                $suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$ex_list_v['supplyer_code']])->value('id');
                $accounts_copewith[] = \addons\kuerp\library\Goods::setDataInfo([
                    'suppliers_id'      => $suppliers,
                    'purchase_time'     => $ex_list_v['purchase_time'],
                    'purchase_code'     => $ex_list_v['purchase_code'],
                    'business_type'     => '6',
                    'receivable'        => $ex_list_v['arrears'],
                    'remark'            => $ex_list_v['note'],
                    'sale_money'        => round(($ex_list_v['real_money']+$ex_list_v['preferential_money']),4),
                    'receivable_money'  => $ex_list_v['real_money'],
                    'amount'            => $ex_list_v['preferential_money'],
                    'cost'              => null,
                    'real_money'        => $ex_list_v['pay_money'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);
            }

            $admin = Session::get('admin');

            //采购销售费用
            $cost_data = [];
            $suppliers = Db::name('kuerp_suppliers')->column('id','supplyer_code');
            foreach ($list as $list_k=>$list_v){
                if(!empty($list_v['cost_data'])){
                    $cost_datas = unserialize($list_v['cost_data']);
                    foreach ($cost_datas as $cost_datas_k=>$cost_datas_v){
                        $list_v['supplyer_code'] = isset($list_v['supplyer_code'])?$list_v['supplyer_code']:'';
                        $cost_data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_time'     => time(),
                            'purchase_code'     => \addons\kuerp\library\Goods::getCodes('',27),
                            'business_type'     => '1',
                            'suppliers_ids'     => isset($suppliers[$list_v['supplyer_code']])?$suppliers[$list_v['supplyer_code']]:null,
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

            //核销单源单
            $writeoff_source = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['arrears']>0){
                    $writeoff_source[] = [
                        'purchase_type' => 4,
                        'type' => 1,
                        'suppliers_code' => $list_v['supplyer_code'],
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
                //修改批次ID
                if(isset($edit_batch) && !empty($edit_batch)){
                    (new \app\admin\model\kuerp\GoodsPurchaseInfo())->saveAll($edit_batch);
                }
                if(!empty($cost_data)){
                    Db::name('kuerp_cost')->insertAll($cost_data);
                }
                if(!empty($accounts_copewith)){
                    Db::name('kuerp_accounts_copewith')->insertAll($accounts_copewith);
                }

                //库存表修改库存
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                //库存表添加库存
                if(!empty($addstock)){
                    foreach ($addstock as $addstock_k=>$addstock_v){
                        $where = [];
                        $where['goods_code'] = $addstock_v['goods_code'];
                        $where['stores_id'] = $addstock_v['stores_id'];
                        if($addstock_v['attribute_id']>0){
                            $where['attribute_id'] = $addstock_v['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_stock')->where($where)->find();
                        if($stock){
                            Db::name('kuerp_goods_stock')->where(['id'=>$stock['id']])->inc('stock',$addstock_v['stock'])->update();
                        }else{
                            Db::name('kuerp_goods_stock')->insert($addstock_v);
                        }
                    }
                }
                //商品列表库存
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                //商品收发明细
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                //批次库存新增
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
                //批次库存更新
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                //批次记录
                if(!empty($goods_batch_log)){
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
                }
                //序列号添加
                if(!empty($goods_serial)){
                    Db::name('kuerp_goods_serial')->insertAll($goods_serial);
                }
                //序列号更新
                if(!empty($edit_serial)){
                    foreach ($edit_serial as $edit_serial_k=>$edit_serial_v){
                        Db::name('kuerp_goods_serial')->where(['serial'=>$edit_serial_v['serial']])->update([
                            'assemble_id'=>$edit_serial_v['assemble_id'],
                            'status'=>$edit_serial_v['type'],
                            'store_status'=>$edit_serial_v['store_status'],
                            'old_status'=>$edit_serial_v['old_status'],
                            'old_store'=>$edit_serial_v['old_store']
                        ]);
                    }
                }
                //序列号跟踪
                if(!empty($serial_log)){
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                }

                //批次库存数据结束
                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_serial')->where(['purchase_id'=>['in',$purchase_info_id]])->update(['store_status'=>1]);
                    Db::name('kuerp_goods_batch_info')->where(['purchase_info_id'=>['in',$purchase_info_id]])->update(['status'=>1]);
                }

                if(!empty($order_count)){
                    $goodsPurchaseOrder = (new \app\admin\model\kuerp\GoodsPurchaseOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsPurchaseOrder->where(['id'=>$order_count_v['id']])->inc('store_number',$order_count_v['store_number'])->update();
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsPurchaseOrder->saveAll($order_count);
                    // 写入销货订单日志：零件入库
                    foreach ($order_count as $oc) {
                        $salesOrderCode = Db::name('kuerp_goods_purchase_order')->where(['id' => $oc['id']])->value('sales_order_id');
                        if (!empty($salesOrderCode)) {
                            GoodsSalesOrderLog::writeLogByCode($salesOrderCode, '零件入库', '购货单审核入库，关联购货单号：' . implode(',', $purchase_code), 'purchase', $ids);
                            break; // 同一个订单只写一次
                        }
                    }
                }

                if(!empty($payment_log)){
                    (new \app\admin\model\kuerp\PaymentLog())->saveAll($payment_log);
                }
                if(!empty($invoicing)){
                    (new \app\admin\model\kuerp\Invoicing())->saveAll($invoicing);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>['in',$purchase_code]])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                if(!empty($log)){
                    Db::name('kuerp_accounts_log')->insertAll($log);
                }
                if(!empty($arrears)){
                    (new \app\admin\model\kuerp\Suppliers())->saveAll($arrears);
                }
                Db::name('kuerp_goods_purord_info')->where(['purchase_id'=>['in',$purchase_info]])->update(['status'=>'1','times'=>time()]);
                foreach ($info_list as $info_list_k=>$info_list_v){
                    unset($info_list[$info_list_k]['id']);
                    unset($info_list[$info_list_k]['storage_number']);
                }
                foreach ($info_list as $info_list_k=>$info_list_v){
                    if(isset($info_list_v['id'])){
                        unset($info_list[$info_list_k]['id']);
                    }
                    if(isset($info_list_v['storage_number'])){
                        unset($info_list[$info_list_k]['storage_number']);
                    }
                }
                Db::name('kuerp_goods_purchase_summary')->insertAll($info_list);
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['id'=>$accountData_v['account']])->dec('balance',$accountData_v['balance'])->update();
                    }
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
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('请选择需要反审核的数据');
            }
            foreach ($list as $list_k=>$list_v){
                if($list_v['check_status']==1){
                    $this->error('不能反审核已对单的数据');
                }
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id'],'type'=>'2'])->column('exchange_id');
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成退货单的数据');
                }
                $purchase_id = Db::name('kuerp_payment_sale')->where(['purchase_number'=>$list_v['purchase_code']])->find();
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成付款单的数据'];
                    }
                    $this->error('不能反审核已生成付款单的数据');
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
                    $suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$list_v['supplyer_code']])->field('id,need_give_account')->find();
                    if($suppliers){
                        $status = false;
                        foreach ($arrears as $arrears_k=>$arrears_v){
                            if($arrears_v['id']==$suppliers['id']){
                                $arrears[$arrears_k]['need_give_account'] = $arrears_v['need_give_account']-$list_v['arrears'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $arrears[] = [
                                'id'  => $suppliers['id'],
                                'need_give_account'  => $suppliers['need_give_account']-$list_v['arrears'],
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

            $log = Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$list],'business_type'=>2])->select();
            $accountData = [];
            foreach ($log as $list_k=>$list_v){
                $accountData[] = [
                    'balance'  => $list_v['expenditure'],
                    'account'  => $list_v['account_coding'],
                ];
            }

            $info_list = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>['in',$list]])->select();
            $editstock = [];//库存表修改库存
            $goods_stock = [];//商品列表库存
            $goods_batch_edit = [];//批次库存更新
            $purchase_info = [];
            foreach ($info_list as $info_list_k=>$info_list_v){
                $stock_number = $info_list_v['basic_nmber'];
                $purchase_info[] = $info_list_v['id'];

                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$info_list_v['goods_code']])->find();
                //商品表库存
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
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['inventory']<0){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                        }
                        $this->error($goods['goods_name'].'库存不足');
                    }
                }
                //商品表库存结束

                //商品库存表库存
                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                $goods_attr = null;
                if($info_list_v['goods_attr']>0){
                    $goods_attr = $info_list_v['goods_attr'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] -= $stock_number;
                            $editstock[$editstock_k]['cost_money'] = (round($editstock_v['cost_money']-$info_list_v['money_tax_price'],4));
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']-$stock_number,4)),
                            'cost_money'=>(round($stock['cost_money']-$info_list_v['money_tax_price'],4))
                        ];
                    }
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['stock']<0){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                            }
                            $this->error($goods['goods_name'].'库存不足');
                        }
                    }
                }else{
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                    }
                    $this->error($goods['goods_name'].'库存不足');
                }
                //商品库存表库存结束

                //批次跟踪
                if($batch_set==1 && $goods['batch']==1){
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>$goods['goods_name'].$info_list_v['batch_name'].'库存不足'];
                        }
                        $this->error($goods['goods_name'].$info_list_v['batch_name'].'库存不足');
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                        foreach ($goods_batch_edit as $goods_batch_edit_k=>$goods_batch_edit_v){
                            if($goods_batch_edit_v<0){
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$goods['goods_name'].$info_list_v['batch'].'库存不足'];
                                }
                                $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                            }
                        }
                    }
                }

                //序列号跟踪
                if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                    $period = unserialize($info_list_v['period']);
                    foreach ($period as $period_k=>$period_v){
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->find();//序列号
                        if($per_s){//仓库有记录
                            $edit_serial[] = ['serial'=>$per_s['serial'],'assemble_id'=>$info_list_v['id'],'type'=>$per_s['old_status'],'store_status'=>$per_s['old_store']];
                        }else{
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>$goods['goods_name'].'序列号'.$period_v['serial'].'不存在'];
                            }
                            $this->error($goods['goods_name'].'序列号'.$period_v['serial'].'不存在');
                        }
                    }
                }
                //序列号跟踪
            }
            $order_count = [];//先组装半入库
            $code_id = Db::name('kuerp_goods_purord_info')->where(['purchase_id'=>['in',$purchase_info]])->column('order_id');
            $purchase_order_info = Db::name('kuerp_goods_purchase_order_info')
                ->field('id,purchase_code')
                ->where(['id'=>['in',$code_id]])
                ->select();
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $purord_info = Db::name('kuerp_goods_purord_info')
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
                    $number = Db::name('kuerp_goods_purchase_order')->where(['purchase_code'=>$order_count_v['purchase_code']])->field('id,number,store_number')->find();
                    if($number['store_number']<=$order_count_v['store_number']){
                        $order_count[$order_count_k]['status'] = 0;
                    }else{
                        $order_count[$order_count_k]['status'] = 2;
                    }
                    $order_count[$order_count_k]['id'] = $number['id'];
                }
            }
            //应付账款明细表
            $ex_list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                Db::name('kuerp_writeoff_source')->where(['purchase_code'=>['in',$list],'purchase_type'=>'4','type'=>'1'])->delete();

                Db::name('kuerp_cost')->where(['business_type'=>'1','purchase_number'=>['in',$ex_list]])->delete();
                Db::name('kuerp_accounts_copewith')->where(['business_type'=>'6','purchase_code'=>['in',$ex_list]])->delete();

                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }

                Db::name('kuerp_goods_log')->where(['business_type'=>'2','purchase_id'=>['in',$purchase_info]])->delete();

                Db::name('kuerp_goods_batch_log')->where(['business_type'=>'2','order_id'=>['in',$purchase_info]])->delete();
                Db::name('kuerp_goods_serial')->where(['purchase_code'=>['in',$purchase_code]])->delete();
                Db::name('kuerp_goods_serial_log')->where(['business_type'=>'2','order_id'=>['in',$purchase_info]])->delete();

                if(!empty($order_count)){
                    $goodsPurchaseOrder = (new \app\admin\model\kuerp\GoodsPurchaseOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsPurchaseOrder->where(['id'=>$order_count_v['id']])->dec('store_number',$order_count_v['store_number'])->update();
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsPurchaseOrder->saveAll($order_count);
                }

                Db::name('kuerp_payment_log')->where(['bill_codes'=>['in',$list]])->delete();
                Db::name('kuerp_invoicing')->where(['bill_codes'=>['in',$list]])->delete();

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>['in',$list]])->update(['examine_status'=>'0','examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$list],'business_type'=>2])->delete();
                Db::name('kuerp_goods_purchase_summary')->where(['purchase_code'=>['in',$list]])->delete();
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['account_coding'=>$accountData_v['account']])->inc('balance',$accountData_v['balance'])->update();
                    }
                }
                if(!empty($arrears)){
                    (new \app\admin\model\kuerp\Suppliers())->saveAll($arrears);
                }
                Db::name('kuerp_goods_purord_info')->where(['purchase_id'=>['in',$purchase_info]])->update(['status'=>'0']);
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
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('反审核失败');
            }
        }
    }

    //对单/反对单 (直接访问请使用"采购单入库"模块)
    public function counter($ids = null,$counter=1){
     
        return $this->_counter($ids, $counter);
    }

    //对单/反对单 (内部逻辑)
    protected function _counter($ids = null,$counter=1){
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

    //检测可生成退货单
    public function checkPurchase(){
        $order_id = isset($_POST['exchange'])?trim($_POST['exchange']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_purchase')->where(['id'=>$order_id])
            ->field('examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        if($row['examine_status']!=1){
            $this->error('此购货单未审核，不可生成退货单');
        }
        $purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_excpur_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'2'])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此购货单已全部生成退货单');
        }
        $this->success();
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
    //获取单位价格信息
    public function getUnit($goods_code='',$store='',$attr='',$supplyer_code=''){
        $set= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');

        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:0;
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])->field('batch,serial,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')->find();
        }
        if(empty($row)){
            return [];
        }
        if($supplyer_code){
            $suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$supplyer_code])->value('vat_rate');
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
            //$attribute_id = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->value('attribute_id');
            $where['attribute_id'] = $attr;
        }
        $stock = Db::name('kuerp_goods_stock')->where($where)->value('stock');

        // 始终调整可用库存（考虑锁定）
        // - 传入 sales_order_id → 可看到自己的锁定库存
        // - 未传入 → 扣除所有锁定库存（无关联订单的购货单/销货单等）
        $salesOrderId = isset($_POST['sales_order_id']) ? trim($_POST['sales_order_id']) : '';
        $attrId = isset($attr) ? (int)$attr : 0;
        $storesId = isset($where['stores_id']) ? (int)$where['stores_id'] : 0;
        $stock = \addons\kuerp\library\Goods::getAvailableStock($goods_id, $storesId, $attrId, $salesOrderId);

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
                        //$unit[$unit_k]['purchase'] = $goods_price_v['purchase']*$goods_price_v['advance_rate'];
                        $unit[$unit_k]['purchase'] = $goods_price_v['purchase'];
                        if($goods_price_v['is_default']!=1){
                            $unit[$unit_k]['basic_nmber'] = $goods_price_v['advance_rate'];
                        }else{
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($row['auxiliary']==1 && !empty($attr)){
                    $purchase = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->value('purchase');
                }else{
                    $purchase = $row['purchase'];
                }
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $purchase;
                }
            }
        }
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($suppliers) && $suppliers!==''){
                $tax = $suppliers;
            }else{
                $tax = $set['value'];
            }
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1){
                $unit[$unit_k]['tax'] = 1;
                $unit[$unit_k]['tax_rate'] = $tax;
            }else{
                $unit[$unit_k]['tax'] = 0;
                $unit[$unit_k]['tax_rate'] = 0;
            }
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes==1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']/(100+$tax)*100),4);
                $unit[$unit_k]['tax_included'] = $unit_v['purchase'];
            }elseif(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes!=1){
                $unit[$unit_k]['purchase'] = $unit_v['purchase'];
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*(100+$tax)/100),4);
            }else{
                $unit[$unit_k]['purchase'] = $unit_v['purchase'];
                $unit[$unit_k]['tax_included'] = $unit_v['purchase'];
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
                    $unit_v['tax_money'] = round(($unit_v['tax_included']-$unit_v['purchase']),4);;
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode(['unit'=>$data,'attr'=>$goods_attr,'goods'=>$row]);
    }
    public function formatInfo(){
        $data = input();
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
            'tax_money'=>$price_decimal,
            'money_tax_price'=>$price_decimal,
        ];
        foreach ($data as $k=>$v){
            if(array_key_exists($k,$format)){
                $data[$k] = (float)$data[$k];
            }
        }

        $data['basic_nmber'] = $data['number']*$data['basic_nmber'];
        $all_money = $data['number']*$data['unit_price'];
        if(isset($data['discount'])){
            $data['dis_money'] = $all_money*$data['discount']/100;
            $data['all_money'] = $all_money-$data['dis_money'];
        }else{
            $data['all_money'] = $all_money;
        }
        $data['money_tax_price'] = $data['number']*$data['tax_included'];

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
        if($data['tax_rate']===''){
            $suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$data['supplyer_code']])->value('vat_rate');
            if(isset($suppliers) && $suppliers!==''){
                $data['tax_rate'] = $suppliers;
            }else{
                $data['tax_rate'] = $setList['taxes']['value'];
            }
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

        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');//价格含税
        $now_field = $data['now_field'];
        switch ($now_field){
            case 'goods_unit':
                //商品单位
                if($taxes){
                    $data['tax_included'] = isset($goods_price['purchase'])?$goods_price['purchase']:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price['purchase'])?$goods_price['purchase']:$data['unit_price'];//单价
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
                    $data['tax_included'] = isset($goods_price['purchase'])?$goods_price['purchase']:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price['purchase'])?$goods_price['purchase']:$data['unit_price'];//单价
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
                $data['dis_money'] = 0;//$data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
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

    // 打印采购单详情
    public function printpage($ids = null)
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

        // 购货单详情
        $purchase_info = Db::name('kuerp_goods_purchase_info')->where(['purchase_code' => $row['purchase_code']])->select();
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

    /**
     * 采购汇总 — 勾选多条采购单，按商品+属性+仓库汇总
     */
    public function summary($ids = null)
    {
        $ids = $ids ? $ids : $this->request->get("ids");
        if (empty($ids)) {
            return '请选择要汇总的采购单';
        }
        $idArr = explode(',', $ids);
        $idArr = array_map('intval', $idArr);
        $idArr = array_filter($idArr);
        if (empty($idArr)) {
            return '请选择要汇总的采购单';
        }

        $price_decimal = Db::name('kuerp_sets')->where(['name' => 'price_decimal'])->value('value');
        $number_decimal = Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
        try {
            Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {
        }

        // 通过主表id查找对应的purchase_code
        $purchaseCodes = Db::name('kuerp_goods_purchase')
            ->where('id', 'in', $idArr)
            ->column('purchase_code');
        if (empty($purchaseCodes)) {
            return '未找到对应的采购单数据';
        }

        $list = Db::name('kuerp_goods_purchase_info')->alias('i')
            ->join('kuerp_goods g', 'g.goods_code=i.goods_code')
            ->join('kuerp_goods_type gt', 'gt.id=g.goods_type_id', 'LEFT')
            ->join('kuerp_stores s', 's.id=i.stores_id', 'LEFT')
            ->join('kuerp_unit u', 'u.id=i.goods_unit', 'LEFT')
            ->where('i.purchase_code', 'in', $purchaseCodes)
            ->group('i.goods_code,i.goods_attr,i.stores_id')
            ->field('SUM(i.number) as i_number, SUM(i.basic_nmber) as basic_nmber, SUM(i.all_money) as all_money,
                SUM(i.tax_money) as tax_money, SUM(i.money_tax_price) as money_tax_price,
                SUM(i.purchase_money) as purchase_money,
                ANY_VALUE(i.goods_code) as goods_code, ANY_VALUE(i.goods_attr) as goods_attr,
                ANY_VALUE(i.stores_id) as stores_id, ANY_VALUE(i.goods_unit) as goods_unit,
                ANY_VALUE(i.default_unit) as default_unit,
                ANY_VALUE(g.goods_name) as goods_name,
                ANY_VALUE(g.goods_specification_id) as goods_specification_id,
                ANY_VALUE(gt.supplier_type_name) as supplier_type_name,
                ANY_VALUE(s.name) as s_name, ANY_VALUE(u.name) as u_name')
            ->select();

        // 处理单位换算 & 属性名称
        // 预加载辅助属性
        $auxiliary = Db::name('kuerp_auxiliary')->where(['status' => 1])->column('name', 'id');

        foreach ($list as $lk => $lv) {
            // 解析属性名称
            $attrName = '';
            if (!empty($lv['goods_attr'])) {
                $attrId = Db::name('kuerp_goods_attr')->where(['id' => $lv['goods_attr']])->value('attribute_id');
                if ($attrId) {
                    $attrIds = Db::name('kuerp_aux_combination')->where(['id' => $attrId])->value('attr_ids');
                    if ($attrIds) {
                        $parts = explode('&', $attrIds);
                        $names = [];
                        foreach ($parts as $pid) {
                            if (isset($auxiliary[$pid])) {
                                $names[] = $auxiliary[$pid];
                            }
                        }
                        $attrName = implode('/', $names);
                    }
                }
            }
            $list[$lk]['attr_name'] = $attrName;

            if (!empty($lv['default_unit']) && !empty($lv['goods_unit'])) {
                $list[$lk]['unit_conversion'] = Db::name('kuerp_unit_conversion')
                    ->where(['main_unit' => $lv['default_unit'], 'vice_unit' => $lv['goods_unit']])
                    ->value('advance_rate');
            } else {
                $list[$lk]['unit_conversion'] = '';
            }
            // 副单位数显示
            if (!empty($list[$lk]['unit_conversion'])) {
                $unitConv = $list[$lk]['unit_conversion'];
                $basicNmber = $list[$lk]['basic_nmber'];
                if ($basicNmber % $unitConv > 0) {
                    $list[$lk]['basic_nmber_display'] = floor($basicNmber / $unitConv) . $list[$lk]['u_name'] . ($basicNmber % $unitConv) . $list[$lk]['u_name'];
                } else {
                    $list[$lk]['basic_nmber_display'] = floor($basicNmber / $unitConv) . $list[$lk]['u_name'];
                }
            } else {
                $list[$lk]['basic_nmber_display'] = '';
            }
            // 格式化
            $list[$lk]['i_number']       = round($lv['i_number'], $number_decimal);
            $list[$lk]['all_money']      = round($lv['all_money'], $price_decimal);
            $list[$lk]['purchase_money'] = round($lv['purchase_money'], $price_decimal);
            $list[$lk]['tax_money']      = round($lv['tax_money'], $price_decimal);
            $list[$lk]['money_tax_price'] = round($lv['money_tax_price'], $price_decimal);
            $list[$lk]['unit_price']     = $lv['i_number'] > 0 ? round($lv['all_money'] / $lv['i_number'], $price_decimal) : 0;
        }

        // 合计
        $totalNumber = 0; $totalAllMoney = 0; $totalPurchaseMoney = 0;
        $totalTaxMoney = 0; $totalMoneyTaxPrice = 0;
        foreach ($list as $lv) {
            $totalNumber += $lv['i_number'];
            $totalAllMoney += $lv['all_money'];
            $totalPurchaseMoney += $lv['purchase_money'];
            $totalTaxMoney += $lv['tax_money'];
            $totalMoneyTaxPrice += $lv['money_tax_price'];
        }
        $totals = [
            'i_number'        => round($totalNumber, $number_decimal),
            'unit_price'      => $totalNumber > 0 ? round($totalAllMoney / $totalNumber, $price_decimal) : 0,
            'all_money'       => round($totalAllMoney, $price_decimal),
            'purchase_money'  => round($totalPurchaseMoney, $price_decimal),
            'tax_money'       => round($totalTaxMoney, $price_decimal),
            'money_tax_price' => round($totalMoneyTaxPrice, $price_decimal),
        ];

        $setData = \addons\kuerp\library\Goods::getSetData();
        $set = $setData['setList'];
        $taxes = isset($set['taxes']['is_checked']) && $set['taxes']['is_checked'] == 1 ? 1 : 0;

        $this->assign('list', $list);
        $this->assign('totals', $totals);
        $this->assign('taxes', $taxes);
        $this->assign('price_decimal', $price_decimal);
        $this->assign('number_decimal', $number_decimal);

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
            $this->success(sizeof($list).'条数据二次审核成功');
        }else{
            $this->error('二次审核失败');
        }
    }
}
