<?php

namespace app\admin\model\kuerp;

use think\Model;


class Assemble extends Model
{

    // 表名
    protected $name = 'kuerp_assemble';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'purchase_time_text',
        'business_type_text',
        'examine_status_text',
        'inbound_status_text',
        'examine_time_text',
        'outbound_status_text',
        'outbound_time_text',
        'in_time_text'
    ];
    

    
    public function getBusinessTypeList()
    {
        return ['1' => __('Business_type 1'), '2' => __('Business_type 2')];
    }

    public function getExamineStatusList()
    {
        return ['0' => __('Examine_status 0'), '1' => __('Examine_status 1')];
    }

    public function getInboundStatusList()
    {
        return ['0' => __('Inbound_status 0'), '1' => __('Inbound_status 1')];
    }

    public function getOutboundStatusList()
    {
        return ['0' => __('Outbound_status 0'), '1' => __('Outbound_status 1'), '2' => __('Outbound_status 2')];
    }

    public function getStoresList(){
        return (new Stores())->where(['status'=>1])->select();
    }
    public function getGoodsList(){
        return (new Goods())->where(['status'=>1])->select();
    }

    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getBusinessTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['business_type']) ? $data['business_type'] : '');
        $list = $this->getBusinessTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getExamineStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_status']) ? $data['examine_status'] : '');
        $list = $this->getExamineStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getInboundStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['inbound_status']) ? $data['inbound_status'] : '');
        $list = $this->getInboundStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getExamineTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_time']) ? $data['examine_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getOutboundStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['outbound_status']) ? $data['outbound_status'] : '');
        $list = $this->getOutboundStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getOutboundTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['outbound_time']) ? $data['outbound_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getInTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['in_time']) ? $data['in_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPurchaseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setOutboundTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setInTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
