--
-- 迁移：新增「排产管理」菜单与权限规则（2026-11-09）
--
--   kuerp/production_manage          排产管理（菜单，挂在 销货 组下）
--   kuerp/production_manage/index    查看
--   kuerp/production_manage/detail   查看详情
--   kuerp/production_manage/export   导出
--
-- 说明：页面为「计划排期」的只读副本，列表只显示已排产（production_status=1）的销货单，
--       工具栏只有 刷新 / 导出，行操作只有 查看详情；新增/编辑/删除等写操作后端一律拦截。
--       三条子规则增量授予「已拥有计划排期（kuerp/goods_sales_schedule）权限」的角色，
--       升级后现有角色行为不变，之后可在 角色管理 → 排产管理 里按需取消。
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_production_manage.sql
-- 可重复执行。
--

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/production_manage','排产管理','fa fa-tasks','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'97',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/sales') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/production_manage/index','查看','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/index');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/production_manage/detail','查看详情','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/detail');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/production_manage/export','导出','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/export');

-- 增量授予：已有「计划排期」菜单或查看权限的角色，追加 排产管理 菜单 + 查看/查看详情/导出
UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage')))
WHERE `rules` <> '*'
  AND (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule'), `rules`)
    OR FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/index')))
WHERE `rules` <> '*'
  AND (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule'), `rules`)
    OR FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/index'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/detail')))
WHERE `rules` <> '*'
  AND (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule'), `rules`)
    OR FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/detail'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/export')))
WHERE `rules` <> '*'
  AND (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule'), `rules`)
    OR FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/production_manage/export'), `rules`);
