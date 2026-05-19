<?php

namespace addons\kuerp\model;

use think\Model;

class MiniConfig extends Model
{
    protected $name = 'kuerp_mini_config';

    protected $autoWriteTimestamp = false;

    /**
     * 获取配置值
     */
    public static function getValue($name, $default = '')
    {
        $row = self::where('name', $name)->find();
        return $row ? $row->value : $default;
    }
}
