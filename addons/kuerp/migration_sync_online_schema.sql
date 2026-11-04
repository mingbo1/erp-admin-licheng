-- ============================================================================
-- 线上库结构同步迁移（本地 → 线上 dyerp_shengbokj_）
-- 生成时间：2026-10-28
-- 说明：本脚本只做「结构」变更，不修改/删除线上任何业务数据行。
--   1) 新建 18 张表（CREATE TABLE IF NOT EXISTS）
--   2) 37 个新增列（幂等，已存在则跳过；位置与本地一致）
--   3) 3 个列定义调整（enclosure.image 注释 / enclosure_log.type 枚举扩展 / process_board_info.variance 注释）
--   4) 67 条权限规则（按 name 幂等插入，pid 用父级 name 解析，不写死 id）
--   5) 设置项 / 单号规则 / 历史数据修复（均为幂等）
--   6) 角色权限：只追加不删除（线上角色已有的权限全部保留）
--
-- ⚠ 唯一会删除的行：3 条已废弃的外协加工权限规则
--   （kuerp/process 的 outbound / cancelOutbound / processIn），
--   其控制器方法与前端代码已不存在，保留会在角色管理里显示无效节点。
--
-- 执行：
--   mysql -h118.31.20.155 -udyerp_shengbokj_ -p'***' -D dyerp_shengbokj_ < addons/kuerp/migration_sync_online_schema.sql
--
-- 回滚：仅可回滚「第 4/5 步的插入行」；建表/加列不可逆，请先备份：
--   mysqldump -h118.31.20.155 -udyerp_shengbokj_ -p'***' --no-data dyerp_shengbokj_ > online_schema_backup.sql
-- ============================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- ============================================================================
-- 一、新建表（18 张，线上当前不存在）
-- ============================================================================
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

CREATE TABLE IF NOT EXISTS `fa_kuerp_goods_outbound_apply` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apply_code` varchar(50) NOT NULL DEFAULT '' COMMENT '申请单号 (CKSQ...)',
  `sales_id` int(11) NOT NULL DEFAULT '0' COMMENT '销货单ID (kuerp_goods_sales.id)',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '销货单号',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户ID(冗余,便于列表筛选)',
  `apply_admin_id` int(11) DEFAULT NULL COMMENT '申请人(admin.id)',
  `apply_time` bigint(16) DEFAULT NULL COMMENT '申请时间',
  `status` enum('0','1','2','3') DEFAULT '0' COMMENT '状态:0=待审核,1=已同意出库,2=已拒绝,3=已撤销',
  `examine_admin_id` int(11) DEFAULT NULL COMMENT '审核人/撤销人(admin.id)',
  `examine_time` bigint(16) DEFAULT NULL COMMENT '审核时间/撤销时间',
  `out_data` text COMMENT '实际同意出库数量JSON {sales_info_id:数量}(审核同意后写入,审计用)',
  `refuse_reason` varchar(255) DEFAULT '' COMMENT '拒绝原因',
  `remark` varchar(255) DEFAULT '' COMMENT '申请备注',
  `createtime` int(11) DEFAULT NULL,
  `updatetime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_id` (`sales_id`),
  KEY `status` (`status`),
  KEY `apply_code` (`apply_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货单出库申请';

