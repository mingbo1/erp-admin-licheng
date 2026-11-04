-- ============================================================
-- #7 出库流程调整: 销货出库申请 → 仓库"出库管理"审核执行
-- 1. 新建 fa_kuerp_goods_outbound_apply 出库申请主表
-- 2. 新建 fa_kuerp_goods_outbound_apply_info 出库申请明细表
-- 3. fa_kuerp_prefix 增加出库申请单号前缀 (type=29 CKSQ)
-- 4. 权限规则: 仓库组下"出库管理"菜单+子规则; 销货单出库下 申请/撤销 权限
-- 执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_outbound_apply.sql
-- 注意: 执行后需清空 runtime/cache 侧栏菜单才会出现"出库管理"; 角色分配走 角色管理 UI
-- ============================================================

-- 1. 出库申请主表
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

-- 2. 出库申请明细表
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

-- 3. 单号前缀 (仿销货单 type=11: 年月日+3位流水, 例 CKSQ20261018001; type/prefix 均有唯一键)
INSERT INTO `fa_kuerp_prefix` (`admin_id`, `type`, `name`, `prefix`, `year`, `month`, `day`, `hour`, `minute`, `second`, `start`, `strlen`, `dbname`, `field`)
SELECT 0, 29, '出库申请', 'CKSQ', '1', '1', '1', '2', '2', '2', 1, 3, 'kuerp_goods_outbound_apply', 'apply_code'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_kuerp_prefix` WHERE `type` = 29);

-- 4. 权限规则: 仓库组(pid=185 kuerp/stores/default)下新菜单"出库管理"
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply', '出库管理', 'fa fa-truck', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '76', 185
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply');

-- 4.1 子规则 (pid 用双层子查询解析菜单 id, 避免写死)
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/index');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/detail', '详情', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/detail');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/examine', '同意出库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/examine');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/refuse', '拒绝出库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/refuse');

-- 4.2 销货单出库(pid=639)下 申请出库/撤销申请 权限 (计划部门用)
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_sales_outbound/apply', '申请出库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', 639
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_sales_outbound/apply');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_sales_outbound/applycancel', '撤销申请', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', 639
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_sales_outbound/applycancel');

-- 说明: 不为 outbound/saveOutbound 建规则 — 二者已从 GoodsSalesOutbound::$noNeedRight 移除,
-- 直连 URL 一律无权限(防绕过申请直接出库); 出库执行由 goods_outbound_apply/examine 内部调用 saveOutbound 完成.

-- 5. 编辑申请/删除申请 权限 (2026-10-18 补充; 编辑仅待审核申请可改数量, 删除仅未同意出库的申请)
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/edit', '编辑申请', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/edit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_outbound_apply/del', '删除申请', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_outbound_apply/del');
