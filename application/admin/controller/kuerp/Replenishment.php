<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 智能补货
 *
 * @icon fa fa-circle-o
 */
class Replenishment extends Backend
{
    protected $noNeedRight = ['setdata'];
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

        $this->model = new \app\admin\model\kuerp\GoodsSalesOrder();
    }

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $data = input();
            $where = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['goods_code'])){
                    $where['goods_code'] = ["like", '%'.$filter['goods_code'].'%'];
                }
            }

            $sales_list = Db::name('kuerp_goods_sales_order_info')->where($where)->group('goods_code,goods_attr')->field('goods_code,goods_attr')->select();
            $lists = [];
            foreach ($sales_list as $sales_list_k=>$sales_list_v){
                $row = [];
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$sales_list_v['goods_code']])->find();
                $row['goods_id'] = $goods['id'];
                $row['goods_code'] = $goods['goods_code'];
                $row['goods_name'] = $goods['goods_code'].'_'.$goods['goods_name'];
                $row['buy_price'] = $goods['purchase'];

                if($goods['min_stock']<=0){
                    $goods['min_stock'] = Db::name('kuerp_goods_stock_warning')->where(['goods_code'=>$goods['goods_code']])->sum('min_stock');
                }
                $row['min_stock'] = $goods['min_stock'];

                $where = [];
                $where['goods_code'] = $goods['goods_code'];
                if($sales_list_v['goods_attr']>0){
                    $where['attribute_id'] = $sales_list_v['goods_attr'];
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->sum('stock');
                $row['stock'] = $stock;

                $row['attr_id'] = '';
                $row['attr_name'] = '';
                if($goods['auxiliary']==1){
                    //商品设置属性
                    $row['attr_id'] = $sales_list_v['goods_attr'];
                    $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$sales_list_v['goods_attr']])->value('attribute_id');
                    $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
                    if($attribute_ids){
                        $attribute_ids = explode('&',$attribute_ids);
                        $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                        $attribute_name = implode('/',$attribute);
                        $row['attr_name'] = $attribute_name;
                    }
                }

                //商品单位
                $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->field('id,name')->find();
                $row['goods_unit'] = $goods['unit_id'];
                $row['unit_name'] = isset($unit['name'])?$unit['name']:'';

                $list = Db::name('kuerp_goods_sales_order_info')->where(['goods_code'=>$sales_list_v['goods_code'],'goods_attr'=>$sales_list_v['goods_attr']])->select();
                $row['number'] = 0;
                foreach ($list as $list_k=>$list_v){
                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->find();
                    $number = $list_v['number'];
                    $row['number'] = $number;
                    if($goods['more_unit']==1 && $goods['unit_id']!=$list_v['goods_unit']){
                        $advance_rate = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$list_v['goods_unit']])->value('advance_rate');
                        if($advance_rate){
                            $row['number'] = $number*$advance_rate;
                        }
                    }
                }

                $list = Db::name('kuerp_goods_purchase_order_info')->where(['goods_code'=>$sales_list_v['goods_code'],'goods_attr'=>$sales_list_v['goods_attr']])->select();
                $row['buy_number'] = 0;
                foreach ($list as $list_k=>$list_v){
                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->find();
                    $number = $list_v['number'];
                    $row['buy_number'] = $number;
                    if($goods['more_unit']==1 && $goods['unit_id']!=$list_v['goods_unit']){
                        $advance_rate = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$list_v['goods_unit']])->value('advance_rate');
                        if($advance_rate){
                            $row['buy_number'] = $number*$advance_rate;
                        }
                    }
                }
                $row['now_buy_number'] = $row['number']-$row['stock']-$row['buy_number']+$row['min_stock'];
                if($row['now_buy_number']<0){
                    $row['now_buy_number'] = 0;
                }
                $lists[] = $row;
            }
            $result = array("total" => sizeof($lists), "rows" => $lists);
            return json($result);
        }
        return $this->view->fetch();
    }

    public function setdata(){
        $params = $this->request->post("row/a");
        if ($params) {
            if($params[0]){
                $params = json_decode($params[0],true);
            }else{
                $params = [];
            }
            if(empty($params)){
                $this->error('参数错误');
            }else{
                Session::set('GoodsPurchaseOrder',serialize($params));
                $this->success();
            }
        }
    }
}
