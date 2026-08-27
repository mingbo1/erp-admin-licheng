-- 商品表添加厂商、封装字段（均为文字输入，varchar 200）
ALTER TABLE `fa_kuerp_goods`
  ADD COLUMN `vendor_name` varchar(200) NOT NULL DEFAULT '' COMMENT '厂商' AFTER `note`,
  ADD COLUMN `packaging` varchar(200) NOT NULL DEFAULT '' COMMENT '封装' AFTER `vendor_name`;
