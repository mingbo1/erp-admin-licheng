<?php

namespace app\admin\controller\kuerp;

use app\admin\library\kuerp\ArAnalysis as ArAnalysisLib;
use app\common\controller\Backend;
use think\Db;

/**
 * 应收账款分析（月度快照）
 *
 * 口径见 application/admin/library/kuerp/ArAnalysis.php 与
 * 资料/财务功能/应收账款及考核.xlsx
 *
 * @icon fa fa-line-chart
 */
class ArAnalysis extends Backend
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
     * 列表（默认展示最近一次快照月份）
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        $period = trim((string)$this->request->get('period', ''));

        if ($this->request->isAjax()) {
            // 月份只从 URL 的 period 参数取（前端 table.reload 时改 url），
            // 不放进 where，避免 buildparams 与这里的条件叠加成 period='A' AND period='B'
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            if ($period === '') {
                // 默认只展示最新一期，避免历史月份混在一起
                $period = (string)Db::name('kuerp_ar_analysis')->max('period');
            }

            // 只展示「有账」的客户：本月账面应收余额 > 0（已结清 / 预付的 0 和负余额不显示）
            $build = function () use ($period, $where) {
                $query = Db::name('kuerp_ar_analysis');
                if ($period !== '') {
                    $query->where('period', $period);
                }
                $query->where($where)->where('end_money', '>', 0);
                return $query;
            };

            // ⚠️ TP5 不能 `clone $query` 后复用同一个 Query（会报 No data supplied for parameters），
            //    count / 列表 / 合计 各建一个 Query
            $total = $build()->count();
            // 先按应收余额倒序（财务最关心欠得多的），再落到用户点选的排序列
            $rows = $build()
                ->order('end_money desc, id asc')
                ->limit($offset, $limit)
                ->select();

            $totals = [
                'begin_money'      => 0,
                'sale_money'       => 0,
                'collection_money' => 0,
                'return_money'     => 0,
                'end_money'        => 0,
                'overdue_money'    => 0,
                'diff_money'       => 0,
            ];
            // 合计与列表同口径（只要「有账」的客户）
            $sum = $build()
                ->field('SUM(begin_money) AS begin_money,SUM(sale_money) AS sale_money,SUM(collection_money) AS collection_money,SUM(return_money) AS return_money,SUM(end_money) AS end_money,SUM(overdue_money) AS overdue_money,SUM(diff_money) AS diff_money')
                ->find();
            if ($sum) {
                foreach ($totals as $key => $value) {
                    $totals[$key] = round((float)$sum[$key], 4);
                }
            }
            $totals['collection_rate'] = $totals['sale_money'] > 0
                ? round($totals['collection_money'] / $totals['sale_money'], 4)
                : 0;

            return json([
                'total'  => $total,
                'rows'   => $rows,
                'period' => $period,
                'totals' => $totals,
            ]);
        }

        $this->view->assign('period', $period !== '' ? $period : ArAnalysisLib::defaultPeriod());
        $this->view->assign('periods', $this->periodOptions());
        return $this->view->fetch();
    }

    /**
     * 重新分析（POST period）
     */
    public function rebuild()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $period = trim((string)$this->request->post('period', ''));
        if ($period === '') {
            $period = ArAnalysisLib::defaultPeriod();
        }
        try {
            $result = ArAnalysisLib::build($period, true);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('已重新分析 ' . $result['period'] . '，共 ' . $result['rows'] . ' 个客户');
    }

    /**
     * 可选月份列表（已有快照 + 上个月）
     */
    public function periodlist()
    {
        return json(['code' => 1, 'data' => $this->periodOptions()]);
    }

    /**
     * 导出 Excel
     */
    public function export()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $period = trim((string)$this->request->post('period', ''));
        if ($period === '') {
            $period = (string)Db::name('kuerp_ar_analysis')->max('period');
        }
        if ($period === '') {
            $this->error('暂无分析数据，请先执行「重新分析」');
        }
        // 与页面同口径：只导出「有账」的客户（账面应收余额 > 0）
        $rows = Db::name('kuerp_ar_analysis')
            ->where('period', $period)
            ->where('end_money', '>', 0)
            ->order('end_money desc,id asc')
            ->select();
        if (empty($rows)) {
            $this->error('该月份暂无分析数据');
        }
        $priceDecimal = (int)Db::name('kuerp_sets')->where(['name' => 'price_decimal'])->value('value');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('应收账款分析');

        $headers = ['销售负责人', '客户名称', '客户信用账期', '期初应收账款（上月末余额）', '本月含税销售总额（新增）',
            '本月实际回款总额', '本月退货/红冲金额', '本月账面应收余额', '其中：逾期应收账款', '本月销售回款率',
            '对账差异金额', '差异原因备注'];
        $colCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->setCellValue('A1', $period . ' 应收账款分析');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 2, $header);
        }
        $sheet->getStyle('A2:' . $lastCol . '2')->getFont()->setBold(true);
        $sheet->getRowDimension(2)->setRowHeight(32);
        $sheet->getStyle('A2:' . $lastCol . '2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $rowIndex = 3;
        $totals = ['begin_money' => 0, 'sale_money' => 0, 'collection_money' => 0, 'return_money' => 0,
            'end_money' => 0, 'overdue_money' => 0, 'diff_money' => 0];
        foreach ($rows as $row) {
            $sheet->setCellValueExplicitByColumnAndRow(1, $rowIndex, $row['sale_user_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicitByColumnAndRow(2, $rowIndex, $row['customer_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueByColumnAndRow(3, $rowIndex, $row['credit_days'] > 0 ? $row['credit_days'] . '天' : '');
            $sheet->setCellValueByColumnAndRow(4, $rowIndex, round((float)$row['begin_money'], $priceDecimal));
            $sheet->setCellValueByColumnAndRow(5, $rowIndex, round((float)$row['sale_money'], $priceDecimal));
            $sheet->setCellValueByColumnAndRow(6, $rowIndex, round((float)$row['collection_money'], $priceDecimal));
            $sheet->setCellValueByColumnAndRow(7, $rowIndex, round((float)$row['return_money'], $priceDecimal));
            $sheet->setCellValueByColumnAndRow(8, $rowIndex, round((float)$row['end_money'], $priceDecimal));
            $sheet->setCellValueByColumnAndRow(9, $rowIndex, round((float)$row['overdue_money'], $priceDecimal));
            $rateCell = $sheet->getCellByColumnAndRow(10, $rowIndex);
            $rateCell->setValue(round((float)$row['collection_rate'], 4));
            $sheet->getStyleByColumnAndRow(10, $rowIndex)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->setCellValueByColumnAndRow(11, $rowIndex, round((float)$row['diff_money'], $priceDecimal));
            $sheet->setCellValueExplicitByColumnAndRow(12, $rowIndex, $row['diff_reason'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            foreach ($totals as $key => $value) {
                $totals[$key] += (float)$row[$key];
            }
            $rowIndex++;
        }

        // 合计行
        $sheet->setCellValueByColumnAndRow(1, $rowIndex, '合计');
        $sheet->mergeCells('A' . $rowIndex . ':C' . $rowIndex);
        $sheet->setCellValueByColumnAndRow(4, $rowIndex, round($totals['begin_money'], $priceDecimal));
        $sheet->setCellValueByColumnAndRow(5, $rowIndex, round($totals['sale_money'], $priceDecimal));
        $sheet->setCellValueByColumnAndRow(6, $rowIndex, round($totals['collection_money'], $priceDecimal));
        $sheet->setCellValueByColumnAndRow(7, $rowIndex, round($totals['return_money'], $priceDecimal));
        $sheet->setCellValueByColumnAndRow(8, $rowIndex, round($totals['end_money'], $priceDecimal));
        $sheet->setCellValueByColumnAndRow(9, $rowIndex, round($totals['overdue_money'], $priceDecimal));
        $rate = $totals['sale_money'] > 0 ? round($totals['collection_money'] / $totals['sale_money'], 4) : 0;
        $sheet->getCellByColumnAndRow(10, $rowIndex)->setValue($rate);
        $sheet->getStyleByColumnAndRow(10, $rowIndex)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->setCellValueByColumnAndRow(11, $rowIndex, round($totals['diff_money'], $priceDecimal));
        $sheet->getStyle('A' . $rowIndex . ':' . $lastCol . $rowIndex)->getFont()->setBold(true);

        // 列宽
        $widths = [14, 32, 14, 22, 22, 18, 18, 20, 20, 14, 16, 34];
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
        $sheet->freezePane('A3');

        $fileName = '应收账款分析' . $period . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Content-Disposition: attachment;filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * 月份下拉
     */
    protected function periodOptions()
    {
        $list = Db::name('kuerp_ar_analysis')->distinct(true)->column('period');
        $default = ArAnalysisLib::defaultPeriod();
        if (!in_array($default, $list)) {
            $list[] = $default;
        }
        rsort($list);
        $options = [];
        foreach ($list as $period) {
            $options[] = ['value' => $period, 'text' => $period];
        }
        return $options;
    }
}
