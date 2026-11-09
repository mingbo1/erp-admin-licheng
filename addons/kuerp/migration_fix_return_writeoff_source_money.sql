-- =====================================================================
-- 销货退货单：核销源单「已核销金额」写成 0（1.6.45，2026-11-09）
--
-- 问题：
--   GoodsSalesExchange::examine() 写 fa_kuerp_writeoff_source 时用的是
--   `money = -pay_money`，而退货单的 pay_money（表单隐藏域，从原销货单带过来）恒为 0，
--   真正的已退款金额是 retreat_money。结果 retired 源单出现
--       price - money <> write_off
--   （例：XHTH20261108001 退货 113、已退 100、还差 13 →
--         price=-113, money=0, write_off=-13，应当 money=-100）。
--
--   表面现象（销货对账 → 去收款 / 收款单「选择源单」）：
--     「已核销金额」列全部显示 0，「未核销金额」却是按真实退款算的，
--     于是 单据金额 - 已核销 ≠ 未核销，合计行也对不上
--     （KHDZ20261109001：单据金额合计 1270、已核销 0、未核销 1550）。
--
-- 代码侧已同步修复：
--   ① GoodsSalesExchange::examine() 的 money 改为 -retreat_money；
--   ② Collection::buildReconSources() 无核销记录时「已核销」回落取源单 money（原来写死 0）。
--
-- 本脚本把历史数据按 price - write_off 纠正，可重复执行。
-- 已有 kuerp_writeoff_log 的源单不处理（真实已核销金额以日志为准）。
-- =====================================================================

UPDATE `fa_kuerp_writeoff_source` s
   SET s.`money` = ROUND(s.`price` - s.`write_off`, 4)
 WHERE s.`purchase_type` = '3'
   AND s.`type` = '2'
   AND ROUND(s.`price` - s.`money`, 4) <> s.`write_off`
   AND NOT EXISTS (
        SELECT 1 FROM `fa_kuerp_writeoff_log` l WHERE l.`source_id` = s.`id`
   );
