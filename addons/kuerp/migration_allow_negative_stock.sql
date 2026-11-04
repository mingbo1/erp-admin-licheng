-- 销货预订单允许库存 0/负数下单；销售出库允许把仓库库存扣成负数
-- 原因：fa_kuerp_goods_stock.stock 原为 double(15,4) unsigned，写负数会被 MySQL 截断为 0
-- 业务流程：库存 0/负数也能下单并出库 → 仓库库存扣成负数 → 采购单/组装单/外协加工单入库后自动补平
ALTER TABLE `fa_kuerp_goods_stock`
  MODIFY COLUMN `stock` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '库存（允许负数：销售先出库，采购/组装/外协入库后补平）';
