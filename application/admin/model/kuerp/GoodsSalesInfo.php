<?php

namespace app\admin\model\kuerp;

use think\Model;


class GoodsSalesInfo extends Model
{

    // 表名
    protected $name = 'kuerp_goods_sales_info';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'purchase_type_text',
        'purchase_time_text',
        'delivery_time_text',
        'pay_status_text',
        'check_status_text',
        'examine_status_text',
        'examine_time_text'
    ];
    

    
    public function getPurchaseTypeList()
    {
        return ['1' => __('Purchase_type 1'), '2' => __('Purchase_type 2')];
    }

    public function getPayStatusList()
    {
        return ['0' => __('Pay_status 0'), '1' => __('Pay_status 1'), '2' => __('Pay_status 2')];
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


    public function getPayStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_status']) ? $data['pay_status'] : '');
        $list = $this->getPayStatusList();
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


    public function customers()
    {
        return $this->belongsTo('Customers', 'customers_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
        public function staffMember()
    {
        return $this->belongsTo('StaffMember', 'sale_user', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function getCustomersList(){
        return (new Customers())->where(['status'=>1])->select();
    }

    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_code', 'goods_code', [], 'LEFT')->setEagerlyType(0);
    }
    public function unit()
    {
        return $this->belongsTo('Unit', 'default_unit', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function stores()
    {
        return $this->belongsTo('Stores', 'stores_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function goodspurchase()
    {
        return $this->belongsTo('GoodsPurchase', 'purchase_code', 'purchase_code', [], 'LEFT')->setEagerlyType(0);
    }
}
