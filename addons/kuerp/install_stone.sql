SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- 石材经销存小程序 — 出入库专用表
-- 在 kuerp install.sql 和 install_mini.sql 基础上追加
-- ============================================================

-- ============================================================
-- 1. 入库单主表: fa_kuerp_stone_inbound
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_stone_inbound` (
    `id`               INT(11)         NOT NULL AUTO_INCREMENT,
    `in_no`            VARCHAR(50)     NOT NULL COMMENT '入库单号',
    `supplier_id`      INT(11)         NOT NULL DEFAULT 0 COMMENT '供应商ID',
    `supplier_name`    VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '供应商名称(快照)',
    `store_id`         INT(11)         NOT NULL DEFAULT 0 COMMENT '仓库ID',
    `store_name`       VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '仓库名称(快照)',
    `total_amount`     DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '入库总金额',
    `remark`           VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '备注',
    `examine_status`   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '审核状态:0=未审核,1=已审核',
    `examine_time`     INT(11)         NOT NULL DEFAULT 0 COMMENT '审核时间',
    `admin_id`         INT(11)         NOT NULL DEFAULT 0 COMMENT '操作管理员ID',
    `createtime`       INT(11)         NOT NULL DEFAULT 0,
    `updatetime`       INT(11)         NOT NULL DEFAULT 0,
    `deletetime`       INT(11)         DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_in_no` (`in_no`),
    KEY `idx_supplier` (`supplier_id`),
    KEY `idx_store` (`store_id`),
    KEY `idx_examine` (`examine_status`),
    KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='石材-入库单(采购入库)';

