<?php

namespace app\admin\model\kuerp;

use think\Model;
use traits\model\SoftDelete;

class StoneInbound extends Model
{
    use SoftDelete;

    protected $name = 'kuerp_stone_inbound';
    protected $autoWriteTimestamp = false;
    protected $deleteTime = 'deletetime';

    protected $append = [
        'examine_status_text'
    ];

    public function getExamineStatusList()
    {
        return ['0' => __('未审核'), '1' => __('已审核')];
    }

    public function getExamineStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['examine_status'] ?? '0');
        $list = $this->getExamineStatusList();
        return $list[$value] ?? '';
    }

    public function info()
    {
        return $this->hasMany(\app\admin\model\kuerp\StoneInboundInfo::class, 'in_id', 'id');
    }
}
