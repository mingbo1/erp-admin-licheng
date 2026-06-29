<?php

namespace addons\kuerp\controller\api;

use addons\kuerp\model\MiniCart;
use think\Db;

class Cart extends Base
{
    protected $noNeedLogin = [];
    /**
     * 购物车列表
     * GET /api/kuerp/cart/index
     */
    public function index()
    {
        $ids = $this->request->param('ids', '');
    

        $list = Db::name('kuerp_mini_cart')
            ->alias('c')
            ->join('kuerp_goods g', 'c.goods_id = g.id', 'LEFT')
            ->where('c.user_id', $this->auth->id)
            ->field('c.*, g.goods_code, g.goods_name, g.goods_image, g.market_sell_price, g.wholesale_price, g.vip_price, g.status as goods_status')
            ->order('c.id', 'desc')
            ->where(function ($query) use ($ids) {
                if ($ids) {
                    $query->where('c.id', 'in', $ids);
                }
            })
            ->select();

        $customerId = $this->getCustomerId();
        $priceField = $this->getPriceField($customerId);

        foreach ($list as &$item) {
            // 多规格：从 goods_attr 获取 SKU 价格和库存
            if (!empty($item['attr_ids'])) {
                $attrIdsStr = htmlspecialchars_decode($item['attr_ids']);
                $combinationId = Db::name('kuerp_aux_combination')
                    ->where('attr_ids', $attrIdsStr)
                    ->value('id');
                if ($combinationId) {
                    $goodsAttr = Db::name('kuerp_goods_attr')
                        ->where('goods_id', $item['goods_id'])
                        ->where('attribute_id', $combinationId)
                        ->find();
                    if ($goodsAttr) {
                        $item['show_price'] = (float)$goodsAttr[$priceField];
                        $stock = Db::name('kuerp_goods_stock')
                            ->where('goods_id', $item['goods_id'])
                            ->where('attribute_id', $goodsAttr['id'])
                            ->value('SUM(stock) as total_stock');
                    }
                }
            }
            // 非多规格或 SKU 未找到：使用商品级价格和总库存
            if (!isset($item['show_price'])) {
                $item['show_price'] = (float)$item[$priceField];
            }
            if (!isset($stock)) {
                $stock = Db::name('kuerp_goods_stock')
                    ->where('goods_code', $item['goods_code'])
                    ->value('SUM(stock) as total_stock');
            }
            $item['stock']    = (float)$stock;
            $item['sell_out'] = $item['goods_status'] != '1';

            // 属性和单位名称作为快照展示
            if ($item['unit_name'] && $item['unit_name'] != '0') {
                // 有快照单位名
            } else {
                $unit = Db::name('kuerp_unit')->where('id', $item['goods_unit'])->value('name');
                $item['unit_name'] = $unit ?: '';
            }
            unset($stock);
        }
        unset($item);

        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }

    /**
     * 加入购物车
     * POST /api/kuerp/cart/add
     */
    public function add()
    {
        $goodsId   = $this->request->post('goods_id/d', 0);
        $quantity  = $this->request->post('quantity/f', 1);
        $attrIds   = htmlspecialchars_decode($this->request->post('attr_ids/s', ''));
        $goodsUnit = $this->request->post('goods_unit/d', 0);

        if (!$goodsId || $quantity <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $goods = Db::name('kuerp_goods')->where('id', $goodsId)->where('status', '1')->find();
        if (!$goods) {
            return json(['code' => 1, 'msg' => '商品不存在或已下架']);
        }

        // 检查库存
        $skuStockChecked = false;
        if ($attrIds) {
            $combinationId = Db::name('kuerp_aux_combination')
                ->where('attr_ids', $attrIds)
                ->value('id');
            if ($combinationId) {
                $goodsAttrId = Db::name('kuerp_goods_attr')
                    ->where('goods_id', $goodsId)
                    ->where('attribute_id', $combinationId)
                    ->value('id');
                if ($goodsAttrId) {
                    $stock = Db::name('kuerp_goods_stock')
                        ->where('goods_id', $goodsId)
                        ->where('attribute_id', $goodsAttrId)
                        ->value('SUM(stock) as total_stock');
                    $skuStockChecked = true;
                }
            }
        }
        if (!$skuStockChecked) {
            $stock = Db::name('kuerp_goods_stock')
                ->where('goods_code', $goods['goods_code'])
                ->value('SUM(stock) as total_stock');
        }
        if ((float)$stock <= 0) {
            return json(['code' => 1, 'msg' => '商品已售罄']);
        }

        // 属性名称快照
        $attrName = '';
        if ($attrIds) {
            $ids = explode('&', $attrIds);
            $names = Db::name('kuerp_auxiliary')->where('id', 'in', $ids)->column('name');
            $attrName = implode('/', $names);
        }

        // 单位名称快照
        $unitName = '';
        if ($goodsUnit > 0) {
            $unitName = Db::name('kuerp_unit')->where('id', $goodsUnit)->value('name') ?: '';
        }

        // 查找是否存在相同规格
        $where = [
            'user_id'    => $this->auth->id,
            'goods_id'   => $goodsId,
            'attr_ids'   => $attrIds,
            'goods_unit' => $goodsUnit,
        ];
        $cart = MiniCart::where($where)->find();

        if ($cart) {
            $cart->quantity   = $quantity;
            $cart->attr_name  = $attrName;
            $cart->unit_name  = $unitName;
            $cart->updatetime = time();
            $cart->save();
        } else {
            $cart = MiniCart::create([
                'user_id'     => $this->auth->id,
                'goods_id'    => $goodsId,
                'attr_ids'    => $attrIds,
                'attr_name'   => $attrName,
                'goods_unit'  => $goodsUnit,
                'unit_name'   => $unitName,
                'quantity'    => $quantity,
                'createdtime' => time(),
                'updatetime'  => time(),
            ]);
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => ['cart' => $cart]]);
    }

    /**
     * 修改购物车
     * POST /api/kuerp/cart/edit
     */
    public function edit()
    {
        $id       = $this->request->post('id/d', 0);
        $quantity = $this->request->post('quantity/f', 1);

        if (!$id || $quantity <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $cart = MiniCart::where('id', $id)->where('user_id', $this->auth->id)->find();
        if (!$cart) {
            return json(['code' => 1, 'msg' => '购物车记录不存在']);
        }

        $cart->quantity  = $quantity;
        $cart->updatetime = time();
        $cart->save();

        return json(['code' => 0, 'msg' => '成功', 'data' => ['cart' => $cart]]);
    }

    /**
     * 删除购物车商品
     * POST /api/kuerp/cart/delete
     */
    public function delete()
    {
        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        MiniCart::where('id', $id)->where('user_id', $this->auth->id)->delete();
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 清空购物车
     * POST /api/kuerp/cart/clear
     */
    public function clear()
    {

        MiniCart::where('user_id', $this->auth->id)->delete();
        return json(['code' => 0, 'msg' => '已清空']);
    }

    /**
     * 获取购物车数量
     * GET /api/kuerp/cart/count
     */
    public function count()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 0, 'msg' => '成功', 'data' => ['count' => 0]]);
        }
        $count = MiniCart::where('user_id', $this->auth->id)->count();
        return json(['code' => 0, 'msg' => '成功', 'data' => ['count' => $count]]);
    }

}
