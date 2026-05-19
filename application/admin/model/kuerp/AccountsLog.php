<?php

namespace app\admin\model\kuerp;

use think\Model;

class AccountsLog extends Model
{
    // 表名
    protected $name = 'kuerp_accounts_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'pay_time_text',
        'business_type_text'
    ];
    

    
    public function getBusinessTypeList()
    {
        return ['1' => __('Business_type 1'), '2' => __('Business_type 2'), '3' => __('Business_type 3'), '4' => __('Business_type 4'), '5' => __('Business_type 5'), '6' => __('Business_type 6'), '7' => __('Business_type 7'), '8' => __('Business_type 8'), '9' => __('Business_type 9'), '10' => __('Business_type 10'), '11' => __('Business_type 11'), '12' => __('Business_type 12')];
    }

    public function getAccountsList(){
        return (new Accounts())->where(['status'=>1])->select();
    }
    public function getCustomersList(){
        return (new Customers())->where(['status'=>1])->select();
    }
    public function getSuppliersList(){
        return (new Suppliers())->where(['status'=>1])->select();
    }
    public function getSaleList(){
        return (new StaffMember())->where(['status'=>1])->select();
    }

    public function getPayTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_time']) ? $data['pay_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getBusinessTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['business_type']) ? $data['business_type'] : '');
        $list = $this->getBusinessTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setPayTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function customers()
    {
        return $this->belongsTo('Customers', 'customers_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function suppliers()
    {
        return $this->belongsTo('Suppliers', 'supplyer_code', 'supplyer_code', [], 'LEFT')->setEagerlyType(0);
    }
}
