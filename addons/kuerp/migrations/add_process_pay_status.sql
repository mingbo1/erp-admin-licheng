-- 外协加工单：增加付款相关字段，支持付款单以加工单为源单进行核销
ALTER TABLE fa_kuerp_process ADD COLUMN pay_money DECIMAL(15,2) DEFAULT 0.00 COMMENT '已付加工费' AFTER total_process_fee;
ALTER TABLE fa_kuerp_process ADD COLUMN pay_status TINYINT(1) DEFAULT 0 COMMENT '付款状态:0=未付,1=已付,2=部分付款' AFTER pay_money;
