-- ============================================================
-- 外协加工单出库/入库审核流程改造 (对齐组装单)
-- 1. fa_kuerp_process 增加 入库成品审核 字段
-- 2. fa_kuerp_process_info 增加 out_number 已出库数量
-- 3. 新建 fa_kuerp_process_log 操作记录表
-- 4. 历史数据修复: 已审核单据原材料视为已全部出库
-- 5. 权限规则: 入库审核/实际出入库/保存实际出入库; 移除旧出库规则
-- 执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_process_outbound_flow.sql
-- ============================================================

-- 1. 主表: 入库成品审核状态
ALTER TABLE `fa_kuerp_process`
    ADD COLUMN `inbound_examine_status` enum('0','1') DEFAULT '0' COMMENT '入库成品审核状态:0=未审核,1=已审核' AFTER `outbound_time`,
    ADD COLUMN `inbound_examine_id` int(11) DEFAULT NULL COMMENT '入库成品审核人' AFTER `inbound_examine_status`,
    ADD COLUMN `inbound_examine_time` bigint(16) DEFAULT NULL COMMENT '入库成品审核时间' AFTER `inbound_examine_id`;

-- 2. 明细表: 已出库数量 (仅type=1 原材料)
ALTER TABLE `fa_kuerp_process_info`
    ADD COLUMN `out_number` double(15,4) DEFAULT '0.0000' COMMENT '已出库数量(仅type=1)' AFTER `number`;

-- 3. 操作记录表
CREATE TABLE IF NOT EXISTS `fa_kuerp_process_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `process_id` int(11) NOT NULL COMMENT '加工单ID (kuerp_process.id)',
    `action` varchar(50) NOT NULL COMMENT '操作类型',
    `note` varchar(500) DEFAULT '' COMMENT '备注(含本次出入库明细)',
    `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '操作人ID',
    `createtime` int(11) NOT NULL COMMENT '操作时间',
    PRIMARY KEY (`id`),
    KEY `process_id` (`process_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='外协加工单操作记录';

-- 4. 历史数据: 已审核单据的原材料在审核时已实际出库(自动审核模式扣库存/旧出库按钮扣库存), 统一标记为已全部出库
UPDATE `fa_kuerp_process` SET `outbound_status` = 2, `outbound_time` = COALESCE(`outbound_time`, `examine_time`) WHERE `examine_status` = '1';
UPDATE `fa_kuerp_process_info` SET `out_number` = `number` WHERE `type` = '1'
    AND `process_code` IN (SELECT `process_code` FROM `fa_kuerp_process` WHERE `examine_status` = '1');

-- 5. 权限规则: 新增 (pid=外协加工单菜单 601)
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process/examineinbound', '入库成品审核/反审核', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', 601
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process/examineinbound');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process/outboundEdit', '实际出库/入库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', 601
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process/outboundEdit');
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process/saveOutbound', '保存实际出库/入库', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', 601
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process/saveOutbound');

-- 5.2 移除旧的一次性出库/取消出库规则 (功能已被"实际出库/入库"替代)
DELETE FROM `fa_auth_rule` WHERE `name` IN ('kuerp/process/outbound', 'kuerp/process/cancelOutbound');

-- 6. 批次预留表增加外协加工明细关联列 (对齐 assemble_id/split_id 模式; 修复 add/edit/del 写入报错)
ALTER TABLE `fa_kuerp_goods_batch_info`
    ADD COLUMN `process_info_id` int(11) DEFAULT NULL COMMENT '外协加工单明细ID (kuerp_process_info.id)' AFTER `split_id`;

-- 7. 移除"外协入库"(成品入库并入"实际出库/入库"弹窗, 加工费结算同弹窗内完成)
DELETE FROM `fa_auth_rule` WHERE `name` IN ('kuerp/process/processIn', 'kuerp/process/getinfo', 'kuerp/process/getInfo');

