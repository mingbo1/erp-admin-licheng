<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;

/**
 * 外协订单
 *
 * @icon fa fa-file-text-o
 */
class ProcessOrder extends Backend
{
    // printpage 已纳入权限表（kuerp/process_order/printpage）
    protected $noNeedRight = ['getunit', 'examine_fee'];

    /**
     * ProcessOrder模型对象
     * @var \app\admin\model\kuerp\ProcessOrder
     */
    protected $model = null;

    /**
     * 需要隐藏的金额字段（订单页面展示完整金额信息）
     */
    private $hiddenAmountFields = [];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\ProcessOrder;
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("inStatusList", $this->model->getInStatusList());

        $this->view->assign("suppliersList", $this->model->getSuppliersList());
        $this->assignconfig("suppliersLists", $this->model->getSuppliersList());

        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("accountsList", $this->model->getAccountsList());
        $this->assignconfig("expendTypeLists", $this->model->getExpendTypeList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'process_time';
            $order = 'DESC';
            $list = $this->model
                ->with(['suppliers', 'preparation', 'examine'])
                ->where($where)
                ->order($sort, $order)
                ->order('process_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('suppliers')->visible(['supplyer_code', 'supplyer_name']);
                $row->getRelation('preparation')->visible(['nickname']);
                if ($row->getRelation('examine')) {
                    $row->getRelation('examine')->visible(['nickname']);
                }
            }
            foreach ($list as $list_k => $list_v) {
                if (isset($list_v['print_num']) && empty($list_v['print_num'])) {
                    $list[$list_k]['print_num'] = 0;
                }
                // 计划部门隐藏金额字段
                $row = $list[$list_k];
                $row->hidden($this->hiddenAmountFields);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $examine = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');
            if ($params) {
                $params = $this->preExcludeFields($params);
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['process_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
                if (empty($params['process_code'])) {
                    $this->error('请填写单据编号');
                }
                if (empty($params['supplyer_code'])) {
                    $this->error('请选择加工商');
                }

                $admin = Session::get('admin');
                $serial_set = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');
                $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');
                $auxiliary_status = Db::name('kuerp_sets')->where(['name' => 'auxiliary'])->value('is_checked');

                $data = [];
                $p = 2; // type=1 出库原材料标记
                $z = 2; // type=2 入库成品标记
                $all_number = 0;
                $goods_serial_all = [];

                foreach ($params['goods_code'] as $k => $v) {
                    if (!empty($v)) {
                        // 通过 type 字段区分: 第一组为原材料(type=1), 第二组为成品(type=2)
                        $row_type = isset($params['row_type'][$k]) ? $params['row_type'][$k] : '1';

                        if (!isset($params['goods_unit'][$k]) || empty($params['goods_unit'][$k])) {
                            $this->error('请选择单位');
                        }
                        if (!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])) {
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code' => $v])->find();
                        if (empty($goods)) {
                            $this->error('商品不存在');
                        }
                        if ($goods['auxiliary'] == 1 && empty($params['goods_attr'][$k]) && $auxiliary_status == 1) {
                            $this->error('请选择商品' . $v . '属性');
                        }

                        $goods_unit = $params['goods_unit'][$k] === '' ? null : (float)$params['goods_unit'][$k];
                        $advance_rate = 1;
                        if ($goods['more_unit'] == 1) {
                            if ($goods_unit != $goods['unit_id']) {
                                $more_unit = Db::name('kuerp_unit_conversion')
                                    ->where(['main_unit' => $goods['unit_id'], 'vice_unit' => $goods_unit])
                                    ->field('id,advance_rate')
                                    ->find();
                                if (empty($more_unit)) {
                                    $this->error('商品单位信息查询失败');
                                } else {
                                    $advance_rate = $more_unit['advance_rate'];
                                }
                            }
                        }
                        $basic_number = round(($advance_rate * $params['number'][$k]), 4);

                        if ($row_type == '1') {
                            // ===== 出库原材料 =====
                            // 批次
                            $goods_batch = [];
                            $goods_batch_info = [];
                            if ($goods['batch'] == 1 && $batch_set == 1) {
                                if (empty($params['batch'][$k])) {
                                    $this->error('请选择生产批次');
                                }
                                if (empty($params['manufacture'][$k])) {
                                    $this->error('请输入生产日期');
                                }
                                if ($params['guarantee'][$k] === '') {
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k] < 0) {
                                    $this->error('保质期天数不可小于0');
                                }
                                if (empty($params['validity'][$k])) {
                                    $this->error('请输入过期时间');
                                }
                                $batch_data = Db::name('kuerp_goods_batch')->where(['id' => $params['batch_id'][$k]])->find();
                                if (empty($batch_data)) {
                                    $this->error('商品批次信息查询失败');
                                }
                                $goods_batch_info = \addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id'     => isset($batch_data['id']) ? $batch_data['id'] : '',
                                    'goods_id'     => $goods['id'],
                                    'goods_code'   => $params['goods_code'][$k],
                                    'attribute_id' => isset($params['goods_attr'][$k]) ? $params['goods_attr'][$k] : 0,
                                    'goods_unit'   => (int)$params['goods_unit'][$k],
                                    'stores_id'    => $params['stores_id'][$k],
                                    'stock'        => (float)$params['number'][$k],
                                    'number'       => (float)$params['number'][$k],
                                    'batch_name'   => isset($batch_data['batch']) ? $batch_data['batch'] : '',
                                    'manufacture'  => strtotime($params['manufacture'][$k]),
                                    'guarantee'    => $params['guarantee'][$k],
                                    'validity'     => strtotime($params['validity'][$k]),
                                    'status'       => '0',
                                    'type'         => 5, // 外协出库
                                    'createtime'   => time(),
                                    'updatetime'   => time(),
                                ]);
                            }

                            // 序列号
                            $goods_serial = '';
                            if (isset($params['period_period'][$k]) && $goods['serial'] == 1 && $serial_set == 1) {
                                if (empty($params['period_period'][$k])) {
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k => $period_period_v) {
                                    if (in_array($period_period_v['serial'], $goods_serial_all)) {
                                        $this->error('商品序列号' . $period_period_v['serial'] . '重复');
                                    } else {
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial' => ['in', $serial_code], 'store_status' => '1'])->column('serial');
                                if (sizeof($serial) != sizeof($serial_code)) {
                                    foreach ($serial_code as $serial_code_k => $serial_code_v) {
                                        if (!in_array($serial_code_v, $serial)) {
                                            $this->error('商品序列号' . $serial_code_v . '不在库，不可出库');
                                        }
                                    }
                                    $this->error('商品序列号不在库，不可出库');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            // 单位成本: 自动从库存获取 (表单 unit_price → DB unit_cost)
                            $unit_cost = isset($params['unit_price'][$k]) ? (float)$params['unit_price'][$k] : 0;
                            $cost_money = isset($params['all_money'][$k]) ? (float)$params['all_money'][$k] : ($unit_cost * $basic_number);

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'process_code'   => $params['process_code'],
                                'process_time'   => strtotime($params['process_time']),
                                'type'           => '1',

                                'goods_id'       => $goods['id'],
                                'goods_code'     => $goods['goods_code'],
                                'goods_attr'     => $params['goods_attr'][$k] === '' ? null : (float)$params['goods_attr'][$k],
                                'goods_unit'     => $goods_unit,

                                'stores_id'      => $params['stores_id'][$k] === '' ? null : (float)$params['stores_id'][$k],
                                'allow_stock'    => $params['allow_stock'][$k] === '' ? null : (float)$params['allow_stock'][$k],
                                'number'         => $params['number'][$k] === '' ? null : (float)$params['number'][$k],
                                'period'         => $goods_serial,
                                'basic_number'   => $basic_number,
                                'unit_cost'      => $unit_cost,
                                'cost_money'     => $cost_money,
                                'remark'         => $params['remark'][$k] === '' ? null : $params['remark'][$k],

                                'batch'          => $params['batch'][$k] === '' ? null : $params['batch'][$k],
                                'batch_id'       => $params['batch_id'][$k] === '' ? null : (float)$params['batch_id'][$k],
                                'manufacture'    => $params['manufacture'][$k] === '' ? null : strtotime($params['manufacture'][$k]),
                                'guarantee'      => $params['guarantee'][$k] === '' ? null : (float)$params['guarantee'][$k],
                                'validity'       => $params['validity'][$k] === '' ? null : strtotime($params['validity'][$k]),

                                'preparation_id' => $admin['id'],
                                'operation_id'   => $admin['id'],

                                'createtime'     => time(),
                                'updatetime'     => time(),
                                'goods_batch'    => $goods_batch,
                                'goods_batch_info' => $goods_batch_info
                            ]);
                            $p = 1;
                            $all_number += (float)$params['number'][$k];

                        } else {
                            // ===== 入库成品 =====
                            // 成品创建时不填成本，入库时手动输入
                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'process_code'   => $params['process_code'],
                                'process_time'   => strtotime($params['process_time']),
                                'type'           => '2',

                                'goods_id'       => $goods['id'],
                                'goods_code'     => $goods['goods_code'],
                                'goods_attr'     => $params['goods_attr'][$k] === '' ? null : (float)$params['goods_attr'][$k],
                                'goods_unit'     => $goods_unit,

                                'stores_id'      => $params['stores_id'][$k] === '' ? null : (float)$params['stores_id'][$k],
                                'allow_stock'    => $params['allow_stock'][$k] === '' ? null : (float)$params['allow_stock'][$k],
                                'number'         => $params['number'][$k] === '' ? null : (float)$params['number'][$k],
                                'in_number'      => 0,
                                'period'         => null,
                                'basic_number'   => $basic_number,
                                'unit_cost'      => null,  // 入库时手动输入
                                'cost_money'     => null,
                                'remark'         => $params['remark'][$k] === '' ? null : $params['remark'][$k],

                                'preparation_id' => $admin['id'],
                                'operation_id'   => $admin['id'],

                                'createtime'     => time(),
                                'updatetime'     => time(),
                                'goods_batch'    => [],
                                'goods_batch_info' => []
                            ]);
                            $z = 1;
                        }
                    }
                }
                if ($p == 2) {
                    $this->error('出库原材料信息不能为空');
                }
                if ($z == 2) {
                    $this->error('入库成品信息不能为空');
                }

