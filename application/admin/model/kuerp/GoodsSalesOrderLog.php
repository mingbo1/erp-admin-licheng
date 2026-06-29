<?php

namespace app\admin\model\kuerp;

use think\Db;
use think\Model;
use think\Session;

class GoodsSalesOrderLog extends Model
{
    protected $name = 'kuerp_goods_sales_order_log';
    protected $autoWriteTimestamp = false;

    public function admin()
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id')
            ->bind(['admin_username' => 'username']);
    }

    /**
     * 写入销货订单操作日志（静态方法，供各处调用）
     *
     * @param int    $orderId    销货订单ID (kuerp_goods_sales_order.id)
     * @param string $action     操作类型标签
     * @param string $note       详细描述
     * @param string $sourceType 来源单据类型 (sales_order|sales|purchase_order|purchase|assemble)
     * @param int    $sourceId   来源单据ID
     */
    public static function writeLog($orderId, $action, $note = '', $sourceType = '', $sourceId = 0)
    {
        $admin = Session::get('admin');
        Db::name('kuerp_goods_sales_order_log')->insert([
            'order_id'    => (int)$orderId,
            'action'      => $action,
            'note'        => $note,
            'admin_id'    => $admin ? (int)$admin['id'] : 0,
            'source_type' => $sourceType,
            'source_id'   => (int)$sourceId,
            'createtime'  => time(),
        ]);
    }

    /**
     * 通过销货订单purchase_code查询并写入日志
     *
     * @param string $purchaseCode 销货订单号 (kuerp_goods_sales_order.purchase_code)
     * @param string $action       操作类型标签
     * @param string $note         详细描述
     * @param string $sourceType   来源单据类型
     * @param int    $sourceId     来源单据ID
     * @return bool 是否成功写入
     */
    public static function writeLogByCode($purchaseCode, $action, $note = '', $sourceType = '', $sourceId = 0)
    {
        if (empty($purchaseCode)) {
            return false;
        }
        $orderId = Db::name('kuerp_goods_sales_order')->where(['purchase_code' => $purchaseCode])->value('id');
        if (!$orderId) {
            return false;
        }
        self::writeLog($orderId, $action, $note, $sourceType, $sourceId);
        return true;
    }
}
