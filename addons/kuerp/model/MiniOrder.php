<?php

namespace addons\kuerp\model;

use think\Model;
use traits\model\SoftDelete;

class MiniOrder extends Model
{
    use SoftDelete;

    protected $name = 'kuerp_mini_order';

    protected $autoWriteTimestamp = false;

    protected $deleteTime = 'deletedtime';

    protected $type = [
        'total_amount'   => 'float',
        'preferential'   => 'float',
        'payable_amount' => 'float',
    ];

    const STATUS_MAP = [
        'pending_confirm' => '待确认',
        'confirmed'       => '已确认',
        'shipped'         => '已发货',
        'received'        => '已签收',
        'completed'       => '已完成',
        'cancelled'       => '已取消',
        'returned'        => '已退货',
    ];

    public function goods()
    {
        return $this->hasMany(MiniOrderGoods::class, 'order_id', 'id');
    }

    public function delivery()
    {
        return $this->hasMany(MiniDelivery::class, 'order_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(MiniUser::class, 'user_id', 'id');
    }
}
