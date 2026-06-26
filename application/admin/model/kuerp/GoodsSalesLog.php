<?php

namespace app\admin\model\kuerp;

use think\Model;

class GoodsSalesLog extends Model
{
    protected $name = 'kuerp_goods_sales_log';
    protected $autoWriteTimestamp = false;

    public function admin()
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id')
            ->bind(['admin_username' => 'username']);
    }
}
