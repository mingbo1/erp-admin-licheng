-- ============================================================================
-- 对账差异金额 + 客户信用账期 + 应收账款分析快照（2026-10-28）
--
-- 背景：对账单二审驳回后，允许针对单张销货单/销货退货单登记「差异金额」（可正可负），
--       填写差异原因并上传原始附件；该差异按客户汇总后影响客户最终应收/付款金额。
--       财务需要每月月初 1 日 0 点生成一次「应收账款分析」快照（按客户、按供应商负责人）。
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_recon_diff_and_ar_analysis.sql
-- 可重复执行。
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. 对账差异记录（一张单据可多条，天然留痕）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_sales_recon_diff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0' COMMENT '对账单ID',
  `doc_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '1=销货单 2=销货退货单',
  `doc_id` int(11) NOT NULL DEFAULT '0' COMMENT '源单据ID',
  `doc_code` varchar(50) NOT NULL DEFAULT '' COMMENT '源单编号',
  `customers_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `diff_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '差异金额(正=增加应收 负=减少应收)',
  `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '差异原因备注',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '录入人',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recon_id` (`recon_id`),
  KEY `doc_code` (`doc_code`),
  KEY `customers_id` (`customers_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货对账差异记录';

-- ---------------------------------------------------------------------------
-- 2. 对账单：差异合计（派生冗余，便于列表/打印/收款判满）
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `kuerp_add_sales_recon_diff`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_sales_recon_diff`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_sales_recon'
           AND COLUMN_NAME = 'diff_money') = 0 THEN
        ALTER TABLE `fa_kuerp_sales_recon`
            ADD COLUMN `diff_money` double(15,4) NOT NULL DEFAULT '0.0000'
            COMMENT '对账差异合计(派生)' AFTER `total_amount`;
    END IF;

    -- 2.1 单据级快照也要有差异，详情页按单据显示
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_sales_recon_doc'
           AND COLUMN_NAME = 'diff_money') = 0 THEN
        ALTER TABLE `fa_kuerp_sales_recon_doc`
            ADD COLUMN `diff_money` double(15,4) NOT NULL DEFAULT '0.0000'
            COMMENT '该单据的差异合计(派生)' AFTER `total_amount`;
    END IF;

    -- -----------------------------------------------------------------------
    -- 3. 客户信用账期（逾期判断用：开票日 + 账期天数）
    -- -----------------------------------------------------------------------
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_customers'
           AND COLUMN_NAME = 'credit_days') = 0 THEN
        ALTER TABLE `fa_kuerp_customers`
            ADD COLUMN `credit_days` int(11) NOT NULL DEFAULT '0'
            COMMENT '信用账期(天)：0=未设置，逾期从开票日起算' AFTER `customerlevel_id`;
    END IF;

    -- -----------------------------------------------------------------------
    -- 3.1 源单据：差异合计（派生冗余）
    --     销货单/退货单各自的差异合计，供 收款/核销 的应收上限、应收账款分析直接读取，
    --     避免每次收款都回查差异明细表。
    -- -----------------------------------------------------------------------
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales'
           AND COLUMN_NAME = 'diff_money') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales`
            ADD COLUMN `diff_money` double(15,4) NOT NULL DEFAULT '0.0000'
            COMMENT '对账差异合计(对客户应收的影响,正=多收负=少收)' AFTER `customers_money`;
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_sales_exchange'
           AND COLUMN_NAME = 'diff_money') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_exchange`
            ADD COLUMN `diff_money` double(15,4) NOT NULL DEFAULT '0.0000'
            COMMENT '对账差异合计(对客户应收的影响,正=多收负=少收)' AFTER `customers_money`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_sales_recon_diff`();
DROP PROCEDURE `kuerp_add_sales_recon_diff`;

-- ---------------------------------------------------------------------------
-- 4. 应收账款分析月度快照（每月1日0点生成，一条客户一行）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_ar_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period` varchar(7) NOT NULL DEFAULT '' COMMENT '分析月份 YYYY-MM',
  `customers_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `customer_name` varchar(150) NOT NULL DEFAULT '' COMMENT '客户名称(快照)',
  `customer_code` varchar(50) NOT NULL DEFAULT '' COMMENT '客户编号(快照)',
  `sale_user` int(11) NOT NULL DEFAULT '0' COMMENT '销售负责人(职员ID)',
  `sale_user_name` varchar(100) NOT NULL DEFAULT '' COMMENT '销售负责人姓名(快照)',
  `credit_days` int(11) NOT NULL DEFAULT '0' COMMENT '信用账期天数(快照)',
  `begin_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '期初应收账款(上月末余额)',
  `sale_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本月含税销售总额(新增)',
  `collection_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本月实际回款总额',
  `return_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本月退货/红冲金额(正数)',
  `end_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本月账面应收余额=期初+销售-回款-退货',
  `overdue_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '其中：逾期应收账款',
  `collection_rate` double(8,4) NOT NULL DEFAULT '0.0000' COMMENT '本月销售回款率',
  `diff_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '对账差异金额(本月新增)',
  `diff_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '差异原因备注(汇总)',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `period_customer` (`period`,`customers_id`),
  KEY `period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='应收账款分析月度快照';

-- ---------------------------------------------------------------------------
-- 5. 原始单据关联类型：13=对账差异附件
-- ---------------------------------------------------------------------------
ALTER TABLE `fa_kuerp_enclosure_log`
  MODIFY COLUMN `type` enum('1','2','3','4','5','6','7','8','9','10','11','12','13') DEFAULT NULL
  COMMENT '单据类型:1=购货订单,2=采购单,3=购货退货单,4=销货订单,5=销货单,6=销货退货单,7=入库记录,8=采购对账单,9=发票,10=付款回单,11=销货对账单,12=销货发票,13=对账差异';

-- ---------------------------------------------------------------------------
-- 6. 菜单与权限规则（角色分配请到 角色管理 中勾选）
-- ---------------------------------------------------------------------------
-- 6.1 销货对账：差异金额的添加/删除
INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/diff','差异金额','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'9',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/diff');

-- 6.2 应收账款分析（资金组）
INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/ar_analysis','应收账款分析','fa fa-line-chart','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'84',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/capital') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/ar_analysis');

SET @ar_pid = (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/ar_analysis') t);

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/ar_analysis/index','查看','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@ar_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/ar_analysis/index');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/ar_analysis/rebuild','重新分析','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'1',@ar_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/ar_analysis/rebuild');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/ar_analysis/export','导出','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'2',@ar_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/ar_analysis/export');

-- ---------------------------------------------------------------------------
-- 7. 历史差异回填（当前无历史数据，仅为幂等占位；差异合计由回填脚本按记录汇总）
-- ---------------------------------------------------------------------------
UPDATE `fa_kuerp_sales_recon` r
SET r.`diff_money` = IFNULL((SELECT SUM(d.`diff_money`) FROM `fa_kuerp_sales_recon_diff` d WHERE d.`recon_id` = r.`id`), 0);