                if (!empty($data)) {
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'process_code'   => $params['process_code'],
                        'process_time'   => strtotime($params['process_time']),
                        'supplyer_code'  => $params['supplyer_code'],
                        'number'         => $all_number,
                        'in_number'      => 0,
                        'total_process_fee' => 0,
                        'remark'         => $params['note'] === '' ? null : $params['note'],
                        'preparation_id' => $admin['id'],
                        'operation_id'   => $admin['id'],
                        'createtime'     => time(),
                        'updatetime'     => time(),
                    ]);
                } else {
                    $this->error('明细信息不能为空');
                }

                if ($this->model->where(['process_code' => trim($params['process_code'])])->find()) {
                    $this->error('单据编号已占用');
                }

                $result = false;
                Db::startTrans();
                try {
                    if (!empty($row)) {
                        $result = $this->model->insertGetId($row);
                    }
                    foreach ($data as $data_k => $data_v) {
                        $goods_batch = $data_v['goods_batch'];
                        unset($data_v['goods_batch']);
                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);
                        $goods_batch_id = 0;
                        if (!empty($goods_batch)) {
                            $goods_batch_id = Db::name('kuerp_goods_batch')->insertGetId($goods_batch);
                        }
                        if ($goods_batch_id > 0) {
                            $goods_batch_info['batch_id'] = $goods_batch_id;
                            $goods_batch_info['batch_name'] = $goods_batch['batch_name'];
                            $data_v['batch_id'] = $goods_batch_id;
                            $data_v['batch'] = $goods_batch['batch_name'];
                        }
                        $info_id = Db::name('kuerp_process_info')->insertGetId($data_v);
                        // 批次
                        if ($goods_batch_info) {
                            $goods_batch_info['process_info_id'] = $info_id;
                            Db::name('kuerp_goods_batch_info')->insert($goods_batch_info);
                        }
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    if ($examine != 1) {
                        try {
                            $exa = $this->examine($result, 1);
                        } catch (Exception $e) {
                        }
                        if (!isset($exa) || $exa['status'] != 1) {
                            $exa = $this->examine($result, 1);
                            try {
                                $this->del($result);
                            } catch (Exception $e) {
                            }
                            $this->error($exa['msg']);
                        }
                    }
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $code = \addons\kuerp\library\Goods::getCodes('', 28);
        $this->view->assign("code", $code);

        $last_customer = Db::name('kuerp_sets')->where(['name' => 'customer'])->value('is_checked');
        $set_supplier = '';
        if ($last_customer) {
            $set_supplier = Db::name('kuerp_process')->order('id', 'DESC')->value('supplyer_code');
        }
        $this->view->assign("set_supplier", $set_supplier);
        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code' => $set_supplier])->field('supplyer_code,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);

        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['process_time']);
            if ($checkout !== true) {
                $this->error($checkout);
            }
            $examine = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');
            if ($row['examine_status'] == 1 && $examine == 1) {
                $this->error('不能编辑已审核的数据');
            }
            if ($row['in_status'] != 0) {
                $this->error('不能编辑已有入库记录的数据');
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $old_process_code = $params['old_process_code'];
                unset($params['old_process_code']);

                if (empty($params['process_code'])) {
                    $this->error('请填写单据编号');
                }
                if (empty($params['supplyer_code'])) {
                    $this->error('请选择加工商');
                }

                $admin = Session::get('admin');
                $serial_set = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');
                $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');
                $auxiliary_status = Db::name('kuerp_sets')->where(['name' => 'auxiliary'])->value('is_checked');

                $data = [];
                $p = 2;
                $z = 2;
                $all_number = 0;
                $goods_serial_all = [];

                foreach ($params['goods_code'] as $k => $v) {
                    if (!empty($v)) {
                        $row_type = isset($params['row_type'][$k]) ? $params['row_type'][$k] : '1';

                        if (!isset($params['goods_unit'][$k]) || empty($params['goods_unit'][$k])) {
                            $this->error('请选择单位');
                        }
                        if (!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])) {
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code' => $v])->find();
                        if (empty($goods)) {
                            $this->error('商品不存在');
                        }
                        if ($goods['auxiliary'] == 1 && empty($params['goods_attr'][$k]) && $auxiliary_status == 1) {
                            $this->error('请选择商品' . $v . '属性');
                        }

                        $goods_unit = $params['goods_unit'][$k] === '' ? null : (float)$params['goods_unit'][$k];
                        $advance_rate = 1;
                        if ($goods['more_unit'] == 1) {
                            if ($goods_unit != $goods['unit_id']) {
                                $more_unit = Db::name('kuerp_unit_conversion')
                                    ->where(['main_unit' => $goods['unit_id'], 'vice_unit' => $goods_unit])
                                    ->field('id,advance_rate')
                                    ->find();
                                if (empty($more_unit)) {
                                    $this->error('商品单位信息查询失败');
                                } else {
                                    $advance_rate = $more_unit['advance_rate'];
                                }
                            }
                        }
                        $basic_number = round(($advance_rate * $params['number'][$k]), 4);

                        if ($row_type == '1') {
                            // 出库原材料
                            $goods_batch = [];
                            $goods_batch_info = [];
                            if ($goods['batch'] == 1 && $batch_set == 1) {
                                if (empty($params['batch'][$k])) {
                                    $this->error('请选择生产批次');
                                }
                                if (empty($params['manufacture'][$k])) {
                                    $this->error('请输入生产日期');
                                }
                                if ($params['guarantee'][$k] === '') {
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k] < 0) {
                                    $this->error('保质期天数不可小于0');
                                }
                                if (empty($params['validity'][$k])) {
                                    $this->error('请输入过期时间');
                                }
                                $batch_data = Db::name('kuerp_goods_batch')->where(['id' => $params['batch_id'][$k]])->find();
                                if (empty($batch_data)) {
                                    $this->error('商品批次信息查询失败');
                                }
                                $goods_batch_info = \addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id'     => isset($batch_data['id']) ? $batch_data['id'] : '',
                                    'goods_id'     => $goods['id'],
                                    'goods_code'   => $params['goods_code'][$k],
                                    'attribute_id' => isset($params['goods_attr'][$k]) ? $params['goods_attr'][$k] : 0,
                                    'goods_unit'   => (int)$params['goods_unit'][$k],
                                    'stores_id'    => $params['stores_id'][$k],
                                    'stock'        => (float)$params['number'][$k],
                                    'number'       => (float)$params['number'][$k],
                                    'batch_name'   => isset($batch_data['batch']) ? $batch_data['batch'] : '',
                                    'manufacture'  => strtotime($params['manufacture'][$k]),
                                    'guarantee'    => $params['guarantee'][$k],
                                    'validity'     => strtotime($params['validity'][$k]),
                                    'status'       => '0',
                                    'type'         => 5,
                                    'createtime'   => time(),
                                    'updatetime'   => time(),
                                ]);
                            }

                            $goods_serial = '';
                            if (isset($params['period_period'][$k]) && $goods['serial'] == 1 && $serial_set == 1) {
                                if (empty($params['period_period'][$k])) {
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k => $period_period_v) {
                                    if (in_array($period_period_v['serial'], $goods_serial_all)) {
                                        $this->error('商品序列号' . $period_period_v['serial'] . '重复');
                                    } else {
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial' => ['in', $serial_code], 'store_status' => '1'])->column('serial');
                                if (sizeof($serial) != sizeof($serial_code)) {
                                    foreach ($serial_code as $serial_code_k => $serial_code_v) {
                                        if (!in_array($serial_code_v, $serial)) {
                                            $this->error('商品序列号' . $serial_code_v . '不在库，不可出库');
                                        }
                                    }
                                    $this->error('商品序列号不在库，不可出库');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            $unit_cost = isset($params['unit_price'][$k]) ? (float)$params['unit_price'][$k] : 0;
                            $cost_money = isset($params['all_money'][$k]) ? (float)$params['all_money'][$k] : ($unit_cost * $basic_number);

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'process_code'   => $params['process_code'],
                                'process_time'   => strtotime($params['process_time']),
                                'type'           => '1',

                                'goods_id'       => $goods['id'],
                                'goods_code'     => $goods['goods_code'],
                                'goods_attr'     => $params['goods_attr'][$k] === '' ? null : (float)$params['goods_attr'][$k],
                                'goods_unit'     => $goods_unit,

                                'stores_id'      => $params['stores_id'][$k] === '' ? null : (float)$params['stores_id'][$k],
                                'allow_stock'    => $params['allow_stock'][$k] === '' ? null : (float)$params['allow_stock'][$k],
                                'number'         => $params['number'][$k] === '' ? null : (float)$params['number'][$k],
                                'period'         => $goods_serial,
                                'basic_number'   => $basic_number,
                                'unit_cost'      => $unit_cost,
                                'cost_money'     => $cost_money,
                                'remark'         => $params['remark'][$k] === '' ? null : $params['remark'][$k],

                                'batch'          => $params['batch'][$k] === '' ? null : $params['batch'][$k],
                                'batch_id'       => $params['batch_id'][$k] === '' ? null : (float)$params['batch_id'][$k],
                                'manufacture'    => $params['manufacture'][$k] === '' ? null : strtotime($params['manufacture'][$k]),
                                'guarantee'      => $params['guarantee'][$k] === '' ? null : (float)$params['guarantee'][$k],
                                'validity'       => $params['validity'][$k] === '' ? null : strtotime($params['validity'][$k]),

                                'preparation_id' => $admin['id'],
                                'operation_id'   => $admin['id'],

                                'createtime'     => time(),
                                'updatetime'     => time(),
                                'goods_batch'    => $goods_batch,
                                'goods_batch_info' => $goods_batch_info
                            ]);
                            $p = 1;
                            $all_number += (float)$params['number'][$k];

                        } else {
                            // 入库成品 (保持原有的 in_number)
                            $old_info = Db::name('kuerp_process_info')
                                ->where(['process_code' => $old_process_code, 'type' => '2', 'goods_code' => $v])
                                ->find();
                            $old_in_number = $old_info ? $old_info['in_number'] : 0;

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'process_code'   => $params['process_code'],
                                'process_time'   => strtotime($params['process_time']),
                                'type'           => '2',

                                'goods_id'       => $goods['id'],
                                'goods_code'     => $goods['goods_code'],
                                'goods_attr'     => $params['goods_attr'][$k] === '' ? null : (float)$params['goods_attr'][$k],
                                'goods_unit'     => $goods_unit,

                                'stores_id'      => $params['stores_id'][$k] === '' ? null : (float)$params['stores_id'][$k],
                                'allow_stock'    => $params['allow_stock'][$k] === '' ? null : (float)$params['allow_stock'][$k],
                                'number'         => $params['number'][$k] === '' ? null : (float)$params['number'][$k],
                                'in_number'      => $old_in_number,
                                'period'         => null,
                                'basic_number'   => $basic_number,
                                'unit_cost'      => null,
                                'cost_money'     => null,
                                'remark'         => $params['remark'][$k] === '' ? null : $params['remark'][$k],

                                'preparation_id' => $admin['id'],
                                'operation_id'   => $admin['id'],

                                'createtime'     => time(),
                                'updatetime'     => time(),
                                'goods_batch'    => [],
                                'goods_batch_info' => []
                            ]);
                            $z = 1;
                        }
                    }
                }
                if ($p == 2) {
                    $this->error('出库原材料信息不能为空');
                }
                if ($z == 2) {
                    $this->error('入库成品信息不能为空');
                }

                if (!empty($data)) {
                    $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                        'process_code'   => $params['process_code'],
                        'process_time'   => strtotime($params['process_time']),
                        'supplyer_code'  => $params['supplyer_code'],
                        'number'         => $all_number,
                        'remark'         => $params['note'] === '' ? null : $params['note'],
                        'preparation_id' => $admin['id'],
                        'operation_id'   => $admin['id'],
                        'updatetime'     => time(),
                    ]);
                }

                if ($this->model->where(['process_code' => trim($params['process_code']), 'id' => ['<>', $ids]])->find()) {
                    $this->error('单据编号已占用');
                }

                $old_info_ids = Db::name('kuerp_process_info')->where(['process_code' => $old_process_code])->column('id');

                $result = false;
                Db::startTrans();
                try {
                    if ($examine != 1 && $row['examine_status'] == 1) {
                        $this->examine($ids, 2);
                    }
                    if (!empty($old_info_ids)) {
                        Db::name('kuerp_goods_batch_info')->where(['process_info_id' => ['in', $old_info_ids]])->delete();
                    }
                    Db::name('kuerp_process_info')->where(['process_code' => $old_process_code])->delete();
                    if (!empty($rowdata)) {
                        $result = $this->model->where(['id' => $ids])->update($rowdata);
                    }
                    foreach ($data as $data_k => $data_v) {
                        $goods_batch = $data_v['goods_batch'];
                        unset($data_v['goods_batch']);
                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);
                        $goods_batch_id = 0;
                        if (!empty($goods_batch)) {
                            $goods_batch_id = Db::name('kuerp_goods_batch')->insertGetId($goods_batch);
                        }
                        if ($goods_batch_id > 0) {
                            $goods_batch_info['batch_id'] = $goods_batch_id;
                            $goods_batch_info['batch_name'] = $goods_batch['batch_name'];
                            $data_v['batch_id'] = $goods_batch_id;
                            $data_v['batch'] = $goods_batch['batch_name'];
                        }
                        $info_id = Db::name('kuerp_process_info')->insertGetId($data_v);
                        if ($goods_batch_info) {
                            $goods_batch_info['process_info_id'] = $info_id;
                            Db::name('kuerp_goods_batch_info')->insert($goods_batch_info);
                        }
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    if ($examine != 1) {
                        try {
                            $exa = $this->examine($ids, 1);
                        } catch (Exception $e) {
                        }
                        if (!isset($exa) || $exa['status'] != 1) {
                            $this->error(__('No rows were inserted'));
                        }
                    }
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        // 隐藏金额字段
        $row->hidden($this->hiddenAmountFields);
        $this->view->assign("row", $row);

        $info = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $row['process_code']])
            ->field('a.*,g.goods_name')
            ->select();
        // 批量查询动态库存
        $goodsCodes = array_column($info, 'goods_code');
        $goodsIdMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id', 'goods_code') : [];
        $stockItems = [];
        foreach ($info as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? $goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        foreach ($info as $info_k => $info_v) {
            $info[$info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($info_v['goods_code'], $info_v['stores_id'], $info_v['goods_attr']), true);
            // DB→表单字段映射（成本在后台计算，前端隐藏）
            $info[$info_k]['unit_price'] = $info_v['unit_cost'];
            $info[$info_k]['all_money'] = $info_v['cost_money'];
            $gid = isset($goodsIdMap[$info_v['goods_code']]) ? $goodsIdMap[$info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$info_v['stores_id'] . '-' . (int)$info_v['goods_attr'];
            $info[$info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
        }
        $this->view->assign("info_list", $info);
        $this->assignconfig("info_list", $info);

        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->field('supplyer_code,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);

        // 审核禁止编辑
        $examine_set = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');
        if ($examine_set == 1 && $row['examine_status'] == 1) {
            $this->assign('exclass', 'hiddens');
        }
        $this->assignconfig("examine_status", $row['examine_status']);

        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $examine = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');

            foreach ($list as $list_k => $list_v) {
                if ($list_v['examine_status'] == 1) {
                    $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['process_time']);
                    if ($checkout !== true) {
                        $this->error($checkout);
                    }
                }
                if ($list_v['examine_status'] == 1 && $examine == 1) {
                    $this->error('不能删除已审核的数据');
                } elseif ($list_v['examine_status'] == 1 && $examine != 1) {
                    try {
                        $this->examine($list_v['id'], 2);
                    } catch (Exception $e) {
                    }
                }
                if ($list_v['in_status'] != 0) {
                    $this->error('不能删除已有入库记录的数据');
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    $info_ids = Db::name('kuerp_process_info')->where(['process_code' => $v['process_code']])->column('id');
                    Db::name('kuerp_goods_batch_info')->where(['process_info_id' => ['in', $info_ids]])->delete();
                    Db::name('kuerp_process_info')->where(['process_code' => $v['process_code']])->delete();
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 审核/反审核
    /**
     * 审核时填写加工费页面
     * GET: 展示外协订单, 可编辑每个入库成品的加工费
     * POST: 保存加工费, 累加total_process_fee, 然后执行审核
     */
    public function examine_fee($ids = null)
    {
        // 处理逗号分隔的ID
        $ids = trim($ids, ',');
        $idsArray = explode(',', $ids);

        if (count($idsArray) > 1) {
            $this->error('一次只能审核一条数据');
        }
        $id = $idsArray[0];

        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($row['examine_status'] == 1) {
            $this->error('该订单已审核');
        }

        if ($this->request->isPost()) {
            // 保存每行加工费（单件加工费）
            $process_fees = $this->request->post('process_fee/a');
            $total_fee = 0;

            if (!empty($process_fees)) {
                foreach ($process_fees as $info_id => $fee) {
                    $fee = (float)$fee;
                    if ($fee > 0) {
                        // 按单件加工费 × 计划数量 计算该成品的加工费小计
                        $info = Db::name('kuerp_process_info')->where(['id' => $info_id])->field('number,process_fee')->find();
                        if ($info) {
                            $subtotal = round($fee * (float)$info['number'], 2);
                            $total_fee += $subtotal;
                            Db::name('kuerp_process_info')->where(['id' => $info_id])->update(['process_fee' => $fee]);
                        }
                    }
                }
            }

            // 更新主表累计加工费
            if ($total_fee > 0) {
                $this->model->where(['id' => $id])->update(['total_process_fee' => $total_fee]);
            }

            // 创建核销源单（写入 writeoff_source，使付款单可选此加工单为源单）
            $row = $this->model->get($id);
            if ($total_fee > 0 && !empty($row['supplyer_code'])) {
                $existing = Db::name('kuerp_writeoff_source')
                    ->where(['purchase_code' => $row['process_code'], 'purchase_type' => 6])
                    ->find();
                if (!$existing) {
                    Db::name('kuerp_writeoff_source')->insert([
                        'purchase_type'  => 6,
                        'type'           => 1,
                        'suppliers_code' => $row['supplyer_code'],
                        'purchase_code'  => $row['process_code'],
                        'purchase_time'  => $row['process_time'],
                        'price'          => $total_fee,
                        'money'          => 0,
                        'write_off'      => $total_fee,
                        'remark'         => $row['remark'],
                    ]);
                    // 增加供应商应付余额
                    Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])
                        ->inc('need_give_account', $total_fee)->update();
                    // 记录应付明细
                    $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->field('id')->find();
                    if ($supplier) {
                        Db::name('kuerp_accounts_copewith')->insert(\addons\kuerp\library\Goods::setDataInfo([
                            'suppliers_id'  => $supplier['id'],
                            'purchase_time' => $row['process_time'],
                            'purchase_code' => $row['process_code'],
                            'business_type' => '10',
                            'receivable'    => $total_fee,
                            'remark'        => $row['remark'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]));
                    }
                }
            }

            // 执行审核
            $this->examine($ids, 1);
            return;
        }

        // GET: 展示页面
        $row->hidden($this->hiddenAmountFields);

        $info = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $row['process_code']])
            ->field('a.*,g.goods_name')
            ->select();
        foreach ($info as $info_k => $info_v) {
            $info[$info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($info_v['goods_code']), true);
            $info[$info_k]['unit_price'] = $info_v['unit_cost'];
            $info[$info_k]['all_money'] = $info_v['cost_money'];
            $info[$info_k]['stores_name'] = Db::name('kuerp_stores')->where(['id' => $info_v['stores_id']])->value('name');
            $info[$info_k]['goods_unit_name'] = Db::name('kuerp_unit')->where(['id' => $info_v['goods_unit']])->value('name');
            // 属性名称
            if (!empty($info_v['goods_attr'])) {
                $attr = Db::name('kuerp_goods_attr')->alias('ga')
                    ->join('kuerp_aux_combination ac', 'ac.id=ga.combination_id')
                    ->join('kuerp_auxiliary a', 'a.id=ac.auxiliary_id')
                    ->where(['ga.goods_id' => $info_v['goods_id'], 'ga.id' => $info_v['goods_attr']])
                    ->field('a.attr_name')
                    ->find();
                $info[$info_k]['goods_attr_name'] = $attr ? $attr['attr_name'] : '';
            }
        }
        $this->view->assign("row", $row);
        $this->view->assign("info_list", $info);

        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->field('supplyer_code,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);

        return $this->view->fetch('examine_fee');
    }

    /**
     * 审核/反审核
     * 审核: 仅更新状态, 库存扣减移至加工单出库操作
     * 反审核: 仅更新状态 (需 in_status=0 且 outbound_status=0)
     */
    public function examine($ids = null, $examine = 1)
    {
        $examine_set = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');
        if (empty($ids)) {
            if ($examine_set != 1) {
                return ['status' => 0, 'msg' => '系统错误'];
            }
            $this->error('请选择要操作的数据');
        }

        if ($examine == 1) {
            // ===== 审核: 仅更新状态 =====
            $list = $this->model->where(['id' => ['in', $ids], 'examine_status' => '0'])->select();
            foreach ($list as $list_k => $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['process_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
            }
            if (empty($list)) {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id' => ['in', $ids], 'examine_status' => '0'])->update([
                    'examine_status'  => 1,
                    'examine_id'      => $admin['id'],
                    'examine_time'    => time(),
                    'operation_id'    => $admin['id'],
                    'in_status'       => '0',
                    'outbound_status' => '0',
                    'pay_status'      => '0',
                    'pay_money'       => 0,
                ]);
                // 创建核销源单（auto-audit模式或examine_fee漏创建时的补偿）
                foreach ($list as $list_v) {
                    if (!empty($list_v['total_process_fee']) && $list_v['total_process_fee'] > 0 && !empty($list_v['supplyer_code'])) {
                        $existing = Db::name('kuerp_writeoff_source')
                            ->where(['purchase_code' => $list_v['process_code'], 'purchase_type' => 6])
                            ->find();
                        if (!$existing) {
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type'  => 6,
                                'type'           => 1,
                                'suppliers_code' => $list_v['supplyer_code'],
                                'purchase_code'  => $list_v['process_code'],
                                'purchase_time'  => $list_v['process_time'],
                                'price'          => $list_v['total_process_fee'],
                                'money'          => 0,
                                'write_off'      => $list_v['total_process_fee'],
                                'remark'         => $list_v['remark'],
                            ]);
                            // 增加供应商应付余额
                            Db::name('kuerp_suppliers')->where(['supplyer_code' => $list_v['supplyer_code']])
                                ->inc('need_give_account', $list_v['total_process_fee'])->update();
                            // 记录应付明细
                            $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $list_v['supplyer_code']])->field('id')->find();
                            if ($supplier) {
                                Db::name('kuerp_accounts_copewith')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                    'suppliers_id'  => $supplier['id'],
                                    'purchase_time' => $list_v['process_time'],
                                    'purchase_code' => $list_v['process_code'],
                                    'business_type' => '10',
                                    'receivable'    => $list_v['total_process_fee'],
                                    'remark'        => $list_v['remark'],
                                    'createtime'    => time(),
                                    'updatetime'    => time(),
                                ]));
                            }
                        }
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if (isset($result) && $result !== false) {
                if ($examine_set != 1) {
                    return ['status' => 1, 'msg' => '操作成功'];
                }
                $this->success(sizeof($list) . '条数据审核成功');
            } else {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('审核失败');
            }

        } else {
            // ===== 反审核: 仅更新状态 =====
            $list = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->select();
            foreach ($list as $list_k => $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['process_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
                if ($list_v['in_status'] != 0) {
                    if ($examine_set != 1) {
                        return ['status' => 0, 'msg' => '已有入库记录，请先处理入库记录再反审核'];
                    }
                    $this->error('已有入库记录，请先处理入库记录再反审核');
                }
                if ($list_v['outbound_status'] == 1) {
                    if ($examine_set != 1) {
                        return ['status' => 0, 'msg' => '已出库，请先在加工单页面取消出库后再反审核'];
                    }
                    $this->error('已出库，请先在加工单页面取消出库后再反审核');
                }
            }
            if (empty($list)) {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('请选择需要反审核的数据');
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->update([
                    'examine_status' => 0,
                    'examine_id'     => 0,
                    'examine_time'   => '',
                    'operation_id'   => $admin['id'],
                    'pay_status'     => 0,
                    'pay_money'      => 0,
                ]);
                // 删除核销源单及应付记录（仅当未发生付款核销时）
                foreach ($list as $list_v) {
                    $has_log = Db::name('kuerp_writeoff_log')->alias('l')
                        ->join('kuerp_writeoff_source s', 's.id=l.source_id')
                        ->where(['s.purchase_code' => $list_v['process_code'], 's.purchase_type' => 6])
                        ->where('l.money', '>', 0)
                        ->find();
                    if (!$has_log) {
                        Db::name('kuerp_writeoff_source')
                            ->where(['purchase_code' => $list_v['process_code'], 'purchase_type' => 6])
                            ->delete();
                        // 减少供应商应付余额
                        if (!empty($list_v['total_process_fee']) && $list_v['total_process_fee'] > 0 && !empty($list_v['supplyer_code'])) {
                            Db::name('kuerp_suppliers')->where(['supplyer_code' => $list_v['supplyer_code']])
                                ->dec('need_give_account', $list_v['total_process_fee'])->update();
                            // 删除应付明细
                            Db::name('kuerp_accounts_copewith')
                                ->where(['business_type' => '10', 'purchase_code' => $list_v['process_code']])
                                ->delete();
                        }
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if (isset($result) && $result !== false) {
                if ($examine_set != 1) {
                    return ['status' => 1, 'msg' => '操作成功'];
                }
                $this->success(sizeof($list) . '条数据反审核成功');
            } else {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('反审核失败');
            }
        }
    }

    /**
     * 查看详情（隐藏金额）
     */
    public function detail($ids = null)
    {
        $process_id = $ids ? $ids : $this->request->get('ids');
        if (empty($process_id)) {
            $this->error('参数错误');
        }
        $row = $this->model->with(['suppliers', 'preparation', 'examine'])->find($process_id);
        if (!$row) {
            $this->error('外协订单不存在');
        }
        $row->getRelation('suppliers')->visible(['supplyer_code', 'supplyer_name']);
        // 隐藏金额
        $row->hidden($this->hiddenAmountFields);

        // 明细（出库原材料 + 入库成品）
        $info_list = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $row['process_code']])
            ->field('a.*,g.goods_name')
            ->order('a.type', 'ASC')
            ->select();
        foreach ($info_list as &$v) {
            $v['store_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
        }

        // 加工费记录
        $fee_list = Db::name('kuerp_process_fee')->alias('f')
            ->join('kuerp_accounts a', 'a.id=f.account_id', 'LEFT')
            ->join('kuerp_expend_type e', 'e.id=f.expend_type_id', 'LEFT')
            ->where(['f.process_code' => $row['process_code']])
            ->field('f.*,a.account_name,e.name as expend_name')
            ->select();

        $this->view->assign("row", $row);
        $this->view->assign("info_list", $info_list);
        $this->view->assign("fee_list", $fee_list);
        return $this->view->fetch('detail');
    }

    /**
     * 打印
     */
    public function printpage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 展示完整金额信息
        $row->hidden($this->hiddenAmountFields);

        // 供应商名称
        $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->field('supplyer_code,supplyer_name')->find();
        $row['supplyer_name'] = $supplier ? $supplier['supplyer_name'] : '';

        // 制单人 / 审核人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname');

        // 明细（出库原材料 type=1 + 入库成品 type=2）
        $info_list = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $row['process_code']])
            ->field('a.*,g.goods_name,g.note as goods_note')
            ->order('a.type', 'ASC')
            ->select();
        foreach ($info_list as &$v) {
            $v['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
            $v['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
        }
        unset($v);

        // 分离：出库原材料和入库成品
        $out_materials = [];
        $in_products = [];
        foreach ($info_list as $v) {
            if ($v['type'] == '1') {
                $out_materials[] = $v;
            } else {
                $in_products[] = $v;
            }
        }

        $this->view->assign("row", $row);
        $this->view->assign("out_materials", $out_materials);
        $this->view->assign("in_products", $in_products);

        // 打印次数+1
        $this->model->where(['id' => $ids])->setInc('print_num');

        return $this->view->fetch();
    }

    /**
     * 获取商品单位/库存/成本信息 (AJAX)
     * 计划部门页面不展示成本，但后台仍需计算传递（通过隐藏域保存）
     */
    public function getUnit()
    {
        $goods_code = isset($_POST['goods_code']) ? trim($_POST['goods_code']) : '';
        $store = isset($_POST['store']) ? trim($_POST['store']) : '';
        $attr = isset($_POST['attr']) ? trim($_POST['attr']) : '';
        $supplyer_code = isset($_POST['supplyer_code']) ? trim($_POST['supplyer_code']) : '';

        // 先获取原始数据
        $result = \addons\kuerp\library\GoodsPurchase::getUnit($goods_code, $store, $attr, $supplyer_code);
        $data = json_decode($result, true);
        if (empty($data) || !isset($data['unit'])) {
            return $result;
        }

        // 查询库存实际成本, 替换 purchase 字段
        $goods = Db::name('kuerp_goods')->where(['goods_code' => $goods_code])->field('id,goods_code,inventory')->find();
        if ($goods) {
            $goods_id = $goods['id'];
            $where = [];
            $where['goods_code'] = $goods_code;
            if ($store) {
                $where['stores_id'] = $store;
            } else {
                $where['stores_id'] = Db::name('kuerp_goods')->where(['goods_code' => $goods_code])->value('manager_warehouse_id');
            }
            if ($attr) {
                $where['attribute_id'] = $attr;
            }
            $stockInfo = Db::name('kuerp_goods_stock')->where($where)->field('stock,cost_money')->find();

            if ($stockInfo && $stockInfo['stock'] > 0) {
                $unit_cost = round($stockInfo['cost_money'] / $stockInfo['stock'], 4);
            } else {
                $unit_cost = 0;
            }

            // 替换每个单位的 purchase 为库存实际成本（后台计算用，前端隐藏域接收）
            foreach ($data['unit'] as $k => $v) {
                if ($v['is_default'] == 1) {
                    $data['unit'][$k]['purchase'] = $unit_cost;
                    $data['unit'][$k]['tax_included'] = $unit_cost;
                    $data['unit'][$k]['tax_rate'] = 0;
                    $data['unit'][$k]['tax_money'] = 0;
                } else {
                    $data['unit'][$k]['purchase'] = round($unit_cost * $v['basic_nmber'], 4);
                    $data['unit'][$k]['tax_included'] = round($unit_cost * $v['basic_nmber'], 4);
                    $data['unit'][$k]['tax_rate'] = 0;
                    $data['unit'][$k]['tax_money'] = 0;
                }
            }

            // 外协加工单无销售订单关联，扣除所有锁定库存
            $storesId = isset($where['stores_id']) ? (int)$where['stores_id'] : 0;
            $attrId = isset($attr) ? (int)$attr : 0;
            $availableStock = \addons\kuerp\library\Goods::getAvailableStock($goods_id, $storesId, $attrId, '');
            foreach ($data['unit'] as $k => $v) {
                if ($v['is_default'] == 1) {
                    $data['unit'][$k]['stock'] = $availableStock;
                } else {
                    $data['unit'][$k]['stock'] = round($availableStock / $v['basic_nmber'], 2);
                }
            }
        }

        return json_encode($data);
    }
}
