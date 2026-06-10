<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 入库管理（石材采购入库）
 *
 * @icon fa fa-arrow-down
 */
class StoneIn extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['*'];
    protected $searchFields = 'in_no|supplier_name|store_name';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\kuerp\StoneInbound;

        // 仓库列表
        $storesList = Db::name('kuerp_stores')->where('status', 1)->field('id,name')->select();
        $this->view->assign('storesList', $storesList);
        $this->assignconfig('storesList', $storesList);

        // 供应商列表
        $supplierList = Db::name('kuerp_suppliers')->field('id,supplyer_code,supplyer_full_name')->order('id asc')->select();
        $this->view->assign('supplierList', $supplierList);
        $this->assignconfig('supplierList', $supplierList);
    }

    /**
     * 入库单列表（展平：每个商品一行）
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();

        $total = $this->model->where($where)->order($sort, $order)->count();
        $list = $this->model->with('info')->where($where)->order($sort, $order)->limit($offset, $limit)->select();

        // 展平：每个商品一行
        $rows = [];
        foreach ($list as $item) {
            $data = $item->toArray();
            $infoList = $data['info'] ?? [];
            unset($data['info']);
            if (empty($infoList)) {
                $data['goods_name'] = '';
                $data['goods_code'] = '';
                $data['quantity']   = '';
                $rows[] = $data;
            } else {
                foreach ($infoList as $g) {
                    $row = $data;
                    $row['goods_code'] = $g['goods_code'] ?? '';
                    $row['goods_name'] = $g['goods_name'] ?? '';
                    $row['unit_name']  = $g['unit_name'] ?? '';
                    $row['quantity']   = $g['quantity'] ?? 0;
                    $row['unit_price'] = $g['unit_price'] ?? 0;
                    $row['total_price'] = $g['total_price'] ?? 0;
                    $rows[] = $row;
                }
            }
        }

        return json(['total' => $total, 'rows' => $rows]);
    }

    /**
     * 添加入库单
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $goodsData = $this->request->post('goods/a', []);

            if (empty($params['store_id'])) {
                $this->error(__('请选择仓库'));
            }
            if (empty($goodsData) || !is_array($goodsData)) {
                $this->error(__('请添加入库商品'));
            }

            // 校验并获取仓库名称
            $store = Db::name('kuerp_stores')->where('id', $params['store_id'])->find();
            if (!$store) {
                $this->error(__('仓库不存在'));
            }

            // 校验供应商
            $supplierName = '';
            if (!empty($params['supplier_id'])) {
                $supplier = Db::name('kuerp_suppliers')->where('id', $params['supplier_id'])->find();
                $supplierName = $supplier ? $supplier['supplyer_full_name'] : '';
            }

            Db::startTrans();
            try {
                // 生成入库单号
                $inNo = 'RK' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

                $totalAmount = 0;
                $infoRows = [];
                foreach ($goodsData as $item) {
                    $goodsId = intval($item['goods_id'] ?? 0);
                    $quantity = floatval($item['quantity'] ?? 0);
                    $unitPrice = floatval($item['unit_price'] ?? 0);

                    if ($goodsId <= 0 || $quantity <= 0) {
                        continue;
                    }

                    $goods = Db::name('kuerp_goods')->where('id', $goodsId)->find();
                    if (!$goods) {
                        Db::rollback();
                        $this->error(__('商品不存在: ID=' . $goodsId));
                    }

                    $totalPrice = round($quantity * $unitPrice, 2);
                    $totalAmount += $totalPrice;

                    $infoRows[] = [
                        'goods_id'    => $goods['id'],
                        'goods_code'  => $goods['goods_code'],
                        'goods_name'  => $goods['goods_name'],
                        'unit_name'   => $item['unit_name'] ?? '',
                        'quantity'    => $quantity,
                        'unit_price'  => $unitPrice,
                        'total_price' => $totalPrice,
                        'createtime'  => time(),
                    ];
                }

                if (empty($infoRows)) {
                    Db::rollback();
                    $this->error(__('请添加有效的入库商品'));
                }

                // 创建入库单
                $inbound = $this->model->create([
                    'in_no'          => $inNo,
                    'supplier_id'    => intval($params['supplier_id'] ?? 0),
                    'supplier_name'  => $supplierName,
                    'store_id'       => intval($params['store_id']),
                    'store_name'     => $store['name'],
                    'total_amount'   => $totalAmount,
                    'remark'         => $params['remark'] ?? '',
                    'examine_status' => 0,
                    'admin_id'       => $this->auth->id,
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]);

                // 保存明细
                foreach ($infoRows as &$row) {
                    $row['in_id'] = $inbound->id;
                }
                unset($row);
                Db::name('kuerp_stone_inbound_info')->insertAll($infoRows);

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error(__('保存失败: ' . $e->getMessage()));
            }

            $this->success(__('保存成功'));
        }

        // 商品列表（用于弹窗选择）
        $goodsList = Db::name('kuerp_goods')
            ->where('status', 1)
            ->field('id,goods_code,goods_name,goods_image,unit_id,purchase as price')
            ->select();
        foreach ($goodsList as &$g) {
            $unitName = Db::name('kuerp_unit')->where('id', $g['unit_id'])->value('name');
            $g['unit_name'] = $unitName ?: '';
        }
        unset($g);
        $this->view->assign('goodsList', $goodsList);
        $this->assignconfig('goodsList', $goodsList);

        return $this->view->fetch();
    }

    /**
     * 入库单详情
     */
    public function detail($ids = '')
    {
        $row = $this->model->with('info')->find($ids);
        if (!$row) {
            $this->error(__('单据不存在'));
        }
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 审核/反审核
     */
    public function examine($ids = '')
    {
        $row = $this->model->find($ids);
        if (!$row) {
            $this->error(__('单据不存在'));
        }

        $infoList = Db::name('kuerp_stone_inbound_info')->where('in_id', $row->id)->select();
        if (empty($infoList)) {
            $this->error(__('单据明细为空'));
        }

        Db::startTrans();
        try {
            if ($row->examine_status == 0) {
                // 审核 — 增加库存
                foreach ($infoList as $info) {
                    $stock = Db::name('kuerp_goods_stock')
                        ->where('goods_code', $info['goods_code'])
                        ->where('stores_id', $row->store_id)
                        ->find();
                    if ($stock) {
                        Db::name('kuerp_goods_stock')
                            ->where('id', $stock['id'])
                            ->setInc('stock', $info['quantity']);
                    } else {
                        Db::name('kuerp_goods_stock')->insert([
                            'goods_code' => $info['goods_code'],
                            'stores_id'  => $row->store_id,
                            'stock'      => $info['quantity'],
                        ]);
                    }
                }
                $row->examine_status = 1;
                $row->examine_time   = time();
                $row->updatetime     = time();
                $row->save();
                Db::commit();
                $this->success(__('审核成功'));
            } else {
                // 反审核 — 扣减库存
                foreach ($infoList as $info) {
                    Db::name('kuerp_goods_stock')
                        ->where('goods_code', $info['goods_code'])
                        ->where('stores_id', $row->store_id)
                        ->setDec('stock', $info['quantity']);
                }
                $row->examine_status = 0;
                $row->examine_time   = 0;
                $row->updatetime     = time();
                $row->save();
                Db::commit();
                $this->success(__('反审核成功'));
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->error(__('操作失败: ' . $e->getMessage()));
        }
    }

    /**
     * 删除
     */
    public function del($ids = '')
    {
        if (!$ids) {
            $this->error(__('参数错误'));
        }
        $pk = $this->model->getPk();
        $rows = $this->model->where($pk, 'in', $ids)->select();

        Db::startTrans();
        try {
            foreach ($rows as $row) {
                if ($row->examine_status == 1) {
                    $this->error(__('已审核单据不可删除，请先反审核'));
                }
                Db::name('kuerp_stone_inbound_info')->where('in_id', $row->id)->delete();
                $row->delete();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error(__('删除失败: ' . $e->getMessage()));
        }

        $this->success(__('删除成功'));
    }
}
