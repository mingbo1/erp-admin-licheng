<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\Session;

/**
 * 采购对账
 *
 * 采购勾选采购单 / 购货退货单生成对账单（退货单负数冲减，标记单据已对账）
 * → 采购提交 → 财务一审 → 审计/主管二审 → 已对账
 * → 上传发票（多张、部分开票）并登记账期 → 采购发起付款申请（kuerp/purchase_payment_apply）
 *
 * 规则对齐 `Desc/销货对账-需求规格.md`：
 *   - 退货单负数冲减，净额对账
 *   - 一审通过即锁定单据，二审驳回/撤销对账解锁
 *   - invoice_status 0未开票/1部分开票/2已开票，按累计开票金额派生
 *
 * @icon fa fa-check-square-o
 */
class PurchaseRecon extends Backend
{

    /**
     * @var \app\admin\model\kuerp\PurchaseRecon
     */
    protected $model = null;

    protected $noNeedRight = ['purchaselist', 'uploadfile', 'delfile', 'materials'];

    // 对账单状态
    const STATUS_DRAFT   = '0'; // 待对账/草稿
    const STATUS_FINANCE = '1'; // 待财务审核（已提交）
    const STATUS_SECOND  = '2'; // 待二审（财务一审通过、单据已锁定）
    const STATUS_DONE    = '3'; // 已对账（二审通过）
    const STATUS_REJECT  = '4'; // 已驳回

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\kuerp\PurchaseRecon;
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);
        $this->assignconfig('nowmenu', $setData['nowmenu']);
        $this->assignconfig('remindDays', $this->getRemindDays($setData['setList']));
        $this->assignconfig('leaderMoney', $this->getLeaderMoney($setData['setList']));
    }

    protected function getRemindDays($setList = null)
    {
        if ($setList === null) {
            $setList = \addons\kuerp\library\Goods::getSetData()['setList'];
        }
        $v = isset($setList['purchase_payment_remind_days']['value']) ? $setList['purchase_payment_remind_days']['value'] : 7;
        return $v === '' ? 7 : (int)$v;
    }

    protected function getLeaderMoney($setList = null)
    {
        if ($setList === null) {
            $setList = \addons\kuerp\library\Goods::getSetData()['setList'];
        }
        return isset($setList['purchase_payment_leader_money']['value']) ? (float)$setList['purchase_payment_leader_money']['value'] : 0;
    }

    // ==================================================================
    // 对账单
    // ==================================================================

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->order('id', 'desc')
                ->paginate($limit);

            $rows = $list->items();
            $reconIds = array_column($rows, 'id');

            $adminIds = array_filter(array_unique(array_merge(
                array_column($rows, 'preparation_id'),
                array_column($rows, 'examine_id'),
                array_column($rows, 'second_examine_id')
            )));
            $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];

            $invoiceMap = [];
            if (!empty($reconIds)) {
                $invoices = Db::name('kuerp_purchase_recon_invoice')
                    ->where(['recon_id' => ['in', $reconIds]])
                    ->field('recon_id,min(due_date) as due_date,max(due_date) as last_due')
                    ->group('recon_id')
                    ->select();
                foreach ($invoices as $inv) {
                    $invoiceMap[$inv['recon_id']] = $inv;
                }
            }

            $applyMap = [];
            if (!empty($reconIds)) {
                $applyRows = Db::name('kuerp_purchase_payment_apply_info')->alias('i')
                    ->join('kuerp_purchase_payment_apply a', 'a.id=i.apply_id')
                    ->where(['i.recon_id' => ['in', $reconIds]])
                    ->field('i.recon_id,a.apply_code')
                    ->order('a.id', 'desc')
                    ->select();
                foreach ($applyRows as $applyRow) {
                    if (!isset($applyMap[$applyRow['recon_id']])) {
                        $applyMap[$applyRow['recon_id']] = $applyRow['apply_code'];
                    }
                }
            }

            $remindDays = $this->getRemindDays();
            $today = strtotime(date('Y-m-d'));

            foreach ($rows as $k => $row) {
                $rows[$k]['preparation_name'] = isset($adminMap[$row['preparation_id']]) ? $adminMap[$row['preparation_id']] : '';
                $rows[$k]['examine_name'] = isset($adminMap[$row['examine_id']]) ? $adminMap[$row['examine_id']] : '';
                $rows[$k]['second_examine_name'] = isset($adminMap[$row['second_examine_id']]) ? $adminMap[$row['second_examine_id']] : '';
                $rows[$k]['apply_code'] = isset($applyMap[$row['id']]) ? $applyMap[$row['id']] : '';

                $dueDate = isset($invoiceMap[$row['id']]['due_date']) ? (int)$invoiceMap[$row['id']]['due_date'] : 0;
                $rows[$k]['due_date'] = $dueDate;
                $rows[$k]['due_date_text'] = $dueDate ? date('Y-m-d', $dueDate) : '';
                $rows[$k]['days_left'] = $dueDate ? (int)floor(($dueDate - $today) / 86400) : null;
                if ($row['pay_status'] == '2') {
                    $rows[$k]['term_state'] = 3;
                } elseif ($dueDate && $today > $dueDate) {
                    $rows[$k]['term_state'] = 2;
                } elseif ($dueDate && $dueDate - $remindDays * 86400 <= $today) {
                    $rows[$k]['term_state'] = 1;
                } else {
                    $rows[$k]['term_state'] = 0;
                }
            }

            return json(['total' => $list->total(), 'rows' => $rows]);
        }
        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        return $this->view->fetch();
    }

    /**
     * 新增对账单
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (!$params) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $supplyerCode = isset($params['supplyer_code']) ? trim($params['supplyer_code']) : '';
            $remark = isset($params['remark']) ? trim($params['remark']) : '';
            $periodStart = $this->parseDate(isset($params['period_start']) ? $params['period_start'] : '');
            $periodEnd = $this->parseDate(isset($params['period_end']) ? $params['period_end'] : '');
            $purchaseIds = $this->request->post('purchase_ids', '');
            $exchangeIds = $this->request->post('exchange_ids', '');

            $supplier = $this->checkSupplier($supplyerCode);
            $purchases = $this->checkPurchases($purchaseIds, $supplyerCode, 0);
            $exchanges = $this->checkExchanges($exchangeIds, $supplyerCode, 0);
            if (empty($purchases) && empty($exchanges)) {
                $this->error('请勾选需要对账的采购单或购货退货单');
            }

            $admin = Session::get('admin');
            $reconCode = \addons\kuerp\library\Goods::getCodes('', 30);
            $totals = $this->calcTotals($purchases, $exchanges);

            Db::startTrans();
            try {
                $reconId = Db::name('kuerp_purchase_recon')->insertGetId([
                    'recon_code'      => $reconCode,
                    'supplyer_code'   => $supplier['supplyer_code'],
                    'supplyer_name'   => $supplier['supplyer_name'],
                    'period_start'    => $periodStart,
                    'period_end'      => $periodEnd,
                    'purchase_count'  => $totals['purchase_count'],
                    'return_count'    => $totals['return_count'],
                    'total_number'    => $totals['total_number'],
                    'total_money'     => $totals['total_money'],
                    'status'          => self::STATUS_DRAFT,
                    'invoice_status'  => '0',
                    'invoice_amount'  => 0,
                    'pay_status'      => '0',
                    'remark'          => $remark,
                    'preparation_id'  => $admin['id'],
                    'operation_id'    => $admin['id'],
                    'createtime'      => time(),
                    'updatetime'      => time(),
                ]);
                $this->saveDocs($reconId, $reconCode, $purchases, $exchanges, 0);
                $this->writeLog($reconId, '创建对账单',
                    '采购单 ' . $totals['purchase_count'] . ' 张、退货单 ' . $totals['return_count'] . ' 张', $admin['id']);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('对账单创建成功', null, ['id' => $reconId, 'recon_code' => $reconCode]);
        }

        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        $this->view->assign('periodStart', date('Y-m-01'));
        $this->view->assign('periodEnd', date('Y-m-t'));
        return $this->view->fetch();
    }

    /**
     * 编辑对账单（草稿 / 已驳回）
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!in_array($row['status'], [self::STATUS_DRAFT, self::STATUS_REJECT])) {
            $this->error('只有待对账或已驳回的对账单可以编辑');
        }

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $supplyerCode = isset($params['supplyer_code']) ? trim($params['supplyer_code']) : $row['supplyer_code'];
            $remark = isset($params['remark']) ? trim($params['remark']) : '';
            $periodStart = $this->parseDate(isset($params['period_start']) ? $params['period_start'] : '');
            $periodEnd = $this->parseDate(isset($params['period_end']) ? $params['period_end'] : '');
            $purchaseIds = $this->request->post('purchase_ids', '');
            $exchangeIds = $this->request->post('exchange_ids', '');

            if ($supplyerCode != $row['supplyer_code']) {
                $this->error('对账单的供应商不可修改');
            }
            $supplier = $this->checkSupplier($supplyerCode);
            $purchases = $this->checkPurchases($purchaseIds, $supplyerCode, $row['id']);
            $exchanges = $this->checkExchanges($exchangeIds, $supplyerCode, $row['id']);
            if (empty($purchases) && empty($exchanges)) {
                $this->error('请勾选需要对账的采购单或购货退货单');
            }

            $admin = Session::get('admin');
            $totals = $this->calcTotals($purchases, $exchanges);

            $oldDocs = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $row['id']])->select();
            $newKeys = [];
            foreach ($purchases as $doc) {
                $newKeys['1_' . $doc['id']] = true;
            }
            foreach ($exchanges as $doc) {
                $newKeys['2_' . $doc['id']] = true;
            }

            Db::startTrans();
            try {
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id']])->update([
                    'supplyer_name'  => $supplier['supplyer_name'],
                    'period_start'   => $periodStart,
                    'period_end'     => $periodEnd,
                    'purchase_count' => $totals['purchase_count'],
                    'return_count'   => $totals['return_count'],
                    'total_number'   => $totals['total_number'],
                    'total_money'    => $totals['total_money'],
                    'remark'         => $remark,
                    'operation_id'   => $admin['id'],
                    'updatetime'     => time(),
                ]);
                // 移除不再包含的单据
                foreach ($oldDocs as $oldDoc) {
                    $key = $oldDoc['doc_type'] . '_' . $oldDoc['purchase_id'];
                    if (isset($newKeys[$key])) {
                        continue;
                    }
                    $this->syncSource($oldDoc['doc_type'], [$oldDoc['purchase_id']], 0, '', '0');
                    Db::name('kuerp_purchase_recon_info')->where(['id' => $oldDoc['id']])->delete();
                }
                $this->saveDocs($row['id'], $row['recon_code'], $purchases, $exchanges, 0);
                $this->writeLog($row['id'], '编辑对账单',
                    '采购单 ' . $totals['purchase_count'] . ' 张、退货单 ' . $totals['return_count'] . ' 张', $admin['id']);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('保存成功');
        }

        $this->view->assign('row', $row);
        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        $this->view->assign('periodStart', $row['period_start'] ? date('Y-m-d', $row['period_start']) : date('Y-m-01'));
        $this->view->assign('periodEnd', $row['period_end'] ? date('Y-m-d', $row['period_end']) : date('Y-m-t'));
        $this->view->assign('reconInfo', Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $row['id']])->order('doc_type asc,id asc')->select());
        return $this->view->fetch();
    }

    /**
     * 删除对账单（待对账 / 已驳回，且未进入付款流程）
     */
    public function del($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $ids ?: $this->request->post('ids');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$ids))));
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $list = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $ids]])->select();
        foreach ($list as $row) {
            if (!in_array($row['status'], [self::STATUS_DRAFT, self::STATUS_REJECT])) {
                $this->error('对账单' . $row['recon_code'] . '已提交/已审核，不能删除');
            }
            if ($row['pay_status'] != '0') {
                $this->error('对账单' . $row['recon_code'] . '已进入付款流程，不能删除');
            }
        }

        Db::startTrans();
        try {
            foreach ($list as $row) {
                Db::name('kuerp_goods_purchase')->where(['recon_id' => $row['id']])
                    ->update(['recon_id' => 0, 'recon_code' => '', 'recon_status' => '0']);
                Db::name('kuerp_goods_exchange')->where(['recon_id' => $row['id']])
                    ->update(['recon_id' => 0, 'recon_code' => '', 'recon_status' => '0']);
                $encLogs = Db::name('kuerp_enclosure_log')->where(['order_id' => $row['id'], 'type' => ['in', ['8', '9']]])->select();
                if ($encLogs) {
                    $encIds = array_filter(array_unique(array_column($encLogs, 'enclosure_id')));
                    Db::name('kuerp_enclosure_log')->where(['id' => ['in', array_column($encLogs, 'id')]])->delete();
                    if ($encIds) {
                        Db::name('kuerp_enclosure')->where(['id' => ['in', $encIds]])->delete();
                    }
                }
                Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $row['id']])->delete();
                Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $row['id']])->delete();
                Db::name('kuerp_purchase_recon_log')->where(['recon_id' => $row['id']])->delete();
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id']])->delete();
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    /**
     * 详情
     */
    public function detail($ids = null)
    {
        $row = Db::name('kuerp_purchase_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');
        $row['examine_name'] = $row['examine_id'] ? Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname') : '';
        $row['second_examine_name'] = $row['second_examine_id'] ? Db::name('admin')->where(['id' => $row['second_examine_id']])->value('nickname') : '';
        $statusList = $this->model->getStatusList();
        $invoiceStatusList = $this->model->getInvoiceStatusList();
        $payStatusList = $this->model->getPayStatusList();
        $row['status_text'] = isset($statusList[$row['status']]) ? $statusList[$row['status']] : '';
        $row['invoice_status_text'] = isset($invoiceStatusList[$row['invoice_status']]) ? $invoiceStatusList[$row['invoice_status']] : '';
        $row['pay_status_text'] = isset($payStatusList[$row['pay_status']]) ? $payStatusList[$row['pay_status']] : '';

        $reconInfo = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $row['id']])->order('doc_type asc,purchase_time asc,id asc')->select();
        foreach ($reconInfo as $k => $info) {
            $reconInfo[$k]['doc_type_text'] = $info['doc_type'] == '2' ? '购货退货单' : '采购单';
            $reconInfo[$k]['doc_code'] = $info['purchase_code'];
            $reconInfo[$k]['is_return'] = $info['doc_type'] == '2';
        }

        // 资料（合同/入库单/退货单/对账单/发票）
        $materialsMap = \app\admin\model\kuerp\PurchaseRecon::materials($row['id']);
        $materials = isset($materialsMap[$row['id']]) ? $materialsMap[$row['id']] : null;

        // 发票与账期
        $invoices = Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $row['id']])->order('id asc')->select();
        $today = strtotime(date('Y-m-d'));
        $remindDays = $this->getRemindDays();
        foreach ($invoices as $ik => $invoice) {
            $invoices[$ik]['file'] = $invoice['enclosure_id'] ? $this->enclosureOne($invoice['enclosure_id']) : null;
            $invoices[$ik]['invoice_date_text'] = $invoice['invoice_date'] ? date('Y-m-d', $invoice['invoice_date']) : '';
            $invoices[$ik]['upload_date_text'] = $invoice['upload_date'] ? date('Y-m-d', $invoice['upload_date']) : '';
            $invoices[$ik]['due_date_text'] = $invoice['due_date'] ? date('Y-m-d', $invoice['due_date']) : '';
            $daysLeft = $invoice['due_date'] ? (int)floor(($invoice['due_date'] - $today) / 86400) : null;
            $invoices[$ik]['days_left'] = $daysLeft;
            if ($row['pay_status'] == '2') {
                $invoices[$ik]['term_state'] = 3;
            } elseif ($invoice['due_date'] && $today > $invoice['due_date']) {
                $invoices[$ik]['term_state'] = 2;
            } elseif ($invoice['due_date'] && $invoice['due_date'] - $remindDays * 86400 <= $today) {
                $invoices[$ik]['term_state'] = 1;
            } else {
                $invoices[$ik]['term_state'] = 0;
            }
        }

        // 付款申请
        $applyRows = Db::name('kuerp_purchase_payment_apply_info')->alias('i')
            ->join('kuerp_purchase_payment_apply a', 'a.id=i.apply_id')
            ->where(['i.recon_id' => $row['id']])
            ->field('a.id,a.apply_code,a.status,a.total_money,a.apply_time,a.pay_time')
            ->order('a.id desc')
            ->select();
        $applyStatusList = (new \app\admin\model\kuerp\PurchasePaymentApply())->getStatusList();
        foreach ($applyRows as $ak => $apply) {
            $applyRows[$ak]['status_text'] = isset($applyStatusList[$apply['status']]) ? $applyStatusList[$apply['status']] : '';
            $applyRows[$ak]['apply_time_text'] = $apply['apply_time'] ? date('Y-m-d H:i', $apply['apply_time']) : '';
            $applyRows[$ak]['pay_time_text'] = $apply['pay_time'] ? date('Y-m-d H:i', $apply['pay_time']) : '';
        }

        // 操作日志
        $logs = Db::name('kuerp_purchase_recon_log')->where(['recon_id' => $row['id']])->order('id desc')->select();
        $adminIds = array_filter(array_unique(array_column($logs, 'admin_id')));
        $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];
        foreach ($logs as $lk => $log) {
            $logs[$lk]['admin_name'] = isset($adminMap[$log['admin_id']]) ? $adminMap[$log['admin_id']] : '';
            $logs[$lk]['time_text'] = $log['createtime'] ? date('Y-m-d H:i:s', $log['createtime']) : '';
        }

        // 商品行明细（与打印页/导出同一份 buildTemplateData：商品名称/型号规格/数量/含税单价/未税单价/税点/
        // 采购合计金额/采购费用/总金额/备注，采购费用与总金额只在每个单据首行显示）
        $this->assignItemRows($row);

        $remainingInvoice = round((float)$row['total_money'] - (float)$row['invoice_amount'], 4);

        $this->view->assign('row', $row);
        $this->view->assign('reconInfo', $reconInfo);
        $this->view->assign('materials', $materials);
        $this->view->assign('invoices', $invoices);
        $this->view->assign('applyRows', $applyRows);
        $this->view->assign('logs', $logs);
        $this->view->assign('remindDays', $remindDays);
        $this->view->assign('remainingInvoice', $remainingInvoice);
        return $this->view->fetch();
    }

    /**
     * 提交财务审核（提交前按源单据刷新数据）
     */
    public function submit($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $ids ?: $this->request->post('ids');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$ids))));
        if (empty($ids)) {
            $this->error('请选择要提交的对账单');
        }
        $list = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $ids], 'status' => ['in', [self::STATUS_DRAFT, self::STATUS_REJECT]]])->select();
        if (empty($list)) {
            $this->error('请选择待对账或已驳回状态的对账单');
        }
        $admin = Session::get('admin');
        Db::startTrans();
        try {
            foreach ($list as $row) {
                $this->refreshDocs($row['id']);
                $count = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $row['id']])->count();
                if ($count <= 0) {
                    throw new Exception('对账单' . $row['recon_code'] . '没有可对账的单据');
                }
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id']])
                    ->update(['status' => self::STATUS_FINANCE, 'reject_reason' => '', 'operation_id' => $admin['id'], 'updatetime' => time()]);
                $this->writeLog($row['id'], '提交财务审核', '', $admin['id']);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('已提交财务审核');
    }

    /**
     * 财务一审（通过 / 驳回）
     */
    public function audit($ids = null)
    {
        $row = Db::name('kuerp_purchase_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            if ($row['status'] != self::STATUS_FINANCE) {
                $this->error('该对账单不是待财务审核状态');
            }
            $result = (int)$this->request->post('result', 1);
            $remark = trim((string)$this->request->post('remark', ''));
            $admin = Session::get('admin');
            if ($result == 1) {
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id'], 'status' => self::STATUS_FINANCE])->update([
                    'status'           => self::STATUS_SECOND,
                    'examine_id'       => $admin['id'],
                    'examine_time'     => time(),
                    'reject_reason'    => '',
                    'operation_id'     => $admin['id'],
                    'updatetime'       => time(),
                ]);
                $this->lockDocs($row['id'], '1');
                $this->writeLog($row['id'], '财务一审通过', $remark, $admin['id']);
                $this->success('一审通过，已转二审');
            } else {
                if ($remark === '') {
                    $this->error('请填写驳回原因');
                }
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id'], 'status' => self::STATUS_FINANCE])->update([
                    'status'           => self::STATUS_REJECT,
                    'examine_id'       => $admin['id'],
                    'examine_time'     => time(),
                    'reject_reason'    => mb_substr($remark, 0, 250),
                    'operation_id'     => $admin['id'],
                    'updatetime'       => time(),
                ]);
                $this->lockDocs($row['id'], '0');
                $this->writeLog($row['id'], '财务驳回', $remark, $admin['id']);
                $this->success('已驳回');
            }
        }
        $this->view->assign('row', $row);
        $this->view->assign('step', 'finance');
        $this->view->assign('stepName', '财务一审');
        $this->assignItemRows($row);
        $materials = \app\admin\model\kuerp\PurchaseRecon::materials($row['id']);
        $this->view->assign('materials', isset($materials[$row['id']]) ? $materials[$row['id']] : null);
        return $this->view->fetch();
    }

    /**
     * 二审（审计/主管）：通过 / 驳回
     */
    public function secondaudit($ids = null)
    {
        $row = Db::name('kuerp_purchase_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            if ($row['status'] != self::STATUS_SECOND) {
                $this->error('该对账单不是待二审状态');
            }
            $result = (int)$this->request->post('result', 1);
            $remark = trim((string)$this->request->post('remark', ''));
            $admin = Session::get('admin');
            if ($result == 1) {
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id'], 'status' => self::STATUS_SECOND])->update([
                    'status'              => self::STATUS_DONE,
                    'second_examine_id'   => $admin['id'],
                    'second_examine_time' => time(),
                    'reject_reason'       => '',
                    'operation_id'        => $admin['id'],
                    'updatetime'          => time(),
                ]);
                $this->lockDocs($row['id'], '2');
                $this->writeLog($row['id'], '二审通过', $remark, $admin['id']);
                $this->success('二审通过，对账完成');
            } else {
                if ($remark === '') {
                    $this->error('请填写驳回原因');
                }
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id'], 'status' => self::STATUS_SECOND])->update([
                    'status'              => self::STATUS_REJECT,
                    'second_examine_id'   => $admin['id'],
                    'second_examine_time' => time(),
                    'reject_reason'       => mb_substr($remark, 0, 250),
                    'operation_id'        => $admin['id'],
                    'updatetime'          => time(),
                ]);
                $this->lockDocs($row['id'], '0');
                $this->writeLog($row['id'], '二审驳回', $remark, $admin['id']);
                $this->success('已驳回');
            }
        }
        $this->view->assign('row', $row);
        $this->view->assign('step', 'second');
        $this->view->assign('stepName', '二审');
        $this->assignItemRows($row);
        $materials = \app\admin\model\kuerp\PurchaseRecon::materials($row['id']);
        $this->view->assign('materials', isset($materials[$row['id']]) ? $materials[$row['id']] : null);
        return $this->view->fetch('audit');
    }

    /**
     * 下发「对账单明细」商品行到视图（详情页 / 财务审核弹窗共用，数据来自 buildTemplateData）
     */
    protected function assignItemRows($row)
    {
        $templateData = $this->buildTemplateData($row);
        $this->view->assign('itemRows', $templateData['rows']);
        $this->view->assign('itemSumLine', $templateData['sum_line']);
        $this->view->assign('itemSumTotal', $templateData['sum_total']);
        $this->view->assign('itemPriceDecimal', $templateData['price_decimal']);
        $this->view->assign('itemNumberDecimal', $templateData['number_decimal']);
    }

    /**
     * 撤销对账（未开票、未付款时）
     */
    public function revoke($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $ids ?: $this->request->post('ids');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$ids))));
        if (empty($ids)) {
            $this->error('请选择要撤销的对账单');
        }
        $list = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $ids]])->select();
        $admin = Session::get('admin');
        Db::startTrans();
        try {
            foreach ($list as $row) {
                if (!in_array($row['status'], [self::STATUS_SECOND, self::STATUS_DONE])) {
                    throw new Exception('对账单' . $row['recon_code'] . '当前状态不能撤销');
                }
                if ($row['invoice_status'] != '0') {
                    throw new Exception('对账单' . $row['recon_code'] . '已开票，不能撤销');
                }
                if ($row['pay_status'] != '0') {
                    throw new Exception('对账单' . $row['recon_code'] . '已进入付款流程，不能撤销');
                }
                Db::name('kuerp_purchase_recon')->where(['id' => $row['id']])->update([
                    'status'              => self::STATUS_DRAFT,
                    'examine_id'          => 0,
                    'examine_time'        => null,
                    'second_examine_id'   => 0,
                    'second_examine_time' => null,
                    'reject_reason'       => '',
                    'operation_id'        => $admin['id'],
                    'updatetime'          => time(),
                ]);
                $this->lockDocs($row['id'], '0');
                $this->writeLog($row['id'], '撤销对账', '', $admin['id']);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('已撤销对账');
    }

    /**
     * 上传发票 + 新增账期记录（支持多张、部分开票）
     */
    public function invoice($ids = null)
    {
        $row = Db::name('kuerp_purchase_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            if ($row['status'] != self::STATUS_DONE) {
                $this->error('对账单需二审通过后才能上传发票');
            }
            if ((float)$row['total_money'] <= 0) {
                $this->error('对账净额不大于 0，无需开票');
            }
            $enclosureId = (int)$this->request->post('enclosure_id', 0);
            $invoiceNo = trim((string)$this->request->post('invoice_no', ''));
            $invoiceAmount = (float)$this->request->post('invoice_amount', 0);
            $invoiceDate = $this->parseDate($this->request->post('invoice_date', ''));
            $termDays = (int)$this->request->post('term_days', 0);

            if ($enclosureId <= 0) {
                $this->error('请上传发票文件');
            }
            if ($invoiceAmount <= 0) {
                $this->error('请填写开票金额');
            }
            if ($termDays < 0) {
                $this->error('账期天数不能小于 0');
            }
            if ($invoiceNo !== '' && Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $row['id'], 'invoice_no' => $invoiceNo])->find()) {
                $this->error('该对账单内发票号已存在');
            }
            $invoiced = (float)Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $row['id']])->sum('invoice_amount');
            $remain = round((float)$row['total_money'] - $invoiced, 4);
            if ($invoiceAmount > $remain + 0.01) {
                $this->error('本次开票金额不能超过剩余可开票金额￥' . $remain);
            }
            $file = Db::name('kuerp_enclosure')->where(['id' => $enclosureId])->find();
            if (!$file) {
                $this->error('发票文件不存在，请重新上传');
            }

            $admin = Session::get('admin');
            $uploadDate = strtotime(date('Y-m-d'));
            $dueDate = $uploadDate + $termDays * 86400;

            Db::startTrans();
            try {
                Db::name('kuerp_purchase_recon_invoice')->insert([
                    'recon_id'       => $row['id'],
                    'enclosure_id'   => $enclosureId,
                    'invoice_no'     => mb_substr($invoiceNo, 0, 100),
                    'invoice_amount' => $invoiceAmount,
                    'invoice_date'   => $invoiceDate ?: $uploadDate,
                    'upload_date'    => $uploadDate,
                    'term_days'      => $termDays,
                    'due_date'       => $dueDate,
                    'admin_id'       => $admin['id'],
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]);
                $exists = Db::name('kuerp_enclosure_log')->where(['enclosure_id' => $enclosureId, 'type' => '9'])->find();
                if (!$exists) {
                    Db::name('kuerp_enclosure_log')->insert([
                        'enclosure_id'  => $enclosureId,
                        'order_id'      => $row['id'],
                        'purchase_code' => $row['recon_code'],
                        'type'          => '9',
                    ]);
                }
                $this->recomputeInvoice($row['id']);
                $this->writeLog($row['id'], '上传发票',
                    '发票号' . $invoiceNo . '，金额' . $invoiceAmount . '，账期' . $termDays . '天，到期日' . date('Y-m-d', $dueDate), $admin['id']);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('发票与账期保存成功');
        }

        $invoices = Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $row['id']])->order('id asc')->select();
        $invoiced = 0;
        foreach ($invoices as $k => $invoice) {
            $invoices[$k]['file'] = $invoice['enclosure_id'] ? $this->enclosureOne($invoice['enclosure_id']) : null;
            $invoices[$k]['invoice_date_text'] = $invoice['invoice_date'] ? date('Y-m-d', $invoice['invoice_date']) : '';
            $invoices[$k]['due_date_text'] = $invoice['due_date'] ? date('Y-m-d', $invoice['due_date']) : '';
            $invoiced += (float)$invoice['invoice_amount'];
        }
        $this->view->assign('row', $row);
        $this->view->assign('invoices', $invoices);
        $this->view->assign('invoiced', $invoiced);
        $this->view->assign('remaining', round((float)$row['total_money'] - $invoiced, 4));
        $this->view->assign('defaultTermDays', 30);
        $this->view->assign('remindDays', $this->getRemindDays());
        return $this->view->fetch();
    }

    // ==================================================================
    // 工作台 / 统计
    // ==================================================================

    /**
     * 候选单据（新增/编辑对账单页面）：采购单 + 购货退货单
     */
    public function purchaselist()
    {
        $supplyerCode = trim((string)$this->request->request('supplyer_code', ''));
        $periodStart = $this->parseDate($this->request->request('period_start', ''));
        $periodEnd = $this->parseDate($this->request->request('period_end', ''));
        $reconId = (int)$this->request->request('recon_id', 0);
        if ($supplyerCode === '') {
            return json(['total' => 0, 'rows' => []]);
        }

        $purchaseQuery = Db::name('kuerp_goods_purchase')
            ->where(['purchase_type' => '1', 'examine_status' => '1', 'supplyer_code' => $supplyerCode]);
        $exchangeQuery = Db::name('kuerp_goods_exchange')
            ->where(['examine_status' => '1', 'supplyer_code' => $supplyerCode]);
        foreach ([$purchaseQuery, $exchangeQuery] as $query) {
            $query->where(function ($q) use ($reconId) {
                if ($reconId > 0) {
                    $q->where('recon_id', 0)->whereOr('recon_id', $reconId);
                } else {
                    $q->where('recon_id', 0);
                }
            });
            if ($periodStart) {
                $query->where('purchase_time', '>=', $periodStart);
            }
            if ($periodEnd) {
                $query->where('purchase_time', '<=', $periodEnd + 86399);
            }
        }

        $purchases = $purchaseQuery->field('id,purchase_code,purchase_time,number,real_money,pay_money,recon_id')
            ->order('purchase_time asc,id asc')->limit(500)->select();
        $exchanges = $exchangeQuery->field('id,purchase_code,purchase_time,number,retreat_number,real_money,retreat_money,recon_id')
            ->order('purchase_time asc,id asc')->limit(500)->select();

        $statusMap = \app\admin\model\kuerp\GoodsPurchase::storageStatusMap(array_column($purchases, 'purchase_code'));
        $rows = [];
        foreach ($purchases as $item) {
            $rows[] = [
                'doc_type'           => '1',
                'doc_id'             => $item['id'],
                'doc_code'           => $item['purchase_code'],
                'doc_time'           => $item['purchase_time'],
                'doc_time_text'      => $item['purchase_time'] ? date('Y-m-d', $item['purchase_time']) : '',
                'number'             => $item['number'],
                'real_money'         => $item['real_money'],
                'paid_money'         => $item['pay_money'],
                'storage_status'     => isset($statusMap[$item['purchase_code']]) ? $statusMap[$item['purchase_code']] : 0,
                'checked'            => ($reconId > 0 && $item['recon_id'] == $reconId),
            ];
        }
        foreach ($exchanges as $item) {
            $rows[] = [
                'doc_type'           => '2',
                'doc_id'             => $item['id'],
                'doc_code'           => $item['purchase_code'],
                'doc_time'           => $item['purchase_time'],
                'doc_time_text'      => $item['purchase_time'] ? date('Y-m-d', $item['purchase_time']) : '',
                // 退货单按「实际退货数量」冲减，主表 number 是原采购数量
                'number'             => -1 * (float)$item['retreat_number'],
                'real_money'         => -1 * (float)$item['real_money'],
                'paid_money'         => -1 * (float)$item['retreat_money'],
                'storage_status'     => null,
                'checked'            => ($reconId > 0 && $item['recon_id'] == $reconId),
            ];
        }
        usort($rows, function ($a, $b) {
            return $a['doc_time'] == $b['doc_time'] ? $a['doc_id'] <=> $b['doc_id'] : $a['doc_time'] <=> $b['doc_time'];
        });
        return json(['total' => count($rows), 'rows' => $rows]);
    }

    /**
     * 全供应商单据列表（采购单 + 退货单，含入库/对账/开票/付款状态）
     */
    public function purchases()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $filter = json_decode((string)$this->request->request('filter', '{}'), true);
            $filter = is_array($filter) ? $filter : [];
            $page = max(1, (int)$this->request->request('page', 1));
            $limit = (int)$this->request->request('limit', 20);
            if ($limit <= 0) {
                $limit = 20;
            }
            $docType = isset($filter['doc_type']) ? (string)$filter['doc_type'] : '';

            // 对账 / 开票状态对应的对账单ID
            $reconState = isset($filter['recon_state']) ? (string)$filter['recon_state'] : '';
            $invoiceState = isset($filter['invoice_state']) ? (string)$filter['invoice_state'] : '';
            $reconIdsByState = null;
            if ($reconState !== '') {
                if ($reconState === '0') {
                    $reconIdsByState = []; // 未对账单独处理
                } else {
                    $reconIdsByState = Db::name('kuerp_purchase_recon')->where(['status' => $reconState])->column('id');
                    if (empty($reconIdsByState)) {
                        return json(['total' => 0, 'rows' => []]);
                    }
                }
            }
            $reconIdsByInvoice = null;
            if ($invoiceState !== '') {
                $reconIdsByInvoice = Db::name('kuerp_purchase_recon')->where(['invoice_status' => $invoiceState])->column('id');
                if ($invoiceState !== '0' && empty($reconIdsByInvoice)) {
                    return json(['total' => 0, 'rows' => []]);
                }
            }

            $build = function ($table, $isReturn) use ($filter, $reconState, $reconIdsByState, $invoiceState, $reconIdsByInvoice) {
                $query = Db::name($table)->where(['examine_status' => '1']);
                if ($isReturn) {
                    // 购货退货单无 purchase_type 过滤
                } else {
                    $query->where('purchase_type', '1');
                }
                $this->applyDocFilters($query, $filter);
                if ($reconState !== '') {
                    if ($reconState === '0') {
                        $query->where('recon_id', 0);
                    } else {
                        $query->where('recon_id', 'in', $reconIdsByState);
                    }
                }
                if ($invoiceState !== '') {
                    if ($invoiceState === '0') {
                        $query->where(function ($q) use ($reconIdsByInvoice) {
                            $q->where('recon_id', 0);
                            if (!empty($reconIdsByInvoice)) {
                                $q->whereOr('recon_id', 'in', $reconIdsByInvoice);
                            }
                        });
                    } else {
                        $query->where('recon_id', 'in', $reconIdsByInvoice);
                    }
                }
                if (isset($filter['pay_state']) && $filter['pay_state'] !== '') {
                    $query->where($isReturn ? 'retreat_status' : 'pay_status', (string)$filter['pay_state']);
                }
                $fields = $isReturn
                    ? 'id,purchase_code,purchase_time,supplyer_code,number,retreat_number,real_money,retreat_money as pay_money,retreat_status as pay_status,recon_id,recon_code,examine_status'
                    : 'id,purchase_code,purchase_time,supplyer_code,number,real_money,pay_money,pay_status,recon_id,recon_code,examine_status';
                return $query->field($fields)->select();
            };

            $purchaseRows = ($docType === '' || $docType === '1') ? $build('kuerp_goods_purchase', false) : [];
            $exchangeRows = ($docType === '' || $docType === '2') ? $build('kuerp_goods_exchange', true) : [];
            foreach ($purchaseRows as $k => $row) {
                $purchaseRows[$k]['doc_type'] = '1';
                $purchaseRows[$k]['doc_id'] = $row['id'];
                $purchaseRows[$k]['doc_code'] = $row['purchase_code'];
                $purchaseRows[$k]['doc_time'] = $row['purchase_time'];
            }
            foreach ($exchangeRows as $k => $row) {
                $exchangeRows[$k]['doc_type'] = '2';
                $exchangeRows[$k]['doc_id'] = $row['id'];
                $exchangeRows[$k]['doc_code'] = $row['purchase_code'];
                $exchangeRows[$k]['doc_time'] = $row['purchase_time'];
                $exchangeRows[$k]['number'] = -1 * (float)$row['retreat_number'];
                $exchangeRows[$k]['real_money'] = -1 * (float)$row['real_money'];
                $exchangeRows[$k]['pay_money'] = -1 * (float)$row['pay_money'];
            }
            $rows = array_merge($purchaseRows, $exchangeRows);
            usort($rows, function ($a, $b) {
                return $a['doc_time'] == $b['doc_time'] ? $b['doc_id'] <=> $a['doc_id'] : $b['doc_time'] <=> $a['doc_time'];
            });
            $total = count($rows);
            $rows = array_slice($rows, ($page - 1) * $limit, $limit);

            // 装饰
            $storageMap = \app\admin\model\kuerp\GoodsPurchase::storageStatusMap(array_column($purchaseRows, 'purchase_code'));
            $reconIds = array_values(array_filter(array_unique(array_column($rows, 'recon_id'))));
            $reconMap = [];
            if (!empty($reconIds)) {
                $reconRows = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $reconIds]])->select();
                foreach ($reconRows as $reconRow) {
                    $reconMap[$reconRow['id']] = $reconRow;
                }
            }
            $supplierCodes = array_values(array_filter(array_unique(array_column($rows, 'supplyer_code'))));
            $supplierMap = $supplierCodes ? Db::name('kuerp_suppliers')->where(['supplyer_code' => ['in', $supplierCodes]])->column('supplyer_name', 'supplyer_code') : [];
            $reconStatusList = $this->model->getStatusList();

            foreach ($rows as $k => $row) {
                $rows[$k]['doc_time_text'] = $row['doc_time'] ? date('Y-m-d', $row['doc_time']) : '';
                $rows[$k]['supplyer_name'] = isset($supplierMap[$row['supplyer_code']]) ? $supplierMap[$row['supplyer_code']] : '';
                $recon = isset($reconMap[$row['recon_id']]) ? $reconMap[$row['recon_id']] : null;
                $rows[$k]['recon_status'] = $row['recon_id'] ? ($recon ? $recon['status'] : '') : '';
                $rows[$k]['recon_status_text'] = $rows[$k]['recon_status'] !== '' && isset($reconStatusList[$rows[$k]['recon_status']])
                    ? $reconStatusList[$rows[$k]['recon_status']] : '未对账';
                $rows[$k]['invoice_status'] = $recon ? $recon['invoice_status'] : '0';
                $rows[$k]['storage_status'] = $row['doc_type'] == '1'
                    ? (isset($storageMap[$row['purchase_code']]) ? $storageMap[$row['purchase_code']] : 0)
                    : null;
            }

            return json(['total' => $total, 'rows' => $rows]);
        }
        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        return $this->view->fetch();
    }

    /**
     * 供应商待付款统计（含退货净额）
     */
    public function supplierstat()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $filter = json_decode((string)$this->request->request('filter', '{}'), true);
            $filter = is_array($filter) ? $filter : [];

            $purchaseQuery = Db::name('kuerp_goods_purchase')->where(['purchase_type' => '1', 'examine_status' => '1']);
            $exchangeQuery = Db::name('kuerp_goods_exchange')->where(['examine_status' => '1']);
            foreach ([$purchaseQuery, $exchangeQuery] as $query) {
                $this->applyDocFilters($query, $filter);
            }
            $purchases = $purchaseQuery->field('supplyer_code,real_money,pay_money,recon_id')->select();
            $exchanges = $exchangeQuery->field('supplyer_code,real_money,retreat_money,recon_id')->select();

            $reconIds = array_values(array_filter(array_unique(array_merge(
                array_column($purchases, 'recon_id'),
                array_column($exchanges, 'recon_id')
            ))));
            $reconMap = [];
            $dueMap = [];
            if (!empty($reconIds)) {
                $reconRows = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $reconIds]])->select();
                foreach ($reconRows as $reconRow) {
                    $reconMap[$reconRow['id']] = $reconRow;
                }
                $dueRows = Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => ['in', $reconIds]])
                    ->field('recon_id,min(due_date) as due_date')->group('recon_id')->select();
                foreach ($dueRows as $dueRow) {
                    $dueMap[$dueRow['recon_id']] = $dueRow['due_date'];
                }
            }
            $supplierCodes = array_values(array_filter(array_unique(array_merge(
                array_column($purchases, 'supplyer_code'),
                array_column($exchanges, 'supplyer_code')
            ))));
            $supplierMap = $supplierCodes ? Db::name('kuerp_suppliers')->where(['supplyer_code' => ['in', $supplierCodes]])->column('supplyer_name', 'supplyer_code') : [];

            $today = strtotime(date('Y-m-d'));
            $stat = [];
            $add = function ($code, $amount, $paid, $reconId, $isReturn) use (&$stat, $supplierMap, $reconMap, $dueMap, $today) {
                if (!isset($stat[$code])) {
                    $stat[$code] = [
                        'supplyer_code'    => $code,
                        'supplyer_name'    => isset($supplierMap[$code]) ? $supplierMap[$code] : '',
                        'purchase_count'   => 0,
                        'return_count'     => 0,
                        'total_money'      => 0,
                        'reconed_money'    => 0,
                        'unreconed_money'  => 0,
                        'invoiced_money'   => 0,
                        'uninvoiced_money' => 0,
                        'paid_money'       => 0,
                        'unpaid_money'     => 0,
                        'overdue_money'    => 0,
                    ];
                }
                if ($isReturn) {
                    $stat[$code]['return_count'] += 1;
                } else {
                    $stat[$code]['purchase_count'] += 1;
                }
                $stat[$code]['total_money'] += $amount;
                $stat[$code]['paid_money'] += $paid;
                if ($reconId) {
                    $stat[$code]['reconed_money'] += $amount;
                    $recon = isset($reconMap[$reconId]) ? $reconMap[$reconId] : null;
                    if ($recon && $recon['invoice_status'] == '2') {
                        $stat[$code]['invoiced_money'] += $amount;
                    } else {
                        $stat[$code]['uninvoiced_money'] += $amount;
                    }
                    $dueDate = isset($dueMap[$reconId]) ? (int)$dueMap[$reconId] : 0;
                    if ($recon && $recon['status'] == '3' && $recon['pay_status'] != '2' && $dueDate && $dueDate < $today) {
                        $stat[$code]['overdue_money'] += $amount;
                    }
                } else {
                    $stat[$code]['unreconed_money'] += $amount;
                }
            };
            foreach ($purchases as $purchase) {
                $add($purchase['supplyer_code'], (float)$purchase['real_money'], (float)$purchase['pay_money'], $purchase['recon_id'], false);
            }
            foreach ($exchanges as $exchange) {
                $add($exchange['supplyer_code'], -1 * (float)$exchange['real_money'], -1 * (float)$exchange['retreat_money'], $exchange['recon_id'], true);
            }
            foreach ($stat as $code => $row) {
                $stat[$code]['unpaid_money'] = round($row['total_money'] - $row['paid_money'], 4);
                foreach (['total_money', 'reconed_money', 'unreconed_money', 'invoiced_money', 'uninvoiced_money', 'paid_money', 'overdue_money'] as $key) {
                    $stat[$code][$key] = round($row[$key], 4);
                }
            }
            $rows = array_values($stat);
            usort($rows, function ($a, $b) {
                return $b['unpaid_money'] <=> $a['unpaid_money'];
            });
            $summary = [
                'purchase_count'   => 0,
                'return_count'     => 0,
                'total_money'      => 0,
                'reconed_money'    => 0,
                'unreconed_money'  => 0,
                'invoiced_money'   => 0,
                'uninvoiced_money' => 0,
                'paid_money'       => 0,
                'unpaid_money'     => 0,
                'overdue_money'    => 0,
            ];
            foreach ($rows as $row) {
                foreach ($summary as $key => $value) {
                    $summary[$key] += $row[$key];
                }
            }
            foreach ($summary as $key => $value) {
                $summary[$key] = round($value, 4);
            }
            return json(['total' => count($rows), 'rows' => $rows, 'summary' => $summary]);
        }
        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        return $this->view->fetch();
    }

    // ==================================================================
    // 附件 / 资料
    // ==================================================================

    /**
     * 对账单关联资料（付款申请页面 AJAX 用）
     */
    public function materials($ids = null)
    {
        $ids = $ids ?: $this->request->request('ids');
        $data = \app\admin\model\kuerp\PurchaseRecon::materials($ids);
        return json(['code' => 1, 'msg' => '', 'data' => $data]);
    }

    /**
     * 上传对账单附件（type=8）或发票文件（type=9）
     */
    public function uploadfile($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $recon = Db::name('kuerp_purchase_recon')->where(['id' => $ids])->find();
        if (!$recon) {
            $this->error('对账单不存在');
        }
        $type = (string)$this->request->post('type', '8');
        if (!in_array($type, ['8', '9'])) {
            $this->error('附件类型错误');
        }
        $file = $this->request->file('file');
        if (!$file) {
            $this->error('请选择要上传的文件');
        }
        try {
            $upload = new \app\common\library\Upload($file);
            $attachment = $upload->upload();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $fileName = $file->getInfo('name');
        $name = $fileName ? pathinfo($fileName, PATHINFO_FILENAME) : '';

        Db::startTrans();
        try {
            $enclosureId = Db::name('kuerp_enclosure')->insertGetId([
                'image'       => $attachment->url,
                'name'        => mb_substr($name, 0, 200),
                'type'        => 1,
                'status'      => 1,
                'upload_date' => date('Y-m-d'),
                'createtime'  => time(),
                'updatetime'  => time(),
            ]);
            Db::name('kuerp_enclosure_log')->insert([
                'enclosure_id'  => $enclosureId,
                'order_id'      => $recon['id'],
                'purchase_code' => $recon['recon_code'],
                'type'          => $type,
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $enclosure = \app\admin\model\kuerp\Enclosure::get($enclosureId);
        \app\admin\model\kuerp\Enclosure::fillFileInfo($enclosure);
        $this->success('上传成功', null, [
            'id'          => $enclosureId,
            'name'        => $enclosure['name'],
            'url'         => $enclosure['url'],
            'is_image'    => $enclosure['is_image'],
            'file_suffix' => $enclosure['file_suffix'],
        ]);
    }

    /**
     * 删除对账单附件 / 发票文件
     */
    public function delfile($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $log = Db::name('kuerp_enclosure_log')->where(['enclosure_id' => $ids, 'type' => ['in', ['8', '9']]])->find();
        if (!$log) {
            $this->error('该文件不属于采购对账单，不能在此删除');
        }
        $recon = Db::name('kuerp_purchase_recon')->where(['id' => $log['order_id']])->find();
        if (!$recon) {
            $this->error('对账单不存在');
        }
        if ($recon['pay_status'] != '0') {
            $this->error('对账单已进入付款流程，不能删除附件');
        }
        if ($log['type'] == '9' && $recon['status'] != self::STATUS_DONE) {
            $this->error('对账单未完成对账，不能删除发票');
        }

        Db::startTrans();
        try {
            Db::name('kuerp_enclosure_log')->where(['id' => $log['id']])->delete();
            Db::name('kuerp_enclosure')->where(['id' => $ids])->delete();
            if ($log['type'] == '9') {
                Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $recon['id'], 'enclosure_id' => $ids])->delete();
                $this->recomputeInvoice($recon['id']);
            }
            $admin = Session::get('admin');
            $this->writeLog($recon['id'], $log['type'] == '9' ? '删除发票' : '删除对账单附件', '', $admin['id']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    // ==================================================================
    // 导出 / 打印
    // ==================================================================

    /**
     * 导出 Excel（按供应商对账模板，无物流单号）
     */
    public function export($ids = null)
    {
        $ids = $ids ?: $this->request->post('ids');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$ids))));
        if (empty($ids)) {
            $this->error('请选择要导出的对账单');
        }
        $recons = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $ids]])->order('id asc')->select();
        if (empty($recons)) {
            $this->error('对账单不存在');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetIndex = 0;
        $firstRecon = null;
        foreach ($recons as $recon) {
            $data = $this->buildTemplateData($recon);
            if ($firstRecon === null) {
                $firstRecon = $recon;
            }
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(mb_substr($recon['recon_code'], 0, 31));
            $this->fillSheet($sheet, $data);
            $sheetIndex++;
        }

        $fileName = ($firstRecon['supplyer_name'] ?: $firstRecon['supplyer_code'])
            . ($firstRecon['period_start'] ? date('Ymd', $firstRecon['period_start']) : '')
            . '-' . ($firstRecon['period_end'] ? date('Ymd', $firstRecon['period_end']) : '');
        $fileName = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', $fileName) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . rawurlencode($fileName) . '";filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * 打印页（HTML，按模板版式）
     */
    public function printpage($ids = null)
    {
        $row = Db::name('kuerp_purchase_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $data = $this->buildTemplateData($row);
        $this->view->assign('row', $row);
        $this->view->assign('data', $data);
        return $this->view->fetch();
    }

    /**
     * 组装对账模板数据（行明细 + 合计 + 往期未付款）
     */
    protected function buildTemplateData($recon)
    {
        $setList = \addons\kuerp\library\Goods::getSetData()['setList'];
        $priceDecimal = isset($setList['price_decimal']['value']) ? (int)$setList['price_decimal']['value'] : 2;
        $numberDecimal = isset($setList['number_decimal']['value']) ? (int)$setList['number_decimal']['value'] : 0;
        $companyName = isset($setList['company_name']['value']) ? $setList['company_name']['value'] : '';

        $infos = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $recon['id']])
            ->order('doc_type asc,purchase_time asc,id asc')->select();
        $goodsCodes = [];
        $docCache = [];
        foreach ($infos as $info) {
            $isReturn = $info['doc_type'] == '2';
            $table = $isReturn ? 'kuerp_goods_exchange' : 'kuerp_goods_purchase';
            $infoTable = $isReturn ? 'kuerp_goods_exchange_info' : 'kuerp_goods_purchase_info';
            $doc = Db::name($table)->where(['id' => $info['purchase_id']])->find();
            if (!$doc) {
                continue;
            }
            $lines = Db::name($infoTable)->where(['purchase_code' => $doc['purchase_code']])->select();
            if ($isReturn) {
                // 购货退货单：只统计「实际退货数量 > 0」的明细行，行上的 number 是原采购数量，不能用于对账
                $lines = array_values(array_filter($lines, function ($line) {
                    return isset($line['retreat_number']) && (float)$line['retreat_number'] > 0;
                }));
            }
            if (empty($lines)) {
                // 兼容历史/异常单据：没有明细行时按单据级金额输出一行
                $lines = [[
                    'goods_code'      => '',
                    'number'          => $isReturn ? $doc['retreat_number'] : $doc['number'],
                    'unit_price'      => 0,
                    'tax_included'    => 0,
                    'tax_rate'        => 0,
                    'money_tax_price' => $doc['real_money'],
                    'remark'          => '',
                ]];
            }
            foreach ($lines as $line) {
                $goodsCodes[] = $line['goods_code'];
            }
            $docCache[] = ['doc' => $doc, 'lines' => $lines, 'is_return' => $isReturn];
        }
        $goodsCodes = array_values(array_filter(array_unique($goodsCodes)));
        $goodsMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])
            ->column('goods_name,goods_specification_id,vendor_name,packaging', 'goods_code') : [];

        $rows = [];
        $sumLine = 0;
        $sumTotal = 0;
        $reconDate = $recon['createtime'] ? date('Y-m-d', $recon['createtime']) : date('Y-m-d');
        foreach ($docCache as $cache) {
            $doc = $cache['doc'];
            $lines = $cache['lines'];
            $isReturn = $cache['is_return'];
            $sign = $isReturn ? -1 : 1;
            $docAmount = round((float)$doc['real_money'] * $sign, 4);
            $docCost = round((float)$doc['purchase_money'] * $sign, 4);
            $docTotal = round($docAmount + $docCost, 4);
            $gross = 0;
            foreach ($lines as $line) {
                $gross += (float)$line['money_tax_price'];
            }
            $factor = $gross != 0 ? $docAmount / $gross : 0;
            $allocated = 0;
            $lineCount = count($lines);
            foreach ($lines as $index => $line) {
                $lineAmount = $index == $lineCount - 1
                    ? round($docAmount - $allocated, 4)
                    : round((float)$line['money_tax_price'] * $factor, 4);
                $allocated += $lineAmount;
                $goods = isset($goodsMap[$line['goods_code']]) ? $goodsMap[$line['goods_code']] : [];
                $rows[] = [
                    'doc_code'     => $doc['purchase_code'],
                    'recon_date'   => $reconDate,
                    'doc_date'     => $doc['purchase_time'] ? date('Y-m-d', $doc['purchase_time']) : '',
                    'supplyer_name'=> $recon['supplyer_name'],
                    'goods_name'   => isset($goods['goods_name']) ? $goods['goods_name'] : '',
                    'spec'         => isset($goods['goods_specification_id']) ? $goods['goods_specification_id'] : '',
                    'number'       => (float)($isReturn ? $line['retreat_number'] : $line['number']) * $sign,
                    'tax_included' => (float)$line['tax_included'],
                    'unit_price'   => (float)$line['unit_price'],
                    'tax_rate'     => (float)$line['tax_rate'],
                    'line_amount'  => $lineAmount,
                    'doc_cost'     => $index == 0 ? $docCost : null,
                    'doc_total'    => $index == 0 ? $docTotal : null,
                    'remark'       => $line['remark'],
                    'is_first'     => $index == 0,
                ];
                $sumLine += $lineAmount;
            }
            $sumTotal += $docTotal;
        }

        // 往期未付款（同供应商、已对账、未付款、期间早于本期）
        $history = [];
        if (!empty($recon['period_start'])) {
            $historyRows = Db::name('kuerp_purchase_recon')
                ->where(['supplyer_code' => $recon['supplyer_code'], 'status' => self::STATUS_DONE, 'pay_status' => ['<>', '2'], 'id' => ['<>', $recon['id']]])
                ->where('period_end', '<', $recon['period_start'])
                ->field("FROM_UNIXTIME(period_end,'%Y-%m') as ym, SUM(total_money) as amount")
                ->group('ym')->order('ym asc')->select();
            foreach ($historyRows as $historyRow) {
                $history[] = [
                    'month'  => (int)substr($historyRow['ym'], 5, 2) . '月',
                    'amount' => round((float)$historyRow['amount'], 4),
                ];
            }
        }
        $historyTotal = 0;
        foreach ($history as $item) {
            $historyTotal += $item['amount'];
        }
        $currentUnpaid = $recon['pay_status'] == '2' ? 0 : (float)$recon['total_money'];
        $totalUnpaid = round($historyTotal + $currentUnpaid, 4);

        return [
            'recon'        => $recon,
            'company_name' => $companyName,
            'rows'         => $rows,
            'sum_line'     => round($sumLine, 4),
            'sum_total'    => round($sumTotal, 4),
            'history'      => $history,
            'total_unpaid' => $totalUnpaid,
            'price_decimal'=> $priceDecimal,
            'number_decimal'=> $numberDecimal,
        ];
    }

    protected function fillSheet($sheet, $data)
    {
        $headers = ['采购单号', '对账日期', '采购日期', '供应商', '商品名称', '型号/规格', '数量',
            '含税单价', '未税单价', '税点(%)', '采购合计金额', '采购费用', '总金额', '备注'];
        $colCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->setCellValue('A1', $data['company_name'] . '采购对账单');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        foreach ($headers as $index => $title) {
            $sheet->setCellValueByColumnAndRow($index + 1, 2, $title);
        }
        $sheet->getStyle('A2:' . $lastCol . '2')->getFont()->setBold(true);
        $sheet->getStyle('A2:' . $lastCol . '2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $rowNum = 3;
        foreach ($data['rows'] as $row) {
            $values = [
                $row['doc_code'], $row['recon_date'], $row['doc_date'], $row['supplyer_name'],
                $row['goods_name'], $row['spec'],
                number_format($row['number'], $data['number_decimal'], '.', ''),
                number_format($row['tax_included'], $data['price_decimal'], '.', ''),
                number_format($row['unit_price'], $data['price_decimal'], '.', ''),
                number_format($row['tax_rate'], 2, '.', ''),
                number_format($row['line_amount'], $data['price_decimal'], '.', ''),
                $row['doc_cost'] === null ? '' : number_format($row['doc_cost'], $data['price_decimal'], '.', ''),
                $row['doc_total'] === null ? '' : number_format($row['doc_total'], $data['price_decimal'], '.', ''),
                $row['remark'],
            ];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicitByColumnAndRow($index + 1, $rowNum, (string)$value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $rowNum++;
        }

        // 本期合计
        $sheet->setCellValue('A' . $rowNum, '本期合计');
        $sheet->mergeCells('A' . $rowNum . ':J' . $rowNum);
        $sheet->setCellValue('K' . $rowNum, number_format($data['sum_line'], $data['price_decimal'], '.', ''));
        $sheet->setCellValue('M' . $rowNum, number_format($data['sum_total'], $data['price_decimal'], '.', ''));
        $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFont()->setBold(true);
        $rowNum++;

        foreach ($data['history'] as $history) {
            $sheet->setCellValue('A' . $rowNum, $history['month'] . '已对账未付款合计');
            $sheet->mergeCells('A' . $rowNum . ':J' . $rowNum);
            $sheet->setCellValue('K' . $rowNum, number_format($history['amount'], $data['price_decimal'], '.', ''));
            $rowNum++;
        }
        $sheet->setCellValue('A' . $rowNum, '总未付款金额合计');
        $sheet->mergeCells('A' . $rowNum . ':J' . $rowNum);
        $sheet->setCellValue('K' . $rowNum, number_format($data['total_unpaid'], $data['price_decimal'], '.', ''));
        $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFont()->setBold(true);
        $rowNum += 2;

        $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
        $sheet->setCellValue('A' . $rowNum,
            '请贵公司核对本对账单金额，无误后盖章回传确认，如有异议，请在收到此账单后三个工作日内回复，逾期未提出异议，视为认可本对账单金额。谢谢！');
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setWrapText(true);

        foreach (range(1, $colCount) as $index) {
            $sheet->getColumnDimensionByColumn($index)->setWidth($index <= 4 ? 18 : 14);
        }
    }

    // ==================================================================
    // 内部工具
    // ==================================================================

    protected function applyDocFilters($query, $filter)
    {
        if (!empty($filter['purchase_time']) && strpos($filter['purchase_time'], ' - ') !== false) {
            list($start, $end) = explode(' - ', $filter['purchase_time']);
            $query->where('purchase_time', 'between', [strtotime($start . ' 00:00:00'), strtotime($end . ' 23:59:59')]);
        }
        if (!empty($filter['supplyer_code'])) {
            $query->where('supplyer_code', trim($filter['supplyer_code']));
        }
        if (!empty($filter['purchase_code'])) {
            $query->where('purchase_code', 'like', '%' . trim($filter['purchase_code']) . '%');
        }
        return $query;
    }

    protected function parseDate($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        $time = strtotime($value);
        return $time ? $time : 0;
    }

    protected function checkSupplier($supplyerCode)
    {
        $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $supplyerCode, 'status' => 1])->find();
        if (!$supplier) {
            $this->error('请选择有效的供应商');
        }
        return $supplier;
    }

    protected function checkPurchases($purchaseIds, $supplyerCode, $reconId = 0)
    {
        $purchaseIds = $this->normalizeIds($purchaseIds);
        if (empty($purchaseIds)) {
            return [];
        }
        $purchases = Db::name('kuerp_goods_purchase')->where(['id' => ['in', $purchaseIds]])->select();
        if (count($purchases) != count($purchaseIds)) {
            $this->error('部分采购单不存在，请刷新后重试');
        }
        $checkout = new \addons\kuerp\library\Checkout();
        foreach ($purchases as $purchase) {
            if ($purchase['purchase_type'] != '1') {
                $this->error('采购单' . $purchase['purchase_code'] . '不是普通采购单，不能对账');
            }
            if ($purchase['examine_status'] != '1') {
                $this->error('采购单' . $purchase['purchase_code'] . '未审核，不能对账');
            }
            if ($purchase['supplyer_code'] != $supplyerCode) {
                $this->error('采购单' . $purchase['purchase_code'] . '不属于当前供应商');
            }
            if ($purchase['recon_id'] != 0 && $purchase['recon_id'] != $reconId) {
                $this->error('采购单' . $purchase['purchase_code'] . '已在对账单中，不能重复对账');
            }
            $check = $checkout->checkOutTime($purchase['purchase_time']);
            if ($check !== true) {
                $this->error('采购单' . $purchase['purchase_code'] . '：' . $check);
            }
        }
        return $purchases;
    }

    protected function checkExchanges($exchangeIds, $supplyerCode, $reconId = 0)
    {
        $exchangeIds = $this->normalizeIds($exchangeIds);
        if (empty($exchangeIds)) {
            return [];
        }
        $exchanges = Db::name('kuerp_goods_exchange')->where(['id' => ['in', $exchangeIds]])->select();
        if (count($exchanges) != count($exchangeIds)) {
            $this->error('部分购货退货单不存在，请刷新后重试');
        }
        $checkout = new \addons\kuerp\library\Checkout();
        foreach ($exchanges as $exchange) {
            if ($exchange['examine_status'] != '1') {
                $this->error('购货退货单' . $exchange['purchase_code'] . '未审核，不能对账');
            }
            if ($exchange['supplyer_code'] != $supplyerCode) {
                $this->error('购货退货单' . $exchange['purchase_code'] . '不属于当前供应商');
            }
            if ($exchange['recon_id'] != 0 && $exchange['recon_id'] != $reconId) {
                $this->error('购货退货单' . $exchange['purchase_code'] . '已在对账单中，不能重复对账');
            }
            $check = $checkout->checkOutTime($exchange['purchase_time']);
            if ($check !== true) {
                $this->error('购货退货单' . $exchange['purchase_code'] . '：' . $check);
            }
        }
        return $exchanges;
    }

    protected function normalizeIds($ids)
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        return array_values(array_filter(array_unique(array_map('intval', (array)$ids))));
    }

    protected function calcTotals($purchases, $exchanges)
    {
        $number = 0;
        $money = 0;
        foreach ($purchases as $purchase) {
            $number += (float)$purchase['number'];
            $money += (float)$purchase['real_money'];
        }
        foreach ($exchanges as $exchange) {
            // 购货退货单主表的 number 是「原采购数量」（明细 number 合计），实际退货数量在 retreat_number
            $number -= (float)$exchange['retreat_number'];
            $money -= (float)$exchange['real_money'];
        }
        return [
            'purchase_count' => count($purchases),
            'return_count'   => count($exchanges),
            'total_number'   => round($number, 4),
            'total_money'    => round($money, 4),
        ];
    }

    /**
     * 写入对账明细并回写源单据归属
     */
    protected function saveDocs($reconId, $reconCode, $purchases, $exchanges, $reconStatus = '0')
    {
        $statusMap = \app\admin\model\kuerp\GoodsPurchase::storageStatusMap(array_column($purchases, 'purchase_code'));
        $purchaseType = '1';
        foreach ($purchases as $purchase) {
            $this->upsertInfo($reconId, $purchaseType, $purchase, $statusMap);
            Db::name('kuerp_goods_purchase')->where(['id' => $purchase['id']])->update([
                'recon_id'     => $reconId,
                'recon_code'   => $reconCode,
                'recon_status' => $reconStatus,
            ]);
        }
        $exchangeType = '2';
        foreach ($exchanges as $exchange) {
            $this->upsertInfo($reconId, $exchangeType, $exchange, []);
            Db::name('kuerp_goods_exchange')->where(['id' => $exchange['id']])->update([
                'recon_id'     => $reconId,
                'recon_code'   => $reconCode,
                'recon_status' => $reconStatus,
            ]);
        }
    }

    protected function upsertInfo($reconId, $docType, $doc, $statusMap)
    {
        $isReturn = $docType === '2';
        $data = [
            'recon_id'       => $reconId,
            'doc_type'       => $docType,
            'purchase_id'    => $doc['id'],
            'purchase_code'  => $doc['purchase_code'],
            'purchase_time'  => $doc['purchase_time'],
            'number'         => $isReturn ? -1 * (float)$doc['retreat_number'] : (float)$doc['number'],
            'real_money'     => $isReturn ? -1 * (float)$doc['real_money'] : (float)$doc['real_money'],
            'storage_status' => $isReturn ? 0 : (isset($statusMap[$doc['purchase_code']]) ? $statusMap[$doc['purchase_code']] : 0),
            'remark'         => '',
        ];
        $exists = Db::name('kuerp_purchase_recon_info')
            ->where(['recon_id' => $reconId, 'doc_type' => $docType, 'purchase_id' => $doc['id']])->find();
        if ($exists) {
            Db::name('kuerp_purchase_recon_info')->where(['id' => $exists['id']])->update($data);
        } else {
            $data['createtime'] = time();
            Db::name('kuerp_purchase_recon_info')->insert($data);
        }
    }

    /**
     * 回写源单据归属（reconId=0 表示释放）
     */
    protected function syncSource($docType, $ids, $reconId, $reconCode, $reconStatus)
    {
        if (empty($ids)) {
            return;
        }
        $table = $docType == '2' ? 'kuerp_goods_exchange' : 'kuerp_goods_purchase';
        Db::name($table)->where(['id' => ['in', $ids]])->update([
            'recon_id'     => $reconId,
            'recon_code'   => $reconCode,
            'recon_status' => $reconStatus,
        ]);
    }

    /**
     * 按源单据刷新对账明细（提交前/草稿修改后）
     */
    protected function refreshDocs($reconId)
    {
        $infos = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $reconId])->select();
        foreach ($infos as $info) {
            $isReturn = $info['doc_type'] == '2';
            $table = $isReturn ? 'kuerp_goods_exchange' : 'kuerp_goods_purchase';
            $doc = Db::name($table)->where(['id' => $info['purchase_id']])->find();
            if (!$doc || $doc['examine_status'] != '1') {
                // 源单据已删除或反审核：从对账单中移除并释放
                Db::name('kuerp_purchase_recon_info')->where(['id' => $info['id']])->delete();
                $this->syncSource($info['doc_type'], [$info['purchase_id']], 0, '', '0');
                continue;
            }
            $statusMap = $isReturn ? [] : \app\admin\model\kuerp\GoodsPurchase::storageStatusMap([$doc['purchase_code']]);
            $this->upsertInfo($reconId, $info['doc_type'], $doc, $statusMap);
        }
        $this->recomputeMain($reconId);
    }

    /**
     * 重算对账单主表汇总
     */
    protected function recomputeMain($reconId)
    {
        $infos = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => $reconId])->select();
        $purchaseCount = 0;
        $returnCount = 0;
        $number = 0;
        $money = 0;
        foreach ($infos as $info) {
            if ($info['doc_type'] == '2') {
                $returnCount++;
            } else {
                $purchaseCount++;
            }
            $number += (float)$info['number'];
            $money += (float)$info['real_money'];
        }
        Db::name('kuerp_purchase_recon')->where(['id' => $reconId])->update([
            'purchase_count' => $purchaseCount,
            'return_count'   => $returnCount,
            'total_number'   => round($number, 4),
            'total_money'    => round($money, 4),
        ]);
    }

    /**
     * 批量设置源单据对账锁状态（0解锁 / 1一审锁定 / 2已对账）
     */
    protected function lockDocs($reconId, $reconStatus)
    {
        Db::name('kuerp_goods_purchase')->where(['recon_id' => $reconId])->update(['recon_status' => $reconStatus]);
        Db::name('kuerp_goods_exchange')->where(['recon_id' => $reconId])->update(['recon_status' => $reconStatus]);
    }

    /**
     * 按发票表重算累计开票金额与开票状态
     */
    protected function recomputeInvoice($reconId)
    {
        $recon = Db::name('kuerp_purchase_recon')->where(['id' => $reconId])->find();
        $sum = (float)Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $reconId])->sum('invoice_amount');
        $count = Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => $reconId])->count();
        if ($count <= 0) {
            $status = '0';
        } elseif ($sum >= (float)$recon['total_money'] - 0.01) {
            $status = '2';
        } else {
            $status = '1';
        }
        Db::name('kuerp_purchase_recon')->where(['id' => $reconId])->update([
            'invoice_amount' => round($sum, 4),
            'invoice_status' => $status,
        ]);
        return ['amount' => round($sum, 4), 'status' => $status];
    }

    protected function writeLog($reconId, $action, $note = '', $adminId = 0)
    {
        if (!$adminId) {
            $admin = Session::get('admin');
            $adminId = $admin ? $admin['id'] : 0;
        }
        Db::name('kuerp_purchase_recon_log')->insert([
            'recon_id'   => $reconId,
            'action'     => $action,
            'note'       => mb_substr((string)$note, 0, 500),
            'admin_id'   => $adminId,
            'createtime' => time(),
        ]);
    }

    protected function enclosureList($orderId, $type)
    {
        $encIds = Db::name('kuerp_enclosure_log')
            ->where(['order_id' => $orderId, 'type' => $type])
            ->order('id asc')
            ->column('enclosure_id');
        if (empty($encIds)) {
            return [];
        }
        $rows = \app\admin\model\kuerp\Enclosure::where(['id' => ['in', $encIds]])->select();
        foreach ($rows as $row) {
            \app\admin\model\kuerp\Enclosure::fillFileInfo($row);
        }
        return $rows;
    }

    protected function enclosureOne($id)
    {
        $row = \app\admin\model\kuerp\Enclosure::get($id);
        if (!$row) {
            return null;
        }
        return \app\admin\model\kuerp\Enclosure::fillFileInfo($row);
    }

    protected function fileInfo($path, $name = '')
    {
        if (empty($path)) {
            return null;
        }
        $suffix = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return [
            'name'        => $name ?: pathinfo($path, PATHINFO_FILENAME),
            'url'         => cdnurl($path, true),
            'file_suffix' => $suffix,
            'is_image'    => in_array($suffix, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico']) ? 1 : 0,
        ];
    }
}
