<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 销售汇总管理
 *
 * @icon fa fa-circle-o
 */
class GoodsSalesSummary extends Backend
{

    /**
     * GoodsSalesSummary模型对象
     * @var \app\admin\model\kuerp\GoodsSalesSummary
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesSummary;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("saleList", $this->model->getSaleList());

        $this->view->assign("goodsTypeList", Db::name('kuerp_goods_type')->where(['status'=>'1'])->select());
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
                        }else if ($k=='customers_id'){
                            $where['gt.id'] = $v;
                        }else{
                            $where['i.'.$k] = ["LIKE", "%{$v}%"];
                        }
                    }
                }
            }
            $field = empty(input('field'))?'goods_code':trim(input('field'));
            $order = empty(input('sort'))?'DESC':trim(input('sort'));
            try {
                Db::execute("SET @@sql_mode='';");
            } catch (\Exception $e) {
            }
            $list = [];
            $lists = Db::name('kuerp_goods_sales_summary')->alias('i')
                ->join('kuerp_goods g','g.goods_code=i.goods_code')
                ->join('kuerp_goods_type gt','gt.id=g.goods_type_id')
                ->join('kuerp_stores s','s.id=i.stores_id')
                ->join('kuerp_unit u','u.id=i.goods_unit')
                ->where($where)
                ->order(['i.'.$field=>$order])
                ->group('i.goods_code,i.stores_id')
                ->field('sum(i.tax_money) as tax_money,sum(i.money_tax_price) as money_tax_price,sum(i.basic_nmber) as basic_nmber,sum(i.number) as i_number,sum(i.all_money) as all_money,
                ANY_VALUE(i.default_unit) as default_unit,ANY_VALUE(i.goods_unit) as goods_unit,ANY_VALUE(i.purchase_money) as purchase_money,
                ANY_VALUE(i.stores_id) as stores_id,ANY_VALUE(i.unit_price) as unit_price,
                ANY_VALUE(g.goods_code) as goods_code,ANY_VALUE(g.goods_name) as goods_name,ANY_VALUE(g.goods_specification_id) as goods_specification_id,
                ANY_VALUE(g.goods_barcode) as goods_barcode,ANY_VALUE(g.unit_id) as unit_id,ANY_VALUE(g.more_unit) as more_unit,
                ANY_VALUE(gt.supplier_type_name) as supplier_type_name,
                ANY_VALUE(s.name) as s_name,ANY_VALUE(u.name) as u_name')
                ->select();
            if(!empty($lists)){
                foreach ($lists as $lists_k=>$lists_v){
                    $lists_v['i_number'] = $lists_v['basic_nmber'];
                    $unit_conversion = '';
                    $unit = Db::name('kuerp_unit')->where(['id'=>$lists_v['unit_id']])->field('id,name')->find();
                    if($unit){
                        $lists_v['u_name'] = $unit['name'];
                        $unit_name = $unit['name'];
                        $rowadvance_rate = '1';
                        if($lists_v['more_unit']==1){
                            $more_unit = Db::name('kuerp_unit_conversion')->alias('uc')
                                ->join('kuerp_unit u','u.id=uc.vice_unit')
                                ->where(['uc.main_unit'=>$unit['id']])
                                ->order('uc.advance_rate','ASC')
                                ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                                ->select();
                            foreach ($more_unit as $unit_k=>$unit_v){
                                $unit_name .= ','.$unit_v['name'];
                                $rowadvance_rate .= ':'.$unit_v['advance_rate'];
                                if($unit_v['id']==$lists_v['goods_unit']){
                                    $lists_v['advance_rate'] = $unit_v['advance_rate'];
                                    $lists_v['more_unit_name'] = $unit_v['name'];
                                }
                            }
                            $unit_conversion = $unit_name.'('.$rowadvance_rate.')';
                        }
                    }
                    $lists_v['unit_conversion'] = $unit_conversion;

                    if(!empty($list)){
                        foreach ($list as $list_k=>$list_v){
                            if($list_v['goods_code']==$lists_v['goods_code'] && $list_v['stores_id']==$lists_v['stores_id']){
                                $list[$list_k]['i_number'] += $lists_v['i_number'];
                                $list[$list_k]['all_money'] += $lists_v['all_money'];
                                $list[$list_k]['purchase_money'] += $lists_v['purchase_money'];
                                $status = true;
                            }
                        }
                    }
                    if(!isset($status)){
                        $list[] = $lists_v;
                    }
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
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['advance_rate'])){
                    $list[$list_k]['basic_nmber'] = round( ($list_v['i_number']/$list_v['advance_rate']) ,$number_decimal).$list_v['more_unit_name'];
                }
                if($list_v['i_number']!=0){
                    $list[$list_k]['unit_price'] = round( ($list_v['all_money']/$list_v['i_number']) ,$price_decimal);
                    $list[$list_k]['tax_included'] = round( ($list_v['money_tax_price']/$list_v['i_number']) ,$price_decimal);
                }else{
                    $list[$list_k]['unit_price'] = 0;
                    $list[$list_k]['tax_included'] = 0;
                }

                $list[$list_k]['i_number'] = round( ($list_v['i_number']) ,$number_decimal);
                $list[$list_k]['all_money'] = round( ($list_v['all_money']) ,$price_decimal);
                $list[$list_k]['tax_money'] = round( ($list_v['tax_money']) ,$price_decimal);
                $list[$list_k]['money_tax_price'] = round( ($list_v['money_tax_price']) ,$price_decimal);
            }
            $result = array("total" => sizeof($list), "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

}
