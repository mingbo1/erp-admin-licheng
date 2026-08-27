-- ============================================================
-- 外协加工单: 出库 / 取消出库 / 打印 权限规则
-- 表: fa_auth_rule  (name 字段有唯一索引, 用 INSERT IGNORE 可重复执行)
-- 父级: id=601 (kuerp/process 外协加工单)
-- 执行命令:
--   mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_process_permissions.sql
-- 注意: 执行后相关管理员需重新登录, 新权限才生效
-- ============================================================

INSERT IGNORE INTO `fa_auth_rule`
(`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
(678, 'file', 601, 'kuerp/process/outbound', '出库', 'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(679, 'file', 601, 'kuerp/process/cancelOutbound', '取消出库', 'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(680, 'file', 601, 'kuerp/process/printpage', '打印', 'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
