-- 组装单增加"生产日期"字段（add/edit 可设置，打印组装单时展示）
ALTER TABLE `fa_kuerp_assemble` ADD COLUMN `produce_date` bigint(16) DEFAULT NULL COMMENT '生产日期' AFTER `purchase_time`;
