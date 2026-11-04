<?php

namespace app\admin\command;

use app\admin\library\kuerp\ArAnalysis as ArAnalysisLib;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 应收账款分析月度快照
 *
 * 用法：
 *   php think kuerp:ar_analysis                 # 分析上个月（每月1日0点跑的就是这个）
 *   php think kuerp:ar_analysis --period=2026-10 # 指定月份
 *
 * crontab（每月 1 日 0 点 0 分）：
 *   0 0 1 * * cd /path/to/erp && /path/to/php think kuerp:ar_analysis >> runtime/ar_analysis.log 2>&1
 */
class ArAnalysis extends Command
{
    protected function configure()
    {
        $this->setName('kuerp:ar_analysis')
            ->addOption('period', null, Option::VALUE_OPTIONAL, '分析月份 YYYY-MM，默认上个月')
            ->setDescription('生成应收账款分析月度快照');
    }

    protected function execute(Input $input, Output $output)
    {
        $period = trim((string)$input->getOption('period'));
        if ($period === '') {
            $period = ArAnalysisLib::defaultPeriod();
        }
        try {
            $result = ArAnalysisLib::build($period, true);
        } catch (\Exception $e) {
            $output->writeln('<error>分析失败：' . $e->getMessage() . '</error>');
            return 1;
        }
        $output->writeln('<info>' . $result['period'] . ' 应收账款分析完成，' . $result['rows'] . ' 个客户</info>');
        return 0;
    }
}
