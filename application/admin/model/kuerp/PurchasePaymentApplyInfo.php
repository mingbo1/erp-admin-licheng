<?php

namespace app\admin\model\kuerp;

use think\Model;

/**
 * 采购付款申请明细（关联对账单）
 */
class PurchasePaymentApplyInfo extends Model
{

    // 表名
    protected $name = 'kuerp_purchase_payment_apply_info';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
}
