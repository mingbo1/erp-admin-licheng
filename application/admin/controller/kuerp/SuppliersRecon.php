<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 供应商对账单
 *
 * @icon fa fa-circle-o
 */
class SuppliersRecon extends Backend
{
    
    /**
     * AccountsReceivable模型对象
     * @var \app\admin\model\kuerp\AccountsReceivable
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\AccountsReceivable;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("suppliers", $this->model->getSuppliersList());
        $this->view->assign("saleList", $this->model->getSaleList());
    }

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
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['suppliers_id'])){
                    $map['sp.id'] = trim($filter['suppliers_id']);
                }
            }

            $sup = Db::name('kuerp_suppliers')->alias('sp')
                ->join('kuerp_supplyertype gt','sp.supplyertype_id=gt.id')
                ->where($map)
                ->order('sp.supplyer_code asc')
                ->field('sp.id,sp.receive_account,sp.supplyer_code,sp.supplyer_name,gt.supplier_type_name,sp.opening_payable,sp.opening_prepayment')
                ->select();

            $field = empty(input('field'))?'createtime':trim(input('field'));
            $order = empty(input('sort'))?'ASC':trim(input('sort'));

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


            $listarr = [];
            $all_sale_money = 0;
            $all_receivable = 0;
            $all_cost = 0;
            $all_real_money = 0;
            $all_balance = 0;
            foreach ($sup as $sup_k=>$sup_v){
                $sale_money = 0;
                $receivable = 0;
                $cost = 0;
                $real_money = 0;
                $balance = 0;
                $sup_v['receive_account'] = round($sup_v['opening_payable']-$sup_v['opening_prepayment'],$price_decimal);
                $listarr[] = [
                    'customer_name' => $sup_v['supplyer_name'],
                    'purchase_time' => '',
                    'purchase_code' => '期初余额',
                    'business_type' => '',
                    'sale_money'    => '',
                    'receivable'    => '',
                    'amount'        => '',
                    'cost'          => '',
                    'real_money'    => '',
                    'balance'       => $sup_v['receive_account'],
                    'remark'        => ''
                ];
                $balance += $sup_v['receive_account'];

                $list = Db::name('kuerp_accounts_copewith')
                    ->where($where)
                    ->where(['suppliers_id'=>$sup_v['id'],'business_type'=>['not in',[1,2]]])
                    ->order([$field=>$order])
                    ->select();
                foreach ($list as $list_k=>$list_v){
                    if($list_v['business_type']==7){
                        $balance     -= $list_v['receivable_money'];
                        $balance     += $list_v['real_money'];
                        $sale_money  -= $list_v['sale_money'];
                        $receivable  -= $list_v['receivable_money'];
                        $cost        -= $list_v['cost'];
                        $real_money  -= $list_v['real_money'];
                        $listarr[] = [
                            'customer_name' => $sup_v['supplyer_name'],
                            'purchase_time' => !empty($list_v['purchase_time'])?date('Y-m-d',$list_v['purchase_time']):'',
                            'purchase_code' => $list_v['purchase_code'],
                            'business_type' => $list_v['business_type'],
                            'sale_money'    => -$list_v['sale_money'],
                            'receivable'    => -$list_v['receivable_money'],
                            'amount'        => -$list_v['amount'],
                            'cost'          => -$list_v['cost'],
                            'real_money'    => -$list_v['real_money'],
                            'balance'       => round($balance,$price_decimal),
                            'remark'        => $list_v['remark'],
                        ];
                    }else{
                        $balance     += $list_v['receivable_money'];
                        $balance     -= $list_v['real_money'];
                        $sale_money  += $list_v['sale_money'];
                        $receivable  += $list_v['receivable_money'];
                        $cost        += $list_v['cost'];
                        $real_money  += $list_v['real_money'];
                        $listarr[] = [
                            'customer_name' => $sup_v['supplyer_name'],
                            'purchase_time' => !empty($list_v['purchase_time'])?date('Y-m-d',$list_v['purchase_time']):'',
                            'purchase_code' => $list_v['purchase_code'],
                            'business_type' => $list_v['business_type'],
                            'sale_money'    => $list_v['sale_money'],
                            'receivable'    => $list_v['receivable_money'],
                            'amount'        => $list_v['amount'],
                            'cost'          => $list_v['cost'],
                            'real_money'    => $list_v['real_money'],
                            'balance'       => round($balance,$price_decimal),
                            'remark'        => $list_v['remark'],
                        ];
                    }
                }
                $listarr[] = [
                    'customer_name' => $sup_v['supplyer_name'],
                    'purchase_time' => '',
                    'purchase_code' => '小计',
                    'business_type' => '',

                    'sale_money'    => round($sale_money,$price_decimal),
                    'receivable'    => round($receivable,$price_decimal),
                    'amount'        => '',
                    'cost'          => round($cost,$price_decimal),
                    'real_money'    => round($real_money,$price_decimal),
                    'balance'       => round($balance,$price_decimal),
                    'remark'        => ''
                ];
                $all_sale_money += $sale_money;
                $all_receivable += $receivable;
                $all_cost       += $cost;
                $all_real_money += $real_money;
                $all_balance    += $balance;
            }

            $result = array("total" => sizeof($listarr),
                "rows" => $listarr,
                "all_sale_money" => $all_sale_money,
                "all_receivable" => $all_receivable,
                "all_cost" => $all_cost,
                "all_real_money" => $all_real_money,
                "all_balance" => $all_balance,
            );

            return json($result);
        }
        return $this->view->fetch();
    }

}
