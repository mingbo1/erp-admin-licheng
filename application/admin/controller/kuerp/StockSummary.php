<?php

namespace app\admin\controller\kuerp;

use app\admin\library\kuerp\StockSummary as StockSummaryLib;
use app\common\controller\Backend;
use think\Db;

/**
 * 收发存汇总表（月度快照）
 *
 * 口径见 application/admin/library/kuerp/StockSummary.php 与 资料/财务功能/收发存汇总表.xlsx
 *
 * 每月 1 日 0 点由定时任务（php think kuerp:stock_summary）自动生成上月快照；
 * 未满月份（当月）按实时数据计算，期末三列留空。
 *
 * @icon fa fa-cubes
 */
class StockSummary extends Backend
{

    protected $noNeedRight = ['periodlist'];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);
    }

    /**
     * 列表
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        $period = trim((string)$this->request->get('period', ''));
        $storesId = (int)$this->request->get('stores_id', 0);

        if ($this->request->isAjax()) {
            if ($period === '') {
                $period = StockSummaryLib::defaultPeriod();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            // 该月份还没有快照（首次查看历史月 / 定时任务没跑）→ 现场补算一次。
            // 只补算到当月为止，未来月份不建。
            if (!Db::name('kuerp_stock_summary')->where(['period' => $period])->count()) {
                if (preg_match('/^\d{4}-\d{2}$/', $period) && $period <= date('Y-m')) {
                    try {
                        StockSummaryLib::build($period, true);
                    } catch (\Exception $e) {
                        // 补算失败不阻断页面，下面的查询会返回空并提示
                    }
                }
            }

            $build = function () use ($period, $where, $storesId) {
                $query = Db::name('kuerp_stock_summary')->where('period', $period);
                if ($storesId > 0) {
                    $query->where('stores_id', $storesId);
                }
                return $query->where($where);
            };

            // 「全部仓库」要按商品合并，合并后才能算分页与合计，所以整月取出来在 PHP 里聚合
            $rawRows = $build()->order('goods_code asc,stores_id asc')->select();
            $groups = [];
            foreach ($rawRows as $row) {
                $groupKey = $storesId > 0 ? $row['id'] : (int)$row['goods_id'];
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'goods_id'   => $row['goods_id'],
                        'goods_code' => $row['goods_code'],
                        'goods_name' => $row['goods_name'],
                        'goods_spec' => $row['goods_spec'],
                        'goods_unit' => $row['goods_unit'],
                        'rows'       => [],
                    ];
                }
                $groups[$groupKey]['rows'][] = $row;
            }

            $merge = $storesId <= 0;
            $list = [];
            foreach ($groups as $group) {
                $sum = StockSummaryLib::mergeRows($group['rows'], $merge);
                $list[] = array_merge([
                    'goods_id'   => $group['goods_id'],
                    'goods_code' => $group['goods_code'],
                    'goods_name' => $group['goods_name'],
                    'goods_spec' => $group['goods_spec'],
                    'goods_unit' => $group['goods_unit'],
                ], self::calcPrices($sum));
            }

            // 月份是否已结束以「月份」为准（空数据月份也按同一规则判断）
            try {
                $monthComplete = StockSummaryLib::isComplete($period);
            } catch (\Exception $e) {
                $monthComplete = true;
            }

            // 合计行按全部数据算（不受分页影响）
            $totals = ['begin_qty' => 0, 'begin_money' => 0, 'in_qty' => 0, 'in_money' => 0,
                'out_qty' => 0, 'out_money' => 0, 'end_qty' => 0, 'end_money' => 0];
            foreach ($list as $row) {
                foreach (['begin_qty', 'begin_money', 'in_qty', 'in_money', 'out_qty', 'out_money'] as $field) {
                    $totals[$field] += (float)$row[$field];
                }
                if ($row['end_qty'] !== null) {
                    $totals['end_qty'] += (float)$row['end_qty'];
                    $totals['end_money'] += (float)$row['end_money'];
                }
            }
            foreach ($totals as $k => $v) {
                $totals[$k] = round((float)$v, 4);
            }
            $totals = self::calcPrices($totals);
            if (!$monthComplete) {
                $totals['end_qty'] = null;
                $totals['end_money'] = null;
                $totals['end_price'] = null;
            }

            $total = count($list);

            if ($limit > 0) {
                $list = array_slice($list, $offset, $limit);
            }

            $numberDecimal = (int)Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
            $priceDecimal = (int)Db::name('kuerp_sets')->where(['name' => 'price_decimal'])->value('value');

            return json([
                'total'       => $total,
                'rows'        => $list,
                'total_row'   => $totals,
                'period'      => $period,
                'is_complete' => $monthComplete ? 1 : 0,
                'decimal'     => ['number' => $numberDecimal, 'price' => $priceDecimal],
            ]);
        }

        $periods = StockSummaryLib::periods();
        if ($period === '') {
            $period = StockSummaryLib::defaultPeriod();
        }
        $this->view->assign('period', $period);
        $this->view->assign('periods', $periods);
        $this->view->assign('storesList', Db::name('kuerp_stores')->where(['status' => '1'])->field('id,name')->order('id asc')->select());
        $this->view->assign('storesId', $storesId);
        return $this->view->fetch();
    }

    /**
     * 重新计算（POST period）
     */
    public function rebuild()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $period = trim((string)$this->request->post('period', ''));
        if ($period === '') {
            $period = StockSummaryLib::defaultPeriod();
        }
        try {
            $result = StockSummaryLib::build($period, true);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $tip = $result['complete'] ? '' : '（本月未结束，期末已留空）';
        $this->success('已重新计算 ' . $result['period'] . '，共 ' . $result['rows'] . ' 行' . $tip);
    }

    /**
     * 可选月份（AJAX）
     */
    public function periodlist()
    {
        $options = [];
        foreach (StockSummaryLib::periods() as $month) {
            $options[] = ['value' => $month, 'text' => $month];
        }
        return json(['code' => 1, 'data' => $options]);
    }

    /**
     * 导出 Excel（与页面同口径）
     */
    public function export()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $period = trim((string)$this->request->post('period', ''));
        $storesId = (int)$this->request->post('stores_id', 0);
        if ($period === '') {
            $period = StockSummaryLib::defaultPeriod();
        }
        if (!Db::name('kuerp_stock_summary')->where(['period' => $period])->count()) {
            $this->error('该月份暂无数据，请先点【重新计算】');
        }

        $query = Db::name('kuerp_stock_summary')->where('period', $period);
        if ($storesId > 0) {
            $query->where('stores_id', $storesId);
        }
        $rawRows = $query->order('goods_code asc,stores_id asc')->select();
        if (empty($rawRows)) {
            $this->error('该月份暂无数据');
        }

        $groups = [];
        foreach ($rawRows as $row) {
            $groupKey = $storesId > 0 ? $row['id'] : (int)$row['goods_id'];
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'goods_id'   => $row['goods_id'],
                    'goods_code' => $row['goods_code'],
                    'goods_name' => $row['goods_name'],
                    'goods_spec' => $row['goods_spec'],
                    'goods_unit' => $row['goods_unit'],
                    'rows'       => [],
                ];
            }
            $groups[$groupKey]['rows'][] = $row;
        }
        $merge = $storesId <= 0;
        $list = [];
        foreach ($groups as $group) {
            $list[] = array_merge([
                'goods_code' => $group['goods_code'],
                'goods_name' => $group['goods_name'],
                'goods_spec' => $group['goods_spec'],
                'goods_unit' => $group['goods_unit'],
            ], self::calcPrices(StockSummaryLib::mergeRows($group['rows'], $merge)));
        }

        $priceDecimal = (int)Db::name('kuerp_sets')->where(['name' => 'price_decimal'])->value('value');
        $numberDecimal = (int)Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('收发存汇总表');

        $headers = ['物料编码', '物料名称', '规格型号', '单位', '期初数量', '期初单价', '期初金额',
            '本期入库数量', '本期入库单价', '本期入库金额',
            '本期出库数量', '本期出库单价', '本期出库金额',
            '期末数量', '期末单价', '期末金额'];
        $colCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $storesName = '';
        if ($storesId > 0) {
            $storesName = '（' . Db::name('kuerp_stores')->where(['id' => $storesId])->value('name') . '）';
        }
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->setCellValue('A1', $period . ' 收发存汇总表' . $storesName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 2, $header);
        }
        $sheet->getStyle('A2:' . $lastCol . '2')->getFont()->setBold(true);
        $sheet->getStyle('A2:' . $lastCol . '2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $moneyFields = ['begin_price', 'begin_money', 'in_price', 'in_money', 'out_price', 'out_money', 'end_price', 'end_money'];
        $numberFields = ['begin_qty', 'in_qty', 'out_qty', 'end_qty'];
        $rowIndex = 3;
        $totals = ['begin_qty' => 0, 'begin_money' => 0, 'in_qty' => 0, 'in_money' => 0,
            'out_qty' => 0, 'out_money' => 0, 'end_qty' => 0, 'end_money' => 0];
        $endComplete = true;
        foreach ($list as $row) {
            $sheet->setCellValueExplicitByColumnAndRow(1, $rowIndex, $row['goods_code'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicitByColumnAndRow(2, $rowIndex, $row['goods_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicitByColumnAndRow(3, $rowIndex, $row['goods_spec'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicitByColumnAndRow(4, $rowIndex, $row['goods_unit'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $col = 5;
            foreach (['begin_qty', 'begin_price', 'begin_money', 'in_qty', 'in_price', 'in_money',
                         'out_qty', 'out_price', 'out_money', 'end_qty', 'end_price', 'end_money'] as $field) {
                $value = $row[$field];
                if ($value === null) {
                    // 未满月份期末留空
                    $sheet->setCellValueByColumnAndRow($col, $rowIndex, '');
                } else {
                    $decimal = in_array($field, $moneyFields, true) ? $priceDecimal : $numberDecimal;
                    $sheet->setCellValueByColumnAndRow($col, $rowIndex, round((float)$value, $decimal));
                }
                $col++;
            }
            foreach (['begin_qty', 'begin_money', 'in_qty', 'in_money', 'out_qty', 'out_money'] as $field) {
                $totals[$field] += (float)$row[$field];
            }
            if ($row['end_qty'] === null) {
                $endComplete = false;
            } else {
                $totals['end_qty'] += (float)$row['end_qty'];
                $totals['end_money'] += (float)$row['end_money'];
            }
            $rowIndex++;
        }

        // 合计行
        $sheet->setCellValueByColumnAndRow(1, $rowIndex, '合计');
        $sheet->mergeCells('A' . $rowIndex . ':D' . $rowIndex);
        $col = 5;
        foreach (['begin_qty', 'begin_price', 'begin_money', 'in_qty', 'in_price', 'in_money',
                     'out_qty', 'out_price', 'out_money', 'end_qty', 'end_price', 'end_money'] as $field) {
            if (in_array($field, ['begin_price', 'in_price', 'out_price', 'end_price'], true)) {
                $col++; // 单价合计无意义，留空
                continue;
            }
            if (in_array($field, ['end_qty', 'end_money'], true) && !$endComplete) {
                $sheet->setCellValueByColumnAndRow($col, $rowIndex, '');
                $col++;
                continue;
            }
            $decimal = in_array($field, $moneyFields, true) ? $priceDecimal : $numberDecimal;
            $sheet->setCellValueByColumnAndRow($col, $rowIndex, round($totals[$field], $decimal));
            $col++;
        }
        $sheet->getStyle('A' . $rowIndex . ':' . $lastCol . $rowIndex)->getFont()->setBold(true);

        // 字段说明（与页面表格底部说明一致）
        $noteRow = $rowIndex + 2;
        $notes = [
            '字段说明：',
            '期初数量 / 期初单价 / 期初金额 = 上一期期末数量 / 单价 / 金额',
            '本期入库数量 = 采购 + 销售退回 + 盘盈 + 其他入库 + 组装单入库 + 拆卸单入库 + 加工单入库',
            '本期入库单价 = 本期入库金额 / 本期入库数量',
            '本期入库金额 = 上述入库单据的成本合计 + 成本调整单调整金额',
            '本期出库数量 = 销售 + 采购退回 + 盘亏 + 其他出库 + 组装单出库 + 拆卸单出库 + 加工单出库',
            '本期出库单价 = (期初金额 + 本期入库金额) / (期初数量 + 本期入库数量)',
            '本期出库金额 = 上述出库单据记录的实际成本合计',
            '期末数量 = 期初数量 + 本期入库数量 − 本期出库数量',
            '期末单价 = 期末金额 / 期末数量',
            '期末金额 = 期初金额 + 本期入库金额 − 本期出库金额',
            '未满月份（当月未结束）期末三列留空，只给期初与本期收发数据；月份结束后点【重新计算】或等次月 1 日定时任务补齐。',
            '调拨单为仓间移动，「全部仓库」时一进一出相互抵消不计入本期收发；按单个仓库查看时计入该仓库的本期收发。',
        ];
        foreach ($notes as $i => $note) {
            $sheet->setCellValueByColumnAndRow(1, $noteRow + $i, $note);
            if ($i === 0) {
                $sheet->getStyleByColumnAndRow(1, $noteRow + $i)->getFont()->setBold(true);
            }
        }

        $widths = [16, 26, 18, 8, 12, 12, 14, 14, 14, 16, 14, 14, 16, 12, 12, 14];
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
        $sheet->freezePane('A3');

        $fileName = '收发存汇总表' . $period . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Content-Disposition: attachment;filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * 在合并后的数量金额上补算单价（合并行不能直接取某一仓库的单价）
     *
     * @param array $row
     * @return array
     */
    protected static function calcPrices($row)
    {
        $row['begin_price'] = (float)$row['begin_qty'] > 0 ? round((float)$row['begin_money'] / (float)$row['begin_qty'], 4) : 0;
        $row['in_price'] = (float)$row['in_qty'] > 0 ? round((float)$row['in_money'] / (float)$row['in_qty'], 4) : 0;
        $beginPlusInQty = (float)$row['begin_qty'] + (float)$row['in_qty'];
        $row['out_price'] = $beginPlusInQty > 0
            ? round(((float)$row['begin_money'] + (float)$row['in_money']) / $beginPlusInQty, 4)
            : 0;
        if (array_key_exists('end_qty', $row)) {
            $row['end_price'] = ($row['end_qty'] !== null && (float)$row['end_qty'] > 0)
                ? round((float)$row['end_money'] / (float)$row['end_qty'], 4)
                : null;
        }
        return $row;
    }
}
