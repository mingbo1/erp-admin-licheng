<?php

namespace addons\kuerp\model;

use think\Model;
use traits\model\SoftDelete;

class MiniUser extends Model
{
    use SoftDelete;

    protected $name = 'kuerp_mini_user';

    protected $autoWriteTimestamp = false;

    protected $deleteTime = 'deletedtime';

    protected $type = [
        'gender' => 'integer',
    ];

    const APPTYPE_MAP = [
        'miniapp' => '小程序',
        'mp'      => '公众号',
        'mobile'  => '手机注册',
    ];

    const USER_TYPE_MAP = [
        'sales'    => '销售员',
        'supplier' => '供应商',
    ];

    public function user()
    {
        return $this->belongsTo('\app\common\model\User', 'user_id', 'id');
    }

    public function staffMember()
    {
        return $this->belongsTo('\app\admin\model\kuerp\StaffMember', 'staff_id', 'id');
    }
}
