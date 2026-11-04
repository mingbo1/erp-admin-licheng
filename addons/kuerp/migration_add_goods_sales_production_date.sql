--
-- 迁移：计划排期「生产日期」
-- 1. fa_kuerp_goods_sales 新增 production_date 字段（计划排期列表勾选「排产」时弹窗填写，生成组装单时带入生产日期）
--    存 YYYY-MM-DD 字符串（与 expected_delivery_date 同口径，layui 表格直接可读）
-- 2. expected_delivery_date 语义改为「预计生产完成日期」，同步字段注释
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_sales_production_date.sql
-- 可重复执行（列已存在时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_goods_sales_production_date`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_goods_sales_production_date`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales'
           AND COLUMN_NAME = 'production_date') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales`
            ADD COLUMN `production_date` varchar(10) NULL DEFAULT NULL COMMENT '生产日期(计划排期排产时填写)' AFTER `delivery_time`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales'
           AND COLUMN_NAME = 'expected_delivery_date') > 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales`
            MODIFY COLUMN `expected_delivery_date` varchar(10) NULL DEFAULT NULL COMMENT '预计生产完成日期(计划排期审核时填写,可二次修改,不打印)';
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_goods_sales_production_date`();
DROP PROCEDURE `kuerp_add_goods_sales_production_date`;
