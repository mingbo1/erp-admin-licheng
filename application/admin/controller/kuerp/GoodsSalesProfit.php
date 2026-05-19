<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 销售利润管理
 *
 * @icon fa fa-circle-o
 */
class GoodsSalesProfit extends Backend
{

    /**
     * GoodsSalesProfit模型对象
     * @var \app\admin\model\kuerp\GoodsSalesProfit
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesProfit;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());

        $this->view->assign("customerType", $this->model->getCustomertypeList());
        $this->view->assign("customerList", $this->model->getCustomersList());
        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->view->assign("goodsType", $this->model->getGoodsTypeList());
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
        $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
        $number_decimal = Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value');
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $where = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['customerType'])){
                    $cid = Db::name('kuerp_customers')->where(['customertype_id'=>$filter['customerType']])->column('id');
                    $where['customers_id'] = ["in", $cid];
                }
                if(!empty($filter['customerList'])){
                    $where['customers_id'] = ['in',$filter['customerList']];
                }
            }
            $list = Db::name('kuerp_goods_sales')->where($where)->where(['examine_status'=>'1'])->group('customers_id')->field('customers_id')->select();

            $listss = [];
            foreach ($list as $list_k2=>$list_v2){
                $customers = Db::name('kuerp_customers')->where(['id'=>$list_v2['customers_id']])->field('customer_code,customertype_id,customer_name')->find();
                if($customers){
                    $c_type = Db::name('kuerp_customertype')->where(['id'=>$customers['customertype_id']])->value('type_name');
                }

                $info = Db::name('kuerp_goods_sales_profit')->where(['customers_id'=>$list_v2['customers_id']])->select();

                if($info){
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

                    $sort = 'purchase_time';$order='ASC';
                    $lists = Db::name('kuerp_goods_sales_profit')
                        ->where($map)
                        ->order($sort, $order)
                        ->select();
                    $goods_info = [];
                    $stores_ids = Db::name('kuerp_stores')->where(['status'=>'1'])->column('id');

                    $lists = \addons\kuerp\library\Goods::getPrList($lists,$stores_set,$stores_ids,$enable_time,$valuation_set,$goods_info,$price_taxes,$taxes_set,$price_decimal);

                    foreach ($info as $list_k=>$list_v){
                        $status = true;
                        foreach ($lists as $lists_k=>$lists_v){
                            if($list_v['id']==$lists_v['id']){
                                $status = false;
                                $info[$list_k]['pay_money'] = $lists[$lists_k]['pay_money'];
                                $info[$list_k]['sales_cost'] = $lists[$lists_k]['sales_cost'];
                                $info[$list_k]['profit'] = $lists[$lists_k]['profit'];
                                $info[$list_k]['profit_rate'] = $lists[$lists_k]['profit_rate'];
                                $info[$list_k]['net_profit'] = $lists[$lists_k]['net_profit'];
                                $info[$list_k]['net_profit_rate'] = $lists[$lists_k]['net_profit_rate'];
                            }
                        }
                        if($status){
                            $cost_all = $list_v['sales_cost'];
                            $info[$list_k]['sales_cost'] = round($cost_all,$price_decimal);
                            $info[$list_k]['profit'] = round($list_v['all_money']-$cost_all,$price_decimal);
                            if($list_v['all_money']>0){
                                $info[$list_k]['profit_rate'] = round($info[$list_k]['profit']/$list_v['all_money']*100,2);
                            }else{
                                $info[$list_k]['profit_rate'] = 0;
                            }
                            $info[$list_k]['net_profit'] = round($info[$list_k]['profit']-$list_v['preferential_money']-$list_v['purchase_money'],$price_decimal);
                            if($list_v['all_money']>0){
                                $info[$list_k]['net_profit_rate'] = round($info[$list_k]['net_profit']/$list_v['all_money']*100,2);
                            }else{
                                $info[$list_k]['net_profit_rate'] = 0;
                            }
                        }
                    }
                }

                $count = 0;
                $basic_nmber = 0;
                $all_money = 0;
                $tax_money = 0;
                $money_tax_price = 0;
                $cost_all = 0;
                foreach ($info as $info_k=>$info_v){
                    $basic_nmber += $info_v['number'];
                    $all_money += $info_v['all_money'];
                    $tax_money += $info_v['tax_money'];
                    $money_tax_price += $info_v['money_tax_price'];
                    $cost_all += $info_v['sales_cost'];
                    if($info_v['purchase_type']==1){
                        $count += 1;
                        /*$basic_nmber += $info_v['number'];
                        $all_money += $info_v['all_money'];
                        $tax_money += $info_v['tax_money'];
                        $money_tax_price += $info_v['money_tax_price'];
                        $cost_all += $info_v['sales_cost'];
                    }else{
                        $basic_nmber -= $info_v['number'];
                        $all_money -= $info_v['all_money'];
                        $tax_money -= $info_v['tax_money'];
                        $money_tax_price -= $info_v['money_tax_price'];
                        $cost_all -= $info_v['sales_cost'];*/
                    }
                }
                if($basic_nmber>0){
                    $unit_cost = round($cost_all/$basic_nmber,$price_decimal);
                }else{
                    $unit_cost = 0;
                }
                $profit = $all_money-$cost_all;
                if($profit>0){
                    $profit_rate = round($profit/$all_money*100,2);
                }else{
                    $profit_rate = 0;
                }
                $listss[] = [
                    'c_type_name' => isset($c_type)?$c_type:'',
                    'customer_code' => isset($customers['customer_code'])?$customers['customer_code']:'',
                    'customer_name' => isset($customers['customer_name'])?$customers['customer_name']:'',
                    'count' => $count,
                    'basic_nmber' => round($basic_nmber,$number_decimal),
                    'all_money' => round($all_money,$price_decimal),
                    'tax_money' => round($tax_money,$price_decimal),
                    'money_tax_price' => round($money_tax_price,$price_decimal),
                    'unit_cost' => round($unit_cost,$price_decimal),
                    'cost_all' => round($cost_all,$price_decimal),
                    'profit' => round($profit,$price_decimal),
                    'profit_rate' => round($profit_rate,4),
                ];
            }
            $lists = $listss;

            $result = array("total" => sizeof($lists), "rows" => $lists);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function index2()
    {
        $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
        $number_decimal = Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value');

        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $where = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['goodsType'])){
                    $cid = Db::name('kuerp_goods')->where(['goods_type_id'=>$filter['goodsType']])->column('goods_code');
                    $where['goods_code'] = ["in", $cid];
                }
                if(!empty($filter['goods_code'])){
                    $where['goods_code'] = ['in',$filter['goods_code']];
                }
                if(!empty($filter['id'])){
                    $where['id'] = ['<',$filter['id']];
                }
            }

            $sort = 'purchase_time';$order='ASC';
            $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
            $map = [];
            if(!empty($checkdate)){
                $map['purchase_time'] = ['>',$checkdate];
            }
            $lists = Db::name('kuerp_goods_sales_info')
                ->where($map)
                ->order($sort, $order)
                ->select();

            $list = Db::name('kuerp_goods_sales_info')
                ->where($where)
                ->order($sort, $order)
                ->select();

            if(!empty($list)){
                $all_money_all = 0;$profit_all = 0;$net_profit_add = 0;

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
                            $list[$list_k]['info_goods'] = $lists_v['info_goods'];
                            $all_money_all += $lists[$lists_k]['all_money'];
                            $profit_all += $lists[$lists_k]['profit'];
                            $net_profit_add += $lists[$lists_k]['net_profit'];
                        }
                    }
                    if($status){
                        $cost_all = (float)(Db::name('kuerp_goods_sales_profit')
                            ->where(['purchase_code'=>$list_v['purchase_code']])->value('sales_cost'));
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
                        $list[$list_k]['info_goods'] = [];
                    }
                }
                $profit_list = [];$cost_price_list = [];
                foreach ($list as $list_k=>$list_v){
                    $profit_list[$list_v['purchase_code']] = $list_v['sales_cost'];
                    $cost_price_list[$list_v['purchase_code']] = $list_v['info_goods'];
                }
                unset($list);


                $where = [];
                if(isset($data['filter'])){
                    $filter = (array)json_decode($data['filter'], true);
                    $filter = $filter ? $filter : [];
                    if(!empty($filter['purchase_time'])){
                        $str = explode(' - ',$filter['purchase_time']);
                        $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                    }
                    if(!empty($filter['goodsType'])){
                        $cid = Db::name('kuerp_goods')->where(['goods_type_id'=>$filter['goodsType']])->column('goods_code');
                        $where['goods_code'] = ["in", $cid];
                    }
                    if(!empty($filter['goods_code'])){
                        $where['goods_code'] = ['in',$filter['goods_code']];
                    }
                }
                $lists=[];
                $list = Db::name('kuerp_goods_sales_info')->where($where)->group('goods_code')->field('goods_code')->select();
                foreach ($list as $list_k=>$list_v){
                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('goods_code,goods_type_id,goods_name')->find();
                    if($goods){
                        $c_type = Db::name('kuerp_goods_type')->where(['id'=>$goods['goods_type_id']])->value('supplier_type_name');
                    }

                    $info = Db::name('kuerp_goods_sales_info')->where(['goods_code'=>$list_v['goods_code']])->select();
                    $count = 0;
                    $basic_nmber = 0;
                    $all_money = 0;
                    $tax_money = 0;
                    $money_tax_price = 0;
                    $cost_all = 0;
                    foreach ($info as $info_k=>$info_v){
                        if($info_v['purchase_type']==1){
                            $count += 1;
                            $basic_nmber += $info_v['basic_nmber'];
                            $all_money += $info_v['all_money'];
                            $tax_money += $info_v['tax_money'];
                            $money_tax_price += $info_v['money_tax_price'];
                            $cost_all += isset($profit_list[$info_v['purchase_code']])?$profit_list[$info_v['purchase_code']]:0;
                        }else{
                            $basic_nmber -= $info_v['basic_nmber'];
                            $all_money -= $info_v['all_money'];
                            $tax_money -= $info_v['tax_money'];
                            $money_tax_price -= $info_v['money_tax_price'];
                            $cost_price = isset($cost_price_list[$info_v['purchase_code']])?$cost_price_list[$info_v['purchase_code']]:[];
                            $cost_price = isset($cost_price[$info_v['goods_code']])?$cost_price[$info_v['goods_code']]:'';
                            if($cost_price===''){
                                $cost_price = Db::name('kuerp_goods')->where(['goods_code'=>$info_v['goods_code']])->value('purchase');
                            }
                            $cost_all -= $cost_price*$info_v['basic_nmber'];
                        }
                    }
                    if($basic_nmber>0){
                        $unit_cost = round($cost_all/$basic_nmber,$price_decimal);
                    }else{
                        $unit_cost = 0;
                    }
                    $profit = $all_money-$cost_all;
                    if($profit>0){
                        $profit_rate = round($profit/$all_money*100,2);
                    }else{
                        $profit_rate = 0;
                    }
                    $lists[] = [
                        'c_type_name' => isset($c_type)?$c_type:'',
                        'customer_code' => isset($goods['goods_code'])?$goods['goods_code']:'',
                        'customer_name' => isset($goods['goods_name'])?$goods['goods_name']:'',
                        'count' => $count,
                        'basic_nmber' => round($basic_nmber,$number_decimal),
                        'all_money' => round($all_money,$price_decimal),
                        'tax_money' => round($tax_money,$price_decimal),
                        'money_tax_price' => round($money_tax_price,$price_decimal),
                        'unit_cost' => round($unit_cost,$price_decimal),
                        'cost_all' => round($cost_all,$price_decimal),
                        'profit' => round($profit,$price_decimal),
                        'profit_rate' => round($profit_rate,$price_decimal),
                    ];
                }
            }else{
                $lists=[];
            }

            $result = array("total" => sizeof($lists), "rows" => $lists);

            return json($result);
        }
        return $this->view->fetch();
    }
}
