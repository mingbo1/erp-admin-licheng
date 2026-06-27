-- 给购货单明细表增加"已入库数量"字段，支持部分入库追踪
-- fa_kuerp_goods_purchase_order_info 已有 library_number(未入库数量) 不需要额外加字段
ALTER TABLE `fa_kuerp_goods_purchase_info`
ADD COLUMN `storage_number` double(15,4) NOT NULL DEFAULT 0 COMMENT '已入库数量(基本单位)'
AFTER `basic_nmber`;
