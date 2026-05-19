<?php

namespace app\admin\model\kuerp;

use think\Model;


class GoodsBatchStock extends Model
{

    // 表名
    protected $name = 'kuerp_goods_batch_stock';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getGoodsList(){
        return (new Goods())->where(['status'=>1])->select();
    }
    public function getBatchList(){
        return (new GoodsBatch())->select();
    }


    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function goodsbatch()
    {
        return $this->belongsTo('GoodsBatch', 'batch_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
