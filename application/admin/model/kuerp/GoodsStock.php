<?php

namespace app\admin\model\kuerp;

use think\Model;
use traits\model\SoftDelete;

class GoodsStock extends Model
{

    // 表名
    protected $name = 'kuerp_goods_stock';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'status_text',
        'stock_warning_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getStockWarningList()
    {
        return ['0' => __('Stock_warning 0'), '1' => __('Stock_warning 1')];
    }

    public function getStoresList(){
        return (new Stores())->where(['status'=>1])->select();
    }
    public function getGoodsList(){
        return (new Goods())->where(['status'=>1])->select();
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStockWarningTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['stock_warning']) ? $data['stock_warning'] : '');
        $list = $this->getStockWarningList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
