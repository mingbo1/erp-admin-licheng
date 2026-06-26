<?php

namespace app\admin\model\kuerp;

use think\Model;

class GoodsSalesOrderDelivery extends Model
{
    protected $name = 'kuerp_goods_sales_order_delivery';

    protected $autoWriteTimestamp = false;

    public function getDeliveryTypeList()
    {
        return ['logistics' => '物流配送', 'professional' => '专业配送'];
    }

    public function getStatusList()
    {
        return ['shipped' => '已发货', 'received' => '已签收'];
    }
}
