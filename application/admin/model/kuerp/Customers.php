<?php

namespace app\admin\model\kuerp;

use think\Model;


class Customers extends Model
{

    // 表名
    protected $name = 'kuerp_customers';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    public function getCustomersList(){
        return (new Customertype())->where(['status'=>1])->select();
    }
    public function getSaleList(){
        return (new StaffMember())->where(['status'=>1])->select();
    }

    public function customerlevel()
    {
        return $this->belongsTo('Customerlevel', 'customerlevel_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function customertype()
    {
        return $this->belongsTo('Customertype', 'customertype_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function saler()
    {
        return $this->belongsTo('StaffMember', 'staff_member_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
