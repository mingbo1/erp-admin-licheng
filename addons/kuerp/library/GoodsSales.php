<?php

namespace addons\kuerp\library;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
class GoodsSales
{
    //获取单位信息
    public static function getUnits($goods_code='',$store=''){
        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('id,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,warehousing,warehouse')->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = $_POST['goods_id'];
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])->field('unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,warehousing,warehouse')->find();
        }
        if(empty($row)){
            return [];
        }
        if($store){
            $stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$row['goods_code'],'stores_id'=>$store])->value('stock');
        }else{
            $stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$row['goods_code'],'stores_id'=>$row['manager_warehouse_id']])->value('stock');
        }
        if($row['more_unit']==1){
            $unit_ids_arr = Db::name('kuerp_unit_conversion')->where(['id'=>$row['more_unit_id']])->field('main_unit,vice_unit,advance_rate')->find();
            $unit_ids = [$unit_ids_arr['main_unit'],$unit_ids_arr['vice_unit']];
            $goods_price = Db::name('kuerp_goods_price')->where(['unit'=>['in',$unit_ids],'goods_id'=>$goods_id])->select();
        }else{
            $unit_ids = [$row['unit_id']];
        }
        $unit = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($goods_price)){
                foreach ($goods_price as $goods_price_k=>$goods_price_v){
                    if($unit_v['id']==$goods_price_v['unit']){
                        $unit[$unit_k]['is_default'] = $goods_price_v['is_default'];
                        if($unit_v['id']==$unit_ids_arr['vice_unit']){
                            $unit[$unit_k]['purchase'] = round(($goods_price_v['purchase']/$unit_ids_arr['advance_rate']),2);
                            $unit[$unit_k]['basic_nmber'] = $unit_ids_arr['advance_rate'];
                        }else{
                            $unit[$unit_k]['purchase'] = $goods_price_v['purchase'];
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $row['purchase'];
                }
            }
        }
        $data = [];
        foreach ($unit_ids as $unit_ids_k=>$unit_ids_v){
            foreach ($unit as $unit_k=>$unit_v){
                if($unit_ids_v==$unit_v['id']){
                    if($unit_v['is_default']==1){
                        $unit_v['stock'] = is_null($stock)?0:$stock;
                    }else{
                        $unit_v['stock'] = round($stock/$unit_v['basic_nmber'],2);
                    }
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode($data);
    }

    //获取单位价格信息
    public static function getUnit($goods_code='',$store='',$attr='',$customers_id=''){
        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');

        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])
                ->field('market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')
                ->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:0;
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])
                ->field('market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')
                ->find();
        }
        if(empty($row)){
            return [];
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
        $number_decimal = $setList['number_decimal']['value'];
        $price_decimal  = $setList['price_decimal']['value'];

        $field = 'market_sell_price';
        if($customers_id){
            $customers = Db::name('kuerp_customers')->where(['id'=>$customers_id])->find();
            if($customers){
                switch ($customers['customerlevel_id']){
                    case 1:
                        $field = 'market_sell_price';
                        break;
                    case 2:
                        $field = 'wholesale_price';
                        break;
                    case 3:
                        $field = 'vip_price';
                        break;
                    case 4:
                        $field = 'discount';
                        break;
                    case 5:
                        $field = 'discount2';
                        break;
                    default:
                        break;
                }
            }
        }

        $auxiliary = Db::name('kuerp_auxiliary')->where(['status'=>1])->column('name','id');
        $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id])->select();
        foreach ($goods_attr as $goods_attr_k=>$goods_attr_v){
            $aux_combination = Db::name('kuerp_aux_combination')->where(['id'=>$goods_attr_v['attribute_id']])->value('attr_ids');
            $goods_attr[$goods_attr_k]['attr_ids'] = [];
            $goods_attr[$goods_attr_k]['attr_name'] = '';
            if($aux_combination){
                $goods_attr[$goods_attr_k]['attr_id'] = $aux_combination;
                $aux_combination = explode('&',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_ids'] = $aux_combination;
                foreach ($aux_combination as $aux_combination_k=>$aux_combination_v){
                    $aux_combination[$aux_combination_k] = $auxiliary[$aux_combination_v];
                }
                $aux_combination = implode('/',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_name'] = $aux_combination;
            }
        }


        $where = [];
        $where['goods_id'] = $goods_id;
        if($store){
            $where['stores_id'] = $store;
        }else{
            $where['stores_id'] = $row['manager_warehouse_id'];
        }
        if($attr){
            //$attribute_id = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->value('attribute_id');
            $where['attribute_id'] = $attr;
        }
        $stock = Db::name('kuerp_goods_stock')->where($where)->value('stock');
        if($row['more_unit']==1){
            $unit = Db::name('kuerp_unit_conversion')->alias('uc')
                ->join('kuerp_unit u','u.id=uc.vice_unit')
                ->where(['uc.main_unit'=>$row['unit_id']])
                ->order('uc.advance_rate','ASC')
                ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                ->select();
            $unit_ids = [$row['unit_id']];
            foreach ($unit as $unit_k=>$unit_v){
                $unit_ids[] = $unit_v['id'];
            }
            $goods_price = Db::name('kuerp_goods_price')->where(['unit'=>['in',$unit_ids],'goods_id'=>$goods_id])->select();
            foreach ($goods_price as $goods_price_k=>$goods_price_v){
                foreach ($unit as $unit_k=>$unit_v){
                    if($goods_price_v['unit']==$row['unit_id']){
                        $goods_price[$goods_price_k]['advance_rate'] = 1;
                    }else if($goods_price_v['unit']==$unit_v['id']){
                        $goods_price[$goods_price_k]['advance_rate'] = $unit_v['advance_rate'];
                    }
                }
            }
        }else{
            $unit_ids = [$row['unit_id']];
        }

        $unit = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($goods_price)){
                foreach ($goods_price as $goods_price_k=>$goods_price_v){
                    if($unit_v['id']==$goods_price_v['unit']){
                        $unit[$unit_k]['is_default'] = $goods_price_v['is_default'];
                        if($field=='discount' || $field=='discount2'){
                            $unit[$unit_k]['purchase'] = round( ($goods_price_v['market_sell_price']*$goods_price_v[$field]/100),$price_decimal );
                        }else{
                            $unit[$unit_k]['purchase'] = $goods_price_v[$field];
                        }
                        if($goods_price_v['is_default']!=1){
                            $unit[$unit_k]['basic_nmber'] = $goods_price_v['advance_rate'];
                        }else{
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($row['auxiliary']==1 && !empty($attr)){
                    $purchase_data = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->find();
                    if($field=='discount' || $field=='discount2'){
                        $purchase = round( ($purchase_data['market_sell_price']*$purchase_data[$field]/100),$price_decimal );
                    }else{
                        $purchase = $purchase_data[$field];
                    }
                }else{
                    if($field=='discount' || $field=='discount2'){
                        $purchase = round( ($row['market_sell_price']*$row[$field]/100),$price_decimal );
                    }else{
                        $purchase = $row[$field];
                    }
                }
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $purchase;
                }
            }
            $unit[$unit_k]['purchase'] = isset($unit[$unit_k]['purchase'])?$unit[$unit_k]['purchase']:0;
            $unit[$unit_k]['basic_nmber'] = isset($unit[$unit_k]['basic_nmber'])?$unit[$unit_k]['basic_nmber']:1;
            $unit[$unit_k]['is_default'] = isset($unit[$unit_k]['is_default'])?$unit[$unit_k]['is_default']:2;
        }

        $set= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        foreach ($unit as $unit_k=>$unit_v){
            $tax = $set['value'];
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1){
                $unit[$unit_k]['tax'] = 1;
                $unit[$unit_k]['tax_rate'] = $tax;
            }else{
                $unit[$unit_k]['tax'] = 0;
                $unit[$unit_k]['tax_rate'] = 0;
            }
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes==1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']/(100+$tax)*100),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']),4);
            }elseif(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes!=1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*(100+$tax)/100),4);
            }else{
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']),4);
            }
        }
        $data = [];
        foreach ($unit_ids as $unit_ids_k=>$unit_ids_v){
            foreach ($unit as $unit_k=>$unit_v){
                if($unit_ids_v==$unit_v['id']){
                    if($unit_v['is_default']==1){
                        $unit_v['stock'] = is_null($stock)?0:$stock;
                    }else{
                        $unit_v['stock'] = round($stock/$unit_v['basic_nmber'],2);
                    }
                    $unit_v['tax_money'] = round(($unit_v['tax_included']-$unit_v['purchase']),4);
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode(['unit'=>$data,'attr'=>$goods_attr,'goods'=>$row]);
    }

    public static function formatInfo2(){
        $data = input();

        $row = Db::name('kuerp_goods')->where(['goods_code'=>$data['goods_code']])->field('batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id')->find();
        if(empty($row)){
            return [];
        }else{
            $goods_unit = $data['goods_unit'];
            if($row['more_unit']==1){//多单位，多属性以单位为准
                $goods_price = Db::name('kuerp_goods_price')->where(['goods_code'=>$data['goods_code'],'unit'=>$goods_unit])->find();
            }elseif ($row['more_unit']!=1 && $row['auxiliary']==1){//单一单位，多属性以属性价格为准
                $goods_price = Db::name('kuerp_goods_attr')->where(['id'=>$data['attr']])->find();
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
        $number_decimal = $setList['number_decimal']['value'];
        $price_decimal  = $setList['price_decimal']['value'];

        //税率
        $taxes= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        if(!isset($data['tax_rate']) || (isset($data['tax_rate']) && $data['tax_rate']==='')){
            $data['tax_rate'] = $taxes['value'];
        }
        $taxes_status = Db::name('kuerp_sets')->where(['name'=>'taxes'])->value('is_checked');//是否启用税金
        if($taxes_status!=1){
            $data['tax_rate'] = 0;
            $data['tax_money'] = 0;
            $data['tax_included'] = $data['unit_price'];
            $data['money_tax_price'] = $data['all_money'];
        }

        //需格式化字段数组
        $format = [
            'number'=>$number_decimal,
            'allow_stock'=>$number_decimal,
            'basic_nmber'=>$number_decimal,
            'unit_price'=>$price_decimal,
            'tax_included'=>$price_decimal,
            'discount'=>$price_decimal,
            'dis_money'=>$price_decimal,
            'all_money'=>$price_decimal,
            'tax_rate'=>$price_decimal,
            'tax_money'=>$price_decimal,
            'money_tax_price'=>$price_decimal,
            'advance_rate'=>$number_decimal,
        ];
        foreach ($data as $k=>$v){
            if(array_key_exists($k,$format)){
                $data[$k] = (float)$data[$k];
            }
        }

        $field = 'market_sell_price';
        $customers_id = isset($data['customers_id'])?$data['customers_id']:'';
        if($customers_id){
            $customers = Db::name('kuerp_customers')->where(['id'=>$customers_id])->find();
            if($customers){
                switch ($customers['customerlevel_id']){
                    case 1:
                        $field = 'market_sell_price';
                        break;
                    case 2:
                        $field = 'wholesale_price';
                        break;
                    case 3:
                        $field = 'vip_price';
                        break;
                    case 4:
                        $field = 'discount';
                        break;
                    case 5:
                        $field = 'discount2';
                        break;
                    default:
                        break;
                }
            }
        }

        $data['tax_included'] = isset($data['tax_included'])?$data['tax_included']:$data['unit_price']*(100+$data['tax_rate'])/100;
        $data['tax_rate'] = isset($data['tax_rate'])?$data['tax_rate']:0;
        $data['discount'] = isset($data['discount'])?$data['discount']:0;
        $data['dis_money'] = isset($data['dis_money'])?$data['dis_money']:0;

        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');//价格含税
        $now_field = $data['now_field'];
        switch ($now_field){
            case 'goods_unit':
                //商品单位
                if($taxes){
                    $data['tax_included'] = isset($goods_price[$field])?$goods_price[$field]:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price[$field])?$goods_price[$field]:$data['unit_price'];//单价
                    $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                }
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'attr':
                //商品属性
                if($taxes){
                    $data['tax_included'] = isset($goods_price[$field])?$goods_price[$field]:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price[$field])?$goods_price[$field]:$data['unit_price'];//单价
                    $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                }
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'number':
                //数量
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'unit_price':
                //单价
                $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_included':
                //含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_rate':
                //税率
                $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_money':
                //税额
                $data['money_tax_price'] = $data['all_money'] + $data['tax_money'];//价税合计减折扣额
                break;
            case 'discount':
                //折扣率
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'dis_money':
                //折扣额
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['discount'] = (100*$data['dis_money']+$data['dis_money']*$data['tax_rate'])/$data['money_tax_price'];//折扣率
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'all_money':
                //购货金额
                $data['money_tax_price'] = $data['all_money']*(100+$data['tax_rate'])/100;//价税合计
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                $money_tax_price = $data['money_tax_price']*(100+$data['discount'])/100;//价税合计未减折扣额
                $data['tax_included'] = $money_tax_price/$data['number'];//含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                break;
            case 'money_tax_price':
                //价税合计
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                $money_tax_price = $data['money_tax_price']*(100+$data['discount'])/100;//价税合计未减折扣额
                $data['tax_included'] = $money_tax_price/$data['number'];//含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                break;
            default:
                return [];
                break;
        }

        $data['advance_rate'] = 1;
        if($row['more_unit']==1){
            $advance_rate = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$row['more_unit_id'],'vice_unit'=>$goods_price['unit']])->value('advance_rate');
            if(!empty($advance_rate)){
                $data['advance_rate'] = $advance_rate;
            }
        }
        $data['basic_nmber'] = $data['number']*$data['advance_rate'];
        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
            }
        }

        if(isset($data['tax_rate'])){
            $data['tax_money'] = $data['money_tax_price']-$data['all_money'];
        }else{
            $data['tax_money'] = 0;
        }

        $arr = [];
        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);

                if($format[$data_k]>0){
                    $price = explode('.',$data[$data_k]);
                    if(isset($price[1])){
                        $cha = $format[$data_k]-strlen($price[1]);
                    }else{
                        $cha = $format[$data_k];
                        $data[$data_k] = $data[$data_k].'.';
                    }
                    $str = '';
                    for ($a=0;$a<$cha;$a++){
                        $str .= '0';
                    }
                    $data[$data_k] .= $str;
                }

            }
            $arr[] = ['key'=>$data_k,'value'=>$data[$data_k]];
        }
        return json_encode(['data'=>$arr]);
    }
    //获取批次
    public static function getBranch($goods_code=''){
        $data = input();
        $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('batch,shelf_life')->find();
        $goods_batch = Db::name('kuerp_goods_batch')->where(['goods_code'=>$data['goods_code']])->field('id,batch_name,manufacture,guarantee,validity')->select();
        foreach ($goods_batch as $goods_batch_k=>$goods_batch_v){
            $goods_batch[$goods_batch_k]['manufacture'] = date('Y-m-d',$goods_batch_v['manufacture']);
            $goods_batch[$goods_batch_k]['validity'] = date('Y-m-d',$goods_batch_v['validity']);
        }
        return json_encode(['goods'=>$row,'batch'=>$goods_batch]);
    }
    public static function getTime($manufacture='',$shelf_life='',$validity=''){
        if(!empty($manufacture) && !empty($shelf_life)){
            $validity = date('Y-m-d',strtotime($manufacture)+$shelf_life*86400);
        }elseif(!empty($validity) && !empty($shelf_life)){
            $manufacture = date('Y-m-d',strtotime($validity)-$shelf_life*86400);
        }elseif(!empty($validity) && !empty($manufacture)){
            $shelf_life = ceil((strtotime($validity)-strtotime($manufacture))/86400);
        }
        return json_encode(['manufacture'=>$manufacture,'shelf_life'=>$shelf_life,'validity'=>$validity]);
    }
    //获取客户收货地址
    public static function getReceivingAddress(){
        $customers_id = isset($_POST['customers_id'])?trim($_POST['customers_id']):0;
        if(empty($customers_id)){
            return '';
        }
        $address = Db::name('kuerp_customers')->where(['id'=>$customers_id])->value('getgoods_address');
        return (empty($address)?'':$address);
    }
}
