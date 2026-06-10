<?php

namespace app\admin\model\kuerp;

use think\Model;


class GoodsLog extends Model
{

    // 表名
    protected $name = 'kuerp_goods_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'purchase_time_text',
        'business_type_text'
    ];
    

    
    public function getBusinessTypeList()
    {
        return [
            '1'  => __('Business_type 1'),
            '2'  => __('Business_type 2'),
            '3'  => __('Business_type 3'),
            '4'  => __('Business_type 4'),
            '5'  => __('Business_type 5'),
            '6'  => __('Business_type 6'),
            '7'  => __('Business_type 7'),
            '8'  => __('Business_type 8'),
            '9'  => __('Business_type 9'),
            '10' => __('Business_type 10'),
            '11' => __('Business_type 11'),
            '12' => __('Business_type 12'),
            '13' => __('Business_type 13'),
            '14' => __('Business_type 14'),
            '15' => __('Business_type 15'),
            '16' => __('Business_type 16'),
            '17' => __('Business_type 17'),
        ];
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
