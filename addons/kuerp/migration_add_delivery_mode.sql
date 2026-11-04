--
-- 迁移：销货订单新增「发货方式（代发/直发）」标记
-- 1. fa_kuerp_goods_sales_order / _info     销货订单 主表/明细
-- 2. fa_kuerp_goods_sales / _info           销货单（与计划排期共用表）主表/明细
-- 3. 回填：已生成的销货单/计划排期按关联的销货订单号同步发货方式
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_delivery_mode.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_delivery_mode`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_delivery_mode`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_order'
           AND COLUMN_NAME = 'delivery_mode') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_order`
            ADD COLUMN `delivery_mode` enum('direct','dropship') NOT NULL DEFAULT 'direct'
            COMMENT '发货方式:direct=直发,dropship=代发' AFTER `delivery_time`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_order_info'
           AND COLUMN_NAME = 'delivery_mode') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_order_info`
            ADD COLUMN `delivery_mode` enum('direct','dropship') NOT NULL DEFAULT 'direct'
            COMMENT '发货方式:direct=直发,dropship=代发' AFTER `delivery_time`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales'
           AND COLUMN_NAME = 'delivery_mode') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales`
            ADD COLUMN `delivery_mode` enum('direct','dropship') NOT NULL DEFAULT 'direct'
            COMMENT '发货方式:direct=直发,dropship=代发' AFTER `delivery_time`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_info'
           AND COLUMN_NAME = 'delivery_mode') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_info`
            ADD COLUMN `delivery_mode` enum('direct','dropship') NOT NULL DEFAULT 'direct'
            COMMENT '发货方式:direct=直发,dropship=代发' AFTER `delivery_time`;
    END IF;

    -- 已存在的销货单/计划排期：按关联的销货订单号回填
    UPDATE `fa_kuerp_goods_sales` gs
        INNER JOIN `fa_kuerp_goods_sales_order` o ON gs.purchase_order_id = o.purchase_code
        SET gs.delivery_mode = o.delivery_mode;

    UPDATE `fa_kuerp_goods_sales_info` gsi
        INNER JOIN `fa_kuerp_goods_sales` gs ON gsi.purchase_code = gs.purchase_code
        SET gsi.delivery_mode = gs.delivery_mode;

    UPDATE `fa_kuerp_goods_sales_order_info` oi
        INNER JOIN `fa_kuerp_goods_sales_order` o ON oi.purchase_code = o.purchase_code
        SET oi.delivery_mode = o.delivery_mode;
END$$
DELIMITER ;

CALL `kuerp_add_delivery_mode`();
DROP PROCEDURE `kuerp_add_delivery_mode`;
