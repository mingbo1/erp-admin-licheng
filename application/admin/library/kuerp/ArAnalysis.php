<?php

namespace app\admin\library\kuerp;

use think\Db;

/**
 * 应收账款分析（月度快照）
 *
 * 口径严格按 资料/财务功能/应收账款及考核.xlsx：
 *   期初应收账款(上月末余额)  = 上月快照的本月账面应收余额；无上月快照时按「截至上月末的滚动计算」兜底
 *   本月含税销售总额(新增)    = 本月已审核销货单 real_money + customers_money + 对账差异
 *   本月实际回款总额          = 本月已审核收款单核销到「销货」的金额（与单据不相关，取收款单总额）
 *   本月退货/红冲金额          = 本月已审核销货退货单（real_money + customers_money），正数
 *   本月账面应收余额          = 期初 + 本月销售 − 本月回款 − 本月退货
 *   其中：逾期应收账款        = 已开票且 开票日 + 客户信用账期 < 分析时点 且尚未收完的金额
 *   本月销售回款率            = 本月实际回款总额 / 本月含税销售总额
 *   对账差异金额              = 本月登记的差异合计（本表的「月度新增」，不是累计）
 *
 * 以「客户」为一行，「销售负责人」取客户的 staff_member_id（客户档案维护）。
 */
class ArAnalysis
{

