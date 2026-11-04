<?php

namespace app\admin\model\kuerp;

use think\Model;

/**
 * 销货对账商品明细快照
 */
class SalesReconInfo extends Model
{

    // 表名
    protected $name = 'kuerp_sales_recon_info';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
}
