<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;

/**
 * 出库管理（销货出库申请审核）
 * 计划部门在"销货单出库"页提交出库申请（本次各产品出库数量），
 * 仓库部门在此审核：同意出库（可逐行调整本次出库数量，同意后立即执行出库扣减库存）或拒绝（必填原因）
 * @icon fa fa-truck
 */
class GoodsOutboundApply extends Backend
{
    /**
     * GoodsOutboundApply模型对象
     * @var \app\admin\model\kuerp\GoodsOutboundApply
     */
    protected $model = null;

    /**
     * 系统设置（打印页需要判断属性/批次列是否显示）
     * @var array
     */
    protected $setList = [];

    /**
     * 无需鉴权的方法,但需要登录（打印页与其余单据打印页一致）
     * @var array
     */
    // printpage / deliverynote 已纳入权限表（kuerp/goods_outbound_apply/printpage|deliverynote）
    protected $noNeedRight = [];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->setList = $setData['setList'];
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsOutboundApply;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            // 出库状态为派生筛选（按销货单号汇总销货明细的已出库数量），不能进 filter，走独立参数
            $outboundFilter = \addons\kuerp\library\Goods::outboundStatusCodes($this->request->get('outbound_status', ''));
            $query = $this->model->where($where);
            if ($outboundFilter !== null) {
                $query->where('purchase_code', $outboundFilter['mode'], $outboundFilter['codes']);
            }
            $list = $query
                ->order('id', 'DESC')
                ->paginate($limit);
            $rows = $list->items();

            // 批量补客户名/申请人/审核人昵称
            $customerIds = [];
            $adminIds = [];
            foreach ($rows as $row) {
                if (!empty($row['customers_id'])) {
                    $customerIds[] = $row['customers_id'];
                }
                if (!empty($row['apply_admin_id'])) {
                    $adminIds[] = $row['apply_admin_id'];
                }
                if (!empty($row['examine_admin_id'])) {
                    $adminIds[] = $row['examine_admin_id'];
                }
            }
            $customerMap = !empty($customerIds) ? Db::name('kuerp_customers')->where('id', 'in', array_unique($customerIds))->column('customer_name', 'id') : [];
            $adminMap = !empty($adminIds) ? Db::name('admin')->where('id', 'in', array_unique($adminIds))->column('nickname', 'id') : [];
            // 发货方式（代发/直发）：取关联销货单
            $salesIds = [];
            foreach ($rows as $row) {
                if (!empty($row['sales_id'])) {
                    $salesIds[] = $row['sales_id'];
                }
            }
            $deliveryModeMap = !empty($salesIds) ? Db::name('kuerp_goods_sales')->where('id', 'in', array_unique($salesIds))->column('delivery_mode', 'id') : [];
            foreach ($rows as $k => $row) {
                $rows[$k]['customer_name'] = isset($customerMap[$row['customers_id']]) ? $customerMap[$row['customers_id']] : '';
                $rows[$k]['apply_admin_name'] = isset($adminMap[$row['apply_admin_id']]) ? $adminMap[$row['apply_admin_id']] : '';
                $rows[$k]['examine_admin_name'] = isset($adminMap[$row['examine_admin_id']]) ? $adminMap[$row['examine_admin_id']] : '';
                $rows[$k]['delivery_mode'] = isset($deliveryModeMap[$row['sales_id']]) ? $deliveryModeMap[$row['sales_id']] : 'direct';
            }

