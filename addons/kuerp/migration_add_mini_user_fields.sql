-- 给小程序用户表增加：用户类型、绑定销售员、登录密码
ALTER TABLE `fa_kuerp_mini_user`
ADD COLUMN `user_type` enum('sales','supplier') DEFAULT 'sales' COMMENT '用户类型: sales=销售员, supplier=供应商' AFTER `apptype`,
ADD COLUMN `staff_id` int(11) DEFAULT 0 COMMENT '绑定销售人员(fa_kuerp_staff_member.id)' AFTER `user_type`,
ADD COLUMN `password` varchar(64) DEFAULT '' COMMENT '登录密码(仅账密登录)' AFTER `staff_id`;
