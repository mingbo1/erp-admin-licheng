--
-- 迁移：出库申请填写快递信息（送货单打印用）
-- 1. fa_kuerp_goods_outbound_apply 新增 express_data 字段
--    存 JSON 数组：[{"code":"SF","company":"顺丰速运","no":"SF123456"}, ...]
--    单次出库（一次申请）可填写多个快递公司 + 快递单号
-- 2. 销货订单列表的「发货」按钮已隐藏，发货信息改由申请出库时填写，送货单按申请单打印
-- 执行：
--   mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_outbound_express.sql
-- 可重复执行（列已存在时跳过）。
--

DROP PROCEDURE IF EXISTS `kuerp_add_outbound_express`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_outbound_express`()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_outbound_apply'
           AND COLUMN_NAME = 'express_data') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_outbound_apply`
            ADD COLUMN `express_data` text NULL COMMENT '快递信息JSON [{code,company,no}]（申请出库时填写,一次出库可多条）' AFTER `out_data`;
    END IF;
END$$
DELIMITER ;

CALL `kuerp_add_outbound_express`();
DROP PROCEDURE `kuerp_add_outbound_express`;
