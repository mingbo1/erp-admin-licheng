-- ============================================================================
-- 采购对账对齐销货对账规则（2026-10-27）
--   1) 购货退货单冲减：goods_exchange 增加 recon_id/recon_code/recon_status
--   2) 单据锁定状态：goods_purchase 增加 recon_status
--   3) 对账单状态机：0草稿 1待财务审核 2待二审 3已对账 4已驳回
--   4) 多张发票/部分开票：invoice_status 0/1/2 + invoice_amount
--   5) 对账明细支持退货单：doc_type 1采购单 2退货单
--   6) 权限：新增 secondaudit(二审)、revoke(撤销对账)
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_align_purchase_recon_sales_rules.sql
-- 可重复执行。
-- ============================================================================

DROP PROCEDURE IF EXISTS `kuerp_align_purchase_recon`;
DELIMITER $$
CREATE PROCEDURE `kuerp_align_purchase_recon`()
BEGIN
    -- 采购单锁状态
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_purchase'
           AND COLUMN_NAME = 'recon_status') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_purchase`
            ADD COLUMN `recon_status` enum('0','1','2') NOT NULL DEFAULT '0'
            COMMENT '对账锁状态:0未对账/可编辑,1已锁定(一审通过),2已对账(二审通过)' AFTER `recon_code`;
    END IF;

    -- 购货退货单：对账归属与锁状态
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_exchange'
           AND COLUMN_NAME = 'recon_id') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_exchange`
            ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属采购对账单ID(0=未对账)' AFTER `check_status`,
            ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT '' COMMENT '所属采购对账单号' AFTER `recon_id`,
            ADD COLUMN `recon_status` enum('0','1','2') NOT NULL DEFAULT '0'
            COMMENT '对账锁状态:0未对账/可编辑,1已锁定(一审通过),2已对账(二审通过)' AFTER `recon_code`;
    END IF;

    -- 对账单主表：二审、累计开票、退货单数
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_purchase_recon'
           AND COLUMN_NAME = 'second_examine_id') = 0 THEN
        ALTER TABLE `fa_kuerp_purchase_recon`
            ADD COLUMN `second_examine_id` int(11) DEFAULT '0' COMMENT '二审人' AFTER `examine_time`,
            ADD COLUMN `second_examine_time` bigint(16) DEFAULT NULL COMMENT '二审时间' AFTER `second_examine_id`,
            ADD COLUMN `invoice_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '累计已开票金额' AFTER `invoice_status`,
            ADD COLUMN `return_count` int(11) NOT NULL DEFAULT '0' COMMENT '退货单数' AFTER `purchase_count`;
    END IF;

    -- 对账明细：单据类型
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_purchase_recon_info'
           AND COLUMN_NAME = 'doc_type') = 0 THEN
        ALTER TABLE `fa_kuerp_purchase_recon_info`
            ADD COLUMN `doc_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '单据类型:1采购单,2购货退货单' AFTER `recon_id`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_align_purchase_recon`();
DROP PROCEDURE `kuerp_align_purchase_recon`;

-- 状态枚举扩展（先扩展，再映射旧值）
ALTER TABLE `fa_kuerp_purchase_recon`
  MODIFY COLUMN `status` enum('0','1','2','3','4') NOT NULL DEFAULT '0'
  COMMENT '0草稿/待对账 1待财务审核 2待二审 3已对账 4已驳回';

-- 旧值映射：旧2已审核→新3已对账；旧3已驳回→新4已驳回
UPDATE `fa_kuerp_purchase_recon`
   SET `status` = CASE `status` WHEN '2' THEN '3' WHEN '3' THEN '4' ELSE `status` END
 WHERE `status` IN ('2','3');

-- 开票状态三态
ALTER TABLE `fa_kuerp_purchase_recon`
  MODIFY COLUMN `invoice_status` enum('0','1','2') NOT NULL DEFAULT '0'
  COMMENT '0未开票 1部分开票 2已开票';

-- 以发票表重算累计开票金额与状态
UPDATE `fa_kuerp_purchase_recon` r
   SET r.`invoice_amount` = (SELECT COALESCE(SUM(i.`invoice_amount`),0) FROM `fa_kuerp_purchase_recon_invoice` i WHERE i.`recon_id` = r.`id`),
       r.`invoice_status` = CASE
           WHEN (SELECT COUNT(*) FROM `fa_kuerp_purchase_recon_invoice` i WHERE i.`recon_id` = r.`id`) = 0 THEN '0'
           WHEN (SELECT COALESCE(SUM(i.`invoice_amount`),0) FROM `fa_kuerp_purchase_recon_invoice` i WHERE i.`recon_id` = r.`id`) >= r.`total_money` - 0.01 THEN '2'
           ELSE '1'
       END;

-- 明细唯一键改为 (recon_id, doc_type, purchase_id)
DROP PROCEDURE IF EXISTS `kuerp_purchase_recon_info_index`;
DELIMITER $$
CREATE PROCEDURE `kuerp_purchase_recon_info_index`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_purchase_recon_info'
           AND INDEX_NAME = 'recon_purchase') > 0 THEN
        ALTER TABLE `fa_kuerp_purchase_recon_info` DROP INDEX `recon_purchase`;
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_purchase_recon_info'
           AND INDEX_NAME = 'recon_doc') = 0 THEN
        ALTER TABLE `fa_kuerp_purchase_recon_info`
            ADD UNIQUE KEY `recon_doc` (`recon_id`,`doc_type`,`purchase_id`);
    END IF;
END$$
DELIMITER ;
CALL `kuerp_purchase_recon_info_index`();
DROP PROCEDURE `kuerp_purchase_recon_info_index`;

-- 既有对账单回写单据锁状态：新1/2(待财务/待二审) → 1? 待财务尚未锁定，应为0；新2待二审→1；新3已对账→2
UPDATE `fa_kuerp_goods_purchase` p
  JOIN `fa_kuerp_purchase_recon` r ON r.`id` = p.`recon_id`
   SET p.`recon_status` = CASE r.`status` WHEN '2' THEN '1' WHEN '3' THEN '2' ELSE '0' END;
UPDATE `fa_kuerp_goods_exchange` e
  JOIN `fa_kuerp_purchase_recon` r ON r.`id` = e.`recon_id`
   SET e.`recon_status` = CASE r.`status` WHEN '2' THEN '1' WHEN '3' THEN '2' ELSE '0' END;

-- 权限规则：财务一审改名 + 二审/撤销
UPDATE `fa_auth_rule` SET `title`='财务一审' WHERE `name`='kuerp/purchase_recon/audit';

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/secondaudit','二审通过/驳回','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/secondaudit');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/revoke','撤销对账','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/revoke');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/export','导出Excel','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/export');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/printpage','打印','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/printpage');
