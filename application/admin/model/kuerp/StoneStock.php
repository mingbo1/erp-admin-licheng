<?php

namespace app\admin\model\kuerp;

use think\Model;

class StoneStock extends Model
{
    // 虚拟模型：库存查询不绑定具体表，使用 DB 查询
    protected $name = 'kuerp_goods_stock';
}
