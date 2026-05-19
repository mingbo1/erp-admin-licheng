<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品采购明细
 *
 * @icon fa fa-circle-o
 */
class GoodsPurchaseInfo extends Backend
{
    
    /**
     * GoodsPurchaseInfo模型对象
     * @var \app\admin\model\kuerp\GoodsPurchaseInfo
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsPurchaseInfo;
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $wheres = [];
            $searchdata = json_decode(input('filter'),true);
            if($searchdata){
                foreach ($searchdata as $k => &$v) {
                    if(!empty($v)){
                        if(strpos($v,' - ') !== false && strpos($k,'time') !== false){
                            $str = explode(' - ',$v);
                            $wheres['goods_purchase_info.'.$k] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                        }else{
                            $wheres['goods_purchase_info.'.$k] = ["LIKE", "%{$v}%"];
                        }
                    }
                }
            }

            $list = $this->model
                    ->with(['goods','unit','suppliers','stores'])
                    ->where($where)->where($wheres)
                    ->where(['goods_purchase_info.examine_status'=>'1'])
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('goods')->visible(['goods_name','goods_specification_id','goods_code','goods_barcode']);
                $row->getRelation('unit')->visible(['name']);
                $row->getRelation('suppliers')->visible(['supplyer_code','supplyer_name']);
                $row->getRelation('stores')->visible(['name']);
            }

            $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
            $number_decimal = Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value');

            foreach ($list as $list_k=>$list_v){
                if($list_v['purchase_type']==1){
                    $note = Db::name('kuerp_goods_purchase')->where(['purchase_code'=>$list_v['purchase_code']])->value('note');
                }else{
                    $note = Db::name('kuerp_goods_exchange')->where(['purchase_code'=>$list_v['purchase_code']])->value('note');
                    $list[$list_k]['number'] = -$list_v['number'];
                    $list[$list_k]['all_money'] = -$list_v['all_money'];
                    $list[$list_k]['tax_money'] = -$list_v['tax_money'];
                    $list[$list_k]['money_tax_price'] = -$list_v['money_tax_price'];
                }
                $list[$list_k]['note'] = $note;

                $list[$list_k]['number'] = round($list[$list_k]['number'],$number_decimal);
                $list[$list_k]['unit_price'] = round($list[$list_k]['unit_price'],$price_decimal);
                $list[$list_k]['tax_included'] = round($list[$list_k]['tax_included'],$price_decimal);
                $list[$list_k]['all_money'] = round($list[$list_k]['all_money'],$price_decimal);
                $list[$list_k]['purchase_money'] = round($list[$list_k]['purchase_money'],$price_decimal);
                $list[$list_k]['tax_money'] = round($list[$list_k]['tax_money'],$price_decimal);
                $list[$list_k]['money_tax_price'] = round($list[$list_k]['money_tax_price'],$price_decimal);
            }

            $map = ['goods_purchase_info.examine_status'=>'1'];
            $number = $this->model->where($where)->where($map)->sum('number');
            $all_money = $this->model->where($where)->where($map)->sum('all_money');
            $purchase_money = $this->model->where($where)->where($map)->sum('purchase_money');
            $tax_money = $this->model->where($where)->where($map)->sum('tax_money');
            $money_tax_price = $this->model->where($where)->where($map)->sum('money_tax_price');

            if($number>0){
                $unit_price = round($all_money/$number,$price_decimal);
                $tax_included = round($money_tax_price/$number,$price_decimal);
            }else{
                $unit_price = 0;
                $tax_included = 0;
            }
            $number = round($number,$number_decimal);
            $all_money = round($all_money,$price_decimal);
            $purchase_money = round($purchase_money,$price_decimal);
            $tax_money = round($tax_money,$price_decimal);
            $money_tax_price = round($money_tax_price,$price_decimal);
            $count = ['number'=>$number,'unit_price'=>$unit_price,'tax_included'=>$tax_included,'all_money'=>$all_money,'purchase_money'=>$purchase_money,'tax_money'=>$tax_money,'money_tax_price'=>$money_tax_price];

            $result = array("total" => $list->total(), "rows" => $list->items(),'count'=>$count);

            return json($result);
        }
        return $this->view->fetch();
    }

}
