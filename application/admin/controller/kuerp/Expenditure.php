<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 其他支出单
 *
 * @icon fa fa-circle-o
 */
class Expenditure extends Backend
{

    /**
     * Expenditure模型对象
     * @var \app\admin\model\kuerp\Expenditure
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Expenditure;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("suppliers", $this->model->getSuppliersList());
        $this->view->assign("incomeType", $this->model->getIncomeTypeList());
        $this->view->assign("accountsList", $this->model->getAccountsList());

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

            $list = $this->model
                ->with(['admin','suppliers','examine'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('examine')->visible(['nickname']);
                $row->getRelation('admin')->visible(['nickname']);
                $row->getRelation('suppliers')->visible(['supplyer_name']);
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

                $data = [];
                if(!empty($params['suppliers_id'])){
                    $customers = Db::name('kuerp_suppliers')->where(['id'=>$params['suppliers_id']])->find();
                    if(empty($customers)){
                        $this->error('供应商信息错误');
                    }
                }
                $money = 0;
                foreach ($params['money'] as $k=>$v){
                    if($v!==''){
                        if($v<=0){
                            $this->error('支出金额不能小于0');
                        }else if(empty($params['expend_type_id'][$k])){
                            $this->error('请选择支出类别');
                        }else{
                            $money += (float)$v;
                            $income_type = Db::name('kuerp_expend_type')->where(['id'=>$params['expend_type_id'][$k]])->find();
                            if(empty($income_type)){
                                $this->error('支出类别查询错误');
                            }
                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'    => $params['purchase_code'],
                                'purchase_time'    => strtotime($params['purchase_time']),
                                'business_type'    => '1',
                                'suppliers_id'     => isset($customers)?$customers['id']:null,
                                'expend_type_id'   => $income_type['id'],
                                'money'            => (float)$v,
                                'remark'           => $params['remark'][$k]!==''?$params['remark'][$k]:null,
                                'cost_id'          => !empty($params['cost_id'][$k])?$params['cost_id'][$k]:null,
                                'createtime'       => time(),
                                'updatetime'       => time(),
                            ]);
                        }
                    }
                }

                if($money<=0){
                    $this->error('支出金额不能小于0');
                }else if($money>0 && empty($params['accounts_id'])){
                    $this->error('请选择结算账户');
                }else if($params['pay_money']>0 && (int)$params['pay_money']>(int)$money){
                    $this->error('付款金额不能大于支出金额');
                }else{
                    $accounts = Db::name('kuerp_accounts')->where(['id'=>$params['accounts_id']])->find();
                    if(empty($accounts)){
                        $this->error('结算账户信息错误');
                    }
                }

                $pay_status = 0;
                if($params['pay_money']>0){
                    $pay_status = $money==$params['pay_money']?1:2;
                }
                $row = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'business_type'     =>  '1',
                    'suppliers_id'      =>  isset($customers)?$customers['id']:null,
                    'accounts_id'       =>  isset($accounts)?$accounts['id']:null,
                    'cost_id'           =>  $params['cost_id']!==''?$params['cost_id']:null,
                    'money'             =>  $money,
                    'pay_money'         =>  $params['pay_money']!==''?(float)$params['pay_money']:null,
                    'arrears'           =>  $params['arrears']!==''?(float)$params['arrears']:null,
                    'remark'            =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
                    'pay_status'        =>  $pay_status,
                    'createtime'        =>  time(),
                    'updatetime'        =>  time(),
                ]);

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
                        $data[$data_k]['purchase_id'] = $result;
                    }
                    Db::name('kuerp_expenditure_info')->insertAll($data);
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
        $code = \addons\kuerp\library\Goods::getCodes('',25);
        $this->view->assign("code", $code);

        $list = Db::name('kuerp_cost')->where(['id'=>['in',$ids]])->select();
        $money = Db::name('kuerp_cost')->where(['id'=>['in',$ids]])->sum('money');
        $this->view->assign("money", $money);
        $this->view->assign("list", $list);
        $this->view->assign("row", (isset($list[0])?$list[0]:[]));
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
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                $data = [];
                if(!empty($params['suppliers_id'])){
                    $customers = Db::name('kuerp_suppliers')->where(['id'=>$params['suppliers_id']])->find();
                    if(empty($customers)){
                        $this->error('供应商信息错误');
                    }
                }
                $money = 0;
                foreach ($params['money'] as $k=>$v){
                    if($v!==''){
                        if($v<=0){
                            $this->error('支出金额不能小于0');
                        }else if(empty($params['expend_type_id'][$k])){
                            $this->error('请选择支出类别');
                        }else{
                            $money += (float)$v;
                            $income_type = Db::name('kuerp_expend_type')->where(['id'=>$params['expend_type_id'][$k]])->find();
                            if(empty($income_type)){
                                $this->error('收入类别查询错误');
                            }
                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'    => $params['purchase_code'],
                                'purchase_time'    => strtotime($params['purchase_time']),
                                'business_type'    => '1',
                                'suppliers_id'     => isset($customers)?$customers['id']:null,
                                'expend_type_id'   => $income_type['id'],
                                'money'            => (float)$v,
                                'remark'           => $params['remark'][$k]!==''?$params['remark'][$k]:null,
                                'cost_id'          => !empty($params['cost_id'][$k])?$params['cost_id'][$k]:null,
                                'createtime'       => time(),
                                'updatetime'       => time(),
                            ]);
                        }
                    }
                }

                if($money<=0){
                    $this->error('支出金额不能小于0');
                }else if($money>0 && empty($params['accounts_id'])){
                    $this->error('请选择结算账户');
                }else if($params['pay_money']>0 && $params['pay_money']>$money){
                    $this->error('付款金额不能大于支出金额');
                }else{
                    $accounts = Db::name('kuerp_accounts')->where(['id'=>$params['accounts_id']])->find();
                    if(empty($accounts)){
                        $this->error('结算账户信息错误');
                    }
                }

                $pay_status = 0;
                if($params['pay_money']>0){
                    $pay_status = $money==$params['pay_money']?1:2;
                }
                $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'business_type'     =>  '1',
                    'suppliers_id'      =>  isset($customers)?$customers['id']:null,
                    'accounts_id'       =>  isset($accounts)?$accounts['id']:null,
                    'cost_id'           =>  $params['cost_id']!==''?$params['cost_id']:null,
                    'money'             =>  $money,
                    'pay_money'         =>  $params['pay_money']!==''?(float)$params['pay_money']:null,
                    'arrears'           =>  $params['arrears']!==''?(float)$params['arrears']:null,
                    'remark'            =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
                    'pay_status'        =>  $pay_status,
                    'createtime'        =>  time(),
                    'updatetime'        =>  time(),
                ]);

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    Db::name('kuerp_expenditure_info')->where(['purchase_id'=>$ids])->delete();

                    foreach ($data as $data_k=>$data_v){
                        $data[$data_k]['purchase_id'] = $ids;
                    }
                    Db::name('kuerp_expenditure_info')->insertAll($data);
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
        $price_decimal  = $setList['price_decimal']['value'];

        $info = Db::name('kuerp_expenditure_info')->where(['purchase_id'=>$row['id']])->select();
        $money_hj = Db::name('kuerp_expenditure_info')->where(['purchase_id'=>$row['id']])->sum('money');
        $this->view->assign("info", $info);
        $this->view->assign("money_hj", round($money_hj,$price_decimal));

        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['id'=>$row['suppliers_id']])->field('id,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);

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

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::name('kuerp_expenditure_info')->where(['purchase_id'=>['in',$del_id]])->delete();
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
            $list = Db::name('kuerp_expenditure')->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
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
            $income_order = [];//采购销售费用
            $account_list = [];//账户
            $account_log  = [];//账户记录
            $accounts_receivable = [];//应收账款明细表
            foreach ($list as $list_k=>$list_v){
                //关联采购销售费用
                $c_list = Db::name('kuerp_expenditure_info')->where(['purchase_id'=>$list_v['id']])->select();
                $pay_money = $list_v['pay_money'];
                foreach ($c_list as $c_list_k=>$c_list_v){
                    if(!empty($c_list_v['cost_id'])){
                        $cost = Db::name('kuerp_cost')->where(['id'=>$c_list_v['cost_id']])->order('id','ASC')->find();
                        if(empty($cost)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'编号'.$list_v['purchase_code'].'审核失败，关联的采购销售费用数据错误'];
                            }
                            $this->error('编号'.$list_v['purchase_code'].'审核失败，关联的采购销售费用数据错误');
                        }
                        $status = true;
                        if($cost['pay_money']<=$pay_money){
                            $pay_status = 1;
                            $now_pay_money = $cost['pay_money'];
                        }elseif ($cost['pay_money']>$pay_money && $pay_money>0){
                            $pay_status = 2;
                            $now_pay_money = $pay_money;
                        }else{
                            $pay_status = $cost['pay_status'];
                            $now_pay_money = 0;
                        }
                        $pay_money -= $now_pay_money;
                        foreach ($income_order as $income_order_k=>$income_order_v){
                            if($income_order_v['id']==$cost['id']){
                                $status = false;
                                $income_order[$income_order_k]['pay_money'] -= $now_pay_money;
                                $income_order[$income_order_k]['pay_status'] = $pay_status;
                            }
                        }
                        if($status){
                            $income_order[] = [
                                'id'        => $c_list_v['cost_id'],
                                'pay_money' => $cost['pay_money'] - $now_pay_money,
                                'pay_status'=> $pay_status,
                            ];
                        }
                    }
                }

                //账户信息
                $account = Db::name('kuerp_accounts')->where(['id'=>$list_v['accounts_id']])->find();
                $status = true;
                foreach ($account_list as $account_list_k=>$account_list_v){
                    if($account_list_v['id']==$account['id']){
                        $status = false;
                        $account_list[$account_list_k]['balance'] -= $list_v['pay_money'];
                    }
                }
                if($status){
                    $account_list[] = [
                        'id'        => $account['id'],
                        'balance'   => $account['balance'] - $list_v['pay_money'],
                    ];
                }
                //账户记录
                $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_id']])->find();
                $account_log[] = [
                    'account_coding'    => $account['account_coding'],
                    'account_name'      => $account['account_name'],
                    'balance'           => $account['balance']+$list_v['pay_money'],
                    'expenditure'       => $list_v['pay_money'],
                    'pay_time'          => $list_v['purchase_time'],
                    'bill_code'         => $list_v['purchase_code'],
                    'business_type'     => '10',
                    'supplyer_code'     => isset($suppliers['supplyer_code'])?$suppliers['supplyer_code']:'',
                    'remark'            => $list_v['remark'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ];

                //应付账款明细表
                if($list_v['pay_money']<$list_v['money']){
                    $accounts_receivable[] = \addons\kuerp\library\Goods::setDataInfo([
                        'suppliers_id'      => $list_v['suppliers_id'],
                        'purchase_time'     => $list_v['purchase_time'],
                        'purchase_code'     => $list_v['purchase_code'],
                        'business_type'     => '8',
                        'receivable'        => $list_v['arrears'],
                        'remark'            => $list_v['remark'],
                        'sale_money'        => $list_v['money'],
                        'receivable_money'  => $list_v['money'],
                        'real_money'        => $list_v['pay_money'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ]);
                }
            }
            Db::startTrans();
            try {
                if(!empty($accounts_receivable)){
                    Db::name('kuerp_accounts_copewith')->insertAll($accounts_receivable);
                }
                if(!empty($account_log)){
                    Db::name('kuerp_accounts_log')->insertAll($account_log);
                }
                if(!empty($account_list)){
                    (new \app\admin\model\kuerp\Accounts())->saveAll($account_list);
                }
                if(!empty($income_order)){
                    (new \app\admin\model\kuerp\Cost())->saveAll($income_order);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
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
            $list = Db::name('kuerp_expenditure')->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
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
            $income_order = [];//采购销售费用
            $account_list = [];//账户
            $purchase_code = [];//单据编号
            foreach ($list as $list_k=>$list_v){
                $purchase_code[] = $list_v['purchase_code'];
                //关联采购销售费用
                $pay_money = $list_v['pay_money'];
                $c_list = Db::name('kuerp_expenditure_info')->where(['purchase_id'=>$list_v['id']])->select();
                foreach ($c_list as $c_list_k=>$c_list_v){
                    if(!empty($c_list_v['cost_id'])){
                        $cost = Db::name('kuerp_cost')->where(['id'=>$c_list_v['cost_id']])->order('id','ASC')->find();
                        if(empty($cost)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'编号'.$list_v['purchase_code'].'审核失败，关联的采购销售费用数据错误'];
                            }
                            $this->error('编号'.$list_v['purchase_code'].'审核失败，关联的采购销售费用数据错误');
                        }
                        $status = true;
                        if($c_list_v['money']<=$pay_money){
                            $now_pay_money = $c_list_v['money'];
                        }elseif ($c_list_v['money']>$pay_money && $pay_money>0){
                            $now_pay_money = $pay_money;
                        }else{
                            $now_pay_money = 0;
                        }
                        if($cost['pay_money']-$now_pay_money<=0){
                            $pay_status = 0;
                        }else if($cost['pay_money']-$now_pay_money>0 && $now_pay_money<$cost['money']){
                            $pay_status = 2;
                        }else{
                            $pay_status = 1;
                        }
                        $pay_money -= $now_pay_money;
                        foreach ($income_order as $income_order_k=>$income_order_v){
                            if($income_order_v['id']==$cost['id']){
                                $status = false;
                                $income_order[$income_order_k]['pay_money'] += $c_list_v['money'];
                                $income_order[$income_order_k]['pay_status'] = $pay_status;
                            }
                        }
                        if($status){
                            $income_order[] = [
                                'id'        => $c_list_v['cost_id'],
                                'pay_money' => $cost['pay_money'] + $now_pay_money,
                                'pay_status'=> $pay_status,
                            ];
                        }
                    }
                }
                //账户信息
                $account = Db::name('kuerp_accounts')->where(['id'=>$list_v['accounts_id']])->find();
                $status = true;
                foreach ($account_list as $account_list_k=>$account_list_v){
                    if($account_list_v['id']==$account['id']){
                        $status = false;
                        $account_list[$account_list_k]['balance'] += $list_v['pay_money'];
                    }
                }
                if($status){
                    $account_list[] = [
                        'id'        => $account['id'],
                        'balance'   => $account['balance'] + $list_v['pay_money'],
                    ];
                }
            }
            Db::startTrans();
            try {
                Db::name('kuerp_accounts_copewith')->where(['business_type'=>'8','purchase_code'=>['in',$purchase_code]])->delete();
                Db::name('kuerp_accounts_log')->where(['business_type'=>'10','bill_code'=>['in',$purchase_code]])->delete();
                if(!empty($account_list)){
                    (new \app\admin\model\kuerp\Accounts())->saveAll($account_list);
                }
                if(!empty($income_order)){
                    (new \app\admin\model\kuerp\Cost())->saveAll($income_order);
                }
                $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
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
}
