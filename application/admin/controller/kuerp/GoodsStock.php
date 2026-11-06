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
        //列：「所有仓库」全仓合计一组，之后每个仓库各一组
        //价格（单位成本/成本）相关统计已全部去掉，只保留数量
        //副单位、副单位数、商品条码列已去掉
        $rowlist = [
            ['field'=>'stock','title'=>'基本数量']
        ];
        $rowspan = [
            //fixed:'left' 冻结商品编号/名称列：列多横向滚动时始终可见（layui 2.8+ 复杂表头支持固定列）
            ['field'=>'goods_code','title'=>'商品编号','rowspan'=>2,'fixed'=>'left'],
            ['field'=>'goods_name','title'=>'商品名称','rowspan'=>2,'width'=>'120','fixed'=>'left'],
            ['field'=>'type_name','title'=>'商品类别','rowspan'=>2],
            //型号加宽，避免长型号（含厂商/封装后缀）被省略号截断
            ['field'=>'goods_specification_id','title'=>'型号','rowspan'=>2,'width'=>'320'],
            ['field'=>'vendor_name','title'=>'规格2/厂商','rowspan'=>2,'width'=>'150'],
            ['field'=>'packaging','title'=>'规格3/封装','rowspan'=>2,'width'=>'150'],
            ['field'=>'note','title'=>'商品备注','rowspan'=>2],
            ['field'=>'unit_name','title'=>'单位','rowspan'=>2],
            ['field'=>'stores_id','title'=>'所有仓库','colspan'=>1]
        ];
        //每个仓库一组列（与 index() 里写入的 stock{id} 对应）
        foreach ($storesList as $data_k=>$data_v){
            $rowspan[] = ['field'=>'stores_id'.$data_v['id'],'title'=>$data_v['name'],'colspan'=>1];
            $rowlist[] = ['field'=>'stock'.$data_v['id'],'title'=>'基本数量'];
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
                    ANY_VALUE(goods_stock.attribute_id) as attribute_id')
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

            //价格相关统计已去掉，只需要数量小数位
            $number_decimal = $this->getNumberDecimal();
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
                //各仓库的库存（价格相关列已去掉）
                $storeKey = $list_v['goods_code'].'_'.$list_v['attribute_id'];
                foreach ($storesList as $stores_v){
                    $store_stock = isset($storeStockMap[$storeKey][$stores_v['id']]) ? $storeStockMap[$storeKey][$stores_v['id']] : 0;
                    $list[$list_k]['stock'.$stores_v['id']] = round($store_stock,$number_decimal);
                }
                $list[$list_k]['type_name'] = Db::name('kuerp_goods_type')->where(['id'=>$list_v['goods']['goods_type_id']])->value('supplier_type_name');
                //单位信息
                $list[$list_k]['unit_name'] = Db::name('kuerp_unit')->where(['id'=>$list_v['goods']['unit_id']])->value('name');
                $list[$list_k]['goods_code'] = $list_v['goods']['goods_code'];
                $list[$list_k]['goods_name'] = $list_v['goods']['goods_name'];
                $list[$list_k]['goods_specification_id'] = $list_v['goods']['goods_specification_id'];
                //规格2/厂商、规格3/封装（取商品档案，空值原样留空）
                $list[$list_k]['vendor_name'] = $list_v['goods']['vendor_name'];
                $list[$list_k]['packaging'] = $list_v['goods']['packaging'];
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
     * 价格相关统计（成本合计/平均单位成本）已去掉，只统计品种与库存数量
     */
    public function total()
    {
        $where = $this->getSearchWhere();
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {
        }
        $number_decimal = $this->getNumberDecimal();

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

        $storesList = $this->model->getStoresList();
        $storeTotals = [];
        foreach ($storesList as $stores_v) {
            $storeTotals[$stores_v['id']] = 0;
        }
        $stockTotal = 0;
        foreach ($rows as $row) {
            $stockTotal += $row['stock'];
        }
        foreach ($storeRows as $storeRow) {
            if (!isset($storeTotals[$storeRow['stores_id']])) {
                $storeTotals[$storeRow['stores_id']] = 0;
            }
            $storeTotals[$storeRow['stores_id']] += $storeRow['stock'];
        }

        $stores = [];
        foreach ($storesList as $stores_v) {
            $stores[] = [
                'stores_id'     => $stores_v['id'],
                'name'          => $stores_v['name'],
                'stock'         => round(isset($storeTotals[$stores_v['id']]) ? $storeTotals[$stores_v['id']] : 0, $number_decimal),
            ];
        }

        return json(['code' => 1, 'msg' => '', 'data' => [
            'goods_count'   => count($rows),
            'stock'         => round($stockTotal, $number_decimal),
            'stores'        => $stores,
        ]]);
    }

    /**
     * 数量小数位（列表/统计共用）
     * @return int
     */
    protected function getNumberDecimal()
    {
        return (int)Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value');
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
        //规格型号是商品档案(kuerp_goods)的字段，goods_stock 表没有该列，走独立查询参数
        //（列表与顶部统计共用本方法，两处口径一致）
        $goodsSpec = trim((string)input('goods_specification_id'));
        if ($goodsSpec !== '') {
            $where['goods.goods_specification_id'] = ['LIKE', "%{$goodsSpec}%"];
        }
        return $where;
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
