<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
/**
 * 重新初始化
 *
 * @icon fa fa-circle-o
 */
class Initialization extends Backend
{
    
    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);
    }

    public function index(){
        if ($this->request->isPost()) {
            $data = $_POST;
            if(isset($data['type']) && $data['type']=='initialization'){
                $limitTable   = [
                    'kuerp_accounts','kuerp_accounts_copewith','kuerp_accounts_log','kuerp_accounts_receivable','kuerp_accounts_transfer',
                    'kuerp_accounts_transfer_info','kuerp_address','kuerp_adjustment','kuerp_adjustment_info','kuerp_allocation',
                    'kuerp_allocation_info','kuerp_assemble','kuerp_assemble_info','kuerp_assemble_info_temp','kuerp_assemble_temp',
                    'kuerp_attr_combination','kuerp_attribute','kuerp_aux_combination','kuerp_auxiliary','kuerp_collection','kuerp_collection_account',
                    'kuerp_collection_sale','kuerp_cost','kuerp_customer_goodscoding','kuerp_customers','kuerp_customers_user',
                    'kuerp_customertype','kuerp_expend_type','kuerp_expenditure','kuerp_expenditure_info','kuerp_goods','kuerp_goods_attr',

                    'kuerp_goods_batch','kuerp_goods_batch_exchange_info','kuerp_goods_batch_info','kuerp_goods_batch_log','kuerp_goods_batch_sale_info',
                    'kuerp_goods_batch_salee_info','kuerp_goods_batch_stock','kuerp_goods_exchange','kuerp_goods_exchange_info','kuerp_goods_excpur',
                    'kuerp_goods_excpur_info','kuerp_goods_log','kuerp_goods_period','kuerp_goods_price','kuerp_goods_purchase','kuerp_goods_purchase_info',
                    'kuerp_goods_purchase_order','kuerp_goods_purchase_order_info','kuerp_goods_purchase_summary','kuerp_goods_purord','kuerp_goods_purord_info',
                    'kuerp_goods_saleexc','kuerp_goods_saleexc_info','kuerp_goods_saleord','kuerp_goods_saleord_info','kuerp_goods_sales','kuerp_goods_sales_exchange',
                    'kuerp_goods_sales_exchange_info','kuerp_goods_sales_info','kuerp_goods_sales_order','kuerp_goods_sales_order_info','kuerp_goods_sales_profit',
                    'kuerp_goods_sales_summary','kuerp_goods_serial','kuerp_goods_serial_log','kuerp_goods_stock','kuerp_goods_stock_warning','kuerp_goods_store_info',

                    'kuerp_goods_type','kuerp_income','kuerp_income_info','kuerp_income_type','kuerp_inventory','kuerp_inventory_info','kuerp_inventory_serial',
                    'kuerp_inventory_serial_info','kuerp_invoicing','kuerp_invoicing_log','kuerp_payment','kuerp_payment_account','kuerp_payment_log',
                    'kuerp_payment_sale','kuerp_sales_invoicing','kuerp_sales_invoicing_log','kuerp_sales_payment_log','kuerp_settlementtype','kuerp_spendingtype',
                    'kuerp_split','kuerp_split_info','kuerp_split_info_temp','kuerp_split_temp','kuerp_staff_member','kuerp_stores','kuerp_suppliers','kuerp_suppliers_user',
                    'kuerp_supplyertype','kuerp_transfers','kuerp_unit','kuerp_unit_conversion','kuerp_warehouse','kuerp_warehouse_info','kuerp_warehousing',
                    'kuerp_warehousing_info','kuerp_writeoff','kuerp_writeoff_info','kuerp_writeoff_log','kuerp_writeoff_source',

                    'kuerp_enclosure','kuerp_enclosure_log',
                ];
                $result = false;
                Db::startTrans();
                try {
                    foreach ($limitTable as $limitTable_k=>$limitTable_v){
                        $result = Db::name($limitTable_v)->where(['id'=>['>',0]])->delete();
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if(isset($result) && $result !== false){
                    $this->success('初始化成功');
                }else{
                    $this->error('初始化失败');
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

}
