<?php

namespace app\admin\controller\kuerp;

/**
 * 采购单(仓库)
 * 继承自采购单(计划)，供仓库部门使用 - 只读：仅查看 + 打印
 * 与采购单(计划)共用 fa_kuerp_goods_purchase 表和同一 Model(GoodsPurchasePlan)
 *
 * @icon fa fa-eye
 */
class GoodsPurchasePlanWarehouse extends GoodsPurchasePlan
{
    // 只读页面：仅打印保持公开(与采购单(计划)一致)，不再暴露录入页用的取单位/批次/格式化接口
    protected $noNeedRight = ['printpage'];

    /**
     * 只读页面不支持添加
     */
    public function add($ids = null)
    {
        $this->error('采购单(仓库)为只读页面，不能添加单据');
    }

    /**
     * 只读页面不支持编辑
     */
    public function edit($ids = null)
    {
        $this->error('采购单(仓库)为只读页面，不能编辑单据');
    }

    /**
     * 只读页面不支持删除
     */
    public function del($ids = "")
    {
        $this->error('采购单(仓库)为只读页面，不能删除单据');
    }
}
