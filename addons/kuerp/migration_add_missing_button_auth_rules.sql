--
-- 迁移：补齐后加按钮缺失的权限规则（2026-11-05）
--
--   kuerp/goods_bom/printpage       BOM模板列表「打印」
--                                   （Kuerp.php 的 $menu 里早已加了这条，数据库里漏装）
--   kuerp/goods_purchase/summary    采购单列表「汇总」（打开 采购汇总 页面）
--   kuerp/process_order/printpage   外协订单列表「打印」
--   kuerp/sales_recon/deldiff       销货对账 - 差异金额弹窗「删除差异」
--   kuerp/sales_recon/uploaddiff    销货对账 - 差异金额弹窗「上传附件」
--
-- 说明：
--   前三条对应的方法写在 $noNeedRight 里，规则本身不参与后端鉴权；但视图里用
--   $auth->check($nowmenu.'/xxx') 控制按钮显隐，权限表没有这条规则时 check 恒为 false，
--   非超管角色看不到按钮（菜单/按钮“加了权限也显示不出来”的原因）。
--   后两条（deldiff / uploaddiff）不在 $noNeedRight，是真实参与后端鉴权的方法：
--   缺规则时除超管外任何角色点「删除差异 / 上传附件」都会提示“你没有权限”。
--
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_missing_button_auth_rules.sql
-- 可重复执行。
--

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_bom/printpage','打印','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_bom') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_bom/printpage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase/summary','汇总','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/summary');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/process_order/printpage','打印','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/process_order') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/process_order/printpage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/deldiff','删除差异金额','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'15',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/deldiff');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/sales_recon/uploaddiff','上传差异附件','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'16',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/sales_recon/uploaddiff');
