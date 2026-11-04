--
-- 迁移：采购对账补充页面（2026-10-23）
--   kuerp/purchase_recon/purchases     全供应商采购单列表（入库/对账/开票/付款状态）
--   kuerp/purchase_recon/supplierstat  供应商待付款统计
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_purchase_recon_pages.sql
-- 可重复执行。
--

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/purchases','采购单列表','fa fa-list','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'77',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/purchases');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/supplierstat','供应商待付款统计','fa fa-bar-chart','','','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'76',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/supplierstat');
