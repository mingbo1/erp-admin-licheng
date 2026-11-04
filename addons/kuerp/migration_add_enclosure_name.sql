--
-- 迁移：原始单据（采购 kuerp/enclosure、销售 kuerp/enclosuresale）支持任意文件 + 文件名称
-- fa_kuerp_enclosure 新增「文件名称」字段；image 由「仅图片」改为「任意文件路径」（图片/PDF/Excel/压缩包等）。
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_enclosure_name.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_enclosure_name`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_enclosure_name`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_enclosure'
           AND COLUMN_NAME = 'name') = 0 THEN
        ALTER TABLE `fa_kuerp_enclosure`
            ADD COLUMN `name` varchar(255) NOT NULL DEFAULT '' COMMENT '文件名称' AFTER `image`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_enclosure_name`();
DROP PROCEDURE `kuerp_add_enclosure_name`;

-- image 列语义变更：不再只是图片，可能是 pdf/xlsx/zip 等任意文件路径
ALTER TABLE `fa_kuerp_enclosure`
    MODIFY COLUMN `image` varchar(255) NOT NULL DEFAULT '' COMMENT '原始单据文件路径';
