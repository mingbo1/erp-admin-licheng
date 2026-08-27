-- ============================================================
-- kuerp 控制器审计: 补全缺失的权限规则
-- 表: fa_auth_rule  (name 字段有唯一索引, 用 INSERT IGNORE 可重复执行)
-- 说明: 以下方法在控制器中存在且不在 $noNeedRight 中,
--       但 fa_auth_rule 无对应规则 -> 普通管理员访问会 403 且无法分配权限
-- 执行命令:
--   mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_missing_permissions.sql
-- 注意: 执行后相关管理员需重新登录, 新权限才生效
-- ============================================================

INSERT IGNORE INTO `fa_auth_rule`
(`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
(684, 'file', 186, 'kuerp/allocation/printpage',              '调拨单打印',       'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(685, 'file', 102, 'kuerp/goods_exchange_info/index',          '查看明细',         'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(686, 'file', 663, 'kuerp/goods_purchase_order_plan/examine',   '审核/反审核',       'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(687, 'file', 663, 'kuerp/goods_purchase_order_plan/secondExamine', '二次审核',     'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(688, 'file', 148, 'kuerp/goods_sales_exchange_info/index',    '查看明细',         'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(689, 'file', 639, 'kuerp/goods_sales_outbound/add',           '添加',             'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(690, 'file', 639, 'kuerp/goods_sales_outbound/secondExamine',  '二次审核',         'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(691, 'file', 245, 'kuerp/goods_serial/index',                 '查看',             'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(692, 'file', 119, 'kuerp/invoicing_log/index',                '查看',             'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(693, 'file', 649, 'kuerp/process_order/detail',               '查看详情',         'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(694, 'file', 159, 'kuerp/sales_invoicing_log/index',          '查看',             'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(695, 'file', 320, 'kuerp/suppliers/destroy',                  '删除(回收站)',     'fa fa-circle-o', '', '', '', 0, NULL, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
