-- 为外协加工单明细表添加单件加工费字段
ALTER TABLE fa_kuerp_process_info ADD COLUMN process_fee DECIMAL(15,4) DEFAULT 0.0000 COMMENT '单件加工费' AFTER cost_money;
