<?php

namespace app\admin\model\kuerp;

use think\Model;


class GoodsSerial extends Model
{

    // 表名
    protected $name = 'kuerp_goods_serial';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'store_status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4')];
    }

    public function getStoreStatusList()
    {
        return ['0' => __('Store_status 0'), '1' => __('Store_status 1'), '2' => __('Store_status 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStoreStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['store_status']) ? $data['store_status'] : '');
        $list = $this->getStoreStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function accounts()
    {
        return $this->belongsTo('Accounts', 'goods_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
