-- ============================================================================
-- 移除采购单打印「订货说明」的两个设置项（2026-11-07，1.6.40）
--
-- 采购单打印页（kuerp/goods_purchase/printpage）底部的 1~5 条订货说明
-- （交货方式 / 收货地址 / 付款方式 / 质量保证 / 合同声明）整块已从视图移除；
-- 其中第 2、3 条原由设置项提供，现一并下线，从 设置 → 基础设置 中消失：
--   purchase_print_receipt  采购单打印收货信息
--   purchase_print_payment  采购单打印付款方式
--
-- 替代 migration_add_purchase_print_terms.sql（该文件已删除）。
-- 打印页不再读取这两个设置项，残留行不会影响任何功能，删除只为清理设置界面。
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_remove_purchase_print_terms.sql
-- 可重复执行。
-- ============================================================================

DELETE FROM `fa_kuerp_sets`
WHERE `name` IN ('purchase_print_receipt', 'purchase_print_payment');
