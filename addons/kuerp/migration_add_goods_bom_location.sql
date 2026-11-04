--
-- 迁移：BOM明细新增「放置位置」
-- fa_kuerp_goods_bom_info 新增 location 字段（父商品行 type=1 / 子商品行 type=2 都可填写，建BOM时手填）
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_bom_location.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_goods_bom_location`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_goods_bom_location`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_bom_info'
           AND COLUMN_NAME = 'location') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_bom_info`
            ADD COLUMN `location` varchar(255) NOT NULL DEFAULT '' COMMENT '放置位置' AFTER `number`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_goods_bom_location`();
DROP PROCEDURE `kuerp_add_goods_bom_location`;
