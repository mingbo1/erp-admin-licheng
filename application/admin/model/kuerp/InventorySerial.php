<?php

namespace app\admin\model\kuerp;

use think\Model;


class InventorySerial extends Model
{

    // 表名
    protected $name = 'kuerp_inventory_serial';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'purchase_time_text',
        'store_type_text'
    ];
    

    
    public function getStoreTypeList()
    {
        return ['1' => __('Store_type 1'), '2' => __('Store_type 2')];
    }


    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStoreTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['store_type']) ? $data['store_type'] : '');
        $list = $this->getStoreTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setPurchaseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'admin_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
