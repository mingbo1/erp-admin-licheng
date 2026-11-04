<?php

namespace app\admin\library\traits;

use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use think\Db;

/**
 * 其他出库单 / 其他入库单 明细的 Excel 模板下载 + 导入解析
 *
 * 使用方（Warehouse / Warehousing）需定义：
 *   使用方覆盖 excelDocType() 返回 'out' 或 'in'：'out' 其他出库单（无单价列） / 'in' 其他入库单（含入库单价列）
 *
 * 导入只做解析与校验，不写库：前端拿到行数据后填进明细表格，再走单据原有的保存流程。
 */
trait WarehouseExcel
{
    /** 隐藏的下拉数据源表名（PHP 7.4 的 trait 不支持常量，故用属性） */
    protected $excelOptSheet = 'Lists';
    /** 模板预留的可填写行数（数据自第 2 行开始） */
    protected $excelTemplateRows = 500;
    /** 明细表名（下载与导入共用） */
    protected $excelSheetTitle = '出入库明细';

    /**
     * 单据类型：'out' = 其他出库单，'in' = 其他入库单
     * （trait 不能用属性默认值与使用方不同，故用方法，由使用方覆盖）
     */
    protected function excelDocType()
    {
        return 'out';
    }

    /**
     * 是否包含「入库单价」列（其他入库单才有，出库单在页面上单价恒为 0）
     */
    protected function excelWithPrice()
    {
        return $this->excelDocType() === 'in';
    }

    /**
     * 单据名称（用于模板标题、文件名与填写说明）
     */
    protected function excelDocTitle()
    {
        return $this->excelDocType() === 'in' ? '其他入库单' : '其他出库单';
    }

    /**
     * 模板列定义（下载与导入共用），alias 用于导入时容错匹配表头
     */
    protected function excelColumns()
    {
        $columns = [
            ['field' => 'goods_code', 'title' => '商品编号', 'alias' => ['商品编号', '商品编码', 'goods_code'], 'required' => true],
            ['field' => 'goods_name', 'title' => '商品名称', 'alias' => ['商品名称', '商品名', 'goods_name'], 'required' => false],
            ['field' => 'unit_name', 'title' => '商品单位', 'alias' => ['商品单位', '单位', 'unit'], 'required' => false],
            ['field' => 'store_name', 'title' => '仓库', 'alias' => ['仓库', '仓库名称', 'stores'], 'required' => false],
            ['field' => 'number', 'title' => '数量', 'alias' => ['数量', 'number'], 'required' => true],
        ];
        if ($this->excelWithPrice()) {
            $columns[] = ['field' => 'unit_price', 'title' => '入库单价', 'alias' => ['入库单价', '单价', '含税单价', 'unit_price'], 'required' => false];
        }
        $columns[] = ['field' => 'remark', 'title' => '备注', 'alias' => ['备注', 'remark'], 'required' => false];
        return $columns;
    }

    /**
     * 表头归一化：去掉空白与 * 必填标记、去掉结尾的 % 单位，便于容错匹配
     */
    protected function normalizeExcelHeader($title)
    {
        $title = (string)$title;
        $title = preg_replace('/[\s\*\x{3000}]+/u', '', $title);
        return rtrim($title, '%');
    }

    /**
     * 解析表头行，返回 内部字段 => 列号(从1开始)
     */
    protected function mapExcelColumns($sheet, $headerRow)
    {
        $byAlias = [];
        foreach ($this->excelColumns() as $col) {
            foreach (array_merge([$col['title']], $col['alias']) as $alias) {
                $byAlias[$this->normalizeExcelHeader($alias)] = $col['field'];
            }
        }
        $map    = [];
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($i = 1; $i <= $maxCol; $i++) {
            $head = $this->normalizeExcelHeader($sheet->getCellByColumnAndRow($i, $headerRow)->getValue());
            if ($head !== '' && isset($byAlias[$head]) && !isset($map[$byAlias[$head]])) {
                $map[$byAlias[$head]] = $i;
            }
        }
        return $map;
    }

    /**
     * Excel 单元格值转字符串：避免纯数字的商品编号被读成科学计数法或丢掉前导零
     */
    protected function excelCellString($value)
    {
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_float($value)) {
            return (floor($value) == $value)
                ? number_format($value, 0, '', '')
                : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        return trim((string)$value);
    }

