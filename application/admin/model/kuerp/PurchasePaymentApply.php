<?php

namespace app\admin\model\kuerp;

use think\Model;

/**
 * 采购付款申请单
 */
class PurchasePaymentApply extends Model
{

    // 表名
    protected $name = 'kuerp_purchase_payment_apply';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'status_text',
    ];

    public function getStatusList()
    {
        return [
            '0' => '草稿',
            '1' => '待财务审核',
            '2' => '待审计审核',
            '3' => '待领导审核',
            '4' => '待出纳付款',
            '5' => '已付款',
            '6' => '已驳回',
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $list = $this->getStatusList();
        return isset($list[$data['status']]) ? $list[$data['status']] : '';
    }
}
