-- ============================================================
-- 组装单[打印领料单]按钮权限 (Assemble::pickingpage)
-- 实际出库/入库弹窗(outbound_edit.html)标题栏的 [打印领料单] 按钮，
-- 打开 kuerp/assemble/pickingpage 领料单打印页（仅子件）。
-- 幂等执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_assemble_pickingpage_auth_rule.sql
-- ============================================================

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/assemble/pickingpage', '打印领料单', 'fa fa-print', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/assemble') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/assemble/pickingpage');
