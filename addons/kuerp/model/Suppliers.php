<?php

namespace addons\kuerp\model;

use think\Model;

class Suppliers extends Model
{

    // 表名
    protected $name = 'kuerp_suppliers';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'account_time_text',
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getAccountTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['account_time']) ? $data['account_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setAccountTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function supplyertype()
    {
        return $this->belongsTo('Supplyertype', 'supplyertype_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
