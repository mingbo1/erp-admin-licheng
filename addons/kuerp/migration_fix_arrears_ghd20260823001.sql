-- =====================================================================
-- 修复 GHD20260823001 应付余额为 0 的问题
-- 根因：GoodsPurchaseOrder::generateBySupplier() 生成购货单时漏写 arrears 字段
-- 该单 real_money=72、pay_money=0、未付款，本次欠款(arrears)/应付余额应为 72
-- 需手动执行：
--   mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_fix_arrears_ghd20260823001.sql
-- =====================================================================

START TRANSACTION;

-- 1. 购货单主表：本次欠款补 72
UPDATE fa_kuerp_goods_purchase
SET arrears = 72, all_arrears = 72
WHERE id = 23
  AND purchase_code = 'GHD20260823001'
  AND arrears = 0
  AND real_money = 72;

-- 2. 应付账款明细：应付余额(receivable)补 72
UPDATE fa_kuerp_accounts_copewith
SET receivable = 72
WHERE purchase_code = 'GHD20260823001'
  AND business_type = '6'
  AND receivable = 0;

-- 3. 收付款记录：欠款(give_money)补 72
UPDATE fa_kuerp_payment_log
SET give_money = 72
WHERE bill_code = 'GHD20260823001'
  AND business_type = '1'
  AND give_money = 0;

-- 4. 核销源单：补充应付源单（与 GHD20260823002 的 writeoff_source 结构一致）
INSERT INTO fa_kuerp_writeoff_source
    (purchase_type, type, suppliers_code, customers_id, purchase_code, purchase_time, price, money, write_off, remark)
SELECT 4, 1, 'GYS001', NULL, 'GHD20260823001', 1787449084, 72, 0, 72, ''
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM fa_kuerp_writeoff_source
    WHERE purchase_code = 'GHD20260823001' AND purchase_type = 4 AND type = 1
);

-- 5. 供应商欠款：供应商01(id=5) need_give_account 10 → 82
UPDATE fa_kuerp_suppliers
SET need_give_account = need_give_account + 72
WHERE id = 5
  AND supplyer_code = 'GYS001'
  AND need_give_account = 10;

COMMIT;
