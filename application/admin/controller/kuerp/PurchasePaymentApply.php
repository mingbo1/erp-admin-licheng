<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\Session;
use think\exception\HttpResponseException;

/**
 * 采购付款申请
 *
 * 采购发起 → 财务审核 → 审计审核 →（金额达到设置阈值时）领导审核 → 出纳打款
 * 出纳确认打款时自动生成并审核付款单（kuerp/payment），核销采购单应付，并保存付款回单。
 *
 * @icon fa fa-credit-card
 */
class PurchasePaymentApply extends Backend
{

    /**
     * @var \app\admin\model\kuerp\PurchasePaymentApply
     */
    protected $model = null;

    protected $noNeedRight = ['reconlist', 'uploadvoucher'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\kuerp\PurchasePaymentApply;
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);
        $this->assignconfig('nowmenu', $setData['nowmenu']);
        $this->assignconfig('leaderMoney', $this->getLeaderMoney($setData['setList']));
    }

    protected function getLeaderMoney($setList = null)
    {
        if ($setList === null) {
            $setList = \addons\kuerp\library\Goods::getSetData()['setList'];
        }
        return isset($setList['purchase_payment_leader_money']['value']) ? (float)$setList['purchase_payment_leader_money']['value'] : 0;
    }

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
            $adminIds = [];
            foreach ($rows as $row) {
                $adminIds[] = $row['apply_admin_id'];
                $adminIds[] = $row['finance_admin_id'];
                $adminIds[] = $row['audit_admin_id'];
                $adminIds[] = $row['leader_admin_id'];
                $adminIds[] = $row['cashier_admin_id'];
            }
            $adminIds = array_filter(array_unique($adminIds));
            $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];

            foreach ($rows as $k => $row) {
                $rows[$k]['apply_admin_name'] = isset($adminMap[$row['apply_admin_id']]) ? $adminMap[$row['apply_admin_id']] : '';
                $rows[$k]['finance_admin_name'] = isset($adminMap[$row['finance_admin_id']]) ? $adminMap[$row['finance_admin_id']] : '';
                $rows[$k]['audit_admin_name'] = isset($adminMap[$row['audit_admin_id']]) ? $adminMap[$row['audit_admin_id']] : '';
                $rows[$k]['leader_admin_name'] = isset($adminMap[$row['leader_admin_id']]) ? $adminMap[$row['leader_admin_id']] : '';
                $rows[$k]['cashier_admin_name'] = isset($adminMap[$row['cashier_admin_id']]) ? $adminMap[$row['cashier_admin_id']] : '';
                $rows[$k]['apply_time_text'] = $row['apply_time'] ? date('Y-m-d H:i', $row['apply_time']) : '';
                $rows[$k]['pay_time_text'] = $row['pay_time'] ? date('Y-m-d H:i', $row['pay_time']) : '';
            }

            return json(['total' => $list->total(), 'rows' => $rows]);
        }
        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        return $this->view->fetch();
    }

    /**
     * 新增付款申请
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
            $action = $this->request->post('action', 'submit');
            $reconIds = $this->request->post('recon_ids', '');

            $supplier = $this->checkSupplier($supplyerCode);
            $recons = $this->checkRecons($reconIds, $supplyerCode, 0);
            $totalMoney = 0;
            foreach ($recons as $recon) {
                $totalMoney += (float)$recon['total_money'];
            }

            $admin = Session::get('admin');
            $applyCode = \addons\kuerp\library\Goods::getCodes('', 31);
            $submit = ($action === 'submit');
            $needLeader = ($this->getLeaderMoney() > 0 && $totalMoney >= $this->getLeaderMoney()) ? 1 : 0;

            Db::startTrans();
            try {
                $applyId = Db::name('kuerp_purchase_payment_apply')->insertGetId([
                    'apply_code'     => $applyCode,
                    'supplyer_code'  => $supplier['supplyer_code'],
                    'supplyer_name'  => $supplier['supplyer_name'],
                    'recon_count'    => count($recons),
                    'total_money'    => round($totalMoney, 4),
                    'status'         => $submit ? '1' : '0',
                    'need_leader'    => $submit ? $needLeader : 0,
                    'apply_admin_id' => $admin['id'],
                    'apply_time'     => $submit ? time() : null,
                    'remark'         => $remark,
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]);
                $this->saveApplyInfo($applyId, $recons);
                foreach ($recons as $recon) {
                    Db::name('kuerp_purchase_recon')->where(['id' => $recon['id']])
                        ->update(['pay_status' => '1', 'updatetime' => time()]);
                }
                $this->writeLog($applyId, $submit ? '创建并提交付款申请' : '创建付款申请草稿',
                    '对账单 ' . count($recons) . ' 张，金额 ' . round($totalMoney, 2), $admin['id']);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success($submit ? '付款申请已提交' : '草稿已保存', null, ['id' => $applyId, 'apply_code' => $applyCode]);
        }

        $presetReconId = (int)$this->request->param('recon_id', 0);
        $presetSupplyerCode = $presetReconId ? Db::name('kuerp_purchase_recon')->where(['id' => $presetReconId])->value('supplyer_code') : '';
        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        $this->view->assign('presetReconId', $presetReconId);
        $this->view->assign('presetSupplyerCode', $presetSupplyerCode);
        $this->assignconfig('presetReconId', $presetReconId);
        return $this->view->fetch();
    }

    /**
     * 编辑付款申请（草稿 / 已驳回）
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!in_array($row['status'], ['0', '6'])) {
            $this->error('只有草稿或已驳回的付款申请可以编辑');
        }
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $remark = isset($params['remark']) ? trim($params['remark']) : '';
            $action = $this->request->post('action', 'submit');
            $reconIds = $this->request->post('recon_ids', '');

            $supplier = $this->checkSupplier($row['supplyer_code']);
            $recons = $this->checkRecons($reconIds, $row['supplyer_code'], $row['id']);
            $totalMoney = 0;
            foreach ($recons as $recon) {
                $totalMoney += (float)$recon['total_money'];
            }
            $admin = Session::get('admin');
            $submit = ($action === 'submit');
            $needLeader = ($this->getLeaderMoney() > 0 && $totalMoney >= $this->getLeaderMoney()) ? 1 : 0;

            $oldReconIds = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->column('recon_id');
            $newReconIds = array_column($recons, 'id');
            $removeIds = array_values(array_diff($oldReconIds, $newReconIds));

            Db::startTrans();
            try {
                Db::name('kuerp_purchase_payment_apply')->where(['id' => $row['id']])->update([
                    'supplyer_name'  => $supplier['supplyer_name'],
                    'recon_count'    => count($recons),
                    'total_money'    => round($totalMoney, 4),
                    'status'         => $submit ? '1' : '0',
                    'need_leader'    => $submit ? $needLeader : 0,
                    'apply_time'     => $submit ? ($row['apply_time'] ?: time()) : null,
                    'remark'         => $remark,
                    'updatetime'     => time(),
                ]);
                if (!empty($removeIds)) {
                    Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id'], 'recon_id' => ['in', $removeIds]])->delete();
                    Db::name('kuerp_purchase_recon')->where(['id' => ['in', $removeIds]])->update(['pay_status' => '0']);
                }
                $this->saveApplyInfo($row['id'], $recons);
                foreach ($recons as $recon) {
                    Db::name('kuerp_purchase_recon')->where(['id' => $recon['id']])->update(['pay_status' => '1']);
                }
                $this->writeLog($row['id'], $submit ? '修改并重新提交' : '修改草稿',
                    '对账单 ' . count($recons) . ' 张，金额 ' . round($totalMoney, 2), $admin['id']);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success($submit ? '已提交审核' : '草稿已保存');
        }

        $this->view->assign('row', $row);
        $this->view->assign('suppliers', Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_code,supplyer_name')->order('supplyer_code asc')->select());
        $applyInfo = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->select();
        $this->view->assign('applyInfo', $applyInfo);
        return $this->view->fetch();
    }

    /**
     * 删除付款申请（草稿 / 已驳回）
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
        $list = Db::name('kuerp_purchase_payment_apply')->where(['id' => ['in', $ids]])->select();
        foreach ($list as $row) {
            if (!in_array($row['status'], ['0', '6'])) {
                $this->error('付款申请' . $row['apply_code'] . '正在审批或已付款，不能删除');
            }
        }
        Db::startTrans();
        try {
            foreach ($list as $row) {
                $reconIds = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->column('recon_id');
                if (!empty($reconIds)) {
                    Db::name('kuerp_purchase_recon')->where(['id' => ['in', $reconIds], 'pay_status' => '1'])->update(['pay_status' => '0']);
                }
                Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->delete();
                Db::name('kuerp_purchase_payment_apply_log')->where(['apply_id' => $row['id']])->delete();
                Db::name('kuerp_purchase_payment_apply')->where(['id' => $row['id']])->delete();
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
        $row = Db::name('kuerp_purchase_payment_apply')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $statusList = $this->model->getStatusList();
        $row['status_text'] = isset($statusList[$row['status']]) ? $statusList[$row['status']] : '';
        foreach (['apply_admin_id' => 'apply', 'finance_admin_id' => 'finance', 'audit_admin_id' => 'audit', 'leader_admin_id' => 'leader', 'cashier_admin_id' => 'cashier'] as $field => $prefix) {
            $row[$prefix . '_admin_name'] = $row[$field] ? Db::name('admin')->where(['id' => $row[$field]])->value('nickname') : '';
        }
        foreach (['apply_time' => 'apply', 'finance_time' => 'finance', 'audit_time' => 'audit', 'leader_time' => 'leader', 'pay_time' => 'pay'] as $field => $prefix) {
            $row[$prefix . '_time_text'] = $row[$field] ? date('Y-m-d H:i:s', $row[$field]) : '';
        }

        $applyInfo = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->order('id asc')->select();
        foreach ($applyInfo as $k => $info) {
            $applyInfo[$k]['due_date_text'] = $info['due_date'] ? date('Y-m-d', $info['due_date']) : '';
        }
        $reconMaterials = \app\admin\model\kuerp\PurchaseRecon::materials(array_column($applyInfo, 'recon_id'));

        // 付款回单（type=10）
        $vouchers = $this->enclosureList($row['id'], '10');

        $logs = Db::name('kuerp_purchase_payment_apply_log')->where(['apply_id' => $row['id']])->order('id desc')->select();
        $adminIds = array_filter(array_unique(array_column($logs, 'admin_id')));
        $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];
        foreach ($logs as $k => $log) {
            $logs[$k]['admin_name'] = isset($adminMap[$log['admin_id']]) ? $adminMap[$log['admin_id']] : '';
            $logs[$k]['time_text'] = $log['createtime'] ? date('Y-m-d H:i:s', $log['createtime']) : '';
        }

        $payment = null;
        if ($row['payment_id']) {
            $payment = Db::name('kuerp_payment')->where(['id' => $row['payment_id']])->find();
            if ($payment) {
                $payment['examine_status_text'] = $payment['examine_status'] == '1' ? '已审核' : '未审核';
            }
        }

        $this->view->assign('row', $row);
        $this->view->assign('applyInfo', $applyInfo);
        $this->view->assign('reconMaterials', $reconMaterials);
        $this->view->assign('vouchers', $vouchers);
        $this->view->assign('logs', $logs);
        $this->view->assign('payment', $payment);
        $this->view->assign('leaderMoney', $this->getLeaderMoney());
        return $this->view->fetch();
    }

    /**
     * 提交审核
     */
    public function submit($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $ids ?: $this->request->post('ids');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$ids))));
        $list = Db::name('kuerp_purchase_payment_apply')->where(['id' => ['in', $ids], 'status' => ['in', ['0', '6']]])->select();
        if (empty($list)) {
            $this->error('请选择草稿或已驳回的付款申请');
        }
        $admin = Session::get('admin');
        $leaderMoney = $this->getLeaderMoney();
        Db::startTrans();
        try {
            foreach ($list as $row) {
                $count = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->count();
                if ($count <= 0) {
                    throw new Exception('付款申请' . $row['apply_code'] . '没有对账单明细');
                }
                $needLeader = ($leaderMoney > 0 && $row['total_money'] >= $leaderMoney) ? 1 : 0;
                Db::name('kuerp_purchase_payment_apply')->where(['id' => $row['id']])->update([
                    'status'      => '1',
                    'need_leader' => $needLeader,
                    'apply_time'  => time(),
                    'updatetime'  => time(),
                ]);
                $reconIds = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->column('recon_id');
                if (!empty($reconIds)) {
                    Db::name('kuerp_purchase_recon')->where(['id' => ['in', $reconIds]])->update(['pay_status' => '1']);
                }
                $this->writeLog($row['id'], '提交审核', '', $admin['id']);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('已提交审核');
    }

    /**
     * 财务审核
     */
    public function financeexamine($ids = null)
    {
        return $this->stepExamine($ids, '1', 'finance', '财务');
    }

    /**
     * 审计审核
     */
    public function auditexamine($ids = null)
    {
        return $this->stepExamine($ids, '2', 'audit', '审计');
    }

    /**
     * 领导审核
     */
    public function leaderexamine($ids = null)
    {
        return $this->stepExamine($ids, '3', 'leader', '领导');
    }

    /**
     * 审核节点通用处理（GET 弹窗 / POST 提交）
     */
    protected function stepExamine($ids, $expectStatus, $step, $stepName)
    {
        $row = Db::name('kuerp_purchase_payment_apply')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            if ($row['status'] != $expectStatus) {
                $this->error('该付款申请当前不是「' . $stepName . '审核」状态');
            }
            $result = (int)$this->request->post('result', 1);
            $remark = trim((string)$this->request->post('remark', ''));
            $admin = Session::get('admin');
            $fieldPrefix = $step;
            if ($result == 1) {
                if ($step === 'finance') {
                    $nextStatus = '2';
                } elseif ($step === 'audit') {
                    $nextStatus = $row['need_leader'] == 1 ? '3' : '4';
                } else {
                    $nextStatus = '4';
                }
                Db::name('kuerp_purchase_payment_apply')->where(['id' => $row['id'], 'status' => $expectStatus])->update([
                    $fieldPrefix . '_admin_id' => $admin['id'],
                    $fieldPrefix . '_time'     => time(),
                    $fieldPrefix . '_remark'   => mb_substr($remark, 0, 250),
                    'status'                   => $nextStatus,
                    'updatetime'               => time(),
                ]);
                $nextText = $nextStatus == '2' ? '审计审核' : ($nextStatus == '3' ? '领导审核' : '出纳付款');
                $this->writeLog($row['id'], $stepName . '审核通过', $remark . '（转' . $nextText . '）', $admin['id']);
                $this->success($stepName . '审核通过');
            } else {
                if ($remark === '') {
                    $this->error('请填写驳回原因');
                }
                Db::name('kuerp_purchase_payment_apply')->where(['id' => $row['id'], 'status' => $expectStatus])->update([
                    $fieldPrefix . '_admin_id' => $admin['id'],
                    $fieldPrefix . '_time'     => time(),
                    $fieldPrefix . '_remark'   => mb_substr($remark, 0, 250),
                    'status'                   => '6',
                    'updatetime'               => time(),
                ]);
                $this->writeLog($row['id'], $stepName . '驳回', $remark, $admin['id']);
                $this->success('已驳回');
            }
        }

        $statusList = $this->model->getStatusList();
        $row['status_text'] = isset($statusList[$row['status']]) ? $statusList[$row['status']] : '';
        $this->view->assign('row', $row);
        $this->view->assign('step', $step);
        $this->view->assign('stepName', $stepName);
        return $this->view->fetch('step_examine');
    }

    /**
     * 出纳确认打款（自动生成付款单并审核入账）
     */
    public function pay($ids = null)
    {
        $row = Db::name('kuerp_purchase_payment_apply')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            if ($row['status'] != '4') {
                $this->error('该付款申请当前不是「待出纳付款」状态');
            }
            $accountId = (int)$this->request->post('accounts_id', 0);
            $payDate = trim((string)$this->request->post('pay_date', date('Y-m-d')));
            $payNumber = trim((string)$this->request->post('pay_number', ''));
            $remark = trim((string)$this->request->post('remark', ''));
            $enclosureId = (int)$this->request->post('enclosure_id', 0);

            $account = Db::name('kuerp_accounts')->where(['id' => $accountId])->find();
            if (!$account) {
                $this->error('请选择付款账户');
            }
            $payTimestamp = strtotime($payDate);
            if (!$payTimestamp) {
                $this->error('请选择付款日期');
            }
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($payTimestamp);
            if ($checkout !== true) {
                $this->error($checkout);
            }

            $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->find();
            if (!$supplier) {
                $this->error('供应商不存在');
            }

            $applyInfo = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $row['id']])->select();
            $reconIds = array_column($applyInfo, 'recon_id');
            $reconInfos = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => ['in', $reconIds]])
                ->order('recon_id asc,doc_type asc,id asc')->select();
            if (empty($reconInfos)) {
                $this->error('付款申请没有采购单明细');
            }
            // 按对账单分组：对账净额（采购单 − 退货单）分摊到采购单核销；
            // 退货单在退货审核时已冲减供应商应付，这里不再生成核销行。
            $byRecon = [];
            foreach ($reconInfos as $info) {
                $byRecon[$info['recon_id']][] = $info;
            }

            $saleRows = [];
            $payTotal = 0;
            foreach ($byRecon as $reconId => $infos) {
                $recon = Db::name('kuerp_purchase_recon')->where(['id' => $reconId])->find();
                if (!$recon) {
                    $this->error('对账单不存在');
                }
                $target = round((float)$recon['total_money'], 4);
                if ($target <= 0) {
                    $this->error('对账单' . $recon['recon_code'] . '对账净额不大于 0，不能付款');
                }
                $remaining = $target;
                foreach ($infos as $info) {
                    if ($info['doc_type'] == '2') {
                        continue;
                    }
                    $order = Db::name('kuerp_goods_purchase')->where(['purchase_code' => $info['purchase_code']])->find();
                    if (!$order) {
                        $this->error('采购单' . $info['purchase_code'] . '不存在');
                    }
                    $remain = (float)$order['real_money'] - (float)$order['pay_money'];
                    if ($remain <= 0) {
                        continue;
                    }
                    $amount = min($remain, $remaining);
                    if ($amount <= 0) {
                        break;
                    }
                    $saleRows[] = [
                        'purchase_number' => $order['purchase_code'],
                        'money'           => (float)$order['real_money'],
                        'write_off_price' => round($amount, 4),
                        'remark'          => '采购对账付款（' . $recon['recon_code'] . '）',
                    ];
                    $remaining = round($remaining - $amount, 4);
                }
                if ($remaining > 0.01) {
                    $this->error('对账单' . $recon['recon_code'] . '净额￥' . $target . '超过采购单可付余额，无法核销，请检查采购单付款情况');
                }
                $payTotal += $target;
            }
            $payTotal = round($payTotal, 4);
            if (abs($payTotal - (float)$row['total_money']) > 0.01) {
                $this->error('本次可付款金额￥' . $payTotal . '与申请金额￥' . $row['total_money'] . '不一致，请调整付款申请');
            }

            $admin = Session::get('admin');
            $paymentCode = \addons\kuerp\library\Goods::getCodes('', 22);

            Db::startTrans();
            try {
                $paymentId = Db::name('kuerp_payment')->insertGetId([
                    'admin_id'       => $admin['id'],
                    'purchase_code'  => $paymentCode,
                    'purchase_time'  => $payTimestamp,
                    'business_type'  => '2',
                    'customers_id'   => $supplier['id'],
                    'money'          => $payTotal,
                    'write_off'      => $payTotal,
                    'discount'       => 0,
                    'advance'        => 0,
                    'remarks'        => $remark ?: ('采购付款申请' . $row['apply_code']),
                    'preparation_id' => $admin['id'],
                    'operation_id'   => $admin['id'],
                    'examine_status' => '0',
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]);
                Db::name('kuerp_payment_account')->insert([
                    'admin_id'       => $admin['id'],
                    'collection_id'  => $paymentId,
                    'purchase_code'  => $paymentCode,
                    'purchase_time'  => $payTimestamp,
                    'accounts_id'    => $account['id'],
                    'money'          => $payTotal,
                    'settlementtype' => 0,
                    'number'         => mb_substr($payNumber, 0, 100),
                    'remark'         => $remark,
                ]);
                foreach ($saleRows as $sale) {
                    Db::name('kuerp_payment_sale')->insert([
                        'admin_id'        => $admin['id'],
                        'collection_id'   => $paymentId,
                        'purchase_code'   => $paymentCode,
                        'purchase_time'   => $payTimestamp,
                        'purchase_number' => $sale['purchase_number'],
                        'business_type'   => '4',
                        'times'           => 0,
                        'money'           => $sale['money'],
                        'write_off'       => 0,
                        'write_off_money' => $sale['money'],
                        'write_off_price' => $sale['write_off_price'],
                        'remark'          => $sale['remark'],
                        'source_id'       => 0,
                        'type'            => '1',
                    ]);
                }

                // 复用付款单审核逻辑：账户扣款 / 供应商应付减少 / 采购单 pay_money 核销 / 台账
                $this->runPaymentExamine($paymentId);

                // 付款回单（type=10）
                if ($enclosureId > 0) {
                    $enc = Db::name('kuerp_enclosure')->where(['id' => $enclosureId])->find();
                    if ($enc) {
                        Db::name('kuerp_enclosure_log')->insert([
                            'enclosure_id'  => $enclosureId,
                            'order_id'      => $row['id'],
                            'purchase_code' => $row['apply_code'],
                            'type'          => '10',
                        ]);
                        Db::name('kuerp_payment')->where(['id' => $paymentId])->update(['voucher_images' => $enc['image']]);
                    }
                }

                Db::name('kuerp_purchase_payment_apply')->where(['id' => $row['id'], 'status' => '4'])->update([
                    'status'           => '5',
                    'cashier_admin_id' => $admin['id'],
                    'pay_time'         => $payTimestamp,
                    'payment_id'       => $paymentId,
                    'pay_account_id'   => $account['id'],
                    'pay_number'       => mb_substr($payNumber, 0, 100),
                    'pay_remark'       => mb_substr($remark, 0, 250),
                    'updatetime'       => time(),
                ]);
                Db::name('kuerp_purchase_recon')->where(['id' => ['in', $reconIds]])->update(['pay_status' => '2', 'updatetime' => time()]);
                $this->writeLog($row['id'], '出纳打款',
                    '付款单' . $paymentCode . '，金额' . $payTotal . '，账户' . $account['account_name'], $admin['id']);
                Db::commit();
            } catch (HttpResponseException $e) {
                Db::rollback();
                $msg = $this->parseResponseMessage($e);
                $this->error($msg ?: '付款单入账失败');
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('打款成功，付款单已生成并审核入账');
        }

        $statusList = $this->model->getStatusList();
        $row['status_text'] = isset($statusList[$row['status']]) ? $statusList[$row['status']] : '';
        $this->view->assign('row', $row);
        $this->view->assign('accounts', Db::name('kuerp_accounts')->where(['status' => 1])->field('id,account_coding,account_name,balance')->order('account_coding asc')->select());
        return $this->view->fetch();
    }

    /**
     * 候选对账单（新增/编辑付款申请页面）
     */
    public function reconlist()
    {
        $supplyerCode = trim((string)$this->request->request('supplyer_code', ''));
        $applyId = (int)$this->request->request('apply_id', 0);
        if ($supplyerCode === '') {
            return json(['total' => 0, 'rows' => []]);
        }
        $query = Db::name('kuerp_purchase_recon')
            ->where(['supplyer_code' => $supplyerCode, 'status' => '3', 'invoice_status' => '2'])
            ->where('total_money', '>', 0);
        $ownReconIds = [];
        if ($applyId > 0) {
            $ownReconIds = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $applyId])->column('recon_id');
            $query->where(function ($q) use ($ownReconIds) {
                $q->where('pay_status', '0');
                if (!empty($ownReconIds)) {
                    $q->whereOr('id', 'in', $ownReconIds);
                }
            });
        } else {
            $query->where('pay_status', '0');
        }
        $rows = $query->field('id,recon_code,supplyer_code,supplyer_name,period_start,period_end,total_money,pay_status')
            ->order('id asc')
            ->select();

        // 账期到期日
        $reconIds = array_column($rows, 'id');
        $dueMap = [];
        if (!empty($reconIds)) {
            $invoices = Db::name('kuerp_purchase_recon_invoice')
                ->where(['recon_id' => ['in', $reconIds]])
                ->field('recon_id,min(due_date) as due_date,sum(invoice_amount) as invoice_amount')
                ->group('recon_id')
                ->select();
            foreach ($invoices as $invoice) {
                $dueMap[$invoice['recon_id']] = $invoice;
            }
        }
        foreach ($rows as $k => $row) {
            $rows[$k]['period_text'] = ($row['period_start'] ? date('Y-m-d', $row['period_start']) : '') . ' 至 ' . ($row['period_end'] ? date('Y-m-d', $row['period_end']) : '');
            $rows[$k]['due_date_text'] = isset($dueMap[$row['id']]['due_date']) ? date('Y-m-d', $dueMap[$row['id']]['due_date']) : '';
            $rows[$k]['invoice_amount'] = isset($dueMap[$row['id']]['invoice_amount']) ? $dueMap[$row['id']]['invoice_amount'] : 0;
            $rows[$k]['checked'] = in_array($row['id'], $ownReconIds);
        }
        return json(['total' => count($rows), 'rows' => $rows]);
    }

    /**
     * 上传付款回单（type=10）
     */
    public function uploadvoucher($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $apply = Db::name('kuerp_purchase_payment_apply')->where(['id' => $ids])->find();
        if (!$apply) {
            $this->error('付款申请不存在');
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
        $enclosureId = Db::name('kuerp_enclosure')->insertGetId([
            'image'       => $attachment->url,
            'name'        => mb_substr($name, 0, 200),
            'type'        => 1,
            'status'      => 1,
            'upload_date' => date('Y-m-d'),
            'createtime'  => time(),
            'updatetime'  => time(),
        ]);
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

    // ------------------------------------------------------------------
    // 内部工具
    // ------------------------------------------------------------------

    protected function checkSupplier($supplyerCode)
    {
        $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $supplyerCode, 'status' => 1])->find();
        if (!$supplier) {
            $this->error('请选择有效的供应商');
        }
        return $supplier;
    }

    /**
     * 校验对账单是否可纳入付款申请
     */
    protected function checkRecons($reconIds, $supplyerCode, $applyId = 0)
    {
        if (is_string($reconIds)) {
            $reconIds = explode(',', $reconIds);
        }
        $reconIds = array_values(array_filter(array_unique(array_map('intval', (array)$reconIds))));
        if (empty($reconIds)) {
            $this->error('请勾选需要付款的对账单');
        }
        $recons = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $reconIds]])->select();
        if (count($recons) != count($reconIds)) {
            $this->error('部分对账单不存在，请刷新后重试');
        }
        foreach ($recons as $recon) {
            if ($recon['status'] != '3') {
                $this->error('对账单' . $recon['recon_code'] . '未完成对账（需二审通过），不能付款');
            }
            if ($recon['invoice_status'] != '2') {
                $this->error('对账单' . $recon['recon_code'] . '还未完成开票');
            }
            if ((float)$recon['total_money'] <= 0) {
                $this->error('对账单' . $recon['recon_code'] . '对账净额不大于 0，无需付款');
            }
            if ($recon['supplyer_code'] != $supplyerCode) {
                $this->error('对账单' . $recon['recon_code'] . '不属于当前供应商');
            }
            if ($recon['pay_status'] == '2') {
                $this->error('对账单' . $recon['recon_code'] . '已付款');
            }
            if ($recon['pay_status'] == '1') {
                $inApply = Db::name('kuerp_purchase_payment_apply_info')->where(['recon_id' => $recon['id']])->value('apply_id');
                if (!$applyId || $inApply != $applyId) {
                    $this->error('对账单' . $recon['recon_code'] . '已在其他付款申请中');
                }
            }
        }
        return $recons;
    }

    protected function saveApplyInfo($applyId, $recons)
    {
        $dueMap = [];
        $reconIds = array_column($recons, 'id');
        if (!empty($reconIds)) {
            $invoices = Db::name('kuerp_purchase_recon_invoice')
                ->where(['recon_id' => ['in', $reconIds]])
                ->field('recon_id,min(due_date) as due_date')
                ->group('recon_id')
                ->select();
            foreach ($invoices as $invoice) {
                $dueMap[$invoice['recon_id']] = $invoice['due_date'];
            }
        }
        foreach ($recons as $recon) {
            $exists = Db::name('kuerp_purchase_payment_apply_info')->where(['apply_id' => $applyId, 'recon_id' => $recon['id']])->find();
            $data = [
                'apply_id'    => $applyId,
                'recon_id'    => $recon['id'],
                'recon_code'  => $recon['recon_code'],
                'total_money' => $recon['total_money'],
                'due_date'    => isset($dueMap[$recon['id']]) ? $dueMap[$recon['id']] : null,
                'remark'      => '',
                'createtime'  => time(),
            ];
            if ($exists) {
                unset($data['createtime']);
                Db::name('kuerp_purchase_payment_apply_info')->where(['id' => $exists['id']])->update($data);
            } else {
                Db::name('kuerp_purchase_payment_apply_info')->insert($data);
            }
        }
    }

    protected function writeLog($applyId, $action, $note = '', $adminId = 0)
    {
        if (!$adminId) {
            $admin = Session::get('admin');
            $adminId = $admin ? $admin['id'] : 0;
        }
        Db::name('kuerp_purchase_payment_apply_log')->insert([
            'apply_id'   => $applyId,
            'action'     => $action,
            'note'       => mb_substr((string)$note, 0, 500),
            'admin_id'   => $adminId,
            'createtime' => time(),
        ]);
    }

    /**
     * 调用付款单审核（复用 Payment::examine，解析响应异常）
     */
    protected function runPaymentExamine($paymentId)
    {
        $controller = new Payment();
        try {
            $result = $controller->examine($paymentId, 1);
        } catch (HttpResponseException $e) {
            $data = json_decode($e->getResponse()->getContent(), true);
            if (!is_array($data) || !isset($data['code']) || $data['code'] != 1) {
                throw new Exception(isset($data['msg']) ? $data['msg'] : '付款单审核失败');
            }
            return;
        }
        if (is_array($result) && (!isset($result['status']) || $result['status'] != 1)) {
            throw new Exception(isset($result['msg']) ? $result['msg'] : '付款单审核失败');
        }
    }

    protected function parseResponseMessage(HttpResponseException $e)
    {
        $data = json_decode($e->getResponse()->getContent(), true);
        return is_array($data) && isset($data['msg']) ? $data['msg'] : '';
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
}
