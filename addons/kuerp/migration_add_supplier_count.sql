-- 采购订单多供应商改造：主表新增 supplier_count 字段
ALTER TABLE `fa_kuerp_goods_purchase_order`
ADD COLUMN `supplier_count` int(11) DEFAULT 0 COMMENT '涉及供应商数量' AFTER `supplyer_code`;
