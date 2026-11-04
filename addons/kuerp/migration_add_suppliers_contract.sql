--
-- 迁移：供应商合同附件
-- fa_kuerp_suppliers 新增 contract 字段（合同文件路径，单文件，选填）
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_suppliers_contract.sql
-- 可重复执行（已存在该列时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_suppliers_contract`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_suppliers_contract`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_suppliers'
           AND COLUMN_NAME = 'contract') = 0 THEN
        ALTER TABLE `fa_kuerp_suppliers`
            ADD COLUMN `contract` varchar(255) NOT NULL DEFAULT '' COMMENT '合同附件' AFTER `bank_number`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_suppliers_contract`();
DROP PROCEDURE `kuerp_add_suppliers_contract`;