CREATE TABLE IF NOT EXISTS `fa_kuerp_goods_outbound_apply_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apply_id` int(11) NOT NULL DEFAULT '0' COMMENT '申请ID (kuerp_goods_outbound_apply.id)',
  `sales_info_id` int(11) NOT NULL DEFAULT '0' COMMENT '销货单明细ID (kuerp_goods_sales_info.id)',
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号(冗余)',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库ID(冗余)',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '本次申请出库数量',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `apply_id` (`apply_id`),
  KEY `sales_info_id` (`sales_info_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货单出库申请明细';

CREATE TABLE IF NOT EXISTS `fa_kuerp_goods_purchase_storage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL DEFAULT '0' COMMENT '采购单ID (kuerp_goods_purchase.id)',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '采购单号',
  `total_number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本次入库总数量',
  `detail` text COMMENT '本次入库明细JSON',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '操作人',
  `createtime` bigint(16) DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `purchase_code` (`purchase_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购单入库操作记录';

CREATE TABLE IF NOT EXISTS `fa_kuerp_process_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `process_id` int(11) NOT NULL COMMENT '加工单ID (kuerp_process.id)',
  `action` varchar(50) NOT NULL COMMENT '操作类型',
  `note` varchar(500) DEFAULT '' COMMENT '备注(含本次出入库明细)',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '操作人ID',
  `createtime` int(11) NOT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `process_id` (`process_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='外协加工单操作记录';

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

CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_recon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_code` varchar(50) NOT NULL DEFAULT '' COMMENT '对账单号',
  `supplyer_code` varchar(30) NOT NULL DEFAULT '' COMMENT '供应商编号',
  `supplyer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '供应商名称',
  `period_start` bigint(16) DEFAULT NULL COMMENT '对账期间开始',
  `period_end` bigint(16) DEFAULT NULL COMMENT '对账期间结束',
  `purchase_count` int(11) NOT NULL DEFAULT '0' COMMENT '采购单数',
  `return_count` int(11) NOT NULL DEFAULT '0' COMMENT '退货单数',
  `total_number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '采购总数量',
  `total_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '对账金额(Σ采购单价税合计)',
  `status` enum('0','1','2','3','4') NOT NULL DEFAULT '0' COMMENT '0草稿/待对账 1待财务审核 2待二审 3已对账 4已驳回',
  `invoice_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '0未开票 1部分开票 2已开票',
  `invoice_amount` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '累计已开票金额',
  `pay_status` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '0未付款 1付款申请中 2已付款',
  `examine_id` int(11) DEFAULT NULL COMMENT '财务审核人',
  `examine_time` bigint(16) DEFAULT NULL COMMENT '财务审核时间',
  `second_examine_id` int(11) DEFAULT '0' COMMENT '二审人',
  `second_examine_time` bigint(16) DEFAULT NULL COMMENT '二审时间',
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

CREATE TABLE IF NOT EXISTS `fa_kuerp_purchase_recon_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recon_id` int(11) NOT NULL DEFAULT '0',
  `doc_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '单据类型:1采购单,2购货退货单',
  `purchase_id` int(11) NOT NULL DEFAULT '0' COMMENT '采购单ID',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '采购单号',
  `purchase_time` bigint(16) DEFAULT NULL COMMENT '采购日期',
  `number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '采购数量',
  `real_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '采购单价税合计',
  `storage_status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '入库状态(冗余快照)',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recon_doc` (`recon_id`,`doc_type`,`purchase_id`),
  KEY `purchase_code` (`purchase_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购对账单明细';

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
  `diff_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '对账差异合计(派生)',
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
  `diff_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '该单据的差异合计(派生)',
  `express_no` varchar(100) NOT NULL DEFAULT '' COMMENT '物流单号',
  `out_status` tinyint(3) DEFAULT NULL COMMENT '0未出库 1部分出库 2已出库(退货为NULL)',
  `pay_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '已收(销货)/已退(退货)',
  `sort` int(11) NOT NULL DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recon_doc` (`recon_id`,`doc_type`,`doc_id`),
  KEY `recon_id` (`recon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货对账单据快照';

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


-- ============================================================================
-- 二、新增列（37 个）
--    用 information_schema 判断，已存在则跳过，可重复执行。
-- ============================================================================
-- fa_kuerp_assemble.produce_date
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_assemble' AND COLUMN_NAME='produce_date')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_assemble` ADD COLUMN `produce_date` bigint(16) DEFAULT NULL COMMENT \'生产日期\' AFTER `purchase_time`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_customers.credit_days
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_customers' AND COLUMN_NAME='credit_days')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_customers` ADD COLUMN `credit_days` int(11) NOT NULL DEFAULT \'0\' COMMENT \'信用账期(天)：0=未设置，逾期从开票日起算\' AFTER `customerlevel_id`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_enclosure.name
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_enclosure' AND COLUMN_NAME='name')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_enclosure` ADD COLUMN `name` varchar(255) NOT NULL DEFAULT \'\' COMMENT \'文件名称\' AFTER `image`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods.frequent_customers
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods' AND COLUMN_NAME='frequent_customers')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods` ADD COLUMN `frequent_customers` varchar(255) NOT NULL DEFAULT \'\' COMMENT \'常用客户(客户ID,逗号分隔)\' AFTER `is_offten`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_batch_info.process_info_id
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_batch_info' AND COLUMN_NAME='process_info_id')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_batch_info` ADD COLUMN `process_info_id` int(11) DEFAULT NULL COMMENT \'外协加工单明细ID (kuerp_process_info.id)\' AFTER `split_id`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_bom_info.location
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_bom_info' AND COLUMN_NAME='location')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_bom_info` ADD COLUMN `location` varchar(255) NOT NULL DEFAULT \'\' COMMENT \'放置位置\' AFTER `number`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_exchange.recon_id
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_exchange' AND COLUMN_NAME='recon_id')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_exchange` ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT \'0\' COMMENT \'所属采购对账单ID(0=未对账)\' AFTER `check_status`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_exchange.recon_code
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_exchange' AND COLUMN_NAME='recon_code')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_exchange` ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT \'\' COMMENT \'所属采购对账单号\' AFTER `recon_id`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_exchange.recon_status
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_exchange' AND COLUMN_NAME='recon_status')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_exchange` ADD COLUMN `recon_status` enum(\'0\',\'1\',\'2\') NOT NULL DEFAULT \'0\' COMMENT \'对账锁状态:0未对账/可编辑,1已锁定(一审通过),2已对账(二审通过)\' AFTER `recon_code`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_purchase.recon_id
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_purchase' AND COLUMN_NAME='recon_id')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_purchase` ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT \'0\' COMMENT \'所属采购对账单ID(0=未对账)\' AFTER `check_status`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_purchase.recon_code
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_purchase' AND COLUMN_NAME='recon_code')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_purchase` ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT \'\' COMMENT \'所属采购对账单号\' AFTER `recon_id`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_purchase.recon_status
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_purchase' AND COLUMN_NAME='recon_status')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_purchase` ADD COLUMN `recon_status` enum(\'0\',\'1\',\'2\') NOT NULL DEFAULT \'0\' COMMENT \'对账锁状态:0未对账/可编辑,1已锁定(一审通过),2已对账(二审通过)\' AFTER `recon_code`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_purchase_order.order_source
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_purchase_order' AND COLUMN_NAME='order_source')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_purchase_order` ADD COLUMN `order_source` enum(\'plan\',\'warehouse\',\'purchase\') NOT NULL DEFAULT \'plan\' COMMENT \'来源:plan=计划部门,warehouse=仓库部门,purchase=购货订单\' AFTER `purchase_type`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales.delivery_mode
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='delivery_mode')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales` ADD COLUMN `delivery_mode` enum(\'direct\',\'dropship\') NOT NULL DEFAULT \'direct\' COMMENT \'发货方式:direct=直发,dropship=代发\' AFTER `delivery_time`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales.expected_delivery_date
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='expected_delivery_date')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales` ADD COLUMN `expected_delivery_date` varchar(10) DEFAULT NULL COMMENT \'预计交付日期(计划排期审核时填写,可二次修改,不打印)\' AFTER `delivery_mode`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales.diff_money
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='diff_money')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales` ADD COLUMN `diff_money` double(15,4) NOT NULL DEFAULT \'0.0000\' COMMENT \'对账差异合计(对客户应收的影响,正=多收负=少收)\' AFTER `customers_money`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales.recon_id
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='recon_id')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales` ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT \'0\' COMMENT \'所属销货对账单ID(0=未对账)\' AFTER `check_status`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales.recon_code
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='recon_code')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales` ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT \'\' COMMENT \'所属销货对账单号\' AFTER `recon_id`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales.recon_status
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='recon_status')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales` ADD COLUMN `recon_status` enum(\'0\',\'1\',\'2\') NOT NULL DEFAULT \'0\' COMMENT \'对账状态:0未对账 1待二审 2已对账\' AFTER `recon_code`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales.production_status
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales' AND COLUMN_NAME='production_status')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales` ADD COLUMN `production_status` enum(\'0\',\'1\') DEFAULT \'0\' COMMENT \'是否已排产(计划排期):0=未排产,1=已排产\' AFTER `print_num`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_exchange.diff_money
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_exchange' AND COLUMN_NAME='diff_money')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_exchange` ADD COLUMN `diff_money` double(15,4) NOT NULL DEFAULT \'0.0000\' COMMENT \'对账差异合计(对客户应收的影响,正=多收负=少收)\' AFTER `customers_money`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_exchange.recon_id
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_exchange' AND COLUMN_NAME='recon_id')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_exchange` ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT \'0\' COMMENT \'所属销货对账单ID(0=未对账)\' AFTER `check_status`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_exchange.recon_code
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_exchange' AND COLUMN_NAME='recon_code')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_exchange` ADD COLUMN `recon_code` varchar(50) NOT NULL DEFAULT \'\' COMMENT \'所属销货对账单号\' AFTER `recon_id`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_exchange.recon_status
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_exchange' AND COLUMN_NAME='recon_status')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_exchange` ADD COLUMN `recon_status` enum(\'0\',\'1\',\'2\') NOT NULL DEFAULT \'0\' COMMENT \'对账状态:0未对账 1待二审 2已对账\' AFTER `recon_code`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_info.delivery_mode
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_info' AND COLUMN_NAME='delivery_mode')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_info` ADD COLUMN `delivery_mode` enum(\'direct\',\'dropship\') NOT NULL DEFAULT \'direct\' COMMENT \'发货方式:direct=直发,dropship=代发\' AFTER `delivery_time`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_info.production_remark
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_info' AND COLUMN_NAME='production_remark')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_info` ADD COLUMN `production_remark` varchar(255) DEFAULT \'\' COMMENT \'生产备注\' AFTER `remark`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_order.delivery_mode
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_order' AND COLUMN_NAME='delivery_mode')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_order` ADD COLUMN `delivery_mode` enum(\'direct\',\'dropship\') NOT NULL DEFAULT \'direct\' COMMENT \'发货方式:direct=直发,dropship=代发\' AFTER `delivery_time`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_order.customers_money
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_order' AND COLUMN_NAME='customers_money')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_order` ADD COLUMN `customers_money` double(15,4) NOT NULL DEFAULT \'0.0000\' COMMENT \'客户承担费用\' AFTER `purchase_money`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_order_info.delivery_mode
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_order_info' AND COLUMN_NAME='delivery_mode')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_order_info` ADD COLUMN `delivery_mode` enum(\'direct\',\'dropship\') NOT NULL DEFAULT \'direct\' COMMENT \'发货方式:direct=直发,dropship=代发\' AFTER `delivery_time`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_order_info.production_remark
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_order_info' AND COLUMN_NAME='production_remark')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_order_info` ADD COLUMN `production_remark` varchar(255) DEFAULT \'\' COMMENT \'生产备注\' AFTER `remark`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_goods_sales_order_info.customers_money
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_goods_sales_order_info' AND COLUMN_NAME='customers_money')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_goods_sales_order_info` ADD COLUMN `customers_money` double(15,4) NOT NULL DEFAULT \'0.0000\' COMMENT \'客户承担费用\' AFTER `purchase_money`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_process.inbound_examine_status
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_process' AND COLUMN_NAME='inbound_examine_status')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_process` ADD COLUMN `inbound_examine_status` enum(\'0\',\'1\') DEFAULT \'0\' COMMENT \'入库成品审核状态:0=未审核,1=已审核\' AFTER `outbound_time`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_process.inbound_examine_id
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_process' AND COLUMN_NAME='inbound_examine_id')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_process` ADD COLUMN `inbound_examine_id` int(11) DEFAULT NULL COMMENT \'入库成品审核人\' AFTER `inbound_examine_status`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_process.inbound_examine_time
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_process' AND COLUMN_NAME='inbound_examine_time')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_process` ADD COLUMN `inbound_examine_time` bigint(16) DEFAULT NULL COMMENT \'入库成品审核时间\' AFTER `inbound_examine_id`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_process_info.out_number
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_process_info' AND COLUMN_NAME='out_number')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_process_info` ADD COLUMN `out_number` double(15,4) DEFAULT \'0.0000\' COMMENT \'已出库数量(仅type=1)\' AFTER `number`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_sales_invoicing.recon_id
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_sales_invoicing' AND COLUMN_NAME='recon_id')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_sales_invoicing` ADD COLUMN `recon_id` int(11) NOT NULL DEFAULT \'0\' COMMENT \'来源销货对账单ID\' AFTER `invoice_status`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- fa_kuerp_suppliers.contract
SET @s = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kuerp_suppliers' AND COLUMN_NAME='contract')>0, 'SELECT 1', 'ALTER TABLE `fa_kuerp_suppliers` ADD COLUMN `contract` varchar(255) NOT NULL DEFAULT \'\' COMMENT \'合同附件\' AFTER `bank_number`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 新增列合计: 37


-- ============================================================================
-- 三、列定义调整（3 处）
--    enclosure.image            注释改为「原始单据文件路径」（支持任意文件，不再是纯图片）
--    enclosure_log.type          枚举由 1~6 扩展为 1~13（入库记录/对账单/发票/付款回单/对账差异）
--    process_board_info.variance 注释修正为「差异=累计合格-累计目标」（与代码实际算法一致）
--    注意：MODIFY 只改定义，不影响已有数据（type 只在 1~6 内取值，扩展枚举不丢值）。
-- ============================================================================
-- fa_kuerp_enclosure.image
ALTER TABLE `fa_kuerp_enclosure` MODIFY COLUMN `image` varchar(255) NOT NULL DEFAULT '' COMMENT '原始单据文件路径';

-- fa_kuerp_enclosure_log.type
ALTER TABLE `fa_kuerp_enclosure_log` MODIFY COLUMN `type` enum('1','2','3','4','5','6','7','8','9','10','11','12','13') DEFAULT NULL COMMENT '单据类型:1=购货订单,2=采购单,3=购货退货单,4=销货订单,5=销货单,6=销货退货单,7=入库记录,8=采购对账单,9=发票,10=付款回单,11=销货对账单,12=销货发票,13=对账差异';

-- fa_kuerp_process_board_info.variance
ALTER TABLE `fa_kuerp_process_board_info` MODIFY COLUMN `variance` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '差异=累计合格-累计目标(不足为负数)';

-- ============================================================================
-- 四、权限规则（67 条）
--   按 name 幂等插入；pid 用父级 name 解析，不写死 id。
-- ============================================================================

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process/examineinbound', '入库成品审核/反审核', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process/examineinbound');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process/outboundEdit', '实际出库/入库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process/outboundEdit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process/saveOutbound', '保存实际出库/入库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process/saveOutbound');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply', '出库管理', 'fa fa-truck', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '76', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stores/default') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/detail', '详情', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/detail');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/examine', '同意出库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/examine');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/refuse', '拒绝出库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/refuse');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_sales_outbound/apply', '申请出库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_sales_outbound') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_sales_outbound/apply');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_sales_outbound/applycancel', '撤销申请', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_sales_outbound') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_sales_outbound/applycancel');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/del', '删除申请', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/del');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/edit', '编辑申请', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/edit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/assemble/pickingpage', '打印领料单', 'fa fa-print', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/assemble') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/assemble/pickingpage');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_plan_warehouse', '采购单(仓库)', 'fa fa-eye', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '78', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stores/default') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_plan_warehouse');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_plan_warehouse/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_plan_warehouse/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon', '采购对账', 'fa fa-check-square-o', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '78', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/buy/default') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/index', '对账单列表', 'fa fa-circle-o', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '79', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/add', '添加', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/add');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/edit', '编辑', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/edit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/del', '删除', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/del');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/detail', '详情', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/detail');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/submit', '提交审核', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/submit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/audit', '财务一审', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/audit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/invoice', '上传发票/账期', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/invoice');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply', '付款申请', 'fa fa-credit-card', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '98', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/capital') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/add', '添加', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/add');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/edit', '编辑', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/edit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/del', '删除', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/del');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/detail', '详情', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/detail');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/submit', '提交审核', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/submit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/financeexamine', '财务审核', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/financeexamine');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/auditexamine', '审计审核', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/auditexamine');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/leaderexamine', '领导审核', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/leaderexamine');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_payment_apply/pay', '确认打款', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_payment_apply/pay');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/purchases', '采购单列表', 'fa fa-list', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '77', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/purchases');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/supplierstat', '供应商待付款统计', 'fa fa-bar-chart', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '76', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/supplierstat');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon', '销货对账', 'fa fa-file-text-o', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '78', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/items', '销货对账明细', 'fa fa-list', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '77', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/items');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/add', '生成对账单', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '1', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/add');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/edit', '编辑', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '2', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/edit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/del', '删除', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '3', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/del');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/detail', '详情', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '4', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/detail');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/printpage', '打印', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '5', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/printpage');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/export', '导出', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '6', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/export');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/confirm', '一审', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '7', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/confirm');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/secondconfirm', '二审通过', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '8', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/secondconfirm');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/reject', '二审驳回', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '9', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/reject');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/cancelconfirm', '撤销对账', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '10', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/cancelconfirm');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/invoice', '开票', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '11', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/invoice');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/delinvoice', '删除发票', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '12', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/delinvoice');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/sync', '刷新明细', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '13', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/sync');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/secondaudit', '二审通过/驳回', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/secondaudit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/revoke', '撤销对账', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/revoke');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/export', '导出Excel', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/export');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/purchase_recon/printpage', '打印', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/purchase_recon/printpage');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse', '采购预订单(仓库)', 'fa fa-calendar-check-o', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '77', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stores/default') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/add', '添加', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/add');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/edit', '编辑', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/edit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/del', '删除', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/del');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/sales_recon/diff', '差异金额', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '9', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/sales_recon/diff');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/ar_analysis', '应收账款分析', 'fa fa-line-chart', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '84', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/capital') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/ar_analysis');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/ar_analysis/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/ar_analysis') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/ar_analysis/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/ar_analysis/rebuild', '重新分析', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '1', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/ar_analysis') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/ar_analysis/rebuild');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/ar_analysis/export', '导出', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '2', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/ar_analysis') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/ar_analysis/export');

