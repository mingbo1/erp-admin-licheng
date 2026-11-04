-- 组装单[一键生成采购预订单(计划)]按钮权限 (Assemble::setdata)
-- 执行: mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_assemble_setdata_auth_rule.sql
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `url`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', 222, 'kuerp/assemble/setdata', '一键生成采购预订单(计划)', 'fa fa-circle-o', '', '', '', 0, NULL, '', 'yjsc cgd', 'yijianshengchengcaigoudan', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'kuerp/assemble/setdata');

-- pid=222 为 kuerp/assemble(组装单) 菜单规则，部署到其他库时请先确认:
-- SELECT id FROM fa_auth_rule WHERE name='kuerp/assemble';
