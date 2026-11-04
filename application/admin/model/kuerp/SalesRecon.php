<?php

namespace app\admin\model\kuerp;

use think\Db;
use think\Model;

/**
 * 销货对账单（客户对账单）
 */
class SalesRecon extends Model
{

    // 表名
    protected $name = 'kuerp_sales_recon';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'recon_status_text',
        'invoice_status_text',
        'pay_status_text',
    ];

    public function getReconStatusList()
    {
        return ['0' => '待对账', '1' => '待二审', '2' => '已对账'];
    }

    public function getReconStatusTextAttr($value, $data)
    {
        $list = $this->getReconStatusList();
        $status = isset($data['recon_status']) ? $data['recon_status'] : '0';
        return isset($list[$status]) ? $list[$status] : '';
    }

    public function getInvoiceStatusList()
    {
        return ['0' => '未开票', '1' => '部分开票', '2' => '已开票'];
    }

    public function getInvoiceStatusTextAttr($value, $data)
    {
        $list = $this->getInvoiceStatusList();
        $status = isset($data['invoice_status']) ? $data['invoice_status'] : '0';
        return isset($list[$status]) ? $list[$status] : '';
    }

    public function getPayStatusList()
    {
        return ['0' => '未收款', '1' => '部分收款', '2' => '已收款'];
    }

    public function getPayStatusTextAttr($value, $data)
    {
        $list = $this->getPayStatusList();
        $status = isset($data['pay_status']) ? $data['pay_status'] : '0';
        return isset($list[$status]) ? $list[$status] : '';
    }

    /**
     * 业务状态文本（列表展示用，组合三个独立状态）
     */
    public static function businessStatusText($row)
    {
        $reconStatus = isset($row['recon_status']) ? (string)$row['recon_status'] : '0';
        $invoiceStatus = isset($row['invoice_status']) ? (string)$row['invoice_status'] : '0';
        $payStatus = isset($row['pay_status']) ? (string)$row['pay_status'] : '0';
        if ($reconStatus === '0') {
            return !empty($row['reject_reason']) ? '已驳回·待重提' : '待对账';
        }
        if ($reconStatus === '1') {
            return '待二审';
        }
        if ($payStatus === '2') {
            return '已完成';
        }
        if ($invoiceStatus === '0') {
            return '已对账·待开票';
        }
        if ($invoiceStatus === '1') {
            return '部分开票';
        }
        return $payStatus === '1' ? '已开票·部分收款' : '已开票·待收款';
    }

    /**
     * 重算某张对账单的已收金额与收款状态
     *
     * 口径：已收 = Σ销货单 pay_money − Σ退货单 retreat_money；
     *       应收 = total_amount + diff_money（对账差异计入应收）。
     * 结果回写 sales_recon.pay_money / pay_status，供列表、到期提醒、
     * 差异校验（已收款不能加差异）直接读取。
     *
     * @param int $reconId
     * @return array ['pay_money'=>float, 'pay_status'=>string]
     */
    public static function recalcPay($reconId)
    {
        $reconId = (int)$reconId;
        $recon = Db::name('kuerp_sales_recon')->where(['id' => $reconId])->find();
        if (!$recon) {
            return ['pay_money' => 0, 'pay_status' => '0'];
        }
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $reconId])->select();
        $salesCodes = [];
        $returnCodes = [];
        foreach ($docs as $doc) {
            if ($doc['doc_type'] == '1') {
                $salesCodes[] = $doc['doc_code'];
            } else {
                $returnCodes[] = $doc['doc_code'];
            }
        }
        $payMoney = 0;
        if ($salesCodes) {
            $payMoney += (float)Db::name('kuerp_goods_sales')
                ->where(['purchase_code' => ['in', $salesCodes]])
                ->sum('pay_money');
        }
        if ($returnCodes) {
            $payMoney -= (float)Db::name('kuerp_goods_sales_exchange')
                ->where(['purchase_code' => ['in', $returnCodes]])
                ->sum('retreat_money');
        }
        $payMoney = round($payMoney, 4);
        // 应收总额 = 原总金额 + 对账差异合计（差异正=客户多付，负=客户少付）
        $total = round((float)$recon['total_amount'] + (float)$recon['diff_money'], 4);
        if ($total <= 0) {
            $status = '2';
        } elseif ($payMoney <= 0) {
            $status = '0';
        } elseif ($payMoney >= $total) {
            $status = '2';
        } else {
            $status = '1';
        }
        Db::name('kuerp_sales_recon')->where(['id' => $reconId])->update([
            'pay_money'  => $payMoney,
            'pay_status' => $status,
        ]);
        return ['pay_money' => $payMoney, 'pay_status' => $status];
    }

    /**
     * 收款单审核/反审核后，按单据编号把所属对账单的收款状态刷新一遍
     *
     * 不刷新的话 sales_recon.pay_status 会一直是旧值（它只在打开对账列表时才重算），
     * 到期提醒会继续把「已收完」的对账单标成逾期。
     *
     * @param array|string $codes 销货单/退货单编号
     */
    public static function recalcPayByDocCodes($codes)
    {
        $codes = array_values(array_filter(array_unique((array)$codes), function ($v) {
            return $v !== '' && $v !== null;
        }));
        if (empty($codes)) {
            return;
        }
        $reconIds = Db::name('kuerp_sales_recon_doc')
            ->where(['doc_code' => ['in', $codes]])
            ->distinct(true)
            ->column('recon_id');
        foreach ($reconIds as $reconId) {
            self::recalcPay($reconId);
        }
    }
}
