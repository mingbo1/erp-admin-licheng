<?php

namespace app\admin\model\kuerp;

use think\Model;

class PaymentAccount extends Model
{

    // 表名
    protected $name = 'kuerp_payment_account';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [

    ];
    

    







}
