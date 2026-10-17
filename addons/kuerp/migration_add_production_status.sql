--
-- 迁移：计划排期（原销货排期）
-- 1. fa_kuerp_goods_sales 新增「是否已排产」字段（排期列表显示/筛选，排产/取消排产操作）
-- 2. fa_auth_rule 菜单标题「销货排期」改为「计划排期」
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_production_status.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_production_status`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_production_status`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales'
           AND COLUMN_NAME = 'production_status') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales`
            ADD COLUMN `production_status` enum('0','1') DEFAULT '0' COMMENT '是否已排产(计划排期):0=未排产,1=已排产' AFTER `print_num`;
    END IF;

    UPDATE `fa_auth_rule` SET `title` = '计划排期'
        WHERE `name` = 'kuerp/goods_sales_schedule' AND `title` = '销货排期';

    UPDATE `fa_auth_rule` SET `title` = '获取计划排期单位价格信息'
        WHERE `name` = 'kuerp/goods_sales_schedule/getUnit' AND `title` = '获取销货排期单位价格信息';
    UPDATE `fa_auth_rule` SET `title` = '格式化计划排期价格'
        WHERE `name` = 'kuerp/goods_sales_schedule/formatInfo' AND `title` = '格式化销货排期价格';
    UPDATE `fa_auth_rule` SET `title` = '获取计划排期批次'
        WHERE `name` = 'kuerp/goods_sales_schedule/getBranch' AND `title` = '获取销货排期批次';
    UPDATE `fa_auth_rule` SET `title` = '获取计划排期时间'
        WHERE `name` = 'kuerp/goods_sales_schedule/getTime' AND `title` = '获取销货排期时间';
    UPDATE `fa_auth_rule` SET `title` = '获取排期商品数据'
        WHERE `name` = 'kuerp/goods_sales_schedule/getschedulegoods' AND `title` = '获取排期商品数据';
END$$
DELIMITER ;

CALL `kuerp_add_production_status`();
DROP PROCEDURE `kuerp_add_production_status`;
