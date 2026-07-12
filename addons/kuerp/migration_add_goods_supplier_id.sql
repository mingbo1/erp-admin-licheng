-- 商品表添加供应商绑定字段
-- 非必须绑定，默认0表示未绑定供应商
ALTER TABLE `fa_kuerp_goods` ADD COLUMN `supplier_id` int(11) NOT NULL DEFAULT '0' COMMENT '供应商ID' AFTER `location_id`;
