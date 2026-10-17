-- ============================================================
-- 工序小时看板: 新增"图表"独立页权限规则 kuerp/process_board/chart
-- (星空背景大屏: 当日汇总参数 + 柱状图(实际)/折线(目标)对比)
-- 幂等执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_process_board_chart_rule.sql
-- ============================================================

INSERT INTO `fa_auth_rule` (`type`, `status`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `createtime`, `updatetime`, `weigh`, `pid`)
SELECT 'file', 'normal', 'kuerp/process_board/chart', '图表', 'fa fa-bar-chart', '', '', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board') t)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/process_board/chart');
