-- 职员表增加类型字段
-- 执行: mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_staff_member_type.sql

ALTER TABLE `fa_kuerp_staff_member`
ADD COLUMN `type` enum('sales','plan','warehouse','finance','sales_director','admin') DEFAULT NULL COMMENT '职员类型:sales=销售员,plan=计划,warehouse=仓库管理员,finance=财务,sales_director=销售部长,admin=管理员' AFTER `phone`;
