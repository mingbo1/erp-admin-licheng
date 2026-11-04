--
-- 迁移：销货订单新增「客户承担费用」字段
-- 1. fa_kuerp_goods_sales_order     销货订单主表
-- 2. fa_kuerp_goods_sales_order_info 销货订单明细
-- 说明：该字段原仅存在于销货单（fa_kuerp_goods_sales.customers_money），
--       本次前移到销货订单，生成销货单时自动带出。
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_sales_order_customers_money.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_sales_order_customers_money`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_sales_order_customers_money`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_order'
           AND COLUMN_NAME = 'customers_money') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_order`
            ADD COLUMN `customers_money` double(15,4) NOT NULL DEFAULT '0.0000'
            COMMENT '客户承担费用' AFTER `purchase_money`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_order_info'
           AND COLUMN_NAME = 'customers_money') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_order_info`
            ADD COLUMN `customers_money` double(15,4) NOT NULL DEFAULT '0.0000'
            COMMENT '客户承担费用' AFTER `purchase_money`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_sales_order_customers_money`();
DROP PROCEDURE `kuerp_add_sales_order_customers_money`;
