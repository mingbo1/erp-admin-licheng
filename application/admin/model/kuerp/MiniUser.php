<?php

namespace app\admin\model\kuerp;

use think\Model;
use traits\model\SoftDelete;

class MiniUser extends Model
{
    use SoftDelete;

    protected $name = 'kuerp_mini_user';

    protected $autoWriteTimestamp = false;

    protected $deleteTime = 'deletedtime';

    protected $append = [
        'status_text',
        'apptype_text',
    ];

    public function getStatusList()
    {
        return ['normal' => __('正常'), 'hidden' => __('禁用')];
    }

    public function getApptypeList()
    {
        return ['miniapp' => __('小程序'), 'mp' => __('公众号'), 'mobile' => __('手机注册')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function getApptypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['apptype'] ?? '');
        $list = $this->getApptypeList();
        return $list[$value] ?? '';
    }
}
