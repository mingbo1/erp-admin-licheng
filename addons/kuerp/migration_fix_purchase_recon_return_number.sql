-- =====================================================================
-- 采购对账：购货退货单的冲减数量取错字段（1.6.41，2026-11-08）
--
-- 问题：
--   fa_kuerp_goods_exchange.number 是「原采购数量」（明细行 number 的合计，如 10×4=40），
--   真正的退货数量在 fa_kuerp_goods_exchange.retreat_number（如 4）。
--   PurchaseRecon 原先用 number 写入对账明细，导致退货单在采购对账里显示 -40、
--   对账单 total_number 被算成 0（43 - 43）。
--
-- 本脚本把已生成的对账明细与主表汇总按 retreat_number 重算，可重复执行。
-- 代码侧（PurchaseRecon.php）已同步修复，之后新建/编辑的对账单不再出现该问题。
-- =====================================================================

-- 1) 对账明细：退货单数量改为 −retreat_number
UPDATE `fa_kuerp_purchase_recon_info` i
  JOIN `fa_kuerp_goods_exchange` e ON e.`id` = i.`purchase_id`
   SET i.`number` = -1 * e.`retreat_number`
 WHERE i.`doc_type` = '2'
   AND i.`number` <> -1 * e.`retreat_number`;

-- 2) 对账单主表：按明细重算数量与金额合计
UPDATE `fa_kuerp_purchase_recon` r
  JOIN (
        SELECT `recon_id`, SUM(`number`) AS n, SUM(`real_money`) AS m
          FROM `fa_kuerp_purchase_recon_info`
         GROUP BY `recon_id`
       ) t ON t.`recon_id` = r.`id`
   SET r.`total_number` = ROUND(t.n, 4),
       r.`total_money`  = ROUND(t.m, 4)
 WHERE r.`total_number` <> ROUND(t.n, 4)
    OR r.`total_money`  <> ROUND(t.m, 4);
