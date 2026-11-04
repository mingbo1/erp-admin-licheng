--
-- 迁移：销货对账「开票金额」改为包含客户承担费用 (2026-10-29)
--
-- 背景：原开票基数 = 销货合计净额 total_money（客户承担费用不开票）。
--       现改为 total_amount = total_money + total_customers_money，客户承担费用同样需要开票。
--       （代码侧已同步：SalesRecon::invoice() / refreshInvoiceStatus() / syncInvoiceLedger() / detail()）
--
-- 本脚本只做一次性数据订正：把历史上按旧口径判定为「已开票(invoice_status=2)」、
-- 但实际开票金额不足 total_amount 的对账单，重算为「部分开票(1)」，
-- 使其在列表里重新出现「开票」按钮，可以把客户承担费用补开掉。
--
-- 说明：invoice_status 是派生字段（由 invoice_amount 与 total_amount 比较得出），
--       重算不会改变任何金额；已有的发票记录保持不变。
--
-- 执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_fix_sales_recon_invoice_base.sql
-- 可重复执行。
--

UPDATE `__PREFIX__kuerp_sales_recon`
SET `invoice_status` = CASE
        WHEN `invoice_amount` <= 0.0001 THEN '0'
        WHEN `invoice_amount` + 0.0001 >= `total_amount` THEN '2'
        ELSE '1'
    END
WHERE `invoice_amount` + 0.0001 < `total_amount`
  AND `total_customers_money` <> 0;
