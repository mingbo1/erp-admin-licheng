--
-- 迁移：商品「规格型号」字段加长
-- fa_kuerp_goods.goods_specification_id 由 varchar(50) 加长到 varchar(255)
-- 原因：录入较长的规格/型号时保存报
--   1406 Data too long for column 'goods_specification_id'
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_widen_goods_specification_id.sql
-- 可重复执行（已是 varchar(255) 时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_widen_goods_specification_id`;
DELIMITER $$
CREATE PROCEDURE `kuerp_widen_goods_specification_id`()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods'
                  AND COLUMN_NAME = 'goods_specification_id'
                  AND CHARACTER_MAXIMUM_LENGTH < 255) THEN
        ALTER TABLE `fa_kuerp_goods`
            MODIFY COLUMN `goods_specification_id` varchar(255) NOT NULL DEFAULT '' COMMENT '规格型号';
    END IF;
END$$
DELIMITER ;

CALL `kuerp_widen_goods_specification_id`();
DROP PROCEDURE `kuerp_widen_goods_specification_id`;