    /**
     * 生成指定月份的快照
     *
     * @param string $period YYYY-MM
     * @param bool   $force  已存在时是否覆盖
     * @return array ['period'=>..., 'rows'=>int, 'skipped'=>bool]
     */
    public static function build($period, $force = true)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \Exception('分析月份格式错误，应为 YYYY-MM');
        }
        $exists = Db::name('kuerp_ar_analysis')->where(['period' => $period])->count();
        if ($exists && !$force) {
            return ['period' => $period, 'rows' => $exists, 'skipped' => true];
        }

        $start = strtotime($period . '-01 00:00:00');
        $end = strtotime(date('Y-m-d 00:00:00', strtotime('+1 month', $start)));
        $prevStart = strtotime(date('Y-m-d 00:00:00', strtotime('-1 month', $start)));

        // 期初：优先取上月快照的期末余额
        $beginMap = [];
        $prevPeriod = date('Y-m', $prevStart);
        $prevRows = Db::name('kuerp_ar_analysis')->where(['period' => $prevPeriod])->select();
        foreach ($prevRows as $prevRow) {
            $beginMap[$prevRow['customers_id']] = (float)$prevRow['end_money'];
        }

        $customers = Db::name('kuerp_customers')->field('id,customer_code,customer_name,saler,staff_member_id,credit_days')->select();
        $staffMap = Db::name('kuerp_staff_member')->column('name', 'id');

        // 本月销售
        $salesRows = Db::name('kuerp_goods_sales')
            ->where(['examine_status' => '1'])
            ->where('purchase_time', '>=', $start)
            ->where('purchase_time', '<', $end)
            ->field('customers_id,SUM(real_money + customers_money + diff_money) AS total')
            ->group('customers_id')
            ->select();
        $salesMap = [];
        foreach ($salesRows as $row) {
            $salesMap[$row['customers_id']] = (float)$row['total'];
        }

        // 本月退货（正数）
        $returnRows = Db::name('kuerp_goods_sales_exchange')
            ->where(['examine_status' => '1'])
            ->where('purchase_time', '>=', $start)
            ->where('purchase_time', '<', $end)
            ->field('customers_id,SUM(real_money + customers_money - diff_money) AS total')
            ->group('customers_id')
            ->select();
        $returnMap = [];
        foreach ($returnRows as $row) {
            $returnMap[$row['customers_id']] = (float)$row['total'];
        }

        // 本月实际回款：已审核收款单（与单据无关，取整单金额）
        $collectionRows = Db::name('kuerp_collection')
            ->where(['examine_status' => '1'])
            ->where('purchase_time', '>=', $start)
            ->where('purchase_time', '<', $end)
            ->field('customers_id,SUM(money) AS total')
            ->group('customers_id')
            ->select();
        $collectionMap = [];
        foreach ($collectionRows as $row) {
            $collectionMap[$row['customers_id']] = (float)$row['total'];
        }

        // 本月差异（按登记时间）
        $diffMap = \app\admin\model\kuerp\SalesReconDiff::sumByCustomers(
            array_column($customers, 'id'),
            $start,
            $end
        );
        $diffReasonMap = [];
        $diffReasons = Db::name('kuerp_sales_recon_diff')
            ->where('createtime', '>=', $start)
            ->where('createtime', '<', $end)
            ->field('customers_id,reason')
            ->order('id asc')
            ->select();
        foreach ($diffReasons as $diffReason) {
            $diffReasonMap[$diffReason['customers_id']][] = $diffReason['reason'];
        }

        // 逾期：本月分析时点前，已开票（invoicing.invoice_status=1）且「开票日+账期」已过、尚未收完的销货单
        $overdueMap = self::overdueMap($end, $customers);

        // 无上月快照的客户：用「截至上月末的滚动口径」兜底
        $needFallback = [];
        foreach ($customers as $customer) {
            if (!isset($beginMap[$customer['id']])) {
                $needFallback[] = $customer['id'];
            }
        }
        if ($needFallback) {
            // 期初 = 截至本月 1 日 0 点（不含）的滚动余额
            $fallback = self::rollingBalance($needFallback, $start);
            foreach ($fallback as $customerId => $money) {
                $beginMap[$customerId] = $money;
            }
        }

        Db::startTrans();
        try {
            Db::name('kuerp_ar_analysis')->where(['period' => $period])->delete();
            $insert = [];
            $now = time();
            foreach ($customers as $customer) {
                $cid = $customer['id'];
                $begin = isset($beginMap[$cid]) ? (float)$beginMap[$cid] : 0;
                $sale = isset($salesMap[$cid]) ? (float)$salesMap[$cid] : 0;
                $collection = isset($collectionMap[$cid]) ? (float)$collectionMap[$cid] : 0;
                $return = isset($returnMap[$cid]) ? (float)$returnMap[$cid] : 0;
                $diff = isset($diffMap[$cid]) ? (float)$diffMap[$cid] : 0;
                $endMoney = round($begin + $sale - $collection - $return, 4);
                $rate = $sale > 0 ? round($collection / $sale, 4) : 0;
                $insert[] = [
                    'period'           => $period,
                    'customers_id'     => $cid,
                    'customer_name'    => $customer['customer_name'],
                    'customer_code'    => $customer['customer_code'],
                    'sale_user'        => (int)$customer['staff_member_id'],
                    'sale_user_name'   => isset($staffMap[$customer['staff_member_id']]) ? $staffMap[$customer['staff_member_id']] : '',
                    'credit_days'      => (int)$customer['credit_days'],
                    'begin_money'      => round($begin, 4),
                    'sale_money'       => round($sale, 4),
                    'collection_money' => round($collection, 4),
                    'return_money'     => round($return, 4),
                    'end_money'        => $endMoney,
                    'overdue_money'    => isset($overdueMap[$cid]) ? round($overdueMap[$cid], 4) : 0,
                    'collection_rate'  => $rate,
                    'diff_money'       => round($diff, 4),
                    'diff_reason'      => isset($diffReasonMap[$cid]) ? mb_substr(implode('；', array_unique($diffReasonMap[$cid])), 0, 500) : '',
                    'createtime'       => $now,
                    'updatetime'       => $now,
                ];
            }
            if ($insert) {
                foreach (array_chunk($insert, 200) as $chunk) {
                    Db::name('kuerp_ar_analysis')->insertAll($chunk);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
        return ['period' => $period, 'rows' => count($insert), 'skipped' => false];
    }

    /**
     * 逾期应收：开票日 + 客户信用账期 早于 $asOf，且该单据尚未收完
     *
     * 口径（按 应收账款及考核.xlsx 备注：「销售单在开票之后算起超过设定的账期，即视为逾期」）：
     *   开票日 = 该销货单的实际开票时间
     *            · 对账单开票 → kuerp_sales_recon_invoice.invoice_date
     *            · 开票管理单独开票 → kuerp_sales_invoicing_log.times
     *            · 都取不到时退回台账 bill_time（单据日期）
     *   金额   = 该销货单尚未收回的部分（real_money + customers_money + 差异 − pay_money）
     *
     * @param int   $asOf 分析时点（时间戳）
     * @param array $customers 客户列表（含 credit_days）
     * @return array [customers_id => 逾期金额]
     */
    protected static function overdueMap($asOf, $customers)
    {
        $creditMap = [];
        foreach ($customers as $customer) {
            $creditMap[$customer['id']] = (int)$customer['credit_days'];
        }
        $invoicings = Db::name('kuerp_sales_invoicing')
            ->where(['invoice_status' => '1'])
            ->field('bill_code,customers_id,bill_time,recon_id')
            ->select();
        if (empty($invoicings)) {
            return [];
        }

        // 对账单开票时固化的到期日（账期在开票那一刻快照，客户后来改账期不影响已开票的对账单）
        $reconDueMap = [];
        $reconDueRows = Db::name('kuerp_sales_recon')
            ->where('due_date', '>', 0)
            ->field('id,due_date')
            ->select();
        foreach ($reconDueRows as $reconDueRow) {
            $reconDueMap[$reconDueRow['id']] = (int)$reconDueRow['due_date'];
        }

        // 开票日：对账单发票日期
        $reconIds = array_values(array_filter(array_unique(array_column($invoicings, 'recon_id'))));
        if ($reconIds) {
            $reconInvoices = Db::name('kuerp_sales_recon_invoice')
                ->where(['recon_id' => ['in', $reconIds]])
                ->field('recon_id,invoice_date')
                ->order('invoice_date asc')
                ->select();
            $reconDateMap = [];
            foreach ($reconInvoices as $reconInvoice) {
                if (empty($reconInvoice['invoice_date'])) {
                    continue;
                }
                $rid = $reconInvoice['recon_id'];
                $reconDateMap[$rid] = isset($reconDateMap[$rid])
                    ? max($reconDateMap[$rid], (int)$reconInvoice['invoice_date'])
                    : (int)$reconInvoice['invoice_date'];
            }
            foreach ($invoicings as $invoicing) {
                if (!empty($invoicing['recon_id']) && isset($reconDateMap[$invoicing['recon_id']])) {
                    $invoiceDateMap[$invoicing['bill_code']] = $reconDateMap[$invoicing['recon_id']];
                }
            }
        }
        // 开票日：开票管理的开票时间
        $logDates = Db::name('kuerp_sales_invoicing_log')
            ->where(['bill_code' => ['in', array_column($invoicings, 'bill_code')]])
            ->field('bill_code,times')
            ->order('times asc')
            ->select();
        foreach ($logDates as $logDate) {
            $code = $logDate['bill_code'];
            if (isset($invoiceDateMap[$code])) {
                continue;
            }
            if (!empty($logDate['times'])) {
                $invoiceDateMap[$code] = (int)$logDate['times'];
            }
        }

        $codes = array_values(array_filter(array_unique(array_column($invoicings, 'bill_code'))));
        $salesMap = $codes
            ? Db::name('kuerp_goods_sales')
                ->where(['purchase_code' => ['in', $codes]])
                ->column('customers_id,real_money,customers_money,diff_money,pay_money', 'purchase_code')
            : [];

        $map = [];
        foreach ($invoicings as $invoicing) {
            $code = $invoicing['bill_code'];
            $sales = isset($salesMap[$code]) ? $salesMap[$code] : null;
            if (!$sales) {
                continue;
            }
            // 优先级：对账单开票时固化的到期日 > 动态「开票日 + 客户当前账期」
            $reconDue = (!empty($invoicing['recon_id']) && isset($reconDueMap[$invoicing['recon_id']]))
                ? (int)$reconDueMap[$invoicing['recon_id']]
                : 0;
            if ($reconDue > 0) {
                $due = $reconDue;
            } else {
                $days = isset($creditMap[$sales['customers_id']]) ? $creditMap[$sales['customers_id']] : 0;
                if ($days <= 0) {
                    continue; // 未设置账期不判逾期
                }
                $invoiceDate = isset($invoiceDateMap[$code]) ? $invoiceDateMap[$code] : (int)$invoicing['bill_time'];
                if (!$invoiceDate) {
                    continue;
                }
                $due = $invoiceDate + $days * 86400;
            }
            if ($due >= $asOf) {
                continue;
            }
            $should = (float)$sales['real_money'] + (float)$sales['customers_money'] + (float)$sales['diff_money'];
            $unpaid = round($should - (float)$sales['pay_money'], 4);
            if ($unpaid <= 0) {
                continue;
            }
            $cid = $sales['customers_id'];
            $map[$cid] = round((isset($map[$cid]) ? $map[$cid] : 0) + $unpaid, 4);
        }
        return $map;
    }

    /**
     * 滚动余额兜底：截至 $until（不含）的
     *   已审核销货（含差异） − 已审核退货 − 已审核收款
     *
     * @param array $customersIds
     * @param int   $until
     * @return array [customers_id => 余额]
     */
    protected static function rollingBalance($customersIds, $until)
    {
        $customersIds = array_values(array_filter(array_unique(array_map('intval', (array)$customersIds))));
        if (empty($customersIds)) {
            return [];
        }
        $map = [];
        $rows = Db::name('kuerp_goods_sales')
            ->where(['customers_id' => ['in', $customersIds], 'examine_status' => '1'])
            ->where('purchase_time', '<', $until)
            ->field('customers_id,SUM(real_money + customers_money + diff_money) AS total')
            ->group('customers_id')
            ->select();
        foreach ($rows as $row) {
            $map[$row['customers_id']] = (isset($map[$row['customers_id']]) ? $map[$row['customers_id']] : 0) + (float)$row['total'];
        }
        $rows = Db::name('kuerp_goods_sales_exchange')
            ->where(['customers_id' => ['in', $customersIds], 'examine_status' => '1'])
            ->where('purchase_time', '<', $until)
            ->field('customers_id,SUM(real_money + customers_money - diff_money) AS total')
            ->group('customers_id')
            ->select();
        foreach ($rows as $row) {
            $map[$row['customers_id']] = (isset($map[$row['customers_id']]) ? $map[$row['customers_id']] : 0) - (float)$row['total'];
        }
        $rows = Db::name('kuerp_collection')
            ->where(['customers_id' => ['in', $customersIds], 'examine_status' => '1'])
            ->where('purchase_time', '<', $until)
            ->field('customers_id,SUM(money) AS total')
            ->group('customers_id')
            ->select();
        foreach ($rows as $row) {
            $map[$row['customers_id']] = (isset($map[$row['customers_id']]) ? $map[$row['customers_id']] : 0) - (float)$row['total'];
        }
        foreach ($map as $k => $v) {
            $map[$k] = round($v, 4);
        }
        return $map;
    }

    /**
     * 当前应分析的月份（上个月），用于「每月1日0点分析」后的默认展示
     *
     * @return string YYYY-MM
     */
    public static function defaultPeriod()
    {
        return date('Y-m', strtotime('-1 month', strtotime(date('Y-m-01'))));
    }
}
