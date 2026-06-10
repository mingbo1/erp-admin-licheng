<?php

namespace app\admin\model\kuerp;

use think\Model;

class GoodsBom extends Model
{

    // 表名
    protected $name = 'kuerp_goods_bom';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
    ];

    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'admin_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function bomInfo()
    {
        return $this->hasMany('GoodsBomInfo', 'bom_id', 'id');
    }
}