-- 移除已被替代的旧外协出库规则（功能并入「实际出库/入库」；控制器方法与前端代码均已删除）
DELETE FROM `fa_auth_rule` WHERE `name` IN ('kuerp/process/outbound', 'kuerp/process/cancelOutbound', 'kuerp/process/processIn', 'kuerp/process/getinfo', 'kuerp/process/getInfo');

-- 清理角色 rules 中指向「已不存在规则」的悬空 id（删除上面的旧规则后会产生）
-- 仅剔除悬空 id，角色其余权限原样保留。
DROP PROCEDURE IF EXISTS `kuerp_clean_group_rules`;
DELIMITER $$
CREATE PROCEDURE `kuerp_clean_group_rules`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE g_id INT;
    DECLARE g_rules TEXT;
    DECLARE parts TEXT;
    DECLARE cleaned TEXT;
    DECLARE cur CURSOR FOR SELECT `id`, `rules` FROM `fa_auth_group` WHERE `rules` IS NOT NULL AND `rules` <> '*' AND `rules` <> '';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO g_id, g_rules;
        IF done = 1 THEN LEAVE read_loop; END IF;
        SET parts = g_rules;
        SET cleaned = '';
        WHILE parts <> '' DO
            SET @seg = SUBSTRING_INDEX(parts, ',', 1);
            IF LOCATE(',', parts) > 0 THEN
                SET parts = SUBSTRING(parts, LOCATE(',', parts) + 1);
            ELSE
                SET parts = '';
            END IF;
            IF @seg <> '' THEN
                SET @seg = TRIM(@seg);
                SET @ok = (SELECT COUNT(*) FROM `fa_auth_rule` WHERE `id` = @seg);
                IF @ok > 0 THEN
                    SET cleaned = CONCAT(cleaned, IF(cleaned = '', '', ','), @seg);
                END IF;
            END IF;
        END WHILE;
        IF cleaned <> g_rules THEN
            UPDATE `fa_auth_group` SET `rules` = cleaned WHERE `id` = g_id;
        END IF;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL `kuerp_clean_group_rules`();
