<?php

namespace addons\kuerp\library;

use think\Db;
/**
 * 结账/反结账
 *
 * @icon fa fa-circle-o
 */
class Checkout
{

    public static function checkOutGoods($start_time,$end_time){
        $sort = 'purchase_time';$order='ASC';
        $where = [];
        $where['purchase_time'] = ['BETWEEN',[$start_time+1,$end_time]];
        $lists = Db::name('kuerp_goods_sales_profit')
            ->where($where)
            ->order($sort, $order)
            ->select();

        $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');

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
            if($list_v['purchase_type']==1){
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
                                    ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
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
                                    ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
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
            }
            $lists[$list_k]['pay_money'] = $pay_money;
            $lists[$list_k]['sales_cost'] = round($cost_all,$price_decimal);
            $lists[$list_k]['profit'] = round($list_v['all_money']-$cost_all,$price_decimal);
            if($list_v['all_money']>0){
                $lists[$list_k]['profit_rate'] = round($lists[$list_k]['profit']/$list_v['all_money']*100,2);
            }else{
                $lists[$list_k]['profit_rate'] = 0;
            }
            $lists[$list_k]['net_profit'] = round($lists[$list_k]['profit']-$list_v['preferential_money']-$list_v['purchase_money'],$price_decimal);
            if($list_v['all_money']>0){
                $lists[$list_k]['net_profit_rate'] = round($lists[$list_k]['net_profit']/$list_v['all_money']*100,2);
            }else{
                $lists[$list_k]['net_profit_rate'] = 0;
            }
        }
        return $lists;
    }

    public static function checkOutGoodss($end_time){
        $stores_set= Db::name('kuerp_sets')->where(['name'=>'stores'])->value('is_checked');//是否分仓
        $valuation_set = 2;
        if($stores_set!=1){
            $stores_set = 0;
        }

        $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checkdate');
        if(empty($checkdate)){
            $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
            $start_time = strtotime($enable_time);
        }else{
            $start_time = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
            $start_time+=1;
        }

        $goods = Db::name('kuerp_goods')->where(['status'=>'1'])->field('id,goods_code,inventory,unit_id')->select();
        $sort = 'purchase_time';$order='ASC';
        foreach ($goods as $goods_k=>$goods_v){
            $goods[$goods_k]['sales_cost'] = 0;
            $goods[$goods_k]['basic_nmber'] = 0;
            $purchase = Db::name('kuerp_goods_purchase_info')->where(['goods_code'=>$goods_v['goods_code'],'purchase_time'=>['BETWEEN',[$start_time,$end_time]]])
                ->field('sum(all_money) as all_money,sum(basic_nmber) as basic_nmber')->find();
            $goods[$goods_k]['all_money'] = (float)$purchase['all_money'];
            $goods[$goods_k]['purchase_basic_nmber'] = (float)$purchase['basic_nmber'];

            $goods_info = [];
            $info = Db::name('kuerp_goods_sales_info')->where(['goods_code'=>$goods_v['goods_code'],'purchase_time'=>['BETWEEN',[$start_time,$end_time]]])
                ->order($sort, $order)
                ->field('pay_money,basic_nmber,goods_code,stores_id,purchase_time')->select();

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
                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_time')
                                ->field('purchase_time,sum(basic_nmber) as basic_nmber,sum(all_money) as all_money')
                                ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
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
                                ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
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

                    $where = [];
                    $where['goods_id'] = $goods_v['id'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($wheretime)){
                        $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                    }
                    //成本调整
                    $adjustment_money = 0;
                    $adjustment = Db::name('kuerp_adjustment_info')->where($where)->select();
                    foreach ($adjustment as $adjustment_k=>$adjustment_v){
                        $advance_rate = 1;//单位换算进率
                        if($adjustment_v['goods_unit']!=$goods_v['unit_id']){
                            $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods_v['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
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
                }else{
                    $purchase_info = $goods_info[$info_v['goods_code']];
                }

                $cost = \addons\kuerp\library\Goods::getCost_Price($info_v['goods_code'],'',$info_v['basic_nmber'],$purchase_info);

                if(isset($cost['purchase_info'])){
                    $goods_info[$info_v['goods_code']] = $cost['purchase_info'];
                    $purchase_info = $goods_info[$info_v['goods_code']];
                    $cost = $cost['cost'];
                }
                $cost_all = $cost*$info_v['basic_nmber'];
                $goods[$goods_k]['sales_cost'] += $cost_all;
                $goods[$goods_k]['basic_nmber'] += $info_v['basic_nmber'];
            }

            if(isset($purchase_info)){
                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                    if($purchase_info_v['basic_nmber']>0){
                        $goods[$goods_k]['purchase_info'] = $purchase_info_v;
                        break;
                    }
                }
            }
        }
        return $goods;
    }

    public static function checkOutGoodsss($end_time){
        $stores_set= Db::name('kuerp_sets')->where(['name'=>'stores'])->value('is_checked');//是否分仓
        $valuation_set = 1;
        if($stores_set!=1){
            $stores_set = 0;
        }

        $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checkdate');
        if(empty($checkdate)){
            $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
            $start_time = strtotime($enable_time);
        }else{
            $start_time = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
            $start_time+=1;
        }

        $goods = Db::name('kuerp_goods')->where(['status'=>'1'])->field('id,goods_code,inventory,unit_id')->select();
        $sort = 'purchase_time';$order='ASC';
        foreach ($goods as $goods_k=>$goods_v){
            $goods[$goods_k]['sales_cost'] = 0;
            $goods[$goods_k]['basic_nmber'] = 0;
            $purchase = Db::name('kuerp_goods_purchase_info')->where(['goods_code'=>$goods_v['goods_code'],'purchase_time'=>['BETWEEN',[$start_time,$end_time]]])
                ->field('sum(all_money) as all_money,sum(basic_nmber) as basic_nmber')->find();
            $goods[$goods_k]['all_money'] = (float)$purchase['all_money'];
            $goods[$goods_k]['purchase_basic_nmber'] = (float)$purchase['basic_nmber'];

            $goods_info = [];
            $info = Db::name('kuerp_goods_sales_info')->where(['goods_code'=>$goods_v['goods_code'],'purchase_time'=>['BETWEEN',[$start_time,$end_time]]])
                ->order($sort, $order)
                ->field('pay_money,basic_nmber,goods_code,stores_id,purchase_time')->select();

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
                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_time')
                                ->field('purchase_time,sum(basic_nmber) as basic_nmber,sum(all_money) as all_money')
                                ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
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
                                ->order(['purchase_time'=>'ASC','purchase_code'=>'ASC'])->select();
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

                    $where = [];
                    $where['goods_id'] = $goods_v['id'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($wheretime)){
                        $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                    }
                    //成本调整
                    $adjustment_money = 0;
                    $adjustment = Db::name('kuerp_adjustment_info')->where($where)->select();
                    foreach ($adjustment as $adjustment_k=>$adjustment_v){
                        $advance_rate = 1;//单位换算进率
                        if($adjustment_v['goods_unit']!=$goods_v['unit_id']){
                            $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods_v['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
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
                }else{
                    $purchase_info = $goods_info[$info_v['goods_code']];
                }

                $cost = \addons\kuerp\library\Goods::getCost_Price($info_v['goods_code'],'',$info_v['basic_nmber'],$purchase_info);

                if(isset($cost['purchase_info'])){
                    $goods_info[$info_v['goods_code']] = $cost['purchase_info'];
                    $purchase_info = $goods_info[$info_v['goods_code']];
                    $cost = $cost['cost'];
                }
                $cost_all = $cost*$info_v['basic_nmber'];
                $goods[$goods_k]['sales_cost'] += $cost_all;
                $goods[$goods_k]['basic_nmber'] += $info_v['basic_nmber'];
            }

            if(isset($purchase_info)){
                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                    if($purchase_info_v['basic_nmber']>0){
                        $goods[$goods_k]['purchase_info'] = $purchase_info_v;
                        break;
                    }
                }
            }
        }
        return $goods;
    }

    public static function checkOutTime($time){
        $checktime = (new \addons\kuerp\model\Checkout())->order(['checktime'=>'DESC'])->value('checktime');
        $checkdate = (new \addons\kuerp\model\Checkout())->order(['checktime'=>'DESC'])->value('checkdate');
        if(strpos($time,' - ') !== false){
            $time = strtotime($time);
        }
        if(strpos($time,'-') !== false){
            $time = strtotime($time);
        }
        if($time<=$checktime){
            return '不可操作小于结账日期'.$checkdate.'的数据';
        }
        return true;
    }
}
