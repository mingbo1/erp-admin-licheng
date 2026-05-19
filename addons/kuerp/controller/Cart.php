<?php

namespace addons\kuerp\controller;

use addons\kuerp\model\MiniCart;
use addons\kuerp\model\Goods;
class Cart extends Base
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function index()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        $list = MiniCart::with(['goods'])->where('user_id', $this->auth->id)->order('id', 'desc')->select();
        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }

    public function add()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        $goodsId  = $this->request->post('goods_id/d', 0);
        $quantity = $this->request->post('quantity/f', 1);

        if (!$goodsId || $quantity <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $goods = Goods::where('status', 'normal')->find($goodsId);
        if (!$goods) {
            return json(['code' => 1, 'msg' => '商品不存在或已下架']);
        }
        if ($goods->max_stock > 0 && $quantity > $goods->max_stock) {
            return json(['code' => 1, 'msg' => '数量不能超过库存上限']);
        }

        $cart = MiniCart::where('user_id', $this->auth->id)->where('goods_id', $goodsId)->find();
        if ($cart) {
            $cart->quantity  = $quantity;
            $cart->updatetime = time();
            $cart->save();
        } else {
            $cart = MiniCart::create([
                'user_id'     => $this->auth->id,
                'goods_id'    => $goodsId,
                'quantity'    => $quantity,
                'createdtime' => time(),
                'updatetime'  => time(),
            ]);
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => ['cart' => $cart]]);
    }

    public function edit()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        $id       = $this->request->post('id/d', 0);
        $quantity = $this->request->post('quantity/f', 1);

        if (!$id || $quantity <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $cart = MiniCart::with(['goods'])->where('id', $id)->where('user_id', $this->auth->id)->find();
        if (!$cart) {
            return json(['code' => 1, 'msg' => '购物车记录不存在']);
        }
        if ($cart->goods && $cart->goods->max_stock > 0 && $quantity > $cart->goods->max_stock) {
            return json(['code' => 1, 'msg' => '数量不能超过库存上限']);
        }

        $cart->quantity  = $quantity;
        $cart->updatetime = time();
        $cart->save();

        return json(['code' => 0, 'msg' => '成功', 'data' => ['cart' => $cart]]);
    }

    public function delete()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }
        MiniCart::where('id', $id)->where('user_id', $this->auth->id)->delete();
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function clear()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        MiniCart::where('user_id', $this->auth->id)->delete();
        return json(['code' => 0, 'msg' => '已清空']);
    }
}
