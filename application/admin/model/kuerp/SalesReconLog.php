<?php

namespace app\admin\model\kuerp;

use think\Model;

/**
 * 销货对账单操作日志
 */
class SalesReconLog extends Model
{

    // 表名
    protected $name = 'kuerp_sales_recon_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;

    /**
     * 写操作日志
     */
    public static function writeLog($reconId, $action, $note = '')
    {
        $admin = \think\Session::get('admin');
        return self::create([
            'recon_id'   => (int)$reconId,
            'action'     => $action,
            'note'       => (string)$note,
            'admin_id'   => isset($admin['id']) ? (int)$admin['id'] : 0,
            'createtime' => time(),
        ], true);
    }
}
