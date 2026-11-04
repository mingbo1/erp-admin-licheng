<?php

namespace app\admin\model\kuerp;

use think\Model;

/**
 * 销货对账单单据级快照（销货单 / 销货退货单）
 */
class SalesReconDoc extends Model
{

    // 表名
    protected $name = 'kuerp_sales_recon_doc';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
}
