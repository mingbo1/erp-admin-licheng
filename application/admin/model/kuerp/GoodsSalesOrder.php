<?php

namespace app\admin\model\kuerp;

use app\admin\model\Admin;
use think\Db;
use think\Model;


class GoodsSalesOrder extends Model
{

    // 表名
    protected $name = 'kuerp_goods_sales_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'purchase_type_text',
        'purchase_time_text',
        'delivery_time_text',
        'delivery_mode_text',
        'status_text',
        'check_status_text',
        'examine_status_text',
        'examine_time_text',
        'second_examine_status_text',
        'second_examine_time_text',
        'source_text',
    ];
    

    
    public function getPurchaseTypeList()
    {
        return ['1' => __('Purchase_type 1'), '2' => __('Purchase_type 2')];
    }

    /**
     * 发货方式：direct=直发，dropship=代发
     */
    public function getDeliveryModeList()
    {
        return ['direct' => __('Delivery_mode direct'), 'dropship' => __('Delivery_mode dropship')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4')];
    }

    public function getCheckStatusList()
    {
        return ['0' => __('Check_status 0'), '1' => __('Check_status 1')];
    }

    public function getExamineStatusList()
    {
        return ['0' => __('Examine_status 0'), '1' => __('Examine_status 1')];
    }

    public function getSourceList()
    {
        return ['admin' => __('Source admin'), 'miniapp' => __('Source miniapp')];
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


    public function getDeliveryModeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['delivery_mode']) ? $data['delivery_mode'] : 'direct');
        $list = $this->getDeliveryModeList();
        return isset($list[$value]) ? $list[$value] : $list['direct'];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
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

    public function getSourceTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['source']) ? $data['source'] : '');
        $list = $this->getSourceList();
        return isset($list[$value]) ? $list[$value] : '';
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


    public function customers()
    {
        return $this->belongsTo('Customers', 'customers_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function getStoresList(){
        return (new Stores())->where(['status'=>1])->select();
    }
    public function getSuppliersList(){
        return (new Suppliers())->where(['status'=>1])->select();
    }
    public function getGoodsList(){
        return (new Goods())->where(['status'=>1])->select();
    }
    public function getAdminList(){
        return Admin::all();
    }
    public function getAccountsList(){
        return (new Accounts())->where(['status'=>1])->select();
    }
    public function getSaleList(){
        return (new StaffMember())->where(['status'=>1])->select();
    }
    public function getCustomersList(){
        return (new Customers())->where(['status'=>1])->select();
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

    public function delivery()
    {
        return $this->hasMany(\app\admin\model\kuerp\GoodsSalesOrderDelivery::class, 'order_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(\app\admin\model\kuerp\GoodsSalesOrderLog::class, 'order_id', 'id')->order('id', 'desc');
    }

    /**
     * 根据销货单的实际出库数量重算订单状态并落库
     * 0=未出库 1=全部出库 2=部分出库；3=已取消、4=已收货 为人工状态，不参与重算
     *
     * @param array|string $orderCodes 销货订单编号(purchase_code)
     * @return array purchase_code => status
     */
    public static function syncStatusByOutbound($orderCodes)
    {
        if (is_string($orderCodes)) {
            $orderCodes = explode(',', $orderCodes);
        }
        $orderCodes = array_values(array_unique(array_filter(array_map('trim', (array)$orderCodes))));
        if (empty($orderCodes)) {
            return [];
        }
        $orders = Db::name('kuerp_goods_sales_order')
            ->where(['purchase_code' => ['in', $orderCodes]])
            ->field('id,purchase_code,number,status')
            ->select();
        if (empty($orders)) {
            return [];
        }

        // 订单编号 => 关联销货单编号
        $salesRows = Db::name('kuerp_goods_sales')
            ->where(['purchase_order_id' => ['in', $orderCodes]])
            ->field('purchase_code,purchase_order_id')
            ->select();
        $salesMap = [];
        $salesCodes = [];
        foreach ($salesRows as $salesRow) {
            $salesMap[$salesRow['purchase_order_id']][] = $salesRow['purchase_code'];
            $salesCodes[] = $salesRow['purchase_code'];
        }

        // 销货单编号 => 已出库数量
        $outMap = [];
        if (!empty($salesCodes)) {
            $outRows = Db::name('kuerp_goods_sales_info')
                ->where(['purchase_code' => ['in', $salesCodes]])
                ->field('purchase_code,sum(out_number) as total_out')
                ->group('purchase_code')
                ->select();
            foreach ($outRows as $outRow) {
                $outMap[$outRow['purchase_code']] = (float)$outRow['total_out'];
            }
        }

        $result = [];
        foreach ($orders as $order) {
            $currentStatus = (string)$order['status'];
            if ($currentStatus === '3' || $currentStatus === '4') {
                $result[$order['purchase_code']] = $currentStatus;
                continue;
            }
            $totalOut = 0;
            if (isset($salesMap[$order['purchase_code']])) {
                foreach ($salesMap[$order['purchase_code']] as $salesCode) {
                    $totalOut += isset($outMap[$salesCode]) ? $outMap[$salesCode] : 0;
                }
            }
            $orderNumber = (float)$order['number'];
            if ($totalOut <= 0) {
                $status = '0';
            } elseif ($orderNumber > 0 && $totalOut + 0.0001 >= $orderNumber) {
                $status = '1';
            } else {
                $status = '2';
            }
            if ($status !== $currentStatus) {
                Db::name('kuerp_goods_sales_order')
                    ->where(['id' => $order['id']])
                    ->update(['status' => $status, 'updatetime' => time()]);
            }
            $result[$order['purchase_code']] = $status;
        }
        return $result;
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
