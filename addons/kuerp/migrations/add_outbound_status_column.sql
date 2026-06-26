-- 外协加工单: 审核与出库分离，新增出库状态字段
ALTER TABLE fa_kuerp_process ADD COLUMN outbound_status TINYINT(1) DEFAULT 0 COMMENT '出库状态:0=未出库,1=已出库' AFTER examine_status;
ALTER TABLE fa_kuerp_process ADD COLUMN outbound_time INT(10) DEFAULT NULL COMMENT '出库时间' AFTER outbound_status;
