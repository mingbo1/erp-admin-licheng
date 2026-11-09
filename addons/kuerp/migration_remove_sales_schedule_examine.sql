-- ============================================================================
-- 移除计划排期（kuerp/goods_sales_schedule）的「审核/反审核」权限规则（2026-11-09）
--
-- 计划排期列表的 [审核] / [反审核] 按钮已下线，后端对应方法
-- GoodsSalesSchedule::examine() 一并删除（审核改由 销货单 列表 kuerp/goods_sales 完成）。
-- 原「审核」按钮的位置改为 [预计生产完成时间] 批量改期（复用已有规则
-- kuerp/goods_sales_schedule/setexpecteddelivery，本迁移不新建规则）。
--
-- 本脚本：
--   1. 从各角色的 rules 串里摘掉该规则 id（避免删规则后留下悬空 id）
--   2. 删除 fa_auth_rule 中的 kuerp/goods_sales_schedule/examine
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_remove_sales_schedule_examine.sql
-- 可重复执行（规则已不存在时不做事）。
-- ============================================================================

DROP PROCEDURE IF EXISTS `kuerp_remove_schedule_examine_rule`;
DELIMITER $$
CREATE PROCEDURE `kuerp_remove_schedule_examine_rule`()
BEGIN
    DECLARE v_id INT DEFAULT 0;

    WHILE (SELECT COUNT(*) FROM `fa_auth_rule`
            WHERE `name` = 'kuerp/goods_sales_schedule/examine') > 0 DO
        SELECT MIN(`id`) INTO v_id FROM `fa_auth_rule`
         WHERE `name` = 'kuerp/goods_sales_schedule/examine';

        UPDATE `fa_auth_group`
           SET `rules` = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', COALESCE(`rules`, ''), ','), CONCAT(',', v_id, ','), ','))
         WHERE `rules` <> '*'
           AND FIND_IN_SET(v_id, `rules`);

        DELETE FROM `fa_auth_rule` WHERE `id` = v_id;
    END WHILE;
END$$
DELIMITER ;

CALL `kuerp_remove_schedule_examine_rule`();
DROP PROCEDURE `kuerp_remove_schedule_examine_rule`;
