-- 销货订单表增加来源字段，区分后台添加和小程序端添加
ALTER TABLE `fa_kuerp_goods_sales_order`
ADD COLUMN `source` enum('admin','miniapp') DEFAULT 'admin' COMMENT '来源: admin=后台添加, miniapp=小程序端' AFTER `status`,
ADD COLUMN `mini_user_id` int(11) DEFAULT 0 COMMENT '小程序用户ID(fa_kuerp_mini_user.id)' AFTER `source`;
