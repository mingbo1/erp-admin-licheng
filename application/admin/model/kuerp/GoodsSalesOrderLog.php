<?php

namespace app\admin\model\kuerp;

use think\Model;

class GoodsSalesOrderLog extends Model
{
    protected $name = 'kuerp_goods_sales_order_log';
    protected $autoWriteTimestamp = false;

    public function admin()
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id')
            ->bind(['admin_username' => 'username']);
    }
}
