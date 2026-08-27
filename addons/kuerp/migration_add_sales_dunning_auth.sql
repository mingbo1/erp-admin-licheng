-- ============================================================
-- 销货单「催收」权限
-- 新增两条权限规则到 fa_auth_rule（幂等，可重复执行）
--   * kuerp/goods_sales/dunning   催收
--   * kuerp/goods_sales/delDunning 删除催收记录
--
-- 说明：
--   1. 本 SQL 只负责把权限规则写进 fa_auth_rule 权限表；
--   2. 规则所属父级 pid 通过子查询定位「销货单」(kuerp/goods_sales)，兼容各环境 id 不一致的情况；
--   3. 分配权限请到后台【权限管理 -> 角色】勾选“催收 / 删除催收记录”（超级管理员默认拥有全部权限，无需处理）。
-- 执行方式：
--   mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_sales_dunning_auth.sql
-- ============================================================

INSERT INTO `fa_auth_rule`
    (`pid`, `type`, `name`, `title`, `icon`, `ismenu`, `weigh`, `status`, `createtime`, `updatetime`)
SELECT p.id, 'file', 'kuerp/goods_sales/dunning', '催收', 'fa fa-circle-o', 0, 0, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `fa_auth_rule` p
WHERE p.name = 'kuerp/goods_sales'
  AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` r WHERE r.name = 'kuerp/goods_sales/dunning');

INSERT INTO `fa_auth_rule`
    (`pid`, `type`, `name`, `title`, `icon`, `ismenu`, `weigh`, `status`, `createtime`, `updatetime`)
SELECT p.id, 'file', 'kuerp/goods_sales/delDunning', '删除催收记录', 'fa fa-circle-o', 0, 0, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `fa_auth_rule` p
WHERE p.name = 'kuerp/goods_sales'
  AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` r WHERE r.name = 'kuerp/goods_sales/delDunning');

-- ============================================================
-- 可选：为已有角色批量追加催收权限
-- 移除 noNeedRight 后，原先“任何登录用户都能催收”的行为将失效，
-- 只有被勾选了「催收」权限的角色才能看到并使用催收按钮。
-- 以下按需执行，把括号内的角色ID替换为需要开启催收的角色（1=超级管理员 默认全权限，无需处理）。
-- 示例角色ID：2=销售部 3=销售员 4=财务部 5=销售主管
-- ============================================================
-- UPDATE `fa_auth_group`
-- SET `rules` = CONCAT(`rules`, ',661,662')
-- WHERE `id` IN (2, 3, 5)
--   AND CONCAT(',', `rules`, ',') NOT LIKE '%,661,%';
