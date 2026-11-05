--
-- 迁移：采购单入库（kuerp/goods_purchase_storage）「详情 / 打印 / 入库」纳入权限表（2026-11-05）
--
--   这三个方法原先写在 $noNeedRight 里，权限表没有对应规则 → 无法按角色控制，
--   且视图无法用 $auth->check 隐藏按钮。本次：
--     1) 新增三条规则（pid = 采购单入库菜单）；
--     2) 把三条规则增量授予「已拥有 kuerp/goods_purchase_storage/index 查看权限」的角色，
--        保证升级后现有角色（仓储部/采购部/仓库专员等）行为不变，之后可在 角色管理 里按需取消。
--
-- 配套代码：GoodsPurchaseStorage::$noNeedRight 已移除 detail/printstorage/storage；
--           view/kuerp/goods_purchase_storage/index.html 行按钮加 $auth->check 控制显隐。
--
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_purchase_storage_auth_rules.sql
-- 可重复执行。
--

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase_storage/detail','详情','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'1',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/detail');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase_storage/printstorage','打印','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'2',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/printstorage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase_storage/storage','入库','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'3',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/storage');

-- 增量授予：只对已有「查看」权限的角色追加，已有该规则的角色跳过（不覆盖任何现有规则）
UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(`rules`, ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/detail')))
WHERE FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/index'), `rules`)
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/detail'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(`rules`, ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/printstorage')))
WHERE FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/index'), `rules`)
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/printstorage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(`rules`, ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/storage')))
WHERE FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/index'), `rules`)
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_storage/storage'), `rules`);
