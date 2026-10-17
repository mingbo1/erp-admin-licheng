--
-- 迁移：销货订单 / 销货单 明细新增「生产备注」字段
-- 说明：生产备注为内部备注，仅在「销货排期单」打印页展示，
--       销货订单与销货单打印页不展示；表头「品号与功率」沿用原 remark 字段。
-- 影响表：
--   fa_kuerp_goods_sales_info       (销货单 / 销货排期 / 销货出库 共用明细表)
--   fa_kuerp_goods_sales_order_info (销货订单明细表)
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_production_remark.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_production_remark`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_production_remark`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_info'
           AND COLUMN_NAME = 'production_remark') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_info`
            ADD COLUMN `production_remark` varchar(255) NULL DEFAULT '' COMMENT '生产备注' AFTER `remark`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_order_info'
           AND COLUMN_NAME = 'production_remark') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_order_info`
            ADD COLUMN `production_remark` varchar(255) NULL DEFAULT '' COMMENT '生产备注' AFTER `remark`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_production_remark`();
DROP PROCEDURE `kuerp_add_production_remark`;