<?php

namespace app\admin\model\kuerp;

use think\Model;


class CustomerGoodscoding extends Model
{

    // 表名
    protected $name = 'kuerp_customer_goodscoding';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    public function customers()
    {
        return $this->belongsTo('Customers', 'customers_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function unit()
    {
        return $this->belongsTo('unit', 'unit_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_code', 'goods_code', [], 'LEFT')->setEagerlyType(0);
    }
}
