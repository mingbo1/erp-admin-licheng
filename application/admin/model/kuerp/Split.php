<?php

namespace app\admin\model\kuerp;

use think\Model;


class Split extends Model
{

    // 表名
    protected $name = 'kuerp_split';
    
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
        'examine_time_text'
    ];
    

    
    public function getBusinessTypeList()
    {
        return ['1' => __('Business_type 1'), '2' => __('Business_type 2')];
    }

    public function getExamineStatusList()
    {
        return ['0' => __('Examine_status 0'), '1' => __('Examine_status 1')];
    }


    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getStoresList(){
        return (new Stores())->where(['status'=>1])->select();
    }
    public function getGoodsList(){
        return (new Goods())->where(['status'=>1])->select();
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


    public function getExamineTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_time']) ? $data['examine_time'] : '');
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


    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
