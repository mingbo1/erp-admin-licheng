-- ============================================
-- 新增 BOM 导入权限节点：kuerp/goods_bom/import
-- 背景: BOM模板列表新增“导入”功能，工具栏按钮与导入页面
--       通过权限节点 kuerp/goods_bom/import 控制显示与访问。
-- 说明: 该节点已同步写入 addons/kuerp/Kuerp.php 菜单定义，
--       升级插件时 Menu::upgrade 会自动同步；
--       本 SQL 用于无需升级插件时直接使权限节点生效。
-- 执行方式: 在MySQL中执行以下SQL
-- ============================================

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `weigh`, `status`, `createtime`, `updatetime`)
SELECT 'file', `id`, 'kuerp/goods_bom/import', '导入', 'fa fa-circle-o', 0, 0, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `fa_auth_rule`
WHERE `name` = 'kuerp/goods_bom' AND `ismenu` = 1
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
