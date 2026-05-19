<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 账户记录管理
 *
 * @icon fa fa-circle-o
 */
class AccountsLog extends Backend
{
    
    /**
     * AccountsLog模型对象
     * @var \app\admin\model\kuerp\AccountsLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\AccountsLog;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());

        $accountsList = $this->model->getAccountsList();
        $accountsLists = [];
        foreach ($accountsList as $accountsList_k=>$accountsList_v){
            $accountsLists[] = ['name'=>$accountsList_v['account_name'],'value'=>$accountsList_v['id']];
        }
        $this->view->assign("accountsList", $accountsList);
        $this->assignconfig("accountsLists", $accountsLists);

        $suppliersList = $this->model->getSuppliersList();
        $suppliersLists = [];
        foreach ($suppliersList as $suppliersList_k=>$suppliersList_v){
            $suppliersLists[] = ['name'=>$suppliersList_v['supplyer_name'],'value'=>$suppliersList_v['id']];
        }
        $this->view->assign("suppliersList", $suppliersList);
        $this->assignconfig("suppliersLists", $suppliersLists);

        $customersList = $this->model->getCustomersList();
        $customersLists = [];
        foreach ($customersList as $customersList_k=>$customersList_v){
            $customersLists[] = ['name'=>$customersList_v['customer_name'],'value'=>$customersList_v['id']];
        }
        $this->view->assign("customersList", $customersList);
        $this->assignconfig("customersLists", $customersLists);

        $saleList = $this->model->getSaleList();
        $saleLists = [];
        foreach ($saleList as $saleList_k=>$saleList_v){
            $saleLists[] = ['name'=>$saleList_v['name'],'value'=>$saleList_v['id']];
        }
        $this->view->assign("saleList", $saleList);
        $this->assignconfig("saleLists", $saleLists);
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
            $data = input();
            $where = [];
            $map = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['pay_time'])){
                    $str = explode(' - ',$filter['pay_time']);
                    $where['pay_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['accounts_id'])){
                    $map['id'] = ['in',trim($filter['accounts_id'])];
                }
                if(!empty($filter['customers_id'])){
                    $where['customers_id'] = ['in',trim($filter['customers_id'])];
                }
                if(!empty($filter['suppliers_id'])){
                    $suppliers = Db::name('kuerp_suppliers')->where(['id'=>['in',trim($filter['suppliers_id'])]])->column('supplyer_code');
                    $where['supplyer_code'] = ['in',$suppliers];
                }
                if(!empty($filter['payment_id'])){
                    $where['payment'] = ['in',trim($filter['payment_id'])];
                }
            }

            $sup = Db::name('kuerp_accounts')
                ->where($map)
                ->order('account_coding asc')
                ->select();

            $field = empty(input('field'))?'pay_time':trim(input('field'));
            $order = empty(input('sort'))?'ASC':trim(input('sort'));

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
            $stmen = Db::name('kuerp_staff_member')->column('name','id');


            $listarr = [];
            $all_income = 0;
            $all_expenditure = 0;
            $all_balance = 0;
            foreach ($sup as $sup_k=>$sup_v){
                $income = 0;
                $expenditure = 0;
                $balance = 0;
                $listarr[] = [
                    'account_code'  => $sup_v['account_coding'],
                    'account_name'  => $sup_v['account_name'],
                    'pay_time'      => '',
                    'bill_code'     => '',
                    'business_type' => '期初余额',
                    'income'        => '',
                    'expenditure'   => '',
                    'balance'       => round($sup_v['start_account_balance'],$price_decimal),
                    'correspondent' => '',
                    'payment'  => '',
                    'remark'        => ''
                ];
                $balance += $sup_v['start_account_balance'];

                $list = Db::name('kuerp_accounts_log')
                    ->where($where)
                    ->where(['account_coding'=>$sup_v['account_coding']])
                    ->order([$field=>$order])
                    ->select();
                foreach ($list as $list_k=>$list_v){
                    $balance        += $list_v['income'];
                    $balance        -= $list_v['expenditure'];
                    $income         += $list_v['income'];
                    $expenditure    += $list_v['expenditure'];
                    $correspondent = '';
                    if(in_array($list_v['business_type'],[2,3,4,8,10])){
                        $correspondent = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$list_v['supplyer_code']])->value('supplyer_name');
                    }else if(in_array($list_v['business_type'],[5,6,7,9])){
                        $correspondent = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->value('customer_name');
                    }
                    $listarr[] = [
                        'account_code'  => $sup_v['account_coding'],
                        'account_name'  => $sup_v['account_name'],
                        'pay_time'      => !empty($list_v['pay_time'])?date('Y-m-d',$list_v['pay_time']):'',
                        'bill_code'     => $list_v['bill_code'],
                        'business_type' => $list_v['business_type'],
                        'income'        => $list_v['income'],
                        'expenditure'   => $list_v['expenditure'],
                        'balance'       => round($balance,$price_decimal),
                        'correspondent' => $correspondent,
                        'payment'  => isset($stmen[$list_v['payment']])?$stmen[$list_v['payment']]:'',
                        'remark'        => $list_v['remark'],
                    ];
                }
                $listarr[] = [
                    'account_code'  => $sup_v['account_coding'],
                    'account_name'  => $sup_v['account_name'],
                    'pay_time'      => '',
                    'bill_code'     => '',
                    'business_type' => '小计',
                    'income'        => round($income,$price_decimal),
                    'expenditure'   => round($expenditure,$price_decimal),
                    'balance'       => round($balance,$price_decimal),
                    'correspondent' => '',
                    'payment'  => '',
                    'remark'        => ''
                ];
                $all_balance        += $balance;
                $all_income         += $income;
                $all_expenditure    += $expenditure;
            }

            $result = array("total" => sizeof($listarr), "rows" => $listarr, "all_balance" => $all_balance, "all_receivable" => $all_income, "all_advance" => $all_expenditure);

            return json($result);
        }
        return $this->view->fetch();
    }

}
