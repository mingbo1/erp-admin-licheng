<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品计算器
 *
 * @icon fa fa-calculator
 */
class GoodsCalculator extends Backend
{
    protected $noNeedRight = ['calculate', 'getbomlist'];
    /**
     * GoodsCalculator模型对象（复用GoodsBom）
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsBom;
    }

    /**
     * 计算器页面
     */
    public function index()
    {
        // 获取所有BOM模板列表供选择
        $bomList = Db::name('kuerp_goods_bom')
            ->field('id,bom_name,goods_code')
            ->select();
        $this->view->assign("bomList", $bomList);

        // 获取仓库列表（分仓模式使用，只显示零件仓）
        $stores = Db::name('kuerp_stores')->where(['status' => 1, 'store_type' => 'part'])->field('id,name')->select();
        $this->view->assign("stores", $stores);

        $setList = \addons\kuerp\library\Goods::getSetData();
        $this->view->assign("is_branch", isset($setList['setList']['stores']) ? $setList['setList']['stores'] : 0);

        return $this->view->fetch();
    }

    /**
     * 执行计算
     */
    public function calculate()
    {
        $bomId = $this->request->request('bom_id');
        $storesId = $this->request->request('stores_id', '');

        if (empty($bomId)) {
            $this->error('请选择BOM模板');
        }

        // 1. 查询BOM模板
        $bom = Db::name('kuerp_goods_bom')->where(['id' => $bomId])->find();
        if (empty($bom)) {
            $this->error('未找到该BOM模板');
        }

        // 2. 查询父商品
        $parent = Db::name('kuerp_goods_bom_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.bom_id' => $bomId, 'a.type' => '1'])
            ->field('a.*,g.goods_name as parent_name,g.product_type')
            ->find();

        // 3. 查询子商品
        $children = Db::name('kuerp_goods_bom_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.bom_id' => $bomId, 'a.type' => '2'])
            ->field('a.*,g.goods_name,g.product_type,g.unit_id as goods_unit_id')
            ->select();

        if (empty($children)) {
            $this->error('该BOM模板没有子商品，请先配置');
        }

        // 4. 查询每个子商品的库存
        $resultChildren = [];
        $maxProducibleList = [];

        foreach ($children as $child) {
            // 查询库存：只统计零件仓的库存数量
            $stockQuery = Db::name('kuerp_goods_stock')->alias('gs')
                ->join('kuerp_stores s', 's.id=gs.stores_id')
                ->where(['gs.goods_code' => $child['goods_code'], 's.store_type' => 'part']);

            // 如果选择了指定仓库（进一步限定在零件仓范围内）
            if (!empty($storesId)) {
                $stockQuery->where(['gs.stores_id' => $storesId]);
            }

            $totalStock = $stockQuery->sum('gs.stock');
            $totalStock = $totalStock ? floatval($totalStock) : 0;

            // 单耗数量
            $consumption = floatval($child['number']);

            // 计算该子件可支持生产的成品数量
            if ($consumption > 0) {
                $producible = floor($totalStock / $consumption);
            } else {
                $producible = 0;
            }

            $maxProducibleList[] = $producible;

            $resultChildren[] = [
                'goods_id' => $child['goods_id'],
                'goods_code' => $child['goods_code'],
                'goods_name' => $child['goods_name'],
                'product_type' => $child['product_type'],
                'consumption' => $consumption,
                'total_stock' => $totalStock,
                'producible' => $producible,
                'unit_id' => $child['unit_id'] ?: $child['goods_unit_id'],
            ];
        }

        // 5. 计算最大可组装数 = MIN(所有子件的可生产数)
        $maxAssembleCount = !empty($maxProducibleList) ? min($maxProducibleList) : 0;

        // 6. 确定瓶颈商品（可生产数等于最大值的所有子件都是瓶颈）
        $bottleneckGoods = [];
        foreach ($resultChildren as $child) {
            if ($child['producible'] == $maxAssembleCount) {
                $bottleneckGoods[] = $child['goods_name'];
            }
        }

        $result = [
            'bom_name' => $bom['bom_name'],
            'parent_goods_name' => $parent ? $parent['parent_name'] : '',
            'parent_goods_code' => $bom['goods_code'],
            'max_assemble_count' => $maxAssembleCount,
            'bottleneck' => $bottleneckGoods,
            'children' => $resultChildren,
            'stores_id' => $storesId,
        ];

        $this->success('计算完成', '',$result);
    }

    /**
     * 获取BOM模板列表（供前端下拉选择）
     */
    public function getbomlist()
    {
        $list = Db::name('kuerp_goods_bom')
            ->field('id,bom_name,goods_code')
            ->order('id', 'desc')
            ->select();
        $this->success('', '',$list);
    }
}
