-- ============================================================================
-- 收发存汇总表（月度快照）(2026-10-28)
--
-- 需求：资料/财务功能/收发存汇总表.xlsx
--   每月 1 日 0 点自动生成上月快照；未满月份按实时数据计算，期末三列留空。
--   快照按「商品 + 仓库」存一行，页面在「全部仓库」时合并为每个商品一行。
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_stock_summary.sql
-- 可重复执行。
-- ============================================================================

CREATE TABLE IF NOT EXISTS `fa_kuerp_stock_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period` varchar(7) NOT NULL DEFAULT '' COMMENT '汇总月份 YYYY-MM',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `goods_code` varchar(50) NOT NULL DEFAULT '' COMMENT '物料编码(快照)',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '物料名称(快照)',
  `goods_spec` varchar(100) NOT NULL DEFAULT '' COMMENT '规格型号(快照)',
  `goods_unit` varchar(50) NOT NULL DEFAULT '' COMMENT '单位名称(快照)',
  `begin_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '期初数量(上一期期末)',
  `begin_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '期初单价(上一期期末)',
  `begin_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '期初金额(上一期期末)',
  `in_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期入库数量',
  `in_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期入库单价=入库金额/入库数量',
  `in_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期入库金额(含成本调整)',
  `out_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期出库数量',
  `out_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期出库单价=(期初金额+入库金额)/(期初数量+入库数量)',
  `out_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期出库金额(单据实际成本)',
  `transfer_in_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期调拨入库数量(仅单仓库查看时计入)',
  `transfer_in_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期调拨入库金额',
  `transfer_out_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期调拨出库数量(仅单仓库查看时计入)',
  `transfer_out_money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本期调拨出库金额',
  `end_qty` double(15,4) DEFAULT NULL COMMENT '期末数量=期初+入库-出库(未满月份为NULL)',
  `end_price` double(15,4) DEFAULT NULL COMMENT '期末单价=期末金额/期末数量(未满月份为NULL)',
  `end_money` double(15,4) DEFAULT NULL COMMENT '期末金额=期初+入库-出库(未满月份为NULL)',
  `is_complete` tinyint(1) NOT NULL DEFAULT '1' COMMENT '月份是否已结束:1=是(期末已算),0=否(期末留空)',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `period_goods_stores` (`period`,`goods_id`,`stores_id`),
  KEY `period` (`period`),
  KEY `goods_code` (`goods_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收发存汇总月度快照';

-- ---------------------------------------------------------------------------
-- 菜单：资金 → 收发存汇总表（pid 用子查询解析「资金」组 id）
-- ---------------------------------------------------------------------------
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/stock_summary', '收发存汇总表', 'fa fa-cubes', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '33',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/capital') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/stock_summary');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/stock_summary/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stock_summary') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/stock_summary/index');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/stock_summary/rebuild', '重新计算', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '1',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stock_summary') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/stock_summary/rebuild');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/stock_summary/export', '导出', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '2',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stock_summary') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/stock_summary/export');
