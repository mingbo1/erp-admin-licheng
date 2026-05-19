<?php

namespace addons\kuerp\model;

use think\Model;

class MiniOrderGoods extends Model
{
    protected $name = 'kuerp_mini_order_goods';

    protected $autoWriteTimestamp = false;

    protected $type = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'all_money'  => 'float',
    ];

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(MiniOrder::class, 'order_id', 'id');
    }
}
