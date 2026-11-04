<?php

namespace app\admin\model\kuerp;

use app\admin\model\Admin;
use think\Model;


class GoodsSales extends Model
{

    // 表名
    protected $name = 'kuerp_goods_sales';
    
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
        'delivery_mode_text',
        'pay_status_text',
        'check_status_text',
        'examine_status_text',
        'examine_time_text',
        'second_examine_status_text',
        'second_examine_time_text',
        'dunning_count',
    ];
    

    
    public function getPurchaseTypeList()
    {
        return ['1' => __('Purchase_type 1'), '2' => __('Purchase_type 2')];
    }

    /**
     * 发货方式：direct=直发，dropship=代发
     */
    public function getDeliveryModeList()
    {
        return ['direct' => __('Delivery_mode direct'), 'dropship' => __('Delivery_mode dropship')];
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


    public function getDeliveryModeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['delivery_mode']) ? $data['delivery_mode'] : 'direct');
        $list = $this->getDeliveryModeList();
        return isset($list[$value]) ? $list[$value] : $list['direct'];
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

    public function getStoresList(){
        return (new Stores())->where(['status'=>1])->select();
    }
    public function getSuppliersList(){
        return (new Suppliers())->where(['status'=>1])->select();
    }
    public function getExpendTypeList(){
        return (new ExpendType())->where(['status'=>1])->select();
    }
    public function getGoodsList(){
        return (new Goods())->where(['status'=>1])->select();
    }
    public function getAdminList(){
        return Admin::all();
    }
    public function getAccountsList(){
        return (new Accounts())->where(['status'=>1])->select();
    }
    public function getSaleList(){
        return (new StaffMember())->where(['status'=>1])->select();
    }
    public function getCustomersList(){
        return (new Customers())->where(['status'=>1])->select();
    }
    public function getStaffMemberList(){
        return (new StaffMember())->where(['status'=>1])->select();
    }

    public function preparation()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function examine()
    {
        return $this->belongsTo('app\admin\model\Admin', 'examine_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function secondExamine()
    {
        return $this->belongsTo('app\admin\model\Admin', 'second_examine_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getSecondExamineStatusList()
    {
        return ['0' => __('Second_examine_status 0'), '1' => __('Second_examine_status 1')];
    }

    public function getSecondExamineStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['second_examine_status']) ? $data['second_examine_status'] : '');
        $list = $this->getSecondExamineStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getSecondExamineTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['second_examine_time']) ? $data['second_examine_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setSecondExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function getDunningCountAttr($value, $data)
    {
        if (isset($value)) {
            return (int)$value;
        }
        $dunning = $this->getRelation('dunning');
        return $dunning ? count($dunning) : 0;
    }

    public function staffmember()
    {
        return $this->belongsTo('StaffMember', 'sale_user', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function dunning()
    {
        return $this->hasMany(\app\admin\model\kuerp\GoodsSalesDunning::class, 'sales_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(\app\admin\model\kuerp\GoodsSalesLog::class, 'sales_id', 'id')->order('id', 'desc');
    }
}
