-- =====================================================
-- 外协订单 菜单权限 SQL
-- 在数据库中执行此SQL以注册菜单和权限
-- =====================================================

-- 1. 主菜单（pid=185 仓库分组下，与外协加工单同级，ismenu=1 显示为菜单）
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
(649, 'file', 185, 'kuerp/process_order', '外协订单', 'fa fa-file-text-o', '', '', '', 1, NULL, '', 'wxdd', 'waixiedingdan', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 61, 'normal');

-- 2. 子菜单项（pid=649，ismenu=0 不作为独立菜单）
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
(650, 'file', 649, 'kuerp/process_order/index', '查看', 'fa fa-circle-o', '', '', '', 0, NULL, '', 'ck', 'chakan', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(651, 'file', 649, 'kuerp/process_order/add', '添加', 'fa fa-circle-o', '', '', '', 0, NULL, '', 'tj', 'tianjia', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(652, 'file', 649, 'kuerp/process_order/edit', '编辑', 'fa fa-circle-o', '', '', '', 0, NULL, '', 'bj', 'bianji', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(653, 'file', 649, 'kuerp/process_order/del', '删除', 'fa fa-circle-o', '', '', '', 0, NULL, '', 'sc', 'shanchu', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
(654, 'file', 649, 'kuerp/process_order/examine', '审核/反审核', 'fa fa-circle-o', '', '', '', 0, NULL, '', 'shfsh', 'shenhefanshenhe', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 3. API端点（pid=449 默认函数下，不需要菜单显示）
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
VALUES
(655, 'file', 449, 'kuerp/process_order/getUnit', '获取外协订单单位信息', 'fa fa-circle-o', '', '', '', 0, NULL, '', 'hqwxdddwx', 'huoquwaixiedingdandanweixinxi', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
