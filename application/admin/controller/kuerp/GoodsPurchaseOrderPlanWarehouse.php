<?php

namespace app\admin\controller\kuerp;

/**
 * 采购预订单(仓库)
 * 复制自计划购货订单（GoodsPurchaseOrderPlan），仓库部门自行录入的采购预订单
 * 与计划购货订单、购货订单共用 fa_kuerp_goods_purchase_order 表，靠 order_source 字段隔离：
 *   计划购货订单 = order_source 'plan'，本页 = order_source 'warehouse'
 * 列表只显示本部门录入的单据，不能编辑/删除其它部门的单据
 *
 * @icon fa fa-calendar-check-o
 */
class GoodsPurchaseOrderPlanWarehouse extends GoodsPurchaseOrderPlan
{
    /**
     * 单据来源标记：仓库部门
     * @return string
     */
    protected function orderSource()
    {
        return 'warehouse';
    }

    /**
     * 列表来源过滤：只显示仓库部门录入的单据
     * @return array|null
     */
    protected function orderSourceListFilter()
    {
        return ['warehouse'];
    }

    /**
     * 单据名称（提示文案）
     * @return string
     */
    protected function docTitle()
    {
        return '采购预订单(仓库)';
    }
}
