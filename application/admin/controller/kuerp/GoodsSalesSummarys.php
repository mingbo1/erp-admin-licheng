<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 销售汇总管理
 *
 * @icon fa fa-circle-o
 */
class GoodsSalesSummarys extends Backend
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
            $field = empty(input('field'))?'sale_user':trim(input('field'));
            $order = empty(input('sort'))?'DESC':trim(input('sort'));

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

            $sup = Db::name('kuerp_staff_member')
                ->field('id,name,men_code')
                ->select();
            try {
                Db::execute("SET @@sql_mode='';");
            } catch (\Exception $e) {
            }
            $listarr = [];
            foreach ($sup as $sup_k=>$sup_v){
                $list = [];
                $lists = Db::name('kuerp_goods_sales_summary')->alias('i')
                    ->join('kuerp_goods g','g.goods_code=i.goods_code')
                    ->join('kuerp_stores s','s.id=i.stores_id')
                    ->join('kuerp_unit u','u.id=i.goods_unit')
                    ->where($where)
                    ->where(['i.sale_user'=>$sup_v['id']])
                    ->order(['i.'.$field=>$order])
                    ->group('i.goods_code,i.stores_id')
                    ->field('sum(i.tax_money) as tax_money,sum(i.money_tax_price) as money_tax_price,sum(i.basic_nmber) as basic_nmber,sum(i.number) as i_number,sum(i.all_money) as all_money,
                    ANY_VALUE(i.stores_id) as stores_id,ANY_VALUE(i.unit_price) as unit_price,ANY_VALUE(i.default_unit) as default_unit,ANY_VALUE(i.goods_unit) as goods_unit,ANY_VALUE(i.purchase_money) as purchase_money,
                ANY_VALUE(g.goods_code) as goods_code,ANY_VALUE(g.goods_name) as goods_name,ANY_VALUE(g.goods_specification_id) as goods_specification_id,ANY_VALUE(g.goods_barcode) as goods_barcode,ANY_VALUE(g.unit_id) as unit_id,ANY_VALUE(g.more_unit) as more_unit,
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
                foreach ($list as $list_k=>$list_v){
                    $list_v[$field] = $sup_v['id'];
                    $list_v['supplyer_name'] = $sup_v['name'];
                    $list_v['supplier_type_name'] = $sup_v['men_code'];

                    if(isset($list_v['advance_rate'])){
                        $list_v['basic_nmber'] = round( ($list_v['i_number']/$list_v['advance_rate']) ,$number_decimal).$list_v['more_unit_name'];
                    }
                    if($list_v['i_number']!=0){
                        $list_v['unit_price'] = round( ($list_v['all_money']/$list_v['i_number']) ,$price_decimal);
                        $list_v['tax_included'] = round( ($list_v['money_tax_price']/$list_v['i_number']) ,$price_decimal);
                    }else{
                        $list_v['unit_price'] = 0;
                        $list_v['tax_included'] = 0;
                    }

                    $list_v['i_number'] = round( ($list_v['i_number']) ,$number_decimal);
                    $list_v['all_money'] = round( ($list_v['all_money']) ,$price_decimal);
                    $list_v['tax_money'] = round( ($list_v['tax_money']) ,$price_decimal);
                    $list_v['money_tax_price'] = round( ($list_v['money_tax_price']) ,$price_decimal);

                    $listarr[] = $list_v;
                }
                $sup[$sup_k]['list'] = $list;
            }

            $count = [
                'i_number'=>0,
                'unit_price'=>0,
                'all_money'=>0,
                'tax_included'=>0,
                'tax_money'=>0,
                'money_tax_price'=>0,
            ];
            $counts = $count;
            $counts['s_name'] = '合计';
            $data = [];
            $now = isset($listarr[0][$field])?$listarr[0][$field]:'';
            foreach ($listarr as $list_k=>$list_v){
                if($list_v[$field]!=$now){
                    $now = $list_v[$field];
                    $row = $count;$row['s_name'] = '小计';
                    $row['unit_price'] = round($row['all_money']/$row['i_number'],$price_decimal);
                    $row['i_number'] = round($row['i_number'],$number_decimal);
                    $row['unit_price'] = round($row['unit_price'],$price_decimal);
                    $row['all_money'] = round($row['all_money'],$price_decimal);
                    $row['tax_money'] = round($row['tax_money'],$price_decimal);
                    $row['money_tax_price'] = round($row['money_tax_price'],$price_decimal);
                    $data[] = $row;
                    foreach ($count as $count_k=>$count_v){
                        $count[$count_k] = $list_v[$count_k];
                        $counts[$count_k] += $list_v[$count_k];
                    }
                }else{
                    foreach ($count as $count_k=>$count_v){
                        $count[$count_k] += $list_v[$count_k];
                        $counts[$count_k] += $list_v[$count_k];
                    }
                }
                $list_v['i_number'] = round($list_v['i_number'],$number_decimal);
                $list_v['unit_price'] = round($list_v['unit_price'],$price_decimal);
                $list_v['all_money'] = round($list_v['all_money'],$price_decimal);
                $list_v['tax_money'] = round($list_v['tax_money'],$price_decimal);
                $list_v['money_tax_price'] = round($list_v['money_tax_price'],$price_decimal);
                $data[] = $list_v;
                if($list_k==(sizeof($listarr)-1) || sizeof($listarr)==1){
                    $row = $count;$row['s_name'] = '小计';
                    $row['unit_price'] = round($row['all_money']/$row['i_number'],$price_decimal);
                    $row['i_number'] = round($row['i_number'],$number_decimal);
                    $row['unit_price'] = round($row['unit_price'],$price_decimal);
                    $row['all_money'] = round($row['all_money'],$price_decimal);
                    $row['tax_money'] = round($row['tax_money'],$price_decimal);
                    $row['money_tax_price'] = round($row['money_tax_price'],$price_decimal);
                    $data[] = $row;
                }
            }
            $counts['unit_price'] = $counts['i_number']>0?(round($counts['all_money']/$counts['i_number'],$price_decimal)):0;
            $counts['tax_included'] = round($counts['tax_included'],$price_decimal);
            $counts['all_money'] = round($counts['all_money'],$price_decimal);
            $counts['tax_money'] = round($counts['tax_money'],$price_decimal);
            $counts['money_tax_price'] = round($counts['money_tax_price'],$price_decimal);
            $data[] = $counts;

            $result = array("total" => sizeof($data), "rows" => $data);
            return json($result);
        }
        return $this->view->fetch();
    }

}
