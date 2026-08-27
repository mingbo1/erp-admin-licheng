<?php

namespace addons\kuerp;

use app\common\library\Menu;
use think\Addons;
use think\Db;
/**
 * 插件
 */
class Kuerp extends Addons
{

    protected $menu = [
        [
            'name'=>'kuerp','title'=>'进销存管理','icon'=>'fa fa-graduation-cap','ismenu'=>'1','weigh'=>'10',
            'sublist'=>[
                ['name'=>'kuerp/datainfo','title'=>'数据面板','icon'=>'fa fa-area-chart','ismenu'=>'1','weigh'=>'100',
                    'sublist'=>[
                        [
                            'name'=>'kuerp/datainfo/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',
                            'sublist'=>[],
                        ],
                    ]
                ],
                ['name'=>'kuerp/buy/default','title'=>'购货','icon'=>'fa fa-github-alt','ismenu'=>'1','weigh'=>'90',
                    'sublist'=>[
                        ['name'=>'kuerp/goods_purchase_order','title'=>'购货订单','icon'=>'fa fa-bar-chart-o','ismenu'=>'1','weigh'=>'90',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase_order/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order/secondExamine','title'=>'二次审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_purchase_order_plan','title'=>'计划购货订单','icon'=>'fa fa-calendar-check-o','ismenu'=>'1','weigh'=>'89',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase_order_plan/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order_plan/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order_plan/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_order_plan/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_purchase_plan','title'=>'采购计划','icon'=>'fa fa-calendar-check-o','ismenu'=>'1','weigh'=>'85',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase_plan/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_plan/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_plan/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase_plan/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_purchase','title'=>'购货单','icon'=>'fa fa-area-chart','ismenu'=>'1','weigh'=>'80',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase/secondExamine','title'=>'二次审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_purchase/counter','title'=>'对单/反对单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_exchange','title'=>'购货退货单','icon'=>'fa fa-database','ismenu'=>'1','weigh'=>'70',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_exchange/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_exchange/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_exchange/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_exchange/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_exchange/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_exchange/counter','title'=>'对单/反对单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_purchase_info','title'=>'采购明细表','icon'=>'fa fa-building-o','ismenu'=>'1','weigh'=>'60',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase_info/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_purchase_order_info','title'=>'采购订单跟踪表','icon'=>'fa fa-building','ismenu'=>'1','weigh'=>'50',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase_order_info/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_purchase_summary','title'=>'采购汇总表(商品)','icon'=>'fa fa-bar-chart-o','ismenu'=>'1','weigh'=>'40',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase_summary/index','title'=>'查询','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_purchase_summarys','title'=>'采购汇总表(供应商)','icon'=>'fa fa-area-chart','ismenu'=>'1','weigh'=>'30',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_purchase_summarys/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/payment_log','title'=>'采购付款一览表','icon'=>'fa fa-dollar','ismenu'=>'1','weigh'=>'20',
                            'sublist'=>[
                                ['name'=>'kuerp/payment_log/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/invoicing','title'=>'采购开票管理','icon'=>'fa fa-bandcamp','ismenu'=>'1','weigh'=>'15',
                            'sublist'=>[
                                ['name'=>'kuerp/invoicing/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/invoicing/list','title'=>'开票源单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/invoicing/add','title'=>'开票','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/invoicing/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/purchaseby','title'=>'以销定购看板','icon'=>'fa fa-first-order','ismenu'=>'1','weigh'=>'10',
                            'sublist'=>[
                                ['name'=>'kuerp/purchaseby/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/replenishment','title'=>'智能补货','icon'=>'fa fa-american-sign-language-interpreting','ismenu'=>'1','weigh'=>'5',
                            'sublist'=>[
                                ['name'=>'kuerp/replenishment/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/enclosure','title'=>'原始单据','icon'=>'fa fa-book','ismenu'=>'1','weigh'=>'4',
                            'sublist'=>[
                                ['name'=>'kuerp/enclosure/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosure/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosure/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosure/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosure/selectpage','title'=>'选择','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                    ],
                ],
                ['name'=>'kuerp/sales','title'=>'销货','icon'=>'fa fa-apple','ismenu'=>'1','weigh'=>'80',
                    'sublist'=>[
                        ['name'=>'kuerp/goods_sales_order','title'=>'销货订单','icon'=>'fa fa-bar-chart-o','ismenu'=>'1','weigh'=>'100',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_order/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_order/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_order/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_order/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_order/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_order/secondExamine','title'=>'二次审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_schedule','title'=>'销货排期','icon'=>'fa fa-calendar','ismenu'=>'1','weigh'=>'98',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_schedule/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_schedule/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_schedule/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_schedule/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales','title'=>'销货单','icon'=>'fa fa-bar-chart','ismenu'=>'1','weigh'=>'95',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/secondExamine','title'=>'二次审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/counter','title'=>'对单/反对单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/dunning','title'=>'催收','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales/delDunning','title'=>'删除催收记录','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_exchange','title'=>'销货退货单','icon'=>'fa fa-database','ismenu'=>'1','weigh'=>'90',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_exchange/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_exchange/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_exchange/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_exchange/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_exchange/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_exchange/counter','title'=>'对单/反对单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_order_info','title'=>'销售订单跟踪表','icon'=>'fa fa-building-o','ismenu'=>'1','weigh'=>'85',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_order_info/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_info','title'=>'商品销售明细','icon'=>'fa fa-building','ismenu'=>'1','weigh'=>'80',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_info/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/sales_invoicing','title'=>'销货开票管理','icon'=>'fa fa-bandcamp','ismenu'=>'1','weigh'=>'75',
                            'sublist'=>[
                                ['name'=>'kuerp/sales_invoicing/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sales_invoicing/list','title'=>'开票源单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sales_invoicing/add','title'=>'开票','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sales_invoicing/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/sales_payment_log','title'=>'销货收款一览表','icon'=>'fa fa-dollar','ismenu'=>'1','weigh'=>'70',
                            'sublist'=>[
                                ['name'=>'kuerp/sales_payment_log/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_summary','title'=>'销售汇总表(按商品)','icon'=>'fa fa-calculator','ismenu'=>'1','weigh'=>'65',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_summary/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_summaryc','title'=>'销售汇总表(按客户)','icon'=>'fa fa-cubes','ismenu'=>'1','weigh'=>'60',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_summaryc/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_summarys','title'=>'销售汇总表(按销售员)','icon'=>'fa fa-fire','ismenu'=>'1','weigh'=>'55',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_summarys/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/salesprofit','title'=>'销售利润表','icon'=>'fa fa-bookmark','ismenu'=>'1','weigh'=>'50',
                            'sublist'=>[
                                ['name'=>'kuerp/salesprofit/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_sales_profit','title'=>'销售排行表','icon'=>'fa fa-bar-chart','ismenu'=>'1','weigh'=>'45',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_sales_profit/index','title'=>'按客户查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_sales_profit/index2','title'=>'按商品查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/intercourse','title'=>'往来单位欠款表','icon'=>'fa fa-bank','ismenu'=>'1','weigh'=>'40',
                            'sublist'=>[
                                ['name'=>'kuerp/intercourse/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/enclosuresale','title'=>'原始单据','icon'=>'fa fa-book','ismenu'=>'1','weigh'=>'35',
                            'sublist'=>[
                                ['name'=>'kuerp/enclosuresale/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosuresale/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosuresale/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosuresale/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/enclosuresale/selectpage','title'=>'选择','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                    ],
                ],
                ['name'=>'kuerp/stores/default', 'title'=>'仓库', 'icon'=>'fa fa-lastfm-square', 'ismenu'=>'1', 'weigh'=>'70',
                    'sublist'=>[
                        ['name'=>'kuerp/allocation','title'=>'调拨单','icon'=>'fa fa-arrows-h','ismenu'=>'1','weigh'=>'100',
                            'sublist'=>[
                                ['name'=>'kuerp/allocation/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/allocation/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/allocation/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/allocation/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/allocation/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/adjustment','title'=>'成本调整单','icon'=>'fa fa-codiepie','ismenu'=>'1','weigh'=>'95',
                            'sublist'=>[
                                ['name'=>'kuerp/adjustment/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/adjustment/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/adjustment/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/adjustment/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/adjustment/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/inventory','title'=>'盘点','icon'=>'fa fa-bug','ismenu'=>'1','weigh'=>'90',
                            'sublist'=>[
                                ['name'=>'kuerp/inventory/import','title'=>'导入','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory/export','title'=>'导出','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/inventory_serial','title'=>'序列号盘点','icon'=>'fa fa-black-tie','ismenu'=>'1','weigh'=>'85',
                            'sublist'=>[
                                ['name'=>'kuerp/inventory_serial/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory_serial/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory_serial/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/inventory_serial/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/warehousing','title'=>'其他入库单','icon'=>'fa fa-arrow-up','ismenu'=>'1','weigh'=>'80',
                            'sublist'=>[
                                ['name'=>'kuerp/warehousing/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehousing/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehousing/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehousing/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehousing/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/warehouse','title'=>'其他出库单','icon'=>'fa fa-arrow-circle-down','ismenu'=>'1','weigh'=>'75',
                            'sublist'=>[
                                ['name'=>'kuerp/warehouse/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehouse/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehouse/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehouse/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/warehouse/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/assemble','title'=>'组装单','icon'=>'fa fa-arrow-circle-o-up','ismenu'=>'1','weigh'=>'70',
                            'sublist'=>[
                                ['name'=>'kuerp/assemble/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/assemble/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/assemble/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/assemble/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/assemble/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/assemble/savetemp','title'=>'保存模板','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/split','title'=>'拆卸单','icon'=>'fa fa-arrow-circle-o-right','ismenu'=>'1','weigh'=>'65',
                            'sublist'=>[
                                ['name'=>'kuerp/split/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/split/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/split/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/split/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/split/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/split/savetemp','title'=>'保存模板','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/process','title'=>'外协加工单','icon'=>'fa fa-wrench','ismenu'=>'1','weigh'=>'62',
                            'sublist'=>[
                                ['name'=>'kuerp/process/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/process/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/process/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/process/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/process/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/process/processIn','title'=>'外协入库','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/datainfo/stockworing', 'title'=>'库存预警', 'icon'=>'fa fa-bug', 'ismenu'=>'1', 'weigh'=>'60'],
                        ['name'=>'kuerp/goods_stock', 'title'=>'商品库存余额表', 'icon'=>'fa fa-css3', 'ismenu'=>'1', 'weigh'=>'55',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_stock/index', 'title'=>'查看', 'icon'=>'fa fa-circle-o', 'ismenu'=>'0', 'weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_batch_log','title'=>'批次跟踪表','icon'=>'fa fa-amazon','ismenu'=>'1','weigh'=>'50',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_batch_log/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_log','title'=>'商品收发明细表','icon'=>'fa fa-adn','ismenu'=>'1','weigh'=>'45',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_log/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_log_summary','title'=>'商品收发汇总表','icon'=>'fa fa-eraser','ismenu'=>'1','weigh'=>'40',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_log_summary/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_serial_log','title'=>'序列号跟踪表','icon'=>'fa fa-file','ismenu'=>'1','weigh'=>'35',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_serial_log/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_serial_status','title'=>'序列号状态表','icon'=>'fa fa-adjust','ismenu'=>'1','weigh'=>'30',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_serial_status/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_batch_stock','title'=>'批次保质期清单','icon'=>'fa fa-codepen','ismenu'=>'1','weigh'=>'25',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_batch_stock/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/assemble_temp','title'=>'模板列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'20',
                            'sublist'=>[
                                ['name'=>'kuerp/assemble_temp/index','title'=>'模板列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/assemble_temp/gettemp','title'=>'获取模板','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/split_temp','title'=>'拆卸单模板','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'15',
                            'sublist'=>[
                                ['name'=>'kuerp/split_temp/index','title'=>'模板列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/split_temp/gettemp','title'=>'获取模板','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_bom','title'=>'商品BOM模板','icon'=>'fa fa-cubes','ismenu'=>'1','weigh'=>'14',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_bom/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_bom/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_bom/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_bom/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_bom/getbomdetail','title'=>'获取BOM详情','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_bom/chosegoods','title'=>'搜索商品','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_bom/chosebom','title'=>'选择BOM模板','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods_calculator','title'=>'商品计算器','icon'=>'fa fa-calculator','ismenu'=>'1','weigh'=>'12',
                            'sublist'=>[
                                ['name'=>'kuerp/goods_calculator/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_calculator/calculate','title'=>'计算','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods_calculator/getbomlist','title'=>'获取BOM列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                    ],
                ],
                ['name'=>'kuerp/capital','title'=>'资金','icon'=>'fa fa-database','ismenu'=>'1','weigh'=>'60',
                    'sublist'=>[
                        ['name'=>'kuerp/payment','title'=>'付款单','icon'=>'fa fa-database','ismenu'=>'1','weigh'=>'100',
                            'sublist'=>[
                                ['name'=>'kuerp/payment/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/payment/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/payment/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/payment/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/payment/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/collection','title'=>'收款单','icon'=>'fa fa-diamond','ismenu'=>'1','weigh'=>'95',
                            'sublist'=>[
                                ['name'=>'kuerp/collection/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/collection/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/collection/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/collection/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/collection/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/writeoff','title'=>'核销单','icon'=>'fa fa-apple','ismenu'=>'1','weigh'=>'90',
                            'sublist'=>[
                                ['name'=>'kuerp/writeoff/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/writeoff/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/writeoff/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/writeoff/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/writeoff/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/accounts_receivable','title'=>'应收账款明细表','icon'=>'fa fa-houzz','ismenu'=>'1','weigh'=>'85',
                            'sublist'=>[
                                ['name'=>'kuerp/accounts_receivable/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/accounts_copewith','title'=>'应付账款明细表','icon'=>'fa fa-glide-g','ismenu'=>'1','weigh'=>'80',
                            'sublist'=>[
                                ['name'=>'kuerp/accounts_copewith/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/income','title'=>'其他收入单','icon'=>'fa fa-meetup','ismenu'=>'1','weigh'=>'75',
                            'sublist'=>[
                                ['name'=>'kuerp/income/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/income/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/income/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/income/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/income/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/cost','title'=>'采购销售费用清单','icon'=>'fa fa-list-alt','ismenu'=>'1','weigh'=>'70',
                            'sublist'=>[
                                ['name'=>'kuerp/cost/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/cost/checkdata','title'=>'组合采购销售费用','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/expenditure','title'=>'其他支出单','icon'=>'fa fa-reply','ismenu'=>'1','weigh'=>'65',
                            'sublist'=>[
                                ['name'=>'kuerp/expenditure/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/expenditure/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/expenditure/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/expenditure/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/expenditure/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/accounts_transfer','title'=>'资金转账单','icon'=>'fa fa-leanpub','ismenu'=>'1','weigh'=>'60',
                            'sublist'=>[
                                ['name'=>'kuerp/accounts_transfer/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts_transfer/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts_transfer/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts_transfer/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts_transfer/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/accounts_log','title'=>'现金银行报表','icon'=>'fa fa-pied-piper-pp','ismenu'=>'1','weigh'=>'55',
                            'sublist'=>[
                                ['name'=>'kuerp/accounts_log/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/customers_recon','title'=>'客户对账单','icon'=>'fa fa-snapchat-ghost','ismenu'=>'1','weigh'=>'50',
                            'sublist'=>[
                                ['name'=>'kuerp/customers_recon/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/suppliers_recon','title'=>'供应商对账单','icon'=>'fa fa-opera','ismenu'=>'1','weigh'=>'45',
                            'sublist'=>[
                                ['name'=>'kuerp/suppliers_recon/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/revenue','title'=>'其他收支明细表','icon'=>'fa fa-steam-square','ismenu'=>'1','weigh'=>'40',
                            'sublist'=>[
                                ['name'=>'kuerp/revenue/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/profit','title'=>'利润表','icon'=>'fa fa-yen','ismenu'=>'1','weigh'=>'35',
                            'sublist'=>[
                                ['name'=>'kuerp/profit/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                    ],
                ],
                ['name'=>'kuerp/defaultset','title'=>'资料','icon'=>'fa fa-cubes','ismenu'=>'1','weigh'=>'50',
                    'sublist'=>[
                        ['name'=>'kuerp/customers','title'=>'客户列表','icon'=>'fa fa-user-plus','ismenu'=>'1','weigh'=>'100',
                            'sublist'=>[
                                ['name'=>'kuerp/customers/import','title'=>'导入','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/customers/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/customers/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/customers/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/customers/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/customers/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/supplyer/default','title'=>'供应商管理','icon'=>'fa fa-user-md','ismenu'=>'1','weigh'=>'90',
                            'sublist'=>[
                                ['name'=>'kuerp/suppliers','title'=>'供应商列表','icon'=>'fa fa-flag','ismenu'=>'1','weigh'=>'0',
                                    'sublist'=>[
                                        ['name'=>'kuerp/suppliers/import','title'=>'导入','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/suppliers/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/suppliers/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/suppliers/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/suppliers/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/suppliers/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                    ],
                                ],
                                ['name'=>'kuerp/supplyertype','title'=>'供应商分类','icon'=>'fa fa-500px','ismenu'=>'1','weigh'=>'0',
                                    'sublist'=>[
                                        ['name'=>'kuerp/supplyertype/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/supplyertype/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/supplyertype/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/supplyertype/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/supplyertype/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/supplyertype/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                    ],
                                ],
                            ],
                        ],
                        [ 'name'=>'kuerp/accounts','title'=>'账户管理','icon'=>'fa fa-bank','ismenu'=>'1','weigh'=>'80',
                            'sublist'=>[
                                ['name'=>'kuerp/accounts/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/accounts/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/goods','title'=>'商品管理','icon'=>'fa fa-leanpub','ismenu'=>'1','weigh'=>'70',
                            'sublist'=>[
                                ['name'=>'kuerp/goods/import','title'=>'导入','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/goods/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/stores','title'=>'仓库管理','icon'=>'fa fa-ambulance','ismenu'=>'1','weigh'=>'60',
                            'sublist'=>[
                                ['name'=>'kuerp/stores/import','title'=>'导入','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stores/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stores/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stores/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stores/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stores/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stores/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/staff_member','title'=>'职员管理','icon'=>'fa fa-user','ismenu'=>'1','weigh'=>'50',
                            'sublist'=>[
                                ['name'=>'kuerp/staff_member/import','title'=>'导入','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/staff_member/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/staff_member/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/staff_member/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/staff_member/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/staff_member/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/staff_member/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/address','title'=>'发货地址管理','icon'=>'fa fa-twitter','ismenu'=>'1','weigh'=>'40',
                            'sublist'=>[
                                ['name'=>'kuerp/address/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/address/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/address/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/address/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/address/area','title'=>'选择地址','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/address/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/data_default','title'=>'辅助资料','icon'=>'fa fa-bitbucket','ismenu'=>'1','weigh'=>'30',
                            'sublist'=>[
                                ['name'=>'kuerp/customerlevel','title'=>'客户等级','icon'=>'fa fa-user-secret','ismenu'=>'1','weigh'=>'100',
                                    'sublist'=>[
                                        ['name'=>'kuerp/customerlevel/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                    ],
                                ],
                                ['name'=>'kuerp/customertype','title'=>'客户分类','icon'=>'fa fa-user-md','ismenu'=>'1','weigh'=>'90',
                                    'sublist'=>[
                                        ['name'=>'kuerp/customertype/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customertype/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customertype/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customertype/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customertype/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customertype/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                    ],
                                ],
                                ['name'=>'kuerp/goods_type','title'=>'商品分类','icon'=>'fa fa-bars','ismenu'=>'1','weigh'=>'80',
                                    'sublist'=>[
                                        ['name'=>'kuerp/goods_type/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_type/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_type/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_type/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_type/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_type/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                    ],
                                ],
                                ['name'=>'kuerp/goods_location','title'=>'商品库位','icon'=>'fa fa-map-marker','ismenu'=>'1','weigh'=>'75',
                                    'sublist'=>[
                                        ['name'=>'kuerp/goods_location/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_location/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_location/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_location/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_location/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/goods_location/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                    ],
                                ],
                                ['name'=>'kuerp/expend_type','title'=>'支出类别管理','icon'=>'fa fa-arrow-down','ismenu'=>'1','weigh'=>'70',
                                    'sublist'=>[
                                        ['name'=>'kuerp/expend_type/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/expend_type/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/expend_type/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/expend_type/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/expend_type/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/expend_type/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                    ],
                                ],
                                ['name'=>'kuerp/income_type','title'=>'收入类别管理','icon'=>'fa fa-arrow-up','ismenu'=>'1','weigh'=>'60',
                                    'sublist'=>[
                                        ['name'=>'kuerp/income_type/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/income_type/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/income_type/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/income_type/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/income_type/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/income_type/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                    ],
                                ],
                                ['name'=>'kuerp/settlementtype','title'=>'结算方式','icon'=>'fa fa-bookmark','ismenu'=>'1','weigh'=>'50',
                                    'sublist'=>[
                                        ['name'=>'kuerp/settlementtype/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/settlementtype/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/settlementtype/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/settlementtype/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/settlementtype/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/settlementtype/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                    ],
                                ],
                                ['name'=>'kuerp/unit/default','title'=>'计量单位','icon'=>'fa fa-amazon','ismenu'=>'1','weigh'=>'40',
                                    'sublist'=>[
                                        ['name'=>'kuerp/unit/index', 'title'=>'单计量单位', 'icon'=>'fa fa-vimeo', 'ismenu'=>'1', 'weigh'=>'0',
                                            'sublist'=>[
                                                ['name'=>'kuerp/unit/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                            ],
                                        ],
                                        ['name'=>'kuerp/unit_conversion/index', 'title'=>'多计量单位', 'icon'=>'fa fa-vimeo-square', 'ismenu'=>'1', 'weigh'=>'0',
                                            'sublist'=>[
                                                ['name'=>'kuerp/unit_conversion/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit_conversion/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit_conversion/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit_conversion/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                                ['name'=>'kuerp/unit_conversion/selectpage','title'=>'下拉列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                            ],
                                        ],
                                    ],
                                ],
                                ['name'=>'kuerp/auxiliary','title'=>'商品辅助属性','icon'=>'fa fa-circle-o','ismenu'=>'1','weigh'=>'30',
                                    'sublist'=>[
                                        ['name'=>'kuerp/auxiliary/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/auxiliary/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/auxiliary/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/auxiliary/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/auxiliary/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                    ],
                                ],
                                ['name'=>'kuerp/customer_goodscoding','title'=>'客户物料编码','icon'=>'fa fa-delicious','ismenu'=>'1','weigh'=>'20',
                                    'sublist'=>[
                                        ['name'=>'kuerp/customer_goodscoding/import','title'=>'Import','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customer_goodscoding/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customer_goodscoding/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customer_goodscoding/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customer_goodscoding/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                        ['name'=>'kuerp/customer_goodscoding/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0',],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                ['name'=>'kuerp/sets/default','title'=>'设置','icon'=>'fa fa-gears','ismenu'=>'1','weigh'=>'40',
                    'sublist'=>[
                        ['name'=>'kuerp/sets','title'=>'系统参数','icon'=>'fa fa-anchor','ismenu'=>'1','weigh'=>'0',
                            'sublist'=>[
                                ['name'=>'kuerp/sets/import','title'=>'Import','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sets/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sets/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sets/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sets/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sets/multi','title'=>'批量更新','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/sets/changestatus','title'=>'状态切换','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/initialization', 'title'=>'系统初始化', 'icon'=>'fa fa-american-sign-language-interpreting', 'ismenu'=>'1', 'weigh'=>'0',
                            'sublist'=>[
                                ['name'=>'kuerp/initialization/index', 'title'=>'初始化', 'icon'=>'fa fa-circle-o', 'ismenu'=>'0', 'weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/checkout', 'title'=>'结账/反结账', 'icon'=>'fa fa-anchor', 'ismenu'=>'1', 'weigh'=>'0',
                            'sublist'=>[
                                ['name'=>'kuerp/checkout/index', 'title'=>'查看', 'icon'=>'fa fa-circle-o', 'ismenu'=>'0', 'weigh'=>'0'],
                                ['name'=>'kuerp/checkout/checkout', 'title'=>'结账', 'icon'=>'fa fa-circle-o', 'ismenu'=>'0', 'weigh'=>'0'],
                                ['name'=>'kuerp/checkout/checkoutf', 'title'=>'反结账', 'icon'=>'fa fa-circle-o', 'ismenu'=>'0', 'weigh'=>'0'],
                            ],
                        ],
                    ],
                ],
                ['name'=>'kuerp/stone','title'=>'石材管理','icon'=>'fa fa-cube','ismenu'=>'1','weigh'=>'35',
                    'sublist'=>[
                        ['name'=>'kuerp/stone_in','title'=>'入库管理','icon'=>'fa fa-arrow-down','ismenu'=>'1','weigh'=>'100',
                            'sublist'=>[
                                ['name'=>'kuerp/stone_in/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_in/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_in/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_in/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_in/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_in/detail','title'=>'详情','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/stone_out','title'=>'出库管理','icon'=>'fa fa-arrow-up','ismenu'=>'1','weigh'=>'90',
                            'sublist'=>[
                                ['name'=>'kuerp/stone_out/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_out/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_out/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_out/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_out/examine','title'=>'审核/反审核','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/stone_out/detail','title'=>'详情','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/stone_stock','title'=>'库存查询','icon'=>'fa fa-database','ismenu'=>'1','weigh'=>'80',
                            'sublist'=>[
                                ['name'=>'kuerp/stone_stock/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/mini_banner','title'=>'轮播图','icon'=>'fa fa-image','ismenu'=>'1','weigh'=>'60',
                            'sublist'=>[
                                ['name'=>'kuerp/mini_banner/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/mini_banner/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/mini_banner/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/mini_banner/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/mini_nav','title'=>'首页导航','icon'=>'fa fa-th-large','ismenu'=>'1','weigh'=>'58',
                            'sublist'=>[
                                ['name'=>'kuerp/mini_nav/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/mini_nav/add','title'=>'添加','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/mini_nav/edit','title'=>'编辑','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                                ['name'=>'kuerp/mini_nav/del','title'=>'删除','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/mini_config','title'=>'小程序配置','icon'=>'fa fa-cog','ismenu'=>'1','weigh'=>'50',
                            'sublist'=>[
                                ['name'=>'kuerp/mini_config/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                        ['name'=>'kuerp/mini_user','title'=>'小程序用户','icon'=>'fa fa-user','ismenu'=>'1','weigh'=>'40',
                            'sublist'=>[
                                ['name'=>'kuerp/mini_user/index','title'=>'查看','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                            ],
                        ],
                    ],
                ],
                ['name'=>'kuerp/default','title'=>'默认函数','icon'=>'fa fa-area-chart','ismenu'=>'0','weigh'=>'100',
                    'sublist'=>[
                        ['name'=>'kuerp/allocation/getUnit','title'=>'获取单位价格','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/allocation/getSerial','title'=>'获取可用序列号','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/auxiliary','title'=>'商品属性','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/period','title'=>'商品期初','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/chosegoods','title'=>'选择商品','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/gettype','title'=>'商品分类','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/getUnit','title'=>'获取单位价格信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/getSaleUnit','title'=>'获取销售单位价格信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/getSerial','title'=>'获取商品序列号','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/getMenu','title'=>'获取菜单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/chosesale','title'=>'获取销售源单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/getSuppliers','title'=>'获取供应商信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods/getGoods','title'=>'获取商品信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_batch/index','title'=>'批次列表','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_batch_info/index','title'=>'批次详情','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_exchange/getsuppliers','title'=>'获取应付款余额','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_exchange/getSerial','title'=>'获取可退序列号','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase/checkPurchase','title'=>'检测可生成退货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase/getSuppliers','title'=>'获取应付款余额','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase/getUnit','title'=>'获取购货单位价格信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase/formatInfo','title'=>'格式化价格','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase/formatInfo2','title'=>'格式化价格','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase/getBranch','title'=>'获取批次','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase/getTime','title'=>'获取时间','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_plan/getUnit','title'=>'获取购货单位价格信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_plan/formatInfo','title'=>'格式化价格','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_plan/getBranch','title'=>'获取批次','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_plan/getTime','title'=>'获取时间','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_order/checkPurchase','title'=>'检测可生成购货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_order/checkExchange','title'=>'检测可生成退货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_order_plan/checkPurchase','title'=>'检测可生成购货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_order_plan/checkExchange','title'=>'检测可生成退货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_order_plan/checkSupplierCount','title'=>'检测供应商数量','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_order_plan/batchGeneratePurchase','title'=>'批量生成购货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_purchase_order_plan/generateBySupplier','title'=>'按供应商生成购货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/checkPurchase','title'=>'检测可生成退货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/getSuppliers','title'=>'获取应付款余额','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/getUnits','title'=>'获取单位信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/getUnit','title'=>'获取单位价格信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/formatInfo2','title'=>'格式化销货价格','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/getBranch','title'=>'获取批次','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/getTime','title'=>'获取时间','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales/getReceivingAddress','title'=>'获取客户收货地址','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_schedule/getUnit','title'=>'获取销货排期单位价格信息','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_schedule/formatInfo','title'=>'格式化销货排期价格','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_schedule/getBranch','title'=>'获取销货排期批次','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_schedule/getTime','title'=>'获取销货排期时间','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_schedule/getschedulegoods','title'=>'获取排期商品数据','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_exchange/getSuppliers','title'=>'获取退货应付款余额','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_order/checkPurchase','title'=>'检测可生成销货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/goods_sales_order/checkExchange','title'=>'检测可生成退货单','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/inventory_serial/getSerial','title'=>'获取盘点序列号','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/inventory_serial/period','title'=>'匹配序列号','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/purchaseby/setdata','title'=>'设置数量','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/replenishment/setdata','title'=>'设置数量','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/supplyertype/indexs','title'=>'选择供应商','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/warehouse/getSerial','title'=>'获取出库单序列号','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/warehousing/formatInfo','title'=>'格式化其他入库单价格','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/warehousing/checkInventory','title'=>'检测生成盘盈盘亏','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/writeoff/choselist','title'=>'选择预收单据','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/writeoff/choseadvancelist','title'=>'选择预付单据','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/writeoff/chosereceivable','title'=>'选择应收单据','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                        ['name'=>'kuerp/writeoff/chosecopewith','title'=>'选择应付单据','icon'=>'fa fa-circle-o','ismenu'=>'0','weigh'=>'0'],
                    ]
                ],
            ],
        ]
    ];


    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        Menu::create($this->menu);
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        Menu::delete("kuerp");
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        Menu::enable("kuerp");
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {
        Menu::disable("kuerp");
        return true;
    }

    /**
     * 插件升级方法
     * @return bool
     */
    public function upgrade()
    {
        //如果菜单有变更则升级菜单
        Menu::upgrade('kuerp', $this->menu);
        return true;
    }

}
