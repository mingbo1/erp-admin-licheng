-- ============================================
-- 组装单关联销货订单字段迁移
-- 执行方式: 在MySQL中执行以下SQL
-- ============================================

-- 组装单主表新增销货订单号
ALTER TABLE `fa_kuerp_assemble` ADD COLUMN `sales_order_id` varchar(50) DEFAULT '' COMMENT '销货订单号';

-- 组装单详情表新增销货订单号
ALTER TABLE `fa_kuerp_assemble_info` ADD COLUMN `sales_order_id` varchar(50) DEFAULT '' COMMENT '销货订单号';
