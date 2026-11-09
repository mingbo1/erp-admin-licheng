<?php

namespace app\admin\controller\kuerp;

use think\Db;

/**
 * 排产管理（只读）
 *
 * 数据与「计划排期」同源（共用 fa_kuerp_goods_sales 表），
 * 但仅展示已排产（production_status=1）的销货单，页面不提供新增/编辑/删除，
 * 列表只需「刷新 / 导出」，行操作为「查看详情」。
 */
class ProductionManage extends GoodsSalesSchedule
{
    /**
     * 列表固定只显示已排产单据（生产日期搜索走 filter 的 production_date 字段）
     * @param \think\db\Query $query
     * @return \think\db\Query
     */
    protected function applyIndexExtraWhere($query)
    {
        return $query->where('production_status', '1');
    }

    /**
     * 导出文件名 / 工作表名
     */
    protected function exportDocName()
    {
        return '排产管理';
    }

    /**
     * 只读页面：禁止任何写操作（子类继承自计划排期，逐个封死）
     */
    public function add($ids = null)
    {
        $this->error('排产管理为只读页面，不能添加单据');
    }

    public function edit($ids = null)
    {
        $this->error('排产管理为只读页面，不能编辑单据');
    }

    public function del($ids = "")
    {
        $this->error('排产管理为只读页面，不能删除单据');
    }

    public function setproduction($ids = null, $status = 1)
    {
        $this->error('排产管理为只读页面，不能修改排产状态');
    }

    public function setExpectedDelivery()
    {
        $this->error('排产管理为只读页面，不能修改预计生产完成时间');
    }

    public function counter($ids = null, $counter = 1)
    {
        $this->error('排产管理为只读页面，不能对单');
    }

    public function lockGoods()
    {
        $this->error('排产管理为只读页面，不能锁定库存');
    }

    public function unlockGoods()
    {
        $this->error('排产管理为只读页面，不能解锁库存');
    }

    /**
     * 查看详情（只读）
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row = $this->appendDeliveryModeText($row);

        $row['customer_name']    = Db::name('kuerp_customers')->where('id', $row['customers_id'])->value('customer_name');
        $row['sale_user_name']   = Db::name('kuerp_staff_member')->where('id', $row['sale_user'])->value('name');
        $row['preparation_name'] = Db::name('admin')->where('id', $row['preparation_id'])->value('nickname');
        $row['examine_name']     = Db::name('admin')->where('id', $row['examine_id'])->value('nickname');
        $row['order_note']       = '';
        if (!empty($row['purchase_order_id'])) {
            $row['order_note'] = (string)Db::name('kuerp_goods_sales_order')
                ->where('purchase_code', $row['purchase_order_id'])
                ->value('note');
        }

        $infoList = Db::name('kuerp_goods_sales_info')
            ->where('purchase_code', $row['purchase_code'])
            ->order('id', 'asc')
            ->select();

        $goodsMap = $storesMap = $unitMap = $attrMap = [];
        if (!empty($infoList)) {
            $goodsCodes = array_unique(array_column($infoList, 'goods_code'));
            if (!empty($goodsCodes)) {
                $goodsMap = Db::name('kuerp_goods')
                    ->where('goods_code', 'in', $goodsCodes)
                    ->column('goods_name,goods_specification_id,vendor_name,packaging', 'goods_code');
            }
            $storesIds = array_unique(array_column($infoList, 'stores_id'));
            if (!empty($storesIds)) {
                $storesMap = Db::name('kuerp_stores')->where('id', 'in', $storesIds)->column('name', 'id');
            }
            $unitIds = array_unique(array_column($infoList, 'goods_unit'));
            if (!empty($unitIds)) {
                $unitMap = Db::name('kuerp_unit')->where('id', 'in', $unitIds)->column('name', 'id');
            }
            $attrIds = array_filter(array_unique(array_column($infoList, 'goods_attr')));
            if (!empty($attrIds)) {
                $attrMap = Db::name('kuerp_goods_attr')->where('id', 'in', $attrIds)->column('name', 'id');
            }
        }

        $totalNumber = 0;
        foreach ($infoList as $k => $v) {
            $goods = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : [];
            $infoList[$k]['goods_name']             = isset($goods['goods_name']) ? $goods['goods_name'] : '';
            $infoList[$k]['goods_specification_id'] = isset($goods['goods_specification_id']) ? $goods['goods_specification_id'] : '';
            $infoList[$k]['vendor_name']            = isset($goods['vendor_name']) ? $goods['vendor_name'] : '';
            $infoList[$k]['packaging']              = isset($goods['packaging']) ? $goods['packaging'] : '';
            $infoList[$k]['stores_name']            = isset($storesMap[$v['stores_id']]) ? $storesMap[$v['stores_id']] : '';
            $infoList[$k]['unit_name']              = isset($unitMap[$v['goods_unit']]) ? $unitMap[$v['goods_unit']] : '';
            $infoList[$k]['attr_name']              = isset($attrMap[$v['goods_attr']]) ? $attrMap[$v['goods_attr']] : '';
            $totalNumber += (float)$v['number'];
        }

        // 数量小数位（0 也是有效值，不能用 empty 判断）
        $numberDecimal = Db::name('kuerp_sets')->where('name', 'number_decimal')->value('value');
        $numberDecimal = ($numberDecimal !== null && $numberDecimal !== '') ? (int)$numberDecimal : 2;
        // 辅助属性列开关（关闭时该列被全局 CSS 隐藏，空行/合计行的 colspan 需同步减 1）
        $setData = \addons\kuerp\library\Goods::getSetData();
        $auxOn = (isset($setData['setList']['auxiliary']['is_checked']) && $setData['setList']['auxiliary']['is_checked'] == 1) ? 1 : 0;

        $this->view->assign('row', $row);
        $this->view->assign('info_list', $infoList);
        $this->view->assign('total_number', $totalNumber);
        $this->view->assign('number_decimal', $numberDecimal);
        $this->view->assign('aux_on', $auxOn);
        return $this->view->fetch();
    }
}
