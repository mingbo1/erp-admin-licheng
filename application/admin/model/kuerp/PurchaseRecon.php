<?php

namespace app\admin\model\kuerp;

use think\Db;
use think\Model;

/**
 * 采购对账单
 */
class PurchaseRecon extends Model
{

    // 表名
    protected $name = 'kuerp_purchase_recon';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'status_text',
        'invoice_status_text',
        'pay_status_text',
    ];

    public function getStatusList()
    {
        return [
            '0' => '待对账',
            '1' => '待财务审核',
            '2' => '待二审',
            '3' => '已对账',
            '4' => '已驳回',
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $list = $this->getStatusList();
        return isset($list[$data['status']]) ? $list[$data['status']] : '';
    }

    public function getInvoiceStatusList()
    {
        return ['0' => '未开票', '1' => '部分开票', '2' => '已开票'];
    }

    public function getInvoiceStatusTextAttr($value, $data)
    {
        $list = $this->getInvoiceStatusList();
        return isset($list[$data['invoice_status']]) ? $list[$data['invoice_status']] : '';
    }

    public function getPayStatusList()
    {
        return ['0' => '未付款', '1' => '付款申请中', '2' => '已付款'];
    }

    public function getPayStatusTextAttr($value, $data)
    {
        $list = $this->getPayStatusList();
        return isset($list[$data['pay_status']]) ? $list[$data['pay_status']] : '';
    }

    /**
     * 对账单关联的原始单据资料（财务审核 / 付款申请查看用）
     *
     * 返回结构（按对账单ID索引）：
     *   recon_code / total_money
     *   files      对账单附件（enclosure_log type=8）
     *   contract   供应商合同（kuerp_suppliers.contract）
     *   orders     关联采购订单的原始单据（enclosure_log type=1），[{purchase_code, files}]
     *   storages   每张采购单的历次入库记录及入库原始单据（type=7），[{purchase_code, logs:[{time,number,detail_text,admin_name,files}]}]
     *   invoices   发票（type=9）+ 账期，[{invoice_no, invoice_amount, term_days, due_date_text, file}]
     *
     * 全部为业务人员上传的原始单据，不含系统生成的打印件。
     *
     * @param array|int $reconIds
     * @return array
     */
    public static function materials($reconIds)
    {
        $reconIds = array_values(array_filter(array_unique(array_map('intval', (array)$reconIds))));
        if (empty($reconIds)) {
            return [];
        }
        $recons = Db::name('kuerp_purchase_recon')->where(['id' => ['in', $reconIds]])->select();
        if (empty($recons)) {
            return [];
        }
        $infos = Db::name('kuerp_purchase_recon_info')->where(['recon_id' => ['in', $reconIds]])->order('id asc')->select();
        $infoMap = [];
        $purchaseIds = [];
        $exchangeIds = [];
        foreach ($infos as $info) {
            $infoMap[$info['recon_id']][] = $info;
            if (isset($info['doc_type']) && $info['doc_type'] == '2') {
                $exchangeIds[] = $info['purchase_id'];
            } else {
                $purchaseIds[] = $info['purchase_id'];
            }
        }
        $purchaseIds = array_values(array_filter(array_unique($purchaseIds)));
        $exchangeIds = array_values(array_filter(array_unique($exchangeIds)));

        // 购货退货单（冲减行）
        $exchangeMap = [];
        if (!empty($exchangeIds)) {
            $exchangeRows = Db::name('kuerp_goods_exchange')->where(['id' => ['in', $exchangeIds]])->field('id,purchase_code,purchase_time,real_money')->select();
            foreach ($exchangeRows as $exchangeRow) {
                $exchangeMap[$exchangeRow['id']] = $exchangeRow;
            }
        }

        // 采购单 -> 关联采购订单编号
        $purchaseOrderMap = [];
        if (!empty($purchaseIds)) {
            $purchaseRows = Db::name('kuerp_goods_purchase')->where(['id' => ['in', $purchaseIds]])->field('id,purchase_code,purchase_order_id')->select();
            foreach ($purchaseRows as $purchaseRow) {
                $purchaseOrderMap[$purchaseRow['id']] = $purchaseRow;
            }
        }
        // 采购订单编号 -> 订单ID（取原始单据用）
        $orderCodes = [];
        foreach ($purchaseOrderMap as $purchaseRow) {
            if (!empty($purchaseRow['purchase_order_id'])) {
                $orderCodes[] = $purchaseRow['purchase_order_id'];
            }
        }
        $orderCodes = array_values(array_filter(array_unique($orderCodes)));
        $orderIdMap = [];
        if (!empty($orderCodes)) {
            $orderIdMap = Db::name('kuerp_goods_purchase_order')->where(['purchase_code' => ['in', $orderCodes]])->column('id', 'purchase_code');
        }

        // 供应商合同
        $supplierCodes = array_values(array_filter(array_unique(array_column($recons, 'supplyer_code'))));
        $contractMap = $supplierCodes ? Db::name('kuerp_suppliers')->where(['supplyer_code' => ['in', $supplierCodes]])->column('contract', 'supplyer_code') : [];

        // 入库记录
        $storageLogs = [];
        if (!empty($purchaseIds)) {
            $storageLogs = Db::name('kuerp_goods_purchase_storage_log')->where(['purchase_id' => ['in', $purchaseIds]])->order('id desc')->select();
        }
        $adminIds = array_filter(array_unique(array_column($storageLogs, 'admin_id')));
        $adminMap = $adminIds ? Db::name('admin')->where(['id' => ['in', $adminIds]])->column('nickname', 'id') : [];

        // 附件关联
        $reconEnc = Db::name('kuerp_enclosure_log')->where(['order_id' => ['in', $reconIds], 'type' => '8'])->order('id asc')->select();
        $invoiceEnc = Db::name('kuerp_enclosure_log')->where(['order_id' => ['in', $reconIds], 'type' => '9'])->order('id asc')->select();
        $orderEnc = !empty($orderIdMap) ? Db::name('kuerp_enclosure_log')->where(['order_id' => ['in', array_values($orderIdMap)], 'type' => '1'])->order('id asc')->select() : [];
        $storageEnc = !empty($storageLogs) ? Db::name('kuerp_enclosure_log')->where(['order_id' => ['in', array_column($storageLogs, 'id')], 'type' => '7'])->order('id asc')->select() : [];

        // 一次性加载原始单据文件
        $encIds = array_merge(
            array_column($reconEnc, 'enclosure_id'),
            array_column($invoiceEnc, 'enclosure_id'),
            array_column($orderEnc, 'enclosure_id'),
            array_column($storageEnc, 'enclosure_id')
        );
        $encIds = array_values(array_filter(array_unique($encIds)));
        $encMap = [];
        if (!empty($encIds)) {
            $encRows = \app\admin\model\kuerp\Enclosure::where(['id' => ['in', $encIds]])->select();
            foreach ($encRows as $encRow) {
                \app\admin\model\kuerp\Enclosure::fillFileInfo($encRow);
                $encMap[$encRow['id']] = [
                    'id'          => $encRow['id'],
                    'name'        => $encRow['name'],
                    'url'         => $encRow['url'],
                    'file_suffix' => $encRow['file_suffix'],
                    'is_image'    => $encRow['is_image'],
                ];
            }
        }

        // 发票 / 账期
        $invoiceRows = Db::name('kuerp_purchase_recon_invoice')->where(['recon_id' => ['in', $reconIds]])->order('id asc')->select();

        // 附件分组
        $groupBy = function ($rows, $key) use ($encMap) {
            $result = [];
            foreach ($rows as $row) {
                if (isset($encMap[$row['enclosure_id']])) {
                    $result[$row[$key]][] = $encMap[$row['enclosure_id']];
                }
            }
            return $result;
        };
        $reconFiles = $groupBy($reconEnc, 'order_id');
        $orderFiles = $groupBy($orderEnc, 'order_id');
        $storageFiles = $groupBy($storageEnc, 'order_id');
        $invoiceFiles = $groupBy($invoiceEnc, 'order_id');

        $result = [];
        foreach ($recons as $recon) {
            $reconId = $recon['id'];
            $contract = isset($contractMap[$recon['supplyer_code']]) ? $contractMap[$recon['supplyer_code']] : '';
            $orderList = [];
            $storageList = [];
            $returnList = [];
            foreach ((array)(isset($infoMap[$reconId]) ? $infoMap[$reconId] : []) as $info) {
                if (isset($info['doc_type']) && $info['doc_type'] == '2') {
                    $exchange = isset($exchangeMap[$info['purchase_id']]) ? $exchangeMap[$info['purchase_id']] : null;
                    $returnList[] = [
                        'doc_code'  => $info['purchase_code'],
                        'doc_time'  => $info['purchase_time'] ? date('Y-m-d', $info['purchase_time']) : '',
                        'real_money'=> $info['real_money'],
                        'number'    => $info['number'],
                        'exists'    => !empty($exchange),
                    ];
                    continue;
                }
                $purchase = isset($purchaseOrderMap[$info['purchase_id']]) ? $purchaseOrderMap[$info['purchase_id']] : null;
                $orderCode = $purchase ? $purchase['purchase_order_id'] : '';
                $fileList = [];
                if ($orderCode && isset($orderIdMap[$orderCode]) && isset($orderFiles[$orderIdMap[$orderCode]])) {
                    $fileList = $orderFiles[$orderIdMap[$orderCode]];
                }
                if ($orderCode) {
                    $orderList[] = ['purchase_code' => $orderCode, 'files' => $fileList];
                }
                $logs = [];
                foreach ($storageLogs as $log) {
                    if ($log['purchase_id'] != $info['purchase_id']) {
                        continue;
                    }
                    $details = $log['detail'] ? json_decode($log['detail'], true) : [];
                    $texts = [];
                    foreach ((array)$details as $d) {
                        $label = !empty($d['goods_name']) ? $d['goods_code'] . ' ' . $d['goods_name'] : (isset($d['goods_code']) ? $d['goods_code'] : '');
                        $texts[] = $label . ' × ' . (isset($d['number']) ? $d['number'] : 0) . (isset($d['unit_name']) ? $d['unit_name'] : '');
                    }
                    $logs[] = [
                        'createtime'   => $log['createtime'] ? date('Y-m-d H:i', $log['createtime']) : '',
                        'total_number' => $log['total_number'],
                        'detail_text'  => implode('、', $texts),
                        'admin_name'   => isset($adminMap[$log['admin_id']]) ? $adminMap[$log['admin_id']] : '',
                        'files'        => isset($storageFiles[$log['id']]) ? $storageFiles[$log['id']] : [],
                    ];
                }
                $storageList[] = ['purchase_code' => $info['purchase_code'], 'logs' => $logs];
            }

            $invoices = [];
            foreach ($invoiceRows as $invoice) {
                if ($invoice['recon_id'] != $reconId) {
                    continue;
                }
                $file = null;
                if (!empty($invoice['enclosure_id']) && isset($encMap[$invoice['enclosure_id']])) {
                    $file = $encMap[$invoice['enclosure_id']];
                }
                $invoices[] = [
                    'invoice_no'     => $invoice['invoice_no'],
                    'invoice_amount' => $invoice['invoice_amount'],
                    'term_days'      => $invoice['term_days'],
                    'due_date_text'  => $invoice['due_date'] ? date('Y-m-d', $invoice['due_date']) : '',
                    'file'           => $file,
                ];
            }

            $result[$reconId] = [
                'id'          => $reconId,
                'recon_code'  => $recon['recon_code'],
                'total_money' => $recon['total_money'],
                'files'       => isset($reconFiles[$reconId]) ? $reconFiles[$reconId] : [],
                'contract'    => $contract ? self::fileInfo($contract, '供应商合同') : null,
                'orders'      => $orderList,
                'storages'    => $storageList,
                'returns'     => $returnList,
                'invoices'    => $invoices,
            ];
        }
        return $result;
    }

    /**
     * 文件路径 -> 展示信息
     */
    protected static function fileInfo($path, $name = '')
    {
        $suffix = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return [
            'id'          => 0,
            'name'        => $name ?: pathinfo($path, PATHINFO_FILENAME),
            'url'         => cdnurl($path, true),
            'file_suffix' => $suffix,
            'is_image'    => in_array($suffix, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico']) ? 1 : 0,
        ];
    }
}
