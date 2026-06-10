-- 外协加工单 建表语句
-- 执行方式: mysql -uroot -proot erp_licheng < this_file.sql

-- 1. 外协加工单主表
CREATE TABLE IF NOT EXISTS `fa_kuerp_process` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `process_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `process_time` bigint(16) DEFAULT NULL COMMENT '单据日期',
  `supplyer_code` varchar(50) DEFAULT NULL COMMENT '加工商供应商编号',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '出库总数量',
  `in_number` double(15,4) DEFAULT '0.0000' COMMENT '已入库总数量',
  `total_process_fee` double(15,4) DEFAULT '0.0000' COMMENT '累计加工费',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL COMMENT '审核时间',
  `in_status` enum('0','1','2') DEFAULT '0' COMMENT '入库状态:0=未入库,1=部分入库,2=全部入库',
  `in_time` bigint(16) DEFAULT NULL COMMENT '最后入库时间',
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='外协加工单';

-- 2. 外协加工单明细
CREATE TABLE IF NOT EXISTS `fa_kuerp_process_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `process_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `process_time` bigint(16) DEFAULT NULL COMMENT '单据日期',
  `type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '类型:1=出库原材料,2=入库成品',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(50) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) NOT NULL DEFAULT '0' COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `in_number` double(15,4) DEFAULT '0.0000' COMMENT '已入库数量(仅type=2)',
  `period` text COMMENT '序列号',
  `basic_number` double(15,4) DEFAULT '0.0000' COMMENT '基本数量',
  `unit_cost` double DEFAULT NULL COMMENT '单位成本(type=1:创建时自动带出库存成本; type=2:入库时手动输入)',
  `cost_money` double DEFAULT NULL COMMENT '成本金额(type=1:=unit_cost*basic_number; type=2:=unit_cost*in_number)',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `batch` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '过期时间',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='外协加工单详情';

-- 3. 外协加工费用记录
CREATE TABLE IF NOT EXISTS `fa_kuerp_process_fee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `process_code` varchar(50) DEFAULT NULL COMMENT '加工单编号',
  `process_id` int(11) DEFAULT NULL COMMENT '加工单ID',
  `supplyer_code` varchar(50) DEFAULT NULL COMMENT '加工商(供应商)',
  `fee_time` bigint(16) DEFAULT NULL COMMENT '费用日期',
  `fee_money` double(15,4) DEFAULT '0.0000' COMMENT '加工费金额',
  `account_id` int(11) DEFAULT NULL COMMENT '结算账户',
  `expend_type_id` int(11) DEFAULT NULL COMMENT '支出类别',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='外协加工费用';

-- 4. 编号规则
INSERT INTO `fa_kuerp_prefix` (`type`, `prefix`, `dbname`, `field`, `start`, `strlen`, `year`, `month`, `day`)
VALUES (28, 'WXJG', 'kuerp_process', 'process_code', 1, 3, 1, 1, 1);
