<?php

namespace app\admin\model\kuerp;

use think\Model;


class InvoicingLog extends Model
{

    // 表名
    protected $name = 'kuerp_invoicing_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'bill_time_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4'), '5' => __('Status 5')];
    }

    public function getSuppliersList(){
        return (new Suppliers())->where(['status'=>1])->select();
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getBillTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['bill_time']) ? $data['bill_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setBillTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function suppliers()
    {
        return $this->belongsTo('Suppliers', 'suppliers_code', 'supplyer_code', [], 'LEFT')->setEagerlyType(0);
    }
}
