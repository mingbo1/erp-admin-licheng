<?php

namespace app\admin\model\kuerp;

use think\Model;
use think\Db;

/**
 * 销货单出库申请
 * 计划部门在销货单出库页提交申请(本次各产品出库数量)，仓库部门在"出库管理"审核执行
 */
class GoodsOutboundApply extends Model
{
    // 表名
    protected $name = 'kuerp_goods_outbound_apply';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'status_text',
        'apply_time_text',
        'examine_time_text',
    ];

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = isset($data['status']) ? $data['status'] : '';
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getApplyTimeTextAttr($value, $data)
    {
        $value = isset($data['apply_time']) ? $data['apply_time'] : 0;
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    public function getExamineTimeTextAttr($value, $data)
    {
        $value = isset($data['examine_time']) ? $data['examine_time'] : 0;
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    /**
     * 某销货单的历次出库申请/出库记录（最新在前），附加展示用字段：
     * goods_summary 申请内容、out_summary 实际出库数量、express_list/express_summary 快递信息、
     * apply_admin_name 申请人、examine_admin_name 审核人
     *
     * 「销货出库」页的历次申请记录与「销货单」详情底部的出库记录共用此方法，避免两处口径不一致。
     *
     * @param int $salesId 销货单ID（kuerp_goods_sales.id）
     * @return array
     */
    public static function buildHistory($salesId)
    {
        $list = Db::name('kuerp_goods_outbound_apply')
            ->where(['sales_id' => $salesId])
            ->order('id', 'DESC')
            ->select();
        if (empty($list)) {
            return [];
        }
        $adminIds = array_filter(array_merge(array_column($list, 'apply_admin_id'), array_column($list, 'examine_admin_id')));
        $adminMap = !empty($adminIds) ? Db::name('admin')->where('id', 'in', array_unique($adminIds))->column('nickname', 'id') : [];
        $applyIds = array_column($list, 'id');
        $infoMap = [];
        if (!empty($applyIds)) {
            $infoRows = Db::name('kuerp_goods_outbound_apply_info')->where('apply_id', 'in', $applyIds)->select();
            foreach ($infoRows as $v) {
                $infoMap[$v['apply_id']][] = $v;
            }
        }
        foreach ($list as $k => $v) {
            // 申请内容（商品编号×数量）
            $summaryParts = [];
            if (isset($infoMap[$v['id']])) {
                foreach ($infoMap[$v['id']] as $info) {
                    $summaryParts[] = $info['goods_code'] . '×' . rtrim(rtrim(sprintf('%.4f', $info['number']), '0'), '.');
                }
            }
            $list[$k]['goods_summary'] = implode('，', $summaryParts);
            $list[$k]['apply_admin_name'] = isset($adminMap[$v['apply_admin_id']]) ? $adminMap[$v['apply_admin_id']] : '';
            $list[$k]['examine_admin_name'] = !empty($v['examine_admin_id']) && isset($adminMap[$v['examine_admin_id']]) ? $adminMap[$v['examine_admin_id']] : '';
            // 实际出库数量（仓库同意时写入 out_data：{sales_info_id: 数量}）
            $outData = !empty($v['out_data']) ? json_decode($v['out_data'], true) : [];
            $outParts = [];
            if (is_array($outData) && isset($infoMap[$v['id']])) {
                foreach ($infoMap[$v['id']] as $info) {
                    if (isset($outData[$info['sales_info_id']])) {
                        $outParts[] = $info['goods_code'] . '×' . rtrim(rtrim(sprintf('%.4f', (float)$outData[$info['sales_info_id']]), '0'), '.');
                    }
                }
            }
            $list[$k]['out_summary'] = implode('，', $outParts);
            // 快递信息（申请时填写，一次出库可多条）
            $expressRows = !empty($v['express_data']) ? json_decode($v['express_data'], true) : [];
            $expressRows = is_array($expressRows) ? $expressRows : [];
            $list[$k]['express_list'] = $expressRows;
            $expressParts = [];
            foreach ($expressRows as $ex) {
                $company = isset($ex['company']) && $ex['company'] !== '' ? $ex['company'] : (isset($ex['code']) ? $ex['code'] : '');
                $no = isset($ex['no']) ? $ex['no'] : '';
                $expressParts[] = trim($company . ' ' . $no);
            }
            $list[$k]['express_summary'] = implode('、', $expressParts);
        }
        return $list;
    }
}
