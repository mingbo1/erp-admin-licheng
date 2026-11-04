<?php

namespace app\admin\library\kuerp;

use think\Db;

/**
 * 收发存汇总（月度快照）
 *
 * 口径按 资料/财务功能/收发存汇总表.xlsx：
 *   期初数量/单价/金额 = 上一期期末（跨月链条）
 *   本期入库数量      = 采购 + 销售退回 + 盘盈 + 其他入库 + 组装单入库 + 拆卸单入库 + 加工单入库
 *   本期入库金额      = 上述单据的成本合计 + 成本调整单金额
 *   本期入库单价      = 本期入库金额 / 本期入库数量
 *   本期出库数量      = 销售 + 采购退回 + 盘亏 + 其他出库 + 组装单出库 + 拆卸单出库 + 加工单出库
 *   本期出库金额      = 上述单据记录的实际成本合计
 *   本期出库单价      = (期初金额 + 本期入库金额) / (期初数量 + 本期入库数量)   ← 移动平均单价
 *   期末数量          = 期初 + 入库 − 出库
 *   期末金额          = 期初金额 + 入库金额 − 出库金额
 *   期末单价          = 期末金额 / 期末数量
 *
 * 关键约定：
 *   1. 调拨单(6) 是仓间移动，快照里**单独存**在 transfer_* 列：
 *      「全部仓库」合并时不计入本期收发（一进一出正好抵消），单仓库查看时才计入。
 *   2. 期末余额用「当前库存倒推」锚定：期末 = 商品库存余额表当前库存 − 该期结束后发生的收发，
 *      这样最新一期期末与「商品库存余额表」永远一致；首期期初的差额落在启用期初上。
 *   3. 未满月份（月份还没结束）只算期初/入库/出库，期末三列写 NULL（页面显示空白）。
 *
 * 快照粒度：商品 + 仓库（一行一个组合），页面「全部仓库」时按商品合并。
 */
class StockSummary
{

    /** 调拨单：仓间移动，双向 */
    const TYPE_TRANSFER = '6';

    /** 成本调整单：只改金额不改数量 */
    const TYPE_ADJUST = '7';

    /**
     * 入库单据类型（不含调拨/成本调整）
     * 2=普通采购 5=销售退回 8=盘盈 9=其他入库 12=组装单入库 14=拆卸单入库 17=加工单入库
     */
    public static function inTypes()
    {
        return ['2', '5', '8', '9', '12', '14', '17'];
    }

    /**
     * 出库单据类型（不含调拨）
     * 3=采购退回 4=普通销售 10=盘亏 11=其他出库 13=组装单出库 15=拆卸单出库 16=加工单出库
     */
    public static function outTypes()
    {
        return ['3', '4', '10', '11', '13', '15', '16'];
    }

