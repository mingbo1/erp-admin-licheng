<?php

namespace app\admin\model\kuerp;

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

    protected $append = [
        'status_text'
    ];

    public function getStatusList()
    {
        return [
            'pending_confirm' => __('待确认'),
            'confirmed'       => __('已确认'),
            'shipped'         => __('已发货'),
            'received'        => __('已签收'),
            'completed'       => __('已完成'),
            'cancelled'       => __('已取消'),
            'returned'        => __('已退货'),
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function goods()
    {
        return $this->hasMany(\app\admin\model\kuerp\KuerpMiniOrderGoods::class, 'order_id', 'id');
    }

    public function delivery()
    {
        return $this->hasMany(\app\admin\model\kuerp\KuerpMiniDelivery::class, 'order_id', 'id');
    }
}
