-- ============================================================================
-- 采购单(仓库) — 仓库部门只读查看采购单(计划) (2026-10-20)
--
-- 新增菜单 kuerp/goods_purchase_plan_warehouse, 挂在「仓库」组(kuerp/stores/default)下,
-- 与「出库管理」(kuerp/goods_outbound_apply) 同组。
-- 页面为只读: 仅 查看(index) + 打印(printpage), 控制器 GoodsPurchasePlanWarehouse
-- 已重写 add/edit/del 一律报错, 故不建对应权限规则(非超管直连也拿不到权限)。
-- printpage 在 $noNeedRight 中(与采购单(计划)一致), 同样不建规则。
--
-- 执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_purchase_plan_warehouse.sql
-- 可重复执行(WHERE NOT EXISTS 守卫)。
-- ============================================================================

-- 1. 菜单 (pid 用子查询解析「仓库」组 id, 不写死 185)
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_plan_warehouse', '采购单(仓库)', 'fa fa-eye', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '78',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/stores/default') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_plan_warehouse');

-- 2. 子规则: 查看 (打印不建规则 — printpage 在 $noNeedRight)
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/goods_purchase_plan_warehouse/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_plan_warehouse/index');
