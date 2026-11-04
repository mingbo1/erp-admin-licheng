--
-- 迁移：采购预订单按部门隔离 + 新增「采购预订单(仓库)」菜单 (2026-10-27)
--
-- 1. fa_kuerp_goods_purchase_order 新增 order_source 字段，标记预订单来源部门：
--      plan      计划部门（计划购货订单 / kuerp/goods_purchase_order_plan）
--      warehouse 仓库部门（采购预订单(仓库) / kuerp/goods_purchase_order_plan_warehouse）
--      purchase  购货订单页（kuerp/goods_purchase_order，不参与部门隔离）
--    已存在的历史单据默认按 plan 处理（此前只由计划部门录入）。
-- 2. 菜单「采购预订单(仓库)」挂在「仓库」组(kuerp/stores/default)下，与「采购单(仓库)」「出库管理」同组，
--    含 查看/添加/编辑/删除 四条子规则。
--
-- 执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_purchase_order_source.sql
-- 可重复执行（列已存在 / 规则已存在时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_purchase_order_source`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_purchase_order_source`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_purchase_order'
           AND COLUMN_NAME = 'order_source') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_purchase_order`
            ADD COLUMN `order_source` enum('plan','warehouse','purchase') NOT NULL DEFAULT 'plan'
            COMMENT '来源:plan=计划部门,warehouse=仓库部门,purchase=购货订单' AFTER `purchase_type`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_purchase_order_source`();
DROP PROCEDURE `kuerp_add_purchase_order_source`;

-- 菜单 (pid 用子查询解析「仓库」组 id)
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse', '采购预订单(仓库)', 'fa fa-calendar-check-o', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '77',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stores/default') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse');

-- 子规则: 查看 / 添加 / 编辑 / 删除
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/index');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/add', '添加', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/add');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/edit', '编辑', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/edit');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_order_plan_warehouse/del', '删除', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_order_plan_warehouse/del');