            $result = array("total" => $list->total(), "rows" => $rows);
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 详情/审核弹窗（status=0 待审核时可修改本次出库数量并同意）
     */
    public function detail($ids = null)
    {
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('出库申请不存在');
        }
        $this->assignApplyDetail($row);
        return $this->view->fetch('detail');
    }

    /**
     * 编辑出库申请（仅待审核 status=0，可修改各明细行申请数量）
     */
    public function edit($ids = null)
    {
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('出库申请不存在');
        }
        if ($this->request->isPost()) {
            if ($row['status'] != '0') {
                $this->error('仅待审核的申请可编辑');
            }
            $sales = Db::name('kuerp_goods_sales')->where(['id' => $row['sales_id']])->field('id,examine_status')->find();
            if (!$sales || $sales['examine_status'] != 1) {
                $this->error('关联销货单不存在或已反审核，请删除该申请后重新申请');
            }
            $postNumber = $this->request->post('number/a');
            $applyInfoList = Db::name('kuerp_goods_outbound_apply_info')->where(['apply_id' => $row['id']])->select();
            if (empty($applyInfoList)) {
                $this->error('出库申请明细不存在');
            }
            // 其他待审申请对各行明细的占用（排除本申请）
            $otherPendingMap = $this->otherPendingQtyMap($row['sales_id'], $row['id']);
            $hasQty = false;
            foreach ($applyInfoList as $info) {
                $qty = isset($postNumber[$info['id']]) && $postNumber[$info['id']] !== '' ? (float)$postNumber[$info['id']] : (float)$info['number'];
                if ($qty < 0) {
                    $this->error('申请出库数量不能为负数');
                }
                $salesInfo = Db::name('kuerp_goods_sales_info')->where(['id' => $info['sales_info_id']])->field('number,out_number')->find();
                if (!$salesInfo) {
                    $this->error('销货单明细已变更（单据可能被编辑），请删除该申请后重新申请');
                }
                $other_pending = isset($otherPendingMap[$info['sales_info_id']]) ? (float)$otherPendingMap[$info['sales_info_id']] : 0;
                $max_out = (float)$salesInfo['number'] - (isset($salesInfo['out_number']) ? (float)$salesInfo['out_number'] : 0) - $other_pending;
                if ($qty > $max_out) {
                    $this->error($info['goods_code'] . ' 申请出库数量不能超过' . $max_out . '（其他待审申请已占用 ' . $other_pending . '）');
                }
                if ($qty > 0) {
                    $hasQty = true;
                }
            }
            if (!$hasQty) {
                $this->error('申请出库数量不能全部为0');
            }
            $infoRows = [];
            foreach ($applyInfoList as $info) {
                $qty = isset($postNumber[$info['id']]) && $postNumber[$info['id']] !== '' ? (float)$postNumber[$info['id']] : (float)$info['number'];
                $infoRows[] = ['id' => $info['id'], 'number' => $qty];
            }
            Db::startTrans();
            try {
                // 条件更新守卫：编辑提交瞬间申请被审核/拒绝时终止
                $updated = Db::name('kuerp_goods_outbound_apply')
                    ->where(['id' => $row['id'], 'status' => '0'])
                    ->update(['updatetime' => time()]);
                if (!$updated) {
                    throw new Exception('该出库申请已被处理，请刷新后重试');
                }
                foreach ($infoRows as $infoRow) {
                    Db::name('kuerp_goods_outbound_apply_info')->where(['id' => $infoRow['id']])->update(['number' => $infoRow['number']]);
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('出库申请修改成功');
        }
        $this->assignApplyDetail($row);
        return $this->view->fetch('edit');
    }

    /**
     * 删除出库申请（已同意出库 status=1 的申请已执行库存扣减，禁止删除；其余级联删明细）
     */
    public function del($ids = null)
    {
        if (empty($ids)) {
            $this->error('请选择要操作的数据');
        }
        $ids = is_array($ids) ? $ids : (is_string($ids) ? array_filter(explode(',', $ids)) : [$ids]);
        $rows = $this->model->where('id', 'in', $ids)->select();
        if (empty($rows)) {
            $this->error('请选择要操作的数据');
        }
        foreach ($rows as $row) {
            if ($row['status'] == '1') {
                $this->error('申请[' . $row['apply_code'] . ']已同意出库（库存已扣减），不能删除');
            }
        }
        Db::startTrans();
        try {
            Db::name('kuerp_goods_outbound_apply_info')->where('apply_id', 'in', $ids)->delete();
            // 条件双保险：并发场景下申请刚被同意时不删
            $deleted = Db::name('kuerp_goods_outbound_apply')->where('id', 'in', $ids)->where('status', '<>', '1')->delete();
            if (!$deleted) {
                throw new Exception('该出库申请已被处理（已同意出库），不能删除');
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('删除成功');
    }

    /**
     * 其他待审申请对各行明细的占用 {sales_info_id: qty}（排除指定申请；同一销货单可并存多条待审申请）
     */
    protected function otherPendingQtyMap($salesId, $excludeApplyId)
    {
        $rows = Db::name('kuerp_goods_outbound_apply_info')->alias('ai')
            ->join('kuerp_goods_outbound_apply a', 'a.id = ai.apply_id')
            ->where(['a.sales_id' => $salesId, 'a.status' => '0'])
            ->where('a.id', '<>', $excludeApplyId)
            ->field('ai.sales_info_id, SUM(ai.number) AS pending_qty')
            ->group('ai.sales_info_id')
            ->select();
        $map = [];
        foreach ($rows as $v) {
            $map[$v['sales_info_id']] = (float)$v['pending_qty'];
        }
        return $map;
    }

    /**
     * 详情/编辑弹窗共用装配：主行昵称 + 明细 JOIN 销货单明细（实时计划/已出库数量、属性/单位/批次名、可用库存）
     */
    protected function assignApplyDetail($row)
    {
        // 客户名/申请人/审核人昵称
        $row['customer_name'] = Db::name('kuerp_customers')->where('id', $row['customers_id'])->value('customer_name') ?: '';
        $adminIds = array_filter([$row['apply_admin_id'], $row['examine_admin_id']]);
        $adminMap = !empty($adminIds) ? Db::name('admin')->where('id', 'in', $adminIds)->column('nickname', 'id') : [];
        $row['apply_admin_name'] = isset($adminMap[$row['apply_admin_id']]) ? $adminMap[$row['apply_admin_id']] : '';
        $row['examine_admin_name'] = isset($adminMap[$row['examine_admin_id']]) ? $adminMap[$row['examine_admin_id']] : '';

        // 发货方式（代发/直发）：取销货单，若关联销货订单则以订单为准
        $delivery_mode = 'direct';
        $sales = Db::name('kuerp_goods_sales')->where('id', $row['sales_id'])->field('delivery_mode,purchase_order_id')->find();
        if ($sales) {
            if (in_array($sales['delivery_mode'], ['direct', 'dropship'], true)) {
                $delivery_mode = $sales['delivery_mode'];
            }
            if (!empty($sales['purchase_order_id'])) {
                $order_mode = Db::name('kuerp_goods_sales_order')
                    ->where('purchase_code', $sales['purchase_order_id'])
                    ->value('delivery_mode');
                if (in_array($order_mode, ['direct', 'dropship'], true)) {
                    $delivery_mode = $order_mode;
                }
            }
        }
        $row['delivery_mode'] = $delivery_mode;
        $row['delivery_mode_text'] = $delivery_mode == 'dropship' ? __('Delivery_mode dropship') : __('Delivery_mode direct');

        // 快递信息（申请出库时填写，一次出库可多条）
        $expressRows = !empty($row['express_data']) ? json_decode($row['express_data'], true) : [];
        $expressRows = is_array($expressRows) ? $expressRows : [];
        $expressParts = [];
        foreach ($expressRows as $ex) {
            $company = isset($ex['company']) && $ex['company'] !== '' ? $ex['company'] : (isset($ex['code']) ? $ex['code'] : '');
            $expressParts[] = trim($company . ' ' . (isset($ex['no']) ? $ex['no'] : ''));
        }
        $row['express_list'] = $expressRows;
        $row['express_summary'] = implode('、', $expressParts);
        $this->view->assign('row', $row);

        // 申请明细（JOIN 销货单明细取实时 计划数量/已出库数量/属性/单位/批次）
        $infoList = Db::name('kuerp_goods_outbound_apply_info')->where(['apply_id' => $row['id']])->select();
        $salesInfoIds = array_column($infoList, 'sales_info_id');
        $salesInfoMap = [];
        if (!empty($salesInfoIds)) {
            $salesInfoRows = Db::name('kuerp_goods_sales_info')->where('id', 'in', $salesInfoIds)->select();
            foreach ($salesInfoRows as $v) {
                $salesInfoMap[$v['id']] = $v;
            }
        }

        $storesMap = Db::name('kuerp_stores')->column('name', 'id');
        $unitMap = Db::name('kuerp_unit')->column('name', 'id');
        // 商品名称 / ID / 型号 / 规格2(厂商) / 规格3(封装)，按商品编号索引
        $goodsMap = [];
        $goodsIdMap = [];
        $goodsSpecMap = [];
        $goodsRows = Db::name('kuerp_goods')->field('id,goods_code,goods_name,goods_specification_id,vendor_name,packaging,unit_id')->select();
        foreach ($goodsRows as $g) {
            $goodsMap[$g['goods_code']] = $g['goods_name'];
            $goodsIdMap[$g['goods_code']] = (int)$g['id'];
            $goodsSpecMap[$g['goods_code']] = $g;
        }

        // 批量查询可用库存（按仓库+商品+属性），与销货单出库弹窗同口径
        $stockItems = [];
        foreach ($infoList as $v) {
            $salesInfo = isset($salesInfoMap[$v['sales_info_id']]) ? $salesInfoMap[$v['sales_info_id']] : null;
            if (!$salesInfo) {
                continue;
            }
            $gid = isset($goodsIdMap[$salesInfo['goods_code']]) ? (int)$goodsIdMap[$salesInfo['goods_code']] : 0;
            if ($gid) {
                $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$salesInfo['stores_id'], 'attribute_id' => (int)$salesInfo['goods_attr']];
            }
        }
        $stockMap = !empty($stockItems) ? \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '') : [];

        // 其他待审申请占用（供审核人参考：同意本申请后剩余可出 = 剩余未出 - 其他待审占用）
        $otherPendingMap = $row['status'] == '0' ? $this->otherPendingQtyMap($row['sales_id'], $row['id']) : [];

        $detailHiddenFields = ['all_money', 'unit_price', 'cost_price', 'tax_included', 'discount', 'dis_money', 'tax_rate', 'tax_money', 'money_tax_price', 'preferential_rate', 'preferential_money', 'real_money', 'purchase_money', 'customers_money', 'pay_money'];

        foreach ($infoList as $k => $v) {
            $salesInfo = isset($salesInfoMap[$v['sales_info_id']]) ? $salesInfoMap[$v['sales_info_id']] : null;
            $infoList[$k]['sales_missing'] = $salesInfo ? false : true;
            if (!$salesInfo) {
                $infoList[$k]['goods_name'] = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : '';
                $infoList[$k]['goods_specification_id'] = isset($goodsSpecMap[$v['goods_code']]) ? $goodsSpecMap[$v['goods_code']]['goods_specification_id'] : '';
                $infoList[$k]['vendor_name'] = isset($goodsSpecMap[$v['goods_code']]) ? $goodsSpecMap[$v['goods_code']]['vendor_name'] : '';
                $infoList[$k]['packaging'] = isset($goodsSpecMap[$v['goods_code']]) ? $goodsSpecMap[$v['goods_code']]['packaging'] : '';
                $infoList[$k]['stores_name'] = isset($storesMap[$v['stores_id']]) ? $storesMap[$v['stores_id']] : '';
                $defaultUnitId = isset($goodsSpecMap[$v['goods_code']]['unit_id']) ? (int)$goodsSpecMap[$v['goods_code']]['unit_id'] : 0;
                $infoList[$k]['unit_name'] = $defaultUnitId && isset($unitMap[$defaultUnitId]) ? $unitMap[$defaultUnitId] : '';
                $infoList[$k]['attr_name'] = '';
                $infoList[$k]['batch_name'] = '';
                $infoList[$k]['apply_number'] = $v['number'];
                $infoList[$k]['number'] = 0;
                $infoList[$k]['out_number'] = 0;
                $infoList[$k]['other_pending'] = 0;
                $infoList[$k]['allow_stock'] = 0;
                continue;
            }
            $gid = isset($goodsIdMap[$salesInfo['goods_code']]) ? (int)$goodsIdMap[$salesInfo['goods_code']] : 0;
            $skey = $gid . '-' . (int)$salesInfo['stores_id'] . '-' . (int)$salesInfo['goods_attr'];
            $infoList[$k]['goods_code'] = $salesInfo['goods_code'];
            $infoList[$k]['goods_name'] = isset($goodsMap[$salesInfo['goods_code']]) ? $goodsMap[$salesInfo['goods_code']] : '';
            $infoList[$k]['goods_specification_id'] = isset($goodsSpecMap[$salesInfo['goods_code']]) ? $goodsSpecMap[$salesInfo['goods_code']]['goods_specification_id'] : '';
            $infoList[$k]['vendor_name'] = isset($goodsSpecMap[$salesInfo['goods_code']]) ? $goodsSpecMap[$salesInfo['goods_code']]['vendor_name'] : '';
            $infoList[$k]['packaging'] = isset($goodsSpecMap[$salesInfo['goods_code']]) ? $goodsSpecMap[$salesInfo['goods_code']]['packaging'] : '';
            $infoList[$k]['stores_name'] = isset($storesMap[$salesInfo['stores_id']]) ? $storesMap[$salesInfo['stores_id']] : '';
            $infoList[$k]['unit_name'] = isset($unitMap[$salesInfo['goods_unit']]) ? $unitMap[$salesInfo['goods_unit']] : '';
            // 申请数量（apply_info.number）与实时计划数量（sales_info.number）分开保存
            $infoList[$k]['apply_number'] = $v['number'];
            $infoList[$k]['number'] = $salesInfo['number'];
            $infoList[$k]['out_number'] = isset($salesInfo['out_number']) ? (float)$salesInfo['out_number'] : 0;
            $infoList[$k]['other_pending'] = isset($otherPendingMap[$v['sales_info_id']]) ? (float)$otherPendingMap[$v['sales_info_id']] : 0;
            $infoList[$k]['allow_stock'] = isset($stockMap[$skey]) ? $stockMap[$skey] : 0;
            // 属性名称
            $infoList[$k]['attr_name'] = '';
            if (!empty($salesInfo['goods_attr'])) {
                $attr = Db::name('kuerp_goods_attr')->where(['id' => $salesInfo['goods_attr']])->value('attr_code');
                $infoList[$k]['attr_name'] = $attr ? $attr : '';
            }
            // 批次名称
            $infoList[$k]['batch_name'] = '';
            if (!empty($salesInfo['batch_id'])) {
                $batch = Db::name('kuerp_goods_batch')->where(['id' => $salesInfo['batch_id']])->value('batch_name');
                $infoList[$k]['batch_name'] = $batch ? $batch : '';
            }
            // 隐藏金额字段（仓库人员不可见）
            foreach ($detailHiddenFields as $f) {
                if (isset($infoList[$k][$f])) {
                    unset($infoList[$k][$f]);
                }
            }
        }
        $this->view->assign('infoList', $infoList);
        // 空明细行 colspan：属性/批次列按系统设置隐藏时不占列位，colspan 需跟着减
        // detail 比 edit 多一列「本次出库数量」
        $optionCols = ($this->featureOn('auxiliary') ? 0 : 1) + ($this->featureOn('batch') ? 0 : 1);
        $this->view->assign('detailColCount', 15 - $optionCols);
        $this->view->assign('editColCount', 14 - $optionCols);
        return $infoList;
    }

    /**
     * 系统功能开关是否开启（属性/批次/税金等）
     */
    protected function featureOn($name)
    {
        return isset($this->setList[$name]['is_checked']) && $this->setList[$name]['is_checked'] == 1;
    }

    /**
     * 打印出库申请单（凭单发料；明细取申请数量，已同意出库的另带实际出库数量）
     */
    public function printpage($ids = null)
    {
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('出库申请不存在');
        }
        $infoList = $this->assignApplyDetail($row);

        // 实际出库数量：同意出库时写入的 out_data JSON {sales_info_id: 数量}，未同意的申请为空
        $outDataMap = [];
        if (!empty($row['out_data'])) {
            $decoded = json_decode($row['out_data'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $sid => $qty) {
                    $outDataMap[(int)$sid] = (float)$qty;
                }
            }
        }
        $totalApply = 0;
        $totalOut = 0;
        foreach ($infoList as $k => $v) {
            $applyNumber = isset($v['apply_number']) ? (float)$v['apply_number'] : 0;
            $actualNumber = isset($outDataMap[(int)$v['sales_info_id']]) ? $outDataMap[(int)$v['sales_info_id']] : 0;
            $infoList[$k]['apply_number'] = $applyNumber;
            $infoList[$k]['actual_number'] = $actualNumber;
            $totalApply += $applyNumber;
            $totalOut += $actualNumber;
        }

        // 空明细行的 colspan（属性/批次列按系统设置隐藏，实际出库列仅在已同意出库时出现）
        $colCount = 11;
        if ($this->featureOn('auxiliary')) {
            $colCount++;
        }
        if ($this->featureOn('batch')) {
            $colCount++;
        }
        if ($row['status'] == '1') {
            $colCount++;
        }

        $this->view->assign('infoList', $infoList);
        $this->view->assign('totalApply', $totalApply);
        $this->view->assign('totalOut', $totalOut);
        $this->view->assign('colCount', $colCount);
        $this->view->assign('printTime', date('Y-m-d H:i:s'));
        return $this->view->fetch('printpage');
    }

    /**
     * 打印送货单（版式参照 资料/财务功能/送货单模板.xlsx）
     * 数据：出库申请（本次出库数量 + 申请时填写的快递信息）+ 关联销货单（客户/单价/税额）
     * 「待审核」(0) 按申请数量、「已同意出库」(1) 按实际出库数量，两种状态都可打印；已拒绝/已撤销不能打印。
     * 单价/税额/合计默认隐藏，打印页可勾选「显示价格」。
     */
    public function deliverynote($ids = null)
    {
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('出库申请不存在');
        }
        // 待审核(0) 也可打印（按申请数量），已同意出库(1) 按实际出库数量；已拒绝/已撤销不能打印
        if (!in_array((string)$row['status'], ['0', '1'], true)) {
            $this->error('该出库申请已拒绝或已撤销，不能打印送货单');
        }
        $sales = Db::name('kuerp_goods_sales')->where('id', $row['sales_id'])->find();
        if (!$sales) {
            $this->error('关联销货单不存在');
        }

        // 公司信息（送货单标题/地址）
        $setMap = Db::name('kuerp_sets')->where('name', 'in', ['company_name', 'company_address'])->column('value', 'name');
        $companyName = isset($setMap['company_name']) && $setMap['company_name'] !== '' ? $setMap['company_name'] : '送货单';
        $companyAddress = isset($setMap['company_address']) ? $setMap['company_address'] : '';

        // 客户 / 联系人（优先取客户档案里的首选联系人）
        $customer = Db::name('kuerp_customers')->where('id', $sales['customers_id'])->find();
        $customerName = $customer ? $customer['customer_name'] : '';
        $contactName = $customer ? $customer['connet_name'] : '';
        $contactMobile = $customer ? ($customer['customer_mobile'] !== '' ? $customer['customer_mobile'] : $customer['customer_phone']) : '';
        if ($customer) {
            $firstUser = Db::name('kuerp_customers_user')->where('sid', $customer['id'])->order('is_first DESC, id ASC')->find();
            if ($firstUser) {
                if (!empty($firstUser['first_connect_name'])) {
                    $contactName = $firstUser['first_connect_name'];
                }
                $mobile = !empty($firstUser['first_connect_mobile']) ? $firstUser['first_connect_mobile'] : $firstUser['first_connect_phone'];
                if (!empty($mobile)) {
                    $contactMobile = $mobile;
                }
            }
        }

        // 业务员
        $saleUserName = Db::name('kuerp_staff_member')->where('id', $sales['sale_user'])->value('name');
        $saleUserName = $saleUserName ? $saleUserName : '';

        // 出库明细 + 销货单明细（价格口径：未税单价/未税金额 + 税额 = 价税合计）
        $applyInfoList = Db::name('kuerp_goods_outbound_apply_info')->where('apply_id', $row['id'])->select();
        $salesInfoIds = array_column($applyInfoList, 'sales_info_id');
        $salesInfoMap = [];
        $salesInfoRows = [];
        if (!empty($salesInfoIds)) {
            $salesInfoRows = Db::name('kuerp_goods_sales_info')->where('id', 'in', $salesInfoIds)->select();
            foreach ($salesInfoRows as $v) {
                $salesInfoMap[$v['id']] = $v;
            }
        }
        // 销货明细行可能已被编辑删除（历史脏数据），此时用申请明细里的商品编号兜底查商品档案
        $goodsCodes = array_values(array_unique(array_merge(
            array_column($salesInfoRows, 'goods_code'),
            array_column($applyInfoList, 'goods_code')
        )));
        $goodsMap = [];
        if (!empty($goodsCodes)) {
            $goodsRows = Db::name('kuerp_goods')->where('goods_code', 'in', $goodsCodes)
                ->field('goods_code,goods_name,goods_specification_id,vendor_name,packaging,unit_id')->select();
            foreach ($goodsRows as $g) {
                $goodsMap[$g['goods_code']] = $g;
            }
        }
        $unitMap = Db::name('kuerp_unit')->column('name', 'id');
        // 销货订单商品列表的「品号与功率」：销货单明细的 remark 由订单带入，为空时回退到订单明细，
        // 保证送货单「品号与功率」列与订单商品列表完全一致（同一商品取第一条非空值）
        $orderRemarkMap = [];
        if (!empty($sales['purchase_order_id']) && !empty($goodsCodes)) {
            $orderInfoRows = Db::name('kuerp_goods_sales_order_info')
                ->where('purchase_code', $sales['purchase_order_id'])
                ->where('goods_code', 'in', $goodsCodes)
                ->field('goods_code,remark')
                ->order('id', 'ASC')
                ->select();
            foreach ($orderInfoRows as $oi) {
                if (!isset($orderRemarkMap[$oi['goods_code']]) && $oi['remark'] !== null && $oi['remark'] !== '') {
                    $orderRemarkMap[$oi['goods_code']] = $oi['remark'];
                }
            }
        }
        // 客户物料编码（送货单表头「品号」）
        $customerCodes = [];
        if ($customer && !empty($goodsCodes)) {
            $customerCodes = Db::name('kuerp_customer_goodscoding')
                ->where('customers_id', $customer['id'])->where('goods_code', 'in', $goodsCodes)
                ->column('customer_code', 'goods_code');
        }

        $priceDecimal = isset($this->setList['price_decimal']['value']) ? (int)$this->setList['price_decimal']['value'] : 2;
        if ($priceDecimal < 0) {
            $priceDecimal = 2;
        }
        $numberDecimal = isset($this->setList['number_decimal']['value']) ? (int)$this->setList['number_decimal']['value'] : 2;
        if ($numberDecimal < 0) {
            $numberDecimal = 2;
        }

        // 实际出库数量：同意出库时写入的 out_data JSON {sales_info_id: 数量}（仓库可调整本次出库数量）
        $outDataMap = [];
        if (!empty($row['out_data'])) {
            $decoded = json_decode($row['out_data'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $sid => $qty) {
                    $outDataMap[(int)$sid] = (float)$qty;
                }
            }
        }

        $infoList = [];
        $totalNumber = 0;
        $totalSalesPrice = 0;
        $totalTax = 0;
        $totalMoney = 0;
        $skuCodes = [];
        foreach ($applyInfoList as $k => $info) {
            $si = isset($salesInfoMap[$info['sales_info_id']]) ? $salesInfoMap[$info['sales_info_id']] : null;
            // 送货单按实际出库数量打印；没有 out_data（历史数据）时回退申请数量
            $qty = isset($outDataMap[(int)$info['sales_info_id']]) ? $outDataMap[(int)$info['sales_info_id']] : (float)$info['number'];
            if ($qty <= 0) {
                continue;
            }
            $ratio = ($si && (float)$si['number'] > 0) ? $qty / (float)$si['number'] : 0;
            $salesPrice = $si ? round((float)$si['all_money'] * $ratio, $priceDecimal) : 0;
            $tax = $si ? round((float)$si['tax_money'] * $ratio, $priceDecimal) : 0;
            $money = round($salesPrice + $tax, $priceDecimal);
            $unitPrice = ($si && $qty > 0 && $salesPrice > 0) ? round($salesPrice / $qty, $priceDecimal + 2) : 0;
            if ($unitPrice == 0 && $si) {
                $unitPrice = (float)$si['unit_price'];
            }
            $goodsCode = $si ? $si['goods_code'] : $info['goods_code'];
            $goods = isset($goodsMap[$goodsCode]) ? $goodsMap[$goodsCode] : null;
            // 品号与功率：优先取销货单明细（由销货订单带入），为空时回退订单商品列表
            $power = $si && $si['remark'] !== null ? $si['remark'] : '';
            if (($power === '') && $si && isset($orderRemarkMap[$si['goods_code']])) {
                $power = $orderRemarkMap[$si['goods_code']];
            }
            $infoList[] = [
                'goods_code'   => $si ? $si['goods_code'] : $info['goods_code'],
                'goods_name'   => $goods ? $goods['goods_name'] : '',
                'spec'         => $goods ? $goods['goods_specification_id'] : '',
                'power'        => $power,
                'number'       => $qty,
                'unit_price'   => $unitPrice,
                'sales_price'  => $salesPrice,
                'tax'          => $tax,
                'money'        => $money,
                'remark'       => $si ? $si['goods_note'] : '',
                'customer_code'=> ($si && isset($customerCodes[$si['goods_code']])) ? $customerCodes[$si['goods_code']] : '',
                'unit_name'    => $si
                    ? (isset($unitMap[$si['goods_unit']]) ? $unitMap[$si['goods_unit']] : '')
                    : ($goods && isset($unitMap[$goods['unit_id']]) ? $unitMap[$goods['unit_id']] : ''),
            ];
            $totalNumber += $qty;
            $totalSalesPrice += $salesPrice;
            $totalTax += $tax;
            $totalMoney += $money;
            if ($si && !empty($customerCodes[$si['goods_code']])) {
                $skuCodes[$customerCodes[$si['goods_code']]] = true;
            }
        }

        // 快递信息（申请时填写，可多条）
        $expressRows = !empty($row['express_data']) ? json_decode($row['express_data'], true) : [];
        $expressRows = is_array($expressRows) ? $expressRows : [];
        $expressCompanies = [];
        $expressNos = [];
        foreach ($expressRows as $ex) {
            $expressCompanies[] = isset($ex['company']) && $ex['company'] !== '' ? $ex['company'] : (isset($ex['code']) ? $ex['code'] : '');
            $expressNos[] = isset($ex['no']) ? $ex['no'] : '';
        }

        $this->view->assign([
            'row'                   => $row,
            'company_name'          => $companyName,
            'company_address'       => $companyAddress,
            'customer_name'         => $customerName,
            'contact_name'          => $contactName,
            'contact_mobile'        => $contactMobile,
            'sale_user_name'        => $saleUserName,
            'sales_date'            => !empty($sales['purchase_time']) ? date('Y-m-d', $sales['purchase_time']) : '',
            'customer_codes'        => implode('、', array_keys($skuCodes)),
            'infoList'              => $infoList,
            'total_number'          => round($totalNumber, $numberDecimal),
            'total_sales_price'     => round($totalSalesPrice, $priceDecimal),
            'total_tax'             => round($totalTax, $priceDecimal),
            'total_money'           => round($totalMoney, $priceDecimal),
            'express_companies_text'=> implode('、', array_filter($expressCompanies)),
            'express_nos_text'      => implode('、', array_filter($expressNos)),
            'price_decimal'         => $priceDecimal,
            'number_decimal'        => $numberDecimal,
            'printTime'             => date('Y-m-d H:i:s'),
        ]);
        return $this->view->fetch('deliverynote');
    }

    /**
     * 同意出库（可调整本次出库数量，同意后立即执行出库扣减库存）
     */
    public function examine($ids = null)
    {
        if (empty($ids)) {
            $this->error('请选择要操作的数据');
        }
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('出库申请不存在');
        }
        if ($row['status'] != '0') {
            $this->error('该出库申请已被处理，请刷新后重试');
        }
        $sales = Db::name('kuerp_goods_sales')->where(['id' => $row['sales_id']])->field('id,purchase_code,examine_status')->find();
        if (!$sales) {
            $this->error('关联销货单不存在，请拒绝该申请');
        }
        if ($sales['examine_status'] != 1) {
            $this->error('关联销货单已反审核，请拒绝该申请');
        }

        // 合并实际出库数量：POST out_data/{sales_info_id} 缺省回退申请数量
        $applyInfoList = Db::name('kuerp_goods_outbound_apply_info')->where(['apply_id' => $row['id']])->select();
        if (empty($applyInfoList)) {
            $this->error('出库申请明细不存在');
        }
        $postOutData = $this->request->post('out_data/a');
        $finalOutData = [];
        foreach ($applyInfoList as $info) {
            $sid = $info['sales_info_id'];
            $qty = isset($postOutData[$sid]) && $postOutData[$sid] !== '' ? (float)$postOutData[$sid] : (float)$info['number'];
            if ($qty < 0) {
                $this->error('出库数量不能为负数');
            }
            if ($qty == 0) {
                continue;
            }
            // 以当前明细数据为准封顶（防申请后他人已出库/单据被编辑）
            $salesInfo = Db::name('kuerp_goods_sales_info')->where(['id' => $sid])->field('number,out_number')->find();
            if (!$salesInfo) {
                $this->error('销货单明细已变更（单据可能被编辑），请拒绝该申请，由计划部门重新申请');
            }
            $max_out = (float)$salesInfo['number'] - (isset($salesInfo['out_number']) ? (float)$salesInfo['out_number'] : 0);
            if ($qty > $max_out) {
                $this->error('出库数量不能超过剩余未出数量 ' . $max_out);
            }
            $finalOutData[$sid] = $qty;
        }
        if (empty($finalOutData)) {
            $this->error('本次出库数量不能全部为0');
        }

        $admin = Session::get('admin');
        $adminId = $admin ? (int)$admin['id'] : 0;

        Db::startTrans();
        try {
            // 条件更新抢占，防并发审批
            $updated = Db::name('kuerp_goods_outbound_apply')
                ->where(['id' => $row['id'], 'status' => '0'])
                ->update([
                    'status'           => '1',
                    'examine_admin_id' => $adminId,
                    'examine_time'     => time(),
                    'out_data'         => json_encode($finalOutData),
                    'updatetime'       => time(),
                ]);
            if (!$updated) {
                throw new Exception('该出库申请已被处理，请刷新后重试');
            }
            // 执行出库扣减库存（内部校验库存不足等会抛 HttpResponseException，由 runSaveOutbound 转普通异常统一回滚）
            $this->runSaveOutbound($row['sales_id'], $finalOutData);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('已同意出库，本次出库执行完成');
    }

    /**
     * 拒绝出库（必填原因）
     */
    public function refuse($ids = null)
    {
        if (empty($ids)) {
            $this->error('请选择要操作的数据');
        }
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('出库申请不存在');
        }
        if ($row['status'] != '0') {
            $this->error('该出库申请已被处理，请刷新后重试');
        }
        $reason = trim((string)$this->request->post('reason'));
        if ($reason === '') {
            $this->error('请填写拒绝原因');
        }

        $admin = Session::get('admin');
        $updated = Db::name('kuerp_goods_outbound_apply')
            ->where(['id' => $row['id'], 'status' => '0'])
            ->update([
                'status'           => '2',
                'refuse_reason'    => mb_substr($reason, 0, 255, 'utf-8'),
                'examine_admin_id' => $admin ? (int)$admin['id'] : 0,
                'examine_time'     => time(),
                'updatetime'       => time(),
            ]);
        if (!$updated) {
            $this->error('该出库申请已被处理，请刷新后重试');
        }
        $this->success('已拒绝该出库申请');
    }

    /**
     * 调用销货单出库控制器执行出库；FastAdmin 的 success/error 均以 HttpResponseException 抛出，
     * 成功(code=1)静默返回，失败(code=0)转为普通异常由上层统一回滚并提示
     */
    protected function runSaveOutbound($salesId, $outData)
    {
        try {
            $result = (new \app\admin\controller\kuerp\GoodsSalesOutbound())->saveOutbound($salesId, $outData);
        } catch (\think\exception\HttpResponseException $e) {
            $json = json_decode($e->getResponse()->getContent(), true);
            if (empty($json) || !isset($json['code']) || $json['code'] != 1) {
                $msg = isset($json['msg']) && $json['msg'] !== '' ? $json['msg'] : '未知原因';
                throw new Exception('销货出库执行失败：' . $msg);
            }
            return;
        }
        // 审核开关关闭时控制器以数组返回结果而非抛异常
        if (is_array($result) && isset($result['status']) && $result['status'] == 0) {
            $msg = isset($result['msg']) && $result['msg'] !== '' ? $result['msg'] : '未知原因';
            throw new Exception('销货出库执行失败：' . $msg);
        }
    }
}
