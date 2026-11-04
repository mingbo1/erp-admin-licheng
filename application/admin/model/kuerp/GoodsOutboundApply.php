<?php

namespace app\admin\model\kuerp;

use think\Model;

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
}
