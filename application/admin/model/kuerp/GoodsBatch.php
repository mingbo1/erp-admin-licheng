<?php

namespace app\admin\model\kuerp;

use think\Model;


class GoodsBatch extends Model
{

    // 表名
    protected $name = 'kuerp_goods_batch';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];



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
}
