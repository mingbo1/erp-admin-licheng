<?php

namespace app\admin\model\kuerp;

use think\Model;

/**
 * 销货对账单发票（可多张）
 */
class SalesReconInvoice extends Model
{

    // 表名
    protected $name = 'kuerp_sales_recon_invoice';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
