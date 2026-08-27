-- ============================================
-- 小程序首页导航表 - 中部金刚区导航
-- ============================================
CREATE TABLE IF NOT EXISTS `fa_kuerp_mini_nav` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '名称',
  `image` varchar(500) NOT NULL DEFAULT '' COMMENT '图标URL',
  `link_type` varchar(30) NOT NULL DEFAULT 'page' COMMENT '跳转类型: page=小程序页面, none=无跳转',
  `link_url` varchar(500) NOT NULL DEFAULT '' COMMENT '跳转链接(小程序页面路径)',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序(数字越小越靠前)',
  `status` varchar(20) NOT NULL DEFAULT 'normal' COMMENT '状态: normal=启用, hidden=禁用',
  `createtime` bigint(16) NOT NULL DEFAULT '0',
  `updatetime` bigint(16) NOT NULL DEFAULT '0',
  `deletetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小程序首页导航';
