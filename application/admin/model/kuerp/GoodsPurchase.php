<?php

namespace app\admin\model\kuerp;

use app\admin\model\Admin;
use think\Db;
use think\Model;
use traits\model\SoftDelete;

class GoodsPurchase extends Model
{

    // 表名
    protected $name = 'kuerp_goods_purchase';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'purchase_type_text',
        'purchase_time_text',
        'delivery_time_text',
        'pay_status_text',
        'check_status_text',
        'examine_status_text',
        'examine_time_text',
        'second_examine_status_text',
        'second_examine_time_text'
    ];
    

    
    public function getPurchaseTypeList()
    {
        return ['1' => __('Purchase_type 1'), '2' => __('Purchase_type 2')];
    }

    /**
     * 批量计算采购单的入库状态（0=未入库 1=部分入库 2=已入库）
     *
     * 按采购单号汇总明细表的 采购数量(number) 与 已入库数量(storage_number)：
     *   已入库量 <= 0         → 0 未入库
     *   0 < 已入库量 < 采购数量 → 1 部分入库
     *   已入库量 >= 采购数量    → 2 已入库
     *
     * 采购单(goods_purchase)、采购单(计划)(goods_purchase_plan)、采购单(仓库)
     * (goods_purchase_plan_warehouse) 与采购入库(goods_purchase_storage) 共用
     * fa_kuerp_goods_purchase 表，因此统一调用本方法（其余控制器跨模型全限定调用）。
     *
     * @param array $purchaseCodes 采购单号数组
     * @return array [采购单号 => 0|1|2]（查不到明细的单号不出现在结果里，调用方按 0 处理）
     */
    /**
     * 采购单列表排序 SQL（orderRaw 用）
     *
     * 规则：同一个采购订单(GHDD)生成的采购单排在一起；分组之间按采购订单创建时间倒序
     * （没有关联采购订单的采购单按自身创建时间参与排序）；组内按采购单号升序。
     *
     * 采购单(goods_purchase)、采购单(计划)(goods_purchase_plan)、采购单(仓库)
     * (goods_purchase_plan_warehouse) 与采购入库(goods_purchase_storage) 共用
     * fa_kuerp_goods_purchase 表，因此统一调用本方法。
     *
     * @param string $alias 当前查询主表的别名（由 buildparams 生成，默认是模型名转下划线）
     * @return string
     */
    public static function orderSqlByPurchaseOrder($alias)
    {
        $orderTable = \think\Config::get('database.prefix') . 'kuerp_goods_purchase_order';
        return "COALESCE((SELECT po.createtime FROM {$orderTable} po WHERE po.purchase_code = {$alias}.purchase_order_id), {$alias}.createtime) DESC,"
            . " {$alias}.purchase_order_id DESC, {$alias}.purchase_code ASC";
    }

    public static function storageStatusMap($purchaseCodes)
    {
        $purchaseCodes = array_values(array_filter(array_unique((array)$purchaseCodes)));
        if (empty($purchaseCodes)) {
            return [];
        }
        $rows = Db::name('kuerp_goods_purchase_info')
            ->where(['purchase_code' => ['in', $purchaseCodes]])
            ->field('purchase_code, SUM(number) AS sum_number, SUM(storage_number) AS sum_stored')
            ->group('purchase_code')
            ->select();
        $map = [];
        foreach ($rows as $row) {
            $sumNumber = (float)$row['sum_number'];
            $sumStored = (float)$row['sum_stored'];
            if ($sumStored <= 0) {
                $map[$row['purchase_code']] = 0;
            } elseif ($sumStored < $sumNumber) {
                $map[$row['purchase_code']] = 1;
            } else {
                $map[$row['purchase_code']] = 2;
            }
        }
        return $map;
    }

    public function getPayStatusList()
    {
        return ['0' => __('Pay_status 0'), '1' => __('Pay_status 1'), '2' => __('Pay_status 2')];
    }

    public function getCheckStatusList()
    {
        return ['0' => __('Check_status 0'), '1' => __('Check_status 1')];
    }

    public function getExamineStatusList()
    {
        return ['0' => __('Examine_status 0'), '1' => __('Examine_status 1')];
    }

    public function getSuppliersList(){
        return (new Suppliers())->where(['status'=>1])->select();
    }
    public function getStoresList(){
        return (new Stores())->where(['status'=>1])->select();
    }
    public function getGoodsList(){
        return (new Goods())->where(['status'=>1])->select();
    }
    public function getAdminList(){
        return (new Admin())->select();
    }
    public function getAccountsList(){
        return (new Accounts())->where(['status'=>1])->select();
    }
    public function getExpendTypeList(){
        return (new ExpendType())->where(['status'=>1])->select();
    }

    public function getPurchaseTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_type']) ? $data['purchase_type'] : '');
        $list = $this->getPurchaseTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getDeliveryTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['delivery_time']) ? $data['delivery_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getPayStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_status']) ? $data['pay_status'] : '');
        $list = $this->getPayStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCheckStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['check_status']) ? $data['check_status'] : '');
        $list = $this->getCheckStatusList();
        return isset($list[$value]) ? $list[$value] : '';
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

    protected function setPurchaseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setDeliveryTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function suppliers()
    {
        return $this->belongsTo('Suppliers', 'supplyer_code', 'supplyer_code', [], 'LEFT')->setEagerlyType(0);
    }

    public function preparation()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function examine()
    {
        return $this->belongsTo('app\admin\model\Admin', 'examine_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function secondExamine()
    {
        return $this->belongsTo('app\admin\model\Admin', 'second_examine_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getSecondExamineStatusList()
    {
        return ['0' => __('Second_examine_status 0'), '1' => __('Second_examine_status 1')];
    }

    public function getSecondExamineStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['second_examine_status']) ? $data['second_examine_status'] : '');
        $list = $this->getSecondExamineStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getSecondExamineTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['second_examine_time']) ? $data['second_examine_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setSecondExamineTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }
}