    /**
     * 月份区间 [开始, 结束) 时间戳
     *
     * @param string $period YYYY-MM
     * @return array [int $start, int $end]
     */
    public static function periodRange($period)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \Exception('汇总月份格式错误，应为 YYYY-MM');
        }
        $start = strtotime($period . '-01 00:00:00');
        $end = strtotime(date('Y-m-d 00:00:00', strtotime('+1 month', $start)));
        return [$start, $end];
    }

    /**
     * 月份是否已结束（结束后才能算期末，未满月份期末留空）
     *
     * @param string $period
     * @return bool
     */
    public static function isComplete($period)
    {
        list(, $end) = self::periodRange($period);
        return $end <= time();
    }

    /**
     * 生成指定月份的快照
     *
     * @param string $period YYYY-MM
     * @param bool   $force  已存在时是否覆盖
     * @return array ['period'=>..., 'rows'=>int, 'skipped'=>bool, 'complete'=>bool]
     */
    public static function build($period, $force = true)
    {
        list($start, $end) = self::periodRange($period);
        $complete = $end <= time();

        $exists = Db::name('kuerp_stock_summary')->where(['period' => $period])->count();
        if ($exists && !$force) {
            return ['period' => $period, 'rows' => $exists, 'skipped' => true, 'complete' => $complete];
        }

        // --- 1. 本期收发（按 商品+仓库 分组） ----------------------------------
        $typeIn = implode("','", self::inTypes());
        $typeOut = implode("','", self::outTypes());
        $sumFields = [
            "SUM(CASE WHEN business_type IN ('{$typeIn}') THEN IFNULL(enter_basics,0) ELSE 0 END) AS in_qty",
            "SUM(CASE WHEN business_type IN ('{$typeIn}') THEN IFNULL(enter_cost,0) ELSE 0 END)"
            . " + SUM(CASE WHEN business_type='" . self::TYPE_ADJUST . "' THEN IFNULL(enter_cost,0) ELSE 0 END) AS in_money",
            "SUM(CASE WHEN business_type IN ('{$typeOut}') THEN IFNULL(out_basics,0) ELSE 0 END) AS out_qty",
            "SUM(CASE WHEN business_type IN ('{$typeOut}') THEN IFNULL(out_cost,0) ELSE 0 END) AS out_money",
            "SUM(CASE WHEN business_type='" . self::TYPE_TRANSFER . "' THEN IFNULL(enter_basics,0) ELSE 0 END) AS tin_qty",
            "SUM(CASE WHEN business_type='" . self::TYPE_TRANSFER . "' THEN IFNULL(enter_cost,0) ELSE 0 END) AS tin_money",
            "SUM(CASE WHEN business_type='" . self::TYPE_TRANSFER . "' THEN IFNULL(out_basics,0) ELSE 0 END) AS tout_qty",
            "SUM(CASE WHEN business_type='" . self::TYPE_TRANSFER . "' THEN IFNULL(out_cost,0) ELSE 0 END) AS tout_money",
        ];
        $periodRows = Db::name('kuerp_goods_log')
            ->where('business_type', '<>', '1')
            ->where('purchase_time', '>=', $start)
            ->where('purchase_time', '<', $end)
            ->field('goods_id,stores_id,' . implode(',', $sumFields))
            ->group('goods_id,stores_id')
            ->select();
        $periodMap = [];
        foreach ($periodRows as $row) {
            $periodMap[self::key($row['goods_id'], $row['stores_id'])] = $row;
        }

        // --- 2. 期末锚定：当前库存 − 该期结束后发生的收发 ------------------------
        $afterRows = Db::name('kuerp_goods_log')
            ->where('business_type', '<>', '1')
            ->where('purchase_time', '>=', $end)
            ->field('goods_id,stores_id,'
                . 'SUM(IFNULL(enter_basics,0)-IFNULL(out_basics,0)) AS dq,'
                . 'SUM(IFNULL(enter_cost,0)-IFNULL(out_cost,0)) AS dm')
            ->group('goods_id,stores_id')
            ->select();
        $afterMap = [];
        foreach ($afterRows as $row) {
            $afterMap[self::key($row['goods_id'], $row['stores_id'])] = $row;
        }

        $stockRows = Db::name('kuerp_goods_stock')
            ->where('deletetime', 'null')
            ->field('goods_id,stores_id,SUM(stock) AS q,SUM(cost_money) AS m')
            ->group('goods_id,stores_id')
            ->select();
        $stockMap = [];
        foreach ($stockRows as $row) {
            $stockMap[self::key($row['goods_id'], $row['stores_id'])] = $row;
        }

        // --- 3. 汇总键集合 ------------------------------------------------------
        $keys = [];
        foreach ([$periodMap, $afterMap, $stockMap] as $map) {
            foreach ($map as $k => $v) {
                $keys[$k] = true;
            }
        }
        if (!$keys) {
            Db::name('kuerp_stock_summary')->where(['period' => $period])->delete();
            return ['period' => $period, 'rows' => 0, 'skipped' => false, 'complete' => $complete];
        }

        // --- 4. 商品 / 仓库 / 单位档案 ------------------------------------------
        $goodsIds = [];
        $storesIds = [];
        foreach (array_keys($keys) as $k) {
            list($goodsId, $storesId) = explode('-', $k);
            $goodsIds[$goodsId] = (int)$goodsId;
            $storesIds[$storesId] = (int)$storesId;
        }
        $goodsMap = [];
        foreach (Db::name('kuerp_goods')
                     ->where('id', 'in', array_values($goodsIds))
                     ->field('id,goods_code,goods_name,goods_specification_id,unit_id')
                     ->select() as $goods) {
            $goodsMap[$goods['id']] = $goods;
        }
        $unitMap = Db::name('kuerp_unit')->column('name', 'id');
        $storesMap = Db::name('kuerp_stores')->column('name', 'id');

        // --- 5. 逐行算数 --------------------------------------------------------
        $now = time();
        $insert = [];
        foreach (array_keys($keys) as $k) {
            list($goodsId, $storesId) = array_map('intval', explode('-', $k));
            $p = isset($periodMap[$k]) ? $periodMap[$k] : [];
            $a = isset($afterMap[$k]) ? $afterMap[$k] : [];
            $s = isset($stockMap[$k]) ? $stockMap[$k] : [];

            $inQty   = round((float)(isset($p['in_qty']) ? $p['in_qty'] : 0), 4);
            $inMoney = round((float)(isset($p['in_money']) ? $p['in_money'] : 0), 4);
            $outQty   = round((float)(isset($p['out_qty']) ? $p['out_qty'] : 0), 4);
            $outMoney = round((float)(isset($p['out_money']) ? $p['out_money'] : 0), 4);
            $tinQty   = round((float)(isset($p['tin_qty']) ? $p['tin_qty'] : 0), 4);
            $tinMoney = round((float)(isset($p['tin_money']) ? $p['tin_money'] : 0), 4);
            $toutQty   = round((float)(isset($p['tout_qty']) ? $p['tout_qty'] : 0), 4);
            $toutMoney = round((float)(isset($p['tout_money']) ? $p['tout_money'] : 0), 4);

            // 期末（倒推锚定）：当前库存 − 本期结束后收发
            $endQty   = round((float)(isset($s['q']) ? $s['q'] : 0) - (float)(isset($a['dq']) ? $a['dq'] : 0), 4);
            $endMoney = round((float)(isset($s['m']) ? $s['m'] : 0) - (float)(isset($a['dm']) ? $a['dm'] : 0), 4);

            // 期初 = 期末 − 本期净收发（含调拨，调拨在单仓内是真实进出）
            $beginQty = round($endQty - ($inQty + $tinQty) + ($outQty + $toutQty), 4);
            $beginMoney = round($endMoney - ($inMoney + $tinMoney) + ($outMoney + $toutMoney), 4);

            // 全零行（无期初、无收发、无期末）不落库
            if (abs($beginQty) < 0.00005 && abs($beginMoney) < 0.005
                && abs($inQty) < 0.00005 && abs($inMoney) < 0.005
                && abs($outQty) < 0.00005 && abs($outMoney) < 0.005
                && abs($endQty) < 0.00005 && abs($endMoney) < 0.005) {
                continue;
            }

            $goods = isset($goodsMap[$goodsId]) ? $goodsMap[$goodsId] : null;
            $endPrice = $complete ? ($endQty > 0 ? round($endMoney / $endQty, 4) : 0) : null;

            $insert[] = [
                'period'            => $period,
                'goods_id'          => $goodsId,
                'stores_id'         => $storesId,
                'goods_code'        => $goods ? (string)$goods['goods_code'] : '',
                'goods_name'        => $goods ? (string)$goods['goods_name'] : '',
                'goods_spec'        => $goods ? (string)$goods['goods_specification_id'] : '',
                'goods_unit'        => ($goods && isset($unitMap[$goods['unit_id']])) ? (string)$unitMap[$goods['unit_id']] : '',
                'begin_qty'         => $beginQty,
                'begin_price'       => $beginQty > 0 ? round($beginMoney / $beginQty, 4) : 0,
                'begin_money'       => $beginMoney,
                'in_qty'            => $inQty,
                'in_price'          => $inQty > 0 ? round($inMoney / $inQty, 4) : 0,
                'in_money'          => $inMoney,
                'out_qty'           => $outQty,
                'out_price'         => ($beginQty + $inQty) > 0 ? round(($beginMoney + $inMoney) / ($beginQty + $inQty), 4) : 0,
                'out_money'         => $outMoney,
                'transfer_in_qty'   => $tinQty,
                'transfer_in_money' => $tinMoney,
                'transfer_out_qty'  => $toutQty,
                'transfer_out_money'=> $toutMoney,
                'end_qty'           => $complete ? $endQty : null,
                'end_price'         => $endPrice,
                'end_money'         => $complete ? $endMoney : null,
                'is_complete'       => $complete ? 1 : 0,
                'createtime'        => $now,
                'updatetime'        => $now,
            ];
        }

        // --- 6. 落库 ------------------------------------------------------------
        Db::startTrans();
        try {
            Db::name('kuerp_stock_summary')->where(['period' => $period])->delete();
            if ($insert) {
                foreach (array_chunk($insert, 200) as $chunk) {
                    Db::name('kuerp_stock_summary')->insertAll($chunk);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return ['period' => $period, 'rows' => count($insert), 'skipped' => false, 'complete' => $complete];
    }

    /**
     * 快照行合并（「全部仓库」时按商品合并）
     *
     * 合并时调拨一进一出抵消，不计入本期收发；单仓库时把调拨计入本期收发。
     *
     * @param array $rows  同一 商品+仓库 的快照行
     * @param bool  $merge true=按商品合并（全部仓库）
     * @return array
     */
    public static function mergeRows($rows, $merge)
    {
        $result = [
            'begin_qty' => 0, 'begin_money' => 0,
            'in_qty' => 0, 'in_money' => 0,
            'out_qty' => 0, 'out_money' => 0,
            'end_qty' => null, 'end_money' => null,
        ];
        $complete = true;
        foreach ($rows as $row) {
            $transferInQty = $merge ? 0 : (float)$row['transfer_in_qty'];
            $transferInMoney = $merge ? 0 : (float)$row['transfer_in_money'];
            $transferOutQty = $merge ? 0 : (float)$row['transfer_out_qty'];
            $transferOutMoney = $merge ? 0 : (float)$row['transfer_out_money'];

            $result['begin_qty'] += (float)$row['begin_qty'];
            $result['begin_money'] += (float)$row['begin_money'];
            $result['in_qty'] += (float)$row['in_qty'] + $transferInQty;
            $result['in_money'] += (float)$row['in_money'] + $transferInMoney;
            $result['out_qty'] += (float)$row['out_qty'] + $transferOutQty;
            $result['out_money'] += (float)$row['out_money'] + $transferOutMoney;

            if ($row['is_complete'] == 1) {
                $result['end_qty'] = (float)$result['end_qty'] + (float)$row['end_qty'];
                $result['end_money'] = (float)$result['end_money'] + (float)$row['end_money'];
            } else {
                $complete = false;
            }
        }
        foreach ($result as $k => $v) {
            $result[$k] = round((float)$v, 4);
        }
        if (!$complete) {
            $result['end_qty'] = null;
            $result['end_money'] = null;
        }
        return $result;
    }

    /**
     * 可选月份（启用日期 → 当前月，加上已有快照月份），倒序
     *
     * @return array
     */
    public static function periods()
    {
        $list = [];
        $enableTime = Db::name('kuerp_sets')->where(['name' => 'enable_time'])->value('value');
        $startMonth = $enableTime ? date('Y-m', strtotime($enableTime)) : date('Y-m', strtotime('-11 month'));
        $currentMonth = date('Y-m');
        $cursor = strtotime($startMonth . '-01');
        $limit = 0;
        while ($cursor !== false && $limit < 240) {
            $month = date('Y-m', $cursor);
            $list[] = $month;
            if ($month >= $currentMonth) {
                break;
            }
            $cursor = strtotime('+1 month', $cursor);
            $limit++;
        }
        foreach (Db::name('kuerp_stock_summary')->distinct(true)->column('period') as $month) {
            $list[] = $month;
        }
        $list = array_values(array_unique($list));
        rsort($list);
        return $list;
    }

    /**
     * 上一个月（每月 1 日 0 点定时任务算的就是这个月）
     *
     * @return string YYYY-MM
     */
    public static function defaultPeriod()
    {
        return date('Y-m', strtotime('-1 month', strtotime(date('Y-m-01'))));
    }

    /**
     * 组合键
     */
    protected static function key($goodsId, $storesId)
    {
        return (int)$goodsId . '-' . (int)$storesId;
    }
}
