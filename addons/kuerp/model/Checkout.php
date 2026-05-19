<?php

namespace addons\kuerp\model;

use think\Model;


class Checkout extends Model
{

    // 表名
    protected $name = 'kuerp_checkout';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

}
