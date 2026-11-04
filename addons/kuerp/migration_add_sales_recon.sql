-- ============================================================================
-- 销货对账（客户对账单）（2026-10-27）
--
-- 流程：销售/财务在工作台筛选销货单+销货退货单 → 生成对账单（按客户拆分）
--       → 财务一审 → 主管/领导二审 → 上传发票（可多张）→ 客户付款自动派生
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_sales_recon.sql
-- 可重复执行。
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. 销货对账单
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_sales_recon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_code` varchar(50) NOT NULL DEFAULT '' COMMENT '对账单号',
  `customers_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `customer_name` varchar(150) NOT NULL DEFAULT '' COMMENT '客户名称(快照)',
  `period_start` bigint(16) DEFAULT NULL COMMENT '对账期间开始',
  `period_end` bigint(16) DEFAULT NULL COMMENT '对账期间结束',
  `sales_count` int(11) NOT NULL DEFAULT '0' COMMENT '销货单数',
  `return_count` int(11) NOT NULL DEFAULT '0' COMMENT '退货单数',
  `total_number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '数量净额',
  `total_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '销货合计金额净额',
  `total_customers_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '客户承担费用净额',
  `total_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '总金额=销货合计+客户费用',
  `recon_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '0待对账 1待二审 2已对账',
  `examine_id` int(11) DEFAULT NULL COMMENT '一审人',
  `examine_time` bigint(16) DEFAULT NULL COMMENT '一审时间',
  `second_examine_id` int(11) DEFAULT NULL COMMENT '二审人',
  `second_examine_time` bigint(16) DEFAULT NULL COMMENT '二审时间',
  `reject_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '二审驳回原因',
  `invoice_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '0未开票 1部分开票 2已开票',
  `invoice_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '累计已开票金额',
  `pay_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '0未收 1部分收款 2已收款',
  `pay_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '已收金额净额(派生冗余)',
  `preparation_id` int(11) NOT NULL DEFAULT '0' COMMENT '制单人',
  `operation_id` int(11) NOT NULL DEFAULT '0' COMMENT '最后操作人',
  `print_num` int(11) NOT NULL DEFAULT '0' COMMENT '打印次数',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recon_code` (`recon_code`),
  KEY `customers_id` (`customers_id`),
  KEY `recon_status` (`recon_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货对账单';

-- ---------------------------------------------------------------------------
-- 2. 单据级快照（销货单 / 销货退货单）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_sales_recon_doc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `doc_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '1销货单 2销货退货单',
  `doc_id` int(11) NOT NULL DEFAULT '0',
  `doc_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `doc_time` bigint(16) DEFAULT NULL COMMENT '销售日期',
  `customers_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '客户承担费用(退货取负)',
  `real_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '销货合计(退货取负)',
  `total_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '总金额',
  `express_no` varchar(100) NOT NULL DEFAULT '' COMMENT '物流单号',
  `out_status` tinyint(3) DEFAULT NULL COMMENT '0未出库 1部分出库 2已出库(退货为NULL)',
  `pay_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '已收(销货)/已退(退货)',
  `sort` int(11) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recon_doc` (`recon_id`,`doc_type`,`doc_id`),
  KEY `recon_id` (`recon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货对账单据快照';

