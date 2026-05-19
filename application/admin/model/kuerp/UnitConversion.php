<?php

namespace app\admin\model\kuerp;

use think\Db;
use think\Model;

class UnitConversion extends Model
{
    // 表名
    protected $name = 'kuerp_unit_conversion';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $main_unit = Db::name('kuerp_unit')->where(['id'=>$row['main_unit']])->value('name');
            $vice_unit = Db::name('kuerp_unit')->where(['id'=>$row['vice_unit']])->value('name');
            $row->save(['unit_name' => $main_unit.','.$vice_unit.'(1:'.$row['advance_rate'].')']);
        });
        self::afterUpdate(function ($row) {
            $main_unit = Db::name('kuerp_unit')->where(['id'=>$row['main_unit']])->value('name');
            $vice_unit = Db::name('kuerp_unit')->where(['id'=>$row['vice_unit']])->value('name');
            $str = $main_unit.','.$vice_unit.'(1:'.$row['advance_rate'].')';
            Db::name('kuerp_unit_conversion')->where(['id'=>$row['id']])->update(['unit_name'=>$str]);
        });
    }







    public function units()
    {
        return $this->belongsTo('Unit', 'vice_unit', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function unit()
    {
        return $this->belongsTo('Unit', 'main_unit', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
