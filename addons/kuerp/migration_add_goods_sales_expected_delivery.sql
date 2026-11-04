-- 计划排期(销货排期)审核时填写"预计交付日期"
-- 字段加在 fa_kuerp_goods_sales 上（计划排期与销货单共用同一张表，两边列表同步展示）
-- 存 YYYY-MM-DD 字符串（layui 表格行内编辑/二次修改需要直接可读的值）
-- 用法: mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_sales_expected_delivery.sql

ALTER TABLE `fa_kuerp_goods_sales`
    ADD COLUMN `expected_delivery_date` varchar(10) NULL DEFAULT NULL COMMENT '预计交付日期(计划排期审核时填写,可二次修改,不打印)' AFTER `delivery_time`;
