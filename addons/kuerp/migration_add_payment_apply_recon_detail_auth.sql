-- ============================================================================
-- 付款申请详情「查看对账单明细」按钮权限（2026-11-04）
--
-- 需求：付款申请详情 →「关联对账单」表格右侧新增按钮 [查看明细]，
--       打开该对账单的详情页（kuerp/purchase_recon/detail）。
--
-- 权限口径：按钮用 **采购对账 → 详情（kuerp/purchase_recon/detail）** 控制。
--   之所以复用这条规则而不是新建一条付款申请下的规则：
--   PurchaseRecon::detail 页面本身由 Backend 按 kuerp/purchase_recon/detail 鉴权，
--   若另建规则，只授权新规则的角色仍会「看得到按钮但打开页面 403」。
--   复用后按钮可见性与页面访问权完全一致。
--
-- 本文件做两件事（均可重复执行）：
--   ① 防御式确保 kuerp/purchase_recon/detail 规则存在；
--   ② 把该规则 **增量追加** 给已有「付款申请」菜单/详情权限的角色（只加不删，
--      不改动角色已有的其它权限；线上角色独有权限一律保留）。
--
-- 执行：mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_payment_apply_recon_detail_auth.sql
-- 执行后：后台「系统」→「清除缓存」，在线用户强刷一次。
-- ============================================================================

-- ① 确保「采购对账 → 详情」规则存在（采购对账菜单存在时才建，避免 pid 落空）
INSERT INTO `fa_auth_rule` (`type`,`status`,`name`,`title`,`icon`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`pid`)
SELECT 'file','normal','kuerp/purchase_recon/detail','详情','fa fa-circle-o','','','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'0',
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon') t)
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/detail')
  AND EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon');

-- ② 已有「付款申请」菜单(kuerp/purchase_payment_apply) 或「付款申请 → 详情」权限的角色，追加「采购对账 → 详情」
UPDATE `fa_auth_group`
   SET `rules` = CONCAT(IFNULL(`rules`,''), IF(`rules` IS NULL OR `rules`='', '', ','),
       (SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/detail') t))
 WHERE IFNULL(`rules`,'') <> '*'
   AND FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_recon/detail') t2), IFNULL(`rules`,'')) = 0
   AND (
        FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply') t3), IFNULL(`rules`,'')) > 0
     OR FIND_IN_SET((SELECT `id` FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='kuerp/purchase_payment_apply/detail') t4), IFNULL(`rules`,'')) > 0
   );

-- 完成。
