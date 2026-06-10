<?php

namespace addons\kuerp\controller\api;

use think\Db;

class Goods extends Base
{
    /**
     * 商品列表
     * GET /api/kuerp/goods/index
     */
    public function index()
    {
        $page       = $this->request->get('page/d', 1);
        $limit      = $this->request->get('limit/d', 20);
        $categoryId = $this->request->get('category_id/d', 0);
        $keyword    = $this->request->get('keyword', '');
        $sortField  = $this->request->get('sort_field', '');
        $sortOrder  = $this->request->get('sort_order', 'asc');

        $where = [];

        if ($categoryId > 0) {
            $where['g.goods_type_id'] = $categoryId;
        }

        $order = 'g.createtime DESC';
        if ($sortField == 'sale_price') {
            $order = 'g.market_sell_price ' . ($sortOrder == 'desc' ? 'DESC' : 'ASC') . ', g.createtime DESC';
        }

        $list = Db::name('kuerp_goods')
            ->alias('g')
            ->field('g.id, g.goods_code, g.goods_name, g.goods_image, g.market_sell_price, g.wholesale_price, g.vip_price, g.unit_id, g.status')
            ->where('g.status', '1')
            ->where('g.is_show', '1')
            ->where($where)
            ->where(function ($query) use ($keyword) {
                if ($keyword) {
                    $query->where('g.goods_code', 'like', "%{$keyword}%")
                        ->whereOr('g.goods_name', 'like', "%{$keyword}%");
                }
            })
            ->orderRaw($order)
            ->page($page, $limit)
            ->select();

        // 查询库存
        $goodsCodes = array_column($list, 'goods_code');
        if (!empty($goodsCodes)) {
            $stocks = Db::name('kuerp_goods_stock')
                ->where('goods_code', 'in', $goodsCodes)
                ->group('goods_code')
                ->column('SUM(stock) as total_stock', 'goods_code');
        } else {
            $stocks = [];
        }

        $customerId = $this->getCustomerId();
        $priceField = $this->getPriceField($customerId);

        foreach ($list as &$item) {
            $item['stock']     = isset($stocks[$item['goods_code']]) ? (float)$stocks[$item['goods_code']] : 0;
            $item['sell_out']  = $item['stock'] <= 0;
            $item['show_price'] = (float)$item[$priceField];
        }
        unset($item);

        $total = Db::name('kuerp_goods')
            ->alias('g')
            ->where('g.status', '1')
            ->where('g.is_show', '1')
            ->where($where)
            ->where(function ($query) use ($keyword) {
                if ($keyword) {
                    $query->where('g.goods_code', 'like', "%{$keyword}%")
                        ->whereOr('g.goods_name', 'like', "%{$keyword}%");
                }
            })
            ->count();

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]]);
    }

    /**
     * 商品详情
     * GET /api/kuerp/goods/detail
     */
    public function detail()
    {
        $id = $this->request->get('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $goods = Db::name('kuerp_goods')->where('id', $id)->where('status', '1')->where('is_show', '1')->find();
        if (!$goods) {
            return json(['code' => 1, 'msg' => '商品不存在或已下架']);
        }

        // 库存
        $stock = Db::name('kuerp_goods_stock')
            ->where('goods_code', $goods['goods_code'])
            ->group('goods_code')
            ->value('SUM(stock) as total_stock');
        $goods['stock']    = (float)$stock;
        $goods['sell_out'] = $goods['stock'] <= 0;

        // 价格
        $customerId = $this->getCustomerId();
        $priceField = $this->getPriceField($customerId);
        $goods['show_price'] = (float)$goods[$priceField];

        // 单位信息
        $units = [];
        if ($goods['more_unit'] == 1) {
            // 多计量单位
            $conv = Db::name('kuerp_unit_conversion')->where('id', $goods['more_unit_id'])->find();
            if ($conv) {
                $unitIds = [$conv['main_unit'], $conv['vice_unit']];
                $units = Db::name('kuerp_unit')->where('id', 'in', $unitIds)->field('id, name')->select();
                foreach ($units as &$unit) {
                    if ($unit['id'] == $conv['main_unit']) {
                        $unit['advance_rate'] = 1;
                        $unit['is_default']   = 1;
                    } else {
                        $unit['advance_rate'] = $conv['advance_rate'];
                        $unit['is_default']   = 0;
                    }
                    // 查价格
                    $priceRow = Db::name('kuerp_goods_price')
                        ->where('goods_id', $id)
                        ->where('unit', $unit['id'])
                        ->find();
                    $unit['market_sell_price']  = $priceRow ? (float)$priceRow['market_sell_price'] : 0;
                    $unit['wholesale_price']    = $priceRow ? (float)$priceRow['wholesale_price'] : 0;
                    $unit['vip_price']          = $priceRow ? (float)$priceRow['vip_price'] : 0;
                    $unit['show_price']         = $unit[$priceField] ?? 0;
                }
                unset($unit);
            }
        } else {
            // 单计量单位
            $unit = Db::name('kuerp_unit')->where('id', $goods['unit_id'])->field('id, name')->find();
            $units[] = [
                'id'                  => $unit['id'],
                'name'                => $unit['name'],
                'advance_rate'        => 1,
                'is_default'          => 1,
                'market_sell_price'   => (float)$goods['market_sell_price'],
                'wholesale_price'     => (float)$goods['wholesale_price'],
                'vip_price'           => (float)$goods['vip_price'],
                'show_price'          => (float)$goods[$priceField],
            ];
        }

        // 属性
        $attrs = Db::name('kuerp_goods_attr')
            ->alias('ga')
            ->join('kuerp_auxiliary a', 'ga.attribute_id = a.id', 'LEFT')
            ->where('ga.goods_id', $id)
            ->field('ga.id, ga.attribute_id, ga.attr_code, a.name as attr_group_name')
            ->select();

        // 图集
        $goodsImages = [];
        if (!empty($goods['goods_images'])) {
            $goodsImages = explode(',', $goods['goods_images']);
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'goods'         => $goods,
            'units'         => $units,
            'attrs'         => $attrs,
            'goods_images'  => $goodsImages,
            'content'       => $goods['content'] ?? '',
        ]]);
    }

    /**
     * 获取商品分类
     * GET /api/kuerp/goods/categories
     */
    public function categories()
    {
        $list = Db::name('kuerp_goods_type')
            ->where('status', 1)
            ->field('id, supplier_type_name as name, pid')
            ->order('weigh DESC, id ASC')
            ->select();

        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }

}
