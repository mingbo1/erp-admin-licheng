<?php

namespace app\admin\model\kuerp;

use think\Model;

class GoodsSalesDunning extends Model
{
    protected $name = 'kuerp_goods_sales_dunning';

    protected $autoWriteTimestamp = false;

    public function admin()
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id')->bind(['admin_username' => 'username']);
    }
}
