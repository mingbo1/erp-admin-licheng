-- ============================================
-- 库存锁定表 - 订单级零件锁定/解锁
-- ============================================
CREATE TABLE IF NOT EXISTS `fa_kuerp_goods_lock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL COMMENT '操作人',
  `sales_order_id` varchar(50) NOT NULL COMMENT '销货订单号',
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `goods_code` varchar(50) NOT NULL COMMENT '商品编号',
  `attribute_id` int(11) DEFAULT 0 COMMENT '属性ID',
  `stores_id` int(11) NOT NULL COMMENT '仓库ID',
  `lock_quantity` double(15,4) NOT NULL DEFAULT 0 COMMENT '锁定数量(基本单位)',
  `used_quantity` double(15,4) NOT NULL DEFAULT 0 COMMENT '已使用数量',
  `status` enum('0','1') DEFAULT '1' COMMENT '状态:0=已解锁,1=锁定中',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_goods_store` (`sales_order_id`, `goods_id`, `stores_id`, `attribute_id`),
  KEY `idx_goods_stores` (`goods_id`, `stores_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品库存锁定表';