    /**
     * 下载空白导入模板（商品编号 / 商品单位 / 仓库 为下拉选择）
     */
    public function template()
    {
        $columns = $this->excelColumns();

        $goodsList = Db::name('kuerp_goods')->where(['status' => '1'])
            ->field('goods_code,goods_name')->order('goods_code', 'ASC')->select();
        $storeList = Db::name('kuerp_stores')->where(['status' => '1'])
            ->field('id,name')->order('id', 'ASC')->select();
        $unitNames = [];
        foreach (Db::name('kuerp_unit')->field('id,name')->order('id', 'ASC')->select() as $unit) {
            $unitName = trim((string)$unit['name']);
            if ($unitName !== '' && !in_array($unitName, $unitNames, true)) {
                $unitNames[] = $unitName;
            }
        }

        $numberDecimal = (int)Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
        $priceDecimal  = (int)Db::name('kuerp_sets')->where(['name' => 'price_decimal'])->value('value');
        $numberFormat  = '0' . ($numberDecimal > 0 ? '.' . str_repeat('0', $numberDecimal) : '');
        $priceFormat   = '0' . ($priceDecimal > 0 ? '.' . str_repeat('0', $priceDecimal) : '');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('微软雅黑')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->excelSheetTitle);

        $letterByField = [];
        $fieldByLetter = [];
        foreach ($columns as $k => $col) {
            $letter = Coordinate::stringFromColumnIndex($k + 1);
            $letterByField[$col['field']] = $letter;
            $fieldByLetter[$letter]       = $col['field'];
        }
        $lastColLetter = Coordinate::stringFromColumnIndex(count($columns));

        // ---------- 隐藏的下拉数据源 ----------
        $opt = $spreadsheet->createSheet();
        $opt->setTitle($this->excelOptSheet);
        $opt->fromArray(['商品编号', '商品名称', '商品单位', '仓库'], null, 'A1');
        foreach ($goodsList as $k => $goods) {
            $opt->setCellValueExplicit('A' . ($k + 2), (string)$goods['goods_code'], DataType::TYPE_STRING);
            $opt->setCellValueExplicit('B' . ($k + 2), (string)$goods['goods_name'], DataType::TYPE_STRING);
        }
        foreach ($unitNames as $k => $unitName) {
            $opt->setCellValueExplicit('C' . ($k + 2), $unitName, DataType::TYPE_STRING);
        }
        foreach ($storeList as $k => $store) {
            $opt->setCellValueExplicit('D' . ($k + 2), (string)$store['name'], DataType::TYPE_STRING);
        }
        $opt->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // ---------- 明细表 ----------
        foreach ($columns as $col) {
            $sheet->setCellValue($letterByField[$col['field']] . '1', $col['title'] . ($col['required'] ? '*' : ''));
        }

        $firstDataRow = 2;
        $lastDataRow  = $firstDataRow + $this->excelTemplateRows - 1;

        $optRange = [
            'goods_code' => $this->excelOptSheet . '!$A$2:$A$' . (count($goodsList) + 1),
            'unit_name'  => $this->excelOptSheet . '!$C$2:$C$' . (count($unitNames) + 1),
            'store_name' => $this->excelOptSheet . '!$D$2:$D$' . (count($storeList) + 1),
        ];
        $optPrompt = [
            'goods_code' => ['商品编号', '请从下拉列表中选择商品编号；下拉数据取自系统商品资料'],
            'unit_name'  => ['商品单位', '请从下拉列表中选择；留空则按商品的默认单位'],
            'store_name' => ['仓库', '请从下拉列表中选择；留空则按商品的默认仓库'],
        ];
        foreach ($fieldByLetter as $letter => $field) {
            if (!isset($optRange[$field])) {
                continue;
            }
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setOperator('');
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorStyle(DataValidation::STYLE_WARNING);
            $validation->setErrorTitle('提示');
            $validation->setError('请从下拉列表中选择，或确认后继续。');
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle($optPrompt[$field][0]);
            $validation->setPrompt($optPrompt[$field][1]);
            $validation->setFormula1($optRange[$field]);
            $sheet->setDataValidation($letter . $firstDataRow . ':' . $letter . $lastDataRow, $validation);
        }

