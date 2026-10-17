-- ============================================================
-- 工序小时看板 (按天设置产量看板, 每小时一个时段)
-- 1. 新建 fa_kuerp_process_board 主表 (一天一条, 日汇总落库)
-- 2. 新建 fa_kuerp_process_board_info 明细表 (每小时一条)
-- 3. 权限规则: kuerp/process_board 菜单 + index/add/edit/del
-- 执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_process_board.sql
-- ============================================================

-- 1. 主表
CREATE TABLE IF NOT EXISTS `fa_kuerp_process_board` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `board_date` int(10) NOT NULL DEFAULT '0' COMMENT '看板日期(Unix时间戳,当天零点)',
    `total_target` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '目标合计',
    `first_pass_total` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '一次性合格产出',
    `rework_total` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '返工合格产出',
    `qualified_total` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '总合格产出',
    `first_pass_yield` double(15,6) NOT NULL DEFAULT '0.000000' COMMENT '一次性良率(0-1)',
    `final_yield` double(15,6) NOT NULL DEFAULT '0.000000' COMMENT '最终良率(0-1)',
    `first_pass_defect` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '一次不良数量',
    `rework_defect` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '返工后不良数量',
    `note` varchar(500) DEFAULT '' COMMENT '备注',
    `admin_id` int(11) DEFAULT NULL COMMENT '创建人',
    `createtime` int(10) DEFAULT NULL COMMENT '创建时间',
    `updatetime` int(10) DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_board_date` (`board_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工序小时看板';

-- 2. 明细表
CREATE TABLE IF NOT EXISTS `fa_kuerp_process_board_info` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `board_id` int(11) NOT NULL DEFAULT '0' COMMENT '看板ID (kuerp_process_board.id)',
    `time_range` varchar(50) DEFAULT '' COMMENT '时段(如 8:00-9:00)',
    `target` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '本时段目标',
    `cumulative_target` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '累计目标',
    `assemble_code` varchar(50) DEFAULT '' COMMENT '组装单号(kuerp_assemble.purchase_code, 可空)',
    `model` varchar(100) DEFAULT '' COMMENT '型号(手填)',
    `first_pass_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '一次合格数量',
    `cumulative_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '累计合格数量',
    `variance` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '差异=累计合格-累计目标(不足为负数)',
    `status` enum('OK','NG') NOT NULL DEFAULT 'OK' COMMENT '状态:OK=达标或超量,NG=未达标',
    `defect_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '不良数量',
    `rework_qty` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '返工合格数量',
    `remark` varchar(500) DEFAULT '' COMMENT '备注',
    `createtime` int(10) DEFAULT NULL COMMENT '创建时间',
    `updatetime` int(10) DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `board_id` (`board_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工序小时看板明细';

-- 3. 权限规则
-- 菜单挂在 数据面板(kuerp/datainfo) 组下, 执行前先确认其 id:
-- SELECT id FROM fa_auth_rule WHERE name='kuerp/datainfo';  -- 当前库为 86
INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process_board', '工序小时看板', 'fa fa-dashboard', '', '', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', 86
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process_board/index', '查看', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board/index');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process_board/add', '添加', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board/add');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process_board/edit', '编辑', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board/edit');

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process_board/del', '删除', 'fa fa-circle-o', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board/del');
