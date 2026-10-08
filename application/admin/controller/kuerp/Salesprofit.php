<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 销售利润表
 *
 * @icon fa fa-circle-o
 */
class Salesprofit extends Backend
{
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

        $this->model = new \app\admin\model\kuerp\GoodsSalesProfit();
        $this->view->assign("staffMemberList", $this->model->getStaffMemberList());
        $this->view->assign("customersList", $this->model->getCustomersList());
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
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'purchase_time';$order='ASC';

            $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
            $map = [];
            if(!empty($checkdate)){
                $map['purchase_time'] = ['>',$checkdate];
            }

            $list = $this->model
                ->with(['customers','preparation','staffmember'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            $all_money_all = 0;$profit_all = 0;$net_profit_add = 0;
            if(isset($list[0])){
                $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
                $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
                $map = [];
                if(!empty($checkdate)){
                    $map['purchase_time'] = ['>',$checkdate];
                }
                $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
                $valuation_set= Db::name('kuerp_sets')->where(['name'=>'valuation'])->value('value');//计算方法
                $stores_set= Db::name('kuerp_sets')->where(['name'=>'stores'])->value('is_checked');//是否分仓
                if($valuation_set!=2){
                    $valuation_set = 1;
                }
                if($stores_set!=1){
                    $stores_set = 0;
                }
                $price_taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');
                $taxes_set= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();

                $lists = Db::name('kuerp_goods_sales_profit')
                    ->where($map)
                    ->order($sort, $order)
                    ->select();

                $goods_info = [];
                $stores_ids = Db::name('kuerp_stores')->where(['status'=>'1'])->column('id');

                $lists = \addons\kuerp\library\Goods::getPrList($lists,$stores_set,$stores_ids,$enable_time,$valuation_set,$goods_info,$price_taxes,$taxes_set,$price_decimal);

                foreach ($list as $list_k=>$list_v){
                    $status = true;
                    foreach ($lists as $lists_k=>$lists_v){
                        if($list_v['id']==$lists_v['id']){
                            $status = false;
                            $list[$list_k]['pay_money'] = $lists[$lists_k]['pay_money'];
                            $list[$list_k]['sales_cost'] = $lists[$lists_k]['sales_cost'];
                            $list[$list_k]['profit'] = $lists[$lists_k]['profit'];
                            $list[$list_k]['profit_rate'] = $lists[$lists_k]['profit_rate'];
                            $list[$list_k]['net_profit'] = $lists[$lists_k]['net_profit'];
                            $list[$list_k]['net_profit_rate'] = $lists[$lists_k]['net_profit_rate'];
                            $all_money_all += $lists[$lists_k]['all_money'];
                            $profit_all += $lists[$lists_k]['profit'];
                            $net_profit_add += $lists[$lists_k]['net_profit'];
                        }
                    }
                    if($status){
                        $cost_all = $list_v['sales_cost'];
                        $list[$list_k]['sales_cost'] = round($cost_all,$price_decimal);
                        $list[$list_k]['profit'] = round($list_v['all_money']-$cost_all,$price_decimal);
                        if($list_v['all_money']>0){
                            $list[$list_k]['profit_rate'] = round($list[$list_k]['profit']/$list_v['all_money']*100,2);
                        }else{
                            $list[$list_k]['profit_rate'] = 0;
                        }
                        $list[$list_k]['net_profit'] = round($list[$list_k]['profit']-$list_v['preferential_money']-$list_v['purchase_money'],$price_decimal);
                        if($list_v['all_money']>0){
                            $list[$list_k]['net_profit_rate'] = round($list[$list_k]['net_profit']/$list_v['all_money']*100,2);
                        }else{
                            $list[$list_k]['net_profit_rate'] = 0;
                        }
                    }
                }
            }

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                $row->getRelation('preparation')->visible(['nickname']);
                $row->getRelation('staffmember')->visible(['name']);
            }

            if($all_money_all>0){
                $profit_rate_all = round($profit_all/$all_money_all*100,2);
            }else{
                $profit_rate_all = 0;
            }
            if($all_money_all>0){
                $net_profit_rate_all = round($net_profit_add/$all_money_all*100,2);
            }else{
                $net_profit_rate_all = 0;
            }

            $result = array("total" => $list->total(), "rows" => $list->items(),'profit_rate_all'=>$profit_rate_all,'net_profit_rate_all'=>$net_profit_rate_all);

            return json($result);
        }
        return $this->view->fetch();
    }

}
