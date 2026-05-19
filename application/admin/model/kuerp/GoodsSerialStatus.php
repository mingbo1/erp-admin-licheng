<?php

namespace app\admin\model\kuerp;

use think\Model;


class GoodsSerialStatus extends Model
{

    // 表名
    protected $name = 'kuerp_goods_serial_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'business_type_text',
        'store_status_text',
        'purchase_time_text'
    ];
    

    
    public function getBusinessTypeList()
    {
        return ['1' => __('Business_type 1'), '2' => __('Business_type 2'), '3' => __('Business_type 3'), '4' => __('Business_type 4'), '5' => __('Business_type 5'), '6' => __('Business_type 6')];
    }

    public function getStoreStatusList()
    {
        return ['0' => __('Store_status 0'), '1' => __('Store_status 1'), '2' => __('Store_status 2')];
    }

    public function getSerialList(){
        return (new GoodsSerial())->select();
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


    public function getStoreStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['store_status']) ? $data['store_status'] : '');
        $list = $this->getStoreStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPurchaseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function stores()
    {
        return $this->belongsTo('Stores', 'stores_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
