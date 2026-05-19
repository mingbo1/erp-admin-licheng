<?php

namespace app\admin\model\kuerp;

use think\Model;


class Payment extends Model
{

    // 表名
    protected $name = 'kuerp_payment';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'purchase_time_text',
        'examine_time_text',
        'examine_status_text'
    ];
    

    
    public function getExamineStatusList()
    {
        return ['0' => __('Examine_status 0'), '1' => __('Examine_status 1')];
    }

    public function getCustomersList(){
        return (new Suppliers())->where(['status'=>1])->select();
    }
    public function getAccountsList(){
        return (new Accounts())->where(['status'=>1])->select();
    }
    public function getSaleList(){
        return (new StaffMember())->where(['status'=>1])->select();
    }
    public function getSettlementtype(){
        return (new Settlementtype())->where(['status'=>1])->select();
    }

    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getExamineTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_time']) ? $data['examine_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getExamineStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_status']) ? $data['examine_status'] : '');
        $list = $this->getExamineStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setPurchaseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function staffmember()
    {
        return $this->belongsTo('StaffMember', 'staff_member_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
