<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 核销单
 *
 * @icon fa fa-circle-o
 */
class Writeoff extends Backend
{
    protected $noNeedRight = ['choselist','choseadvancelist','chosereceivable','chosecopewith'];
    /**
     * Writeoff模型对象
     * @var \app\admin\model\kuerp\Writeoff
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Writeoff;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("suppliers", $this->model->getSuppliersList());
        $this->view->assign("accountsList", $this->model->getAccountsList());
        $this->view->assign("saleList", $this->model->getSaleList());
        $this->view->assign("settlementtype", $this->model->getSettlementtype());
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

            $list = $this->model
                ->with(['admin'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('admin')->visible(['nickname']);
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                $list[$list_k]['purchase_times'] = date('Y-m-d',$list_v['purchase_time']);

                if ($list_v['business_type']==4){
                    $list[$list_k]['customers_id'] = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_out_id']])->value('customer_name');
                }else{
                    $list[$list_k]['customers_id'] = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->value('customer_name');
                }
                if ($list_v['business_type']==5){
                    $list[$list_k]['suppliers_id'] = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_out_id']])->value('supplyer_name');
                }else{
                    $list[$list_k]['suppliers_id'] = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_id']])->value('supplyer_name');
                }

                $list[$list_k]['preparation_id'] = Db::name('admin')->where(['id'=>$list_v['preparation_id']])->value('nickname');
                $list[$list_k]['examine_id'] = Db::name('admin')->where(['id'=>$list_v['examine_id']])->value('nickname');
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($ids = null)
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

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                $business_type = $params['business_type'];
                $data = input();
                $account = $data['account'];
                $sale = $data['sale'];
                $info = [];
                $log_arr = [];
                $log_add = [];
                $order_id = [];
                foreach ($account['purchase_numbers'] as $account_k=>$account_v){
                    if(!empty($account_v) && !empty($account['purchase_type'][$account_k])){
                        $order = Db::name('kuerp_writeoff_source')->where(['id'=>$account['purchase_type'][$account_k]])->find();
                        if(empty($order)){
                            $this->error('源单信息查询错误');
                        }
                        if(in_array($account_v,$order_id)){
                            $this->error('不可多次选择相同的源单');
                        }else{
                            $order_id[] = $account_v;
                        }
                        $thisrow = \addons\kuerp\library\Goods::setDataInfo([
                            'source_id'        => $order['id'],
                            'purchase_code'    => $params['purchase_code'],
                            'purchase_time'    => strtotime($params['purchase_time']),
                            'type'             => $order['type'],
                            'purchase_number'  => $order['purchase_code'],
                            'business_type'    => $business_type,
                            'business_name'    => isset($account['business_type'][$account_k])?$account['business_type'][$account_k]:null,
                            'purchase_type'    => $order['purchase_type'],
                            'times'            => $order['purchase_time'],
                            'money'            => $order['price'],
                            'write_off'        => isset($account['write_off'][$account_k])?(float)$account['write_off'][$account_k]:0,
                            'write_off_money'  => isset($account['write_off_money'][$account_k])?(float)$account['write_off_money'][$account_k]:0,
                            'write_off_price'  => isset($account['write_off_price'][$account_k])?(float)$account['write_off_price'][$account_k]:0,
                            'top'              => 1,
                        ]);
                        $info[] = $thisrow;

                        $where = [];
                        $where['source_id'] = $order['id'];
                        $log = Db::name('kuerp_writeoff_log')->where($where)->find();
                        if($log){
                            $status = true;
                            foreach ($log_arr as $log_arr_k=>$log_arr_v){
                                if($log_arr_v['id']==$log['id']){
                                    $status = false;
                                    $log_arr[$log_arr_k]['money'] += $thisrow['write_off_price'];
                                    $log_arr[$log_arr_k]['write_off'] -= $thisrow['write_off_price'];
                                }
                            }
                            if($status){
                                $log_arr[] = [
                                    'id' => $log['id'],
                                    'write_off' => $log['write_off']-$thisrow['write_off_price'],
                                    'money' => $log['money']+$thisrow['write_off_price'],
                                ];
                            }
                        }else{
                            $log_add[] = [
                                'source_id' => $order['id'],
                                'write_off' => $thisrow['write_off_money']-$thisrow['write_off_price'],
                                'money' => $thisrow['write_off_price'],
                            ];
                        }
                    }
                }

                $account_money = 0;
                foreach ($info as $info_k=>$info_v){
                    $account_money += $info_v['write_off_price'];
                }

                $order_id = [];
                $sale_money = 0;
                if($business_type<=3){
                    foreach ($sale['purchase_numbers'] as $account_k=>$account_v){
                        if(!empty($account_v) && !empty($sale['purchase_type'][$account_k])){
                            $sale_money += $sale['write_off_price'][$account_k];
                            $order = Db::name('kuerp_writeoff_source')->where(['id'=>$sale['purchase_type'][$account_k]])->find();
                            if(empty($order)){
                                $this->error('源单信息查询错误');
                            }
                            if(in_array($account_v,$order_id)){
                                $this->error('不可多次选择相同的源单');
                            }else{
                                $order_id[] = $account_v;
                            }
                            $thisrow = \addons\kuerp\library\Goods::setDataInfo([
                                'source_id'        => $order['id'],
                                'purchase_code'    => $params['purchase_code'],
                                'purchase_time'    => strtotime($params['purchase_time']),
                                'type'             => $order['type'],
                                'purchase_number'  => $order['purchase_code'],
                                'business_type'    => $business_type,
                                'business_name'    => isset($sale['business_type'][$account_k])?$sale['business_type'][$account_k]:null,
                                'purchase_type'    => $order['purchase_type'],
                                'times'            => $order['purchase_time'],
                                'money'            => $order['price'],
                                'write_off'        => isset($sale['write_off'][$account_k])?(float)$sale['write_off'][$account_k]:0,
                                'write_off_money'  => isset($sale['write_off_money'][$account_k])?(float)$sale['write_off_money'][$account_k]:0,
                                'write_off_price'  => isset($sale['write_off_price'][$account_k])?(float)$sale['write_off_price'][$account_k]:0,
                                'top'              => 2,
                            ]);
                            $info[] = $thisrow;

                            $where = [];
                            $where['source_id'] = $order['id'];
                            $log = Db::name('kuerp_writeoff_log')->where($where)->find();
                            if($log){
                                $status = true;
                                foreach ($log_arr as $log_arr_k=>$log_arr_v){
                                    if($log_arr_v['id']==$log['id']){
                                        $status = false;
                                        $log_arr[$log_arr_k]['money'] += $thisrow['write_off_price'];
                                        $log_arr[$log_arr_k]['write_off'] -= $thisrow['write_off_price'];
                                    }
                                }
                                if($status){
                                    $log_arr[] = [
                                        'id' => $log['id'],
                                        'write_off' => $log['write_off']-$thisrow['write_off_price'],
                                        'money' => $log['money']+$thisrow['write_off_price'],
                                    ];
                                }
                            }else{
                                $log_add[] = [
                                    'source_id' => $order['id'],
                                    'write_off' => $thisrow['write_off_money']-$thisrow['write_off_price'],
                                    'money' => $thisrow['write_off_price'],
                                ];
                            }
                        }
                    }
                }

                $row = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'business_type'     =>  $params['business_type'],
                    'customers_id'      =>  isset($params['customers_id'])?(float)$params['customers_id']:null,
                    'customers_out_id'  =>  isset($params['customers_out_id'])?(float)$params['customers_out_id']:null,
                    'customers_enter_id'=>  isset($params['customers_enter_id'])?(float)$params['customers_enter_id']:null,
                    'suppliers_id'      =>  isset($params['suppliers_id'])?(float)$params['suppliers_id']:null,
                    'suppliers_out_id'  =>  isset($params['suppliers_out_id'])?(float)$params['suppliers_out_id']:null,
                    'suppliers_enter_id'=>  isset($params['suppliers_enter_id'])?(float)$params['suppliers_enter_id']:null,
                    'write_off'         =>  $account_money,
                    'remarks'           =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);

                if($account_money!=$sale_money && $business_type<=3){
                    $this->error('核销金额错误');
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

                    foreach ($info as $info_k=>$info_v){
                        $info[$info_k]['collection_id'] = $result;
                    }
                    Db::name('kuerp_writeoff_info')->insertAll($info);
                    if(!empty($log_arr)){
                        (new \app\admin\model\kuerp\WriteoffLog())->saveAll($log_arr);
                    }
                    if(!empty($log_add)){
                        (new \app\admin\model\kuerp\WriteoffLog())->insertAll($log_add);
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
        $code = \addons\kuerp\library\Goods::getCodes('',23);
        $this->view->assign("code", $code);
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

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                //详情
                $info_list = Db::name('kuerp_writeoff_info')->where(['collection_id'=>$ids])->select();

                $business_type = $params['business_type'];
                $data = input();
                $account = $data['account'];
                $sale = $data['sale'];
                $info = [];
                $log_arr = [];
                $log_add = [];
                $order_id = [];
                foreach ($account['purchase_numbers'] as $account_k=>$account_v){
                    if(!empty($account_v) && !empty($account['purchase_type'][$account_k])){
                        $order = Db::name('kuerp_writeoff_source')->where(['id'=>$account['purchase_type'][$account_k]])->find();
                        if(empty($order)){
                            $this->error('源单信息查询错误');
                        }
                        if(in_array($account_v,$order_id)){
                            $this->error('不可多次选择相同的源单');
                        }else{
                            $order_id[] = $account_v;
                        }
                        $thisrow = \addons\kuerp\library\Goods::setDataInfo([
                            'source_id'        => $order['id'],
                            'purchase_code'    => $params['purchase_code'],
                            'purchase_time'    => strtotime($params['purchase_time']),
                            'type'             => $order['type'],
                            'purchase_number'  => $order['purchase_code'],
                            'business_type'    => $business_type,
                            'business_name'    => isset($account['business_type'][$account_k])?$account['business_type'][$account_k]:null,
                            'purchase_type'    => $order['purchase_type'],
                            'times'            => $order['purchase_time'],
                            'money'            => $order['price'],
                            'write_off'        => isset($account['write_off'][$account_k])?(float)$account['write_off'][$account_k]:0,
                            'write_off_money'  => isset($account['write_off_money'][$account_k])?(float)$account['write_off_money'][$account_k]:0,
                            'write_off_price'  => isset($account['write_off_price'][$account_k])?(float)$account['write_off_price'][$account_k]:0,
                            'top'              => 1,
                        ]);
                        $info[] = $thisrow;

                        $where = [];
                        $where['source_id'] = $order['id'];
                        $log = Db::name('kuerp_writeoff_log')->where($where)->find();
                        if($log){
                            $status = true;
                            foreach ($log_arr as $log_arr_k=>$log_arr_v){
                                if($log_arr_v['id']==$log['id']){
                                    $status = false;
                                    $log_arr[$log_arr_k]['money'] += $thisrow['write_off_price'];
                                    $log_arr[$log_arr_k]['write_off'] -= $thisrow['write_off_price'];
                                }
                            }
                            if($status){
                                $log_arr[] = [
                                    'id' => $log['id'],
                                    'write_off' => $log['write_off']-$thisrow['write_off_price'],
                                    'money' => $log['money']+$thisrow['write_off_price'],
                                ];
                            }
                        }else{
                            $log_add[] = [
                                'source_id' => $order['id'],
                                'write_off' => $thisrow['write_off_money']-$thisrow['write_off_price'],
                                'money' => $thisrow['write_off_price'],
                            ];
                        }
                    }
                }

                $account_money = 0;
                foreach ($info as $info_k=>$info_v){
                    $account_money += $info_v['write_off_price'];
                }

                $order_id = [];
                $sale_money = 0;
                if($business_type<=3){
                    foreach ($sale['purchase_numbers'] as $account_k=>$account_v){
                        if(!empty($account_v) && !empty($sale['purchase_type'][$account_k])){
                            $sale_money += $sale['write_off_price'][$account_k];
                            $order = Db::name('kuerp_writeoff_source')->where(['id'=>$sale['purchase_type'][$account_k]])->find();
                            if(empty($order)){
                                $this->error('源单信息查询错误');
                            }
                            if(in_array($account_v,$order_id)){
                                $this->error('不可多次选择相同的源单');
                            }else{
                                $order_id[] = $account_v;
                            }
                            $thisrow = \addons\kuerp\library\Goods::setDataInfo([
                                'source_id'        => $order['id'],
                                'purchase_code'    => $params['purchase_code'],
                                'purchase_time'    => strtotime($params['purchase_time']),
                                'type'             => $order['type'],
                                'purchase_number'  => $order['purchase_code'],
                                'business_type'    => $business_type,
                                'business_name'    => isset($sale['business_type'][$account_k])?$sale['business_type'][$account_k]:null,
                                'purchase_type'    => $order['purchase_type'],
                                'times'            => $order['purchase_time'],
                                'money'            => $order['price'],
                                'write_off'        => isset($sale['write_off'][$account_k])?(float)$sale['write_off'][$account_k]:0,
                                'write_off_money'  => isset($sale['write_off_money'][$account_k])?(float)$sale['write_off_money'][$account_k]:0,
                                'write_off_price'  => isset($sale['write_off_price'][$account_k])?(float)$sale['write_off_price'][$account_k]:0,
                                'top'              => 2,
                            ]);
                            $info[] = $thisrow;

                            $where = [];
                            $where['source_id'] = $order['id'];
                            $log = Db::name('kuerp_writeoff_log')->where($where)->find();
                            if($log){
                                $status = true;
                                foreach ($log_arr as $log_arr_k=>$log_arr_v){
                                    if($log_arr_v['id']==$log['id']){
                                        $status = false;
                                        $log_arr[$log_arr_k]['money'] += $thisrow['write_off_price'];
                                        $log_arr[$log_arr_k]['write_off'] -= $thisrow['write_off_price'];
                                    }
                                }
                                if($status){
                                    $log_arr[] = [
                                        'id' => $log['id'],
                                        'write_off' => $log['write_off']-$thisrow['write_off_price'],
                                        'money' => $log['money']+$thisrow['write_off_price'],
                                    ];
                                }
                            }else{
                                $log_add[] = [
                                    'source_id' => $order['id'],
                                    'write_off' => $thisrow['write_off_money']-$thisrow['write_off_price'],
                                    'money' => $thisrow['write_off_price'],
                                ];
                            }
                        }
                    }
                }

                $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'business_type'     =>  $params['business_type'],
                    'customers_id'      =>  isset($params['customers_id'])?(float)$params['customers_id']:null,
                    'customers_out_id'  =>  isset($params['customers_out_id'])?(float)$params['customers_out_id']:null,
                    'customers_enter_id'=>  isset($params['customers_enter_id'])?(float)$params['customers_enter_id']:null,
                    'suppliers_id'      =>  isset($params['suppliers_id'])?(float)$params['suppliers_id']:null,
                    'suppliers_out_id'  =>  isset($params['suppliers_out_id'])?(float)$params['suppliers_out_id']:null,
                    'suppliers_enter_id'=>  isset($params['suppliers_enter_id'])?(float)$params['suppliers_enter_id']:null,
                    'write_off'         =>  $account_money,
                    'remarks'           =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);


                if($account_money!=$sale_money && $business_type<=3){
                    $this->error('核销金额错误');
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                $writeoff_log = $log_arr;
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$info_list_v['source_id']])->find();
                    if($log){
                        $addstatus = true;
                        foreach ($writeoff_log as $writeoff_log_k=>$writeoff_log_v){
                            if($writeoff_log_v['id']==$log['id']){
                                $addstatus = false;
                                $writeoff_log[$writeoff_log_k]['write_off'] = $writeoff_log_v['write_off']+$info_list_v['write_off_price'];
                                $writeoff_log[$writeoff_log_k]['money'] = $writeoff_log_v['money']-$info_list_v['write_off_price'];
                            }
                        }
                        if($addstatus){
                            $writeoff_log[] = [
                                'id' => $log['id'],
                                'write_off'=>($log['write_off']+$info_list_v['write_off_price']),
                                'money'=>($log['money']-$info_list_v['write_off_price'])
                            ];
                        }
                    }
                }

                $result = false;
                Db::startTrans();
                try {if($examine!=1 && $row['examine_status']==1){
                    $this->examine($ids,2);
                }
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    if(!empty($writeoff_log)){
                        (new \app\admin\model\kuerp\WriteoffLog())->saveAll($writeoff_log);
                    }
                    Db::name('kuerp_writeoff_info')->where(['collection_id'=>$ids])->delete();
                    foreach ($info as $info_k=>$info_v){
                        $info[$info_k]['collection_id'] = $ids;
                    }
                    Db::name('kuerp_writeoff_info')->insertAll($info);
                    if(!empty($log_add)){
                        (new \app\admin\model\kuerp\WriteoffLog())->insertAll($log_add);
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

        $info = Db::name('kuerp_writeoff_info')->where(['collection_id'=>$row['id']])->select();
        $this->view->assign("info", $info);

        $kuerp_customers = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->field('id,customer_name')->find();
        $this->assignconfig("kuerp_customers", $kuerp_customers);
        $kuerp_customers_out = Db::name('kuerp_customers')->where(['id'=>$row['customers_out_id']])->field('id,customer_name')->find();
        $this->assignconfig("kuerp_customers_out", $kuerp_customers_out);
        $kuerp_customers_enter = Db::name('kuerp_customers')->where(['id'=>$row['customers_enter_id']])->field('id,customer_name')->find();
        $this->assignconfig("kuerp_customers_enter", $kuerp_customers_enter);

        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['id'=>$row['suppliers_id']])->field('id,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);
        $kuerp_suppliers_out = Db::name('kuerp_suppliers')->where(['id'=>$row['suppliers_out_id']])->field('id,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers_out", $kuerp_suppliers_out);
        $kuerp_suppliers_enter = Db::name('kuerp_suppliers')->where(['id'=>$row['suppliers_enter_id']])->field('id,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers_enter", $kuerp_suppliers_enter);

        //审核禁止输入
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine_set==1 && $row['examine_status']==1){
            $this->assign('exclass','hiddens');
        }
        $this->assignconfig("examine_status", $row['examine_status']);

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
            $del_id = [];
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
                        $del_id[] = $list_v['id'];
                    } catch (Exception $e) {
                    }
                }else{
                    $del_id[] = $list_v['id'];
                }
            }
            $list = $this->model->where(['id'=>['in',$del_id]])->select();
            //详情
            $info_list = Db::name('kuerp_writeoff_info')->where(['collection_id'=>['in',$del_id]])->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$info_list_v['source_id']])->find();
                    if($log){
                        Db::name('kuerp_writeoff_log')->where(['id'=>$log['id']])->update([
                            'write_off'=>($log['write_off']+$info_list_v['write_off_price']),
                            'money'=>($log['money']-$info_list_v['write_off_price'])
                        ]);
                    }
                }
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$info_list_v['source_id']])->find();
                    if($log && $log['money']==0){
                        Db::name('kuerp_writeoff_log')->where(['id'=>$log['id']])->delete();
                    }
                }
                Db::name('kuerp_writeoff_info')->where(['collection_id'=>['in',$del_id]])->delete();
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
        $admin = Session::get('admin');
        if($examine==1){
            $list = Db::name('kuerp_writeoff')->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
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
            $success_count = 0;
            foreach ($list as $list_k=>$list_v){

                $info = Db::name('kuerp_writeoff_info')->where(['collection_id'=>$list_v['id']])->select();

                Db::startTrans();
                try {
                    foreach ($info as $info_k=>$info_v){
                        if($info_v['purchase_type']==3 && $info_v['type']==1){
                            //销售
                            $order = Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            //应收总额统一口径：优惠后价税合计 + 客户承担费用
                            //应收总额统一口径：优惠后价税合计 + 客户承担费用 + 对账差异
                            $more_money = ($order['real_money']+$order['customers_money']+(float)$order['diff_money'])-$info_v['write_off_price']-$order['pay_money'];
                            $status = $more_money<=0?1:2;
                            $pay_money = $order['pay_money']+$info_v['write_off_price'];
                            Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_v['purchase_number']])
                                ->update(['pay_money'=>$pay_money,'pay_status'=>$status]);
                        }else if($info_v['purchase_type']==3 && $info_v['type']==2){
                            //销售退货
                            $order = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            //退货单：退货金额越大冲减越多，差异按反向计入
                            $more_money = ($order['real_money']+$order['customers_money']-(float)$order['diff_money'])+$info_v['write_off_price']-$order['retreat_money'];
                            $status = $more_money==0?1:2;
                            $pay_money = $order['retreat_money']+(-$info_v['write_off_price']);
                            Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_v['purchase_number']])
                                ->update(['retreat_money'=>$pay_money,'retreat_status'=>$status]);
                        }else if($info_v['purchase_type']==4 && $info_v['type']==1){
                            //购货
                            $order = Db::name('kuerp_goods_purchase')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            $more_money = $order['all_money']+$order['tax_money']-$info_v['write_off_price']-$order['pay_money'];
                            $status = $more_money<=0?1:2;
                            $pay_money = $order['pay_money']+$info_v['write_off_price'];
                            Db::name('kuerp_goods_purchase')->where(['purchase_code'=>$info_v['purchase_number']])
                                ->update(['pay_money'=>$pay_money,'pay_status'=>$status]);
                        }else if($info_v['purchase_type']==4 && $info_v['type']==2){
                            //购货退货
                            $order = Db::name('kuerp_goods_exchange')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            $more_money = $order['all_money']+$order['tax_money']+$info_v['write_off_price']-$order['retreat_money'];;
                            $status = $more_money==0?1:2;
                            $pay_money = $order['retreat_money']+(-$info_v['write_off_price']);
                            Db::name('kuerp_goods_exchange')->where(['purchase_code'=>$info_v['purchase_number']])
                                ->update(['retreat_money'=>$pay_money,'retreat_status'=>$status]);
                        }

                        $writeoff_source = Db::name('kuerp_writeoff_source')->where(['id'=>$info_v['source_id']])->find();
                        if ($info_v['top']==1){
                            if(($info_v['business_type']==1 && $info_v['type']==3)){
                                Db::name('kuerp_sales_payment_log')->insert([
                                    'customers_id'      => $writeoff_source['customers_id'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }else if($info_v['business_type']==2 && $info_v['type']==3){
                                Db::name('kuerp_payment_log')->insert([
                                    'suppliers_code'    => $writeoff_source['suppliers_code'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }else if($info_v['business_type']==3 && $info_v['type']<4){
                                Db::name('kuerp_sales_payment_log')->insert([
                                    'customers_id'      => $writeoff_source['customers_id'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }else if($info_v['business_type']==4 && $info_v['type']<4){
                                Db::name('kuerp_sales_payment_log')->insert([
                                    'customers_id'      => $list_v['customers_out_id'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => -$info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                                Db::name('kuerp_sales_payment_log')->insert([
                                    'customers_id'      => $list_v['customers_enter_id'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }else if($info_v['business_type']==5 && $info_v['type']<4){
                                $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_out_id']])->value('supplyer_code');
                                Db::name('kuerp_payment_log')->insert([
                                    'suppliers_code'    => $suppliers,
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                                $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_enter_id']])->value('supplyer_code');
                                Db::name('kuerp_payment_log')->insert([
                                    'suppliers_code'    => $suppliers,
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => -$info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }
                        }else if ($info_v['top']==2){
                            if(($info_v['business_type']==1 && ($info_v['purchase_type']==3 || $info_v['purchase_type']==5))){
                                Db::name('kuerp_sales_payment_log')->insert([
                                    'customers_id'      => $writeoff_source['customers_id'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }else if($info_v['business_type']==2 && ($info_v['purchase_type']==4 || $info_v['purchase_type']==5)){
                                Db::name('kuerp_payment_log')->insert([
                                    'suppliers_code'    => $writeoff_source['suppliers_code'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }else if($info_v['business_type']==3 && ($info_v['purchase_type']==4 || $info_v['purchase_type']==5)){
                                Db::name('kuerp_payment_log')->insert([
                                    'suppliers_code'    => $writeoff_source['suppliers_code'],
                                    'bill_time'         => $info_v['purchase_time'],
                                    'bill_code'         => $info_v['purchase_number'],
                                    'bill_codes'        => $info_v['purchase_code'],
                                    'business_type'     => '7',
                                    'pay_money'         => $info_v['write_off_price'],
                                    'createtime'        => time(),
                                ]);
                            }
                        }

                        if(($info_v['purchase_type']==3 || $info_v['purchase_type']==4) && $info_v['type']==1){
                            Db::name('kuerp_writeoff_info')->where(['id'=>$info_v['id']])->update([
                                'old_money'=>$order['pay_money'],
                                'old_status'=>$order['pay_status'],
                            ]);
                        }else if(($info_v['purchase_type']==3 || $info_v['purchase_type']==4) && $info_v['type']==2){
                            Db::name('kuerp_writeoff_info')->where(['id'=>$info_v['id']])->update([
                                'old_money'=>$order['retreat_money'],
                                'old_status'=>$order['retreat_status'],
                            ]);
                        }
                    }

                    switch ($list_v['business_type']){
                        case 1:
                            //预收冲应收
                            Db::name('kuerp_accounts_receivable')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'customers_id'      => $list_v['customers_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '1',
                                'receivable'        => -$list_v['write_off'],
                                'advance'           => -$list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => -$list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                            break;
                        case 2:
                            //预付冲应付
                            Db::name('kuerp_accounts_copewith')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'suppliers_id'      => $list_v['suppliers_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '2',
                                'receivable'        => -$list_v['write_off'],
                                'advance'           => -$list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => -$list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                            break;
                        case 3:
                            //应收冲应付
                            Db::name('kuerp_accounts_receivable')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'customers_id'      => $list_v['customers_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '3',
                                'receivable'        => -$list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => -$list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));
                            Db::name('kuerp_accounts_copewith')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'suppliers_id'      => $list_v['suppliers_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '3',
                                'receivable'        => -$list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => -$list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));
                            $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->find();
                            $gave_money = $suppliers['need_receive_account'] - $list_v['write_off'];
                            Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->update(['need_receive_account'=>$gave_money]);

                            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_id']])->find();
                            $gave_money = $suppliers['need_give_account'] - $list_v['write_off'];
                            Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_id']])->update(['need_give_account'=>$gave_money]);

                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                            break;
                        case 4:
                            //应收转应收
                            Db::name('kuerp_accounts_receivable')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'customers_id'      => $list_v['customers_out_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '4',
                                'receivable'        => -$list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => -$list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));

                            $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_out_id']])->find();
                            $gave_money = $suppliers['need_receive_account'] - $list_v['write_off'];
                            Db::name('kuerp_customers')->where(['id'=>$list_v['customers_out_id']])->update(['need_receive_account'=>$gave_money]);

                            Db::name('kuerp_accounts_receivable')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'customers_id'      => $list_v['customers_enter_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '4',
                                'receivable'        => $list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => $list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));

                            $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_enter_id']])->find();
                            $gave_money = $suppliers['need_receive_account'] + $list_v['write_off'];
                            Db::name('kuerp_customers')->where(['id'=>$list_v['customers_enter_id']])->update(['need_receive_account'=>$gave_money]);

                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 3,
                                'type' => 4,
                                'customers_id' => $list_v['customers_enter_id'],
                                'purchase_code' => $list_v['purchase_code'],
                                'purchase_time' => $list_v['purchase_time'],
                                'price' => $list_v['write_off'],
                                'money' => 0,
                                'write_off' => $list_v['write_off'],
                            ]);
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                            break;
                        case 5:
                            //应付转应付
                            Db::name('kuerp_accounts_copewith')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'suppliers_id'      => $list_v['suppliers_out_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '5',
                                'receivable'        => -$list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => -$list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));

                            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_out_id']])->find();
                            $gave_money = $suppliers['need_give_account'] - $list_v['write_off'];
                            Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_out_id']])->update(['need_give_account'=>$gave_money]);

                            Db::name('kuerp_accounts_copewith')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'suppliers_id'      => $list_v['suppliers_enter_id'],
                                'purchase_time'     => $list_v['purchase_time'],
                                'purchase_code'     => $list_v['purchase_code'],
                                'business_type'     => '5',
                                'receivable'        => $list_v['write_off'],
                                'remark'            => $list_v['remarks'],
                                'receivable_money'  => $list_v['write_off'],
                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]));

                            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_enter_id']])->find();
                            $gave_money = $suppliers['need_give_account'] + $list_v['write_off'];
                            Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_enter_id']])->update(['need_give_account'=>$gave_money]);

                            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_enter_id']])->value('supplyer_code');
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 4,
                                'type' => 4,
                                'suppliers_code' => $suppliers,
                                'purchase_code' => $list_v['purchase_code'],
                                'purchase_time' => $list_v['purchase_time'],
                                'price' => $list_v['write_off'],
                                'money' => 0,
                                'write_off' => $list_v['write_off'],
                            ]);
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                            break;
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if(isset($result) && $result !== false){
                    $success_count += 1;
                    // 核销后同步所属销货对账单的收款状态（到期提醒、差异校验依赖该字段）
                    try {
                        $reconCodes = Db::name('kuerp_writeoff_info')->where(['collection_id'=>$list_v['id']])->column('purchase_number');
                        \app\admin\model\kuerp\SalesRecon::recalcPayByDocCodes($reconCodes);
                    } catch (\Exception $e) {
                    }
                }
            }
            if($success_count>0){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success($success_count.'条数据审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('审核失败');
            }
        }else{
            $list = Db::name('kuerp_writeoff')->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
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
                $this->error('请选择需要反审核的数据');
            }
            $success_count = 0;
            foreach ($list as $list_k=>$list_v){

                $info = Db::name('kuerp_writeoff_info')->where(['collection_id'=>$list_v['id']])->select();

                Db::startTrans();
                try {
                    foreach ($info as $info_k=>$info_v){
                        if($info_v['purchase_type']==3 && $info_v['type']==1){
                            //销售
                            $order = Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_v['purchase_number']])
                                    ->update(['pay_money'=>$info_v['old_money'],'pay_status'=>$info_v['old_status']]);
                            }
                        }else if($info_v['purchase_type']==3 && $info_v['type']==2){
                            //销售退货
                            $order = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_v['purchase_number']])
                                    ->update(['retreat_money'=>$info_v['old_money'],'retreat_status'=>$info_v['old_status']]);
                            }
                        }else if($info_v['purchase_type']==4 && $info_v['type']==1){
                            //购货
                            $order = Db::name('kuerp_goods_purchase')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_goods_purchase')->where(['purchase_code'=>$info_v['purchase_number']])
                                    ->update(['pay_money'=>$info_v['old_money'],'pay_status'=>$info_v['old_status']]);
                            }
                        }else if($info_v['purchase_type']==4 && $info_v['type']==2){
                            //购货退货
                            $order = Db::name('kuerp_goods_exchange')->where(['purchase_code'=>$info_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_goods_exchange')->where(['purchase_code'=>$info_v['purchase_number']])
                                    ->update(['retreat_money'=>$info_v['old_money'],'retreat_status'=>$info_v['old_status']]);
                            }
                        }

                        Db::name('kuerp_payment_log')->where(['bill_codes'=>$info_v['purchase_code']])->delete();
                        Db::name('kuerp_sales_payment_log')->where(['bill_codes'=>$info_v['purchase_code']])->delete();
                    }

                    switch ($list_v['business_type']){
                        case 1:
                            //预收冲应收
                            Db::name('kuerp_accounts_receivable')->where(['business_type'=>'1','purchase_code'=>$list_v['purchase_code']])->delete();
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
                            break;
                        case 2:
                            //预付冲应付
                            Db::name('kuerp_accounts_copewith')->where(['business_type'=>'2','purchase_code'=>$list_v['purchase_code']])->delete();
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
                            break;
                        case 3:
                            //应收冲应付
                            $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->find();
                            $gave_money = $suppliers['need_receive_account'] + $list_v['write_off'];
                            Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->update(['need_receive_account'=>$gave_money]);

                            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_id']])->find();
                            $gave_money = $suppliers['need_give_account'] + $list_v['write_off'];
                            Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_id']])->update(['need_give_account'=>$gave_money]);

                            Db::name('kuerp_accounts_receivable')->where(['business_type'=>'3','purchase_code'=>$list_v['purchase_code']])->delete();
                            Db::name('kuerp_accounts_copewith')->where(['business_type'=>'3','purchase_code'=>$list_v['purchase_code']])->delete();
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
                            break;
                        case 4:
                            //应收转应收

                            $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_out_id']])->find();
                            $gave_money = $suppliers['need_receive_account'] + $list_v['write_off'];
                            Db::name('kuerp_customers')->where(['id'=>$list_v['customers_out_id']])->update(['need_receive_account'=>$gave_money]);

                            $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_enter_id']])->find();
                            $gave_money = $suppliers['need_receive_account'] - $list_v['write_off'];
                            Db::name('kuerp_customers')->where(['id'=>$list_v['customers_enter_id']])->update(['need_receive_account'=>$gave_money]);

                            Db::name('kuerp_writeoff_source')->where(['purchase_code'=>$list_v['purchase_code'],'type'=>4])->delete();
                            Db::name('kuerp_accounts_receivable')->where(['business_type'=>'4','purchase_code'=>$list_v['purchase_code']])->delete();
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
                            break;
                        case 5:
                            //应付转应付

                            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_out_id']])->find();
                            $gave_money = $suppliers['need_give_account'] + $list_v['write_off'];
                            Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_out_id']])->update(['need_give_account'=>$gave_money]);

                            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_enter_id']])->find();
                            $gave_money = $suppliers['need_give_account'] - $list_v['write_off'];
                            Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_enter_id']])->update(['need_give_account'=>$gave_money]);

                            Db::name('kuerp_writeoff_source')->where(['purchase_code'=>$list_v['purchase_code'],'type'=>4])->delete();
                            Db::name('kuerp_accounts_copewith')->where(['business_type'=>'5','purchase_code'=>$list_v['purchase_code']])->delete();
                            $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
                            break;
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }

                if(isset($result) && $result !== false){
                    $success_count += 1;
                    // 核销后同步所属销货对账单的收款状态（到期提醒、差异校验依赖该字段）
                    try {
                        $reconCodes = Db::name('kuerp_writeoff_info')->where(['collection_id'=>$list_v['id']])->column('purchase_number');
                        \app\admin\model\kuerp\SalesRecon::recalcPayByDocCodes($reconCodes);
                    } catch (\Exception $e) {
                    }
                }
            }
            if($success_count>0){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success($success_count.'条数据反审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('反审核失败');
            }
        }
    }
    //核销单选择预收单据
    public function choselist($customers_id=''){
        $this->assignconfig('customers_id',$customers_id);
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $where = [];
            $where['customers_id'] = $customers_id;
            $where['purchase_type'] = ['in',[1,6]];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['purchase_code'])){
                    $where['purchase_code'] = ["like", '%'.$filter['purchase_code'].'%'];
                }
            }
            $list = Db::name('kuerp_writeoff_source')
                ->where($where)
                ->where(function ($query){
                    $query->where('id', 'NOT IN', function ($query) {
                        $query->name('kuerp_writeoff_log')->field('source_id');
                    })
                        ->whereOr('id', 'IN', function ($query) {
                            $query->name('kuerp_writeoff_log')->where(['write_off'=>['<>','0']])->field('source_id');
                        });
                })
                ->order(['purchase_type'=>'DESC','purchase_time'=>'ASC'])
                ->select();

            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['money_tax_price'] = $list_v['price'];
                $list[$list_k]['pay_money'] = $list_v['money'];
                $list[$list_k]['money'] = $list_v['write_off'];
                $list[$list_k]['purchase_type_id'] = $list_v['id'];
                $list[$list_k]['purchase_codes'] = $list_v['purchase_code'];
                if($list_v['purchase_type']>4){
                    $list[$list_k]['purchase_type'] = '期初余额';
                    $list[$list_k]['purchase_code'] = '期初余额';
                }else{
                    $list[$list_k]['purchase_type'] = '收款';
                }
                $list[$list_k]['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                $list[$list_k]['remark'] = $list_v['remark'];
                $list[$list_k]['type'] = $list_v['type'];

                $writeoff_log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$list_v['id']])->field('money,write_off')->find();
                if($writeoff_log){
                    $list[$list_k]['money'] = $writeoff_log['write_off'];
                    $list[$list_k]['pay_money'] = $writeoff_log['money'];
                }else{
                    $list[$list_k]['pay_money'] = 0;
                }
            }

            $result = array("total" => 1, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
    //核销单选择预付单据
    public function choseadvancelist($suppliers_id=''){
        $this->assignconfig('suppliers_id',$suppliers_id);
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$suppliers_id])->find();
            $where = [];
            $where['suppliers_code'] = $suppliers['supplyer_code'];
            $where['purchase_type'] = ['in',[2,6]];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['purchase_code'])){
                    $where['purchase_code'] = ["like", '%'.$filter['purchase_code'].'%'];
                }
            }
            $list = Db::name('kuerp_writeoff_source')
                ->where($where)
                ->where(function ($query){
                    $query->where('id', 'NOT IN', function ($query) {
                        $query->name('kuerp_writeoff_log')->field('source_id');
                    })
                        ->whereOr('id', 'IN', function ($query) {
                            $query->name('kuerp_writeoff_log')->where(['write_off'=>['<>','0']])->field('source_id');
                        });
                })
                ->order(['purchase_type'=>'DESC','purchase_time'=>'ASC'])
                ->select();

            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['money_tax_price'] = $list_v['price'];
                $list[$list_k]['pay_money'] = $list_v['money'];
                $list[$list_k]['money'] = $list_v['write_off'];
                $list[$list_k]['purchase_type_id'] = $list_v['id'];
                $list[$list_k]['purchase_codes'] = $list_v['purchase_code'];
                if($list_v['purchase_type']>4){
                    $list[$list_k]['purchase_type'] = '期初余额';
                    $list[$list_k]['purchase_code'] = '期初余额';
                }else{
                    $list[$list_k]['purchase_type'] = '付款';
                }
                $list[$list_k]['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                $list[$list_k]['remark'] = $list_v['remark'];
                $list[$list_k]['type'] = $list_v['type'];

                $writeoff_log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$list_v['id']])->field('money,write_off')->find();
                if($writeoff_log){
                    $list[$list_k]['money'] = $writeoff_log['write_off'];
                    $list[$list_k]['pay_money'] = $writeoff_log['money'];
                }else{
                    $list[$list_k]['pay_money'] = 0;
                }
            }
            $result = array("total" => 1, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
    //核销单选择应收单据
    public function chosereceivable($customers_id=''){
        $this->assignconfig('customers_id',$customers_id);
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $where = [];
            $where['customers_id'] = $customers_id;
            $where['purchase_type'] = ['in',[3,5]];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['purchase_code'])){
                    $where['purchase_code'] = ["like", '%'.$filter['purchase_code'].'%'];
                }
            } 
            $list = Db::name('kuerp_writeoff_source')
                ->where($where)
                ->where(function ($query){
                    $query->where('id', 'NOT IN', function ($query) {
                        $query->name('kuerp_writeoff_log')->field('source_id');
                    })
                        ->whereOr('id', 'IN', function ($query) {
                            $query->name('kuerp_writeoff_log')->where(['write_off'=>['<>','0']])->field('source_id');
                        });
                })
                ->order(['purchase_type'=>'DESC','purchase_time'=>'ASC'])
                ->select();

            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['money_tax_price'] = $list_v['price'];
                $list[$list_k]['pay_money'] = $list_v['money'];
                $list[$list_k]['money'] = $list_v['write_off'];
                $list[$list_k]['purchase_type_id'] = $list_v['id'];
                $list[$list_k]['purchase_codes'] = $list_v['purchase_code'];
                if($list_v['purchase_type']>4){
                    $list[$list_k]['purchase_type'] = '期初余额';
                    $list[$list_k]['purchase_code'] = '期初余额';
                }else{
                    if($list_v['type']==1){
                        $list[$list_k]['purchase_type'] = '普通销售';
                    }else if($list_v['type']==4){
                        $list[$list_k]['purchase_type'] = '核销';
                    }else{
                        $list[$list_k]['purchase_type'] = '销售退货';
                    }
                }
                $list[$list_k]['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                $list[$list_k]['remark'] = $list_v['remark'];
                $list[$list_k]['type'] = $list_v['type'];

                $writeoff_log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$list_v['id']])->field('money,write_off')->find();
                if($writeoff_log){
                    $list[$list_k]['money'] = $writeoff_log['write_off'];
                    $list[$list_k]['pay_money'] = $writeoff_log['money'];
                }else{
                    $list[$list_k]['pay_money'] = 0;
                }
            }

            $result = array("total" => 1, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
    //核销单选择应付单据
    public function chosecopewith($suppliers_id=''){
        $this->assignconfig('suppliers_id',$suppliers_id);
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$suppliers_id])->find();
            $where = [];
            $where['suppliers_code'] = $suppliers['supplyer_code'];
            $where['purchase_type'] = ['in',[4,5,6]];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['purchase_code'])){
                    $where['purchase_code'] = ["like", '%'.$filter['purchase_code'].'%'];
                }
            }
            $list = Db::name('kuerp_writeoff_source')
                ->where($where)
                ->where(function ($query){
                    $query->where('id', 'NOT IN', function ($query) {
                        $query->name('kuerp_writeoff_log')->field('source_id');
                    })
                        ->whereOr('id', 'IN', function ($query) {
                            $query->name('kuerp_writeoff_log')->where(['write_off'=>['<>','0']])->field('source_id');
                        });
                })
                ->order(['purchase_type'=>'DESC','purchase_time'=>'ASC'])
                ->select();

            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['money_tax_price'] = $list_v['price'];
                $list[$list_k]['pay_money'] = $list_v['money'];
                $list[$list_k]['money'] = $list_v['write_off'];
                $list[$list_k]['purchase_type_id'] = $list_v['id'];
                $list[$list_k]['purchase_codes'] = $list_v['purchase_code'];
                if($list_v['purchase_type']==6){
                    $list[$list_k]['purchase_type'] = '外协加工';
                }else if($list_v['purchase_type']>4){
                    $list[$list_k]['purchase_type'] = '期初余额';
                    $list[$list_k]['purchase_code'] = '期初余额';
                }else{
                    if($list_v['type']==1){
                        $list[$list_k]['purchase_type'] = '普通购货';
                    }else{
                        $list[$list_k]['purchase_type'] = '购货退货';
                    }
                }
                $list[$list_k]['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                $list[$list_k]['remark'] = $list_v['remark'];
                $list[$list_k]['type'] = $list_v['type'];

                $writeoff_log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$list_v['id']])->field('money,write_off')->find();
                if($writeoff_log){
                    $list[$list_k]['money'] = $writeoff_log['write_off'];
                    $list[$list_k]['pay_money'] = $writeoff_log['money'];
                }else{
                    $list[$list_k]['pay_money'] = 0;
                }
            }

            $result = array("total" => 1, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
}