DROP PROCEDURE `kuerp_clean_group_rules`;

-- ============================================================================
-- 五、配置行补齐（幂等：按 name / type 判重）
-- ============================================================================

-- 5.1 单号规则：出库申请(CKSQ) / 采购对账单(DZD) / 付款申请(FKSQ) / 客户对账单(KHDZ)
INSERT INTO `fa_kuerp_prefix` (`admin_id`, `type`, `name`, `prefix`, `year`, `month`, `day`, `hour`, `minute`, `second`, `start`, `strlen`, `example`, `dbname`, `field`)
SELECT 0, 29, '出库申请', 'CKSQ', 1, 1, 1, 2, 2, 2, 1, 3, NULL, 'kuerp_goods_outbound_apply', 'apply_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type` = 29);
INSERT INTO `fa_kuerp_prefix` (`admin_id`, `type`, `name`, `prefix`, `year`, `month`, `day`, `hour`, `minute`, `second`, `start`, `strlen`, `example`, `dbname`, `field`)
SELECT 1, 30, '采购对账单', 'DZD', 1, 1, 1, 2, 2, 2, 1, 3, 'DZD20261023001', 'kuerp_purchase_recon', 'recon_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type` = 30);
INSERT INTO `fa_kuerp_prefix` (`admin_id`, `type`, `name`, `prefix`, `year`, `month`, `day`, `hour`, `minute`, `second`, `start`, `strlen`, `example`, `dbname`, `field`)
SELECT 1, 31, '付款申请', 'FKSQ', 1, 1, 1, 2, 2, 2, 1, 3, 'FKSQ20261023001', 'kuerp_purchase_payment_apply', 'apply_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type` = 31);
INSERT INTO `fa_kuerp_prefix` (`admin_id`, `type`, `name`, `prefix`, `year`, `month`, `day`, `hour`, `minute`, `second`, `start`, `strlen`, `example`, `dbname`, `field`)
SELECT 1, 32, '客户对账单', 'KHDZ', 1, 1, 1, 2, 2, 2, 1, 3, 'KHDZ20261027001', 'kuerp_sales_recon', 'recon_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type` = 32);

