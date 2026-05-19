<?php

namespace addons\kuerp\model;

use think\Model;

class MiniDelivery extends Model
{
    protected $name = 'kuerp_mini_delivery';

    protected $autoWriteTimestamp = false;

    public function order()
    {
        return $this->belongsTo(MiniOrder::class, 'order_id', 'id');
    }
}
