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

        // 多规格商品：取所有规格中最低价格
        $goodsIds = array_column($list, 'id');
        $attrPriceMap = [];
        if (!empty($goodsIds)) {
            $attrPrices = Db::name('kuerp_goods_attr')
                ->where('goods_id', 'in', $goodsIds)
                ->field('goods_id, MIN(market_sell_price) as min_market_sell, MIN(wholesale_price) as min_wholesale, MIN(vip_price) as min_vip')
                ->group('goods_id')
                ->select();
            foreach ($attrPrices as $ap) {
                $attrPriceMap[$ap['goods_id']] = $ap;
            }
        }

        $customerId = $this->getCustomerId();
        $priceField = $this->getPriceField($customerId);

        foreach ($list as &$item) {
            $item['stock']     = isset($stocks[$item['goods_code']]) ? (float)$stocks[$item['goods_code']] : 0;
            $item['sell_out']  = $item['stock'] <= 0;

            // 多规格商品：使用规格中最低价格
            if (isset($attrPriceMap[$item['id']])) {
                $minPrices = $attrPriceMap[$item['id']];
                $item['market_sell_price'] = (float)$minPrices['min_market_sell'];
                $item['wholesale_price']   = (float)$minPrices['min_wholesale'];
                $item['vip_price']         = (float)$minPrices['min_vip'];
            }
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

        // 客户等级价格字段（提前计算，后续多处使用）
        $customerId = $this->getCustomerId();
        $priceField = $this->getPriceField($customerId);

        // 多规格商品：通过 aux_combination 解析规格名称，取最低价格，构建规格列表
        $attrs = [];
        $attrGroups = [];
        // auxiliary=1 为辅助属性（后台 add/edit 以此判断多规格），more_attribute 为备选
        if ($goods['auxiliary'] == 1 || $goods['more_attribute'] == 1) {
            // 1. 查询所有规格行
            $goodsAttrList = Db::name('kuerp_goods_attr')
                ->where('goods_id', $id)
                ->select();

            // 批量查各规格库存（goods_stock.attribute_id = goods_attr.id）
            $attrStockMap = [];
            if (!empty($goodsAttrList)) {
                $gaIds = array_column($goodsAttrList, 'id');
                $attrStocks = Db::name('kuerp_goods_stock')
                    ->where('goods_id', $id)
                    ->where('attribute_id', 'in', $gaIds)
                    ->group('attribute_id')
                    ->column('SUM(stock) as total_stock', 'attribute_id');
                $attrStockMap = $attrStocks ?: [];
            }

            // 2. 收集所有 attribute_id → aux_combination.id 列表
            $combinationIds = [];
            foreach ($goodsAttrList as $ga) {
                if ($ga['attribute_id']) {
                    $combinationIds[] = $ga['attribute_id'];
                }
            }
            $combinationIds = array_unique($combinationIds);

            // 3. 批量查 aux_combination → attr_ids（如 "2&5"）
            $combinationMap = [];
            if (!empty($combinationIds)) {
                $combinations = Db::name('kuerp_aux_combination')
                    ->where('id', 'in', $combinationIds)
                    ->column('attr_ids', 'id');
                $combinationMap = $combinations ?: [];
            }

            // 4. 收集所有辅助属性值 ID
            $allAuxIds = [];
            foreach ($combinationMap as $attrIds) {
                foreach (explode('&', $attrIds) as $aid) {
                    if ($aid !== '') {
                        $allAuxIds[] = (int)$aid;
                    }
                }
            }
            $allAuxIds = array_unique($allAuxIds);

            // 5. 批量查 auxiliary 属性值（name + pid）
            $auxiliaryMap = [];
            if (!empty($allAuxIds)) {
                $auxList = Db::name('kuerp_auxiliary')
                    ->where('id', 'in', $allAuxIds)
                    ->column('name,pid', 'id');
                foreach ($auxList as $auxId => $auxRow) {
                    $auxiliaryMap[$auxId] = $auxRow;
                }
            }

            // 6. 查询属性组（维度）名称
            $groupIds = [];
            foreach ($auxiliaryMap as $aux) {
                if (!empty($aux['pid'])) {
                    $groupIds[] = $aux['pid'];
                }
            }
            $groupIds = array_unique($groupIds);
            $groupNames = [];
            if (!empty($groupIds)) {
                $groupNames = Db::name('kuerp_auxiliary')
                    ->where('id', 'in', $groupIds)
                    ->column('name', 'id');
            }

            // 7. 遍历规格行：解析名称、计算最低价、归组属性值
            $groupValues = [];
            $minMarketSell = null;
            $minWholesale  = null;
            $minVip        = null;

            foreach ($goodsAttrList as $ga) {
                $attrName = '';
                $attrIds  = '';
                $auxValueIds = [];
                if (!empty($ga['attribute_id']) && isset($combinationMap[$ga['attribute_id']])) {
                    $attrIds = $combinationMap[$ga['attribute_id']];
                    $ids = explode('&', $attrIds);
                    $names = [];
                    foreach ($ids as $aid) {
                        $aid = (int)$aid;
                        $auxValueIds[] = $aid;
                        if (isset($auxiliaryMap[$aid])) {
                            $names[] = $auxiliaryMap[$aid]['name'];
                            $pid = $auxiliaryMap[$aid]['pid'] ?? 0;
                            if ($pid && !isset($groupValues[$pid])) {
                                $groupValues[$pid] = [];
                            }
                            if ($pid && !isset($groupValues[$pid][$aid])) {
                                $groupValues[$pid][$aid] = [
                                    'id'   => $aid,
                                    'name' => $auxiliaryMap[$aid]['name'],
                                ];
                            }
                        }
                    }
                    $attrName = implode('/', $names);
                }
                $ga['attr_name']     = $attrName;
                $ga['attr_ids']      = $attrIds;
                $ga['aux_value_ids'] = $auxValueIds;
                $ga['market_sell_price'] = (float)$ga['market_sell_price'];
                $ga['wholesale_price']   = (float)$ga['wholesale_price'];
                $ga['vip_price']         = (float)$ga['vip_price'];
                $ga['show_price']        = (float)$ga[$priceField];
                $ga['stock']            = isset($attrStockMap[$ga['id']]) ? (float)$attrStockMap[$ga['id']] : 0;
                $attrs[] = $ga;

                // 累计最低价（仅 > 0 参与比较）
                $msp = $ga['market_sell_price'];
                $wp  = $ga['wholesale_price'];
                $vp  = $ga['vip_price'];
                if ($msp > 0 && ($minMarketSell === null || $msp < $minMarketSell)) {
                    $minMarketSell = $msp;
                }
                if ($wp > 0 && ($minWholesale === null || $wp < $minWholesale)) {
                    $minWholesale = $wp;
                }
                if ($vp > 0 && ($minVip === null || $vp < $minVip)) {
                    $minVip = $vp;
                }
            }

            // 8. 构建属性组（维度 + 可选值列表）
            foreach ($groupValues as $pid => $values) {
                $attrGroups[] = [
                    'group_id'   => $pid,
                    'group_name' => $groupNames[$pid] ?? '',
                    'values'     => array_values($values),
                ];
            }

            // 9. 商品级价格 = 所有规格最低价
            if ($minMarketSell !== null) {
                $goods['market_sell_price'] = (float)$minMarketSell;
                $goods['wholesale_price']   = $minWholesale !== null ? (float)$minWholesale : (float)$minMarketSell;
                $goods['vip_price']         = $minVip !== null ? (float)$minVip : (float)$minMarketSell;
            }
        }

        // 价格
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

        // 图集
        $goodsImages = [];
        if (!empty($goods['goods_images'])) {
            $goodsImages = explode(',', $goods['goods_images']);
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'goods'         => $goods,
            'units'         => $units,
            'attr_groups'   => $attrGroups,
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
