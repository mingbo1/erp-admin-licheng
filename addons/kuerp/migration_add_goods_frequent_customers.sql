--
-- 迁移：商品常用客户
-- fa_kuerp_goods 新增「常用客户」字段（逗号分隔客户ID；商品资料维护，销货订单选货按客户优先排序）
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_frequent_customers.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_goods_frequent_customers`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_goods_frequent_customers`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods'
           AND COLUMN_NAME = 'frequent_customers') = 0 THEN
        ALTER TABLE `fa_kuerp_goods`
            ADD COLUMN `frequent_customers` varchar(255) NOT NULL DEFAULT '' COMMENT '常用客户(客户ID,逗号分隔)' AFTER `is_offten`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_goods_frequent_customers`();
DROP PROCEDURE `kuerp_add_goods_frequent_customers`;
