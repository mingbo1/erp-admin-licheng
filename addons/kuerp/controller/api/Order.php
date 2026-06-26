<?php

namespace addons\kuerp\controller\api;

use addons\kuerp\model\MiniCart;
use addons\kuerp\model\MiniDelivery;
use addons\kuerp\model\MiniOrder;
use addons\kuerp\model\MiniOrderGoods;
use think\Db;

class Order extends Base
{
    // 后台管理接口无需登录校验
    protected $noNeedLogin = [];

    /**
     * 提交订单
     * POST /api/kuerp/order/add
     */
    public function add()
    {
      

        $addressId   = $this->request->post('address_id/d', 0);
        $remark      = $this->request->post('remark', '');
        $deliveryType = 'logistics'; // 配送方式由后台发货时选择
        // 商品列表（兼容 form-urlencoded 嵌套数组 和 JSON 字符串两种格式）
        $goodsData   = $this->request->post('goods', '');
        $goodsList   = [];
        if ($goodsData) {
            if (is_array($goodsData)) {
                // form-urlencoded 提交：PHP 已自动解析为数组
                $goodsList = $goodsData;
            } else {
                // JSON 字符串提交（引号可能被 HTML 实体编码）
                $decoded = json_decode(htmlspecialchars_decode($goodsData), true);
                if (is_array($decoded)) {
                    $goodsList = $decoded;
                }
            }
        }
        if (empty($goodsList)) {
            $cartList = MiniCart::where('user_id', $this->auth->id)->select();
            if ($cartList && !empty($cartList)) {
                $goodsList = collection($cartList)->toArray();
            }
        }
        if (empty($goodsList)) {
            return json(['code' => 1, 'msg' => '商品列表为空']);
        }

        if (!$addressId) {
            return json(['code' => 1, 'msg' => '请选择收货地址']);
        }

        $customerId = $this->getCustomerId();
        if (!$customerId) {
            return json(['code' => 1, 'msg' => '客户信息不存在']);
        }

        // 获取收货地址
        $address = Db::name('kuerp_customers_user')->where('id', $addressId)->where('sid', $customerId)->find();
        if (!$address) {
            return json(['code' => 1, 'msg' => '收货地址不存在']);
        }

        // 解析地址中的省市
        $addrParts = preg_split('/\s+/', $address['connect_address'], 4);
        $province  = $addrParts[0] ?? '';
        $city      = $addrParts[1] ?? '';
        $district  = $addrParts[2] ?? '';
        $street    = $addrParts[3] ?? $address['connect_address'];

        $priceField = $this->getPriceField($customerId);

        Db::startTrans();
        try {
            // 生成订单号
            $orderNo = $this->generateOrderNo();

            // 计算金额
            $totalAmount  = 0;
            $orderGoods   = [];
            foreach ($goodsList as $item) {
                $goodsId   = $item['goods_id'] ?? $item['id'];
                $quantity  = $item['quantity'] ?? 1;
                $attrIds   = htmlspecialchars_decode($item['attr_ids'] ?? '');
                $goodsUnit = $item['goods_unit'] ?? 0;

                $goods = Db::name('kuerp_goods')->where('id', $goodsId)->where('status', '1')->find();
                if (!$goods) {
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '商品已下架：' . ($goods['goods_name'] ?? '')]);
                }

                // 查库存 & 计算价格
                $skuStockChecked = false;
                $unitPrice = (float)$goods[$priceField];
                if ($attrIds) {
                    // 多规格：attr_ids → aux_combination → goods_attr 获取 SKU 价格
                    $combinationId = Db::name('kuerp_aux_combination')
                        ->where('attr_ids', $attrIds)
                        ->value('id');
                    if ($combinationId) {
                        $goodsAttr = Db::name('kuerp_goods_attr')
                            ->where('goods_id', $goodsId)
                            ->where('attribute_id', $combinationId)
                            ->find();
                        if ($goodsAttr) {
                            $unitPrice = (float)$goodsAttr[$priceField];
                            // 查该 SKU 库存
                            $stock = Db::name('kuerp_goods_stock')
                                ->where('goods_id', $goodsId)
                                ->where('attribute_id', $goodsAttr['id'])
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
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '商品已售罄：' . $goods['goods_name']]);
                }
                $allMoney  = round($unitPrice * $quantity, 2);
                $totalAmount += $allMoney;

                // 属性名称快照
                $attrName = $item['attr_name'] ?? '';
                if (!$attrName && $attrIds) {
                    $ids   = explode('&', $attrIds);
                    $names = Db::name('kuerp_auxiliary')->where('id', 'in', $ids)->column('name');
                    $attrName = implode('/', $names);
                }

                // 单位名称快照
                $unitName = $item['unit_name'] ?? '';
                if (!$unitName && $goodsUnit > 0) {
                    $unitName = Db::name('kuerp_unit')->where('id', $goodsUnit)->value('name') ?: '';
                }

                $orderGoods[] = [
                    'goods_id'    => $goods['id'],
                    'goods_code'  => $goods['goods_code'],
                    'goods_name'  => $goods['goods_name'],
                    'goods_image' => $goods['goods_image'],
                    'goods_unit'  => $goodsUnit,
                    'unit_name'   => $unitName,
                    'attr_ids'    => $attrIds,
                    'attr_name'   => $attrName,
                    'quantity'    => $quantity,
                    'unit_price'  => $unitPrice,
                    'all_money'   => $allMoney,
                    'createdtime' => time(),
                ];
            }

