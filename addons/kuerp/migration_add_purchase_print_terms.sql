-- ============================================================================
-- 采购单打印按客户模板（资料/财务功能/采购单打印模板.xlsx）改版（2026-11-04）
--
-- 模板的「订货说明」第 2 条（收货地址）、第 3 条（付款方式）标注了"可更改"，
-- 故抽成两个设置项，在 设置 → 基础设置 里可改，采购单打印页直接引用：
--   purchase_print_receipt  收货信息（收货仓库/联系电话/详细地址）
--   purchase_print_payment  付款方式
--
-- 打印页（kuerp/goods_purchase/printpage）不依赖这两个设置项也能工作，
-- 取不到时按 公司电话 + 公司地址 自动拼收货信息、按内置文案输出付款方式。
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_purchase_print_terms.sql
-- 可重复执行。
-- ============================================================================

INSERT INTO `fa_kuerp_sets` (`type`,`is_edit`,`name`,`nickname`,`value`,`admin_id`,`notice`,`explain`,`is_checked`)
SELECT '1','0','purchase_print_receipt','采购单打印收货信息',
       '仓库  18974630250   浙江省杭州市萧山区新街街道长鸣路500号中交智慧交通科技园14幢2楼（走货梯）','0',
       '采购单打印「订货说明」第2条的收货地址与联系电话','留空则自动取 公司电话 + 公司地址','0'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_sets` WHERE `name` = 'purchase_print_receipt');

INSERT INTO `fa_kuerp_sets` (`type`,`is_edit`,`name`,`nickname`,`value`,`admin_id`,`notice`,`explain`,`is_checked`)
SELECT '1','0','purchase_print_payment','采购单打印付款方式',
       '收到合格、合法发票后60天内付款。若供方未按约出具发票，需方有权迟延付款，且不承担违约责任。','0',
       '采购单打印「订货说明」第3条的付款方式','留空则使用内置默认文案','0'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_sets` WHERE `name` = 'purchase_print_payment');
