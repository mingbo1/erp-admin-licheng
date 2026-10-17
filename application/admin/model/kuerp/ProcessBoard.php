<?php

namespace app\admin\model\kuerp;

use think\Model;

class ProcessBoard extends Model
{

    // 表名
    protected $name = 'kuerp_process_board';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'board_date_text',
    ];

    public function getStatusList()
    {
        return ['OK' => 'OK', 'NG' => 'NG'];
    }

    public function getBoardDateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['board_date']) ? $data['board_date'] : '');
        return is_numeric($value) && $value ? date("Y-m-d", $value) : $value;
    }
}
