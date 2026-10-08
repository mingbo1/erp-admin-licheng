<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 资金转账单
 *
 * @icon fa fa-circle-o
 */
class AccountsTransfer extends Backend
{

    /**
     * AccountsTransfer模型对象
     * @var \app\admin\model\kuerp\AccountsTransfer
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\AccountsTransfer;
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());

        $this->view->assign("settlementtype", $this->model->getSettlementtypeList());
        $this->view->assign("accountsList", $this->model->getAccountsList());
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
            $accounts = Db::name('kuerp_accounts')->column('account_name','id');
            $admin    = Db::name('admin')->column('nickname','id');

            $lists = [];
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list_v['print_num'] = 0;
                }
                $info_list = Db::name('kuerp_accounts_transfer_info')->where(['purchase_id'=>$list_v['id']])->select();
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $info_list[$info_list_k]['accounts_out'] = isset($accounts[$info_list_v['accounts_out_id']])?$accounts[$info_list_v['accounts_out_id']]:'';
                    $info_list[$info_list_k]['accounts_enter'] = isset($accounts[$info_list_v['accounts_enter_id']])?$accounts[$info_list_v['accounts_enter_id']]:'';
                    $lists[] = [
                        'id'            => $list_v['id'],
                        'purchase_code' => $list_v['purchase_code'],
                        'purchase_time' => date('Y-m-d',$list_v['purchase_time']),
                        'accounts_out'  => (isset($accounts[$info_list_v['accounts_out_id']])?$accounts[$info_list_v['accounts_out_id']]:''),
                        'accounts_enter'=> (isset($accounts[$info_list_v['accounts_enter_id']])?$accounts[$info_list_v['accounts_enter_id']]:''),
                        'money'         => $info_list_v['money'],
                        'moneys'        => $list_v['money'],
                        'preparation'   => isset($admin[$list_v['preparation_id']])?$admin[$list_v['preparation_id']]:'',
                        'examine'       => isset($admin[$list_v['examine_id']])?$admin[$list_v['examine_id']]:'',
                        'remark'        => $list_v['remark'],
                        'print_num'     => $list_v['print_num'],
                    ];
                }
            }

            $result = array("total" => $list->total(), "rows" => $lists);

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

                $accounts = Db::name('kuerp_accounts')->column('account_name','id');
                $data = [];
                $money = 0;
                foreach ($params['money'] as $k=>$v){
                    if($v!==''){
                        if($v<=0){
                            $this->error('转出金额不能小于0');
                        }else if(empty($params['settlementtype'][$k])){
                            $this->error('请选择结算方式');
                        }else if(empty($params['accounts_out_id'][$k])){
                            $this->error('请选择转出账户');
                        }else if(empty($params['accounts_enter_id'][$k])){
                            $this->error('请选择转入账户');
                        }else if($params['accounts_out_id'][$k]==$params['accounts_enter_id'][$k]){
                            $this->error('转出账户和转入账户不能为同一账户');
                        }else{
                            $money += (float)$v;
                            $income_type = Db::name('kuerp_settlementtype')->where(['id'=>$params['settlementtype'][$k]])->find();
                            if(empty($income_type)){
                                $this->error('结算方式查询错误');
                            }elseif (!isset($accounts[$params['accounts_out_id'][$k]])){
                                $this->error('转出账户信息有误');
                            }elseif (!isset($accounts[$params['accounts_enter_id'][$k]])){
                                $this->error('转入账户信息有误');
                            }
                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'    => $params['purchase_code'],
                                'purchase_time'    => strtotime($params['purchase_time']),
                                'accounts_out_id'  => $params['accounts_out_id'][$k],
                                'accounts_enter_id'=> $params['accounts_enter_id'][$k],
                                'settlementtype_id'=> $params['settlementtype'][$k],
                                'settlement_number'=> $params['settlement_number'][$k]!==''?$params['settlement_number'][$k]:null,
                                'money'            => (float)$v,
                                'remark'           => $params['remark'][$k]!==''?$params['remark'][$k]:null,
                                'createtime'       => time(),
                                'updatetime'       => time(),
                            ]);
                        }
                    }
                }

                if($money<=0){
                    $this->error('转账金额不能小于0');
                }

                $row = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'money'             =>  $money,
                    'remark'            =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
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
                    Db::name('kuerp_accounts_transfer_info')->insertAll($data);
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
        $code = \addons\kuerp\library\Goods::getCodes('',26);
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
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                $accounts = Db::name('kuerp_accounts')->column('account_name','id');
                $data = [];
                $money = 0;
                foreach ($params['money'] as $k=>$v){
                    if($v!==''){
                        if($v<=0){
                            $this->error('转出金额不能小于0');
                        }else if(empty($params['settlementtype'][$k])){
                            $this->error('请选择结算方式');
                        }else if(empty($params['accounts_out_id'][$k])){
                            $this->error('请选择转出账户');
                        }else if(empty($params['accounts_enter_id'][$k])){
                            $this->error('请选择转入账户');
                        }else if($params['accounts_out_id'][$k]==$params['accounts_enter_id'][$k]){
                            $this->error('转出账户和转入账户不能为同一账户');
                        }else{
                            $money += (float)$v;
                            $income_type = Db::name('kuerp_settlementtype')->where(['id'=>$params['settlementtype'][$k]])->find();
                            if(empty($income_type)){
                                $this->error('结算方式查询错误');
                            }elseif (!isset($accounts[$params['accounts_out_id'][$k]])){
                                $this->error('转出账户信息有误');
                            }elseif (!isset($accounts[$params['accounts_enter_id'][$k]])){
                                $this->error('转入账户信息有误');
                            }
                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'    => $params['purchase_code'],
                                'purchase_time'    => strtotime($params['purchase_time']),
                                'accounts_out_id'  => $params['accounts_out_id'][$k],
                                'accounts_enter_id'=> $params['accounts_enter_id'][$k],
                                'settlementtype_id'=> $params['settlementtype'][$k],
                                'settlement_number'=> $params['settlement_number'][$k]!==''?$params['settlement_number'][$k]:null,
                                'money'            => (float)$v,
                                'remark'           => $params['remark'][$k]!==''?$params['remark'][$k]:null,
                                'createtime'       => time(),
                                'updatetime'       => time(),
                            ]);
                        }
                    }
                }

                if($money<=0){
                    $this->error('转账金额不能小于0');
                }

                $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'money'             =>  $money,
                    'remark'            =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
                    'createtime'        =>  time(),
                    'updatetime'        =>  time(),
                ]);

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                $result = false;
                if($examine!=1 && $row['examine_status']==1){
                    $this->examine($ids,2);
                }
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    Db::name('kuerp_accounts_transfer_info')->where(['purchase_id'=>$ids])->delete();

                    foreach ($data as $data_k=>$data_v){
                        $data[$data_k]['purchase_id'] = $ids;
                    }
                    Db::name('kuerp_accounts_transfer_info')->insertAll($data);
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
        $set= \think\Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        //数量价格格式化
        $price_decimal  = $setList['price_decimal']['value'];

        $info = Db::name('kuerp_accounts_transfer_info')->where(['purchase_id'=>$row['id']])->select();
        $money_hj = Db::name('kuerp_accounts_transfer_info')->where(['purchase_id'=>$row['id']])->sum('money');
        $this->view->assign("info", $info);
        $this->view->assign("money_hj", round($money_hj,$price_decimal));

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
                Db::name('kuerp_accounts_transfer_info')->where(['purchase_id'=>['in',$del_id]])->delete();
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
            $list = Db::name('kuerp_accounts_transfer')->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            $accounts = Db::name('kuerp_accounts')->select();
            $accounts_list = [];
            foreach ($accounts as $accounts_k=>$accounts_v){
                $accounts_list[$accounts_v['id']] = $accounts_v;
            }

            $list_id = Db::name('kuerp_accounts_transfer')->where(['id'=>['in',$ids],'examine_status'=>'0'])->column('id');
            $info_list = Db::name('kuerp_accounts_transfer_info')->where(['purchase_id'=>['in',$list_id]])->select();

            $account_list = [];//账户
            $account_log  = [];//账户记录
            foreach ($info_list as $list_k=>$list_v){
                //账户信息
                if(!isset($accounts_list[$list_v['accounts_out_id']])){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$list_v['purchase_code'].'转出账户有误'];
                    }
                    $this->error($list_v['purchase_code'].'转出账户有误');
                }
                $account = $accounts_list[$list_v['accounts_out_id']];
                $status = true;
                foreach ($account_list as $account_list_k=>$account_list_v){
                    if($account_list_v['id']==$account['id']){
                        $status = false;
                        $account_list[$account_list_k]['balance'] -= $list_v['money'];
                    }
                }
                if($status){
                    $account_list[] = [
                        'id'        => $account['id'],
                        'balance'   => $account['balance'] - $list_v['money'],
                    ];
                }
                //账户记录
                $account_log[] = [
                    'account_coding'    => $account['account_coding'],
                    'account_name'      => $account['account_name'],
                    'balance'           => $account['balance']-$list_v['money'],
                    'expenditure'       => $list_v['money'],
                    'income'            => null,
                    'pay_time'          => $list_v['purchase_time'],
                    'bill_code'         => $list_v['purchase_code'],
                    'business_type'     => '12',
                    'remark'            => $list_v['remark'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ];

                if(!isset($accounts_list[$list_v['accounts_enter_id']])){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$list_v['purchase_code'].'转入账户有误'];
                    }
                    $this->error($list_v['purchase_code'].'转入账户有误');
                }
                $account = $accounts_list[$list_v['accounts_enter_id']];
                $status = true;
                foreach ($account_list as $account_list_k=>$account_list_v){
                    if($account_list_v['id']==$account['id']){
                        $status = false;
                        $account_list[$account_list_k]['balance'] += $list_v['money'];
                    }
                }
                if($status){
                    $account_list[] = [
                        'id'        => $account['id'],
                        'balance'   => $account['balance'] + $list_v['money'],
                    ];
                }
                //账户记录
                $account_log[] = [
                    'account_coding'    => $account['account_coding'],
                    'account_name'      => $account['account_name'],
                    'balance'           => $account['balance']+$list_v['money'],
                    'expenditure'       => null,
                    'income'            => $list_v['money'],
                    'pay_time'          => $list_v['purchase_time'],
                    'bill_code'         => $list_v['purchase_code'],
                    'business_type'     => '11',
                    'remark'            => $list_v['remark'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ];
            }


            Db::startTrans();
            try {
                if(!empty($account_log)){
                    Db::name('kuerp_accounts_log')->insertAll($account_log);
                }
                if(!empty($account_list)){
                    (new \app\admin\model\kuerp\Accounts())->saveAll($account_list);
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
            $list = Db::name('kuerp_accounts_transfer')->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
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
            $accounts = Db::name('kuerp_accounts')->select();
            $accounts_list = [];
            foreach ($accounts as $accounts_k=>$accounts_v){
                $accounts_list[$accounts_v['id']] = $accounts_v;
            }

            $list_id = Db::name('kuerp_accounts_transfer')->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('id');
            $info_list = Db::name('kuerp_accounts_transfer_info')->where(['purchase_id'=>['in',$list_id]])->select();

            $account_list = [];//账户
            $account_log  = [];//账户记录
            foreach ($info_list as $list_k=>$list_v){
                //账户信息
                if(!isset($accounts_list[$list_v['accounts_out_id']])){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$list_v['purchase_code'].'转出账户有误'];
                    }
                    $this->error($list_v['purchase_code'].'转出账户有误');
                }
                $account = $accounts_list[$list_v['accounts_out_id']];
                $status = true;
                foreach ($account_list as $account_list_k=>$account_list_v){
                    if($account_list_v['id']==$account['id']){
                        $status = false;
                        $account_list[$account_list_k]['balance'] += $list_v['money'];
                    }
                }
                if($status){
                    $account_list[] = [
                        'id'        => $account['id'],
                        'balance'   => $account['balance'] + $list_v['money'],
                    ];
                }
                //账户记录
                $account_log[] = [
                    'account_coding'    => $account['account_coding'],
                    'account_name'      => $account['account_name'],
                    'balance'           => $account['balance']+$list_v['money'],
                    'expenditure'       => $list_v['money'],
                    'pay_time'          => $list_v['purchase_time'],
                    'bill_code'         => $list_v['purchase_code'],
                    'business_type'     => '11',
                    'remark'            => $list_v['remark'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ];

                if(!isset($accounts_list[$list_v['accounts_enter_id']])){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$list_v['purchase_code'].'转入账户有误'];
                    }
                    $this->error($list_v['purchase_code'].'转入账户有误');
                }
                $account = $accounts_list[$list_v['accounts_enter_id']];
                $status = true;
                foreach ($account_list as $account_list_k=>$account_list_v){
                    if($account_list_v['id']==$account['id']){
                        $status = false;
                        $account_list[$account_list_k]['balance'] -= $list_v['money'];
                    }
                }
                if($status){
                    $account_list[] = [
                        'id'        => $account['id'],
                        'balance'   => $account['balance'] - $list_v['money'],
                    ];
                }
                //账户记录
                $account_log[] = [
                    'account_coding'    => $account['account_coding'],
                    'account_name'      => $account['account_name'],
                    'balance'           => $account['balance']-$list_v['money'],
                    'income'            => $list_v['money'],
                    'pay_time'          => $list_v['purchase_time'],
                    'bill_code'         => $list_v['purchase_code'],
                    'business_type'     => '12',
                    'remark'            => $list_v['remark'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ];
            }

            $purchase_code = Db::name('kuerp_accounts_transfer')->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');
            Db::startTrans();
            try {
                Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$purchase_code],'business_type'=>['in',[11,12]]])->delete();
                if(!empty($account_list)){
                    (new \app\admin\model\kuerp\Accounts())->saveAll($account_list);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
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
