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
     * 列表（展平：一个订单下的每个商品为一行，order_no 合并）
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $total = $this->model->where($where)->order($sort, $order)->count();
        $orders = $this->model->with('goods')->where($where)->order($sort, $order)->limit($offset, $limit)->select();

        // 展平：每个商品一行，order 级字段重复用于 merge
        $rows = [];
        foreach ($orders as $ord) {
            $orderData = $ord->toArray();
            $goodsList = $orderData['goods'] ?? [];
            unset($orderData['goods'], $orderData['delivery']);

            if (empty($goodsList)) {
                $orderData['goods_code']   = '';
                $orderData['goods_name']   = '';
                $orderData['goods_image']  = '';
                $orderData['unit_name']    = '';
                $orderData['attr_name']    = '';
                $orderData['unit_price']   = '';
                $orderData['quantity']     = '';
                $rows[] = $orderData;
            } else {
                foreach ($goodsList as $g) {
                    $row = $orderData;
                    $row['goods_code']   = $g['goods_code']   ?? '';
                    $row['goods_name']   = $g['goods_name']   ?? '';
                    $row['goods_image']  = $g['goods_image']  ?? '';
                    $row['unit_name']    = $g['unit_name']    ?? '';
                    $row['attr_name']    = $g['attr_name']    ?? '';
                    $row['unit_price']   = $g['unit_price']   ?? 0;
                    $row['quantity']     = $g['quantity']     ?? 0;
                    $rows[] = $row;
                }
            }
        }

        // 批量查询关联销货单的审核/对单状态
        $salesCodes = [];
        foreach ($rows as $r) {
            if (!empty($r['sales_code'])) {
                $salesCodes[] = $r['sales_code'];
            }
        }
        $salesMap = [];
        if (!empty($salesCodes)) {
            $salesList = Db::name('kuerp_goods_sales')
                ->where('purchase_code', 'in', array_unique($salesCodes))
                ->column('examine_status,check_status', 'purchase_code');
            $salesMap = $salesList ?: [];
        }

        foreach ($rows as &$r) {
            $sc = $r['sales_code'] ?? '';
            if (!empty($sc) && isset($salesMap[$sc])) {
                $r['sales_examine_status'] = $salesMap[$sc]['examine_status'];
                $r['sales_check_status']   = $salesMap[$sc]['check_status'];
            } else {
                $r['sales_examine_status'] = '';
                $r['sales_check_status']   = '';
            }
        }
        unset($r);

        return json(['total' => $total, 'rows' => $rows]);
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
     * 确认审核订单（跳转到销货单新增页面）
     */
    public function confirm()
    {
        $id = $this->request->param('ids/d');
        if (!$id) {
            $this->error(__('1参数错误'));
        }

        $order = $this->model->find($id);
        if (!$order) {
            $this->error(__('订单不存在'));
        }
        if ($order->status !== 'pending_confirm') {
            $this->error(__('当前状态不可确认'));
        }

        // 标记为已确认，记录操作人
        $order->status       = 'confirmed';
        $order->admin_id     = $this->auth->id;
        $order->confirm_time = time();
        $order->updatetime   = time();
        $order->save();
        $this->success(__('审核成功'));
    }
    /** 
     * 销货单
    **/
    public function sales()
    {
        $id = $this->request->param('ids/d');
        if (!$id) {
            $this->error(__('参数错误'));
        }
        $this->success(__('审核成功'));
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
        if ($order->status !== 'paid') {
            $this->error(__('当前状态不可发货'));
        }

        // 检查关联销货单的审核/对单状态
        if ($order->sales_code) {
            $sales = Db::name('kuerp_goods_sales')
                ->where('purchase_code', $order->sales_code)
                ->field('examine_status,check_status')
                ->find();
            if (!$sales) {
                $this->error(__('Sales_not_found'));
            }
            if ($sales['examine_status'] != 1) {
                $this->error(__('Sales_not_examined'));
            }
            if ($sales['check_status'] != 1) {
                $this->error(__('Sales_not_checked'));
            }
        } else {
            $this->error(__('No_sales_linked'));
        }

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $deliveryType = $params['delivery_type'] ?? 'logistics';
            $remark = $params['remark'] ?? '';

            Db::startTrans();
            try {
                if ($deliveryType == 'logistics') {
                    $expressItems = $params['express'] ?? [];
                    if (is_string($expressItems)) {
                        $expressItems = json_decode($expressItems, true) ?: [];
                    }
                    if (empty($expressItems) || !is_array($expressItems)) {
                        $this->error(__('请填写快递信息'));
                    }
                    $hasData = false;
                    foreach ($expressItems as $item) {
                        $code = trim($item['code'] ?? '');
                        $no   = trim($item['no'] ?? '');
                        if (empty($code) || empty($no)) {
                            continue;
                        }
                        $expressName = Db::name('kuerp_data_express')
                            ->where('code', $code)
                            ->value('name');
                        $deliveryData = [
                            'order_id'       => $id,
                            'delivery_no'    => 'DN' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
                            'delivery_type'  => $deliveryType,
                            'express_company'=> $expressName ?: $code,
                            'express_code'   => $code,
                            'express_no'     => $no,
                            'status'         => 'shipped',
                            'shipped_time'   => time(),
                            'admin_id'       => $this->auth->id,
                            'remark'         => $remark,
                            'createdtime'    => time(),
                            'updatetime'     => time(),
                        ];
                        Db::name('kuerp_mini_delivery')->insert($deliveryData);
                        $hasData = true;
                    }
                    if (!$hasData) {
                        $this->error(__('请填写快递信息'));
                    }
                } else {
                    Db::name('kuerp_mini_delivery')->insert([
                        'order_id'      => $id,
                        'delivery_no'   => 'DN' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
                        'delivery_type' => $deliveryType,
                        'status'        => 'shipped',
                        'shipped_time'  => time(),
                        'admin_id'      => $this->auth->id,
                        'remark'        => $remark,
                        'createdtime'   => time(),
                        'updatetime'    => time(),
                    ]);
                }

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

        $expressList = Db::name('kuerp_data_express')
            ->order('weigh', 'desc')
            ->field('code,name')
            ->select();
        $this->view->assign('expressList', $expressList);
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