-- ---------------------------------------------------------------------------
-- 3. 商品行快照
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_sales_recon_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `recon_doc_id` int(11) NOT NULL DEFAULT '0',
  `doc_type` enum('1','2') NOT NULL DEFAULT '1',
  `doc_id` int(11) NOT NULL DEFAULT '0',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `goods_code` varchar(30) NOT NULL DEFAULT '',
  `goods_name` varchar(150) NOT NULL DEFAULT '',
  `specification` varchar(100) NOT NULL DEFAULT '' COMMENT '型号',
  `vendor_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规格2/厂商',
  `packaging` varchar(100) NOT NULL DEFAULT '' COMMENT '规格3/封装',
  `goods_attr` int(11) NOT NULL DEFAULT '0',
  `attr_name` varchar(100) NOT NULL DEFAULT '',
  `unit_id` int(11) NOT NULL DEFAULT '0',
  `unit_name` varchar(50) NOT NULL DEFAULT '',
  `number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '数量(退货为负)',
  `basic_nmber` double(15,4) NOT NULL DEFAULT '0.0000',
  `unit_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '未税单价',
  `tax_included` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '含税单价',
  `tax_rate` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '税点%',
  `tax_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '税额',
  `all_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '不含税金额',
  `money_tax_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '价税合计',
  `line_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '行销货合计(已摊优惠,退货为负)',
  `purchase_order_id` varchar(50) NOT NULL DEFAULT '' COMMENT '关联销售订单号',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recon_id` (`recon_id`),
  KEY `recon_doc_id` (`recon_doc_id`),
  KEY `goods_code` (`goods_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货对账商品明细快照';

-- ---------------------------------------------------------------------------
-- 4. 发票（可多张）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_sales_recon_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `enclosure_id` int(11) NOT NULL DEFAULT '0' COMMENT '发票文件(enclosure)',
  `invoice_no` varchar(100) NOT NULL DEFAULT '' COMMENT '发票号',
  `invoice_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '开票金额',
  `invoice_date` bigint(16) DEFAULT NULL COMMENT '开票日期',
  `upload_date` bigint(16) DEFAULT NULL COMMENT '上传日',
  `admin_id` int(11) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recon_id` (`recon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货对账单发票';

-- ---------------------------------------------------------------------------
-- 5. 操作日志
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fa_kuerp_sales_recon_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作',
  `note` varchar(500) NOT NULL DEFAULT '' COMMENT '说明',
  `admin_id` int(11) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recon_id` (`recon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货对账单操作日志';

-- ---------------------------------------------------------------------------
-- 6. 既有表：销货单 / 销货退货单 增加对账标记
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `kuerp_add_sales_recon_id`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_sales_recon_id`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='recon_id') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales`
            ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属销货对账单ID(0=未对账)' AFTER `check_status`,
            ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT '' COMMENT '所属销货对账单号' AFTER `recon_id`,
            ADD COLUMN `recon_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '对账状态:0未对账 1待二审 2已对账' AFTER `recon_code`;
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_exchange' AND COLUMN_NAME='recon_id') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_sales_exchange`
            ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属销货对账单ID(0=未对账)' AFTER `check_status`,
            ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT '' COMMENT '所属销货对账单号' AFTER `recon_id`,
            ADD COLUMN `recon_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '对账状态:0未对账 1待二审 2已对账' AFTER `recon_code`;
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_sales_invoicing' AND COLUMN_NAME='recon_id') = 0 THEN
        ALTER TABLE `fa_kuerp_sales_invoicing`
            ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT '0' COMMENT '来源销货对账单ID' AFTER `invoice_status`;
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_sales_recon' AND COLUMN_NAME='print_num') = 0 THEN
        ALTER TABLE `fa_kuerp_sales_recon`
            ADD COLUMN `print_num` int(11) NOT NULL DEFAULT '0' COMMENT '打印次数' AFTER `operation_id`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_sales_recon_id`();
DROP PROCEDURE `kuerp_add_sales_recon_id`;

-- ---------------------------------------------------------------------------
-- 7. 原始单据关联类型扩展：11=销货对账单附件 12=销货发票
-- ---------------------------------------------------------------------------
ALTER TABLE `fa_kuerp_enclosure_log`
  MODIFY COLUMN `type` enum('1','2','3','4','5','6','7','8','9','10','11','12') DEFAULT NULL
  COMMENT '单据类型:1=购货订单,2=采购单,3=购货退货单,4=销货订单,5=销货单,6=销货退货单,7=入库记录,8=采购对账单,9=发票,10=付款回单,11=销货对账单,12=销货发票';

-- ---------------------------------------------------------------------------
-- 8. 单号前缀：32=客户对账单 KHDZ
-- ---------------------------------------------------------------------------
INSERT INTO `fa_kuerp_prefix` (`admin_id`,`type`,`name`,`prefix`,`year`,`month`,`day`,`hour`,`minute`,`second`,`start`,`strlen`,`example`,`dbname`,`field`)
SELECT '1','32','客户对账单','KHDZ','1','1','1','2','2','2','1','3','KHDZ20261027001','kuerp_sales_recon','recon_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type`='32');

-- ---------------------------------------------------------------------------
-- 9. 菜单与权限规则（角色分配请到 角色管理 中勾选）
-- ---------------------------------------------------------------------------
INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon','销货对账','fa fa-file-text-o','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'78',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/sales') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon');

SET @recon_pid = (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon') t);

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/index','查看','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/index');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/items','销货对账明细','fa fa-list','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'77',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/sales') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/items');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/add','生成对账单','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'1',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/add');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/edit','编辑','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'2',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/edit');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/del','删除','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'3',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/del');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/detail','详情','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'4',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/detail');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/printpage','打印','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'5',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/printpage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/export','导出','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'6',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/export');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/confirm','一审','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'7',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/confirm');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/secondconfirm','二审通过','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'8',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/secondconfirm');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/reject','二审驳回','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'9',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/reject');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/cancelconfirm','撤销对账','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'10',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/cancelconfirm');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/invoice','开票','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'11',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/invoice');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/delinvoice','删除发票','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'12',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/delinvoice');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/sync','刷新明细','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'13',@recon_pid
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/sync');