        // 数量 / 单价：数字校验与格式
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_DECIMAL);
        $validation->setOperator(DataValidation::OPERATOR_GREATERTHAN);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorStyle(DataValidation::STYLE_WARNING);
        $validation->setErrorTitle('提示');
        $validation->setError('请填写大于 0 的数字。');
        $validation->setFormula1('0');
        $numberLetter = $letterByField['number'];
        $sheet->setDataValidation($numberLetter . $firstDataRow . ':' . $numberLetter . $lastDataRow, $validation);
        $sheet->getStyle($numberLetter . $firstDataRow . ':' . $numberLetter . $lastDataRow)
            ->getNumberFormat()->setFormatCode($numberFormat);

        if ($this->excelWithPrice()) {
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_DECIMAL);
            $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
            $validation->setAllowBlank(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorStyle(DataValidation::STYLE_WARNING);
            $validation->setErrorTitle('提示');
            $validation->setError('请填写不小于 0 的数字。');
            $validation->setFormula1('0');
            $priceLetter = $letterByField['unit_price'];
            $sheet->setDataValidation($priceLetter . $firstDataRow . ':' . $priceLetter . $lastDataRow, $validation);
            $sheet->getStyle($priceLetter . $firstDataRow . ':' . $priceLetter . $lastDataRow)
                ->getNumberFormat()->setFormatCode($priceFormat);
        }

        // 商品名称列：公式自动带出（导入时以商品编号为准）
        $goodsRange = $this->excelOptSheet . '!$A$2:$B$' . (count($goodsList) + 1);
        for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
            $sheet->setCellValue(
                $letterByField['goods_name'] . $r,
                '=IFERROR(VLOOKUP($' . $letterByField['goods_code'] . $r . ',' . $goodsRange . ',2,0),"")'
            );
        }

        // 表头与边框样式
        $sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C8DBC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getStyle('A' . $firstDataRow . ':' . $lastColLetter . $lastDataRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        ]);

        $widths = [
            'goods_code' => 16, 'goods_name' => 34, 'unit_name' => 10, 'store_name' => 14,
            'number' => 12, 'unit_price' => 12, 'remark' => 26,
        ];
        foreach ($letterByField as $field => $letter) {
            $sheet->getColumnDimension($letter)->setWidth(isset($widths[$field]) ? $widths[$field] : 14);
        }
        $sheet->freezePane('A' . $firstDataRow);

        // ---------- 填写说明 ----------
        $docTitle = $this->excelDocTitle();
        $note = $spreadsheet->createSheet();
        $note->setTitle('填写说明');
        $noteRows = [
            $docTitle . '明细导入模板说明',
            '1、带 * 的列为必填：商品编号、数量。',
            '2、导入会【替换】' . $docTitle . '页面上当前的全部明细行，请先确认页面上的明细已备份。',
            '3、商品编号、商品单位、仓库三列均为下拉选择，下拉数据取自系统资料；',
            '     若下拉中没有需要的选项，请先在系统对应模块中新增资料，然后重新点击「下载模板」。',
            '4、商品名称列由公式自动带出，无需填写；导入时以商品编号为准。',
            '5、商品单位 / 仓库 留空时，分别按商品的默认单位、默认仓库处理；商品未设置默认仓库时必须填写仓库。',
            $this->excelWithPrice()
                ? '6、入库单价可留空，留空时按商品档案价格自动带出；单价按含税单价填写。'
                : '6、本单据不涉及单价，单价与金额由系统自动计算（为 0）。',
            '7、商品编号不存在、仓库名称不正确、数量为空或非数字时，导入会被拒绝并提示具体行号。',
            '8、启用了批次或序列号的商品，导入后需在页面上逐行选择批次 / 序列号后再保存。',
        ];
        foreach ($noteRows as $k => $line) {
            $note->setCellValue('A' . ($k + 1), $line);
        }
        $note->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $note->getColumnDimension('A')->setWidth(105);

        $spreadsheet->setActiveSheetIndex(0);

        // ---------- 输出下载 ----------
        $stamp = date('YmdHis');
        $title = $docTitle . '导入模板';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="warehouse_' . $this->excelDocType() . '_' . $stamp . '.xlsx";filename*=UTF-8\'\'' . rawurlencode($title . '_' . $stamp . '.xlsx'));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    /**
     * 解析导入的 Excel，返回明细行供前端填入表格（不写库）
     */
    public function parseimport()
    {
        $file = $this->request->file('file');
        if (!$file) {
            $this->error('请选择要导入的Excel文件');
        }
        $fileInfo  = $file->getInfo();
        $fileName  = isset($fileInfo['name']) ? $fileInfo['name'] : '';
        $tmpPath   = isset($fileInfo['tmp_name']) ? $fileInfo['tmp_name'] : '';
        if (!in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            $this->error('仅支持 .xlsx / .xls 格式的Excel文件');
        }
        if (!$tmpPath || !is_file($tmpPath)) {
            $this->error('文件上传失败，请重新选择文件');
        }
        if (filesize($tmpPath) > 10 * 1024 * 1024) {
            $this->error('文件过大，请确认使用的是系统下载的模板（不超过10M）');
        }

        try {
            $reader = IOFactory::createReaderForFile($tmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpPath);
        } catch (Exception $e) {
            $this->error('Excel文件解析失败，请确认文件未损坏：' . $e->getMessage());
        }

        $sheet = $spreadsheet->getSheetByName($this->excelSheetTitle);
        if (!$sheet) {
            $sheet = $spreadsheet->getSheet(0);
        }

        // 定位表头行（容错：允许表头前有标题行）
        $maxCol    = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $headerRow = 0;
        $scanTo    = min(10, $sheet->getHighestDataRow());
        for ($r = 1; $r <= $scanTo && !$headerRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                if ($this->normalizeExcelHeader($sheet->getCellByColumnAndRow($c, $r)->getValue()) === '商品编号') {
                    $headerRow = $r;
                    break;
                }
            }
        }
        if (!$headerRow) {
            $this->error('未识别到表头，请使用系统「下载模板」生成的模板填写后再导入');
        }

        $map = $this->mapExcelColumns($sheet, $headerRow);
        if (!isset($map['goods_code']) || !isset($map['number'])) {
            $this->error('模板缺少必填列（商品编号 / 数量），请使用系统「下载模板」生成的模板');
        }

        // ---------- 参照资料 ----------
        $goodsMap = [];
        foreach (Db::name('kuerp_goods')->where(['status' => '1'])
            ->field('id,goods_code,goods_name,unit_id,more_unit,manager_warehouse_id,batch,serial')->select() as $goods) {
            $goodsMap[$goods['goods_code']] = $goods;
        }
        $storeByName = [];
        $storeById   = [];
        foreach (Db::name('kuerp_stores')->where(['status' => '1'])->field('id,name')->select() as $store) {
            $storeByName[trim($store['name'])] = (int)$store['id'];
            $storeById[(int)$store['id']]      = $store['name'];
        }
        $unitByName = [];
        foreach (Db::name('kuerp_unit')->field('id,name')->select() as $unit) {
            $unitByName[trim($unit['name'])][] = (int)$unit['id'];
        }
        $convByMain = [];
        foreach (Db::name('kuerp_unit_conversion')->field('main_unit,vice_unit')->select() as $conv) {
            $convByMain[(int)$conv['main_unit']][] = (int)$conv['vice_unit'];
        }
        $batchOn  = (string)Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked') === '1';
        $serialOn = (string)Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked') === '1';

        $cellValue = function ($rowIdx, $field) use ($sheet, $map) {
            if (!isset($map[$field])) {
                return '';
            }
            return $sheet->getCellByColumnAndRow($map[$field], $rowIdx)->getValue();
        };
        $cellText = function ($rowIdx, $field) use ($cellValue) {
            return $this->excelCellString($cellValue($rowIdx, $field));
        };

        // ---------- 逐行解析 ----------
        $rows     = [];
        $errors   = [];
        $warnings = [];
        $lastRow  = $sheet->getHighestDataRow();

        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            $goodsCode = $cellText($r, 'goods_code');
            if ($goodsCode === '') {
                continue;   // 空行跳过
            }
            $goods = isset($goodsMap[$goodsCode]) ? $goodsMap[$goodsCode] : null;
            if (!$goods) {
                $errors[] = '第' . $r . '行：商品编号「' . $goodsCode . '」不存在';
                continue;
            }

            $numberRaw = $cellText($r, 'number');
            if ($numberRaw === '' || !is_numeric($numberRaw) || (float)$numberRaw <= 0) {
                $errors[] = '第' . $r . '行：数量必须为大于 0 的数字';
                continue;
            }

            // 仓库：留空则回退到商品的默认仓库
            $storeName = $cellText($r, 'store_name');
            if ($storeName !== '') {
                if (!isset($storeByName[$storeName])) {
                    $errors[] = '第' . $r . '行：仓库「' . $storeName . '」不存在，请使用下拉选项';
                    continue;
                }
                $storesId = $storeByName[$storeName];
            } else {
                $storesId = (int)$goods['manager_warehouse_id'];
                if (!$storesId || !isset($storeById[$storesId])) {
                    $errors[] = '第' . $r . '行：未填写仓库，且商品「' . $goodsCode . '」未设置默认仓库';
                    continue;
                }
                $warnings[] = '第' . $r . '行：未填写仓库，已按商品默认仓库「' . $storeById[$storesId] . '」处理';
            }

            // 单位：在该商品的可用单位里按名称匹配；留空或匹配不到则交给前端按默认单位处理
            $unitName = $cellText($r, 'unit_name');
            $unitId   = 0;
            if ($unitName !== '') {
                $validIds = [(int)$goods['unit_id']];
                if ((string)$goods['more_unit'] === '1' && isset($convByMain[(int)$goods['unit_id']])) {
                    foreach ($convByMain[(int)$goods['unit_id']] as $viceId) {
                        $validIds[] = $viceId;
                    }
                }
                foreach (isset($unitByName[$unitName]) ? $unitByName[$unitName] : [] as $candidate) {
                    if (in_array($candidate, $validIds, true)) {
                        $unitId = $candidate;
                        break;
                    }
                }
                if (!$unitId) {
                    $warnings[] = '第' . $r . '行：单位「' . $unitName . '」不适用于商品「' . $goodsCode . '」，已改用默认单位';
                }
            }

            // 入库单价：可留空，填了必须是非负数字
            $unitPrice = '';
            if ($this->excelWithPrice() && isset($map['unit_price'])) {
                $raw = trim(rtrim($cellText($r, 'unit_price'), '%'));
                if ($raw !== '') {
                    if (!is_numeric($raw)) {
                        $errors[] = '第' . $r . '行：入库单价「' . $cellText($r, 'unit_price') . '」不是数字';
                        continue;
                    }
                    if ((float)$raw < 0) {
                        $errors[] = '第' . $r . '行：入库单价不能为负数';
                        continue;
                    }
                    $unitPrice = (float)$raw;
                }
            }

            if ($batchOn && (int)$goods['batch'] === 1) {
                $warnings[] = '第' . $r . '行：商品「' . $goodsCode . '」启用了批次，导入后请在页面上选择生产批次/日期';
            }
            if ($serialOn && (int)$goods['serial'] === 1) {
                $warnings[] = '第' . $r . '行：商品「' . $goodsCode . '」启用了序列号，导入后请在页面上选择序列号';
            }

            $rows[] = [
                'goods_code' => $goodsCode,
                'goods_name' => $goods['goods_name'],
                'goods_unit' => $unitId,
                'stores_id'  => $storesId,
                'number'     => (float)$numberRaw,
                'unit_price' => $unitPrice,
                'remark'     => mb_substr($cellText($r, 'remark'), 0, 255),
            ];
        }

        $spreadsheet->disconnectWorksheets();

        if (!empty($errors)) {
            $msg = implode('；', array_slice($errors, 0, 8));
            if (count($errors) > 8) {
                $msg .= '；……共 ' . count($errors) . ' 处问题';
            }
            $this->error($msg);
        }
        if (empty($rows)) {
            $this->error('未读取到有效明细行，请使用系统「下载模板」生成的模板填写后再导入');
        }

        $this->success('解析成功', null, [
            'rows'     => $rows,
            'warnings' => array_slice($warnings, 0, 10),
        ]);
    }
}
