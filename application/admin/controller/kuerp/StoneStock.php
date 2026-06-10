<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 库存查询（石材库存实时查看）
 *
 * @icon fa fa-database
 */
class StoneStock extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['*'];
    protected $searchFields = 'goods_code|goods_name';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\kuerp\StoneStock;
    }

    /**
     * 库存列表
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            // 仓库筛选
            $storesList = Db::name('kuerp_stores')->where('status', 1)->field('id,name')->select();
            $this->view->assign('storesList', $storesList);
            // 商品分类
            $typeList = Db::name('kuerp_goods_type')->where('status', 1)->field('id,supplier_type_name as name')->select();
            $this->view->assign('typeList', $typeList);
            return $this->view->fetch();
        }

        list($where, $sort, $order, $offset, $limit) = $this->buildparams();

        $storeId = $this->request->get('store_id/d', 0);
        $typeId = $this->request->get('goods_type_id/d', 0);

        $goodsWhere = [];
        if ($typeId > 0) {
            $goodsWhere['g.goods_type_id'] = $typeId;
        }

        $query = Db::name('kuerp_goods_stock')
            ->alias('s')
            ->join('kuerp_goods g', 's.goods_code = g.goods_code', 'LEFT')
            ->join('kuerp_stores st', 's.stores_id = st.id', 'LEFT')
            ->field('s.id, s.goods_code, s.stores_id, s.stock,
                     g.id as gid, g.goods_name, g.goods_image, g.unit_id, g.purchase, g.market_sell_price,
                     st.name as store_name')
            ->where('g.status', 1);

        if (!empty($where)) {
            $query->where($where);
        }
        if ($storeId > 0) {
            $query->where('s.stores_id', $storeId);
        }
        if (!empty($goodsWhere)) {
            $query->where($goodsWhere);
        }

        $total = $query->count();
        $list = $query->order('s.goods_code', 'asc')->limit($offset, $limit)->select();

        foreach ($list as &$item) {
            $unitName = Db::name('kuerp_unit')->where('id', $item['unit_id'])->value('name');
            $item['unit_name'] = $unitName ?: '';
            $item['stock_amount'] = round((float)$item['stock'] * (float)$item['purchase'], 2);

            $inQty = Db::name('kuerp_stone_inbound_info')
                ->where('goods_code', $item['goods_code'])
                ->sum('quantity');
            $outQty = Db::name('kuerp_stone_outbound_info')
                ->where('goods_code', $item['goods_code'])
                ->sum('quantity');
            $item['total_in']  = (float)$inQty;
            $item['total_out'] = (float)$outQty;
        }
        unset($item);

        return json(['total' => $total, 'rows' => $list]);
    }
}