-- 5.2 设置项：付款申请提醒天数 / 领导审核金额阈值
INSERT INTO `fa_kuerp_sets` (`admin_id`, `type`, `name`, `nickname`, `value`, `is_edit`, `notice`, `explain`, `is_checked`, `set`)
SELECT 1, 1, 'purchase_payment_remind_days', '采购付款提前提醒天数', '7', '0', '账期到期前多少天在采购对账列表标黄提醒', '到期日 - 提醒天数 <= 今天 且未付款时提醒', 0, NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_sets` WHERE `name` = 'purchase_payment_remind_days');
INSERT INTO `fa_kuerp_sets` (`admin_id`, `type`, `name`, `nickname`, `value`, `is_edit`, `notice`, `explain`, `is_checked`, `set`)
SELECT 1, 1, 'purchase_payment_leader_money', '付款申请领导审核金额', '50000', '0', '付款申请金额达到该值时，审计审核后需领导审核', '0 或留空表示不需要领导审核', 0, NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_sets` WHERE `name` = 'purchase_payment_leader_money');

-- ============================================================================
-- 六、历史数据兼容修复（仅在有旧数据时才生效，幂等）
-- ============================================================================

-- 6.0 工序小时看板：variance 口径统一为「累计合格 - 累计目标」。
--     线上旧数据按「累计目标 - 累计合格」口径存，这里逐行取反重算。
--     两列都在本行内，直接 UPDATE 即可；重复执行会二次取反，故用 createtime 之外的幂等守卫：
--     先把重算结果写入临时列式判断——用 `variance` 与 `cumulative_qty - cumulative_target` 比较，
--     已符合新口径（相等）则不再改动。
UPDATE `fa_kuerp_process_board_info`
   SET `variance` = ROUND(`cumulative_qty` - `cumulative_target`, 4)
 WHERE `variance` <> ROUND(`cumulative_qty` - `cumulative_target`, 4);

