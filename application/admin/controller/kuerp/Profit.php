<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 利润表
 *
 * @icon fa fa-circle-o
 */
class Profit extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

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
        if ($this->request->isPost()) {
            $data = input();
            $start_time = '';$end_time = '';
            if(isset($data['purchase_time'])){
                if(!empty($data['purchase_time'])){
                    $str = explode(' - ',$data['purchase_time']);
                    $start_time = strtotime($str[0]);$end_time = strtotime($str[1]);
                }
            }
            if(empty($start_time)){
                $start_time = strtotime(date('Y-m-01'));
                $end_time = strtotime(date('Y-m-d',strtotime('+1 day')))>time()?time():strtotime(date('Y-m-d',strtotime('+1 day')));
                if($end_time>time()){
                    $end_time = time();
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
            $price_decimal  = $setList['price_decimal']['value'];

            $result = ['sale_money'=>'','sales_discount'=>'','collection_all'=>'',
                'cost_all'=>'','discount_all'=>'','payment_all'=>'',
                'surplus_loss'=>'','main_business'=>'','other_income'=>'','profit'=>''];
            foreach ($result as $result_k=>$result_v){
                $result[$result_k] = round($result_v,$price_decimal);
            }
            $goods = Db::name('kuerp_goods')->select();
            foreach ($goods as $goods_k=>$goods_v){
                $res = \addons\kuerp\library\Goods::getProfit($goods_v['goods_code'],$start_time,$end_time);
                foreach ($result as $result_k=>$result_v){
                    if($result_k=='other_income'){
                        $result[$result_k] = isset($res[$result_k])?(float)$res[$result_k]:0;
                    }elseif ($result_k=='profit'){
                        $result[$result_k] += isset($res[$result_k])?(float)$res[$result_k]:0;
                        $result[$result_k] -= isset($res['other_income'])?(float)$res['other_income']:0;
                    }else{
                        $result[$result_k] += isset($res[$result_k])?(float)$res[$result_k]:0;
                    }
                }
            }

            $where = [];
            $where['examine_status'] = '1';
            if(!empty($start_time) && !empty($end_time)){
                $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
            }
            $discount_s = Db::name('kuerp_goods_sales')->where(['purchase_type'=>1])->where($where)->sum('preferential_money');
            $discount_s2 = Db::name('kuerp_goods_sales')->where(['purchase_type'=>2])->where($where)->sum('preferential_money');
            $discount_s -= $discount_s2;
            $result['main_business'] = $result['main_business']-$discount_s;
            $result['profit'] = $result['profit']-$discount_s;
            $result['sales_discount'] = $discount_s;

            $result[$result_k] += isset($res['other_income'])?(float)$res['other_income']:0;

            $profit = $this->getProfit($start_time,$end_time);
            $result['cost_all'] = $profit['sales_cost'];
            $result['sale_money'] = $profit['all_money'];
            $result['main_business'] = round($profit['all_money']-$profit['sales_cost']-$result['sales_discount']-$result['collection_all'],$price_decimal);
            $result['profit'] = round($result['main_business']+$result['other_income'],$price_decimal);


            foreach ($result as $result_k=>$result_v){
                $result[$result_k] = round($result_v,$price_decimal);
            }

            return json(['start_time'=>date('Y-m-d',$start_time),'end_time'=>date('Y-m-d',$end_time),'data'=>$result]);
        }
        return $this->view->fetch();
    }

    public function getProfit($start_time,$end_time)
    {
        $sort = 'purchase_time';$order='ASC';
        $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
        $map = [];
        if(!empty($checkdate)){
            $map['purchase_time'] = ['>',$checkdate];
        }
        $lists = Db::name('kuerp_goods_sales_profit')
            ->where($map)
            ->order($sort, $order)
            ->select();

        $where = [];
        $where['purchase_time'] = ['BETWEEN',[$start_time,$end_time]];
        $list = Db::name('kuerp_goods_sales_profit')
            ->where($where)
            ->order($sort, $order)
            ->select();

        $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
        $all_money_all = 0;$profit_all = 0;$net_profit_add = 0;

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
        $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
        $stores_ids = Db::name('kuerp_stores')->where(['status'=>'1'])->column('id');
        $goods_info = [];
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

        $sales_cost = 0;$all_money = 0;
        foreach ($list as $list_k=>$list_v){
            $sales_cost += $list_v['sales_cost'];
            $all_money += $list_v['all_money'];
        }
        return ['sales_cost'=>$sales_cost,'all_money'=>$all_money];
    }

}
