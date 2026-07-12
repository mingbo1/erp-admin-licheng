<?php

namespace addons\kuerp\controller\api;

use addons\kuerp\model\MiniCart;
use think\Db;

class Order extends Base
{
    protected $noNeedLogin = [];

    // 销货订单状态映射
    const STATUS_MAP = [
        '0' => 'pending_confirm', // 未审核 → 待确认
        '1' => 'confirmed',       // 已审核 → 已确认
        '3' => 'cancelled',       // 已取消
        '4' => 'received',        // 已收货
    ];

    // ============================================================
    // 小程序端接口
    // ============================================================

    /**
     * 提交订单 → 直接创建销货订单
     * POST /api/kuerp/order/add
     *
     * 参数:
     *   customer_id     - 客户ID(可选, 默认当前用户关联客户)
     *   address_id      - 收货地址ID(可选)
     *   receiver_name   - 收货人(直接填写)
     *   receiver_mobile - 收货手机
     *   receiver_address- 收货地址
     *   goods           - 商品列表
     *   remark          - 备注
     */
    public function add()
    {

        $remark      = $this->request->post('remark', '');
        $addressId   = $this->request->post('address_id/d', 0);
        $customerId  = $this->request->post('customer_id/d', 0);

        // 商品列表
        $goodsData = $this->request->post('goods', '');
        $goodsList = [];
        if ($goodsData) {
            if (is_array($goodsData)) {
                // ThinkPHP 可能已将 JSON 解析为数组
                $goodsList = $goodsData;
            } else {
                // 多次尝试 JSON 解码（兼容各种输入过滤场景）
                $decoded = json_decode($goodsData, true);
                if (!is_array($decoded)) {
                    // addslashes → 尝试 stripslashes
                    $decoded = json_decode(stripslashes($goodsData), true);
                }
                if (!is_array($decoded)) {
                    // htmlspecialchars → 尝试 htmlspecialchars_decode
                    $decoded = json_decode(htmlspecialchars_decode($goodsData), true);
                }
                if (!is_array($decoded)) {
                    // 二者叠加
                    $decoded = json_decode(htmlspecialchars_decode(stripslashes($goodsData)), true);
                }
                if (is_array($decoded)) {
                    $goodsList = $decoded;
                }
            }
        }
        // 如果 goods 参数解析为空，则从购物车取值
        if (empty($goodsList)) {
            $cartList = MiniCart::where('user_id', $this->auth->id)->select();
            if ($cartList && !empty($cartList)) {
                $goodsList = collection($cartList)->toArray();
            }
        }
        if (empty($goodsList)) {
            return json(['code' => 1, 'msg' => '商品列表为空']);
        }

        // 客户
        if (!$customerId) {
            $customerId = $this->getCustomerId();
        }
        if (!$customerId) {
            return json(['code' => 1, 'msg' => '请选择客户']);
        }
        $customer = Db::name('kuerp_customers')->where('id', $customerId)->where('status', '1')->find();
        if (!$customer) {
            return json(['code' => 1, 'msg' => '所选客户不存在或已禁用']);
        }

        // 收货地址
        $receiverName    = '';
        $receiverMobile  = '';
        $receiverAddress = '';
        if ($addressId) {
            $addr = Db::name('kuerp_customers_user')->where('id', $addressId)->where('sid', $customerId)->find();
            if ($addr) {
                $receiverName   = $addr['first_connect_name'];
                $receiverMobile = $addr['first_connect_mobile'];
                $receiverAddress= $addr['connect_address'];
            }
        }
        if (!$receiverName && !$receiverAddress) {
            $receiverName    = $this->request->post('receiver_name', '');
            $receiverMobile  = $this->request->post('receiver_mobile', '');
            $receiverAddress = $this->request->post('receiver_address', '');
        }
        if (!$receiverName) {
            $receiverName = $customer['connet_name'] ?: $customer['customer_name'];
        }
        if (!$receiverMobile) {
            $receiverMobile = $customer['customer_mobile'] ?: '';
        }
        if (!$receiverAddress) {
            $receiverAddress = $customer['getgoods_address'] ?: '';
        }
        if (!$receiverAddress) {
            return json(['code' => 1, 'msg' => '请填写收货地址']);
        }

        // 销售人员：从 mini_user 获取绑定的 staff_id
        $miniUser  = $this->getMiniUser();
        $saleUser  = 0;
        if ($miniUser && !empty($miniUser['staff_id'])) {
            $staff = Db::name('kuerp_staff_member')->where('id', $miniUser['staff_id'])->find();
            if ($staff) {
                $saleUser = (int)$staff['id'];
            }
        }

        $priceField = $this->getPriceField($customerId);

        Db::startTrans();
        try {
            // 生成销货订单号
            $orderCode = \addons\kuerp\library\Goods::getCodes('', 10);
            if (!$orderCode) {
                $orderCode = 'XSDD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }

            // 计算金额 & 构建明细
            $totalAmount    = 0;
            $totalTax       = 0;
            $totalTaxPrice  = 0;
            $orderGoods     = [];
            $orderNumber    = 0;

            foreach ($goodsList as $item) {
                $goodsId   = $item['goods_id'] ?? $item['id'] ?? 0;
                $quantity  = (float)($item['quantity'] ?? 1);
                $attrIds   = htmlspecialchars_decode($item['attr_ids'] ?? '');
                $goodsUnit = $item['goods_unit'] ?? 0;

                $goods = Db::name('kuerp_goods')->where('id', $goodsId)->where('status', '1')->find();
                if (!$goods) {
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '商品已下架：ID' . $goodsId]);
                }

                // 价格
                $unitPrice = (float)$goods[$priceField];
                if ($attrIds) {
                    $combinationId = Db::name('kuerp_aux_combination')->where('attr_ids', $attrIds)->value('id');
                    if ($combinationId) {
                        $goodsAttr = Db::name('kuerp_goods_attr')
                            ->where('goods_id', $goodsId)->where('attribute_id', $combinationId)->find();
                        if ($goodsAttr) {
                            $unitPrice = (float)$goodsAttr[$priceField];
                        }
                    }
                }

                $allMoney    = round($unitPrice * $quantity, 2);
                $totalAmount += $allMoney;
                $orderNumber += $quantity;

                // 属性：查找 goods_attr.id
                $goodsAttrId = 0;
                if ($attrIds) {
                    $combinationId = Db::name('kuerp_aux_combination')->where('attr_ids', $attrIds)->value('id');
                    if ($combinationId) {
                        $goodsAttrId = Db::name('kuerp_goods_attr')
                            ->where('goods_id', $goodsId)->where('attribute_id', $combinationId)->value('id') ?: 0;
                    }
                }

                // 属性名称
                $attrName = $item['attr_name'] ?? '';
                if (!$attrName && $attrIds) {
                    $ids   = explode('&', $attrIds);
                    $names = Db::name('kuerp_auxiliary')->where('id', 'in', $ids)->column('name');
                    $attrName = implode('/', $names);
                }

                // 单位
                $unitName = $item['unit_name'] ?? '';
                if (!$unitName && $goodsUnit > 0) {
                    $unitName = Db::name('kuerp_unit')->where('id', $goodsUnit)->value('name') ?: '';
                }

                $orderGoods[] = [
                    'purchase_code'    => $orderCode,
                    'purchase_type'    => '1',
                    'purchase_time'    => time(),
                    'customers_id'     => $customerId,
                    'goods_code'       => $goods['goods_code'],
                    'goods_note'       => $goods['goods_name'] ?? '',
                    'goods_image'      => $goods['goods_image'] ?? '',
                    'goods_unit'       => $goodsUnit ?: $goods['unit_id'],
                    'stores_id'        => 0,
                    'number'           => $quantity,
                    'purchase_number'  => $quantity,
                    'default_unit'     => $goods['unit_id'],
                    'basic_nmber'      => $quantity,
                    'unit_price'       => $unitPrice,
                    'all_money'        => $allMoney,
                    'remark'           => $item['remark'] ?? '',
                    'goods_attr'       => $goodsAttrId,
                    'status'           => '0',
                    'createtime'       => time(),
                    'updatetime'       => time(),
                ];
            }

            // 创建销货订单主表
            $salesOrderId = Db::name('kuerp_goods_sales_order')->insertGetId([
                'purchase_code'     => $orderCode,
                'purchase_type'     => '1',
                'purchase_time'     => time(),
                'customers_id'      => $customerId,
                'receiving_address' => $receiverAddress,
                'sale_user'         => $saleUser,
                'number'            => $orderNumber,
                'all_money'         => $totalAmount,
                'real_money'        => $totalAmount,
                'money_tax_price'   => $totalAmount,
                'source'            => 'miniapp',
                'mini_user_id'      => $miniUser ? $miniUser['id'] : 0,
                'status'            => '0',
                'examine_status'    => 0,
                'note'              => $remark,
                'preparation_id'    => $this->auth->id,
                'operation_id'      => $this->auth->id,
                'createtime'        => time(),
                'updatetime'        => time(),
            ]);

            if (!$salesOrderId) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '创建订单失败']);
            }

            // 批量插入明细
            Db::name('kuerp_goods_sales_order_info')->insertAll($orderGoods);

            // 清空购物车
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

        // 返回创建的订单信息
        $order = Db::name('kuerp_goods_sales_order')->where('id', $salesOrderId)->find();

        return json(['code' => 0, 'msg' => '下单成功', 'data' => [
            'order'      => $order,
            'order_code' => $orderCode,
        ]]);
    }

    /**
     * 我的订单列表（查销货订单表中 source=miniapp 的记录）
     * GET /api/kuerp/order/index
     */
    public function index()
    {
        $miniUser = $this->getMiniUser();
        $miniUserId = $miniUser ? $miniUser['id'] : 0;

        $page   = $this->request->get('page/d', 1);
        $limit  = $this->request->get('limit/d', 10);
        $status = $this->request->get('status', '');

        // 只查询当前小程序用户的订单
        $where = [
            'source'       => 'miniapp',
            'mini_user_id' => $miniUserId,
        ];
        // 前端状态 → 后端条件映射
        // pending_review=待审核, pending_ship=待发货, partial_shipped=部分发货, shipped=已发货, cancelled=已取消, received=已收货
        if ($status !== '' && $status !== 'all') {
            switch ($status) {
                case 'pending_review':
                    $where['examine_status'] = 0;
                    $where['status'] = ['<>', '3'];
                    break;
                case 'pending_ship':
                    $where['examine_status'] = 1;
                    $where['status'] = '0';
                    break;
                case 'partial_shipped':
                    $where['examine_status'] = 1;
                    $where['status'] = '2';
                    break;
                case 'shipped':
                    $where['examine_status'] = 1;
                    $where['status'] = '1';
                    break;
                case 'cancelled':
                    $where['status'] = '3';
                    break;
                case 'received':
                    $where['status'] = '4';
                    break;
            }
        }

        $list = Db::name('kuerp_goods_sales_order')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 附加客户名称、明细、显示状态
        foreach ($list as &$row) {
            $customer = Db::name('kuerp_customers')->where('id', $row['customers_id'])->field('customer_name')->find();
            $row['customer_name'] = $customer['customer_name'] ?? '';
            $row['items'] = Db::name('kuerp_goods_sales_order_info')
                ->where('purchase_code', $row['purchase_code'])
                ->field('goods_code,goods_note,goods_image,number,unit_price,all_money,goods_unit')
                ->select();
            foreach ($row['items'] as &$it) {
                $it['goods_name'] = $it['goods_note'] ?? '';
            }
            // 计算 display_status
            $row['display_status'] = $this->calcDisplayStatus($row);
        }

        $total = Db::name('kuerp_goods_sales_order')->where($where)->count();

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
        $id = $this->request->get('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $miniUser = $this->getMiniUser();
        $miniUserId = $miniUser ? $miniUser['id'] : 0;

        $order = Db::name('kuerp_goods_sales_order')
            ->where('id', $id)
            ->where('source', 'miniapp')
            ->where('mini_user_id', $miniUserId)
            ->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }

        // 计算显示状态
        $order['display_status'] = $this->calcDisplayStatus($order);

        // 明细
        $order['items'] = Db::name('kuerp_goods_sales_order_info')
            ->where('purchase_code', $order['purchase_code'])
            ->select();

        // 客户信息
        $order['customer'] = Db::name('kuerp_customers')
            ->where('id', $order['customers_id'])
            ->field('id,customer_code,customer_name,connet_name,customer_mobile,getgoods_address')
            ->find();

        // 如果已发货/已收货，查关联的销货单
        if ($order['display_status'] === 'shipped' || $order['display_status'] === 'received') {
            $salesCodes = Db::name('kuerp_goods_saleord')
                ->where('order_id', $order['id'])
                ->column('purchase_id');
            if (!empty($salesCodes)) {
                $order['sales_list'] = Db::name('kuerp_goods_sales')
                    ->where('id', 'in', $salesCodes)
                    ->field('purchase_code,examine_status,createtime')
                    ->select();
            }
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => ['order' => $order]]);
    }

    /**
     * 取消订单（仅待审核状态可取消）
     * POST /api/kuerp/order/cancel
     */
    public function cancel()
    {

        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $miniUser = $this->getMiniUser();
        $miniUserId = $miniUser ? $miniUser['id'] : 0;

        $order = Db::name('kuerp_goods_sales_order')
            ->where('id', $id)
            ->where('source', 'miniapp')
            ->where('mini_user_id', $miniUserId)
            ->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if ($order['examine_status'] == 1) {
            return json(['code' => 1, 'msg' => '已审核的订单不可取消，请联系客服']);
        }
        if ($order['status'] == '3') {
            return json(['code' => 1, 'msg' => '订单已取消']);
        }

        Db::name('kuerp_goods_sales_order')->where('id', $id)->update([
            'status'      => '3',
            'updatetime'  => time(),
        ]);

        return json(['code' => 0, 'msg' => '取消成功']);
    }

    /**
     * 确认收货（已发货/部分发货 → 已收货）
     * POST /api/kuerp/order/received
     */
    public function received()
    {

        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $miniUser = $this->getMiniUser();
        $miniUserId = $miniUser ? $miniUser['id'] : 0;

        $order = Db::name('kuerp_goods_sales_order')
            ->where('id', $id)
            ->where('source', 'miniapp')
            ->where('mini_user_id', $miniUserId)
            ->find();
        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }
        if ($order['examine_status'] != 1) {
            return json(['code' => 1, 'msg' => '未审核的订单不能确认收货']);
        }
        if ($order['status'] == '0') {
            return json(['code' => 1, 'msg' => '未出库的订单不能确认收货']);
        }
        if ($order['status'] == '3') {
            return json(['code' => 1, 'msg' => '已取消的订单不能确认收货']);
        }
        if ($order['status'] == '4') {
            return json(['code' => 1, 'msg' => '该订单已确认收货']);
        }

        Db::name('kuerp_goods_sales_order')->where('id', $id)->update([
            'status'      => '4',
            'updatetime'  => time(),
        ]);

        return json(['code' => 0, 'msg' => '确认收货成功']);
    }

    // ============================================================
    // 客户 & 收货地址接口
    // ============================================================

    /**
     * 获取当前用户可选的客户列表
     * GET /api/kuerp/order/customer-list
     */
    public function customerList()
    {
        $miniUser = $this->getMiniUser();
        $ownCustomerId = $miniUser ? $miniUser->customer_id : 0;

        $list = [];
        if ($ownCustomerId) {
            $own = Db::name('kuerp_customers')
                ->where('id', $ownCustomerId)->where('status', '1')
                ->field('id,customer_code,customer_name,connet_name,customer_mobile,getgoods_address')
                ->find();
            if ($own) {
                $own['is_own'] = true;
                $list[] = $own;
            }
        }

        $keyword = $this->request->get('keyword', '');
        $where = ['status' => '1'];
        if ($ownCustomerId) {
            $where['id'] = ['<>', $ownCustomerId];
        }
        if ($keyword) {
            $where['customer_name|customer_mobile|customer_code'] = ['like', '%' . $keyword . '%'];
        }

        $others = Db::name('kuerp_customers')
            ->where($where)
            ->field('id,customer_code,customer_name,connet_name,customer_mobile,getgoods_address')
            ->limit(30)
            ->select();
        foreach ($others as &$o) {
            $o['is_own'] = false;
        }

        $list = array_merge($list, $others);
        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }

    /**
     * 添加客户
     * POST /api/kuerp/order/customer-add
     */
    public function customerAdd()
    {

        $name    = trim($this->request->post('customer_name', ''));
        $mobile  = trim($this->request->post('customer_mobile', ''));
        $contact = trim($this->request->post('connet_name', ''));
        $address = trim($this->request->post('getgoods_address', ''));

        if (!$name) {
            return json(['code' => 1, 'msg' => '请输入客户名称']);
        }

        $exist = Db::name('kuerp_customers')->where('customer_name', $name)->find();
        if ($exist) {
            return json(['code' => 1, 'msg' => '该客户名称已存在']);
        }

        Db::startTrans();
        try {
            $customerCode = \addons\kuerp\library\Goods::getCodes('', 1);
            $customerId = Db::name('kuerp_customers')->insertGetId([
                'customer_code'    => $customerCode,
                'customer_name'    => $name,
                'connet_name'      => $contact ?: $name,
                'customer_mobile'  => $mobile,
                'getgoods_address' => $address,
                'customerlevel_id' => 1,
                'customertype_id'  => 1,
                'status'           => 1,
                'createtime'       => time(),
                'updatetime'       => time(),
            ]);

            if ($address && $customerId) {
                Db::name('kuerp_customers_user')->insert([
                    'sid'                 => $customerId,
                    'customers_code'      => $customerCode,
                    'first_connect_name'  => $contact ?: $name,
                    'first_connect_mobile'=> $mobile,
                    'connect_address'     => $address,
                    'is_first'            => 1,
                    'status'              => '1',
                    'createtime'          => time(),
                    'updatetime'          => time(),
                ]);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '添加失败: ' . $e->getMessage()]);
        }

        return json(['code' => 0, 'msg' => '添加成功', 'data' => [
            'customer' => Db::name('kuerp_customers')->find($customerId),
        ]]);
    }

    /**
     * 获取客户的收货地址列表
     * GET /api/kuerp/order/address-list
     */
    public function addressList()
    {
        $customerId = $this->request->get('customer_id/d', 0);
        if (!$customerId) {
            $customerId = $this->getCustomerId();
        }
        if (!$customerId) {
            return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => [], 'customer' => null]]);
        }

        $customer = Db::name('kuerp_customers')
            ->where('id', $customerId)
            ->field('id,customer_code,customer_name,connet_name,customer_mobile,getgoods_address')
            ->find();

        $addresses = Db::name('kuerp_customers_user')
            ->where('sid', $customerId)->where('status', '1')
            ->order('is_first', 'desc')
            ->select();

        if (empty($addresses) && $customer && !empty($customer['getgoods_address'])) {
            $addresses = [[
                'id'                   => 0,
                'sid'                  => $customerId,
                'first_connect_name'   => $customer['connet_name'] ?: $customer['customer_name'],
                'first_connect_mobile' => $customer['customer_mobile'],
                'connect_address'      => $customer['getgoods_address'],
                'is_first'             => 1,
            ]];
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'customer' => $customer,
            'list'     => $addresses,
        ]]);
    }

    /**
     * 保存手动填写的地址到客户地址库
     * POST /api/kuerp/order/addressSave
     *
     * 参数:
     *   customer_id         - 客户ID(必填)
     *   first_connect_name  - 收货人
     *   first_connect_mobile- 收货手机
     *   connect_address     - 收货地址
     */
    public function addressSave()
    {

        $customerId = $this->request->post('customer_id/d', 0);
        $name       = trim($this->request->post('first_connect_name', ''));
        $mobile     = trim($this->request->post('first_connect_mobile', ''));
        $address    = trim($this->request->post('connect_address', ''));

        if (!$customerId) {
            return json(['code' => 1, 'msg' => '参数错误：缺少客户ID']);
        }
        if (!$name || !$address) {
            return json(['code' => 1, 'msg' => '请填写收货人和地址']);
        }

        // 验证客户存在
        $customer = Db::name('kuerp_customers')->where('id', $customerId)->where('status', '1')->find();
        if (!$customer) {
            return json(['code' => 1, 'msg' => '客户不存在或已禁用']);
        }

        // 检查是否已存在相同地址（相同客户+相同收货人+相同地址）
        $exist = Db::name('kuerp_customers_user')
            ->where('sid', $customerId)
            ->where('first_connect_name', $name)
            ->where('connect_address', $address)
            ->find();
        if ($exist) {
            // 更新手机号
            Db::name('kuerp_customers_user')->where('id', $exist['id'])->update([
                'first_connect_mobile' => $mobile,
                'updatetime'           => time(),
            ]);
            $exist['first_connect_mobile'] = $mobile;
            return json(['code' => 0, 'msg' => '地址已存在，已更新', 'data' => ['address' => $exist]]);
        }

        // 检查是否已有默认地址，决定 is_first
        $hasDefault = Db::name('kuerp_customers_user')
            ->where('sid', $customerId)->where('is_first', 1)->find();
        $isFirst = $hasDefault ? 0 : 1;

        $addressId = Db::name('kuerp_customers_user')->insertGetId([
            'sid'                  => $customerId,
            'customers_code'       => $customer['customer_code'],
            'first_connect_name'   => $name,
            'first_connect_mobile' => $mobile,
            'connect_address'      => $address,
            'is_first'             => $isFirst,
            'status'               => '1',
            'createtime'           => time(),
            'updatetime'           => time(),
        ]);

        $newAddress = Db::name('kuerp_customers_user')->where('id', $addressId)->find();

        return json(['code' => 0, 'msg' => '保存成功', 'data' => ['address' => $newAddress]]);
    }

    // ============================================================
    // 工具方法
    // ============================================================

    /**
     * 计算订单的显示状态（供小程序前端使用）
     * 映射: 待审核/待发货/已发货/已取消
     */
    private function calcDisplayStatus($order)
    {
        if (!$order) return 'pending_review';
        // 已取消
        if ((string)$order['status'] === '3') return 'cancelled';
        // 未审核 → 待审核
        if ((int)$order['examine_status'] === 0) return 'pending_review';
        // 已审核 + 状态=未出库 → 待发货
        if ((string)$order['status'] === '0') return 'pending_ship';
        // 已审核 + 状态=部分出库 → 部分发货
        if ((string)$order['status'] === '2') return 'partial_shipped';
        // 已审核 + 状态=全部出库 → 已发货
        if ((string)$order['status'] === '1') return 'shipped';
        // 状态=已收货
        if ((string)$order['status'] === '4') return 'received';
        return 'shipped';
    }
}