-- ============================================================
-- 2. 入库明细表: fa_kuerp_stone_inbound_info
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_stone_inbound_info` (
    `id`           INT(11)         NOT NULL AUTO_INCREMENT,
    `in_id`        INT(11)         NOT NULL COMMENT '入库单ID',
    `goods_id`     INT(11)         NOT NULL DEFAULT 0 COMMENT '商品ID(fa_kuerp_goods)',
    `goods_code`   VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '商品编码(快照)',
    `goods_name`   VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '商品名称(快照)',
    `unit_name`    VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '单位(快照)',
    `quantity`     DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '入库数量',
    `unit_price`   DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '入库单价',
    `total_price`  DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '行金额(=quantity*unit_price)',
    `createtime`   INT(11)         NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_in_id` (`in_id`),
    KEY `idx_goods` (`goods_id`),
    KEY `idx_goods_code` (`goods_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='石材-入库明细';

-- ============================================================
-- 3. 出库单主表: fa_kuerp_stone_outbound
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_stone_outbound` (
    `id`               INT(11)         NOT NULL AUTO_INCREMENT,
    `out_no`           VARCHAR(50)     NOT NULL COMMENT '出库单号',
    `customer_id`      INT(11)         NOT NULL DEFAULT 0 COMMENT '客户ID',
    `customer_name`    VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '客户名称(快照)',
    `store_id`         INT(11)         NOT NULL DEFAULT 0 COMMENT '仓库ID',
    `store_name`       VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '仓库名称(快照)',
    `order_id`         INT(11)         NOT NULL DEFAULT 0 COMMENT '关联订货单ID(可选)',
    `total_amount`     DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '出库总金额',
    `remark`           VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '备注',
    `examine_status`   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '审核状态:0=未审核,1=已审核',
    `examine_time`     INT(11)         NOT NULL DEFAULT 0 COMMENT '审核时间',
    `admin_id`         INT(11)         NOT NULL DEFAULT 0 COMMENT '操作管理员ID',
    `createtime`       INT(11)         NOT NULL DEFAULT 0,
    `updatetime`       INT(11)         NOT NULL DEFAULT 0,
    `deletetime`       INT(11)         DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_out_no` (`out_no`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_store` (`store_id`),
    KEY `idx_examine` (`examine_status`),
    KEY `idx_order` (`order_id`),
    KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='石材-出库单(销售出库)';

-- ============================================================
-- 4. 出库明细表: fa_kuerp_stone_outbound_info
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_stone_outbound_info` (
    `id`           INT(11)         NOT NULL AUTO_INCREMENT,
    `out_id`       INT(11)         NOT NULL COMMENT '出库单ID',
    `goods_id`     INT(11)         NOT NULL DEFAULT 0 COMMENT '商品ID(fa_kuerp_goods)',
    `goods_code`   VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '商品编码(快照)',
    `goods_name`   VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '商品名称(快照)',
    `unit_name`    VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '单位(快照)',
    `quantity`     DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '出库数量',
    `unit_price`   DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '出库单价',
    `total_price`  DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '行金额(=quantity*unit_price)',
    `createtime`   INT(11)         NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_out_id` (`out_id`),
    KEY `idx_goods` (`goods_id`),
    KEY `idx_goods_code` (`goods_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='石材-出库明细';

-- ============================================================
-- 商品类型分类 + BOM模板 + 商品计算器
-- ============================================================

-- 1. 商品类型字段
ALTER TABLE `fa_kuerp_goods` ADD COLUMN `product_type` enum('finished','semi_finished','part') DEFAULT 'part' COMMENT '商品类型:finished=成品,semi_finished=半成品,part=零件' AFTER `goods_type_id`;

-- 2. BOM模板主表
CREATE TABLE `fa_kuerp_goods_bom` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `bom_name` varchar(255) NOT NULL COMMENT '模板名称',
  `goods_id` int(11) NOT NULL COMMENT '父商品ID(成品)',
  `goods_code` varchar(50) DEFAULT NULL COMMENT '父商品编号',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品BOM模板主表';

-- 3. BOM模板明细表
CREATE TABLE `fa_kuerp_goods_bom_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `bom_id` int(11) NOT NULL COMMENT 'BOM模板ID',
  `type` enum('1','2') NOT NULL DEFAULT '2' COMMENT '类型:1=父商品,2=子商品',
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `goods_code` varchar(50) DEFAULT NULL COMMENT '商品编号',
  `goods_name` varchar(255) DEFAULT NULL COMMENT '商品名称(冗余)',
  `unit_id` int(11) DEFAULT NULL COMMENT '单位ID',
  `number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '单耗数量(每个成品需要的子件数)',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bom_id` (`bom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品BOM模板明细表';

-- ============================================================
-- 商品库位管理
-- ============================================================

-- 1. 商品库位字段
ALTER TABLE `fa_kuerp_goods` ADD COLUMN `location_id` int(11) DEFAULT 0 COMMENT '库位ID' AFTER `goods_type_id`;

-- 2. 商品库位表
CREATE TABLE `fa_kuerp_goods_location` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `location_name` varchar(120) NOT NULL COMMENT '库位名称',
  `pid` int(11) NOT NULL DEFAULT 0 COMMENT '上级',
  `path` varchar(255) DEFAULT NULL COMMENT 'path',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `weigh` int(11) NOT NULL DEFAULT 0,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品库位表';

-- ============================================================
-- 销货单/购货单 二次审核机制
-- ============================================================

-- 1. 销货订单表新增二次审核字段
ALTER TABLE `fa_kuerp_goods_sales_order`
  ADD COLUMN `second_examine_status` tinyint(1) DEFAULT 0 COMMENT '二次审核状态 0=未审核 1=已审核' AFTER `examine_time`,
  ADD COLUMN `second_examine_id` int(11) DEFAULT 0 COMMENT '二次审核人ID' AFTER `second_examine_status`,
  ADD COLUMN `second_examine_time` bigint(16) DEFAULT NULL COMMENT '二次审核时间' AFTER `second_examine_id`;

-- 2. 购货订单表新增二次审核字段
ALTER TABLE `fa_kuerp_goods_purchase_order`
  ADD COLUMN `second_examine_status` tinyint(1) DEFAULT 0 COMMENT '二次审核状态 0=未审核 1=已审核' AFTER `examine_time`,
  ADD COLUMN `second_examine_id` int(11) DEFAULT 0 COMMENT '二次审核人ID' AFTER `second_examine_status`,
  ADD COLUMN `second_examine_time` bigint(16) DEFAULT NULL COMMENT '二次审核时间' AFTER `second_examine_id`;

-- 3. 系统设置: 二次审核金额阈值
INSERT INTO `__PREFIX__kuerp_sets` (`admin_id`, `type`, `name`, `nickname`, `value`, `is_edit`, `is_checked`) VALUES
(1, 1, 'sales_order_examine_money', '销货订单二次审核金额', '0', '0', 0),
(1, 1, 'purchase_order_examine_money', '购货订单二次审核金额', '0', '0', 0);

-- 4. 销货单表新增二次审核字段
ALTER TABLE `fa_kuerp_goods_sales`
  ADD COLUMN `second_examine_status` tinyint(1) DEFAULT 0 COMMENT '二次审核状态' AFTER `examine_time`,
  ADD COLUMN `second_examine_id` int(11) DEFAULT 0 COMMENT '二次审核人ID' AFTER `second_examine_status`,
  ADD COLUMN `second_examine_time` bigint(16) DEFAULT NULL COMMENT '二次审核时间' AFTER `second_examine_id`;

-- 5. 购货单表新增二次审核字段
ALTER TABLE `fa_kuerp_goods_purchase`
  ADD COLUMN `second_examine_status` tinyint(1) DEFAULT 0 COMMENT '二次审核状态' AFTER `examine_time`,
  ADD COLUMN `second_examine_id` int(11) DEFAULT 0 COMMENT '二次审核人ID' AFTER `second_examine_status`,
  ADD COLUMN `second_examine_time` bigint(16) DEFAULT NULL COMMENT '二次审核时间' AFTER `second_examine_id`;

COMMIT;
