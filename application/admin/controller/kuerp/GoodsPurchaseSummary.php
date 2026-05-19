<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 采购汇总管理
 *
 * @icon fa fa-circle-o
 */
class GoodsPurchaseSummary extends Backend
{
    
    /**
     * GoodsPurchaseSummary模型对象
     * @var \app\admin\model\kuerp\GoodsPurchaseSummary
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsPurchaseSummary;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
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
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $where = [];
            $searchdata = json_decode(input('filter'),true);
            if($searchdata){
                foreach ($searchdata as $k => &$v) {
                    if(!empty($v)){
                        if(strpos($v,' - ') !== false && strpos($k,'time') !== false){
                            $str = explode(' - ',$v);
                            $where['i.'.$k] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                        }else{
                            $where['i.'.$k] = ["LIKE", "%{$v}%"];
                        }
                    }
                }
            }
            $field = empty(input('field'))?'goods_code':trim(input('field'));
            $order = empty(input('sort'))?'DESC':trim(input('sort'));

            $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
            $number_decimal = Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value');
            try {
                Db::execute("SET @@sql_mode='';");
            } catch (\Exception $e) {
            }
            $list = [];
            $lists = Db::name('kuerp_goods_purchase_summary')->alias('i')
                ->join('kuerp_goods g','g.goods_code=i.goods_code')
                ->join('kuerp_goods_type gt','gt.id=g.goods_type_id')
                ->join('kuerp_stores s','s.id=i.stores_id')
                ->join('kuerp_unit u','u.id=i.goods_unit')
                ->where($where)
                ->order(['i.'.$field=>$order])
                ->group('i.goods_code,i.stores_id')
                ->field('sum(i.tax_money) as tax_money,sum(i.money_tax_price) as money_tax_price,sum(i.basic_nmber) as basic_nmber,sum(number) as i_number,sum(all_money) as all_money,
                ANY_VALUE(i.default_unit) as default_unit,ANY_VALUE(i.goods_unit) as goods_unit,ANY_VALUE(i.purchase_money) as purchase_money,
                ANY_VALUE(i.stores_id) as stores_id,ANY_VALUE(g.goods_code) as goods_code,ANY_VALUE(g.goods_name) as goods_name,
                ANY_VALUE(g.goods_specification_id) as goods_specification_id,ANY_VALUE(gt.supplier_type_name) as supplier_type_name,
                ANY_VALUE(s.name) as s_name,ANY_VALUE(u.name) as u_name')
                ->select();
            if(!empty($lists)){
                foreach ($lists as $lists_k=>$lists_v){
                    $lists_v['i_number'] = $lists_v['basic_nmber'];
                    $lists_v['unit_conversion'] = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$lists_v['default_unit'],'vice_unit'=>$lists_v['goods_unit']])->value('advance_rate');
                    if(!empty($list)){
                        foreach ($list as $list_k=>$list_v){
                            if($list_v['goods_code']==$lists_v['goods_code'] && $list_v['stores_id']==$lists_v['stores_id']){
                                $list[$list_k]['i_number'] += $lists_v['i_number'];
                                $list[$list_k]['all_money'] += $lists_v['all_money'];
                                $list[$list_k]['purchase_money'] += $lists_v['purchase_money'];
                                $list[$list_k]['unit_price'] = round(($list[$list_k]['all_money']/$list[$list_k]['i_number']),$price_decimal);
                                $status = true;
                                if($list[$list_k]['i_number']%$lists_v['basic_nmber']>0){
                                    $list[$list_k]['basic_nmber'] = floor($list[$list_k]['i_number']/$lists_v['unit_conversion']).$lists_v['u_name'].($list[$list_k]['i_number']%$lists_v['unit_conversion']).$list_v['u_name'];
                                }else{
                                    $list[$list_k]['basic_nmber'] = floor($list[$list_k]['i_number']/$lists_v['unit_conversion']).$lists_v['u_name'];
                                }
                                $list[$list_k]['unit_conversion'] = '1'.$lists_v['u_name'].'='.$lists_v['unit_conversion'].$list_v['u_name'];
                            }
                        }
                    }
                    if(!isset($status)){
                        $list[] = $lists_v;
                    }
                }
            }
            $all_money = 0;$i_number = 0;$purchase_money = 0;$tax_money = 0;$money_tax_price = 0;
            foreach ($list as $list_k=>$list_v){
                $all_money += $list_v['all_money'];
                $i_number += $list_v['i_number'];
                $purchase_money += $list_v['purchase_money'];
                $tax_money += $list_v['tax_money'];
                $money_tax_price += $list_v['money_tax_price'];
                $list[$list_k]['all_money'] = round($list_v['all_money'],$price_decimal);
                if($list_v['i_number']!=0){
                    $list[$list_k]['unit_price'] = round($list_v['all_money']/$list_v['i_number'],$price_decimal);
                }else{
                    $list[$list_k]['unit_price'] = 0;
                }
                $list[$list_k]['tax_money'] = round($list_v['tax_money'],$price_decimal);
                $list[$list_k]['money_tax_price'] = round($list_v['money_tax_price'],$price_decimal);
            }
            if($i_number>0){
                $unit_price = round($all_money/$i_number,$price_decimal);
            }else{
                $unit_price = 0;
            }
            $i_number = round($i_number,$number_decimal);
            $all_money = round($all_money,$price_decimal);
            $purchase_money = round($purchase_money,$price_decimal);
            $tax_money = round($tax_money,$price_decimal);
            $money_tax_price = round($money_tax_price,$price_decimal);
            $count = [
                'i_number'=>$i_number,
                'unit_price'=>$unit_price,
                'purchase_money'=>$purchase_money,
                'all_money'=>$all_money,
                'tax_money'=>$tax_money,
                'money_tax_price'=>$money_tax_price,
            ];


            $result = array("total" => sizeof($list), "rows" => $list,'count'=>$count);

            return json($result);
        }
        return $this->view->fetch();
    }

}
