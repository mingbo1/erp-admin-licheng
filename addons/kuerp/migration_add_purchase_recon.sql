-- ============================================================================
-- 采购对账 + 付款申请（2026-10-23）
--
-- 流程：采购勾选采购单生成对账单(标记已对账) → 财务审核(看采购合同/入库单/对账单)
--       → 采购上传发票并登记账期 → 采购发起付款申请 → 财务 → 审计 →（超阈值）领导
--       → 出纳打款(自动生成并审核付款单、核销应付、上传回单)
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_purchase_recon.sql
-- 可重复执行。
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. 采购对账单
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_recon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_code` varchar(50) NOT NULL DEFAULT '' COMMENT '对账单号',
  `supplyer_code` varchar(30) NOT NULL DEFAULT '' COMMENT '供应商编号',
  `supplyer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '供应商名称',
  `period_start` bigint(16) DEFAULT NULL COMMENT '对账期间开始',
  `period_end` bigint(16) DEFAULT NULL COMMENT '对账期间结束',
  `purchase_count` int(11) NOT NULL DEFAULT '0' COMMENT '采购单数',
  `total_number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '采购总数量',
  `total_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '对账金额(Σ采购单价税合计)',
  `status` enum('0','1','2','3') NOT NULL DEFAULT '0' COMMENT '0草稿 1待财务审核 2已审核 3已驳回',
  `invoice_status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '0未开票 1已开票',
  `pay_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '0未付款 1付款申请中 2已付款',
  `examine_id` int(11) DEFAULT NULL COMMENT '财务审核人',
  `examine_time` bigint(16) DEFAULT NULL COMMENT '财务审核时间',
  `reject_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '驳回原因',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `preparation_id` int(11) NOT NULL DEFAULT '0' COMMENT '制单人',
  `operation_id` int(11) NOT NULL DEFAULT '0' COMMENT '最后操作人',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recon_code` (`recon_code`),
  KEY `supplyer_code` (`supplyer_code`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购对账单';

-- ---------------------------------------------------------------------------
-- 2. 对账明细（勾选的采购单）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_recon_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `purchase_id` int(11) NOT NULL DEFAULT '0' COMMENT '采购单ID',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '采购单号',
  `purchase_time` bigint(16) DEFAULT NULL COMMENT '采购日期',
  `number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '采购数量',
  `real_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '采购单价税合计',
  `storage_status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '入库状态(冗余快照)',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recon_purchase` (`recon_id`,`purchase_id`),
  KEY `purchase_code` (`purchase_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购对账单明细';

-- ---------------------------------------------------------------------------
-- 3. 对账单操作日志
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_recon_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作',
  `note` varchar(500) NOT NULL DEFAULT '' COMMENT '说明',
  `admin_id` int(11) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recon_id` (`recon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购对账单操作日志';

-- ---------------------------------------------------------------------------
-- 4. 发票 + 账期记录
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_recon_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `enclosure_id` int(11) NOT NULL DEFAULT '0' COMMENT '发票文件(kuerp_enclosure.id)',
  `invoice_no` varchar(100) NOT NULL DEFAULT '' COMMENT '发票号',
  `invoice_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '开票金额',
  `invoice_date` bigint(16) DEFAULT NULL COMMENT '开票日期',
  `upload_date` bigint(16) DEFAULT NULL COMMENT '上传日期(账期起算日)',
  `term_days` int(11) NOT NULL DEFAULT '0' COMMENT '账期天数',
  `due_date` bigint(16) DEFAULT NULL COMMENT '付款到期日',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '上传人',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recon_id` (`recon_id`),
  KEY `due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购对账单发票与账期';

-- ---------------------------------------------------------------------------
-- 5. 付款申请单
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_payment_apply` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apply_code` varchar(50) NOT NULL DEFAULT '' COMMENT '付款申请单号',
  `supplyer_code` varchar(30) NOT NULL DEFAULT '' COMMENT '供应商编号',
  `supplyer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '供应商名称',
  `recon_count` int(11) NOT NULL DEFAULT '0' COMMENT '对账单数',
  `total_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '申请付款金额',
  `status` enum('0','1','2','3','4','5','6') NOT NULL DEFAULT '0' COMMENT '0草稿 1待财务 2待审计 3待领导 4待出纳 5已付款 6已驳回',
  `need_leader` tinyint(3) NOT NULL DEFAULT '0' COMMENT '是否需要领导审核(提交时按阈值固化)',
  `apply_admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '申请人',
  `apply_time` bigint(16) DEFAULT NULL COMMENT '申请时间',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '申请说明',
  `finance_admin_id` int(11) DEFAULT '0' COMMENT '财务审核人',
  `finance_time` bigint(16) DEFAULT NULL,
  `finance_remark` varchar(255) NOT NULL DEFAULT '',
  `audit_admin_id` int(11) DEFAULT '0' COMMENT '审计审核人',
  `audit_time` bigint(16) DEFAULT NULL,
  `audit_remark` varchar(255) NOT NULL DEFAULT '',
  `leader_admin_id` int(11) DEFAULT '0' COMMENT '领导审核人',
  `leader_time` bigint(16) DEFAULT NULL,
  `leader_remark` varchar(255) NOT NULL DEFAULT '',
  `cashier_admin_id` int(11) DEFAULT '0' COMMENT '出纳',
  `pay_time` bigint(16) DEFAULT NULL COMMENT '付款时间',
  `payment_id` int(11) NOT NULL DEFAULT '0' COMMENT '生成的付款单ID(kuerp_payment.id)',
  `pay_account_id` int(11) NOT NULL DEFAULT '0' COMMENT '付款账户(kuerp_accounts.id)',
  `pay_number` varchar(100) NOT NULL DEFAULT '' COMMENT '银行流水号',
  `pay_remark` varchar(255) NOT NULL DEFAULT '',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apply_code` (`apply_code`),
  KEY `supplyer_code` (`supplyer_code`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购付款申请单';

-- ---------------------------------------------------------------------------
-- 6. 付款申请明细（关联对账单）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_payment_apply_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apply_id` int(11) NOT NULL DEFAULT '0',
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `recon_code` varchar(50) NOT NULL DEFAULT '',
  `total_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '对账金额',
  `due_date` bigint(16) DEFAULT NULL COMMENT '账期到期日',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apply_recon` (`apply_id`,`recon_id`),
  KEY `recon_id` (`recon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购付款申请明细';

-- ---------------------------------------------------------------------------
-- 7. 付款申请审批日志
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_payment_apply_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apply_id` int(11) NOT NULL DEFAULT '0',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作',
  `note` varchar(500) NOT NULL DEFAULT '' COMMENT '说明',
  `admin_id` int(11) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `apply_id` (`apply_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购付款申请审批日志';

-- ---------------------------------------------------------------------------
-- 8. 采购单增加「已对账」标记（不覆盖原有 check_status 对单语义）
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `kuerp_add_purchase_recon_id`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_purchase_recon_id`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_purchase'
           AND COLUMN_NAME = 'recon_id') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_purchase`
            ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属采购对账单ID(0=未对账)' AFTER `check_status`,
            ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT '' COMMENT '所属采购对账单号' AFTER `recon_id`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_purchase_recon_id`();
DROP PROCEDURE `kuerp_add_purchase_recon_id`;

-- ---------------------------------------------------------------------------
-- 9. 原始单据关联类型扩展：8=对账单附件 9=发票 10=付款回单
-- ---------------------------------------------------------------------------
ALTER TABLE `fa_kuerp_enclosure_log`
  MODIFY COLUMN `type` enum('1','2','3','4','5','6','7','8','9','10') DEFAULT NULL
  COMMENT '单据类型:1=购货订单,2=采购单,3=购货退货单,4=销货订单,5=销货单,6=销货退货单,7=入库记录,8=采购对账单,9=发票,10=付款回单';

-- ---------------------------------------------------------------------------
-- 10. 单号前缀：30=采购对账单 DZD / 31=付款申请 FKSQ
-- ---------------------------------------------------------------------------
INSERT INTO `fa_kuerp_prefix` (`admin_id`,`type`,`name`,`prefix`,`year`,`month`,`day`,`hour`,`minute`,`second`,`start`,`strlen`,`example`,`dbname`,`field`)
SELECT '1','30','采购对账单','DZD','1','1','1','2','2','2','1','3','DZD20261023001','kuerp_purchase_recon','recon_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type`='30');

INSERT INTO `fa_kuerp_prefix` (`admin_id`,`type`,`name`,`prefix`,`year`,`month`,`day`,`hour`,`minute`,`second`,`start`,`strlen`,`example`,`dbname`,`field`)
SELECT '1','31','付款申请','FKSQ','1','1','1','2','2','2','1','3','FKSQ20261023001','kuerp_purchase_payment_apply','apply_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type`='31');

-- ---------------------------------------------------------------------------
-- 11. 设置项：账期提前提醒天数 / 领导审核金额阈值
-- ---------------------------------------------------------------------------
INSERT INTO `fa_kuerp_sets` (`admin_id`,`type`,`name`,`nickname`,`value`,`is_edit`,`notice`,`explain`,`is_checked`)
SELECT '1','1','purchase_payment_remind_days','采购付款提前提醒天数','7','0','账期到期前多少天在采购对账列表标黄提醒','到期日 - 提醒天数 <= 今天 且未付款时提醒','0'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_sets` WHERE `name`='purchase_payment_remind_days');

INSERT INTO `fa_kuerp_sets` (`admin_id`,`type`,`name`,`nickname`,`value`,`is_edit`,`notice`,`explain`,`is_checked`)
SELECT '1','1','purchase_payment_leader_money','付款申请领导审核金额','50000','0','付款申请金额达到该值时，审计审核后需领导审核','0 或留空表示不需要领导审核','0'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_sets` WHERE `name`='purchase_payment_leader_money');

-- ---------------------------------------------------------------------------
-- 12. 菜单与权限规则（角色分配请到 角色管理 中勾选）
--    采购对账：采购组(kuerp/buy/default) 下，weigh=78
--    付款申请：资金组(kuerp/capital) 下，weigh=98
-- ---------------------------------------------------------------------------
INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon','采购对账','fa fa-check-square-o','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'78',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/buy/default') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/index','查看','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/index');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/add','添加','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/add');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/edit','编辑','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/edit');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/del','删除','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/del');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/detail','详情','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/detail');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/submit','提交审核','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/submit');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/audit','财务审核','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/audit');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/invoice','上传发票/账期','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/invoice');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply','付款申请','fa fa-credit-card','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'98',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/capital') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply');

SET @apply_pid = (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply') t);

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/index','查看','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/index');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/add','添加','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/add');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/edit','编辑','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/edit');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/del','删除','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/del');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/detail','详情','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/detail');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/submit','提交审核','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/submit');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/financeexamine','财务审核','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/financeexamine');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/auditexamine','审计审核','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/auditexamine');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/leaderexamine','领导审核','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/leaderexamine');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_payment_apply/pay','确认打款','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@apply_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/pay');
