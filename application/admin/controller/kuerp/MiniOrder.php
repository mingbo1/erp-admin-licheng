<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 订货单管理
 *
 * @icon fa fa-file-text-o
 */
class MiniOrder extends Backend
{
    protected $model = null;

    // 无需权限的方法
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\MiniOrder;
        $this->view->assign("statusList", $this->model->getStatusList());

        // 仓库列表（确认订单时选择）
        $storesList = Db::name('kuerp_stores')->where('status', 1)->field('id,name')->select();
        $this->view->assign('storesList', $storesList);
        $this->assignconfig('storesList', $storesList);
    }

    /**
     * 查看订单详情
     */
    public function detail()
    {
        $id = $this->request->param('ids/d');
        if (!$id) {
            $this->error(__('参数错误'));
        }

        $order = $this->model->with(['goods', 'delivery'])->find($id);
        if (!$order) {
            $this->error(__('订单不存在'));
        }

        // 用户信息
        $miniUser = Db::name('kuerp_mini_user')->where('user_id', $order->user_id)->find();
        $customer = Db::name('kuerp_customers')->where('id', $order->customer_id)->find();

        $this->view->assign('order', $order);
        $this->view->assign('miniUser', $miniUser);
        $this->view->assign('customer', $customer);
        return $this->view->fetch();
    }

    /**
     * 确认订单（选择仓库）
     */
    public function confirm()
    {
        $id = $this->request->param('ids/d');
        if (!$id) {
            $this->error(__('参数错误'));
        }

        $order = $this->model->with(['goods'])->find($id);
        if (!$order) {
            $this->error(__('订单不存在'));
        }
        if ($order->status !== 'pending_confirm') {
            $this->error(__('当前状态不可确认'));
        }

        if ($this->request->isPost()) {
            $storeId = $this->request->post('stores_id/d');
            if (!$storeId) {
                $this->error(__('请选择仓库'));
            }

            // 检查库存
            $insufficient = [];
            foreach ($order->goods as $item) {
                $stock = Db::name('kuerp_goods_stock')
                    ->where('goods_code', $item['goods_code'])
                    ->where('stores_id', $storeId)
                    ->value('stock');
                if ((float)$stock < (float)$item['quantity']) {
                    $insufficient[] = $item['goods_name'] . '(需' . $item['quantity'] . ', 库存' . ((float)$stock) . ')';
                }
            }
            if (!empty($insufficient)) {
                $this->error(__('库存不足: ' . implode('; ', $insufficient)));
            }

            Db::startTrans();
            try {
                $salesCode = \addons\kuerp\library\Goods::getCodes('', 11);
                if (!$salesCode) {
                    $salesCode = 'XHD' . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
                }

                // 创建销货单草稿
                $salesData = [
                    'purchase_code'    => $salesCode,
                    'customers_code'   => $order->customer_id,
                    'customer_name'    => Db::name('kuerp_customers')->where('id', $order->customer_id)->value('customer_name'),
                    'examine_status'   => 0,
                    'account'          => $order->payable_amount,
                    'all_money'        => $order->total_amount,
                    'preferential'     => $order->preferential,
                    'actual_money'     => 0,
                    'money_whole'      => $order->payable_amount,
                    'total'            => 0,
                    'discount'         => 0,
                    'discount_money'   => 0,
                    'other_money'      => 0,
                    'description'      => '订货通订单#' . $order->order_no,
                    'sales_date'       => date('Y-m-d'),
                    'stores_id'        => $storeId,
                    'salesman'         => '',
                    'admin_id'         => $this->auth->id,
                    'createtime'       => time(),
                    'updatetime'       => time(),
                ];
                Db::name('kuerp_goods_sales')->insert($salesData);

                // 销货单明细
                $salesInfoData = [];
                foreach ($order->goods as $item) {
                    $salesInfoData[] = [
                        'purchase_code' => $salesCode,
                        'goods_code'    => $item['goods_code'],
                        'goods_name'    => $item['goods_name'],
                        'basic_nmber'   => $item['quantity'],
                        'unit_price'    => $item['unit_price'],
                        'all_money'     => $item['all_money'],
                        'createtime'    => time(),
                    ];
                }
                Db::name('kuerp_goods_sales_info')->insertAll($salesInfoData);

                // 更新订单
                $order->status       = 'confirmed';
                $order->admin_id     = $this->auth->id;
                $order->confirm_time = time();
                $order->sales_code   = $salesCode;
                $order->updatetime   = time();
                $order->save();

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error(__('确认失败: ' . $e->getMessage()));
            }

            $this->success(__('确认成功'));
        }

        $this->view->assign('order', $order);
        return $this->view->fetch();
    }

    /**
     * 发货
     */
    public function ship()
    {
        $id = $this->request->param('ids/d');
        if (!$id) {
            $this->error(__('参数错误'));
        }

        $order = $this->model->find($id);
        if (!$order) {
            $this->error(__('订单不存在'));
        }
        if ($order->status !== 'confirmed') {
            $this->error(__('当前状态不可发货'));
        }

        if ($this->request->isPost()) {
            $deliveryType = $this->request->post('delivery_type', 'logistics');

            $deliveryData = [
                'order_id'      => $id,
                'delivery_no'   => 'DN' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'delivery_type' => $deliveryType,
                'status'        => 'shipped',
                'shipped_time'  => time(),
                'admin_id'      => $this->auth->id,
                'remark'        => $this->request->post('remark', ''),
                'createdtime'   => time(),
                'updatetime'    => time(),
            ];

            if ($deliveryType == 'logistics') {
                $deliveryData['express_company'] = $this->request->post('express_company', '');
                $deliveryData['express_code']    = $this->request->post('express_code', '');
                $deliveryData['express_no']      = $this->request->post('express_no', '');
            } else {
                $deliveryData['carrier_name']   = $this->request->post('carrier_name', '');
                $deliveryData['carrier_mobile'] = $this->request->post('carrier_mobile', '');
            }

            Db::startTrans();
            try {
                Db::name('kuerp_mini_delivery')->insert($deliveryData);
                $order->status     = 'shipped';
                $order->updatetime  = time();
                $order->save();
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error(__('发货失败: ' . $e->getMessage()));
            }

            $this->success(__('发货成功'));
        }

        $this->view->assign('order', $order);
        return $this->view->fetch();
    }

    /**
     * 取消订单
     */
    public function cancel()
    {
        $id = $this->request->param('ids/d');
        if (!$id) {
            $this->error(__('参数错误'));
        }

        $order = $this->model->find($id);
        if (!$order) {
            $this->error(__('订单不存在'));
        }
        if (in_array($order->status, ['completed', 'returned', 'cancelled'])) {
            $this->error(__('当前状态不可取消'));
        }

        Db::startTrans();
        try {
            if ($order->sales_code) {
                Db::name('kuerp_goods_sales')
                    ->where('purchase_code', $order->sales_code)
                    ->update(['examine_status' => 3, 'updatetime' => time()]);
            }
            $order->status     = 'cancelled';
            $order->updatetime  = time();
            $order->save();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error(__('取消失败: ' . $e->getMessage()));
        }

        $this->success(__('取消成功'));
    }

    /**
     * 删除
     */
    public function del($ids = '')
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $this->model->where($pk, 'in', $ids)->delete();
            $this->success(__('删除成功'));
        }
        $this->error(__('参数错误'));
    }
}
