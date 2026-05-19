<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 以销定购
 *
 * @icon fa fa-circle-o
 */
class Purchaseby extends Backend
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
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['purchase_code'])){
                    $where['purchase_code'] = ["like", '%'.$filter['purchase_code'].'%'];
                }
            }
            $lists = [];
            $list = Db::name('kuerp_goods_sales_order_info')->where($where)->select();
            foreach ($list as $list_k=>$list_v){
                $row = [];
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->find();
                $row['goods_id'] = $goods['id'];
                $row['goods_code'] = $goods['goods_code'];
                $row['goods_name'] = $goods['goods_code'].'_'.$goods['goods_name'];
                $row['number'] = $list_v['number'];

                $row['attr_id'] = '';
                $row['attr_name'] = '';
                if($goods['auxiliary']==1){
                    //商品设置属性
                    $row['attr_id'] = $list_v['goods_attr'];
                    $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['goods_attr']])->value('attribute_id');
                    $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
                    if($attribute_ids){
                        $attribute_ids = explode('&',$attribute_ids);
                        $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                        $attribute_name = implode('/',$attribute);
                        $row['attr_name'] = $attribute_name;
                    }
                }

                //商品单位
                $unit = Db::name('kuerp_unit')->where(['id'=>$list_v['goods_unit']])->field('id,name')->find();
                $row['goods_unit'] = $list_v['goods_unit'];
                $row['unit_name'] = isset($unit['name'])?$unit['name']:'';

                //仓库
                $stores = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('id,name')->find();
                $row['stores_id'] = $list_v['stores_id'];
                $row['stores_name'] = isset($stores['name'])?$stores['name']:'';

                //客户
                $customers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->field('id,customer_name,customer_code')->find();
                $row['customers_id'] = $list_v['customers_id'];
                $row['customers_name'] = isset($customers['customer_name'])?$customers['customer_name']:'';
                $row['customer_code'] = isset($customers['customer_code'])?$customers['customer_code']:'';

                $row['purchase_code'] = $list_v['purchase_code'];
                $row['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);

                $goods_sales_info = Db::name('kuerp_goods_sales_info')->where(['purchase_order_id'=>$list_v['purchase_code']])->find();
                if($goods_sales_info){
                    $row['sales_code'] = '';
                    $row['sales_time'] = '';
                    $row['sales_number'] = '';
                    $row['sure_number'] = '';
                    $row['sales_purchase_code'] = '';
                    $row['sales_purchase_number'] = '';
                    $row['warehouse'] = '';
                    $row['buy_number'] = '';
                    if($goods_sales_info['examine_status']==1){
                        $row['sales_code'] = $goods_sales_info['purchase_code'];
                        $row['sales_time'] = date('Y-m-d',$list_v['purchase_time']);
                        $sales_number = $goods_sales_info['number'];
                        $row['sales_number'] = $sales_number;
                        $warehouse = $list_v['number']-$goods_sales_info['number'];
                        $row['warehouse'] = $warehouse;
                        $row['buy_number'] = $warehouse;
                    }else{
                        $warehouse = $list_v['number'];
                        $row['warehouse'] = $warehouse;
                        $row['buy_number'] = $warehouse;
                    }
                }

                $order = Db::name('kuerp_goods_purchase_order_info')->where(['sales_order_id'=>$list_v['purchase_code']])->find();
                if($order){
                    $examine_status = Db::name('kuerp_goods_purchase_order')->where(['purchase_code'=>$order['purchase_code']])->value('examine_status');
                    if($examine_status==1){
                        $row['sales_time'] = date('Y-m-d',$order['purchase_time']);
                        $row['sales_purchase_code'] = $order['purchase_code'];
                        $row['sales_number'] = $order['number'];
                        if(isset($row['buy_number'])){
                            $row['buy_number'] -= $order['number'];
                        }
                    }
                }

                $row['now_buy_number'] = '';
                $row['buy_price'] = $goods['purchase'];
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
