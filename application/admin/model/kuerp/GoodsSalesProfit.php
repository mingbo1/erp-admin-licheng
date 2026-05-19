<?php

namespace app\admin\model\kuerp;

use app\admin\model\Admin;
use think\Model;


class GoodsSalesProfit extends Model
{

    // 表名
    protected $name = 'kuerp_goods_sales_profit';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'purchase_time_text',
        'purchase_type_text'
    ];
    

    
    public function getPurchaseTypeList()
    {
        return ['1' => __('Purchase_type 1'), '2' => __('Purchase_type 2')];
    }


    public function getPurchaseTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_time']) ? $data['purchase_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getPurchaseTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['purchase_type']) ? $data['purchase_type'] : '');
        $list = $this->getPurchaseTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setPurchaseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
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
    public function getExpendTypeList(){
        return (new ExpendType())->where(['status'=>1])->select();
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
    public function getStaffMemberList(){
        return (new StaffMember())->where(['status'=>1])->select();
    }

    public function getCustomertypeList(){
        return (new Customertype())->where(['status'=>1])->select();
    }
    public function getGoodsTypeList(){
        return (new GoodsType())->where(['status'=>1])->select();
    }

    public function preparation()
    {
        return $this->belongsTo('app\admin\model\Admin', 'preparation_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function examine()
    {
        return $this->belongsTo('app\admin\model\Admin', 'examine_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function staffmember()
    {
        return $this->belongsTo('StaffMember', 'sale_user', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
