<?php

namespace app\admin\model\kuerp;

use think\Model;

class PaymentLog extends Model
{

    // 表名
    protected $name = 'kuerp_payment_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'business_type_text',
        'bill_time_text'
    ];
    

    
    public function getBusinessTypeList()
    {
        return ['1' => __('Business_type 1'), '2' => __('Business_type 2'), '3' => __('Business_type 3'), '4' => __('Business_type 4')];
    }


    public function getBusinessTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['business_type']) ? $data['business_type'] : '');
        $list = $this->getBusinessTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getBillTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['bill_time']) ? $data['bill_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setBillTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function suppliers()
    {
        return $this->belongsTo('Suppliers', 'suppliers_code', 'supplyer_code', [], 'LEFT')->setEagerlyType(0);
    }
}
