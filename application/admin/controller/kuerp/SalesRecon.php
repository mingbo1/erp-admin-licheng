<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\Session;

/**
 * 销货对账（客户对账单）
 *
 * 销货单 + 销货退货单（退货为负数冲减）按客户 + 期间生成对账单
 * → 财务一审 → 主管/领导二审 → 上传发票（可多张）→ 收款后自动派生收款状态
 *
 * @icon fa fa-file-text-o
 */
class SalesRecon extends Backend
{

    /**
     * @var \app\admin\model\kuerp\SalesRecon
     */
    protected $model = null;

    protected $noNeedRight = ['itemlist', 'customerslist', 'salesdocs', 'uploadfile', 'reminder'];

    /**
     * 收款到期提前提醒天数（设置项 sales_collection_remind_days，默认 7）
     * @var int
     */
    protected $remindDays = 7;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\kuerp\SalesRecon;
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);
        $this->assignconfig('nowmenu', $setData['nowmenu']);
        $this->remindDays = $this->getRemindDays($setData['setList']);
        $this->assign('remindDays', $this->remindDays);
        $this->assignconfig('remindDays', $this->remindDays);
    }

    /**
     * 到期提醒天数（0=不提醒）
     */
    protected function getRemindDays($setList = null)
    {
        if ($setList === null) {
            $setList = \addons\kuerp\library\Goods::getSetData()['setList'];
        }
        $v = isset($setList['sales_collection_remind_days']['value'])
            ? $setList['sales_collection_remind_days']['value']
            : 7;
        return $v === '' ? 7 : (int)$v;
    }

    /**
     * 到期状态：0=正常 1=即将到期 2=已逾期 3=已收款
     *
     * @param int    $dueDate   到期日时间戳（0=无账期）
     * @param string $payStatus 收款状态
     * @param int    $today     今天 0 点
     * @param int    $remindDays 提前提醒天数
     * @return int
     */
    protected function termState($dueDate, $payStatus, $today, $remindDays)
    {
        if ((string)$payStatus === '2') {
            return 3;
        }
        if (!$dueDate) {
            return 0;
        }
        if ($today > $dueDate) {
            return 2;
        }
        if ($remindDays > 0 && $dueDate - $remindDays * 86400 <= $today) {
            return 1;
        }
        return 0;
    }

    /**
     * 到期状态筛选（SQL 侧，走 sales_recon.due_date + pay_status 冗余字段）
     *
     * @param \think\db\Query $query
     * @param string          $state 1=即将到期 2=已逾期 3=已收款
     */
    protected function applyTermStateFilter($query, $state)
    {
        $state = (string)$state;
        if ($state === '') {
            return;
        }
        $today = strtotime(date('Y-m-d'));
        if ($state === '1') {
            $query->where('due_date', '>', 0)
                ->where('pay_status', '<>', '2')
                ->where('due_date', '>=', $today)
                ->where('due_date', '<=', $today + max($this->remindDays, 0) * 86400);
        } elseif ($state === '2') {
            $query->where('due_date', '>', 0)
                ->where('pay_status', '<>', '2')
                ->where('due_date', '<', $today);
        } elseif ($state === '3') {
            $query->where('pay_status', '2');
        }
    }

    // =====================================================================
    // 工作台：未对账单据明细
    // =====================================================================

    /**
     * 销货对账明细（工作台页面）
     */
    public function items()
    {
        $this->view->assign('customers', $this->customerOptions());
        $this->view->assign('staffList', $this->staffOptions());
        $this->view->assign('dateStart', date('Y-m-01'));
        $this->view->assign('dateEnd', date('Y-m-t'));
        return $this->view->fetch();
    }

    /**
     * 工作台数据
     */
    public function itemlist()
    {
        if (!$this->request->isAjax()) {
            return json(['total' => 0, 'rows' => []]);
        }
        $filters = $this->parseItemFilters();
        list($sales, $returns) = $this->queryDocuments($filters);
        $rows = $this->buildItemRows($sales, $returns);

        // 计算状态过滤（出库/对账/开票/付款需按单据判定）
        $rows = $this->applyItemFilters($rows, $filters);

        // 按单据分组：单据级字段放单据头，商品行放 info_list
        $docs = $this->groupItemDocs($rows);

        // 合计按单据汇总（整批筛选结果，不受分页影响）
        $allMoney = 0;
        $allCustomersMoney = 0;
        $allAmount = 0;
        foreach ($docs as $doc) {
            $allMoney += $doc['real_money'];
            $allCustomersMoney += $doc['customers_money'];
            $allAmount += $doc['total_amount'];
        }

        // 分页按单据（商品行跟随所属单据，不会被分页切断）
        $page = max(1, (int)$this->request->request('page', 1));
        $limit = max(1, (int)$this->request->request('limit', 50));
        $total = count($docs);
        $paged = array_slice($docs, ($page - 1) * $limit, $limit);

        return json([
            'total'               => $total,
            'rows'                => $paged,
            'all_money'           => round($allMoney, 4),
            'all_customers_money' => round($allCustomersMoney, 4),
            'all_amount'          => round($allAmount, 4),
        ]);
    }

    /**
     * 生成对账单（按客户拆分）
     */
    public function add()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $keys = $this->parseDocKeys($this->request->post('docs', ''));
        if (empty($keys) && (int)$this->request->post('all', 0) === 1) {
            // 未勾选：按当前筛选结果生成
            $filters = [
                'customers_id'   => (int)$this->request->post('customers_id', 0),
                'sale_user'      => (int)$this->request->post('sale_user', 0),
                'start'          => $this->parseDate($this->request->post('date_start', '')),
                'end'            => $this->parseDate($this->request->post('date_end', '')),
                'doc_type'       => (string)$this->request->post('doc_type', ''),
                'out_status'     => (string)$this->request->post('f_out_status', ''),
                'recon_status'   => (string)$this->request->post('f_recon_status', ''),
                'invoice_status' => (string)$this->request->post('f_invoice_status', ''),
                'pay_status'     => (string)$this->request->post('f_pay_status', ''),
                'recon_filter'   => '0',
            ];
            $keys = $this->filteredDocKeys($filters);
        }
        if (empty($keys)) {
            $this->error('请选择需要生成对账单的单据');
        }
        $periodStart = $this->parseDate($this->request->post('period_start', ''));
        $periodEnd = $this->parseDate($this->request->post('period_end', ''));
        if (!$periodStart) {
            $periodStart = $this->parseDate($this->request->post('date_start', ''));
        }
        if (!$periodEnd) {
            $periodEnd = $this->parseDate($this->request->post('date_end', ''));
        }
        $docs = $this->checkDocuments($keys, 0);
        if (empty($docs)) {
            $this->error('所选单据均不可对账（未审核、已对账或已结账）');
        }

        // 按客户拆分
        $group = [];
        foreach ($docs as $key => $doc) {
            $group[$doc['customers_id']][] = $key;
        }

        $admin = Session::get('admin');
        $created = [];
        Db::startTrans();
        try {
            foreach ($group as $customerId => $customerKeys) {
                $customer = Db::name('kuerp_customers')->where(['id' => $customerId])->find();
                if (!$customer) {
                    throw new Exception('客户不存在，无法生成对账单');
                }
                $reconCode = \addons\kuerp\library\Goods::getCodes('', 32);
                $reconId = Db::name('kuerp_sales_recon')->insertGetId([
                    'recon_code'    => $reconCode,
                    'customers_id'  => $customerId,
                    'customer_name' => $customer['customer_name'],
                    'period_start'  => $periodStart,
                    'period_end'    => $periodEnd,
                    'recon_status'  => '0',
                    'invoice_status'=> '0',
                    'pay_status'    => '0',
                    'preparation_id'=> isset($admin['id']) ? $admin['id'] : 0,
                    'operation_id'  => isset($admin['id']) ? $admin['id'] : 0,
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);
                $this->buildSnapshot($reconId, $customerKeys);
                $this->writebackDocuments($customerKeys, $reconId, $reconCode, '0');
                $this->writeLog($reconId, '创建对账单', '纳入单据 ' . count($customerKeys) . ' 张');
                $created[] = ['id' => $reconId, 'recon_code' => $reconCode, 'customer_name' => $customer['customer_name']];
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('已生成 ' . count($created) . ' 张对账单', null, ['list' => $created]);
    }

    // =====================================================================
    // 对账单列表 / 详情 / 编辑 / 删除 / 刷新
    // =====================================================================

    /**
     * 对账单列表
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            // 到期状态为派生筛选（走 due_date + pay_status 冗余字段），不能进 filter
            $termState = trim((string)$this->request->get('term_state', ''));

            // ⚠️ TP5 不能 clone 后复用同一个 Query，count / 列表各建一个
            $build = function () use ($where, $termState) {
                $query = Db::name('kuerp_sales_recon')->where($where);
                $this->applyTermStateFilter($query, $termState);
                return $query;
            };
            $total = $build()->count();
            $rows = $build()->order($sort, $order)->order('id desc')->limit($offset, $limit)->select();

            $reconIds = array_column($rows, 'id');
            $adminIds = array_filter(array_unique(array_merge(
                array_column($rows, 'preparation_id'),
                array_column($rows, 'examine_id'),
                array_column($rows, 'second_examine_id')
            )));
            $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];
            $payMap = $this->payStatusMap($reconIds);
            $today = strtotime(date('Y-m-d'));

            foreach ($rows as $k => $row) {
                $rows[$k]['preparation_name'] = isset($adminMap[$row['preparation_id']]) ? $adminMap[$row['preparation_id']] : '';
                $rows[$k]['examine_name'] = isset($adminMap[$row['examine_id']]) ? $adminMap[$row['examine_id']] : '';
                $rows[$k]['second_examine_name'] = isset($adminMap[$row['second_examine_id']]) ? $adminMap[$row['second_examine_id']] : '';
                $rows[$k]['period_text'] = ($row['period_start'] ? date('Y-m-d', $row['period_start']) : '') . ' ~ ' . ($row['period_end'] ? date('Y-m-d', $row['period_end']) : '');
                $rows[$k]['doc_count'] = $row['sales_count'] + $row['return_count'];
                if (isset($payMap[$row['id']])) {
                    $rows[$k]['pay_money'] = $payMap[$row['id']]['pay_money'];
                    $rows[$k]['pay_status'] = $payMap[$row['id']]['pay_status'];
                }
                $rows[$k]['business_status'] = \app\admin\model\kuerp\SalesRecon::businessStatusText($row);

                // 收款到期日 / 剩余天数 / 到期状态（账期从开票日起算）
                $dueDate = (int)$row['due_date'];
                $rows[$k]['due_date'] = $dueDate;
                $rows[$k]['due_date_text'] = $dueDate ? date('Y-m-d', $dueDate) : '';
                $rows[$k]['days_left'] = $dueDate ? (int)floor(($dueDate - $today) / 86400) : null;
                $rows[$k]['term_state'] = $this->termState($dueDate, $rows[$k]['pay_status'], $today, $this->remindDays);
            }
            return json(['total' => $total, 'rows' => $rows]);
        }
        $this->view->assign('customers', $this->customerOptions());
        return $this->view->fetch();
    }

    /**
     * 到期提醒条数（列表顶部提示条用）
     */
    public function reminder()
    {
        if (!$this->request->isAjax()) {
            $this->error(__('Invalid parameters'));
        }
        $today = strtotime(date('Y-m-d'));
        $remindDays = max($this->remindDays, 0);
        $dueSoon = 0;
        if ($remindDays > 0) {
            $dueSoon = (int)Db::name('kuerp_sales_recon')
                ->where('due_date', '>', 0)
                ->where('pay_status', '<>', '2')
                ->where('due_date', '>=', $today)
                ->where('due_date', '<=', $today + $remindDays * 86400)
                ->count();
        }
        $overdue = (int)Db::name('kuerp_sales_recon')
            ->where('due_date', '>', 0)
            ->where('pay_status', '<>', '2')
            ->where('due_date', '<', $today)
            ->count();
        return json(['code' => 1, 'data' => [
            'due_soon'    => $dueSoon,
            'overdue'     => $overdue,
            'remind_days' => $remindDays,
        ]]);
    }

    /**
     * 对账单详情（模板版式）
     */
    public function detail($ids = null)
    {
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        list($docs, $groups) = $this->loadSnapshot($row['id']);
        $pay = $this->calcPay($row['id']);
        $row['pay_money'] = $pay['pay_money'];
        $row['pay_status'] = $pay['pay_status'];

        $adminMap = $this->adminNameMap(array_merge(
            [$row['preparation_id'], $row['examine_id'], $row['second_examine_id']],
            []
        ));
        $row['preparation_name'] = isset($adminMap[$row['preparation_id']]) ? $adminMap[$row['preparation_id']] : '';
        $row['examine_name'] = isset($adminMap[$row['examine_id']]) ? $adminMap[$row['examine_id']] : '';
        $row['second_examine_name'] = isset($adminMap[$row['second_examine_id']]) ? $adminMap[$row['second_examine_id']] : '';
        $row['period_text'] = ($row['period_start'] ? date('Y-m-d', $row['period_start']) : '') . ' ~ ' . ($row['period_end'] ? date('Y-m-d', $row['period_end']) : '');
        $row['business_status'] = \app\admin\model\kuerp\SalesRecon::businessStatusText($row);

        // 该客户所有未付款对账单（按月份归并，含本单）；各行相加 = 总未付款
        $past = $this->pastUnpaid($row['customers_id'], $row['id']);
        $totalUnpaid = $past['total'];

        // 发票
        $invoices = Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $row['id']])->order('id asc')->select();
        $today = strtotime(date('Y-m-d'));
        foreach ($invoices as $k => $invoice) {
            $invoices[$k]['file'] = $invoice['enclosure_id'] ? $this->enclosureOne($invoice['enclosure_id']) : null;
            $invoices[$k]['invoice_date_text'] = $invoice['invoice_date'] ? date('Y-m-d', $invoice['invoice_date']) : '';
            $invoices[$k]['upload_date_text'] = $invoice['upload_date'] ? date('Y-m-d', $invoice['upload_date']) : '';
            $dueDate = (int)$invoice['due_date'];
            $invoices[$k]['due_date_text'] = $dueDate ? date('Y-m-d', $dueDate) : '';
            $invoices[$k]['days_left'] = $dueDate ? (int)floor(($dueDate - $today) / 86400) : null;
            $invoices[$k]['term_state'] = $this->termState($dueDate, $row['pay_status'], $today, $this->remindDays);
        }
        // 应开票金额 = 总金额 total_amount（销货合计 + 客户承担费用）+ 对账差异 diff_money
        // 净额为负（纯退货 / 退货冲抵后仍为负）时没有可开票金额，展示为 0（开票页同样按 0 拦截）
        $shouldInvoice = max(0, $this->invoiceBase($row));

        // 收款记录
        $codes = array_column($docs, 'doc_code');
        $collections = [];
        if ($codes) {
            $collectionRows = Db::name('kuerp_collection_sale')->where(['purchase_number' => ['in', $codes]])->select();
            $collectionIds = array_filter(array_unique(array_column($collectionRows, 'collection_id')));
            $collectionMap = $collectionIds ? Db::name('kuerp_collection')->where(['id' => ['in', $collectionIds]])->column('purchase_code,purchase_time,examine_status,money,write_off,discount,advance', 'id') : [];
            $grouped = [];
            foreach ($collectionRows as $cr) {
                $cid = $cr['collection_id'];
                if (!isset($grouped[$cid])) {
                    $meta = isset($collectionMap[$cid]) ? $collectionMap[$cid] : null;
                    // 收款单金额口径：money=实际收款合计，write_off=该单总核销，advance=本次预收款
                    $collectionMoney = $meta ? (float)$meta['money'] : 0;
                    $collectionWriteOff = $meta ? (float)$meta['write_off'] : 0;
                    $collectionAdvance = $meta && $meta['advance'] !== null && $meta['advance'] !== ''
                        ? (float)$meta['advance']
                        : max($collectionMoney - $collectionWriteOff - ($meta ? (float)$meta['discount'] : 0), 0);
                    $grouped[$cid] = [
                        'collection_id'   => $cid,
                        'purchase_code'   => $meta ? $meta['purchase_code'] : '',
                        'purchase_time'   => $meta && $meta['purchase_time'] ? date('Y-m-d', $meta['purchase_time']) : '',
                        'examine_status'  => $meta ? $meta['examine_status'] : '0',
                        'money'           => $collectionMoney,          // 实际收款金额（收款单收款合计）
                        'advance'         => $collectionAdvance,        // 本次预收款金额
                        'write_off'       => 0,                         // 本对账单所含单据的核销金额（分单汇总）
                    ];
                }
                $grouped[$cid]['write_off'] += (float)$cr['write_off_price'];
            }
            $collections = array_values($grouped);
        }

        // 操作日志：对账单日志 + 所含销货单调整日志
        $logs = Db::name('kuerp_sales_recon_log')->where(['recon_id' => $row['id']])->order('id desc')->select();
        $logAdminMap = $this->adminNameMap(array_column($logs, 'admin_id'));
        foreach ($logs as $k => $log) {
            $logs[$k]['admin_name'] = isset($logAdminMap[$log['admin_id']]) ? $logAdminMap[$log['admin_id']] : '';
            $logs[$k]['time_text'] = $log['createtime'] ? date('Y-m-d H:i:s', $log['createtime']) : '';
        }
        $salesIds = [];
        foreach ($docs as $doc) {
            if ($doc['doc_type'] == '1') {
                $salesIds[] = $doc['doc_id'];
            }
        }
        $salesLogs = [];
        if ($salesIds) {
            $salesLogs = Db::name('kuerp_goods_sales_log')->where(['sales_id' => ['in', $salesIds]])->order('id desc')->limit(100)->select();
            $saleCodeMap = [];
            foreach ($docs as $doc) {
                $saleCodeMap[$doc['doc_id']] = $doc['doc_code'];
            }
            $slAdminMap = $this->adminNameMap(array_column($salesLogs, 'admin_id'));
            foreach ($salesLogs as $k => $log) {
                $salesLogs[$k]['doc_code'] = isset($saleCodeMap[$log['sales_id']]) ? $saleCodeMap[$log['sales_id']] : '';
                $salesLogs[$k]['admin_name'] = isset($slAdminMap[$log['admin_id']]) ? $slAdminMap[$log['admin_id']] : '';
                $salesLogs[$k]['time_text'] = $log['createtime'] ? date('Y-m-d H:i:s', $log['createtime']) : '';
            }
        }

        $this->view->assign('row', $row);
        $this->view->assign('docs', $docs);
        $this->view->assign('groups', $groups);
        $this->view->assign('pastList', $past['list']);
        $this->view->assign('pastTotal', round($past['total'], 4));
        $this->view->assign('totalUnpaid', $totalUnpaid);
        $this->view->assign('invoices', $invoices);
        $this->view->assign('today', $today);
        $this->view->assign('shouldInvoice', $shouldInvoice);
        $this->view->assign('collections', $collections);
        $this->view->assign('logs', $logs);
        // 对账差异（异议调整）
        $diffList = \app\admin\model\kuerp\SalesReconDiff::listByRecon($row['id']);
        $this->view->assign('diffList', $diffList);
        $this->view->assign('diffTotal', round(array_sum(array_column($diffList, 'diff_money')), 4));
        $this->view->assign('salesLogs', $salesLogs);
        $this->view->assign('companyName', $this->companyName());
        return $this->view->fetch();
    }

    /**
     * 编辑（待对账：增删单据 / 改备注）
     */
    public function edit($ids = null)
    {
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->assignconfig('reconId', (int)$row['id']);
        if ($row['recon_status'] != '0') {
            $this->error('该对账单已进入审核流程，不能编辑');
        }
        if ($this->request->isPost()) {
            $keys = $this->parseDocKeys($this->request->post('docs', ''));
            if (empty($keys)) {
                $this->error('对账单至少需要一张单据');
            }
            $remark = trim((string)$this->request->post('remark', ''));
            $docs = $this->checkDocuments($keys, $row['id']);
            foreach ($docs as $doc) {
                if ($doc['customers_id'] != $row['customers_id']) {
                    $this->error('只能选择同一客户（' . $row['customer_name'] . '）的单据');
                }
            }
            if (empty($docs)) {
                $this->error('所选单据均不可对账');
            }

            // 释放被移除的单据
            $oldDocs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->select();
            $oldKeys = [];
            foreach ($oldDocs as $oldDoc) {
                $oldKeys[$oldDoc['doc_type'] . ':' . $oldDoc['doc_id']] = $oldDoc;
            }
            $newKeyMap = array_fill_keys($keys, true);
            foreach ($oldKeys as $key => $oldDoc) {
                if (!isset($newKeyMap[$key])) {
                    $this->writebackOne($oldDoc['doc_type'], $oldDoc['doc_id'], 0, '', '0');
                }
            }

            Db::startTrans();
            try {
                Db::name('kuerp_sales_recon')->where(['id' => $row['id']])->update([
                    'remark'          => mb_substr($remark, 0, 250),
                    'operation_id'    => Session::get('admin')['id'],
                    'updatetime'      => time(),
                ]);
                $this->buildSnapshot($row['id'], $keys);
                $this->writebackDocuments(array_keys($docs), $row['id'], $row['recon_code'], '0');
                $this->writeLog($row['id'], '编辑对账单', '当前单据 ' . count($docs) . ' 张');
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('保存成功');
        }

        list($docs, $groups) = $this->loadSnapshot($row['id']);
        $checkedKeys = [];
        foreach ($docs as $doc) {
            $checkedKeys[$doc['doc_type'] . ':' . $doc['doc_id']] = true;
        }
        $filters = [
            'customers_id'   => (int)$row['customers_id'],
            'sale_user'      => '',
            'start'          => (int)$row['period_start'],
            'end'            => (int)$row['period_end'],
            'doc_type'       => '',
            'out_status'     => '',
            'recon_status'   => '',
            'invoice_status' => '',
            'pay_status'     => '',
            'recon_filter'   => $row['id'],
        ];
        list($candSales, $candReturns) = $this->queryDocuments($filters);
        $candRows = $this->buildItemRows($candSales, $candReturns);
        $candidates = [];
        foreach ($candRows as $candRow) {
            $key = $candRow['doc_key'];
            if (!isset($candidates[$key])) {
                $candidates[$key] = [
                    'doc_key'       => $key,
                    'doc_type'      => $candRow['doc_type'],
                    'doc_code'      => $candRow['doc_code'],
                    'doc_time_text' => $candRow['doc_time_text'],
                    'customer_name' => $candRow['customer_name'],
                    'number'        => 0,
                    'line_amount'   => 0,
                    'checked'       => isset($checkedKeys[$key]),
                ];
            }
            $candidates[$key]['number'] += $candRow['number'];
            $candidates[$key]['line_amount'] += $candRow['line_amount'];
        }
        $this->view->assign('row', $row);
        $this->view->assign('docs', $docs);
        $this->view->assign('groups', $groups);
        $this->view->assign('candidates', array_values($candidates));
        $this->view->assign('dateStart', $row['period_start'] ? date('Y-m-d', $row['period_start']) : '');
        $this->view->assign('dateEnd', $row['period_end'] ? date('Y-m-d', $row['period_end']) : '');
        return $this->view->fetch();
    }

    /**
     * 删除（仅待对账且未开票未收款）
     */
    public function del($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $this->toIdArray($ids ?: $this->request->post('ids'));
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $rows = Db::name('kuerp_sales_recon')->where(['id' => ['in', $ids]])->select();
        Db::startTrans();
        try {
            foreach ($rows as $row) {
                if ($row['recon_status'] != '0') {
                    throw new Exception('对账单' . $row['recon_code'] . '已进入审核流程，不能删除');
                }
                $pay = $this->calcPay($row['id']);
                if ($row['invoice_status'] != '0' || $pay['pay_status'] != '0') {
                    throw new Exception('对账单' . $row['recon_code'] . '已开票或已有收款，不能删除');
                }
                $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->select();
                foreach ($docs as $doc) {
                    $this->writebackOne($doc['doc_type'], $doc['doc_id'], 0, '', '0');
                }
                Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->delete();
                Db::name('kuerp_sales_recon_info')->where(['recon_id' => $row['id']])->delete();
                Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $row['id']])->delete();
                Db::name('kuerp_sales_recon_log')->where(['recon_id' => $row['id']])->delete();
                Db::name('kuerp_sales_recon')->where(['id' => $row['id']])->delete();
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    /**
     * 刷新明细（撤销/驳回后单据被改动时重新快照）
     */
    public function sync($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($row['recon_status'] != '0') {
            $this->error('只能刷新待对账状态的对账单');
        }
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->select();
        $keys = [];
        foreach ($docs as $doc) {
            $keys[] = $doc['doc_type'] . ':' . $doc['doc_id'];
        }
        if (empty($keys)) {
            $this->error('对账单没有单据');
        }
        Db::startTrans();
        try {
            $this->buildSnapshot($row['id'], $keys);
            $this->writeLog($row['id'], '刷新明细', '重新快照 ' . count($keys) . ' 张单据');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('明细已刷新');
    }

    // =====================================================================
    // 一审 / 二审 / 驳回 / 撤销
    // =====================================================================

    /**
     * 一审（财务）
     */
    public function confirm($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($row['recon_status'] != '0') {
            $this->error('该对账单不是待对账状态');
        }
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->select();
        if (empty($docs)) {
            $this->error('对账单没有单据，不能一审');
        }
        $this->assertDocumentsValid($docs);

        $admin = Session::get('admin');
        Db::startTrans();
        try {
            $updated = Db::name('kuerp_sales_recon')
                ->where(['id' => $row['id'], 'recon_status' => '0'])
                ->update([
                    'recon_status'   => '1',
                    'examine_id'     => $admin['id'],
                    'examine_time'   => time(),
                    'reject_reason'  => '',
                    'operation_id'   => $admin['id'],
                    'updatetime'     => time(),
                ]);
            if (!$updated) {
                throw new Exception('对账单状态已变更，请刷新后重试');
            }
            foreach ($docs as $doc) {
                $this->writebackOne($doc['doc_type'], $doc['doc_id'], $row['id'], $row['recon_code'], '1');
            }
            $this->writeLog($row['id'], '一审通过', '');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('一审通过，已提交二审');
    }

    /**
     * 二审通过（主管/领导）
     */
    public function secondconfirm($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($row['recon_status'] != '1') {
            $this->error('该对账单不是待二审状态');
        }
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->select();
        $admin = Session::get('admin');
        Db::startTrans();
        try {
            $updated = Db::name('kuerp_sales_recon')
                ->where(['id' => $row['id'], 'recon_status' => '1'])
                ->update([
                    'recon_status'        => '2',
                    'second_examine_id'   => $admin['id'],
                    'second_examine_time' => time(),
                    'operation_id'        => $admin['id'],
                    'updatetime'          => time(),
                ]);
            if (!$updated) {
                throw new Exception('对账单状态已变更，请刷新后重试');
            }
            foreach ($docs as $doc) {
                $this->writebackOne($doc['doc_type'], $doc['doc_id'], $row['id'], $row['recon_code'], '2');
            }
            $this->writeLog($row['id'], '二审通过', '');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('二审通过，对账完成');
    }

    /**
     * 二审驳回（退回待对账并解锁）
     */
    public function reject($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $reason = trim((string)$this->request->post('reason', ''));
        if ($reason === '') {
            $this->error('请填写驳回原因');
        }
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($row['recon_status'] != '1') {
            $this->error('该对账单不是待二审状态');
        }
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->select();
        $admin = Session::get('admin');
        Db::startTrans();
        try {
            $updated = Db::name('kuerp_sales_recon')
                ->where(['id' => $row['id'], 'recon_status' => '1'])
                ->update([
                    'recon_status'       => '0',
                    'reject_reason'      => mb_substr($reason, 0, 250),
                    'examine_id'         => null,
                    'examine_time'       => null,
                    'second_examine_id'  => null,
                    'second_examine_time'=> null,
                    'operation_id'       => $admin['id'],
                    'updatetime'         => time(),
                ]);
            if (!$updated) {
                throw new Exception('对账单状态已变更，请刷新后重试');
            }
            foreach ($docs as $doc) {
                $this->writebackOne($doc['doc_type'], $doc['doc_id'], $row['id'], $row['recon_code'], '0');
            }
            $this->writeLog($row['id'], '二审驳回', $reason);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('已驳回，单据已解锁');
    }

    /**
     * 撤销对账
     */
    public function cancelconfirm($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!in_array($row['recon_status'], ['1', '2'])) {
            $this->error('该对账单不是已提交状态');
        }
        if ($row['invoice_status'] != '0') {
            $this->error('已开票的对账单不能撤销');
        }
        $pay = $this->calcPay($row['id']);
        if ($pay['pay_status'] != '0') {
            $this->error('已有收款的对账单不能撤销');
        }
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->select();
        $admin = Session::get('admin');
        Db::startTrans();
        try {
            Db::name('kuerp_sales_recon')->where(['id' => $row['id']])->update([
                'recon_status'        => '0',
                'examine_id'          => null,
                'examine_time'        => null,
                'second_examine_id'   => null,
                'second_examine_time' => null,
                'reject_reason'       => '',
                'operation_id'        => $admin['id'],
                'updatetime'          => time(),
            ]);
            foreach ($docs as $doc) {
                $this->writebackOne($doc['doc_type'], $doc['doc_id'], $row['id'], $row['recon_code'], '0');
            }
            $this->writeLog($row['id'], '撤销对账', '');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('已撤销对账，单据已解锁');
    }

    // =====================================================================
    // 对账差异金额
    // =====================================================================

    /**
     * 差异金额管理（GET 列表 / POST 新增）
     *
     * 异议被驳回后，销售/财务可对单张销货单（或销货退货单）登记差异金额：
     *   正数 = 客户要多付（如客户认可的补差）
     *   负数 = 客户少付（售后折让、抹零等）
     * 差异叠加在该单据的应收上，并同步 kuerp_writeoff_source 让收款单能核销到。
     */
    public function diff($ids = null)
    {
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->assignconfig('reconId', (int)$row['id']);
        // 视图里用 $pd 格式化金额（与 detail.html 同口径）
        $pd = (int)\addons\kuerp\library\Goods::getSetData()['setList']['price_decimal']['value'];
        $this->assignconfig('pd', $pd);
        $this->view->assign('pd', $pd);
        if ($this->request->isPost()) {
            $this->saveDiff($row);
        }

        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $row['id']])->order('sort asc,id asc')->select();
        $diffMap = \app\admin\model\kuerp\SalesReconDiff::sumByCodes(array_column($docs, 'doc_code'));
        foreach ($docs as $k => $doc) {
            $docs[$k]['doc_type_text'] = $doc['doc_type'] == '1' ? '销货单' : '销货退货单';
            $docs[$k]['diff_money'] = isset($diffMap[$doc['doc_code']]) ? $diffMap[$doc['doc_code']] : 0;
            $docs[$k]['amount_with_diff'] = round((float)$doc['total_amount'] + $docs[$k]['diff_money'], 4);
        }
        $list = \app\admin\model\kuerp\SalesReconDiff::listByRecon($row['id']);

        $this->view->assign('row', $row);
        $this->view->assign('docs', $docs);
        $this->view->assign('diffList', $list);
        $this->view->assign('diffTotal', round(array_sum(array_column($list, 'diff_money')), 4));
        $this->view->assign('canEdit', $row['recon_status'] != '2' && $row['pay_status'] != '2');
        return $this->view->fetch();
    }

    /**
     * 新增一条差异记录
     *
     * @param array $recon
     * @throws \think\exception\HttpResponseException
     */
    protected function saveDiff($recon)
    {
        if ($recon['recon_status'] == '2') {
            $this->error('已对账的对账单不能再加差异，请先撤销对账');
        }
        if ($recon['pay_status'] == '2') {
            $this->error('该对账单已收款完成，不能再加差异');
        }
        $docCode = trim((string)$this->request->post('doc_code', ''));
        $reason = trim((string)$this->request->post('reason', ''));
        $diffMoney = (float)$this->request->post('diff_money', 0);
        $enclosureIds = $this->toIdArray($this->request->post('enclosure_ids', ''));

        if ($docCode === '') {
            $this->error('请选择单据');
        }
        if ($reason === '') {
            $this->error('请填写差异原因');
        }
        if (abs($diffMoney) < 0.0001) {
            $this->error('差异金额不能为 0');
        }
        $doc = Db::name('kuerp_sales_recon_doc')
            ->where(['recon_id' => $recon['id'], 'doc_code' => $docCode])
            ->find();
        if (!$doc) {
            $this->error('该单据不在此对账单内');
        }

        $admin = Session::get('admin');
        Db::startTrans();
        try {
            $diffId = Db::name('kuerp_sales_recon_diff')->insertGetId([
                'recon_id'     => $recon['id'],
                'doc_type'     => $doc['doc_type'],
                'doc_id'       => $doc['doc_id'],
                'doc_code'     => $doc['doc_code'],
                'customers_id' => $recon['customers_id'],
                'diff_money'   => round($diffMoney, 4),
                'reason'       => mb_substr($reason, 0, 250),
                'admin_id'     => isset($admin['id']) ? $admin['id'] : 0,
                'createtime'   => time(),
                'updatetime'   => time(),
            ]);
            // 附件关联（type=13）
            foreach ($enclosureIds as $enclosureId) {
                $enclosure = Db::name('kuerp_enclosure')->where(['id' => $enclosureId])->find();
                if (!$enclosure) {
                    continue;
                }
                Db::name('kuerp_enclosure_log')->insert([
                    'enclosure_id'  => $enclosureId,
                    'order_id'      => $diffId,
                    'purchase_code' => $doc['doc_code'],
                    'type'          => '13',
                ]);
            }
            $this->refreshDiffTotals($recon['id']);
            $this->writeLog($recon['id'], '登记差异金额', $doc['doc_code'] . ' ' . ($diffMoney > 0 ? '+' : '') . $diffMoney . '｜' . $reason);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('差异金额已登记');
    }

    /**
     * 删除差异记录
     */
    public function deldiff($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $diff = Db::name('kuerp_sales_recon_diff')->where(['id' => $ids])->find();
        if (!$diff) {
            $this->error('差异记录不存在');
        }
        $recon = Db::name('kuerp_sales_recon')->where(['id' => $diff['recon_id']])->find();
        if (!$recon) {
            $this->error('对账单不存在');
        }
        if ($recon['recon_status'] == '2') {
            $this->error('已对账的对账单不能删除差异，请先撤销对账');
        }
        if ($recon['pay_status'] == '2') {
            $this->error('该对账单已收款完成，不能删除差异');
        }
        Db::startTrans();
        try {
            Db::name('kuerp_sales_recon_diff')->where(['id' => $diff['id']])->delete();
            // 清掉该差异的附件关联（原始单据记录本身保留，可被其它单据复用）
            Db::name('kuerp_enclosure_log')->where(['order_id' => $diff['id'], 'type' => '13'])->delete();
            $this->refreshDiffTotals($recon['id']);
            $this->writeLog($recon['id'], '删除差异金额', $diff['doc_code'] . ' ' . $diff['diff_money'] . '｜' . $diff['reason']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('已删除');
    }

    /**
     * 差异附件上传（type=13，先上传拿到 enclosure_id，保存差异时再关联）
     */
    public function uploaddiff()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
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
            'type'        => 2,
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

    /**
     * 重算差异合计（对账单 + 源单据 + 开票台账 + 收款状态）
     *
     * @param int $reconId
     */
    protected function refreshDiffTotals($reconId)
    {
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $reconId])->select();
        $codes = array_column($docs, 'doc_code');
        $diffMap = \app\admin\model\kuerp\SalesReconDiff::sumByCodes($codes);

        $total = 0;
        foreach ($docs as $doc) {
            $diff = isset($diffMap[$doc['doc_code']]) ? $diffMap[$doc['doc_code']] : 0;
            // 单据级快照：差异单独一列，total_amount 保持原值，避免影响模板里「总金额」列的口径
            Db::name('kuerp_sales_recon_doc')->where(['id' => $doc['id']])->update([
                'diff_money' => round($diff, 4),
            ]);
            $total += $diff;
            $this->writebackDiff($doc['doc_type'], $doc['doc_code'], $diff);
        }
        Db::name('kuerp_sales_recon')->where(['id' => $reconId])->update([
            'diff_money' => round($total, 4),
            'updatetime' => time(),
        ]);
        // 差异改变了可开票/可收款口径：开票台账与收款状态都需要重算
        $this->syncInvoiceLedger($reconId);
        $this->calcPay($reconId);
    }

    /**
     * 把差异合计写回源单据 + 核销源单，使收款单可核销额度包含差异
     *
     * @param string $docType 1=销货单 2=销货退货单
     * @param string $docCode
     * @param float  $diff
     */
    protected function writebackDiff($docType, $docCode, $diff)
    {
        $diff = round((float)$diff, 4);
        if ($docType == '1') {
            $sales = Db::name('kuerp_goods_sales')->where(['purchase_code' => $docCode])->find();
            if (!$sales) {
                return;
            }
            Db::name('kuerp_goods_sales')->where(['id' => $sales['id']])->update(['diff_money' => $diff]);
            // 应收口径统一为 real_money + customers_money + diff_money
            $price = round((float)$sales['real_money'] + (float)$sales['customers_money'] + $diff, 4);
            $payed = round((float)$sales['pay_money'], 4);
            $source = Db::name('kuerp_writeoff_source')
                ->where(['purchase_code' => $docCode, 'purchase_type' => '3', 'type' => '1'])
                ->find();
            if ($source) {
                Db::name('kuerp_writeoff_source')->where(['id' => $source['id']])->update([
                    'price'     => $price,
                    'write_off' => round($price - $payed, 4),
                ]);
            } else {
                // 历史单据可能没有核销源单，补建一条，否则收款单「选择源单」里看不到它
                Db::name('kuerp_writeoff_source')->insert([
                    'purchase_type' => '3',
                    'type'          => '1',
                    'customers_id'  => $sales['customers_id'],
                    'purchase_code' => $sales['purchase_code'],
                    'purchase_time' => $sales['purchase_time'],
                    'price'         => $price,
                    'money'         => $payed,
                    'write_off'     => round($price - $payed, 4),
                    'remark'        => $sales['note'],
                ]);
            }
            return;
        }
        $exchange = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code' => $docCode])->find();
        if (!$exchange) {
            return;
        }
        Db::name('kuerp_goods_sales_exchange')->where(['id' => $exchange['id']])->update(['diff_money' => $diff]);
        // 退货单金额本身是负数，差异直接叠加在原负数上
        $price = round(-((float)$exchange['real_money'] + (float)$exchange['customers_money']) + $diff, 4);
        $payed = round(-(float)$exchange['retreat_money'], 4);
        $source = Db::name('kuerp_writeoff_source')
            ->where(['purchase_code' => $docCode, 'purchase_type' => '3', 'type' => '2'])
            ->find();
        if ($source) {
            Db::name('kuerp_writeoff_source')->where(['id' => $source['id']])->update([
                'price'     => $price,
                'write_off' => round($price - $payed, 4),
            ]);
        } else {
            Db::name('kuerp_writeoff_source')->insert([
                'purchase_type' => '3',
                'type'          => '2',
                'customers_id'  => $exchange['customers_id'],
                'purchase_code' => $exchange['purchase_code'],
                'purchase_time' => $exchange['purchase_time'],
                'price'         => $price,
                'money'         => $payed,
                'write_off'     => round($price - $payed, 4),
                'remark'        => $exchange['note'],
            ]);
        }
    }

    // =====================================================================
    // 发票（可多张）
    // =====================================================================

    /**
     * 开票（GET 列表 / POST 新增）
     */
    public function invoice($ids = null)
    {
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->assignconfig('reconId', (int)$row['id']);
        // 开票基数 = total_amount（销货合计 + 客户承担费用）+ diff_money（对账差异），
        // 与应收口径 total_amount + diff_money 保持一致（差异负数=客户少付，开票金额同步扣减）。
        // 按当前口径在内存里重算开票状态（不写库），历史按旧口径判定的「已开票」在新口径下可能只是部分开票
        $invoiceBase = $this->invoiceBase($row);
        $row['invoice_base'] = $invoiceBase;
        $invoicedBefore = (float)Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $row['id']])->sum('invoice_amount');
        $remainingBefore = round($invoiceBase - $invoicedBefore, 4);
        $row['invoice_amount'] = $invoicedBefore;
        if ($invoicedBefore <= 0.0001) {
            $row['invoice_status'] = '0';
        } elseif ($remainingBefore <= 0.0001) {
            $row['invoice_status'] = '2';
        } else {
            $row['invoice_status'] = '1';
        }
        if ($this->request->isPost()) {
            if ($row['recon_status'] != '2') {
                $this->error('对账单尚未二审通过，不能开票');
            }
            if ($invoiceBase <= 0.0001) {
                $this->error('该对账单应开票金额为 0，无需开票');
            }
            if ($remainingBefore <= 0.0001) {
                $this->error('该对账单已全部开票');
            }
            $enclosureId = (int)$this->request->post('enclosure_id', 0);
            $invoiceNo = trim((string)$this->request->post('invoice_no', ''));
            $invoiceAmount = round((float)$this->request->post('invoice_amount', 0), 4);
            $invoiceDate = $this->parseDate($this->request->post('invoice_date', ''));
            // 账期天数：不传（编辑/老调用方）时取客户档案的信用账期；0=不计算到期日
            $termDaysRaw = $this->request->post('term_days', '');
            if ($termDaysRaw === '' || $termDaysRaw === null) {
                $termDays = (int)Db::name('kuerp_customers')
                    ->where(['id' => $row['customers_id']])->value('credit_days');
            } else {
                $termDays = (int)$termDaysRaw;
            }
            $termDays = max($termDays, 0);
            $invoiceTime = $invoiceDate ?: time();
            // 到期日 = 开票日 + 账期天数（账期在开票这一刻固化快照）
            $dueDate = $termDays > 0 ? $invoiceTime + $termDays * 86400 : null;
            if ($enclosureId <= 0) {
                $this->error('请上传发票文件');
            }
            if ($invoiceNo === '') {
                $this->error('请填写发票号');
            }
            if ($invoiceAmount <= 0) {
                $this->error('开票金额必须大于 0');
            }
            $exist = Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $row['id'], 'invoice_no' => $invoiceNo])->count();
            if ($exist) {
                $this->error('发票号 ' . $invoiceNo . ' 已存在');
            }
            $invoiced = (float)Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $row['id']])->sum('invoice_amount');
            $remaining = round($invoiceBase - $invoiced, 4);
            if ($invoiceAmount > $remaining + 0.0001) {
                $this->error('开票金额不能超过剩余可开票金额 ' . $remaining);
            }
            $logRow = Db::name('kuerp_enclosure_log')->where(['enclosure_id' => $enclosureId, 'type' => '12'])->find();
            if (!$logRow) {
                $this->error('发票文件未上传成功，请重新上传');
            }
            if ($logRow['order_id'] != $row['id']) {
                $this->error('发票文件不属于当前对账单');
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                Db::name('kuerp_sales_recon_invoice')->insert([
                    'recon_id'       => $row['id'],
                    'enclosure_id'   => $enclosureId,
                    'invoice_no'     => mb_substr($invoiceNo, 0, 100),
                    'invoice_amount' => $invoiceAmount,
                    'invoice_date'   => $invoiceTime,
                    'term_days'      => $termDays,
                    'due_date'       => $dueDate,
                    'upload_date'    => time(),
                    'admin_id'       => isset($admin['id']) ? $admin['id'] : 0,
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]);
                $this->refreshInvoiceStatus($row['id']);
                $this->syncInvoiceLedger($row['id']);
                $this->writeLog($row['id'], '新增发票', $invoiceNo . ' 金额 ' . $invoiceAmount);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('发票已保存');
        }

        $invoices = Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $row['id']])->order('id asc')->select();
        $today = strtotime(date('Y-m-d'));
        $pay = $this->calcPay($row['id']);
        foreach ($invoices as $k => $invoice) {
            $invoices[$k]['file'] = $invoice['enclosure_id'] ? $this->enclosureOne($invoice['enclosure_id']) : null;
            $invoices[$k]['invoice_date_text'] = $invoice['invoice_date'] ? date('Y-m-d', $invoice['invoice_date']) : '';
            $dueDate = (int)$invoice['due_date'];
            $invoices[$k]['due_date_text'] = $dueDate ? date('Y-m-d', $dueDate) : '';
            $invoices[$k]['days_left'] = $dueDate ? (int)floor(($dueDate - $today) / 86400) : null;
            $invoices[$k]['term_state'] = $this->termState($dueDate, $pay['pay_status'], $today, $this->remindDays);
        }
        $customerCreditDays = (int)Db::name('kuerp_customers')
            ->where(['id' => $row['customers_id']])->value('credit_days');
        $invoiced = (float)Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $row['id']])->sum('invoice_amount');
        $this->view->assign('row', $row);
        $this->view->assign('invoices', $invoices);
        $this->view->assign('invoiced', round($invoiced, 4));
        // 剩余可开按开票基数（含对账差异）计算，负值（历史超开）钳到 0 展示
        $this->view->assign('remaining', max(0, round($invoiceBase - $invoiced, 4)));
        $this->view->assign('customerCreditDays', $customerCreditDays);
        return $this->view->fetch();
    }

    /**
     * 删除发票
     */
    public function delinvoice($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $invoice = Db::name('kuerp_sales_recon_invoice')->where(['id' => $ids])->find();
        if (!$invoice) {
            $this->error('发票不存在');
        }
        $row = Db::name('kuerp_sales_recon')->where(['id' => $invoice['recon_id']])->find();
        if (!$row) {
            $this->error('对账单不存在');
        }
        if ($row['recon_status'] != '2') {
            $this->error('对账单未二审通过，不能删除发票');
        }
        $pay = $this->calcPay($row['id']);
        if ($pay['pay_status'] != '0') {
            $this->error('对账单已有收款，不能删除发票');
        }
        Db::startTrans();
        try {
            Db::name('kuerp_sales_recon_invoice')->where(['id' => $invoice['id']])->delete();
            if ($invoice['enclosure_id']) {
                Db::name('kuerp_enclosure_log')->where(['enclosure_id' => $invoice['enclosure_id'], 'type' => '12'])->delete();
                Db::name('kuerp_enclosure')->where(['id' => $invoice['enclosure_id']])->delete();
            }
            $this->refreshInvoiceStatus($row['id']);
            $this->syncInvoiceLedger($row['id']);
            $this->writeLog($row['id'], '删除发票', $invoice['invoice_no']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('发票已删除');
    }

    /**
     * 上传发票文件（type=12）
     */
    public function uploadfile($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $recon = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$recon) {
            $this->error('对账单不存在');
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
                'type'        => 2,
                'status'      => 1,
                'upload_date' => date('Y-m-d'),
                'createtime'  => time(),
                'updatetime'  => time(),
            ]);
            Db::name('kuerp_enclosure_log')->insert([
                'enclosure_id'  => $enclosureId,
                'order_id'      => $recon['id'],
                'purchase_code' => $recon['recon_code'],
                'type'          => '12',
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

    // =====================================================================
    // 导出 / 打印
    // =====================================================================

    /**
     * 导出 Excel（版式按 资料/财务功能/对账模板.xls）
     */
    public function export()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $this->toIdArray($this->request->post('ids', ''));
        if (empty($ids)) {
            $this->error('请选择要导出的对账单');
        }
        $recons = Db::name('kuerp_sales_recon')->where(['id' => ['in', $ids]])->order('id asc')->select();
        if (empty($recons)) {
            $this->error('未找到对账单');
        }
        $first = $recons[0];
        $fileName = $first['customer_name']
            . ($first['period_start'] ? date('Y-m-d', $first['period_start']) : '')
            . '-'
            . ($first['period_end'] ? date('Y-m-d', $first['period_end']) : '')
            . '.xlsx';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('对账单模板');

        $headers = ['销售单号', '对账日期', '销售日期', '公司名称', '商品名称', '型号/规格', '数量',
            '含税单价', '未税单价', '税点', '销货合计金额', '客户承担费用', '总金额', '备注', '物流单号'];
        $colCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $sheet->mergeCells('B1:' . $lastCol . '1');
        $sheet->setCellValue('B1', $this->companyName() . '对账单');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $rowIndex = 2;
        foreach ($headers as $i => $title) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . $rowIndex, $title);
        }
        $sheet->getStyle('A2:' . $lastCol . '2')->getFont()->setBold(true);
        $sheet->getStyle('A2:' . $lastCol . '2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $rowIndex = 3;

        $numberDecimal = $this->decimal('number_decimal', 2);
        $priceDecimal = $this->decimal('price_decimal', 2);

        foreach ($recons as $recon) {
            $pay = $this->calcPay($recon['id']);
            $recon['pay_money'] = $pay['pay_money'];
            list($docs, $groups) = $this->loadSnapshot($recon['id']);
            foreach ($groups as $group) {
                $doc = $group['doc'];
                $firstLine = true;
                foreach ($group['items'] as $item) {
                    $sheet->setCellValueExplicit('A' . $rowIndex, $doc['doc_code'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B' . $rowIndex, date('Y-m-d', $recon['createtime']));
                    $sheet->setCellValue('C' . $rowIndex, $doc['doc_time'] ? date('Y-m-d', $doc['doc_time']) : '');
                    $sheet->setCellValue('D' . $rowIndex, $recon['customer_name']);
                    $sheet->setCellValue('E' . $rowIndex, $item['goods_name']);
                    $sheet->setCellValue('F' . $rowIndex, $item['specification']);
                    $sheet->setCellValue('G' . $rowIndex, round($item['number'], $numberDecimal));
                    $sheet->setCellValue('H' . $rowIndex, round($item['tax_included'], $priceDecimal));
                    $sheet->setCellValue('I' . $rowIndex, round($item['unit_price'], $priceDecimal));
                    $sheet->setCellValue('J' . $rowIndex, round($item['tax_rate'], 2));
                    $sheet->setCellValue('K' . $rowIndex, round($item['line_amount'], $priceDecimal));
                    if ($firstLine) {
                        $sheet->setCellValue('L' . $rowIndex, round($doc['customers_money'], $priceDecimal));
                        $sheet->setCellValue('M' . $rowIndex, round($doc['total_amount'], $priceDecimal));
                        $sheet->setCellValue('O' . $rowIndex, $doc['express_no']);
                        $firstLine = false;
                    }
                    $sheet->setCellValue('N' . $rowIndex, $item['remark']);
                    $rowIndex++;
                }
            }

            $sheet->mergeCells('B' . $rowIndex . ':J' . $rowIndex);
            $sheet->setCellValue('B' . $rowIndex, '本期合计：');
            $sheet->setCellValue('K' . $rowIndex, round($recon['total_money'], $priceDecimal));
            $sheet->setCellValue('L' . $rowIndex, round($recon['total_customers_money'], $priceDecimal));
            $sheet->setCellValue('M' . $rowIndex, round($recon['total_amount'], $priceDecimal));
            $sheet->getStyle('B' . $rowIndex)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $rowIndex++;

            $past = $this->pastUnpaid($recon['customers_id'], $recon['id']);
            foreach ($past['list'] as $month => $amount) {
                $sheet->mergeCells('B' . $rowIndex . ':J' . $rowIndex);
                $sheet->setCellValue('B' . $rowIndex, $month . '已对账已开票（或者未开票）未付款合计：');
                $sheet->setCellValue('M' . $rowIndex, round($amount, $priceDecimal));
                $sheet->getStyle('B' . $rowIndex)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $rowIndex++;
            }
            $sheet->mergeCells('B' . $rowIndex . ':J' . $rowIndex);
            $sheet->setCellValue('B' . $rowIndex, '总未付款金额合计：');
            $sheet->setCellValue('M' . $rowIndex, round($past['total'], $priceDecimal));
            $sheet->getStyle('B' . $rowIndex)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $rowIndex++;

            $sheet->mergeCells('B' . $rowIndex . ':O' . $rowIndex);
            $sheet->setCellValue('B' . $rowIndex, '请贵公司核对本对账单金额，无误后盖章回传确认，如有异议，请在收到此账单后三个工作日内回复，逾期未提出异议，视为认可本对账单金额。谢谢！');
            $rowIndex += 2;
        }

        foreach (range(1, $colCount) as $i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setWidth(in_array($i, [1, 4, 5, 14, 15]) ? 22 : 14);
        }
        $sheet->getStyle('A2:' . $lastCol . ($rowIndex - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . rawurlencode($fileName) . '";filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * 打印页
     */
    public function printpage($ids = null)
    {
        $row = Db::name('kuerp_sales_recon')->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Db::name('kuerp_sales_recon')->where(['id' => $row['id']])->inc('print_num')->update();
        list($docs, $groups) = $this->loadSnapshot($row['id']);
        $pay = $this->calcPay($row['id']);
        $row['pay_money'] = $pay['pay_money'];
        $past = $this->pastUnpaid($row['customers_id'], $row['id']);
        $this->view->assign('row', $row);
        $this->view->assign('groups', $groups);
        $this->view->assign('pastList', $past['list']);
        $this->view->assign('totalUnpaid', round($past['total'], 4));
        $this->view->assign('companyName', $this->companyName());
        return $this->view->fetch();
    }

    // =====================================================================
    // 辅助接口
    // =====================================================================

    /**
     * 客户下拉
     */
    public function customerslist()
    {
        return json(['total' => count($this->customerOptions()), 'rows' => $this->customerOptions()]);
    }

    /**
     * 编辑页可选单据（同客户、未对账或已在本单）
     */
    public function salesdocs()
    {
        $reconId = (int)$this->request->request('recon_id', 0);
        $customersId = (int)$this->request->request('customers_id', 0);
        $periodStart = $this->parseDate($this->request->request('period_start', ''));
        $periodEnd = $this->parseDate($this->request->request('period_end', ''));
        $recon = $reconId ? Db::name('kuerp_sales_recon')->where(['id' => $reconId])->find() : null;
        if ($recon) {
            $customersId = (int)$recon['customers_id'];
            $periodStart = $periodStart ?: (int)$recon['period_start'];
            $periodEnd = $periodEnd ?: (int)$recon['period_end'];
        }
        if (!$customersId) {
            return json(['total' => 0, 'rows' => []]);
        }
        $filters = [
            'customers_id'  => $customersId,
            'sale_user'     => '',
            'start'         => $periodStart,
            'end'           => $periodEnd,
            'recon_filter'  => $reconId ? $reconId : '0',
            'doc_type'      => '',
        ];
        list($sales, $returns) = $this->queryDocuments($filters);
        $rows = $this->buildItemRows($sales, $returns);
        return json(['total' => count($rows), 'rows' => $rows]);
    }

    // =====================================================================
    // 内部方法
    // =====================================================================

    /**
     * 解析工作台筛选条件
     */
    protected function parseItemFilters()
    {
        return [
            'customers_id'   => (int)$this->request->request('customers_id', 0),
            'sale_user'      => (int)$this->request->request('sale_user', 0),
            'start'          => $this->parseDate($this->request->request('date_start', '')),
            'end'            => $this->parseDate($this->request->request('date_end', '')),
            'doc_type'       => (string)$this->request->request('doc_type', ''),
            'out_status'     => (string)$this->request->request('out_status', ''),
            'recon_status'   => (string)$this->request->request('recon_status', ''),
            'invoice_status' => (string)$this->request->request('invoice_status', ''),
            'pay_status'     => (string)$this->request->request('pay_status', ''),
            // 查看已对账单据时不限制 recon_id，否则只看未对账单据
            'recon_filter'   => in_array((string)$this->request->request('recon_status', ''), ['1', '2'], true)
                ? 'all'
                : '0',
        ];
    }

    /**
     * 查询候选单据（销货单 + 销货退货单）
     *
     * @return array [salesRows, returnRows]
     */
    protected function queryDocuments($filters)
    {
        $base = [];
        if (!empty($filters['customers_id'])) {
            $base['customers_id'] = $filters['customers_id'];
        }
        if (!empty($filters['sale_user'])) {
            $base['sale_user'] = $filters['sale_user'];
        }
        $start = !empty($filters['start']) ? $filters['start'] : 0;
        $end = !empty($filters['end']) ? $filters['end'] : 0;

        $reconFilter = isset($filters['recon_filter']) ? $filters['recon_filter'] : '0';

        $build = function ($table) use ($base, $start, $end, $reconFilter) {
            $q = Db::name($table)->where(['examine_status' => '1'])->where($base);
            if ($reconFilter === '0') {
                $q->where('recon_id', 0);
            } elseif ($reconFilter === 'all') {
                // 不限制
            } elseif (is_numeric($reconFilter) && (int)$reconFilter > 0) {
                $q->where(function ($sub) use ($reconFilter) {
                    $sub->where('recon_id', 0)->whereOr('recon_id', (int)$reconFilter);
                });
            }
            if ($start) {
                $q->where('purchase_time', '>=', $start);
            }
            if ($end) {
                $q->where('purchase_time', '<=', $end + 86399);
            }
            return $q->order('purchase_time asc,id asc')->select();
        };

        $docType = isset($filters['doc_type']) ? (string)$filters['doc_type'] : '';
        $sales = ($docType === '2') ? [] : $build('kuerp_goods_sales');
        $returns = ($docType === '1') ? [] : $build('kuerp_goods_sales_exchange');
        return [$sales, $returns];
    }

    /**
     * 按单据状态过滤工作台行（非首行跟随所属单据，需先按单据判定再整体保留）
     */
    protected function applyItemFilters($rows, $filters)
    {
        $docPass = [];
        foreach ($rows as $row) {
            if (!$row['is_first']) {
                continue;
            }
            $pass = true;
            if ($filters['out_status'] !== '' && (string)$row['out_status'] !== $filters['out_status']) {
                $pass = false;
            }
            if ($pass && $filters['recon_status'] !== '' && (string)$row['recon_status'] !== $filters['recon_status']) {
                $pass = false;
            }
            if ($pass && $filters['invoice_status'] !== '' && (string)$row['invoice_status'] !== $filters['invoice_status']) {
                $pass = false;
            }
            if ($pass && $filters['pay_status'] !== '' && (string)$row['pay_status'] !== $filters['pay_status']) {
                $pass = false;
            }
            $docPass[$row['doc_key']] = $pass;
        }
        $result = [];
        foreach ($rows as $row) {
            if (isset($docPass[$row['doc_key']]) && $docPass[$row['doc_key']]) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * 按筛选条件取单据键（未勾选时"生成对账单"用）
     */
    protected function filteredDocKeys($filters)
    {
        list($sales, $returns) = $this->queryDocuments($filters);
        $rows = $this->applyItemFilters($this->buildItemRows($sales, $returns), $filters);
        $keys = [];
        foreach ($rows as $row) {
            if ($row['is_first']) {
                $keys[$row['doc_key']] = true;
            }
        }
        return array_keys($keys);
    }

    /**
     * 单据收款/退款状态 → 对账工作台口径
     *
     * 两套状态值语义相反，不能直接透传：
     *   销货单 pay_status / 退货单 retreat_status：0 未收(退)、1 全部收(退)、2 部分收(退)
     *     —— 写入见 Collection::examine()、Writeoff::examine()（$more_money <= 0 ? 1 : 2）
     *   对账工作台 / 对账单 sales_recon.pay_status：0 未收款、1 部分收款、2 已收款
     *     —— 见 SalesRecon 模型 recalcPay()、前端 payTag()、筛选下拉
     * 不转换会把「全部收款」显示成「部分收款」（反之亦然）。
     */
    protected function docPayStatus($status)
    {
        $status = (string)$status;
        if ($status === '1') {
            return '2';
        }
        if ($status === '2') {
            return '1';
        }
        return '0';
    }

    /**
     * 构造工作台行（商品行 + 单据级字段只放首行）
     */
    protected function buildItemRows($sales, $returns)
    {
        $docs = [];
        foreach ($sales as $row) {
            $docs[] = ['type' => '1', 'row' => $row];
        }
        foreach ($returns as $row) {
            $docs[] = ['type' => '2', 'row' => $row];
        }
        if (empty($docs)) {
            return [];
        }
        // 按单据日期倒序：最近的在前面（同一天按单号倒序，大号在前）
        usort($docs, function ($a, $b) {
            if ($a['row']['purchase_time'] == $b['row']['purchase_time']) {
                return strcmp($b['row']['purchase_code'], $a['row']['purchase_code']);
            }
            return $a['row']['purchase_time'] < $b['row']['purchase_time'] ? 1 : -1;
        });

        $codes = array_column(array_column($docs, 'row'), 'purchase_code');
        $salesCodes = [];
        $returnCodes = [];
        foreach ($docs as $doc) {
            if ($doc['type'] == '1') {
                $salesCodes[] = $doc['row']['purchase_code'];
            } else {
                $returnCodes[] = $doc['row']['purchase_code'];
            }
        }
        $salesInfos = $salesCodes ? Db::name('kuerp_goods_sales_info')->where(['purchase_code' => ['in', $salesCodes]])->order('id asc')->select() : [];
        $returnInfos = $returnCodes ? Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code' => ['in', $returnCodes]])->order('id asc')->select() : [];
        $infoMap = [];
        foreach ($salesInfos as $info) {
            $infoMap['1:' . $info['purchase_code']][] = $info;
        }
        foreach ($returnInfos as $info) {
            $infoMap['2:' . $info['purchase_code']][] = $info;
        }

        $goodsCodes = array_values(array_unique(array_column(array_merge($salesInfos, $returnInfos), 'goods_code')));
        $goodsMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('goods_name,goods_specification_id,vendor_name,packaging', 'goods_code') : [];
        $unitIds = array_values(array_unique(array_filter(array_column(array_merge($salesInfos, $returnInfos), 'goods_unit'))));
        $unitMap = $unitIds ? Db::name('kuerp_unit')->where(['id' => ['in', $unitIds]])->column('name', 'id') : [];
        $attrIds = array_values(array_unique(array_filter(array_column(array_merge($salesInfos, $returnInfos), 'goods_attr'))));
        $attrMap = $attrIds ? Db::name('kuerp_goods_attr')->where(['id' => ['in', $attrIds]])->column('attr_code', 'id') : [];

        $customerIds = array_values(array_unique(array_filter(array_column(array_column($docs, 'row'), 'customers_id'))));
        $customerMap = $customerIds ? Db::name('kuerp_customers')->where(['id' => ['in', $customerIds]])->column('customer_name', 'id') : [];
        $staffIds = array_values(array_unique(array_filter(array_column(array_column($docs, 'row'), 'sale_user'))));
        $staffMap = $staffIds ? Db::name('kuerp_staff_member')->where(['id' => ['in', $staffIds]])->column('name', 'id') : [];

        $outStatusMap = $this->outboundStatusMap($salesCodes);
        $invoiceStatusMap = $this->invoiceStatusMap($salesCodes);
        $expressMap = $this->expressNoMap($sales);

        $rows = [];
        foreach ($docs as $doc) {
            $type = $doc['type'];
            $d = $doc['row'];
            $sign = ($type == '2') ? -1 : 1;
            $infos = isset($infoMap[$type . ':' . $d['purchase_code']]) ? $infoMap[$type . ':' . $d['purchase_code']] : [];
            $lineAmounts = $this->allocateDiscount($infos, (float)$d['preferential_money']);
            $customersMoney = $sign * (float)$d['customers_money'];
            $realMoney = $sign * (float)$d['real_money'];
            $totalAmount = $realMoney + $customersMoney;

            if ($type == '1') {
                $outStatus = isset($outStatusMap[$d['purchase_code']]) ? $outStatusMap[$d['purchase_code']] : 0;
                $invoiceStatus = isset($invoiceStatusMap[$d['purchase_code']]) ? $invoiceStatusMap[$d['purchase_code']] : 0;
                $payStatus = $this->docPayStatus($d['pay_status']);
                $expressNo = isset($expressMap[$d['purchase_code']]) ? $expressMap[$d['purchase_code']] : '';
            } else {
                $outStatus = null;
                $invoiceStatus = null;
                $payStatus = $this->docPayStatus($d['retreat_status']);
                $expressNo = '';
            }

            if (empty($infos)) {
                // 孤儿明细：保留单据行，商品信息留空
                $rows[] = [
                    'doc_key'             => $type . ':' . $d['id'],
                    'doc_type'            => $type,
                    'doc_id'              => $d['id'],
                    'doc_code'            => $d['purchase_code'],
                    'doc_time'            => $d['purchase_time'],
                    'doc_time_text'       => $d['purchase_time'] ? date('Y-m-d', $d['purchase_time']) : '',
                    'customers_id'        => $d['customers_id'],
                    'customer_name'       => isset($customerMap[$d['customers_id']]) ? $customerMap[$d['customers_id']] : '',
                    'sale_user_name'      => isset($staffMap[$d['sale_user']]) ? $staffMap[$d['sale_user']] : '',
                    'goods_code'          => '',
                    'goods_name'          => '',
                    'specification'       => '',
                    'unit_name'           => '',
                    'number'              => 0,
                    'unit_price'          => 0,
                    'tax_included'        => 0,
                    'tax_rate'            => 0,
                    'line_amount'         => 0,
                    'purchase_order_id'   => '',
                    'remark'              => '',
                    'customers_money'     => $customersMoney,
                    'real_money'          => $realMoney,
                    'total_amount'        => $totalAmount,
                    'express_no'          => $expressNo,
                    'out_status'          => $outStatus,
                    'recon_status'        => $d['recon_status'],
                    'recon_code'          => $d['recon_code'],
                    'invoice_status'      => $invoiceStatus,
                    'pay_status'          => $payStatus,
                    'recon_id'            => $d['recon_id'],
                    'is_first'            => true,
                ];
                continue;
            }

            $first = true;
            foreach ($infos as $info) {
                $goods = isset($goodsMap[$info['goods_code']]) ? $goodsMap[$info['goods_code']] : [];
                $lineAmount = isset($lineAmounts[$info['id']]) ? $lineAmounts[$info['id']] : 0;
                $row = [
                    'doc_key'           => $type . ':' . $d['id'],
                    'doc_type'          => $type,
                    'doc_id'            => $d['id'],
                    'doc_code'          => $d['purchase_code'],
                    'doc_time'          => $d['purchase_time'],
                    'doc_time_text'     => $d['purchase_time'] ? date('Y-m-d', $d['purchase_time']) : '',
                    'customers_id'      => $d['customers_id'],
                    'customer_name'     => isset($customerMap[$d['customers_id']]) ? $customerMap[$d['customers_id']] : '',
                    'sale_user_name'    => isset($staffMap[$d['sale_user']]) ? $staffMap[$d['sale_user']] : '',
                    'goods_code'        => $info['goods_code'],
                    'goods_name'        => isset($goods['goods_name']) ? $goods['goods_name'] : '',
                    'specification'     => isset($goods['goods_specification_id']) ? $goods['goods_specification_id'] : '',
                    'vendor_name'       => isset($goods['vendor_name']) ? $goods['vendor_name'] : '',
                    'packaging'         => isset($goods['packaging']) ? $goods['packaging'] : '',
                    'attr_name'         => isset($attrMap[$info['goods_attr']]) ? $attrMap[$info['goods_attr']] : '',
                    'unit_name'         => isset($unitMap[$info['goods_unit']]) ? $unitMap[$info['goods_unit']] : '',
                    // 退货单明细的 number 是「原销货数量」，实际退货数量在 retreat_number（金额也按后者计算）
                    'number'            => $sign * (float)($type == '2' ? $info['retreat_number'] : $info['number']),
                    'unit_price'        => (float)$info['unit_price'],
                    'tax_included'      => (float)$info['tax_included'],
                    'tax_rate'          => (float)$info['tax_rate'],
                    'line_amount'       => $sign * $lineAmount,
                    'purchase_order_id' => (string)$info['purchase_order_id'],
                    'remark'            => (string)$info['remark'],
                    'customers_money'   => $first ? $customersMoney : null,
                    'real_money'        => $first ? $realMoney : null,
                    'total_amount'      => $first ? $totalAmount : null,
                    'express_no'        => $first ? $expressNo : '',
                    'out_status'        => $outStatus,
                    'recon_status'      => $d['recon_status'],
                    'recon_code'        => $d['recon_code'],
                    'invoice_status'    => $invoiceStatus,
                    'pay_status'        => $payStatus,
                    'recon_id'          => $d['recon_id'],
                    'is_first'          => $first,
                ];
                $rows[] = $row;
                $first = false;
            }
        }
        return $rows;
    }

    /**
     * 把工作台平铺行按单据分组：单据级字段放单据头，商品行放 info_list
     *
     * @param array $rows buildItemRows() 的结果
     * @return array 单据列表（保持原单据顺序）
     */
    protected function groupItemDocs($rows)
    {
        $docs = [];
        foreach ($rows as $row) {
            $key = $row['doc_key'];
            if (!isset($docs[$key])) {
                $docs[$key] = [
                    'doc_key'         => $key,
                    'doc_type'        => $row['doc_type'],
                    'doc_id'          => $row['doc_id'],
                    'doc_code'        => $row['doc_code'],
                    'doc_time'        => $row['doc_time'],
                    'doc_time_text'   => $row['doc_time_text'],
                    'customers_id'    => $row['customers_id'],
                    'customer_name'   => $row['customer_name'],
                    'sale_user_name'  => $row['sale_user_name'],
                    'customers_money' => (float)$row['customers_money'],
                    'real_money'      => (float)$row['real_money'],
                    'total_amount'    => (float)$row['total_amount'],
                    'express_no'      => (string)$row['express_no'],
                    'out_status'      => $row['out_status'],
                    'recon_status'    => $row['recon_status'],
                    'recon_code'      => $row['recon_code'],
                    'invoice_status'  => $row['invoice_status'],
                    'pay_status'      => $row['pay_status'],
                    'recon_id'        => $row['recon_id'],
                    'purchase_order_id' => (string)$row['purchase_order_id'],
                    'info_list'       => [],
                    'info_count'      => 0,
                ];
            }
            // 孤儿明细只保留单据行，不产生商品行
            if ((string)$row['goods_code'] === '' && (string)$row['goods_name'] === '') {
                continue;
            }
            $docs[$key]['info_list'][] = [
                'goods_code'        => $row['goods_code'],
                'goods_name'        => $row['goods_name'],
                'specification'     => isset($row['specification']) ? $row['specification'] : '',
                'vendor_name'       => isset($row['vendor_name']) ? $row['vendor_name'] : '',
                'packaging'         => isset($row['packaging']) ? $row['packaging'] : '',
                'attr_name'         => isset($row['attr_name']) ? $row['attr_name'] : '',
                'unit_name'         => $row['unit_name'],
                'number'            => (float)$row['number'],
                'unit_price'        => (float)$row['unit_price'],
                'tax_included'      => (float)$row['tax_included'],
                'tax_rate'          => (float)$row['tax_rate'],
                'line_amount'       => (float)$row['line_amount'],
                'purchase_order_id' => (string)$row['purchase_order_id'],
                'remark'            => (string)$row['remark'],
            ];
        }
        foreach ($docs as $key => $doc) {
            $docs[$key]['info_count'] = count($doc['info_list']);
        }
        return array_values($docs);
    }

    /**
     * 校验并可返回单据
     *
     * @param array $keys ['1:12','2:34']
     * @param int   $reconId 编辑时允许本单已占用的单据
     */
    protected function checkDocuments($keys, $reconId = 0)
    {
        $salesIds = [];
        $returnIds = [];
        foreach ($keys as $key) {
            list($type, $id) = explode(':', $key);
            if ($type == '1') {
                $salesIds[] = (int)$id;
            } else {
                $returnIds[] = (int)$id;
            }
        }
        $result = [];
        $checkout = new \addons\kuerp\library\Checkout();
        if ($salesIds) {
            $rows = Db::name('kuerp_goods_sales')->where(['id' => ['in', $salesIds], 'examine_status' => '1'])->select();
            foreach ($rows as $row) {
                if ($row['recon_id'] != 0 && $row['recon_id'] != $reconId) {
                    continue;
                }
                if ($checkout->checkOutTime($row['purchase_time']) !== true) {
                    continue;
                }
                $result['1:' . $row['id']] = $row;
            }
        }
        if ($returnIds) {
            $rows = Db::name('kuerp_goods_sales_exchange')->where(['id' => ['in', $returnIds], 'examine_status' => '1'])->select();
            foreach ($rows as $row) {
                if ($row['recon_id'] != 0 && $row['recon_id'] != $reconId) {
                    continue;
                }
                if ($checkout->checkOutTime($row['purchase_time']) !== true) {
                    continue;
                }
                $result['2:' . $row['id']] = $row;
            }
        }
        return $result;
    }

    /**
     * 生成快照（重建）
     */
    protected function buildSnapshot($reconId, $keys)
    {
        Db::name('kuerp_sales_recon_info')->where(['recon_id' => $reconId])->delete();
        Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $reconId])->delete();

        $docs = $this->checkDocuments($keys, $reconId);
        // 保留已锁定在本单的单据（编辑时源单仍存在即可）
        $order = [];
        foreach ($docs as $key => $doc) {
            $order[] = ['key' => $key, 'doc' => $doc];
        }
        usort($order, function ($a, $b) {
            $da = $a['doc'];
            $db = $b['doc'];
            if ($da['purchase_time'] == $db['purchase_time']) {
                return strcmp($da['purchase_code'], $db['purchase_code']);
            }
            return $da['purchase_time'] < $db['purchase_time'] ? -1 : 1;
        });

        $salesCodesForMap = [];
        $expressDocs = [];
        foreach ($order as $item) {
            if (strpos($item['key'], '1:') === 0) {
                $salesCodesForMap[] = $item['doc']['purchase_code'];
                $expressDocs[] = $item['doc'];
            }
        }
        $outStatusMap = $this->outboundStatusMap($salesCodesForMap);
        $expressMap = $this->expressNoMap($expressDocs);

        $salesCount = 0;
        $returnCount = 0;
        $totalNumber = 0;
        $totalMoney = 0;
        $totalCustomersMoney = 0;
        $sort = 0;
        foreach ($order as $item) {
            $key = $item['key'];
            $doc = $item['doc'];
            $type = strpos($key, '1:') === 0 ? '1' : '2';
            $sign = $type == '2' ? -1 : 1;
            if ($type == '1') {
                $salesCount++;
                $outStatus = isset($outStatusMap[$doc['purchase_code']]) ? $outStatusMap[$doc['purchase_code']] : 0;
                $payMoney = (float)$doc['pay_money'];
                $expressNo = isset($expressMap[$doc['purchase_code']]) ? $expressMap[$doc['purchase_code']] : '';
                $infos = Db::name('kuerp_goods_sales_info')->where(['purchase_code' => $doc['purchase_code']])->order('id asc')->select();
            } else {
                $returnCount++;
                $outStatus = null;
                $payMoney = (float)$doc['retreat_money'];
                $expressNo = '';
                $infos = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code' => $doc['purchase_code']])->order('id asc')->select();
            }

            $customersMoney = $sign * (float)$doc['customers_money'];
            $realMoney = $sign * (float)$doc['real_money'];
            $totalAmount = $realMoney + $customersMoney;

            $reconDocId = Db::name('kuerp_sales_recon_doc')->insertGetId([
                'recon_id'        => $reconId,
                'doc_type'        => $type,
                'doc_id'          => $doc['id'],
                'doc_code'        => $doc['purchase_code'],
                'doc_time'        => $doc['purchase_time'],
                'customers_money' => $customersMoney,
                'real_money'      => $realMoney,
                'total_amount'    => $totalAmount,
                'express_no'      => $expressNo,
                'out_status'      => $outStatus,
                'pay_money'       => $sign * $payMoney,
                'sort'            => $sort++,
                'createtime'      => time(),
            ]);

            $lineAmounts = $this->allocateDiscount($infos, (float)$doc['preferential_money']);
            $goodsCodes = array_column($infos, 'goods_code');
            $goodsMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('goods_name,goods_specification_id,vendor_name,packaging', 'goods_code') : [];
            $unitIds = array_values(array_filter(array_column($infos, 'goods_unit')));
            $unitMap = $unitIds ? Db::name('kuerp_unit')->where(['id' => ['in', $unitIds]])->column('name', 'id') : [];
            $attrIds = array_values(array_filter(array_column($infos, 'goods_attr')));
            $attrMap = $attrIds ? Db::name('kuerp_goods_attr')->where(['id' => ['in', $attrIds]])->column('attr_code', 'id') : [];

            foreach ($infos as $info) {
                $goods = isset($goodsMap[$info['goods_code']]) ? $goodsMap[$info['goods_code']] : [];
                // 退货单明细的 number 是「原销货数量」，实际退货数量在 retreat_number（金额也按后者计算）
                $number = $sign * (float)($type == '2' ? $info['retreat_number'] : $info['number']);
                $totalNumber += $number;
                Db::name('kuerp_sales_recon_info')->insert([
                    'recon_id'          => $reconId,
                    'recon_doc_id'      => $reconDocId,
                    'doc_type'          => $type,
                    'doc_id'            => $doc['id'],
                    'purchase_code'     => $doc['purchase_code'],
                    'goods_code'        => $info['goods_code'],
                    'goods_name'        => isset($goods['goods_name']) ? $goods['goods_name'] : '',
                    'specification'     => isset($goods['goods_specification_id']) ? $goods['goods_specification_id'] : '',
                    'vendor_name'       => isset($goods['vendor_name']) ? $goods['vendor_name'] : '',
                    'packaging'         => isset($goods['packaging']) ? $goods['packaging'] : '',
                    'goods_attr'        => (int)$info['goods_attr'],
                    'attr_name'         => isset($attrMap[$info['goods_attr']]) ? $attrMap[$info['goods_attr']] : '',
                    'unit_id'           => (int)$info['goods_unit'],
                    'unit_name'         => isset($unitMap[$info['goods_unit']]) ? $unitMap[$info['goods_unit']] : '',
                    'number'            => $number,
                    'basic_nmber'       => $sign * (float)$info['basic_nmber'],
                    'unit_price'        => (float)$info['unit_price'],
                    'tax_included'      => (float)$info['tax_included'],
                    'tax_rate'          => (float)$info['tax_rate'],
                    'tax_money'         => $sign * (float)$info['tax_money'],
                    'all_money'         => $sign * (float)$info['all_money'],
                    'money_tax_price'   => $sign * (float)$info['money_tax_price'],
                    'line_amount'       => $sign * (isset($lineAmounts[$info['id']]) ? $lineAmounts[$info['id']] : 0),
                    'purchase_order_id' => (string)$info['purchase_order_id'],
                    'remark'            => (string)$info['remark'],
                    'createtime'        => time(),
                ]);
            }

            $totalMoney += $realMoney;
            $totalCustomersMoney += $customersMoney;
        }

        Db::name('kuerp_sales_recon')->where(['id' => $reconId])->update([
            'sales_count'           => $salesCount,
            'return_count'          => $returnCount,
            'total_number'          => round($totalNumber, 4),
            'total_money'           => round($totalMoney, 4),
            'total_customers_money' => round($totalCustomersMoney, 4),
            'total_amount'          => round($totalMoney + $totalCustomersMoney, 4),
            'updatetime'            => time(),
        ]);
        $this->refreshInvoiceStatus($reconId);
    }

    /**
     * 回写单据对账标记
     */
    protected function writebackDocuments($keys, $reconId, $reconCode, $reconStatus)
    {
        foreach ($keys as $key) {
            list($type, $id) = explode(':', $key);
            $this->writebackOne($type, (int)$id, $reconId, $reconCode, $reconStatus);
        }
    }

    protected function writebackOne($type, $id, $reconId, $reconCode, $reconStatus)
    {
        $table = $type == '1' ? 'kuerp_goods_sales' : 'kuerp_goods_sales_exchange';
        Db::name($table)->where(['id' => (int)$id])->update([
            'recon_id'     => (int)$reconId,
            'recon_code'   => $reconCode,
            'recon_status' => $reconStatus,
        ]);
    }

    /**
     * 加载快照（单据 + 商品行）
     *
     * @return array [docs, groups]  groups: [{doc, items}]
     */
    protected function loadSnapshot($reconId)
    {
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $reconId])->order('sort asc,id asc')->select();
        $infos = Db::name('kuerp_sales_recon_info')->where(['recon_id' => $reconId])->order('recon_doc_id asc,id asc')->select();
        $infoMap = [];
        foreach ($infos as $info) {
            $infoMap[$info['recon_doc_id']][] = $info;
        }
        $groups = [];
        foreach ($docs as $doc) {
            $groups[] = [
                'doc'   => $doc,
                'items' => isset($infoMap[$doc['id']]) ? $infoMap[$doc['id']] : [],
            ];
        }
        return [$docs, $groups];
    }

    /**
     * 优惠按行分摊（末行吃分差）
     */
    protected function allocateDiscount($infos, $preferentialMoney)
    {
        $result = [];
        $count = count($infos);
        if ($count === 0) {
            return $result;
        }
        $total = 0;
        foreach ($infos as $info) {
            $total += (float)$info['money_tax_price'];
        }
        $allocated = 0;
        $i = 0;
        foreach ($infos as $info) {
            $mtp = (float)$info['money_tax_price'];
            if ($i === $count - 1) {
                $discount = round($preferentialMoney - $allocated, 4);
            } elseif ($total != 0) {
                $discount = round($preferentialMoney * $mtp / $total, 4);
            } else {
                $discount = 0;
            }
            $allocated += $discount;
            $result[$info['id']] = round($mtp - $discount, 4);
            $i++;
        }
        return $result;
    }

    /**
     * 出库状态（按销货单汇总 out_number / number）
     */
    protected function outboundStatusMap($codes)
    {
        if (empty($codes)) {
            return [];
        }
        $rows = Db::name('kuerp_goods_sales_info')
            ->where(['purchase_code' => ['in', $codes]])
            ->field('purchase_code,SUM(number) AS total_number,SUM(out_number) AS total_out')
            ->group('purchase_code')
            ->select();
        $map = [];
        foreach ($rows as $row) {
            $total = (float)$row['total_number'];
            $out = (float)$row['total_out'];
            if ($out <= 0) {
                $map[$row['purchase_code']] = 0;
            } elseif ($total > 0 && $out >= $total) {
                $map[$row['purchase_code']] = 2;
            } else {
                $map[$row['purchase_code']] = 1;
            }
        }
        return $map;
    }

    /**
     * 开票状态（按销货单号取开票台账）
     */
    protected function invoiceStatusMap($codes)
    {
        if (empty($codes)) {
            return [];
        }
        $rows = Db::name('kuerp_sales_invoicing')->where(['bill_code' => ['in', $codes]])->field('bill_code,invoice_status')->select();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['bill_code']] = (string)$row['invoice_status'];
        }
        return $map;
    }

    /**
     * 物流单号（销货单 -> 关联销货订单 -> 最近一次发货）
     */
    protected function expressNoMap($salesDocs)
    {
        if (empty($salesDocs)) {
            return [];
        }
        $orderCodes = array_values(array_filter(array_column($salesDocs, 'purchase_order_id')));
        if (empty($orderCodes)) {
            return [];
        }
        $orderMap = Db::name('kuerp_goods_sales_order')->where(['purchase_code' => ['in', $orderCodes]])->column('id,purchase_code', 'purchase_code');
        $orderIds = array_values(array_filter(array_column($orderMap, 'id')));
        if (empty($orderIds)) {
            return [];
        }
        $deliveries = Db::name('kuerp_goods_sales_order_delivery')->where(['order_id' => ['in', $orderIds]])->order('id desc')->select();
        $deliveryMap = [];
        foreach ($deliveries as $delivery) {
            if (!isset($deliveryMap[$delivery['order_id']])) {
                $deliveryMap[$delivery['order_id']] = $delivery['express_no'];
            }
        }
        $map = [];
        foreach ($salesDocs as $doc) {
            $orderCode = $doc['purchase_order_id'];
            if ($orderCode && isset($orderMap[$orderCode])) {
                $orderId = $orderMap[$orderCode]['id'];
                if (isset($deliveryMap[$orderId]) && $deliveryMap[$orderId]) {
                    $map[$doc['purchase_code']] = $deliveryMap[$orderId];
                }
            }
        }
        return $map;
    }

    /**
     * 计算收款净额与状态
     */
    protected function calcPay($reconId)
    {
        // 实现在模型里：收款单审核/反审核时需要跨模块调用同一段口径
        return \app\admin\model\kuerp\SalesRecon::recalcPay($reconId);
    }

    /**
     * 批量计算收款状态（列表用）
     */
    protected function payStatusMap($reconIds)
    {
        if (empty($reconIds)) {
            return [];
        }
        $map = [];
        foreach ($reconIds as $reconId) {
            $map[$reconId] = $this->calcPay($reconId);
        }
        return $map;
    }

    /**
     * 重算开票状态
     */
    /**
     * 应开票金额 = 总金额 total_amount（销货合计 + 客户承担费用）+ 对账差异 diff_money
     *
     * 与应收口径（SalesReconModel::recalcPay 的 total_amount + diff_money）保持一致：
     * 差异正数=客户多付（开票金额增加），负数=客户少付（折让/抹零，开票金额扣减）。
     *
     * @param array $recon kuerp_sales_recon 行
     * @return float
     */
    protected function invoiceBase($recon)
    {
        return round((float)$recon['total_amount'] + (float)$recon['diff_money'], 4);
    }

    /**
     * 按发票重算对账单的开票金额/状态与收款到期日
     */
    protected function refreshInvoiceStatus($reconId)
    {
        $recon = Db::name('kuerp_sales_recon')->where(['id' => $reconId])->find();
        if (!$recon) {
            return;
        }
        $amount = (float)Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $reconId])->sum('invoice_amount');
        // 开票基数 = 总金额（含客户承担费用）+ 对账差异
        $should = $this->invoiceBase($recon);
        if ($amount <= 0) {
            $status = '0';
        } elseif ($should > 0 && $amount + 0.0001 >= $should) {
            $status = '2';
        } else {
            $status = '1';
        }
        Db::name('kuerp_sales_recon')->where(['id' => $reconId])->update([
            'invoice_amount' => round($amount, 4),
            'invoice_status' => $status,
            // 到期日派生：取所含发票里最早的到期日（部分开票时也按最早到期的先提醒）
            'due_date'       => $this->syncReconDueDate($reconId),
        ]);
    }

    /**
     * 按发票重算对账单头部的收款到期日（最早到期日），无发票/无账期时置 NULL
     *
     * @param int $reconId
     * @return int|null
     */
    protected function syncReconDueDate($reconId)
    {
        $dueDate = Db::name('kuerp_sales_recon_invoice')
            ->where(['recon_id' => $reconId])
            ->where('due_date', '>', 0)
            ->min('due_date');
        return $dueDate ? (int)$dueDate : null;
    }

    /**
     * 开票台账全量重算（按单据日期 + 单号 FIFO 分摊）
     */
    protected function syncInvoiceLedger($reconId)
    {
        $recon = Db::name('kuerp_sales_recon')->where(['id' => $reconId])->find();
        if (!$recon) {
            return;
        }
        $total = (float)Db::name('kuerp_sales_recon_invoice')->where(['recon_id' => $reconId])->sum('invoice_amount');
        $docs = Db::name('kuerp_sales_recon_doc')
            ->where(['recon_id' => $reconId, 'doc_type' => '1'])
            ->order('doc_time asc,doc_code asc')
            ->select();
        $remaining = round($total, 4);
        foreach ($docs as $doc) {
            $sales = Db::name('kuerp_goods_sales')->where(['purchase_code' => $doc['doc_code']])->find();
            if (!$sales) {
                continue;
            }
            $ledger = Db::name('kuerp_sales_invoicing')->where(['bill_code' => $doc['doc_code']])->find();
            // 已在开票管理中被单独开过票的，不覆盖
            if ($ledger && $ledger['invoice_status'] == '1' && (int)$ledger['recon_id'] === 0) {
                continue;
            }
            // 台账按单据自身可开票额（real_money + 该单对账差异 diff_money）分摊；
            // 对账单多开出的客户承担费用部分不落在单据台账上
            $should = max(0, round((float)$sales['real_money'] + (float)$doc['diff_money'], 4));
            $amount = min($should, max($remaining, 0));
            $remaining = round($remaining - $amount, 4);
            if (!$ledger) {
                // 历史单据可能没有开票台账行，补建一条，保证开票管理页可见
                Db::name('kuerp_sales_invoicing')->insert([
                    'admin_id'       => isset(Session::get('admin')['id']) ? Session::get('admin')['id'] : 0,
                    'customers_id'   => $sales['customers_id'],
                    'status'         => $sales['pay_status'],
                    'bill_time'      => $sales['purchase_time'],
                    'bill_code'      => $sales['purchase_code'],
                    'bill_codes'     => $sales['purchase_code'],
                    'money'          => $sales['real_money'],
                    'tax'            => $sales['tax_money'],
                    'real_money'     => $sales['real_money'],
                    'customers_money'=> $sales['customers_money'],
                    'pay_money'      => round($amount, 4),
                    'give_money'     => round($should - $amount, 4),
                    'invoice_status' => ($should - $amount) <= 0.0001 ? '1' : '0',
                    'recon_id'       => $reconId,
                    'createtime'     => time(),
                ]);
                continue;
            }
            Db::name('kuerp_sales_invoicing')->where(['id' => $ledger['id']])->update([
                'recon_id'       => $reconId,
                'pay_money'      => round($amount, 4),
                'give_money'     => round($should - $amount, 4),
                'invoice_status' => ($should - $amount) <= 0.0001 ? '1' : '0',
            ]);
        }
    }

    /**
     * 该客户所有仍有未付款的对账单（按月份归并）
     *
     * 版式对齐《对账模板.xls》：每月一行「YYYY年M月已对账已开票（或者未开票）未付款合计」。
     * 本月这一行**包含当前这张对账单**，因此各行金额相加 = 「总未付款金额合计」。
     * 月份带年份，避免跨年时同名月份合并。
     *
     * @param int $customersId 客户ID
     * @param int $currentId   当前对账单ID（即使尚未二审也计入，保证与总未付款一致）
     * @return array ['list'=>['2026年10月'=>金额, ...], 'total'=>金额]
     */
    protected function pastUnpaid($customersId, $currentId = 0)
    {
        // 按「年-月」归并（同一月份多张对账单合计）
        $buckets = [];
        $total = 0;
        $addBucket = function ($row, $unpaid) use (&$buckets, &$total) {
            $monthTime = $row['period_end'] ?: $row['createtime'];
            $key = $monthTime ? date('Y-m', $monthTime) : 'past';
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'label'  => $monthTime ? date('Y', $monthTime) . '年' . date('n', $monthTime) . '月' : '往期',
                    'amount' => 0,
                ];
            }
            $buckets[$key]['amount'] = round($buckets[$key]['amount'] + $unpaid, 4);
            $total = round($total + $unpaid, 4);
        };

        $rows = Db::name('kuerp_sales_recon')
            ->where(['customers_id' => $customersId, 'recon_status' => '2'])
            ->order('period_start asc,id asc')
            ->select();
        foreach ($rows as $row) {
            if ($currentId && (int)$row['id'] === (int)$currentId) {
                continue; // 当前单在下面单独补（可能还没二审）
            }
            $pay = $this->calcPay($row['id']);
            $unpaid = round((float)$row['total_amount'] + (float)$row['diff_money'] - $pay['pay_money'], 4);
            if ($unpaid <= 0) {
                continue;
            }
            $addBucket($row, $unpaid);
        }
        if ($currentId) {
            $current = Db::name('kuerp_sales_recon')->where(['id' => $currentId])->find();
            if ($current) {
                $pay = $this->calcPay($current['id']);
                $unpaid = round((float)$current['total_amount'] + (float)$current['diff_money'] - $pay['pay_money'], 4);
                if ($unpaid > 0) {
                    $addBucket($current, $unpaid);
                }
            }
        }

        ksort($buckets);
        $list = [];
        foreach ($buckets as $bucket) {
            $list[$bucket['label']] = $bucket['amount'];
        }
        return ['list' => $list, 'total' => round($total, 4)];
    }

    /**
     * 校验单据是否仍可对账（一审前）
     */
    protected function assertDocumentsValid($docs)
    {
        $checkout = new \addons\kuerp\library\Checkout();
        foreach ($docs as $doc) {
            $table = $doc['doc_type'] == '1' ? 'kuerp_goods_sales' : 'kuerp_goods_sales_exchange';
            $row = Db::name($table)->where(['id' => $doc['doc_id']])->find();
            if (!$row || $row['examine_status'] != '1') {
                throw new Exception('单据' . $doc['doc_code'] . '已不存在或未审核，请先编辑对账单');
            }
            if ($checkout->checkOutTime($row['purchase_time']) !== true) {
                throw new Exception('单据' . $doc['doc_code'] . '已结账，不能对账');
            }
        }
    }

    /**
     * 对账单操作日志
     */
    protected function writeLog($reconId, $action, $note = '')
    {
        $admin = Session::get('admin');
        Db::name('kuerp_sales_recon_log')->insert([
            'recon_id'   => (int)$reconId,
            'action'     => $action,
            'note'       => mb_substr((string)$note, 0, 500),
            'admin_id'   => isset($admin['id']) ? (int)$admin['id'] : 0,
            'createtime' => time(),
        ]);
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

    /**
     * 解析单据键：支持 "1:12,2:34" 或 JSON 数组
     */
    protected function parseDocKeys($raw)
    {
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $raw = trim((string)$raw);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            $list = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        $keys = [];
        foreach ($list as $item) {
            $item = trim((string)$item);
            if (strpos($item, ':') === false) {
                continue;
            }
            list($type, $id) = explode(':', $item);
            $type = (string)$type;
            $id = (int)$id;
            if (!in_array($type, ['1', '2']) || $id <= 0) {
                continue;
            }
            $keys[] = $type . ':' . $id;
        }
        return array_values(array_unique($keys));
    }

    protected function toIdArray($ids)
    {
        if (is_array($ids)) {
            $arr = $ids;
        } else {
            $arr = explode(',', (string)$ids);
        }
        return array_values(array_filter(array_map('intval', $arr)));
    }

    protected function customerOptions()
    {
        return Db::name('kuerp_customers')->where(['status' => '1'])->field('id,customer_code,customer_name')->order('customer_code asc')->select();
    }

    protected function staffOptions()
    {
        return Db::name('kuerp_staff_member')->where(['status' => '1'])->field('id,name,men_code')->order('men_code asc')->select();
    }

    protected function companyName()
    {
        $name = Db::name('kuerp_sets')->where(['name' => 'company_name'])->value('value');
        return $name ?: '对账单';
    }

    protected function decimal($name, $default = 2)
    {
        $value = Db::name('kuerp_sets')->where(['name' => $name])->value('value');
        return $value === null || $value === '' ? $default : (int)$value;
    }

    protected function adminNameMap($ids)
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', (array)$ids))));
        if (empty($ids)) {
            return [];
        }
        return Db::name('admin')->where(['id' => ['in', $ids]])->column('nickname', 'id');
    }

    protected function enclosureOne($id)
    {
        $row = \app\admin\model\kuerp\Enclosure::get($id);
        if (!$row) {
            return null;
        }
        return \app\admin\model\kuerp\Enclosure::fillFileInfo($row);
    }
}
