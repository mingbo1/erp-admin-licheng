<?php

namespace app\admin\model\kuerp;

use app\admin\model\Admin;
use think\Model;

class ProcessOrder extends Model
{

    // 表名 — 与外协加工单共用同一张表
    protected $name = 'kuerp_process';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'process_time_text',
        'examine_status_text',
        'examine_time_text',
        'in_status_text',
        'in_time_text'
    ];


    public function getExamineStatusList()
    {
        return ['0' => __('Examine_status 0'), '1' => __('Examine_status 1')];
    }

    public function getInStatusList()
    {
        return ['0' => __('In_status 0'), '1' => __('In_status 1'), '2' => __('In_status 2')];
    }

    public function getStoresList()
    {
        return (new Stores())->where(['status' => 1])->select();
    }

    public function getGoodsList()
    {
        return (new Goods())->where(['status' => 1])->select();
    }

    public function getSuppliersList()
    {
        return (new Suppliers())->where(['status' => 1])->select();
    }

    public function getAdminList()
    {
        return Admin::all();
    }

    public function getAccountsList()
    {
        return (new Accounts())->where(['status' => 1])->select();
    }

    public function getExpendTypeList()
    {
        return (new ExpendType())->where(['status' => 1])->select();
    }

    public function getProcessTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['process_time']) ? $data['process_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getExamineStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_status']) ? $data['examine_status'] : '');
        $list = $this->getExamineStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getExamineTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['examine_time']) ? $data['examine_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getInStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['in_status']) ? $data['in_status'] : '');
        $list = $this->getInStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getInTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['in_time']) ? $data['in_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setProcessTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setInTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    // 关联供应商
    public function suppliers()
    {
        return $this->belongsTo('Suppliers', 'supplyer_code', 'supplyer_code', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联制单人
    public function preparation()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联审核人
    public function examine()
    {
        return $this->belongsTo('app\admin\model\Admin', 'examine_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
