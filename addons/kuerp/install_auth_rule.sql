-- ============================================================
-- 订货通 后台权限规则 (fa_auth_rule)
-- 执行前请确认 max_id 防止冲突
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- 1. 创建顶级菜单: 订货通
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `ismenu`, `weigh`, `status`) VALUES
(502, 'file', 0, 'order_platform', '订货通', 'fa fa-shopping-cart', '', 1, 8, 'normal');

-- 2. 创建子菜单: 轮播管理 (pid=502)
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `ismenu`, `weigh`, `status`) VALUES
(503, 'file', 502, 'kuerp/mini_banner', '轮播管理', 'fa fa-picture-o', '', 1, 100, 'normal'),
(504, 'file', 503, 'kuerp/mini_banner/index', '查看', 'fa fa-circle-o', '', 0, 0, 'normal'),
(505, 'file', 503, 'kuerp/mini_banner/add', '添加', 'fa fa-circle-o', '', 0, 0, 'normal'),
(506, 'file', 503, 'kuerp/mini_banner/edit', '编辑', 'fa fa-circle-o', '', 0, 0, 'normal'),
(507, 'file', 503, 'kuerp/mini_banner/del', '删除', 'fa fa-circle-o', '', 0, 0, 'normal');

-- 3. 创建子菜单: 订货单管理 (pid=502)
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `ismenu`, `weigh`, `status`) VALUES
(508, 'file', 502, 'kuerp/mini_order', '订货单管理', 'fa fa-file-text-o', '', 1, 90, 'normal'),
(509, 'file', 508, 'kuerp/mini_order/index', '订单列表', 'fa fa-circle-o', '', 0, 0, 'normal'),
(510, 'file', 508, 'kuerp/mini_order/detail', '查看详情', 'fa fa-circle-o', '', 0, 0, 'normal'),
(511, 'file', 508, 'kuerp/mini_order/confirm', '确认订单', 'fa fa-circle-o', '', 0, 0, 'normal'),
(512, 'file', 508, 'kuerp/mini_order/ship', '发货', 'fa fa-circle-o', '', 0, 0, 'normal'),
(513, 'file', 508, 'kuerp/mini_order/cancel', '取消订单', 'fa fa-circle-o', '', 0, 0, 'normal'),
(514, 'file', 508, 'kuerp/mini_order/del', '删除', 'fa fa-circle-o', '', 0, 0, 'normal');

-- 4. 创建子菜单: 系统配置 (pid=502)
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `ismenu`, `weigh`, `status`) VALUES
(515, 'file', 502, 'kuerp/mini_config', '系统配置', 'fa fa-cog', '', 1, 80, 'normal'),
(516, 'file', 515, 'kuerp/mini_config/index', '查看', 'fa fa-circle-o', '', 0, 0, 'normal'),
(517, 'file', 515, 'kuerp/mini_config/edit', '编辑', 'fa fa-circle-o', '', 0, 0, 'normal');

-- 5. 创建子菜单: 用户管理 (pid=502)
INSERT INTO `fa_auth_rule` (`id`, `type`, `pid`, `name`, `title`, `icon`, `url`, `ismenu`, `weigh`, `status`) VALUES
(518, 'file', 502, 'kuerp/mini_user', '用户管理', 'fa fa-users', '', 1, 70, 'normal'),
(519, 'file', 518, 'kuerp/mini_user/index', '查看', 'fa fa-circle-o', '', 0, 0, 'normal'),
(520, 'file', 518, 'kuerp/mini_user/edit', '编辑', 'fa fa-circle-o', '', 0, 0, 'normal'),
(521, 'file', 518, 'kuerp/mini_user/del', '删除', 'fa fa-circle-o', '', 0, 0, 'normal');

COMMIT;
