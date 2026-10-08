<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品库存管理
 *
 * @icon fa fa-circle-o
 */
class GoodsStock extends Backend
{

    /**
     * GoodsStock模型对象
     * @var \app\admin\model\kuerp\GoodsStock
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsStock;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("stockWarningList", $this->model->getStockWarningList());

        $storesList = $this->model->getStoresList();
        $storesLists = [];
        foreach ($storesList as $storesList_k=>$storesList_v){
            $storesLists[] = ['name'=>$storesList_v['name'],'value'=>$storesList_v['id']];
        }
        $this->view->assign("storesList", $storesList);
        $this->assignconfig("storesList", $storesLists);

        $goodsList = $this->model->getGoodsList();
        $goodsLists = [];
        foreach ($goodsList as $goodsList_k=>$goodsList_v){
            $goodsLists[] = ['name'=>$goodsList_v['goods_name'],'value'=>$goodsList_v['id']];
        }
        $this->view->assign("goodsList", $goodsList);
        $this->assignconfig("goodsList", $goodsLists);

        $rowlist = [
            ['field'=>'stock','title'=>'基本数量','totalRow'=>true],
            ['field'=>'more_unit_num','title'=>'副单位数'],
            ['field'=>'unit_price','title'=>'单位成本','totalRow'=>true],
            ['field'=>'cost_money','title'=>'成本','totalRow'=>true]
        ];
        $rowspan = [
            //fixed:'left' 冻结商品编号/名称列：列多横向滚动时始终可见（layui 2.8+ 复杂表头支持固定列）
            ['field'=>'goods_code','title'=>'商品编号','rowspan'=>2,'totalRow'=>'合计','fixed'=>'left'],
            ['field'=>'goods_name','title'=>'商品名称','rowspan'=>2,'width'=>'120','fixed'=>'left'],
            ['field'=>'type_name','title'=>'商品类别','rowspan'=>2],
            ['field'=>'goods_specification_id','title'=>'型号','rowspan'=>2],
            ['field'=>'note','title'=>'商品备注','rowspan'=>2],
            ['field'=>'unit_name','title'=>'单位','rowspan'=>2],
            ['field'=>'unitconversion','title'=>'副单位','rowspan'=>2],
            ['field'=>'goods_barcode','title'=>'商品条码','rowspan'=>2],
            ['field'=>'stores_id','title'=>'所有仓库','colspan'=>4]
        ];
        foreach ($storesList as $data_k=>$data_v){
            $rowspan[] = ['field'=>'stores_id'.$data_v['id'],'title'=>$data_v['name'],'colspan'=>4];
            $rowlist[] = ['field'=>'stock'.$data_v['id'],'title'=>'基本数量','totalRow'=>true];
            $rowlist[] = ['field'=>'more_unit_num'.$data_v['id'],'title'=>'副单位数'];
            $rowlist[] = ['field'=>'unit_price'.$data_v['id'],'title'=>'单位成本','totalRow'=>true];
            $rowlist[] = ['field'=>'cost_money'.$data_v['id'],'title'=>'成本','totalRow'=>true];
        }
        $this->assignconfig('rowspan',json_encode($rowspan));
        $this->assignconfig('rowlist',json_encode($rowlist));
    }

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

            $where = [];
            $searchdata = json_decode(input('filter'),true);
            if($searchdata){
                foreach ($searchdata as $k => &$v) {
                    if($v!==''){
                        if($k=='id'){
                            $where['goods_stock.'.$k] = ['<',$v];
                        }else if(strpos($v,' - ') !== false && strpos($k,'time') !== false){
                            $str = explode(' - ',$v);
                            $where['goods_stock.'.$k] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                        }else if($k=='goods_id' || $k=='stores_id'){
                            $where['goods_stock.'.$k] = ["in", $v];
                        }else if($k=='goods_type_id'){
                            $where['goods.'.$k] = ["in", $v];
                        }else{
                            $where['goods_stock.'.$k] = ["LIKE", "%{$v}%"];
                        }
                    }
                }
            }
            try {
                \think\Db::execute("SET @@sql_mode='';");
            } catch (\Exception $e) {
            }
            $list = Db::name('kuerp_goods_stock')->alias('goods_stock')
                ->join('kuerp_goods goods','goods.goods_code=goods_stock.goods_code')
                ->where($where)
                ->group('goods.goods_code,goods_stock.attribute_id')
                ->field('ANY_VALUE(goods_stock.goods_code) as goods_code,sum(goods_stock.stock) as stock,
                    sum(goods_stock.cost_money) as cost_money,ANY_VALUE(goods_stock.attribute_id) as attribute_id')
                ->select();
            foreach ($list as $list_k=>$list_v) {
                $list[$list_k]['goods'] = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])
                    ->field('goods_type_id,goods_code,goods_name,goods_specification_id,note,unit_id,more_unit,goods_barcode')
                    ->find();
            }

            $number_decimal = Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value');
            $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
            $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime');
            $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
            $valuation_set= Db::name('kuerp_sets')->where(['name'=>'valuation'])->value('value');//计算方法
            $stores_set= Db::name('kuerp_sets')->where(['name'=>'stores'])->value('is_checked');//是否分仓
            if($valuation_set!=2){
                $valuation_set = 1;
            }
            if($stores_set!=1){
                $stores_set = 0;
            }

            $price_taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');
            $taxes_set= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
            $stores_ids = Db::name('kuerp_stores')->where(['status'=>'1'])->column('id');

            foreach ($list as $list_k=>$list_v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->find();
                $purchase_money = Db::name('kuerp_goods_purchase_info')->where(['pi.goods_code'=>$list_v['goods_code'],'p.examine_status'=>'1'])->alias('pi')
                    ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                    ->field('sum(pi.all_money) as all_money,sum(pi.basic_nmber) as basic_nmber')->find();

                $goods_period = Db::name('kuerp_goods_period')->where(['goods_id'=>$goods['id']])
                    ->field('sum(price) as all_money,sum(stock) as basic_nmber')->find();
                if($goods_period){
                    $purchase_money['all_money'] += $goods_period['all_money'];$purchase_money['basic_nmber'] += $goods_period['basic_nmber'];
                }

                $warehousing_money = Db::name('kuerp_warehousing_info')->where(['pi.goods_id'=>$goods['id'],'p.examine_status'=>'1'])->alias('pi')
                    ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                    ->field('sum(pi.price) as all_money,sum(pi.basic_nmber) as basic_nmber')->find();
                if($warehousing_money){
                    $purchase_money['all_money'] += $warehousing_money['all_money'];$purchase_money['basic_nmber'] += $warehousing_money['basic_nmber'];
                }
                $assemble_money = Db::name('kuerp_assemble_info')->where(['pi.goods_code'=>$goods['id'],'p.examine_status'=>'1'])->alias('pi')
                    ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                    ->field('sum(pi.cost_money) as all_money,sum(pi.basic_number) as basic_nmber')->find();
                if($assemble_money){
                    $purchase_money['all_money'] += $assemble_money['all_money'];$purchase_money['basic_nmber'] += $assemble_money['basic_nmber'];
                }
                $result = \addons\kuerp\library\Goods::getGoodsCostPrice(
                    $list_v['goods_code'],
                    $number_decimal,
                    $price_decimal,
                    $checkdate,
                    $enable_time,
                    $valuation_set,
                    $stores_set,
                    $price_taxes,
                    $taxes_set,
                    $stores_ids
                );
                if(isset($result[0]['cost_all']) && isset($result[0]['basic_nmber'])){
                    $cost_money = $purchase_money['all_money']-$result[0]['cost_all'];
                    $cost_number = $purchase_money['basic_nmber']-$result[0]['basic_nmber'];
                }else{
                    $cost_money = $purchase_money['all_money'];
                    $cost_number = $purchase_money['basic_nmber'];
                }
                if($cost_number!=0){
                    $unit_price = round($cost_money/$cost_number,$price_decimal);
                }else{
                    $unit_price = 0;
                }
                $list[$list_k]['unit_price'] = round($unit_price,$price_decimal);
                $list[$list_k]['cost_money'] = round($cost_money,$price_decimal);
                $list[$list_k]['type_name'] = Db::name('kuerp_goods_type')->where(['id'=>$list_v['goods']['goods_type_id']])->value('supplier_type_name');
                //单位信息
                $unit = Db::name('kuerp_unit')->where(['id'=>$list_v['goods']['unit_id']])->field('id,name')->find();
                $list[$list_k]['unit_name'] = $unit['name'];
                $max_unit = 1;
                $unit_arr = [];
                $more_unit_num = '';
                if($unit){
                    $unit_arr[] = ['name'=>$unit['name'],'advance_rate'=>1];
                    if($list_v['goods']['more_unit']==1){
                        $more_unit = Db::name('kuerp_unit_conversion')->alias('uc')
                            ->join('kuerp_unit u','u.id=uc.vice_unit')
                            ->where(['uc.main_unit'=>$unit['id']])
                            ->order('uc.advance_rate','ASC')
                            ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                            ->select();
                        foreach ($more_unit as $unit_k=>$unit_v){
                            $unit_arr[] = ['name'=>$unit_v['name'],'advance_rate'=>$unit_v['advance_rate']];
                            if($unit_v['advance_rate']>$max_unit){
                                $max_unit = $unit_v['advance_rate'];
                            }
                        }
                        $unitconversion = '';
                        $unit_arr = \addons\kuerp\library\Goods::arraySequence($unit_arr,'advance_rate','SORT_DESC');
                        foreach ($unit_arr as $unit_arr_k=>$unit_arr_v){
                            if(!empty($unitconversion)){
                                $unitconversion .= '=';
                            }else{
                                $more_unit_num .= round(($list_v['stock']/$unit_arr_v['advance_rate']),$number_decimal).$unit_arr_v['name'];
                            }
                            $unitconversion .= round(($max_unit/$unit_arr_v['advance_rate']),$number_decimal).$unit_arr_v['name'];
                        }
                        $list[$list_k]['unitconversion'] = $unitconversion;
                        $list[$list_k]['more_unit_num'] = $more_unit_num;
                    }
                }
            }

            $storesList = $this->model->getStoresList();
            $countArr = [];
            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['cost_money'] = round($list_v['stock']*$list_v['unit_price'],$price_decimal);
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($countArr['stock'])){
                    $countArr['stock'] += $list_v['stock'];
                }else{
                    $countArr['stock'] = $list_v['stock'];
                }
                if(isset($countArr['cost_money'])){
                    $countArr['cost_money'] += $list_v['cost_money'];
                }else{
                    $countArr['cost_money'] = $list_v['cost_money'];
                }
                $countArr['stock'] = round($countArr['stock'],$number_decimal);
                $countArr['cost_money'] = round($countArr['cost_money'],$price_decimal);
                $list[$list_k]['goods_code'] = $list_v['goods']['goods_code'];
                $list[$list_k]['goods_name'] = $list_v['goods']['goods_name'];
                $list[$list_k]['goods_specification_id'] = $list_v['goods']['goods_specification_id'];
                $list[$list_k]['note'] = $list_v['goods']['note'];
                $list[$list_k]['goods_barcode'] = $list_v['goods']['goods_barcode'];

                //按属性分组显示
                $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['attribute_id']])->value('attribute_id');
                $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
                if($attribute_ids){
                    $attribute_ids = explode('&',$attribute_ids);
                    $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                    $attribute_name = implode('/',$attribute);
                    $list[$list_k]['goods_name'] = $list_v['goods']['goods_name'].'_'.$attribute_name;
                }

                foreach ($storesList as $storesList_k=>$storesList_v){
                    $list2 = Db::name('kuerp_goods_stock')->alias('goods_stock')
                        ->join('kuerp_goods goods','goods.goods_code=goods_stock.goods_code')
                        ->where($where)
                        ->where(['goods_stock.stores_id'=>$storesList_v['id'],'goods_stock.goods_code'=>$list_v['goods_code']])
                        ->where(['goods_stock.attribute_id'=>$list_v['attribute_id']])
                        ->group('goods.goods_code')
                        ->field('sum(goods_stock.stock) as stock,sum(goods_stock.cost_money) as cost_money')
                        ->find();
                    if($list2){
                        $list2['unit_price'] = $list_v['unit_price'];
                        $list2['cost_money'] = round( ($list_v['unit_price']*$list2['stock']), $price_decimal);

                        $unit = Db::name('kuerp_unit')->where(['id'=>$list_v['goods']['unit_id']])->field('id,name')->find();
                        if($unit){
                            if($list_v['goods']['more_unit']==1){
                                $more_unit = Db::name('kuerp_unit_conversion')->alias('uc')
                                    ->join('kuerp_unit u','u.id=uc.vice_unit')
                                    ->where(['uc.main_unit'=>$unit['id']])
                                    ->order('uc.advance_rate','DESC')
                                    ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                                    ->find();
                                $more_unit_num = '';
                                if($more_unit){
                                    $more_unit_num .= round(($list2['stock']/$more_unit['advance_rate']),$number_decimal).$more_unit['name'];
                                }
                                $list2['more_unit_num'] = $more_unit_num;
                            }
                        }
                        if(!isset($list2['more_unit_num'])){
                            $list2['more_unit_num'] = '';
                        }
                        $list[$list_k]['stock'.$storesList_v['id']] = $list2['stock'];
                        $list[$list_k]['cost_money'.$storesList_v['id']] = round($list2['cost_money'],$price_decimal);
                        $list[$list_k]['unit_price'.$storesList_v['id']] = round($list2['unit_price'],$price_decimal);
                        $list[$list_k]['more_unit_num'.$storesList_v['id']] = $list2['more_unit_num'];

                        if(isset($countArr['stock'.$storesList_v['id']])){
                            $countArr['stock'.$storesList_v['id']] += $list2['stock'];
                        }else{
                            $countArr['stock'.$storesList_v['id']] = $list2['stock'];
                        }
                        if(isset($countArr['cost_money'.$storesList_v['id']])){
                            $countArr['cost_money'.$storesList_v['id']] += $list2['cost_money'];
                        }else{
                            $countArr['cost_money'.$storesList_v['id']] = $list2['cost_money'];
                        }
                    }
                }
            }
            $sumArr = [];
            if(isset($countArr['stock']) && $countArr['stock']>0){
                $sumArr[] = ['unit_price_count'=>round(($countArr['cost_money']/$countArr['stock']),$price_decimal),'id'=>''];
            }else{
                $sumArr[] = ['unit_price_count'=>0,'id'=>''];
            }
            foreach ($storesList as $storesList_k=>$storesList_v){
                if(isset($countArr['stock'.$storesList_v['id']]) && $countArr['stock'.$storesList_v['id']]>0){
                    $sumArr[] = ['unit_price_count'=>round(($countArr['cost_money'.$storesList_v['id']]/$countArr['stock'.$storesList_v['id']]),$price_decimal),'id'=>$storesList_v['id']];
                }else{
                    $sumArr[] = ['unit_price_count'=>0,'id'=>$storesList_v['id']];
                }
            }
            $result = array("total" => sizeof($list), "rows" => $list, "countArr" => $sumArr);

            return json($result);
        }
        return $this->view->fetch();
    }

}
