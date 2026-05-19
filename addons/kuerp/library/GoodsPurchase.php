<?php

namespace addons\kuerp\library;

use app\admin\model\Admin;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
class GoodsPurchase
{
    //获取单位价格信息
    public static function getUnit($goods_code='',$store='',$attr='',$supplyer_code=''){
        $set= (new \addons\kuerp\model\Sets())->where(['name'=>'taxes'])->field('value,is_checked')->find();
        $taxes= (new \addons\kuerp\model\Sets())->where(['name'=>'price_taxes'])->value('is_checked');

        $row = (new \addons\kuerp\model\Goods())->where(['goods_code'=>$goods_code])->field('batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')->find();
        $goods_id = isset($row['id'])?$row['id']:0;
        if(empty($row)){
            return [];
        }
        if($supplyer_code){
            $suppliers = (new \addons\kuerp\model\Suppliers())->where(['supplyer_code'=>$supplyer_code])->value('vat_rate');
        }

        $auxiliary = (new \addons\kuerp\model\Auxiliary())->where(['status'=>1])->column('name','id');
        $goods_attr = (new \addons\kuerp\model\GoodsAttr())->where(['goods_id'=>$goods_id])->select();
        foreach ($goods_attr as $goods_attr_k=>$goods_attr_v){
            $aux_combination = (new \addons\kuerp\model\AuxCombination())->where(['id'=>$goods_attr_v['attribute_id']])->value('attr_ids');
            $goods_attr[$goods_attr_k]['attr_ids'] = [];
            $goods_attr[$goods_attr_k]['attr_name'] = '';
            if($aux_combination){
                $goods_attr[$goods_attr_k]['attr_id'] = $aux_combination;
                $aux_combination = explode('&',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_ids'] = $aux_combination;
                foreach ($aux_combination as $aux_combination_k=>$aux_combination_v){
                    $aux_combination[$aux_combination_k] = isset($auxiliary[$aux_combination_v])?$auxiliary[$aux_combination_v]:'';
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
            $where['attribute_id'] = $attr;
        }
        $stock = (new \addons\kuerp\model\GoodsStock())->where($where)->value('stock');
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
            $goods_price = (new \addons\kuerp\model\GoodsPrice())->where(['unit'=>['in',$unit_ids],'goods_id'=>$goods_id])->select();
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
        $unit = (new \addons\kuerp\model\Unit())->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($goods_price)){
                foreach ($goods_price as $goods_price_k=>$goods_price_v){
                    if($unit_v['id']==$goods_price_v['unit']){
                        $unit[$unit_k]['is_default'] = $goods_price_v['is_default'];
                        $unit[$unit_k]['purchase'] = $goods_price_v['purchase'];
                        if($goods_price_v['is_default']!=1){
                            $unit[$unit_k]['basic_nmber'] = $goods_price_v['advance_rate'];
                        }else{
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($row['auxiliary']==1 && !empty($attr)){
                    $purchase = (new \addons\kuerp\model\GoodsAttr())->where(['goods_id'=>$goods_id,'id'=>$attr])->value('purchase');
                }else{
                    $purchase = $row['purchase'];
                }
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $purchase;
                }
            }
        }
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($suppliers) && $suppliers!==''){
                $tax = $suppliers;
            }else{
                $tax = $set['value'];
            }
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1){
                $unit[$unit_k]['tax'] = 1;
                $unit[$unit_k]['tax_rate'] = $tax;
            }else{
                $unit[$unit_k]['tax'] = 0;
                $unit[$unit_k]['tax_rate'] = 0;
            }
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes==1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']/(100+$tax)*100),4);
                $unit[$unit_k]['tax_included'] = $unit_v['purchase'];
            }elseif(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes!=1){
                $unit[$unit_k]['purchase'] = $unit_v['purchase'];
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*(100+$tax)/100),4);
            }else{
                $unit[$unit_k]['purchase'] = $unit_v['purchase'];
                $unit[$unit_k]['tax_included'] = $unit_v['purchase'];
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
                    $unit_v['tax_money'] = round(($unit_v['tax_included']-$unit_v['purchase']),4);;
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode(['unit'=>$data,'attr'=>$goods_attr,'goods'=>$row]);
    }

}
