<?php

namespace app\admin\model\kuerp;

use think\Db;
use think\Model;
use think\Session;

/**
 * 外协加工单操作记录
 */
class ProcessLog extends Model
{
    protected $name = 'kuerp_process_log';
    protected $autoWriteTimestamp = false;

    /**
     * 写入外协加工单操作日志（静态方法，供各处调用）
     *
     * @param int    $processId 加工单ID (kuerp_process.id)
     * @param string $action    操作类型标签
     * @param string $note      详细描述
     */
    public static function writeLog($processId, $action, $note = '')
    {
        if (empty($processId)) {
            return false;
        }
        $admin = Session::get('admin');
        Db::name('kuerp_process_log')->insert([
            'process_id' => (int)$processId,
            'action'     => $action,
            'note'       => mb_substr($note, 0, 500, 'utf-8'),
            'admin_id'   => $admin ? (int)$admin['id'] : 0,
            'createtime' => time(),
        ]);
        return true;
    }
}
