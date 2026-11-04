--
-- 迁移：采购入库「操作记录」+ 原始单据挂到每次入库
-- 1) 新表 fa_kuerp_goods_purchase_storage_log —— 每次入库一条记录（本次入库总数量/明细/操作人/时间）
-- 2) fa_kuerp_enclosure_log.type 增加 '7' = 入库记录（order_id 存入库记录ID，purchase_code 存采购单号）
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_purchase_storage_log.sql
-- 可重复执行。
--

CREATE TABLE IF NOT EXISTS `fa_kuerp_goods_purchase_storage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL DEFAULT '0' COMMENT '采购单ID (kuerp_goods_purchase.id)',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '采购单号',
  `total_number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本次入库总数量',
  `detail` text COMMENT '本次入库明细JSON',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '操作人',
  `createtime` bigint(16) DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `purchase_code` (`purchase_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购单入库操作记录';

-- 原始单据关联类型增加「入库记录」（幂等：重复执行只是重写同样的定义）
ALTER TABLE `fa_kuerp_enclosure_log`
  MODIFY COLUMN `type` enum('1','2','3','4','5','6','7') DEFAULT NULL
  COMMENT '单据类型:1=购货订单,2=采购单,3=购货退货单,4=销货订单,5=销货单,6=销货退货单,7=入库记录';
