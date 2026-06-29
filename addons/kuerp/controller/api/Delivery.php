<?php

namespace addons\kuerp\controller\api;

use addons\kuerp\model\MiniDelivery;

class Delivery extends Base
{

    /**
     * 查看物流信息
     * GET /api/kuerp/delivery/index
     */
    public function index()
    {

        $deliveryId = $this->request->get('delivery_id/d', 0);
        $orderId    = $this->request->get('order_id/d', 0);

        if ($deliveryId) {
            // 查指定发货记录
            $delivery = MiniDelivery::where('id', $deliveryId)->find();
        } elseif ($orderId) {
            // 查该订单最近一条（向后兼容）
            $delivery = MiniDelivery::where('order_id', $orderId)->order('id', 'desc')->find();
        } else {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        if (!$delivery) {
            return json(['code' => 1, 'msg' => '暂无物流信息']);
        }

        // 物流配送 - 对接第三方物流轨迹
        $traces = [];
        if ($delivery->delivery_type == 'logistics' && $delivery->express_no) {
            // TODO: 对接快递100/快递鸟查询物流轨迹
            // 目前返回空轨迹，前端展示快递公司+单号
            $traces = [];
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'delivery' => $delivery,
            'traces'   => $traces,
        ]]);
    }
}
