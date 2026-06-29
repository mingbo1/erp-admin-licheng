-- 销货订单操作日志扩展
-- 添加来源单据类型字段，支持追溯下游单据操作

ALTER TABLE `fa_kuerp_goods_sales_order_log`
ADD COLUMN `source_type` varchar(30) DEFAULT '' COMMENT '来源单据类型: sales_order/sales/purchase_order/purchase/assemble' AFTER `note`,
ADD COLUMN `source_id` int(11) DEFAULT 0 COMMENT '来源单据ID' AFTER `source_type`;
