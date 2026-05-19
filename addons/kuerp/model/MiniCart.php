<?php

namespace addons\kuerp\model;

use think\Model;

class MiniCart extends Model
{
    protected $name = 'kuerp_mini_cart';

    protected $autoWriteTimestamp = false;

    protected $type = [
        'quantity' => 'float',
    ];

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }
}
