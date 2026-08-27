-- =====================================================================
-- 修正 GHD20260823002 的 all_arrears（总欠款快照）
-- 根因：该单生成时漏算供应商已有欠款，all_arrears 只写了本次欠款 10
-- 正确口径：总欠款 = 本次欠款 + 供应商已有欠款
--   GHD20260823001 先审核（已有欠款=0）→ all_arrears=72（已正确，无需改）
--   GHD20260823002 后审核（已有欠款=72）→ all_arrears=10+72=82
-- 需手动执行：
--   mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_fix_all_arrears_ghd20260823002.sql
-- =====================================================================

START TRANSACTION;

UPDATE fa_kuerp_goods_purchase
SET all_arrears = 82
WHERE id = 24
  AND purchase_code = 'GHD20260823002'
  AND arrears = 10
  AND all_arrears = 10;

COMMIT;
