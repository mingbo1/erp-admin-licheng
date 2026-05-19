<?php

namespace app\admin\model\kuerp;

use think\Model;
use traits\model\SoftDelete;

class MiniBanner extends Model
{
    use SoftDelete;

    protected $name = 'kuerp_mini_banner';

    protected $autoWriteTimestamp = false;

    protected $deleteTime = 'deletetime';

    protected $append = [
        'status_text'
    ];

    public function getStatusList()
    {
        return ['normal' => __('启用'), 'hidden' => __('禁用')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }
}
