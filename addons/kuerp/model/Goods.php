<?php

namespace addons\kuerp\model;

use think\Model;

class Goods extends Model
{

    // 表名
    protected $name = 'kuerp_goods';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'more_unit_text',
        'is_offten_text',
        'status_text'
    ];
    

    
    public function getMoreUnitList()
    {
        return ['1' => __('More_unit 1'), '2' => __('More_unit 2')];
    }

    public function getIsOfftenList()
    {
        return ['0' => __('Is_offten 0'), '1' => __('Is_offten 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getMoreUnitTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['more_unit']) ? $data['more_unit'] : '');
        $list = $this->getMoreUnitList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsOfftenTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_offten']) ? $data['is_offten'] : '');
        $list = $this->getIsOfftenList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function goodstype()
    {
        return $this->belongsTo('GoodsType', 'goods_type_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function unitconversion()
    {
        return $this->belongsTo('UnitConversion', 'more_unit_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function unit()
    {
        return $this->belongsTo('Unit', 'unit_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
