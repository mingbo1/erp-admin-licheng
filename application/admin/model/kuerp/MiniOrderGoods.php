<?php

namespace app\admin\model\kuerp;

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
}
