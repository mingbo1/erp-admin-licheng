<?php

namespace app\admin\model\kuerp;

use think\Model;

/**
 * 采购对账单明细（勾选的采购单）
 */
class PurchaseReconInfo extends Model
{

    // 表名
    protected $name = 'kuerp_purchase_recon_info';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
}
