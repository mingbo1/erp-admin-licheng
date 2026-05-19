<?php

namespace app\admin\model\kuerp;

use think\Model;


class Prefix extends Model
{

    // 表名
    protected $name = 'kuerp_prefix';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;

    // 追加属性
    protected $append = [
        'year_text',
        'month_text',
        'day_text',
        'hour_text',
        'minute_text',
        'second_text'
    ];

    
    public function getYearList()
    {
        return ['1' => __('Year 1'), '2' => __('Year 2')];
    }

    public function getMonthList()
    {
        return ['1' => __('Month 1'), '2' => __('Month 2')];
    }

    public function getDayList()
    {
        return ['1' => __('Day 1'), '2' => __('Day 2')];
    }

    public function getHourList()
    {
        return ['1' => __('Hour 1'), '2' => __('Hour 2')];
    }

    public function getMinuteList()
    {
        return ['1' => __('Minute 1'), '2' => __('Minute 2')];
    }

    public function getSecondList()
    {
        return ['1' => __('Second 1'), '2' => __('Second 2')];
    }


    public function getYearTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['year']) ? $data['year'] : '');
        $list = $this->getYearList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getMonthTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['month']) ? $data['month'] : '');
        $list = $this->getMonthList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDayTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['day']) ? $data['day'] : '');
        $list = $this->getDayList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getHourTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['hour']) ? $data['hour'] : '');
        $list = $this->getHourList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getMinuteTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['minute']) ? $data['minute'] : '');
        $list = $this->getMinuteList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getSecondTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['second']) ? $data['second'] : '');
        $list = $this->getSecondList();
        return isset($list[$value]) ? $list[$value] : '';
    }

}
