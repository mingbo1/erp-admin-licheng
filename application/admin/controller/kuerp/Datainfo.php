<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use app\admin\model\User;
use app\admin\model\Admin;
use app\common\model\Attachment;
use fast\Date;
/**
 * 数据管理
 *
 * @icon fa fa-circle-o
 */
class Datainfo extends Backend
{

    /**
     * Accounts模型对象
     * @var \app\admin\model\kuerp\Accounts
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $storesList = (new \app\admin\model\kuerp\Stores())->select();
        $storesLists = [];
        foreach ($storesList as $storesList_k=>$storesList_v){
            $storesLists[] = ['name'=>$storesList_v['name'],'value'=>$storesList_v['id']];
        }
        $this->view->assign("storesList", $storesList);
        $this->assignconfig("storesList", $storesLists);

        $storesList = (new \app\admin\model\kuerp\GoodsType())->select();
        $storesLists = [];
        foreach ($storesList as $storesList_k=>$storesList_v){
            $storesLists[] = ['name'=>$storesList_v['supplier_type_name'],'value'=>$storesList_v['id']];
        }
        $this->assignconfig("goodsList", $storesLists);
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            try {
                Db::execute("SET @@sql_mode='';");
            } catch (\Exception $e) {

            }
            $column = [];
            $starttime = Date::unixtime('day', -6);
            $endtime = Date::unixtime('day', 0, 'end');

            $types = $this->request->post("types");
            $legend = '购货金额';
            $joinlist = Db("kuerp_goods_purchase")->where('purchase_time', 'between time', [$starttime, $endtime])
                ->where(['examine_status'=>'1'])
                ->field('SUM(real_money) AS nums,DATE_FORMAT(FROM_UNIXTIME(purchase_time), "%Y-%m-%d") AS join_date')
                ->group('join_date')
                ->select();
            if ($types==2){
                $joinlist = Db("kuerp_goods_sales")->where('purchase_time', 'between time', [$starttime, $endtime])
                    ->where(['examine_status'=>'1'])
                    ->field('SUM(real_money) AS nums,DATE_FORMAT(FROM_UNIXTIME(purchase_time), "%Y-%m-%d") AS join_date')
                    ->group('join_date')
                    ->select();
                $legend = '销货金额';
            }elseif ($types==3){
                $list = Db::name('kuerp_stores')->where(['status'=>'1'])->field('id,name')->select();
                foreach ($list as $list_k=>$list_v){
                    $list[$list_k]['value'] = Db::name('kuerp_goods_stock')->where(['stores_id'=>$list_v['id']])->sum('stock');
                    if(isset($list[$list_k]['id'])){
                        unset($list[$list_k]['id']);
                    }
                }
                $result = array('title'=>'库存统计','legend'=>'库存数量',"data" => $list);
                return json($result);
            }elseif ($types==4){
                $list = Db::name('kuerp_accounts')->field('account_name as name,balance as value')->select();
                $result = array('title'=>'账户统计','legend'=>'资金',"data" => $list);
                return json($result);
            }
            for ($time = $starttime; $time <= $endtime;) {
                $column[] = date("Y-m-d", $time);
                $time += 86400;
            }
            $list = array_fill_keys($column, 0);
            foreach ($joinlist as $k => $v) {
                $list[$v['join_date']] = $v['nums'];
            }
            foreach ($list as $list_k=>$list_v){
                if(empty($list_k)){
                    unset($list[$list_k]);
                }
            }
            $result = array("legend" => $legend, "column" => array_keys($list), "data" => array_values($list));
            return json($result);
        }

        $time_arr = $this->getTimestamp('nowmonth');
        $code = Db::name('kuerp_goods_purchase')->where(['examine_status'=>'1'])->where(['purchase_time'=>['BETWEEN',[$time_arr['start'],$time_arr['end']]]])->column('purchase_code');
        $purchase_num = Db::name('kuerp_goods_purchase_info')->where(['purchase_code'=>['in',$code]])->sum('basic_nmber');
        $purchase_mon = Db::name('kuerp_goods_purchase')->where(['purchase_code'=>['in',$code]])->sum('real_money');
        $this->view->assign('purchase_num',round($purchase_num,2));
        $this->view->assign('purchase_mon',round($purchase_mon,2));

        $code = Db::name('kuerp_goods_sales')->where(['examine_status'=>'1'])->where(['purchase_time'=>['BETWEEN',[$time_arr['start'],$time_arr['end']]]])->column('purchase_code');
        $sales_num = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$code]])->sum('basic_nmber');
        $sales_mon = Db::name('kuerp_goods_sales')->where(['purchase_code'=>['in',$code]])->sum('real_money');
        $this->view->assign('sales_num',round($sales_num,2));
        $this->view->assign('sales_mon',round($sales_mon,2));

        $stock_num = Db::name('kuerp_goods')->where(['status'=>1])->sum('inventory');
        $this->view->assign('stock_num',round($stock_num,2));
        $goods = Db::name('kuerp_goods')->where(['status'=>1])->column('goods_code');
        $stock_mon = 0;


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

        $goods_info = [];
        foreach ($lists as $list_k=>$list_v){
            $cost_all = 0;$pay_money = 0;
            $info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$list_v['purchase_code']])->field('pay_money,basic_nmber,goods_code,stores_id,purchase_time')->select();
            foreach ($info as $info_k=>$info_v){

                if(!isset($goods_info[$info_v['goods_code']])){
                    $where = [];
                    $stores_ids = Db::name('kuerp_stores')->where(['status'=>'1'])->column('id');
                    if($stores_set==1){
                        $where['stores_id'] = ['in',$stores_ids];
                    }else{
                        $where['stores_id'] = $info_v['stores_id'];
                    }
                    $where['goods_code'] = $info_v['goods_code'];

                    $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
                    if(empty($checkdate)){
                        $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
                        $start_time = strtotime($enable_time);

                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_time')
                                ->field('purchase_time,sum(basic_nmber) as basic_nmber,sum(all_money) as all_money')
                                ->order(['purchase_time'=>'ASC'])->select();
                        }else{
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->where($where)
                                ->field('purchase_time,basic_nmber,all_money')
                                ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
                        }
                    }else{
                        $checkout_id = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('id');
                        $purchase_more = Db::name('kuerp_checkout_info')->where([
                            'checkout_id'=>$checkout_id,
                            'goods_code'=>$info_v['goods_code'],
                        ])->find();
                        $purchase_infos = Db::name('kuerp_goods_purchase')->where(['id'=>$purchase_more['purchase_id']])->find();

                        $start_time = $checkdate;
                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_time')
                                ->field('purchase_time,sum(basic_nmber) as basic_nmber,sum(all_money) as all_money')
                                ->order(['purchase_time'=>'ASC'])->select();
                            if(!empty($purchase_more)){
                                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                    if($purchase_info_v['purchase_time']==$purchase_more['purchase_time']){
                                        $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$purchase_more['av_basic_nmber'];
                                        $purchase_info[$purchase_info_k]['all_money'] = (float)$purchase_more['av_all_money'];
                                    }
                                }
                            }
                        }else{
                            if(empty($purchase_more)){
                                $purchase_info = Db::name('kuerp_goods_purchase_info')->where($where)
                                    ->field('purchase_time,basic_nmber,all_money')
                                    ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
                            }else{
                                $where['purchase_code'] = ['>=',$purchase_infos['purchase_code']];
                                $purchase_info = Db::name('kuerp_goods_purchase_info')->where($where)
                                    ->field('id,purchase_time,basic_nmber,all_money')
                                    ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
                                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                    if($purchase_info_v['id']==$purchase_more['purchase_id']){
                                        $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$purchase_more['basic_nmber'];
                                        $purchase_info[$purchase_info_k]['all_money'] = (float)$purchase_more['all_money'];
                                    }
                                }
                            }
                        }
                    }


                    $where = [];
                    $where['goods_code'] = $info_v['goods_code'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($wheretime)){
                        $where['createtime'] = ['between',[$wheretime['start_time'],time()]];
                    }
                    //期初
                    $period = Db::name('kuerp_goods_period')->where($where)->field('period as basic_nmber,price as all_money,createtime as purchase_time')->find();
                    if(!empty($period)){
                        $period['purchase_time'] = strtotime(date('Y-m-d',$period['purchase_time']));
                        if($valuation_set==1){
                            $status = true;
                            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                if($purchase_info_v['purchase_time']==$period['purchase_time']){
                                    $status = false;
                                    $purchase_info[$purchase_info_k]['basic_nmber'] += $period['basic_nmber'];
                                    $purchase_info[$purchase_info_k]['all_money'] += $period['all_money'];
                                }
                            }
                            if($status){
                                $purchase_info[] = $period;
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }else{
                            $purchase_infos = [];
                            $purchase_infos[] = $period;
                            foreach ($purchase_info as $k=>$v){
                                $purchase_infos[] = $v;
                            }
                            $purchase_info = $purchase_infos;
                        }
                    }

                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$info_v['goods_code']])->field('id,unit_id')->find();
                    if($goods){
                        $where = [];
                        $where['goods_id'] = $goods['id'];
                        $where['stores_id'] = ['in',$stores_ids];
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        //成本调整
                        $adjustment_money = 0;
                        $adjustment = Db::name('kuerp_adjustment_info')->where($where)->select();
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            $advance_rate = 1;//单位换算进率
                            if($adjustment_v['goods_unit']!=$goods['unit_id']){
                                $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
                                if(!empty($unit) && $unit>0){
                                    $advance_rate = $unit;
                                }
                            }
                            $money = $adjustment_v['money']/$advance_rate;//换算后的调整成本
                            $adjustment[$adjustment_k]['purchase_time'] = strtotime(date('Y-m-d',$adjustment_v['purchase_time']));
                            $adjustment[$adjustment_k]['all_money'] = $adjustment_v['money'];
                        }
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                if($purchase_info_v['purchase_time']==$adjustment_v['purchase_time']){
                                    $purchase_info[$purchase_info_k]['all_money'] += $adjustment_v['all_money'];
                                }
                            }
                        }
                        //成本调整结束
                    }
                }else{
                    $purchase_info = $goods_info[$info_v['goods_code']];
                }

                $cost = \addons\kuerp\library\Goods::getCost_Price($info_v['goods_code'],'',$info_v['basic_nmber'],$purchase_info);

                if(isset($cost['purchase_info'])){
                    $goods_info[$info_v['goods_code']] = $cost['purchase_info'];
                    $purchase_info = $goods_info[$info_v['goods_code']];
                    $cost = $cost['cost'];
                }
                $cost_all += $cost*$info_v['basic_nmber'];
                $pay_money += $info_v['pay_money'];
            }
            $lists[$list_k]['pay_money'] = $pay_money;
            if($list_v['purchase_type']==2){
                $cost_all = -$cost_all;
            }
            $lists[$list_k]['sales_cost'] = round($cost_all,$price_decimal);
        }
        $sales_cost = 0;
        foreach ($list as $list_k=>$list_v){
            $status = true;
            foreach ($lists as $lists_k=>$lists_v){
                if($list_v['id']==$lists_v['id']){
                    $status = false;
                    $sales_cost += $lists[$lists_k]['sales_cost'];
                }
            }
            if($status){
                $sales_cost += $list_v['sales_cost'];
            }
        }
        $purchase_real_money = Db::name('kuerp_goods_purchase')->where(['examine_status'=>'1'])->sum('real_money');
        $purchase_preferential_money = Db::name('kuerp_goods_purchase')->where(['examine_status'=>'1'])->sum('preferential_money');
        $purchase_money = round($purchase_real_money+$purchase_preferential_money-$sales_cost,$price_decimal);
        $this->view->assign('purchase_money',$purchase_money);
        $this->view->assign('stock_mon',$sales_cost);

        $customers_mon = Db::name('kuerp_customers')->sum('need_receive_account');
        $suppliers_mon = Db::name('kuerp_suppliers')->sum('need_give_account');
        $this->view->assign('customers_mon',round($customers_mon,2));
        $this->view->assign('suppliers_mon',round($suppliers_mon,2));

        $accounts_type1 = Db::name('kuerp_accounts')->where(['account_type_id'=>'1'])->sum('balance');
        $accounts_type2 = Db::name('kuerp_accounts')->where(['account_type_id'=>'2'])->sum('balance');
        $this->view->assign('accounts_type1',round($accounts_type1,$price_decimal));
        $this->view->assign('accounts_type2',round($accounts_type2,$price_decimal));

        return $this->view->fetch();
    }

    public function stockworing(){
        if ($this->request->isAjax()) {
            $data = input();
            $where = [];
            $where['stock_warning'] = '1';
            $map = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['goods_code'])){
                    $where['goods_code'] = ["like", '%'.$filter['goods_code'].'%'];
                }
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['goods_name'])){
                    $where['goods_name'] = ["like", '%'.$filter['goods_name'].'%'];
                }
                if(!empty($filter['type_id'])){
                    $where['goods_type_id'] = ["in", $filter['type_id']];
                }
                if(!empty($filter['stores_id'])){
                    $map['sw.stores_id'] = ["in", $filter['stores_id']];
                }
            }

            $list = Db::name('kuerp_goods')
                ->where($where)
                ->select();

            $datas = [];
            foreach ($list as $list_k=>$list_v){
                $unit = Db::name('kuerp_unit')->where(['id'=>$list_v['unit_id']])->field('id,name')->find();
                $list[$list_k]['unit'] = $unit;
                if($unit){
                    $unit_name = $unit['name'];
                    $rowadvance_rate = '1';
                    if($list_v['more_unit']==1){
                        $more_unit = Db::name('kuerp_unit_conversion')->alias('uc')
                            ->join('kuerp_unit u','u.id=uc.vice_unit')
                            ->where(['uc.main_unit'=>$unit['id']])
                            ->order('uc.advance_rate','ASC')
                            ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                            ->select();
                        foreach ($more_unit as $unit_k=>$unit_v){
                            $unit_name .= ','.$unit_v['name'];
                            $rowadvance_rate .= ':'.$unit_v['advance_rate'];
                        }
                        $list[$list_k]['unitconversion'] = $unit_name.'('.$rowadvance_rate.')';
                    }else{
                        $list[$list_k]['unitconversion'] = '';
                    }
                }
                if(empty($unit)){
                    $list[$list_k]['unit'] = ['name'=>''];
                }
                $list[$list_k]['goodstype'] = Db::name('kuerp_goods_type')->where(['id'=>$list_v['goods_type_id']])->field('supplier_type_name')->find();
                $list[$list_k]['stores'] = Db::name('kuerp_stores')->where(['id'=>$list_v['manager_warehouse_id']])->field('name')->find();
                $list[$list_k]['stock'] = $list_v['inventory'];
                if($list_v['branch']==1){
                    $lists = Db::name('kuerp_goods_stock_warning')->alias('sw')
                        ->join('kuerp_stores ks','ks.id=sw.stores_id')->where($map)->where(['sw.goods_code'=>$list_v['goods_code']])
                        ->field('sw.stores_id,sw.min_stock,sw.max_stock,ks.name')->select();
                    foreach ($lists as $lists_k=>$lists_v){
                        $stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$list_v['goods_code'],'stores_id'=>$lists_v['stores_id']])->value('stock');
                        $datas[] = [
                            'goodstype' => $list[$list_k]['goodstype'],
                            'goods_code' => $list_v['goods_code'],
                            'goods_name' => $list_v['goods_name'],
                            'goods_barcode' => $list_v['goods_barcode'],
                            'goods_image' => $list_v['goods_image'],
                            'more_unit' => $list[$list_k]['more_unit'],
                            'unit' => $list[$list_k]['unit'],
                            'unitconversion' => $list[$list_k]['unitconversion'],
                            'stores' => ['name'=>$lists_v['name']],
                            'min_stock' => $lists_v['min_stock'],
                            'max_stock' => $lists_v['max_stock'],
                            'stock' => (float)$stock,
                        ];
                    }
                    unset($list[$list_k]);
                }else{
                    $list[$list_k]['stores'] = ['name'=>'全部仓库'];
                }
            }
            foreach ($datas as $datas_k=>$datas_v){
                $list[] = $datas_v;
            }
            foreach ($list as $list_k=>$list_v){
                if($list_v['stock']>=$list_v['min_stock'] && $list_v['stock']<=$list_v['max_stock']){
                    unset($list[$list_k]);
                }
            }
            // 按预警类型筛选
            if(!empty($filter['warn_type'])){
                foreach ($list as $list_k=>$list_v){
                    if($filter['warn_type'] == 'below' && $list_v['stock'] >= $list_v['min_stock']){
                        unset($list[$list_k]);
                    }
                    if($filter['warn_type'] == 'above' && $list_v['stock'] <= $list_v['max_stock']){
                        unset($list[$list_k]);
                    }
                }
            }
            $result = array("total" => sizeof($list), "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    private function getTimestamp($name = 'today', $param = '')
    {
        $nowtimr = strtotime(date('Y-m-d H') . ':' . (date('i') - date('i') % 10));
        switch ($name) {
            case 'now':
                $timeamp['start'] = $nowtimr - 600;
                $timeamp['end'] = $nowtimr;
                $timeamp['time'] = date('H:i', $timeamp['end']);
                break;
            case 'ten':
                $timeamp['start'] = $nowtimr - 1200;
                $timeamp['end'] = $nowtimr - 600;
                $timeamp['time'] = date('H:i', $timeamp['end']);
                break;
            case 'twenty':
                $timeamp['start'] = $nowtimr - 1800;
                $timeamp['end'] = $nowtimr - 1200;
                $timeamp['time'] = date('H:i', $timeamp['end']);
                break;
            case 'thirty':
                $timeamp['start'] = $nowtimr - 2400;
                $timeamp['end'] = $nowtimr - 1800;
                $timeamp['time'] = date('H:i', $timeamp['end']);
                break;
            case 'forty':
                $timeamp['start'] = $nowtimr - 3000;
                $timeamp['end'] = $nowtimr - 2400;
                $timeamp['time'] = date('H:i', $timeamp['end']);
                break;
            case 'fifty':
                $timeamp['start'] = $nowtimr - 3600;
                $timeamp['end'] = $nowtimr - 3000;
                $timeamp['time'] = date('H:i', $timeamp['end']);
                break;
            case 'today'://今天
                $timeamp['time'] = date('m-d');
                $timeamp['start'] = strtotime(date('Y-m-d'));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('+1 day')))>time()?time():strtotime(date('Y-m-d',strtotime('+1 day')));
                break;
            case 'yesterday'://昨天
                $timeamp['time'] = date('m-d',strtotime('-1 day'));
                $timeamp['start'] = strtotime(date('Y-m-d',strtotime('-1 day')));
                $timeamp['end'] = strtotime(date('Y-m-d'));
                break;
            case 'beforyesterday'://前天
                $timeamp['time'] = date('m-d',strtotime('-2 day'));
                $timeamp['start'] = strtotime(date('Y-m-d',strtotime('-2 day')));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('-1 day')));
                break;
            case 'threeday'://前3天
                $timeamp['time'] = date('m-d',strtotime('-3 day'));
                $timeamp['start'] = strtotime(date('Y-m-d',strtotime('-3 day')));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('-2 day')));
                break;
            case 'fourday'://前4天
                $timeamp['time'] = date('m-d',strtotime('-4 day'));
                $timeamp['start'] = strtotime(date('Y-m-d',strtotime('-4 day')));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('-3 day')));
                break;
            case 'fifday'://前5天
                $timeamp['time'] = date('m-d',strtotime('-5 day'));
                $timeamp['start'] = strtotime(date('Y-m-d',strtotime('-5 day')));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('-4 day')));
                break;
            case 'sixday'://前6天
                $timeamp['time'] = date('m-d',strtotime('-6 day'));
                $timeamp['start'] = strtotime(date('Y-m-d',strtotime('-6 day')));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('-5 day')));
                break;
            case 'beforweek'://本周
                $timeamp['start'] = strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y"))));
                $timeamp['end'] = strtotime(date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), date("d") - date("w") + 7, date("Y"))));
                break;
            case 'nowmonth'://本月
                $timeamp['start'] = strtotime(date('Y-m-01'));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('+1 day')))>time()?time():strtotime(date('Y-m-d',strtotime('+1 day')));
                break;
            case 'permonth'://上月
                $timeamp['start'] = strtotime(date('Y-m-01', strtotime('-1 month')));
                $timeamp['end'] = strtotime(date('Y-m-01'));
                break;
            case 'preweek'://上周 注意我们是从周一开始算
                $timeamp['start'] = strtotime(date('Y-m-d', strtotime('-2 week Monday')));
                $timeamp['end'] = strtotime(date('Y-m-d', strtotime('-1 week Monday +1 day')));
                break;
            case 'nowweek'://本周
                $timeamp['start'] = strtotime(date('Y-m-d', strtotime('-1 week Monday')));
                $timeamp['end'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                break;
            case 'preday'://30
                $timeamp['start'] = strtotime(date('Y-m-d'), strtotime($param . ' day'));
                $timeamp['end'] = strtotime(date('Y-m-d'));
                break;
            case 'nextday'://30
                $timeamp['start'] = strtotime(date('Y-m-d'));
                $timeamp['end'] = strtotime(date('Y-m-d'), strtotime($param . ' day'));
                break;
            case 'preyear'://去年
                $timeamp['start'] = strtotime(date('Y-01-01', strtotime('-1 year')));
                $timeamp['end'] = strtotime(date('Y-12-31', strtotime('-1 year')));
                break;
            case 'nowyear'://今年
                $timeamp['start'] = strtotime(date('Y-01-01'));
                $timeamp['end'] = strtotime(date('Y-m-d',strtotime('+1 day')))>time()?time():strtotime(date('Y-m-d',strtotime('+1 day')));
                break;
            case 'quarter'://季度
                $quarter = empty($param) ? ceil((date('n'))/3) : $param;//获取当前季度
                $timeamp['start'] = mktime(0, 0, 0,$quarter*3-2,1,date('Y'));
                $timeamp['end'] = mktime(0, 0, 0,$quarter*3+1,1,date('Y'))>time()?time():mktime(0, 0, 0,$quarter*3+1,1,date('Y'));
                break;
            case 'lastquarter'://季度
                $quarter = empty($param) ? ceil((date('n'))/3) : $param;//获取当前季度
                $quarter -= 1;
                if($quarter>0){
                    $timeamp['start'] = mktime(0, 0, 0,$quarter*3-2,1,date('Y'));
                    $timeamp['end'] = mktime(0, 0, 0,$quarter*3+1,1,date('Y'))>time()?time():mktime(0, 0, 0,$quarter*3+1,1,date('Y'));
                }else{
                    $quarter = 4;
                    $timeamp['start'] = mktime(0, 0, 0,$quarter*3-2,1,(date('Y')-1));
                    $timeamp['end'] = mktime(0, 0, 0,$quarter*3+1,1,(date('Y')-1))>time()?time():mktime(0, 0, 0,$quarter*3+1,1,(date('Y')-1));
                }
                break;
            case 'lastyeayquarter'://去年同季度
                $quarter = empty($param) ? ceil((date('n'))/3) : $param;//获取当前季度
                $timeamp['start'] = mktime(0, 0, 0,$quarter*3-2,1,(date('Y')-1));
                $timeamp['end'] = mktime(0, 0, 0,$quarter*3+1,1,(date('Y')-1))>time()?time():mktime(0, 0, 0,$quarter*3+1,1,(date('Y')-1));
                break;
            default:
                $timeamp['start'] = strtotime(date('Y-m-d'));
                $timeamp['end'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                break;
        }
        if($timeamp['end']>time()){
            $timeamp['end'] = time();
        }
        return $timeamp;
    }
}
