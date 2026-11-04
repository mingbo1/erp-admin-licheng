-- ============================================================================
-- 销货对账：账期起算 + 收款到期日 + 到期提醒（2026-10-29）
--
-- 需求：
--   1) 录客户时设账期天数（fa_kuerp_customers.credit_days，已存在，见
--      migration_add_recon_diff_and_ar_analysis.sql）
--   2) 对账单「开票」时开始计算账期：开票时把客户档案的账期天数快照到发票行
--      （term_days），并算出收款到期日（due_date = 开票日 + 账期天数）
--   3) 到期日之前 N 天开始在销货对账列表提醒（N 由设置项
--      sales_collection_remind_days 控制，默认 7）
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_sales_recon_due_date.sql
-- 可重复执行。
-- ============================================================================

DROP PROCEDURE IF EXISTS `kuerp_add_sales_recon_due_date`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_sales_recon_due_date`()
BEGIN
    -- -----------------------------------------------------------------------
    -- 1. 发票行：账期天数快照 + 收款到期日
    --    账期在开票这一刻固化，之后客户档案改账期不影响已开票的对账单
    -- -----------------------------------------------------------------------
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_sales_recon_invoice'
           AND COLUMN_NAME = 'term_days') = 0 THEN
        ALTER TABLE `fa_kuerp_sales_recon_invoice`
            ADD COLUMN `term_days` int(11) NOT NULL DEFAULT '0'
            COMMENT '账期天数(开票时快照客户信用账期)' AFTER `invoice_date`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_sales_recon_invoice'
           AND COLUMN_NAME = 'due_date') = 0 THEN
        ALTER TABLE `fa_kuerp_sales_recon_invoice`
            ADD COLUMN `due_date` bigint(16) DEFAULT NULL
            COMMENT '收款到期日=开票日+账期天数' AFTER `term_days`,
            ADD KEY `due_date` (`due_date`);
    END IF;

    -- -----------------------------------------------------------------------
    -- 2. 对账单头：收款到期日（派生 = 所含发票里最早的到期日）
    --    冗余到主表，列表提醒/筛选/排序才能不 JOIN 子查询
    -- -----------------------------------------------------------------------
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_sales_recon'
           AND COLUMN_NAME = 'due_date') = 0 THEN
        ALTER TABLE `fa_kuerp_sales_recon`
            ADD COLUMN `due_date` bigint(16) DEFAULT NULL
            COMMENT '收款到期日(所含发票最早到期日,派生)' AFTER `pay_money`,
            ADD KEY `due_date` (`due_date`);
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_sales_recon_due_date`();
DROP PROCEDURE IF EXISTS `kuerp_add_sales_recon_due_date`;

-- ---------------------------------------------------------------------------
-- 3. 设置项：销货收款提前提醒天数（默认 7）
--    与采购侧的 purchase_payment_remind_days 分开，避免两边互相干扰
-- ---------------------------------------------------------------------------
INSERT INTO `fa_kuerp_sets` (`type`,`is_edit`,`name`,`nickname`,`value`,`admin_id`,`notice`,`explain`,`is_checked`)
SELECT '1','0','sales_collection_remind_days','销货收款提前提醒天数','7','0',
       '账期到期前多少天在销货对账列表标黄提醒','到期日 - 提醒天数 <= 今天 且未收款时提醒','0'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_sets` WHERE `name`='sales_collection_remind_days');

-- ---------------------------------------------------------------------------
-- 4. 历史数据回填：已开票、且客户已设账期的，按开票日 + 客户账期补算
--    （客户账期全为 0 时不产生任何变更，等价于「不判断逾期」）
-- ---------------------------------------------------------------------------
UPDATE `fa_kuerp_sales_recon_invoice` i
JOIN `fa_kuerp_sales_recon` r ON r.`id` = i.`recon_id`
JOIN `fa_kuerp_customers` c ON c.`id` = r.`customers_id`
SET i.`term_days` = c.`credit_days`,
    i.`due_date`  = IFNULL(i.`invoice_date`, i.`upload_date`) + c.`credit_days` * 86400
WHERE i.`term_days` = 0
  AND i.`due_date` IS NULL
  AND c.`credit_days` > 0
  AND IFNULL(i.`invoice_date`, i.`upload_date`) > 0;

UPDATE `fa_kuerp_sales_recon` r
SET r.`due_date` = (
    SELECT MIN(i.`due_date`) FROM `fa_kuerp_sales_recon_invoice` i
    WHERE i.`recon_id` = r.`id` AND i.`due_date` > 0
)
WHERE r.`due_date` IS NULL
  AND EXISTS (SELECT 1 FROM `fa_kuerp_sales_recon_invoice` i2
              WHERE i2.`recon_id` = r.`id` AND i2.`due_date` > 0);
