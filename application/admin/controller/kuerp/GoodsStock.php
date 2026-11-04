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

    protected $noNeedRight = ['getgoods', 'total'];

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
        $this->view->assign("goodsList", $goodsList);
        //商品下拉只给有库存记录的商品，与「仓库/商品联动」的 getgoods 口径保持一致
        $this->assignconfig("goodsList", $this->getGoodsByStores());

        //合计已移到页面顶部的独立统计（GoodsStock::total()），表格不再用 totalRow 页脚合计
        //列：「所有仓库」全仓合计一组，之后每个仓库各一组（基本数量/单位成本/成本）
        //副单位、副单位数、商品条码列已去掉
        $rowlist = [
            ['field'=>'stock','title'=>'基本数量'],
            ['field'=>'unit_price','title'=>'单位成本'],
            ['field'=>'cost_money','title'=>'成本']
        ];
        $rowspan = [
            //fixed:'left' 冻结商品编号/名称列：列多横向滚动时始终可见（layui 2.8+ 复杂表头支持固定列）
            ['field'=>'goods_code','title'=>'商品编号','rowspan'=>2,'fixed'=>'left'],
            ['field'=>'goods_name','title'=>'商品名称','rowspan'=>2,'width'=>'120','fixed'=>'left'],
            ['field'=>'type_name','title'=>'商品类别','rowspan'=>2],
            ['field'=>'goods_specification_id','title'=>'型号','rowspan'=>2],
            ['field'=>'note','title'=>'商品备注','rowspan'=>2],
            ['field'=>'unit_name','title'=>'单位','rowspan'=>2],
            ['field'=>'stores_id','title'=>'所有仓库','colspan'=>3]
        ];
        //每个仓库一组列（与 index() 里写入的 stock{id}/unit_price{id}/cost_money{id} 对应）
        foreach ($storesList as $data_k=>$data_v){
            $rowspan[] = ['field'=>'stores_id'.$data_v['id'],'title'=>$data_v['name'],'colspan'=>3];
            $rowlist[] = ['field'=>'stock'.$data_v['id'],'title'=>'基本数量'];
            $rowlist[] = ['field'=>'unit_price'.$data_v['id'],'title'=>'单位成本'];
            $rowlist[] = ['field'=>'cost_money'.$data_v['id'],'title'=>'成本'];
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

            $where = $this->getSearchWhere();
            try {
                \think\Db::execute("SET @@sql_mode='';");
            } catch (\Exception $e) {
            }
            //分组行一次取出并显式排序（分页必须有序，否则翻页会重复/漏行）；总数=分组数，实际只渲染当前页
            $allList = Db::name('kuerp_goods_stock')->alias('goods_stock')
                ->join('kuerp_goods goods','goods.goods_code=goods_stock.goods_code')
                ->where($where)
                ->group('goods.goods_code,goods_stock.attribute_id')
                ->field('ANY_VALUE(goods.id) as goods_id,ANY_VALUE(goods_stock.goods_code) as goods_code,sum(goods_stock.stock) as stock,
                    sum(goods_stock.cost_money) as cost_money,ANY_VALUE(goods_stock.attribute_id) as attribute_id')
                ->order('goods_id','asc')
                ->select();
            $total = count($allList);
            //layui 分页传的是 page/limit（不是 offset），buildparams 只认 offset，这里按 page 换算
            $limit = (int)$limit;
            $page = (int)$this->request->get('page/d', 1);
            if ($page > 1 && $limit > 0) {
                $offset = ($page - 1) * $limit;
            }
            $offset = max(0, (int)$offset);
            $list = $limit > 0 ? array_slice($allList, $offset, $limit) : $allList;
            foreach ($list as $list_k=>$list_v) {
                $list[$list_k]['goods'] = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->find();
            }

            $settings = $this->getCostSettings();
            $price_decimal = $settings['price_decimal'];
            $number_decimal = $settings['number_decimal'];
            $storesList = $this->model->getStoresList();

            //分仓库数量：一次查出当前页所有商品(+属性)在各仓库的库存，避免逐行逐仓查库
            $pageGoodsCodes = [];
            foreach ($list as $list_v) {
                $pageGoodsCodes[$list_v['goods_code']] = $list_v['goods_code'];
            }
            $storeStockMap = [];
            if (!empty($pageGoodsCodes)) {
                $pageStoreRows = Db::name('kuerp_goods_stock')->alias('goods_stock')
                    ->join('kuerp_goods goods','goods.goods_code=goods_stock.goods_code')
                    ->where($where)
                    ->where('goods_stock.goods_code','in',array_values($pageGoodsCodes))
                    ->group('goods_stock.goods_code,goods_stock.attribute_id,goods_stock.stores_id')
                    ->field('goods_stock.goods_code,goods_stock.attribute_id,goods_stock.stores_id,sum(goods_stock.stock) as stock')
                    ->select();
                foreach ($pageStoreRows as $pageStoreRow) {
                    $storeStockMap[$pageStoreRow['goods_code'].'_'.$pageStoreRow['attribute_id']][$pageStoreRow['stores_id']] = $pageStoreRow['stock'];
                }
            }

            foreach ($list as $list_k=>$list_v){
                $unit_price = $this->getGoodsUnitPrice($list_v['goods'], $settings);
                $list[$list_k]['unit_price'] = round($unit_price,$price_decimal);
                $list[$list_k]['cost_money'] = round($list_v['stock']*$unit_price,$price_decimal);
                //各仓库的库存/成本：单位成本沿用该商品的单位成本（与原口径一致）
                $storeKey = $list_v['goods_code'].'_'.$list_v['attribute_id'];
                foreach ($storesList as $stores_v){
                    $store_stock = isset($storeStockMap[$storeKey][$stores_v['id']]) ? $storeStockMap[$storeKey][$stores_v['id']] : 0;
                    $list[$list_k]['stock'.$stores_v['id']] = round($store_stock,$number_decimal);
                    $list[$list_k]['unit_price'.$stores_v['id']] = round($unit_price,$price_decimal);
                    $list[$list_k]['cost_money'.$stores_v['id']] = round($store_stock*$unit_price,$price_decimal);
                }
                $list[$list_k]['type_name'] = Db::name('kuerp_goods_type')->where(['id'=>$list_v['goods']['goods_type_id']])->value('supplier_type_name');
                //单位信息
                $list[$list_k]['unit_name'] = Db::name('kuerp_unit')->where(['id'=>$list_v['goods']['unit_id']])->value('name');
                $list[$list_k]['goods_code'] = $list_v['goods']['goods_code'];
                $list[$list_k]['goods_name'] = $list_v['goods']['goods_name'];
                $list[$list_k]['goods_specification_id'] = $list_v['goods']['goods_specification_id'];
                $list[$list_k]['note'] = $list_v['goods']['note'];

                //按属性分组显示
                $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['attribute_id']])->value('attribute_id');
                $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
                if($attribute_ids){
                    $attribute_ids = explode('&',$attribute_ids);
                    $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                    $attribute_name = implode('/',$attribute);
                    $list[$list_k]['goods_name'] = $list_v['goods']['goods_name'].'_'.$attribute_name;
                }
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 顶部合计统计（全量计算，与列表分页解耦）
     * 列表翻页不调用本方法，只有页面加载/点刷新/点搜索时才调用
     */
    public function total()
    {
        $where = $this->getSearchWhere();
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {
        }
        $settings = $this->getCostSettings();
        $costPreload = $this->buildCostPreload();

        //每个商品(+属性)的库存合计
        $rows = Db::name('kuerp_goods_stock')->alias('goods_stock')
            ->join('kuerp_goods goods','goods.goods_code=goods_stock.goods_code')
            ->where($where)
            ->group('goods.goods_code,goods_stock.attribute_id')
            ->field('ANY_VALUE(goods_stock.goods_code) as goods_code,sum(goods_stock.stock) as stock,
                ANY_VALUE(goods_stock.attribute_id) as attribute_id')
            ->select();

        //一条 SQL 取所有商品+属性+仓库的库存，避免逐行逐仓库查库
        $storeRows = Db::name('kuerp_goods_stock')->alias('goods_stock')
            ->join('kuerp_goods goods','goods.goods_code=goods_stock.goods_code')
            ->where($where)
            ->group('goods_stock.goods_code,goods_stock.attribute_id,goods_stock.stores_id')
            ->field('goods_stock.goods_code,goods_stock.attribute_id,goods_stock.stores_id,sum(goods_stock.stock) as stock')
            ->select();
        $storeStockMap = [];
        foreach ($storeRows as $storeRow) {
            $storeStockMap[$storeRow['goods_code'].'_'.$storeRow['attribute_id']][$storeRow['stores_id']] = $storeRow['stock'];
        }

        //商品档案一次取回（统计要遍历全部商品，逐行查库会多 676 次查询）
        $goodsMap = [];
        foreach (Db::name('kuerp_goods')->field('id,goods_code')->select() as $goodsRow) {
            $goodsMap[$goodsRow['goods_code']] = $goodsRow;
        }

        $storesList = $this->model->getStoresList();
        $storeTotals = [];
        foreach ($storesList as $stores_v) {
            $storeTotals[$stores_v['id']] = ['stock' => 0, 'cost_money' => 0];
        }
        $stockTotal = 0;
        $costTotal = 0;
        foreach ($rows as $row) {
            if (!isset($goodsMap[$row['goods_code']])) {
                continue;
            }
            $goods = $goodsMap[$row['goods_code']];
            $unit_price = $this->getGoodsUnitPrice($goods, $settings, $costPreload);
            $stockTotal += $row['stock'];
            $costTotal += round($row['stock'] * $unit_price, $settings['price_decimal']);
            $storeKey = $row['goods_code'].'_'.$row['attribute_id'];
            if (isset($storeStockMap[$storeKey])) {
                foreach ($storeStockMap[$storeKey] as $stores_id => $store_stock) {
                    if (!isset($storeTotals[$stores_id])) {
                        $storeTotals[$stores_id] = ['stock' => 0, 'cost_money' => 0];
                    }
                    $storeTotals[$stores_id]['stock'] += $store_stock;
                    $storeTotals[$stores_id]['cost_money'] += round($store_stock * $unit_price, $settings['price_decimal']);
                }
            }
        }

        $stores = [];
        foreach ($storesList as $stores_v) {
            $stores[] = [
                'stores_id'     => $stores_v['id'],
                'name'          => $stores_v['name'],
                'stock'         => round(isset($storeTotals[$stores_v['id']]) ? $storeTotals[$stores_v['id']]['stock'] : 0, $settings['number_decimal']),
                'cost_money'    => round(isset($storeTotals[$stores_v['id']]) ? $storeTotals[$stores_v['id']]['cost_money'] : 0, $settings['price_decimal']),
            ];
        }

        return json(['code' => 1, 'msg' => '', 'data' => [
            'goods_count'   => count($rows),
            'stock'         => round($stockTotal, $settings['number_decimal']),
            'cost_money'    => round($costTotal, $settings['price_decimal']),
            'unit_price'    => $stockTotal > 0 ? round($costTotal / $stockTotal, $settings['price_decimal']) : 0,
            'stores'        => $stores,
        ]]);
    }

    /**
     * 列表/统计共用的搜索条件
     * @return array
     */
    protected function getSearchWhere()
    {
        $where = [];
        $searchdata = json_decode(input('filter'), true);
        if ($searchdata) {
            foreach ($searchdata as $k => $v) {
                if ($v !== '') {
                    if ($k == 'id') {
                        $where['goods_stock.'.$k] = ['<', $v];
                    } else if ($k == 'goods_id' || $k == 'stores_id') {
                        $where['goods_stock.'.$k] = ['in', $v];
                    } else if ($k == 'goods_type_id') {
                        $where['goods.'.$k] = ['in', $v];
                    } else {
                        $where['goods_stock.'.$k] = ['LIKE', "%{$v}%"];
                    }
                }
            }
        }
        return $where;
    }

    /**
     * 成本计算用到的系统参数（列表/统计共用，避免重复查 sets）
     * @return array
     */
    protected function getCostSettings()
    {
        $valuation_set = Db::name('kuerp_sets')->where(['name'=>'valuation'])->value('value');//计算方法
        if($valuation_set!=2){
            $valuation_set = 1;
        }
        $stores_set = Db::name('kuerp_sets')->where(['name'=>'stores'])->value('is_checked');//是否分仓
        if($stores_set!=1){
            $stores_set = 0;
        }
        return [
            'number_decimal' => Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value'),
            'price_decimal'  => Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value'),
            'checkdate'      => Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checktime'),
            'enable_time'    => Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value'),
            'valuation_set'  => $valuation_set,
            'stores_set'     => $stores_set,
            'price_taxes'    => Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked'),
            'taxes_set'      => Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find(),
            'stores_ids'     => Db::name('kuerp_stores')->where(['status'=>'1'])->column('id'),
        ];
    }

    /**
     * 单个商品的单位成本（口径与列表展示一致）
     * @param array $goods    商品档案行（需含 id/goods_code）
     * @param array $settings getCostSettings() 的返回值
     * @param array $preload  可选，批量预取的进货/期初/其他入库/组装成本汇总（见 buildCostPreload），传了就省掉逐商品查库
     * @return float
     */
    protected function getGoodsUnitPrice($goods, $settings, $preload = null)
    {
        $price_decimal = $settings['price_decimal'];
        if (!empty($preload)) {
            //汇总口径与下面逐商品查询逐字一致，只是改成一次 GROUP BY 取回
            $purchase_money = [
                'all_money'    => isset($preload['purchase'][$goods['goods_code']]) ? $preload['purchase'][$goods['goods_code']]['all_money'] : 0,
                'basic_nmber'  => isset($preload['purchase'][$goods['goods_code']]) ? $preload['purchase'][$goods['goods_code']]['basic_nmber'] : 0,
            ];
            $purchase_money['all_money'] += isset($preload['period'][$goods['id']]) ? $preload['period'][$goods['id']]['all_money'] : 0;
            $purchase_money['basic_nmber'] += isset($preload['period'][$goods['id']]) ? $preload['period'][$goods['id']]['basic_nmber'] : 0;
            $purchase_money['all_money'] += isset($preload['warehousing'][$goods['id']]) ? $preload['warehousing'][$goods['id']]['all_money'] : 0;
            $purchase_money['basic_nmber'] += isset($preload['warehousing'][$goods['id']]) ? $preload['warehousing'][$goods['id']]['basic_nmber'] : 0;
            $purchase_money['all_money'] += isset($preload['assemble'][$goods['id']]) ? $preload['assemble'][$goods['id']]['all_money'] : 0;
            $purchase_money['basic_nmber'] += isset($preload['assemble'][$goods['id']]) ? $preload['assemble'][$goods['id']]['basic_nmber'] : 0;
        } else {
            $purchase_money = Db::name('kuerp_goods_purchase_info')->where(['pi.goods_code'=>$goods['goods_code'],'p.examine_status'=>'1'])->alias('pi')
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
            $assemble_money = Db::name('kuerp_assemble_info')->where(['pi.goods_id'=>$goods['id'],'pi.type'=>'1','p.examine_status'=>'1'])->alias('pi')
                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                ->field('sum(pi.cost_money) as all_money,sum(pi.basic_number) as basic_nmber')->find();
            if($assemble_money){
                $purchase_money['all_money'] += $assemble_money['all_money'];$purchase_money['basic_nmber'] += $assemble_money['basic_nmber'];
            }
        }
        $result = \addons\kuerp\library\Goods::getGoodsCostPrice(
            $goods['goods_code'],
            $settings['number_decimal'],
            $price_decimal,
            $settings['checkdate'],
            $settings['enable_time'],
            $settings['valuation_set'],
            $settings['stores_set'],
            $settings['price_taxes'],
            $settings['taxes_set'],
            $settings['stores_ids']
        );
        if(isset($result[0]['cost_all']) && isset($result[0]['basic_nmber'])){
            $cost_money = $purchase_money['all_money']-$result[0]['cost_all'];
            $cost_number = $purchase_money['basic_nmber']-$result[0]['basic_nmber'];
        }else{
            $cost_money = $purchase_money['all_money'];
            $cost_number = $purchase_money['basic_nmber'];
        }
        return $cost_number != 0 ? round($cost_money/$cost_number,$price_decimal) : 0;
    }

    /**
     * 批量取成本汇总（进货/期初/其他入库/组装），供统计接口一次性算完全部商品
     * @return array
     */
    protected function buildCostPreload()
    {
        $preload = ['purchase' => [], 'period' => [], 'warehousing' => [], 'assemble' => []];

        $rows = Db::name('kuerp_goods_purchase_info')->alias('pi')
            ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
            ->where(['p.examine_status'=>'1'])
            ->group('pi.goods_code')
            ->field('pi.goods_code,sum(pi.all_money) as all_money,sum(pi.basic_nmber) as basic_nmber')
            ->select();
        foreach ($rows as $row) {
            $preload['purchase'][$row['goods_code']] = ['all_money' => $row['all_money'], 'basic_nmber' => $row['basic_nmber']];
        }

        $rows = Db::name('kuerp_goods_period')
            ->group('goods_id')
            ->field('goods_id,sum(price) as all_money,sum(stock) as basic_nmber')
            ->select();
        foreach ($rows as $row) {
            $preload['period'][$row['goods_id']] = ['all_money' => $row['all_money'], 'basic_nmber' => $row['basic_nmber']];
        }

        $rows = Db::name('kuerp_warehousing_info')->alias('pi')
            ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
            ->where(['p.examine_status'=>'1'])
            ->group('pi.goods_id')
            ->field('pi.goods_id,sum(pi.price) as all_money,sum(pi.basic_nmber) as basic_nmber')
            ->select();
        foreach ($rows as $row) {
            $preload['warehousing'][$row['goods_id']] = ['all_money' => $row['all_money'], 'basic_nmber' => $row['basic_nmber']];
        }

        //组装明细按商品ID汇总（只取组合件 type=1，子件成本已合并进组合件成本）
        $rows = Db::name('kuerp_assemble_info')->alias('pi')
            ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
            ->where(['p.examine_status'=>'1','pi.type'=>'1'])
            ->group('pi.goods_id')
            ->field('pi.goods_id,sum(pi.cost_money) as all_money,sum(pi.basic_number) as basic_nmber')
            ->select();
        foreach ($rows as $row) {
            $preload['assemble'][$row['goods_id']] = ['all_money' => $row['all_money'], 'basic_nmber' => $row['basic_nmber']];
        }

        return $preload;
    }

    /**
     * 按仓库获取商品列表（仓库/商品搜索联动）
     * stores_id 为空或未传 = 全部仓库下有库存记录的商品
     */
    public function getgoods()
    {
        return json(['code' => 1, 'msg' => '', 'data' => $this->getGoodsByStores(input('stores_id', ''))]);
    }

    /**
     * 取指定仓库下有库存记录的商品（返回 xmSelect 的 data 结构）
     * @param string $stores_id 仓库ID，多个用逗号分隔；留空=不限仓库
     * @return array
     */
    protected function getGoodsByStores($stores_id = '')
    {
        $stores_ids = [];
        foreach (explode(',', (string)$stores_id) as $stores_id_v) {
            $stores_id_v = (int)$stores_id_v;
            if ($stores_id_v > 0 && !in_array($stores_id_v, $stores_ids)) {
                $stores_ids[] = $stores_id_v;
            }
        }
        $query = Db::name('kuerp_goods_stock')->alias('goods_stock')
            ->join('kuerp_goods goods', 'goods.goods_code=goods_stock.goods_code')
            ->where('goods.status', '1');
        if (!empty($stores_ids)) {
            $query->where('goods_stock.stores_id', 'in', $stores_ids);
        }
        $rows = $query->group('goods.id')
            ->field('goods.id,goods.goods_name')
            ->order('goods.id', 'asc')
            ->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['name' => $row['goods_name'], 'value' => $row['id']];
        }
        return $list;
    }

}
