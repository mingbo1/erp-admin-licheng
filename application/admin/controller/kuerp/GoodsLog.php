<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品收发明细管理
 *
 * @icon fa fa-circle-o
 */
class GoodsLog extends Backend
{

    /**
     * GoodsLog模型对象
     * @var \app\admin\model\kuerp\GoodsLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsLog;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());

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
                    if($v!==''){
                        if(strpos($v,' - ') !== false && strpos($k,'time') !== false){
                            $str = explode(' - ',$v);
                            $wheres['goods_log.'.$k] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                        }
                    }
                }
            }

            $list = $this->model
                ->with(['goods','stores'])
                ->where($where)
                ->where($wheres)
                ->order('business_type','ASC')
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('goods')->visible(['goods_code','goods_name','goods_type_id','goods_barcode','goods_specification_id','note','unit_id']);
                $row->getRelation('stores')->visible(['name']);
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

            $format = [
                'enter_number'  =>$number_decimal,
                'enter_basics'  =>$number_decimal,
                'enter_unit'    =>$price_decimal,
                'enter_cost'    =>$price_decimal,
                'out_number'    =>$number_decimal,
                'out_basics'    =>$number_decimal,
                'out_unit'      =>$price_decimal,
                'out_cost'      =>$price_decimal,
            ];
            $goodsData = [];
            foreach ($list as $list_k=>$list_v){
                //计算结存
                if(!isset($goodsData[$list_v['goods_id']])){
                    switch ($list_v['business_type']){
                        case 1:
                            //期初
                            $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['balance_basics'],'cost'=>$list_v['balance_cost']];
                            break;
                        case 2:
                            //购货
                            $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['enter_basics'],'cost'=>$list_v['enter_cost']];
                            break;
                        case 3:
                            //购货退货
                            $goodsData[$list_v['goods_id']] = ['basics'=>-$list_v['out_basics'],'cost'=>-$list_v['out_cost']];
                            break;
                        case 4:
                            //销货
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
                            $map = [];
                            $map['pi.goods_code'] = $list_v['goods_code'];
                            $map['p.purchase_code'] = $list_v['purchase_code'];
                            if($list_v['attribute_id']){
                                $map['pi.goods_attr'] = $list_v['attribute_id'];
                            }
                            if(!empty($checkdate)){
                                $map['pi.purchase_time'] = ['>=',$checkdate];
                            }
                            $lists = Db::name('kuerp_goods_sales_info')->alias('pi')
                                ->join('kuerp_goods_sales p','p.purchase_code=pi.purchase_code','left')
                                ->join('kuerp_goods_sales_exchange e','e.purchase_code=pi.purchase_code','left')
                                ->where($map)->where(['p.examine_status|e.examine_status'=>'1'])
                                ->order('pi.id', $order)
                                ->field('pi.*')
                                ->select();
                            $lists = \addons\kuerp\library\Goods::getPrList($lists,$stores_set,$stores_ids,$enable_time,$valuation_set,[],$price_taxes,$taxes_set,$price_decimal,false);
                            $out_cost = 0;
                            foreach ($lists as $lists_k=>$lists_v){
                                $out_cost += $lists_v['sales_cost'];
                            }
                            $list[$list_k]['out_cost'] = $out_cost;
                            $list[$list_k]['out_unit'] = empty($list_v['out_basics'])?$list_v['out_unit']:($out_cost/$list_v['out_basics']);
                            $goodsData[$list_v['goods_id']] = ['basics'=>-$list_v['out_basics'],'cost'=>-$out_cost];
                            break;
                        case 5:
                            //销货退货
                            $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['enter_basics'],'cost'=>$list_v['enter_cost']];
                            break;
                        case 6:
                            //调拨
                            if($list_v['enter_basics']>0){
                                $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['enter_basics'],'cost'=>$list_v['enter_cost']];
                            }else if($list_v['out_basics']>0){
                                $goodsData[$list_v['goods_id']] = ['basics'=>-$list_v['out_basics'],'cost'=>-$list_v['out_cost']];
                            }
                            break;
                        case 7:
                            //成本调整
                            $goodsData[$list_v['goods_id']] = ['basics'=>'','cost'=>$list_v['enter_cost']];
                            break;
                        case 8:
                            //盘盈
                            $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['enter_basics'],'cost'=>$list_v['enter_cost']];
                            break;
                        case 9:
                            //其他入库
                            $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['out_basics'],'cost'=>$list_v['out_cost']];
                            break;
                        case 10:
                            //盘亏
                            $goodsData[$list_v['goods_id']] = ['basics'=>-$list_v['out_basics'],'cost'=>-$list_v['out_cost']];
                            break;
                        case 11:
                            $goodsData[$list_v['goods_id']] = ['basics'=>-$list_v['out_basics'],'cost'=>-$list_v['out_cost']];
                            break;
                        case 12:
                            $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['enter_basics'],'cost'=>$list_v['enter_cost']];
                            break;
                        case 13:
                            $goodsData[$list_v['goods_id']] = ['basics'=>-$list_v['out_basics'],'cost'=>-$list_v['out_cost']];
                            break;
                        case 14:
                            $goodsData[$list_v['goods_id']] = ['basics'=>$list_v['enter_basics'],'cost'=>$list_v['enter_cost']];
                            break;
                        case 15:
                            $goodsData[$list_v['goods_id']] = ['basics'=>-$list_v['out_basics'],'cost'=>-$list_v['out_cost']];
                            break;
                    }
                }else{
                    switch ($list_v['business_type']){
                        case 1:
                            //期初
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['balance_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['balance_cost']
                            ];
                            break;
                        case 2:
                            //购货
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['enter_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['enter_cost']
                            ];
                            break;
                        case 3:
                            //购货退货
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']-(float)$list_v['out_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']-(float)$list_v['out_cost']
                            ];
                            break;
                        case 4:
                            //销货
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
                            $map = [];
                            $map['pi.goods_code'] = $list_v['goods_code'];
                            $map['p.purchase_code'] = $list_v['purchase_code'];
                            if($list_v['attribute_id']){
                                $map['pi.goods_attr'] = $list_v['attribute_id'];
                            }
                            if(!empty($checkdate)){
                                $map['pi.purchase_time'] = ['>=',$checkdate];
                            }
                            $lists = Db::name('kuerp_goods_sales_info')->alias('pi')
                                ->join('kuerp_goods_sales p','p.purchase_code=pi.purchase_code','left')
                                ->join('kuerp_goods_sales_exchange e','e.purchase_code=pi.purchase_code','left')
                                ->where($map)->where(['p.examine_status|e.examine_status'=>'1'])
                                ->order('pi.id', $order)
                                ->field('pi.*')
                                ->select();
                            $lists = \addons\kuerp\library\Goods::getPrList($lists,$stores_set,$stores_ids,$enable_time,$valuation_set,[],$price_taxes,$taxes_set,$price_decimal,false);
                            $out_cost = 0;
                            foreach ($lists as $lists_k=>$lists_v){
                                $out_cost += $lists_v['sales_cost'];
                            }
                            $list[$list_k]['out_cost'] = $out_cost;
                            $list[$list_k]['out_unit'] = empty($list_v['out_basics'])?$list_v['out_unit']:($out_cost/$list_v['out_basics']);
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']-(float)$list_v['out_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']-(float)$out_cost
                            ];
                            break;
                        case 5:
                            //销货退货
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['enter_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['enter_cost']
                            ];
                            break;
                        case 6:
                            //调拨
                            if($list_v['enter_basics']>0){
                                $goodsData[$list_v['goods_id']] = [
                                    'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['enter_basics'],
                                    'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['enter_cost']
                                ];
                            }else if($list_v['out_basics']>0){
                                $goodsData[$list_v['goods_id']] = [
                                    'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']-(float)$list_v['out_basics'],
                                    'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']-(float)$list_v['out_cost']
                                ];
                            }
                            break;
                        case 7:
                            //购货
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['enter_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['enter_cost']
                            ];
                            break;
                        case 8:
                            //盘盈
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['balance_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['balance_cost']
                            ];
                            break;
                        case 9:
                            //其他入库
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['enter_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['enter_cost']
                            ];
                            break;
                        case 10:
                            //盘亏
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']-(float)$list_v['out_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']-(float)$list_v['out_cost']
                            ];
                            break;
                        case 11:
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']-(float)$list_v['out_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']-(float)$list_v['out_cost']
                            ];
                            break;
                        case 12:
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['enter_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['enter_cost']
                            ];
                            break;
                        case 13:
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']-(float)$list_v['out_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']-(float)$list_v['out_cost']
                            ];
                            break;
                        case 14:
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']+(float)$list_v['enter_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']+(float)$list_v['enter_cost']
                            ];
                            break;
                        case 15:
                            $goodsData[$list_v['goods_id']] = [
                                'basics'=>(float)$goodsData[$list_v['goods_id']]['basics']-(float)$list_v['out_basics'],
                                'cost'=>(float)$goodsData[$list_v['goods_id']]['cost']-(float)$list_v['out_cost']
                            ];
                            break;
                    }
                }
                $list[$list_k]['balance_basics']    = isset($goodsData[$list_v['goods_id']]['basics'])?$goodsData[$list_v['goods_id']]['basics']:0;
                if(isset($goodsData[$list_v['goods_id']]['basics']) && $goodsData[$list_v['goods_id']]['basics']>0){
                    $list[$list_k]['balance_unit']  = round(($goodsData[$list_v['goods_id']]['cost']/$goodsData[$list_v['goods_id']]['basics']),$price_decimal);
                }else{
                    $list[$list_k]['balance_unit']  = 0;
                }
                $list[$list_k]['balance_cost']      = isset($goodsData[$list_v['goods_id']]['cost'])?$goodsData[$list_v['goods_id']]['cost']:0;

                //格式化数字输出
                foreach ($format as $k=>$v){
                    if(isset($list_v[$k]) && $list_v[$k]!==null){
                        $list[$list_k][$k]  = round($list_v[$k],$v);
                    }
                }
                $list[$list_k]['goods_code']    = $list_v['goods']['goods_code'];
                $list[$list_k]['goods_name']    = $list_v['goods']['goods_name'];
                $list[$list_k]['goods_barcode'] = $list_v['goods']['goods_barcode'];
                $list[$list_k]['goods_spec']    = $list_v['goods']['goods_specification_id'];
                $list[$list_k]['note']          = $list_v['goods']['note'];

                $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['attribute_id']])->value('attribute_id');
                $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
                if($attribute_ids){
                    $attribute_ids = explode('&',$attribute_ids);
                    $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                    $attribute_name = implode('/',$attribute);
                    $list[$list_k]['goods_name'] = $list_v['goods']['goods_name'].'_'.$attribute_name;
                }
                //商品类别
                $type_name = Db::name('kuerp_goods_type')->where(['id'=>$list_v['goods']['goods_type_id']])->value('supplier_type_name');
                $list[$list_k]['type_name'] = $type_name;
                //供应商/客户
                if($list_v['business_type']==2 || $list_v['business_type']==3 || $list_v['business_type']==8 || $list_v['business_type']==9){
                    $list[$list_k]['customer_id'] = Db::name('kuerp_suppliers')->where(['id'=>$list_v['customer_id']])->value('supplyer_name');
                }else if($list_v['business_type']==4 || $list_v['business_type']==5 || $list_v['business_type']==10 || $list_v['business_type']==11){
                    $list[$list_k]['customer_id'] = Db::name('kuerp_customers')->where(['id'=>$list_v['customer_id']])->value('customer_name');
                }
                //商品单位
                $unit = Db::name('kuerp_unit')->where(['id'=>$list_v['goods_unit']])->field('id,name')->find();
                $list[$list_k]['unit_name'] = isset($unit['name'])?$unit['name']:'';
                if($list_v['goods_unit']!=$list_v['goods']['unit_id']){//多单位
                    $more_unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$list_v['goods']['unit_id'],'vice_unit'=>$list_v['goods_unit']])->find();
                    $unit_name = Db::name('kuerp_unit')->where(['id'=>$list_v['goods']['unit_id']])->value('name');
                    if($more_unit){
                        $list[$list_k]['unit_name'] = $list[$list_k]['unit_name'].'('.$more_unit['advance_rate'].$unit_name.')';
                    }
                }
            }
            $cost = 0;$basics = 0;
            foreach ($goodsData as $goodsData_k=>$goodsData_v){
                $cost   += $goodsData_v['cost'];
                $basics += (float)$goodsData_v['basics'];
            }
            $basics  = round($basics,$number_decimal);$cost  = round($cost,$price_decimal);
            $result = array("total" => $list->total(), "rows" => $list->items(), "count" => $basics, "balance" => $cost);

            return json($result);
        }
        return $this->view->fetch();
    }

}
