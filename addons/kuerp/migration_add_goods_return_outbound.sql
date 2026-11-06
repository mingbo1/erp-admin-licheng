-- ============================================================================
-- 采购退货单：仓库「退货出库」（2026-11-05）
--
-- 背景：
--   原来购货退货单【审核】时就直接扣减库存（库存/成本/收发明细/序列号/批次），
--   现改为「退货单审核只做账务，仓库在 采购单入库 → 退货出库 里实际出库时才扣库存」。
--
-- 本次改动：
--   1) fa_kuerp_goods_exchange_info 增加 out_number（累计退货出库数量，单据单位）
--   2) 新表 fa_kuerp_goods_return_outbound_log —— 每次退货出库一条操作记录
--   3) 权限规则 kuerp/goods_purchase_storage/returnoutbound（退货出库），
--      并增量授予所有已拥有 kuerp/goods_purchase_storage/index 的角色
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_return_outbound.sql
-- 可重复执行。
-- ============================================================================

DROP PROCEDURE IF EXISTS `kuerp_add_goods_return_outbound`;
DELIMITER $$
CREATE PROCEDURE `kuerp_add_goods_return_outbound`()
BEGIN
    -- -----------------------------------------------------------------------
    -- 1. 退货单明细：累计已退货出库数量（单据单位，与 retreat_number 同单位）
    -- -----------------------------------------------------------------------
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fa_kuerp_goods_exchange_info'
           AND COLUMN_NAME = 'out_number') = 0 THEN
        ALTER TABLE `fa_kuerp_goods_exchange_info`
            ADD COLUMN `out_number` double(15,4) NOT NULL DEFAULT '0.0000'
            COMMENT '累计退货出库数量(单据单位)' AFTER `retreat_number`;

        -- 历史数据：加列之前审核的退货单，库存已在「审核」时扣减过 → 视为已全部出库，
        -- 避免上线后重复扣减（新出的按钮）以及反审核时库存回不来。
        -- 只在本次加列时执行一次（可重复执行不会把「刚审核还没出库」的单据误标）。
        UPDATE `fa_kuerp_goods_exchange_info` i
          JOIN `fa_kuerp_goods_exchange` e ON e.`purchase_code` = i.`purchase_code`
           SET i.`out_number` = i.`retreat_number`
         WHERE e.`examine_status` = '1' AND i.`out_number` = 0;
    END IF;

    -- -----------------------------------------------------------------------
    -- 2. 退货出库操作记录
    -- -----------------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `fa_kuerp_goods_return_outbound_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `purchase_id` int(11) NOT NULL DEFAULT '0' COMMENT '采购单ID (kuerp_goods_purchase.id)',
      `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '采购单号',
      `exchange_codes` varchar(255) NOT NULL DEFAULT '' COMMENT '本次涉及的退货单号(逗号分隔)',
      `total_number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本次出库总数量(单据单位)',
      `total_basics` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本次出库总数量(基本单位)',
      `detail` text COMMENT '本次出库明细JSON',
      `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '操作人',
      `createtime` bigint(16) DEFAULT NULL COMMENT '操作时间',
      PRIMARY KEY (`id`),
      KEY `purchase_id` (`purchase_id`),
      KEY `purchase_code` (`purchase_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购退货出库操作记录';

    -- -----------------------------------------------------------------------
    -- 3. 权限规则：退货出库（pid = 采购单入库菜单）
    -- -----------------------------------------------------------------------
    IF (SELECT COUNT(*) FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/returnoutbound') = 0 THEN
        INSERT INTO `fa_auth_rule`
            (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
        SELECT
            '1', `id`, 'kuerp/goods_purchase_storage/returnoutbound', '退货出库', 'fa fa-sign-out', '', '', '', '0', NULL, '', 'thck', 'tuihuochuku',
            UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', 'normal'
        FROM `fa_auth_rule`
        WHERE `name` = 'kuerp/goods_purchase_storage'
        LIMIT 1;
    END IF;

    -- 增量授予：已有「采购单入库」列表权限的角色，自动获得退货出库权限（只加不删）
    UPDATE `fa_auth_group`
       SET `rules` = CONCAT(`rules`, ',', (
            SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/returnoutbound' LIMIT 1
       ))
     WHERE `status` = 'normal'
       AND `rules` <> '*'
       AND FIND_IN_SET(
            (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/index' LIMIT 1),
            `rules`
       ) > 0
       AND FIND_IN_SET(
            (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/goods_purchase_storage/returnoutbound' LIMIT 1),
            `rules`
       ) = 0;
END$$
DELIMITER ;

CALL `kuerp_add_goods_return_outbound`();
DROP PROCEDURE IF EXISTS `kuerp_add_goods_return_outbound`;