            // 创建订单
            $order = MiniOrder::create([
                'order_no'         => $orderNo,
                'user_id'          => $this->auth->id,
                'customer_id'      => $customerId,
                'status'           => 'pending_confirm',
                'total_amount'     => $totalAmount,
                'preferential'     => 0,
                'payable_amount'   => $totalAmount,
                'remark'           => $remark,
                'receiver_name'    => $address['first_connect_name'],
                'receiver_mobile'  => $address['first_connect_mobile'],
                'receiver_province'=> $province,
                'receiver_city'    => $city,
                'receiver_district'=> $district,
                'receiver_address' => $street,
                'receiver_zip'     => '',
                'createdtime'      => time(),
                'updatetime'       => time(),
            ]);

            // 保存订单商品明细
            foreach ($orderGoods as &$og) {
                $og['order_id'] = $order->id;
            }
            unset($og);
            (new MiniOrderGoods)->saveAll($orderGoods);

            // 清空购物车中已购买的商品
            $cartGoodsIds = array_column($goodsList, 'goods_id');
            if (!empty($cartGoodsIds)) {
                MiniCart::where('user_id', $this->auth->id)
                    ->where('goods_id', 'in', $cartGoodsIds)
                    ->delete();
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '提交失败: ' . $e->getMessage()]);
        }

        return json(['code' => 0, 'msg' => '下单成功', 'data' => [
            'order' => MiniOrder::with(['goods'])->find($order->id),
        ]]);
    }

    /**
     * 我的订单列表
     * GET /api/kuerp/order/index
     */
    public function index()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $page   = $this->request->get('page/d', 1);
        $limit  = $this->request->get('limit/d', 10);
        $status = $this->request->get('status', '');

        $where = [];
        $where['user_id'] = $this->auth->id;
        if ($status !== 'all') {
            $where['status'] = $status;
        }

        $list = MiniOrder::with(['goods'])
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = MiniOrder::where($where)->count();

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]]);
    }

    /**
     * 订单详情
     * GET /api/kuerp/order/detail
     */
    public function detail()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $id = $this->request->get('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $order = MiniOrder::with(['goods', 'delivery'])->where('id', $id)->where('user_id', $this->auth->id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => ['order' => $order]]);
    }

    /**
     * 用户取消订单
     * POST /api/kuerp/order/cancel
     */
    public function cancel()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $order = MiniOrder::where('id', $id)->where('user_id', $this->auth->id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if ($order->status !== 'pending_confirm') {
            return json(['code' => 1, 'msg' => '当前状态不可取消']);
        }

        $order->status    = 'cancelled';
        $order->updatetime = time();
        $order->save();

        return json(['code' => 0, 'msg' => '取消成功']);
    }

    /**
     * 用户确认收货
     * POST /api/kuerp/order/received
     */
    public function received()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $order = MiniOrder::where('id', $id)->where('user_id', $this->auth->id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if ($order->status !== 'shipped') {
            return json(['code' => 1, 'msg' => '当前状态不可确认收货']);
        }

        $order->status       = 'received';
        $order->updatetime    = time();
        $order->save();

        // 自动完成
        $order->status       = 'completed';
        $order->complete_time = time();
        $order->updatetime    = time();
        $order->save();

        return json(['code' => 0, 'msg' => '确认收货成功']);
    }

    // ============================================================
    // 后台管理接口
    // ============================================================

    /**
     * 后台 - 订单管理列表
     * GET /api/kuerp/order/admin-index
     */
    public function adminIndex()
    {
        $page   = $this->request->get('page/d', 1);
        $limit  = $this->request->get('limit/d', 20);
        $status = $this->request->get('status', '');
        $keyword = $this->request->get('keyword', '');
        $dateRange = $this->request->get('date_range', '');

        $where = [];
        if ($status) {
            $where['o.status'] = $status;
        }
        if ($dateRange) {
            $range = explode(' - ', $dateRange);
            if (count($range) == 2) {
                $where['o.createdtime'] = [['>=', strtotime($range[0])], ['<=', strtotime($range[1] . ' 23:59:59')], 'and'];
            }
        }

        $keywordWhere = function ($query) use ($keyword) {
            if ($keyword) {
                $query->where('o.order_no', 'like', "%{$keyword}%")
                    ->whereOr('u.nickname', 'like', "%{$keyword}%")
                    ->whereOr('c.customer_name', 'like', "%{$keyword}%");
            }
        };

        $list = Db::name('kuerp_mini_order')
            ->alias('o')
            ->join('kuerp_mini_user u', 'o.user_id = u.user_id', 'LEFT')
            ->join('kuerp_customers c', 'o.customer_id = c.id', 'LEFT')
            ->field('o.*, u.nickname, u.avatar, c.customer_name')
            ->where($where)
            ->where($keywordWhere)
            ->order('o.id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = Db::name('kuerp_mini_order')
            ->alias('o')
            ->join('kuerp_mini_user u', 'o.user_id = u.user_id', 'LEFT')
            ->join('kuerp_customers c', 'o.customer_id = c.id', 'LEFT')
            ->where($where)
            ->where($keywordWhere)
            ->count();

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]]);
    }

    /**
     * 后台 - 订单详情
     * GET /api/kuerp/order/admin-detail
     */
    public function adminDetail()
    {
        $id = $this->request->get('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $order = MiniOrder::with(['goods', 'delivery'])->where('id', $id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }

        // 补充用户信息
        $miniUser = Db::name('kuerp_mini_user')->where('user_id', $order->user_id)->find();
        $customer = Db::name('kuerp_customers')->where('id', $order->customer_id)->find();

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'order'    => $order,
            'mini_user' => $miniUser,
            'customer'  => $customer,
        ]]);
    }

    /**
     * 后台 - 确认订单（生成销货单草稿）
     * POST /api/kuerp/order/confirm
     */
    public function confirm()
    {
        $id       = $this->request->post('id/d', 0);
        $storeId  = $this->request->post('stores_id/d', 0);
        $adminId  = $this->auth->id;

        if (!$id || !$storeId) {
            return json(['code' => 1, 'msg' => '请选择仓库']);
        }

        $order = MiniOrder::with(['goods'])->where('id', $id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if ($order->status !== 'pending_confirm') {
            return json(['code' => 1, 'msg' => '当前状态不可确认']);
        }

        // 检查库存
        $goodsList = $order->goods;
        $insufficient = [];
        foreach ($goodsList as $item) {
            $stock = Db::name('kuerp_goods_stock')
                ->where('goods_code', $item['goods_code'])
                ->where('stores_id', $storeId)
                ->value('stock');
            if ((float)$stock < (float)$item['quantity']) {
                $insufficient[] = $item['goods_name'] . '(需' . $item['quantity'] . ', 库存' . ((float)$stock) . ')';
            }
        }
        if (!empty($insufficient)) {
            return json(['code' => 1, 'msg' => '库存不足: ' . implode('; ', $insufficient)]);
        }

        Db::startTrans();
        try {
            // 生成销货单号
            $salesCode = \addons\kuerp\library\Goods::getCodes('', 11);
            if (!$salesCode) {
                $salesCode = 'XHD' . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            // 创建销货单草稿
            $salesData = [
                'purchase_code'     => $salesCode,
                'customers_id'      => $order->customer_id,
                'examine_status'    => 0,
                'account'           => $order->payable_amount,
                'all_money'         => $order->total_amount,
                'preferential_money'=> $order->preferential,
                'note'              => '订货通订单#' . $order->order_no,
                'purchase_time'     => time(),
                'sale_user'         => '',
                'admin_id'          => $adminId,
                'createtime'        => time(),
                'updatetime'        => time(),
            ];
            $salesId = Db::name('kuerp_goods_sales')->insertGetId($salesData);

            // 创建销货单明细
            $salesInfoData = [];
            foreach ($goodsList as $item) {
                $salesInfoData[] = [
                    'purchase_code'  => $salesCode,
                    'goods_code'     => $item['goods_code'],
                    'basic_nmber'    => $item['quantity'],
                    'unit_price'     => $item['unit_price'],
                    'all_money'      => $item['all_money'],
                    'stores_id'      => $storeId,
                    'createtime'     => time(),
                ];
            }
            Db::name('kuerp_goods_sales_info')->insertAll($salesInfoData);

            // 更新订单
            $order->status      = 'confirmed';
            $order->admin_id    = $adminId;
            $order->confirm_time = time();
            $order->sales_code  = $salesCode;
            $order->updatetime  = time();
            $order->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '确认失败: ' . $e->getMessage()]);
        }

        return json(['code' => 0, 'msg' => '确认成功', 'data' => [
            'order'      => MiniOrder::with(['goods'])->find($order->id),
            'sales_code' => $salesCode,
        ]]);
    }

    /**
     * 后台 - 确认付款（对单操作，更新对单时间）
     * POST /api/kuerp/order/payment
     */
    public function payment()
    {
        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $order = MiniOrder::where('id', $id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if ($order->status !== 'confirmed') {
            return json(['code' => 1, 'msg' => '当前状态不可确认付款']);
        }
        if (empty($order->sales_code)) {
            return json(['code' => 1, 'msg' => '未关联销货单']);
        }

        $order->payment_time = time();
        $order->updatetime   = time();
        $order->save();

        return json(['code' => 0, 'msg' => '操作成功']);
    }

    /**
     * 后台 - 发货
     * POST /api/kuerp/order/ship
     */
    public function ship()
    {
        $id = $this->request->post('id/d', 0);
        $deliveryType = $this->request->post('delivery_type', 'logistics');
        $adminId = $this->auth->id;

        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $order = MiniOrder::where('id', $id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if ($order->status !== 'confirmed') {
            return json(['code' => 1, 'msg' => '当前状态不可发货']);
        }

        $deliveryNo = 'DN' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $deliveryData = [
            'order_id'       => $id,
            'delivery_no'    => $deliveryNo,
            'delivery_type'  => $deliveryType,
            'status'         => 'shipped',
            'shipped_time'   => time(),
            'admin_id'       => $adminId,
            'createdtime'    => time(),
            'updatetime'     => time(),
        ];

        if ($deliveryType == 'logistics') {
            $deliveryData['express_company'] = $this->request->post('express_company', '');
            $deliveryData['express_code']    = $this->request->post('express_code', '');
            $deliveryData['express_no']      = $this->request->post('express_no', '');
        } else {
            $deliveryData['carrier_name']   = $this->request->post('carrier_name', '');
            $deliveryData['carrier_mobile'] = $this->request->post('carrier_mobile', '');
        }

        $remark = $this->request->post('remark', '');
        if ($remark) {
            $deliveryData['remark'] = $remark;
        }

        Db::startTrans();
        try {
            MiniDelivery::create($deliveryData);

            $order->status     = 'shipped';
            $order->updatetime  = time();
            $order->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '发货失败: ' . $e->getMessage()]);
        }

        return json(['code' => 0, 'msg' => '发货成功', 'data' => [
            'delivery' => $deliveryData,
        ]]);
    }

    /**
     * 后台 - 订单发货记录
     * GET /api/kuerp/order/delivery-list
     */
    public function deliveryList()
    {
        $orderId = $this->request->get('order_id/d', 0);
        if (!$orderId) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $list = MiniDelivery::where('order_id', $orderId)->order('id', 'desc')->select();
        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }

    /**
     * 后台 - 管理员取消订单
     * POST /api/kuerp/order/cancel-admin
     */
    public function cancelAdmin()
    {
        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $order = MiniOrder::with(['goods'])->where('id', $id)->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if (in_array($order->status, ['completed', 'returned', 'cancelled'])) {
            return json(['code' => 1, 'msg' => '当前状态不可取消']);
        }

        Db::startTrans();
        try {
            // 如果有销货单，作废
            if ($order->sales_code) {
                Db::name('kuerp_goods_sales')
                    ->where('purchase_code', $order->sales_code)
                    ->update(['examine_status' => 3, 'updatetime' => time()]); // 3=已作废
            }

            $order->status     = 'cancelled';
            $order->updatetime  = time();
            $order->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '取消失败: ' . $e->getMessage()]);
        }

        return json(['code' => 0, 'msg' => '取消成功']);
    }

    // ============================================================
    // 工具方法
    // ============================================================

    /**
     * 生成订单号（使用 kuerp_prefix 编号规则）
     */
    private function generateOrderNo()
    {
        $prefix = 'DD';
        $prefixRow = Db::name('kuerp_prefix')->where('type', 28)->find();
        if ($prefixRow) {
            $prefix = $prefixRow['prefix'] ?: $prefix;
            $code = \addons\kuerp\library\Goods::getCodes('', 28);
            if ($code) {
                return $code;
            }
        }
        // 默认生成
        $date = date('Ymd');
        $last = Db::name('kuerp_mini_order')->where('order_no', 'like', "{$prefix}{$date}%")->order('id', 'desc')->value('order_no');
        if ($last) {
            $seq = intval(substr($last, -4)) + 1;
        } else {
            $seq = 1;
        }
        return $prefix . $date . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

}
