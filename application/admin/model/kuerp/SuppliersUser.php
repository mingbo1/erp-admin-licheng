<?php

namespace app\admin\model\kuerp;

use think\Model;

class SuppliersUser extends Model
{

    // 表名
    protected $name = 'kuerp_suppliers_user';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'is_first_text',
        'status_text'
    ];
    

    
    public function getIsFirstList()
    {
        return ['0' => __('Is_first 0'), '1' => __('Is_first 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getIsFirstTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_first']) ? $data['is_first'] : '');
        $list = $this->getIsFirstList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function suppliers()
    {
        return $this->belongsTo('Suppliers', 'sid', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
