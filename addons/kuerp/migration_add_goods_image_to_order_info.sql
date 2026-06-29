-- 在销货订单明细表中添加商品图片字段
-- 用于小程序端订单列表/详情展示商品主图
ALTER TABLE `fa_kuerp_goods_sales_order_info`
ADD COLUMN `goods_image` varchar(255) DEFAULT '' COMMENT '商品主图' AFTER `goods_note`;
