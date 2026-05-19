<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品批次库存管理
 *
 * @icon fa fa-circle-o
 */
class GoodsBatchStock extends Backend
{

    /**
     * GoodsBatchStock模型对象
     * @var \app\admin\model\kuerp\GoodsBatchStock
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsBatchStock;
        $this->view->assign("statusList", $this->model->getStatusList());

        $goodsList = $this->model->getGoodsList();
        $goodsLists = [];
        foreach ($goodsList as $goodsList_k=>$goodsList_v){
            $goodsLists[] = ['name'=>$goodsList_v['goods_name'],'value'=>$goodsList_v['id']];
        }
        $this->view->assign("goodsList", $goodsList);
        $this->assignconfig("goodsList", $goodsLists);

        $batchList = $this->model->getBatchList();
        $batchLists = [];
        foreach ($batchList as $batchList_k=>$batchList_v){
            $batchLists[] = ['name'=>$batchList_v['batch_name'],'value'=>$batchList_v['id']];
        }
        $this->view->assign("batchList", $batchList);
        $this->assignconfig("batchList", $batchLists);
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
            try {
                Db::execute("SET @@sql_mode='';");
            } catch (\Exception $e) {
            }
            $list = Db::name('kuerp_goods_batch_stock')->alias('goods_batch_stock')
                ->join('kuerp_goods goods','goods.id=goods_batch_stock.goods_id')
                ->join('kuerp_goods_batch goods_batch','goods_batch.id=goods_batch_stock.batch_id')
                ->where($where)
                ->group('goods_batch_stock.goods_id,goods_batch_stock.stores_id,goods_batch_stock.batch_id')
                ->field('sum(goods_batch_stock.stock) as stock,ANY_VALUE(goods_batch_stock.batch_id) as batch_id,ANY_VALUE(goods_batch_stock.goods_id) as goods_id,ANY_VALUE(goods_batch_stock.stores_id) as stores_id')
                ->select();

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

            $goods_id = isset($list[0]['goods_id']);
            $lists = [];$stock = 0;$stock_all = 0;
            foreach ($list as $list_k=>$list_v) {
                $stock_all += $list_v['stock'];
                if($goods_id!=$list_v['goods_id']){
                    $goods_id=$list_v['goods_id'];
                    $lists[] = ['store_name'=>'小计','stock'=>round($stock,$number_decimal)];
                    $stock = 0;
                }else{
                    $stock += $list_v['stock'];
                }
                $goods = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])->field('more_unit,unit_id,goods_code,goods_name,goods_barcode,goods_specification_id')->find();
                $list[$list_k]['goods_code'] = $goods['goods_code'];
                $list[$list_k]['goods_name'] = $goods['goods_name'];
                $list[$list_k]['goods_spec'] = $goods['goods_specification_id'];

                $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->field('id,name')->find();
                $list[$list_k]['unit_name'] = $unit['name'];

                $max_unit = 1;
                $unit_arr = [];
                $more_unit_num = '';
                if($unit){
                    $unit_arr[] = ['name'=>$unit['name'],'advance_rate'=>1];
                    if($goods['more_unit']==1){
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
                if(!isset($list[$list_k]['unitconversion'])){
                    $list[$list_k]['unitconversion'] = '';
                    $list[$list_k]['more_unit_num'] = '';
                }

                $stores = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('store_code,name')->find();
                $list[$list_k]['store_name'] = $stores['name'];

                $batch = Db::name('kuerp_goods_batch')->where(['id'=>$list_v['batch_id']])->field('batch_name,manufacture,guarantee,validity')->find();
                $list[$list_k]['batch_name'] = $batch['batch_name'];
                $time = ceil(($batch['validity']-time())/86400);
                $list[$list_k]['times'] = $time;
                $list[$list_k]['manufacture'] = date('Y-m-d',$batch['manufacture']);
                $list[$list_k]['guarantee'] = $batch['guarantee'];
                $list[$list_k]['validity'] = date('Y-m-d',$batch['validity']);

                $lists[] = $list[$list_k];
                if(($list_k+1)==sizeof($list)){
                    $goods_id=$list_v['goods_id'];
                    $lists[] = ['store_name'=>'小计','stock'=>round($stock,$number_decimal)];
                    $stock = 0;
                }
            }

            $result = array("total" => sizeof($list), "rows" => $lists,'stock_all'=>round($stock_all,$number_decimal));

            return json($result);
        }
        return $this->view->fetch();
    }

}