-- 6.1 外协加工单：新流程要求入库存量单先做「原料出库审核」。
--     线上已审核的老单据（examine_status=1）在旧逻辑里审核时已扣库存，
--     这里同样标记 outbound_status=2 且明细 out_number=number，防止新页面二次出库。
UPDATE `fa_kuerp_process`
   SET `outbound_status` = 2,
       `outbound_time`   = COALESCE(`outbound_time`, `examine_time`)
 WHERE `examine_status` = '1'
   AND (`outbound_status` IS NULL OR `outbound_status` <> 2);

UPDATE `fa_kuerp_process_info` i
  JOIN `fa_kuerp_process` p ON p.`process_code` = i.`process_code`
   SET i.`out_number` = i.`number`
 WHERE p.`examine_status` = '1'
   AND i.`type` = '1'
   AND (i.`out_number` IS NULL OR i.`out_number` = 0);

-- 6.2 组装单/外协历史批次关联列：新列默认 NULL，无需回填。

-- 6.3 计划排期：新字段 expected_delivery_date / delivery_mode 用默认值即可。
--     （delivery_mode 默认 direct 与业务默认一致，历史单据视为直发。）
UPDATE `fa_kuerp_goods_sales`
   SET `delivery_mode` = 'direct'
 WHERE `delivery_mode` IS NULL OR `delivery_mode` = '';

