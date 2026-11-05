--
-- 迁移：把「后来新增的按钮」对应的动作纳入权限表（2026-11-05 第二批）
--
-- 背景：这些方法原先写在控制器的 $noNeedRight 里 —— 任何能进页面的登录用户都能用，
--       权限表里也没有记录，无法按角色控制、视图也没法隐藏按钮。
-- 本次统一处理：
--   1) 新增缺失的规则（pid = 各模块菜单）；
--   2) 控制器 $noNeedRight 移除这些动作（后端真正鉴权）；
--   3) 视图/JS 加 $auth->check 控制按钮显隐（见各视图改动）；
--   4) 规则增量授予「已拥有该模块查看权限」的角色 —— 升级后现有角色行为不变。
--
-- 覆盖的动作：
--   采购单      printpage / summary / export
--   组装单      printpage / outboundEdit / saveOutbound
--   外协订单    printpage
--   商品BOM     printpage
--   出库管理    printpage(打印申请单) / deliverynote(打印送货单)
--   销货订单    confirmReceipt(确认收货)
--   计划排期    setproduction(排产) / setexpecteddelivery / lockGoods / unlockGoods / export
--   采购预订单  export / parseimport
--   采购单(仓库) printpage
--   小程序用户  add（并一并对齐 index/edit/del 的授权）
--
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_buttons_auth_rules_batch2.sql
-- 可重复执行。
--

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase/printpage','打印','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/printpage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase/export','导出','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/export');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/assemble/saveOutbound','保存实际出入库','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/saveOutbound');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_outbound_apply/printpage','打印申请单','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/printpage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_outbound_apply/deliverynote','打印送货单','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/deliverynote');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_sales_order/confirmReceipt','确认收货','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_order') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_order/confirmReceipt');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_sales_schedule/setproduction','排产','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/setproduction');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_sales_schedule/setexpecteddelivery','修改预计生产完成日期','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/setexpecteddelivery');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_sales_schedule/lockGoods','锁定零件库存','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/lockGoods');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_sales_schedule/unlockGoods','解锁零件库存','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/unlockGoods');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_sales_schedule/export','导出','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/export');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase_order/export','导出明细','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/export');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase_order/parseimport','导入明细','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/parseimport');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/goods_purchase_plan_warehouse/printpage','打印','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_plan_warehouse') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_plan_warehouse/printpage');

INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/mini_user/add','添加','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/add');

-- 增量授权：只对已拥有该模块「查看」权限的角色追加，已有该规则的角色跳过（不覆盖任何现有规则）

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/printpage')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/printpage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/export')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/export'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/summary')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase/summary'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/printpage')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/printpage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/outboundEdit')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/outboundEdit'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/saveOutbound')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/assemble/saveOutbound'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/process_order/printpage')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/process_order/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/process_order/printpage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_bom/printpage')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_bom/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_bom/printpage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/printpage')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/printpage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/deliverynote')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/index'), `rules`) OR FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_outbound/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_outbound_apply/deliverynote'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_order/confirmReceipt')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_order/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_order/confirmReceipt'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/setproduction')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/setproduction'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/setexpecteddelivery')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/setexpecteddelivery'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/lockGoods')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/lockGoods'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/unlockGoods')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/unlockGoods'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/export')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_sales_schedule/export'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/export')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/export'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/parseimport')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_order/parseimport'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_plan_warehouse/printpage')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_plan_warehouse/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/goods_purchase_plan_warehouse/printpage'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/add')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/add'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/edit')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/edit'), `rules`);

UPDATE `fa_auth_group`
SET `rules` = TRIM(BOTH ',' FROM CONCAT(COALESCE(`rules`,''), ',', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/del')))
WHERE (FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/index'), `rules`))
  AND NOT FIND_IN_SET((SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/mini_user/del'), `rules`);
