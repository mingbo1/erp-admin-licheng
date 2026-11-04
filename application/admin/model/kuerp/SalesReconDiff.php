<?php

namespace app\admin\model\kuerp;

use think\Db;
use think\Model;

/**
 * 销货对账差异记录
 *
 * 对账单二审驳回后，销售/财务可针对单张销货单（或销货退货单）登记差异金额：
 *   正数 = 增加应收（客户要多付）
 *   负数 = 减少应收（客户少付，即让步/折让）
 * 差异直接叠加到该单据的应收金额上（退货单本身是负数，正差异即减少退货金额），
 * 并会同步到 kuerp_writeoff_source，使收款单可核销额度包含差异。
 */
class SalesReconDiff extends Model
{

    // 表名
    protected $name = 'kuerp_sales_recon_diff';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 按单号批量取差异合计
     *
     * @param array $codes 单据编号（销货单/销货退货单）
     * @return array [doc_code => 差异合计]
     */
    public static function sumByCodes($codes)
    {
        $codes = array_values(array_filter(array_unique((array)$codes)));
        if (empty($codes)) {
            return [];
        }
        $rows = Db::name('kuerp_sales_recon_diff')
            ->where(['doc_code' => ['in', $codes]])
            ->field('doc_code,SUM(diff_money) AS total')
            ->group('doc_code')
            ->select();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['doc_code']] = round((float)$row['total'], 4);
        }
        return $map;
    }

    /**
     * 单号的差异合计
     *
     * @param string $code
     * @return float
     */
    public static function sumByCode($code)
    {
        $map = self::sumByCodes([$code]);
        return isset($map[$code]) ? $map[$code] : 0.0;
    }

    /**
     * 按客户批量取差异合计（可选时间区间，按录入时间）
     *
     * @param array $customersIds
     * @param int   $start 开始时间戳（含）
     * @param int   $end   结束时间戳（不含）
     * @return array [customers_id => 差异合计]
     */
    public static function sumByCustomers($customersIds, $start = 0, $end = 0)
    {
        $customersIds = array_values(array_filter(array_unique(array_map('intval', (array)$customersIds))));
        if (empty($customersIds)) {
            return [];
        }
        $query = Db::name('kuerp_sales_recon_diff')
            ->where(['customers_id' => ['in', $customersIds]]);
        if ($start) {
            $query->where('createtime', '>=', $start);
        }
        if ($end) {
            $query->where('createtime', '<', $end);
        }
        $rows = $query->field('customers_id,SUM(diff_money) AS total')->group('customers_id')->select();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['customers_id']] = round((float)$row['total'], 4);
        }
        return $map;
    }

    /**
     * 对账单的差异明细（带附件）
     *
     * @param int $reconId
     * @return array
     */
    public static function listByRecon($reconId)
    {
        $rows = Db::name('kuerp_sales_recon_diff')->where(['recon_id' => $reconId])->order('id asc')->select();
        if (empty($rows)) {
            return [];
        }
        $adminIds = array_filter(array_unique(array_column($rows, 'admin_id')));
        $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];

        $logs = Db::name('kuerp_enclosure_log')
            ->where(['order_id' => ['in', array_column($rows, 'id')], 'type' => '13'])
            ->order('id asc')
            ->select();
        $encIds = array_values(array_filter(array_unique(array_column($logs, 'enclosure_id'))));
        $encMap = [];
        if ($encIds) {
            $encRows = Enclosure::where(['id' => ['in', $encIds]])->select();
            foreach ($encRows as $encRow) {
                Enclosure::fillFileInfo($encRow);
                $encMap[$encRow['id']] = [
                    'id'          => $encRow['id'],
                    'name'        => $encRow['name'],
                    'url'         => $encRow['url'],
                    'file_suffix' => $encRow['file_suffix'],
                    'is_image'    => $encRow['is_image'],
                ];
            }
        }
        $fileMap = [];
        foreach ($logs as $log) {
            if (isset($encMap[$log['enclosure_id']])) {
                $fileMap[$log['order_id']][] = $encMap[$log['enclosure_id']];
            }
        }

        foreach ($rows as $k => $row) {
            $rows[$k]['admin_name'] = isset($adminMap[$row['admin_id']]) ? $adminMap[$row['admin_id']] : '';
            $rows[$k]['time_text'] = $row['createtime'] ? date('Y-m-d H:i', $row['createtime']) : '';
            $rows[$k]['files'] = isset($fileMap[$row['id']]) ? $fileMap[$row['id']] : [];
        }
        return $rows;
    }
}
