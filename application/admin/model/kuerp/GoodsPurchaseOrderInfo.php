<?php

namespace app\admin\model\kuerp;

use think\Model;
use traits\model\SoftDelete;

class GoodsPurchaseOrderInfo extends Model
{
    // 表名
    protected $name = 'kuerp_goods_purchase_order_info';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'purchase_type_text',
        'purchase_time_text',
        'delivery_time_text',
        'status_text',
        'check_status_text',
        'examine_status_text',
        'examine_time_text'
    ];
    

    
    public function getPurchaseTypeList()
    {
        return ['1' => __('Purchase_type 1'), '2' => __('Purchase_type 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }

    public function getCheckStatusList()
    {
        return ['0' => __('Check_status 0'), '1' => __('Check_status 1')];
    }

    public function getExamineStatusList()
    {
        return ['0' => __('Examine_status 0'), '1' => __('Examine_status 1')];
    }


    public function getPurchaseTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_type']) ? $data['purchase_type'] : '');
        $list = $this->getPurchaseTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getDeliveryTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['delivery_time']) ? $data['delivery_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCheckStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['check_status']) ? $data['check_status'] : '');
        $list = $this->getCheckStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getExamineStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_status']) ? $data['examine_status'] : '');
        $list = $this->getExamineStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getExamineTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_time']) ? $data['examine_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPurchaseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setDeliveryTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_code', 'goods_code', [], 'LEFT')->setEagerlyType(0);
    }
}