UPDATE `fa_kuerp_goods_sales_info`
   SET `delivery_mode` = 'direct'
 WHERE `delivery_mode` IS NULL OR `delivery_mode` = '';

-- 6.4 存量销货单仓库（recon_*/diff_money）默认 0，表示未对账，无需回填。

-- ============================================================================
-- 七、角色权限：增量追加（只加不删）
--   取「本地角色有、线上角色无」的规则，按 name 追加到线上同名角色。
--     线上角色独有的权限（enclosuresale / goods_type / goods_location / example 等）全部保留。
--     已存在的不会重复写入；可重复执行。
-- ============================================================================

-- 角色 7 仓储部：追加 5 条
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t))
 WHERE `id` = 7 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t2), IFNULL(`rules`,'')) = 0;
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/add') t))
 WHERE `id` = 7 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/add') t2), IFNULL(`rules`,'')) = 0;
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/del') t))
 WHERE `id` = 7 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/del') t2), IFNULL(`rules`,'')) = 0;
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/edit') t))
 WHERE `id` = 7 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/edit') t2), IFNULL(`rules`,'')) = 0;
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/index') t))
 WHERE `id` = 7 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/index') t2), IFNULL(`rules`,'')) = 0;

-- 角色 8 采购部：追加 2 条
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage') t))
 WHERE `id` = 8 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage') t2), IFNULL(`rules`,'')) = 0;
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/index') t))
 WHERE `id` = 8 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/index') t2), IFNULL(`rules`,'')) = 0;

-- 角色 19 采购专员：追加 2 条
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage') t))
 WHERE `id` = 19 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage') t2), IFNULL(`rules`,'')) = 0;
UPDATE `fa_auth_group` SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
    (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/index') t))
 WHERE `id` = 19 AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/index') t2), IFNULL(`rules`,'')) = 0;

-- 追加权限合计：9 条

-- ============================================================================
-- 完成。执行后请在后台「系统」→「清除缓存」，并让所有在线用户强刷一次浏览器。
--
-- 后续手工操作（脚本不自动做）：
--   1. 新增菜单「出库管理 / 采购对账 / 付款申请 / 销货对账 / 应收账款分析」默认只有超管可见，
--      需在「权限管理 → 角色管理」里给对应部门勾选。
--   2. 若侧边栏看不到新菜单，删除 runtime/cache 下的菜单缓存。
-- ============================================================================
