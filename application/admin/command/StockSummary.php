<?php

namespace app\admin\command;

use app\admin\library\kuerp\StockSummary as StockSummaryLib;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 收发存汇总表月度快照
 *
 * 用法：
 *   php think kuerp:stock_summary                  # 计算上个月（每月1日0点跑的就是这个）
 *   php think kuerp:stock_summary --period=2026-10 # 指定月份
 *
 * crontab（每月 1 日 0 点 0 分）：
 *   0 0 1 * * cd /path/to/erp && /path/to/php think kuerp:stock_summary >> runtime/stock_summary.log 2>&1
 */
class StockSummary extends Command
{
    protected function configure()
    {
        $this->setName('kuerp:stock_summary')
            ->addOption('period', null, Option::VALUE_OPTIONAL, '汇总月份 YYYY-MM，默认上个月')
            ->setDescription('生成收发存汇总表月度快照');
    }

    protected function execute(Input $input, Output $output)
    {
        $period = trim((string)$input->getOption('period'));
        if ($period === '') {
            $period = StockSummaryLib::defaultPeriod();
        }
        try {
            $result = StockSummaryLib::build($period, true);
        } catch (\Exception $e) {
            $output->writeln('<error>计算失败：' . $e->getMessage() . '</error>');
            return 1;
        }
        $tip = $result['complete'] ? '' : '（月份未结束，期末已留空）';
        $output->writeln('<info>' . $result['period'] . ' 收发存汇总表计算完成，' . $result['rows'] . ' 行' . $tip . '</info>');
        return 0;
    }
}
