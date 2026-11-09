<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use app\admin\model\kuerp\ProcessLog;
use think\Db;
use Exception;
use think\Session;

/**
 * 外协加工单
 *
 * @icon fa fa-wrench
 */
class Process extends Backend
{
    protected $noNeedRight = ['getunit'];

    /**
     * Process模型对象
     * @var \app\admin\model\kuerp\Process
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Process;
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

                // 补算出库原材料成本（未填成本价时按统一取价规则）
                $this->applyProcessMaterialCost($data);

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
                    // 操作记录
                    ProcessLog::writeLog($result, '添加单据', '新增加工单[' . trim($params['process_code']) . ']');
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
                        // 自动审核模式下同步完成入库成品审核（实际入库仍通过"实际出库/入库"或外协入库填写）
                        try {
                            $exa_in = $this->examineInbound($result, 1);
                        } catch (Exception $e) {
                        }
                        if (!isset($exa_in) || $exa_in['status'] != 1) {
                            $this->error($exa_in['msg']);
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
            if ($examine == 1) {
                // 手动审核模式: 存在实际出库记录或入库成品已审核时禁止编辑
                if ($row['outbound_status'] != 0) {
                    $this->error('不能编辑已有出库记录的数据');
                }
                if ($row['inbound_examine_status'] == 1) {
                    $this->error('入库成品已审核，请先反审核入库成品后再编辑');
                }
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

                // 补算出库原材料成本（未填成本价时按统一取价规则）
                $this->applyProcessMaterialCost($data);

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
                        // 自动模式反审核顺序: 先反入库成品审核, 再反原料出库审核(恢复库存)
                        if ($row['inbound_examine_status'] == 1) {
                            $exa_in = $this->examineInbound($ids, 2);
                            if (!isset($exa_in) || $exa_in['status'] != 1) {
                                throw new Exception($exa_in['msg']);
                            }
                        }
                        $exa_out = $this->examine($ids, 2);
                        if (!isset($exa_out) || $exa_out['status'] != 1) {
                            throw new Exception($exa_out['msg']);
                        }
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
                    // 操作记录
                    ProcessLog::writeLog($ids, '编辑单据', '修改加工单[' . $params['process_code'] . ']');
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
                        // 重新完成入库成品审核
                        try {
                            $exa_in = $this->examineInbound($ids, 1);
                        } catch (Exception $e) {
                        }
                        if (!isset($exa_in) || $exa_in['status'] != 1) {
                            $this->error($exa_in['msg']);
                        }
                    }
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

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
            // DB→表单字段映射
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
                // 手动审核模式: 存在实际出库记录时禁止删除
                if ($examine == 1 && $list_v['outbound_status'] != 0) {
                    $this->error('不能删除已有出库记录的数据');
                }
                if ($list_v['examine_status'] == 1 && $examine == 1) {
                    $this->error('不能删除已审核的数据');
                } elseif ($list_v['examine_status'] == 1 && $examine != 1) {
                    // 自动模式: 先反入库成品审核, 再反原料出库审核(恢复库存)
                    if ($list_v['inbound_examine_status'] == 1) {
                        $exa_in = $this->examineInbound($list_v['id'], 2);
                        if (!isset($exa_in) || $exa_in['status'] != 1) {
                            $this->error($exa_in['msg']);
                        }
                    }
                    $exa_out = $this->examine($list_v['id'], 2);
                    if (!isset($exa_out) || $exa_out['status'] != 1) {
                        $this->error($exa_out['msg']);
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
                    Db::name('kuerp_process_log')->where(['process_id' => $v['id']])->delete();
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
     * 审核: 出库原材料, 扣减库存
     * 反审核: 退回库存 (需 in_status=0)
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

        $serial_set = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');
        $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');

        if ($examine == 1) {
            // ===== 原料出库审核 =====
            $list = $this->model->where(['id' => ['in', $ids], 'examine_status' => '0'])->select();
            foreach ($list as $list_k => $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['process_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
            }
            $process_code = $this->model->where(['id' => ['in', $ids], 'examine_status' => '0'])->column('process_code');
            if (empty($list)) {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }

            // 补算原材料成本（历史单据/尚未计算成本时）
            $this->ensureProcessMaterialCost($process_code);

            $admin = Session::get('admin');

            // 手动审核模式：仅设置审核状态，不做库存变动（实际出库通过 outboundEdit 页面分批填写）
            if ($examine_set == 1) {
                Db::startTrans();
                try {
                    $result = $this->model->where(['id' => ['in', $ids], 'examine_status' => '0'])->update([
                        'examine_status' => 1,
                        'examine_id'     => $admin['id'],
                        'examine_time'   => time(),
                        'operation_id'   => $admin['id'],
                    ]);
                    foreach ($list as $list_v) {
                        ProcessLog::writeLog($list_v['id'], '原料出库审核', '加工单[' . $list_v['process_code'] . ']原料出库审核通过');
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    $this->success(sizeof($list) . '条数据原料出库审核成功');
                } else {
                    $this->error('原料出库审核失败');
                }
            }

            $editstock = [];
            $addstock = [];
            $goods_stock = [];
            $goods_stock_log = [];
            $goods_batch_edit = [];
            $goods_batch_log = [];
            $per_code = [];
            $edit_serial = [];
            $serial_log = [];

            $info_list = Db::name('kuerp_process_info')->where(['process_code' => ['in', $process_code], 'type' => '1'])->select();
            foreach ($info_list as $info_list_k => $info_list_v) {
                $goods = Db::name('kuerp_goods')->where(['id' => $info_list_v['goods_id']])->field('id,goods_code,goods_name,inventory,batch,serial')->find();
                $stock_number = $info_list_v['basic_number'];

                // 商品表总库存扣减
                $status = false;
                foreach ($goods_stock as $goods_stock_k => $goods_stock_v) {
                    if ($goods_stock_v['id'] == $goods['id']) {
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory'] - $stock_number;
                        $status = true;
                    }
                }
                if (!$status) {
                    $goods_stock[] = [
                        'id'        => $goods['id'],
                        'inventory' => $goods['inventory'] - $stock_number,
                    ];
                }

                // 仓库库存扣减
                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                $goods_attr = 0;
                if ($info_list_v['goods_attr'] > 0) {
                    $goods_attr = $info_list_v['goods_attr'];
                    $where['attribute_id'] = $goods_attr;
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if ($stock) {
                    // 检查库存充足
                    if ($stock['stock'] < $stock_number) {
                        $this->error($goods['goods_name'] . '在' . Db::name('kuerp_stores')->where(['id' => $info_list_v['stores_id']])->value('name') . '库存不足');
                    }
                    $status = false;
                    foreach ($editstock as $editstock_k => $editstock_v) {
                        if ($editstock_v['id'] == $stock['id']) {
                            $editstock[$editstock_k]['stock'] -= $stock_number;
                            $editstock[$editstock_k]['cost_money'] = round($editstock_v['cost_money'] - $info_list_v['cost_money'], 4);
                            $status = true;
                        }
                    }
                    if (!$status) {
                        $editstock[] = [
                            'id'         => $stock['id'],
                            'stock'      => round($stock['stock'] - $stock_number, 4),
                            'cost_money' => round($stock['cost_money'] - $info_list_v['cost_money'], 4)
                        ];
                    }
                } else {
                    $this->error($goods['goods_name'] . '库存不足');
                }

                // 收发明细
                $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info_list_v['process_code'],
                    'purchase_id'   => $info_list_v['id'],
                    'purchase_time' => $info_list_v['process_time'],
                    'business_type' => '16',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info_list_v['goods_attr'],
                    'goods_unit'    => (float)$info_list_v['goods_unit'],
                    'stores_id'     => (float)$info_list_v['stores_id'],
                    'remark'        => $info_list_v['remark'],
                    'out_number'    => (float)$info_list_v['number'],
                    'out_basics'    => (float)$stock_number,
                    'out_unit'      => round($info_list_v['unit_cost'], 4),
                    'out_cost'      => (float)$info_list_v['cost_money'],
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);

                // 批次跟踪
                if ($batch_set == 1 && $goods['batch'] == 1) {
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if ($goods_attr > 0) {
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if (empty($goods_batch_stock)) {
                        $this->error($goods['goods_name'] . $info_list_v['batch'] . '批次库存不足');
                    } else {
                        if (isset($goods_batch_edit[$goods_batch_stock['id']])) {
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        } else {
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock'] - $stock_number;
                        }
                        foreach ($goods_batch_edit as $goods_batch_edit_k => $goods_batch_edit_v) {
                            if ($goods_batch_edit_v < 0) {
                                $this->error($goods['goods_name'] . $info_list_v['batch'] . '批次库存不足');
                            }
                        }
                    }

                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'        => $goods['id'],
                        'goods_unit'      => $info_list_v['goods_unit'],
                        'stores_id'       => $info_list_v['stores_id'],
                        'batch_id'        => (float)$info_list_v['batch_id'],
                        'number'          => (float)$stock_number,
                        'purchase_code'   => $info_list_v['process_code'],
                        'purchase_time'   => $info_list_v['process_time'],
                        'business_type'   => '16',
                        'remarks'         => $info_list_v['remark'],
                        'order_id'        => $info_list_v['id'],
                        'unit_price'      => $info_list_v['unit_cost'],
                        'tax_included'    => $info_list_v['unit_cost'],
                        'all_money'       => $info_list_v['cost_money'],
                        'tax_money'       => 0,
                        'money_tax_price' => $info_list_v['cost_money'],
                        'customer_id'     => null,
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]);
                }

                // 序列号跟踪
                if ($goods && $goods['serial'] == 1 && $serial_set == 1 && !empty($info_list_v['period'])) {
                    $period = unserialize($info_list_v['period']);
                    foreach ($period as $period_k => $period_v) {
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial' => $period_v['serial']])->field('serial,status,store_status')->find();
                        if ($per_s) {
                            if ($per_s['store_status'] != 1) {
                                $this->error('序列号' . $period_v['serial'] . '不在库，不可出库');
                            }
                            $edit_serial[] = [
                                'serial'     => $per_s['serial'],
                                'order_id'   => $info_list_v['id'],
                                'type'       => 14,
                                'store_status' => '2',
                                'old_status' => $per_s['status'],
                                'old_store'  => $per_s['store_status']
                            ];
                        } else {
                            $this->error('序列号' . $period_v['serial'] . '不存在');
                        }
                        $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $info_list_v['goods_id'],
                            'stores_id'     => $info_list_v['stores_id'],
                            'attribute_id'  => isset($goods_attr) ? $goods_attr : 0,
                            'serial'        => $period_v['serial'],
                            'business_type' => '16',
                            'store_status'  => '2',
                            'remarks'       => $period_v['remark'],
                            'purchase_id'   => $info_list_v['id'],
                            'purchase_code' => $info_list_v['process_code'],
                            'purchase_time' => $info_list_v['process_time'],
                            'order_id'      => $info_list_v['id'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                    }
                }
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                // 库存更新
                if (!empty($editstock)) {
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if (!empty($addstock)) {
                    foreach ($addstock as $addstock_k => $addstock_v) {
                        $where = [];
                        $where['goods_code'] = $addstock_v['goods_code'];
                        $where['stores_id'] = $addstock_v['stores_id'];
                        if ($addstock_v['attribute_id'] > 0) {
                            $where['attribute_id'] = $addstock_v['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_stock')->where($where)->find();
                        if ($stock) {
                            Db::name('kuerp_goods_stock')->where(['id' => $stock['id']])->inc('stock', $addstock_v['stock'])->update();
                        } else {
                            Db::name('kuerp_goods_stock')->insert($addstock_v);
                        }
                    }
                }
                // 商品总库存
                if (!empty($goods_stock)) {
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                // 收发明细
                if (!empty($goods_stock_log)) {
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                // 批次
                if (!empty($goods_batch_edit)) {
                    foreach ($goods_batch_edit as $k => $v) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $k])->update(['stock' => $v]);
                    }
                }
                if (!empty($goods_batch_log)) {
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
                }
                // 序列号
                if (!empty($edit_serial)) {
                    foreach ($edit_serial as $edit_serial_k => $edit_serial_v) {
                        Db::name('kuerp_goods_serial')->where(['serial' => $edit_serial_v['serial']])->update([
                            'order_id'     => $edit_serial_v['order_id'],
                            'status'       => $edit_serial_v['type'],
                            'store_status' => $edit_serial_v['store_status'],
                            'old_status'   => $edit_serial_v['old_status'],
                            'old_store'    => $edit_serial_v['old_store']
                        ]);
                    }
                }
                if (!empty($serial_log)) {
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                }

                $result = $this->model->where(['id' => ['in', $ids], 'examine_status' => '0'])->update([
                    'examine_status'  => 1,
                    'examine_id'      => $admin['id'],
                    'examine_time'    => time(),
                    'operation_id'    => $admin['id'],
                    'in_status'       => '0',
                    'outbound_status' => 2,
                    'outbound_time'   => time(),
                ]);
                Db::commit();
                // 自动模式审核即全部出库: 回写明细已出库数量 + 操作记录
                Db::name('kuerp_process_info')->where(['process_code' => ['in', $process_code], 'type' => '1'])->update(['out_number' => Db::raw('number')]);
                foreach ($list as $list_v) {
                    ProcessLog::writeLog($list_v['id'], '原料出库审核', '加工单[' . $list_v['process_code'] . ']原料出库审核通过(自动出库全部原材料)');
                }
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if (isset($result) && $result !== false) {
                if ($examine_set != 1) {
                    return ['status' => 1, 'msg' => '操作成功'];
                }
                $this->success(sizeof($list) . '条数据原料出库审核成功');
            } else {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('原料出库审核失败');
            }

        } else {
            // ===== 原料出库反审核 =====
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
                if ($list_v['inbound_examine_status'] == 1) {
                    $msg = '入库成品已审核，请先反审核入库成品';
                    if ($examine_set != 1) {
                        return ['status' => 0, 'msg' => $msg];
                    }
                    $this->error($msg);
                }
                // 手动模式存在分批出库记录时不可反审核
                if ($examine_set == 1 && $list_v['outbound_status'] != 0) {
                    $this->error('加工单' . $list_v['process_code'] . '已有出库记录，不可反审核');
                }
            }
            $process_code = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->column('process_code');
            if (empty($list)) {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('请选择需要反审核的数据');
            }

            $admin = Session::get('admin');

            // 手动审核模式：仅反转审核状态，不做库存变动（上方已校验无任何实际出库）
            if ($examine_set == 1) {
                Db::startTrans();
                try {
                    $result = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->update([
                        'examine_status' => 0,
                        'examine_id'     => 0,
                        'examine_time'   => '',
                        'operation_id'   => $admin['id'],
                    ]);
                    foreach ($list as $list_v) {
                        ProcessLog::writeLog($list_v['id'], '原料出库反审核', '加工单[' . $list_v['process_code'] . ']原料出库反审核');
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    $this->success(sizeof($list) . '条数据原料出库反审核成功');
                } else {
                    $this->error('原料出库反审核失败');
                }
            }

            $editstock = [];
            $goods_stock = [];
            $goods_batch_edit = [];
            $info_list = Db::name('kuerp_process_info')->where(['process_code' => ['in', $process_code], 'type' => '1'])->select();

            foreach ($info_list as $info_list_k => $info_list_v) {
                $goods = Db::name('kuerp_goods')->where(['id' => $info_list_v['goods_id']])->field('id,goods_code,goods_name,inventory,batch,serial')->find();
                $stock_number = $info_list_v['basic_number'];

                // 商品总库存恢复
                $status = false;
                foreach ($goods_stock as $goods_stock_k => $goods_stock_v) {
                    if ($goods_stock_v['id'] == $goods['id']) {
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory'] + $stock_number;
                        $status = true;
                    }
                }
                if (!$status) {
                    $goods_stock[] = [
                        'id'        => $goods['id'],
                        'inventory' => $goods['inventory'] + $stock_number,
                    ];
                }

                // 仓库库存恢复
                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                $goods_attr = 0;
                if ($info_list_v['goods_attr'] > 0) {
                    $goods_attr = $info_list_v['goods_attr'];
                    $where['attribute_id'] = $goods_attr;
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if ($stock) {
                    $status = false;
                    foreach ($editstock as $editstock_k => $editstock_v) {
                        if ($editstock_v['id'] == $stock['id']) {
                            $editstock[$editstock_k]['stock'] += $stock_number;
                            $editstock[$editstock_k]['cost_money'] = round($editstock_v['cost_money'] + $info_list_v['cost_money'], 4);
                            $status = true;
                        }
                    }
                    if (!$status) {
                        $editstock[] = [
                            'id'         => $stock['id'],
                            'stock'      => round($stock['stock'] + $stock_number, 4),
                            'cost_money' => round($stock['cost_money'] + $info_list_v['cost_money'], 4)
                        ];
                    }
                }

                // 批次恢复
                if ($batch_set == 1 && $goods['batch'] == 1) {
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if ($goods_attr > 0) {
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if (!empty($goods_batch_stock)) {
                        if (isset($goods_batch_edit[$goods_batch_stock['id']])) {
                            $goods_batch_edit[$goods_batch_stock['id']] += $stock_number;
                        } else {
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock'] + $stock_number;
                        }
                    }
                }
            }

            $info_ids = Db::name('kuerp_process_info')->where(['process_code' => ['in', $process_code], 'type' => '1'])->column('id');
            $admin = Session::get('admin');

            Db::startTrans();
            try {
                if (!empty($editstock)) {
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if (!empty($goods_stock)) {
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if (!empty($goods_batch_edit)) {
                    foreach ($goods_batch_edit as $k => $v) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $k])->update(['stock' => $v]);
                    }
                }

                // 删除出库日志
                Db::name('kuerp_goods_log')->where(['business_type' => '16', 'purchase_id' => ['in', $info_ids]])->delete();
                Db::name('kuerp_goods_batch_log')->where(['business_type' => '16', 'order_id' => ['in', $info_ids]])->delete();

                // 恢复序列号
                $serial = Db::name('kuerp_goods_serial')->where(['status' => '14', 'order_id' => ['in', $info_ids]])->select();
                foreach ($serial as $serial_k => $serial_v) {
                    Db::name('kuerp_goods_serial')->where(['id' => $serial_v['id']])->update([
                        'status'       => $serial_v['old_status'],
                        'store_status' => $serial_v['old_store']
                    ]);
                }
                Db::name('kuerp_goods_serial_log')->where(['business_type' => '16', 'order_id' => ['in', $info_ids]])->delete();

                $result = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->update([
                    'examine_status'  => 0,
                    'examine_id'      => 0,
                    'examine_time'    => '',
                    'operation_id'    => $admin['id'],
                    'outbound_status' => 0,
                    'outbound_time'   => null,
                ]);
                Db::commit();
                // 自动模式反审核: 恢复明细已出库数量 + 操作记录
                Db::name('kuerp_process_info')->where(['process_code' => ['in', $process_code], 'type' => '1'])->update(['out_number' => 0]);
                foreach ($list as $list_v) {
                    ProcessLog::writeLog($list_v['id'], '原料出库反审核', '加工单[' . $list_v['process_code'] . ']原料出库反审核(自动退回全部原材料)');
                }
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if (isset($result) && $result !== false) {
                if ($examine_set != 1) {
                    return ['status' => 1, 'msg' => '操作成功'];
                }
                $this->success(sizeof($list) . '条数据原料出库反审核成功');
            } else {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('原料出库反审核失败');
            }
        }
    }

    /**
     * 入库成品审核/反审核
     * 审核: 需先完成原料出库审核, 仅更新状态 (实际入库通过 outboundEdit 页面分批填写)
     * 反审核: 需无任何实际入库记录
     */
    /**
     * 补算出库原材料成本（未填写成本价时按统一取价规则）
     * 成品(type=2)的成本在入库时按「原材料成本 + 加工费」计算，不在此处理。
     *
     * @param array $rows 加工单明细行（引用）
     * @return void
     */
    private function applyProcessMaterialCost(array &$rows)
    {
        foreach ($rows as $k => $row) {
            if (!isset($row['type']) || $row['type'] != 1) {
                continue;
            }
            if ((float)$row['unit_cost'] > 0) {
                continue;
            }
            $price = \addons\kuerp\library\Goods::getCostPrice(
                isset($row['goods_code']) ? $row['goods_code'] : '',
                isset($row['stores_id']) ? $row['stores_id'] : 0,
                isset($row['goods_attr']) ? $row['goods_attr'] : 0
            );
            $rows[$k]['unit_cost'] = round($price, 4);
            $rows[$k]['cost_money'] = round($price * (float)$row['basic_number'], 4);
        }
    }

    /**
     * 补算已保存加工单的原材料成本（历史单据或尚未计算成本时）
     *
     * @param array|string $processCodes 加工单号
     * @return void
     */
    private function ensureProcessMaterialCost($processCodes)
    {
        $codes = is_array($processCodes) ? $processCodes : [$processCodes];
        $codes = array_values(array_filter($codes, function ($v) {
            return $v !== '' && $v !== null;
        }));
        if (empty($codes)) {
            return;
        }
        $rows = Db::name('kuerp_process_info')
            ->where(['process_code' => ['in', $codes], 'type' => '1'])->select();
        foreach ($rows as $row) {
            if ((float)$row['unit_cost'] > 0) {
                if ((float)$row['cost_money'] <= 0) {
                    Db::name('kuerp_process_info')->where(['id' => $row['id']])->update([
                        'cost_money' => round((float)$row['unit_cost'] * (float)$row['basic_number'], 4),
                    ]);
                }
                continue;
            }
            $price = \addons\kuerp\library\Goods::getCostPrice($row['goods_code'], $row['stores_id'], $row['goods_attr']);
            if ($price <= 0) {
                continue;
            }
            Db::name('kuerp_process_info')->where(['id' => $row['id']])->update([
                'unit_cost'  => round($price, 4),
                'cost_money' => round($price * (float)$row['basic_number'], 4),
            ]);
        }
    }

    public function examineInbound($ids = null, $examine = 1)
    {
        $examine_set = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');
        if (empty($ids)) {
            if ($examine_set != 1) {
                return ['status' => 0, 'msg' => '系统错误'];
            }
            $this->error('请选择要操作的数据');
        }

        if ($examine == 1) {
            // ===== 入库成品审核 =====
            $list = $this->model->where(['id' => ['in', $ids], 'inbound_examine_status' => '0'])->select();
            foreach ($list as $list_k => $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['process_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
                // 必须先完成原料出库审核
                if ($list_v['examine_status'] != 1) {
                    $msg = '加工单' . $list_v['process_code'] . '未进行原料出库审核，请先进行原料出库审核';
                    if ($examine_set != 1) {
                        return ['status' => 0, 'msg' => $msg];
                    }
                    $this->error($msg);
                }
                // 已有实际入库记录时不可再审核
                if ($list_v['in_status'] != 0) {
                    $msg = '加工单' . $list_v['process_code'] . '已有入库记录，无需入库审核';
                    if ($examine_set != 1) {
                        return ['status' => 0, 'msg' => $msg];
                    }
                    $this->error($msg);
                }
            }
            if (empty($list)) {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '系统错误'];
                }
                $this->error('请选择需要入库成品审核的数据');
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id' => ['in', $ids], 'inbound_examine_status' => '0'])->update([
                    'inbound_examine_status' => 1,
                    'inbound_examine_id'     => $admin['id'],
                    'inbound_examine_time'   => time(),
                    'operation_id'           => $admin['id'],
                ]);
                foreach ($list as $list_v) {
                    ProcessLog::writeLog($list_v['id'], '入库成品审核', '加工单[' . $list_v['process_code'] . ']入库成品审核通过');
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
                $this->success(sizeof($list) . '条数据入库成品审核成功');
            } else {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('入库成品审核失败');
            }

        } else {
            // ===== 入库成品反审核 =====
            $list = $this->model->where(['id' => ['in', $ids], 'inbound_examine_status' => '1'])->select();
            foreach ($list as $list_k => $list_v) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['process_time']);
                if ($checkout !== true) {
                    $this->error($checkout);
                }
                // 已有实际入库记录时不可反审核
                if ($list_v['in_status'] != 0) {
                    $msg = '加工单' . $list_v['process_code'] . '已有入库记录，不可反审核入库成品';
                    if ($examine_set != 1) {
                        return ['status' => 0, 'msg' => $msg];
                    }
                    $this->error($msg);
                }
            }
            if (empty($list)) {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '系统错误'];
                }
                $this->error('请选择需要入库成品反审核的数据');
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id' => ['in', $ids], 'inbound_examine_status' => '1'])->update([
                    'inbound_examine_status' => 0,
                    'inbound_examine_id'     => 0,
                    'inbound_examine_time'   => null,
                    'operation_id'           => $admin['id'],
                ]);
                foreach ($list as $list_v) {
                    ProcessLog::writeLog($list_v['id'], '入库成品反审核', '加工单[' . $list_v['process_code'] . ']入库成品反审核');
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
                $this->success(sizeof($list) . '条数据入库成品反审核成功');
            } else {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('入库成品反审核失败');
            }
        }
    }

    /**
     * 实际出库/入库编辑页面
     * 原材料出库需先"原料出库审核"; 成品入库需先"入库成品审核"
     */
    public function outboundEdit($ids = null)
    {
        $ids = $ids ? $ids : $this->request->get('ids');
        if (empty($ids)) {
            $this->error('参数错误');
        }
        $row = $this->model->where(['id' => $ids])->find();
        if (!$row) {
            $this->error('加工单不存在');
        }
        if ($row['examine_status'] != 1) {
            $this->error('请先进行原料出库审核');
        }

        // 获取详情
        $info_list = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $row['process_code']])
            ->field('a.*,g.goods_name,g.goods_specification_id,g.vendor_name,g.packaging,g.purchase as goods_purchase')
            ->order('a.type', 'ASC')
            ->select();
        foreach ($info_list as &$v) {
            $v['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $v['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
            // 入库成本默认值 = 商品档案的「预估成本」(基本单位)，用户可在弹窗内直接修改
            $v['estimate_cost'] = (float)$v['goods_purchase'];
            // 属性名称
            $v['attr_name'] = '';
            if (!empty($v['goods_attr'])) {
                $attr = Db::name('kuerp_goods_attr')->alias('ga')
                    ->join('kuerp_aux_combination ac', 'ac.id=ga.combination_id')
                    ->join('kuerp_auxiliary a', 'a.id=ac.auxiliary_id')
                    ->where(['ga.goods_id' => $v['goods_id'], 'ga.id' => $v['goods_attr']])
                    ->field('a.attr_name,ga.purchase as attr_purchase')
                    ->find();
                $v['attr_name'] = $attr ? $attr['attr_name'] : '';
                // 辅助属性商品优先取该属性的预估成本（为 0 时回退商品档案）
                if ($attr && (float)$attr['attr_purchase'] > 0) {
                    $v['estimate_cost'] = (float)$attr['attr_purchase'];
                }
            }
        }
        unset($v);

        // 检查是否已完成全部出库和入库（基于实际数量）
        $all_out_done = true;
        $all_in_done = true;
        foreach ($info_list as $v) {
            if ($v['type'] == '1' && $v['out_number'] < $v['number']) {
                $all_out_done = false;
            }
            if ($v['type'] == '2' && $v['in_number'] < $v['number']) {
                $all_in_done = false;
            }
        }
        if ($all_out_done && $all_in_done) {
            $this->error('该单据已完成全部出库和入库');
        }

        // 分离原材料(type=1)和成品(type=2)
        $out_items = []; // 原材料(出库)
        $in_items = [];  // 成品(入库)
        foreach ($info_list as $v) {
            if ($v['type'] == '1') {
                $out_items[] = $v;
            } else {
                $in_items[] = $v;
            }
        }

        // 出入库记录（每次分批出/入库一行，取自商品收发明细 16外协出库/17外协入库）
        $log_list = Db::name('kuerp_goods_log')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.purchase_code' => $row['process_code'], 'a.business_type' => ['in', '16,17']])
            ->field('a.*,g.goods_name,g.goods_specification_id,g.vendor_name,g.packaging')
            ->order('a.createtime DESC, a.id DESC')
            ->select();
        foreach ($log_list as &$lv) {
            $lv['is_out'] = $lv['business_type'] == 16;
            $lv['stores_name'] = Db::name('kuerp_stores')->where(['id' => $lv['stores_id']])->value('name');
            $lv['admin_name'] = Db::name('admin')->where(['id' => $lv['admin_id']])->value('nickname');
        }
        unset($lv);

        $this->view->assign("row", $row);
        $this->view->assign("out_items", $out_items);
        $this->view->assign("in_items", $in_items);
        $this->view->assign("log_list", $log_list);
        $this->view->assign("supplier_name", Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->value('supplyer_name'));
        $this->view->assign("accountsList", $this->model->getAccountsList());
        $this->view->assign("expendTypeList", $this->model->getExpendTypeList());
        $this->assignconfig("processId", $row['id']);
        $this->assignconfig("processCode", $row['process_code']);
        $this->assignconfig("outboundStatus", $row['outbound_status']);
        $this->assignconfig("inboundExamineStatus", $row['inbound_examine_status']);

        return $this->view->fetch('outbound_edit');
    }

    /**
     * 保存实际出库/入库 (分批操作, 每次填写本次数量)
     * out_data: [info_id => out_number] 原材料本次出库数量 (type=1)
     * in_data:  [info_id => [in_number, unit_cost, stores_id]] 成品本次入库 (type=2)
     * batch_data: [info_id => [batch, manufacture, guarantee, validity]] 成品入库批次信息
     */
    public function saveOutbound($ids = null)
    {
        $ids = $ids ? $ids : $this->request->post('ids');
        if (empty($ids)) {
            $this->error('参数错误');
        }
        $row = $this->model->where(['id' => $ids])->find();
        if (!$row) {
            $this->error('加工单不存在');
        }
        if ($row['examine_status'] != 1) {
            $this->error('请先进行原料出库审核');
        }

        $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['process_time']);
        if ($checkout !== true) {
            $this->error($checkout);
        }

        // 补算原材料成本（历史单据/尚未计算成本时）
        $this->ensureProcessMaterialCost($row['process_code']);

        $serial_set = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');
        $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');
        $admin = Session::get('admin');

        // 接收原材料实际出库数量 / 成品实际入库数据
        $out_data = $this->request->post('out_data/a');     // [info_id => out_number]
        $in_data = $this->request->post('in_data/a');       // [info_id => [in_number, unit_cost, stores_id]]
        $batch_data_map = $this->request->post('batch_data/a'); // [info_id => [batch, manufacture, guarantee, validity]]
        $fee_data = $this->request->post('fee_data/a');     // [total_process_fee, account_id, expend_type_id, fee_time, remark]

        $has_out = false;
        $has_in = false;

        // 操作顺序校验：原材料出库需要 examine_status==1（原料出库审核通过）
        if (!empty($out_data) && $row['examine_status'] != 1) {
            $this->error('请先进行原料出库审核，再填写实际出库数量');
        }
        // 操作顺序校验：成品入库需要 inbound_examine_status==1（入库成品审核通过）
        if (!empty($in_data) && $row['inbound_examine_status'] != 1) {
            $this->error('请先进行入库成品审核，再填写实际入库数量（操作顺序：先原料出库审核 → 再入库成品审核）');
        }

        // 加工费结算（可选，原"外协入库"的加工费功能并入此处）：金额>0 时生成费用记录并扣减账户余额
        $total_process_fee = 0;
        $account_id = 0;
        $expend_type_id = 0;
        $fee_time = '';
        $fee_remark = '';
        if (!empty($fee_data) && isset($fee_data['total_process_fee']) && (float)$fee_data['total_process_fee'] > 0) {
            $total_process_fee = round((float)$fee_data['total_process_fee'], 2);
            $account_id = isset($fee_data['account_id']) ? (int)$fee_data['account_id'] : 0;
            $expend_type_id = isset($fee_data['expend_type_id']) ? (int)$fee_data['expend_type_id'] : 0;
            $fee_time = isset($fee_data['fee_time']) ? trim($fee_data['fee_time']) : '';
            $fee_remark = isset($fee_data['remark']) ? trim($fee_data['remark']) : '';
            if (empty($account_id)) {
                $this->error('请选择结算账户');
            }
            if (empty($expend_type_id)) {
                $this->error('请选择支出类别');
            }
            if (empty($fee_time)) {
                $this->error('请输入费用日期');
            }
        }

        // ===== 处理原材料出库（type=1）=====
        $editstock_out = [];
        $goods_stock_out = [];
        $goods_stock_log_out = [];
        $goods_batch_edit_out = [];
        $goods_batch_log_out = [];
        $edit_serial_out = [];
        $serial_log_out = [];
        $update_out_info = [];
        $out_log_names = [];

        if (!empty($out_data) && $row['outbound_status'] != 2) {
            foreach ($out_data as $info_id => $out_number) {
                $out_number = (float)$out_number;
                if ($out_number <= 0) {
                    continue;
                }
                $info = Db::name('kuerp_process_info')->where(['id' => $info_id, 'type' => '1', 'process_code' => $row['process_code']])->find();
                if (!$info) {
                    continue;
                }
                // 本次可出库上限 = 计划数量 - 已出库数量
                $max_out = $info['number'] - $info['out_number'];
                if ($out_number > $max_out) {
                    $this->error('原材料实际出库数量不能超过' . $max_out);
                }

                $has_out = true;
                $goods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->field('id,goods_code,goods_name,inventory,batch,serial')->find();
                // 按比例折算基本数量与成本
                $basic_number = round(($info['basic_number'] / max($info['number'], 0.0001)) * $out_number, 4);
                $unit_cost = (float)$info['unit_cost'];
                if ($unit_cost <= 0) {
                    // 单据未填成本价时按统一取价规则补算
                    $unit_cost = \addons\kuerp\library\Goods::getCostPrice($info['goods_code'], $info['stores_id'], $info['goods_attr']);
                }
                $cost_money = round($unit_cost * $basic_number, 4);

                // 商品表库存扣减
                $status = false;
                foreach ($goods_stock_out as $gk => $gv) {
                    if ($gv['id'] == $goods['id']) {
                        $goods_stock_out[$gk]['inventory'] = $gv['inventory'] - $basic_number;
                        $status = true;
                    }
                }
                if (!$status) {
                    $goods_stock_out[] = [
                        'id'        => $goods['id'],
                        'inventory' => $goods['inventory'] - $basic_number,
                    ];
                }
                // 检查库存充足
                foreach ($goods_stock_out as $gv) {
                    if ($gv['inventory'] < 0) {
                        $this->error($goods['goods_name'] . '库存不足');
                    }
                }

                // 仓库库存扣减
                $where = [];
                $where['goods_code'] = $info['goods_code'];
                $where['stores_id'] = $info['stores_id'];
                $goods_attr = 0;
                if ($info['goods_attr'] > 0) {
                    $goods_attr = $info['goods_attr'];
                    $where['attribute_id'] = $goods_attr;
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if ($stock) {
                    if ($stock['stock'] < $basic_number) {
                        $this->error($goods['goods_name'] . '在' . Db::name('kuerp_stores')->where(['id' => $info['stores_id']])->value('name') . '库存不足');
                    }
                    $status = false;
                    foreach ($editstock_out as $ek => $ev) {
                        if ($ev['id'] == $stock['id']) {
                            $editstock_out[$ek]['stock'] -= $basic_number;
                            $editstock_out[$ek]['cost_money'] = round($ev['cost_money'] - $cost_money, 4);
                            $status = true;
                        }
                    }
                    if (!$status) {
                        $editstock_out[] = [
                            'id'         => $stock['id'],
                            'stock'      => round($stock['stock'] - $basic_number, 4),
                            'cost_money' => round($stock['cost_money'] - $cost_money, 4)
                        ];
                    }
                } else {
                    $this->error($goods['goods_name'] . '库存不足');
                }

                // 收发明细
                $goods_stock_log_out[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info['process_code'],
                    'purchase_id'   => $info['id'],
                    'purchase_time' => $info['process_time'],
                    'business_type' => '16',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info['goods_attr'],
                    'goods_unit'    => (float)$info['goods_unit'],
                    'stores_id'     => (float)$info['stores_id'],
                    'remark'        => $info['remark'],
                    'out_number'    => $out_number,
                    'out_basics'    => $basic_number,
                    'out_unit'      => round($unit_cost, 4),
                    'out_cost'      => $cost_money,
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);

                // 批次跟踪
                if ($batch_set == 1 && $goods['batch'] == 1) {
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if ($goods_attr > 0) {
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info['stores_id'];
                    $where['batch_id'] = $info['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if (empty($goods_batch_stock)) {
                        $this->error($goods['goods_name'] . $info['batch'] . '批次库存不足');
                    } else {
                        if (isset($goods_batch_edit_out[$goods_batch_stock['id']])) {
                            $goods_batch_edit_out[$goods_batch_stock['id']] -= $basic_number;
                        } else {
                            $goods_batch_edit_out[$goods_batch_stock['id']] = $goods_batch_stock['stock'] - $basic_number;
                        }
                        foreach ($goods_batch_edit_out as $bv) {
                            if ($bv < 0) {
                                $this->error($goods['goods_name'] . $info['batch'] . '批次库存不足');
                            }
                        }
                    }
                    $goods_batch_log_out[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'        => $goods['id'],
                        'goods_unit'      => $info['goods_unit'],
                        'stores_id'       => $info['stores_id'],
                        'batch_id'        => (float)$info['batch_id'],
                        'number'          => $basic_number,
                        'purchase_code'   => $info['process_code'],
                        'purchase_time'   => $info['process_time'],
                        'business_type'   => '16',
                        'remarks'         => $info['remark'],
                        'order_id'        => $info['id'],
                        'unit_price'      => $unit_cost,
                        'tax_included'    => $unit_cost,
                        'all_money'       => $cost_money,
                        'tax_money'       => 0,
                        'money_tax_price' => $cost_money,
                        'customer_id'     => null,
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]);
                }

                // 序列号跟踪 (按未出库序列号顺序出库)
                if ($goods && $goods['serial'] == 1 && $serial_set == 1 && !empty($info['period'])) {
                    $period = unserialize($info['period']);
                    $out_count = 0;
                    foreach ($period as $period_k => $period_v) {
                        if ($out_count >= $out_number) break;
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial' => $period_v['serial']])->field('serial,status,store_status')->find();
                        if (!$per_s) {
                            $this->error($goods['goods_name'] . '序列号' . $period_v['serial'] . '不存在');
                        }
                        if ($per_s['store_status'] != 1) {
                            continue; // 已出库的序列号跳过
                        }
                        $edit_serial_out[] = ['serial' => $per_s['serial'], 'order_id' => $info['id'], 'type' => 14, 'store_status' => '2', 'old_status' => $per_s['status'], 'old_store' => $per_s['store_status']];
                        $serial_log_out[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $info['goods_id'],
                            'stores_id'     => $info['stores_id'],
                            'attribute_id'  => isset($goods_attr) ? $goods_attr : 0,
                            'serial'        => $period_v['serial'],
                            'business_type' => '16',
                            'store_status'  => '2',
                            'remarks'       => $period_v['remark'],
                            'purchase_id'   => $info['id'],
                            'purchase_code' => $info['process_code'],
                            'purchase_time' => $info['process_time'],
                            'order_id'      => $info['id'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                        $out_count++;
                    }
                }

                $update_out_info[] = [
                    'id'         => $info_id,
                    'out_number' => round($info['out_number'] + $out_number, 4),
                ];
                $out_log_names[] = $goods['goods_name'] . '×' . $out_number;
            }
        }

        // ===== 处理成品入库（type=2）=====
        $editstock_in = [];
        $addstock_in = [];
        $goods_stock_in = [];
        $goods_stock_log_in = [];
        $goods_batch_add_in = [];
        $goods_batch_edit_in = [];
        $goods_batch_log_in = [];
        $update_in_info = [];
        $total_in_number = 0;
        $in_log_names = [];

        // 本批成品入库成本 = 分摊的原材料成本 + 本批加工费（手工填写入库单价时以手填为准）
        $materialTotal = 0;
        $planInNumber = 0;
        $totalInNumberThis = 0;
        if (!empty($in_data)) {
            $materialTotal = (float)Db::name('kuerp_process_info')
                ->where(['process_code' => $row['process_code'], 'type' => '1'])->sum('cost_money');
            $planInNumber = (float)Db::name('kuerp_process_info')
                ->where(['process_code' => $row['process_code'], 'type' => '2'])->sum('number');
            foreach ($in_data as $r) {
                $totalInNumberThis += max(0, (float)(isset($r['in_number']) ? $r['in_number'] : 0));
            }
        }

        if (!empty($in_data)) {
            foreach ($in_data as $info_id => $in_row) {
                $in_number = isset($in_row['in_number']) ? (float)$in_row['in_number'] : 0;
                $unit_cost = isset($in_row['unit_cost']) ? (float)$in_row['unit_cost'] : 0;
                if ($in_number <= 0) {
                    continue;
                }
                $info = Db::name('kuerp_process_info')->where(['id' => $info_id, 'type' => '2', 'process_code' => $row['process_code']])->find();
                if (!$info) {
                    continue;
                }
                // 本次可入库上限 = 计划数量 - 已入库数量
                $max_in = $info['number'] - $info['in_number'];
                if ($in_number > $max_in) {
                    $this->error('成品实际入库数量不能超过' . $max_in);
                }

                $has_in = true;
                $total_in_number += $in_number;
                $goods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->field('id,goods_code,goods_name,inventory,batch,serial')->find();
                // 按比例折算基本数量
                $basic_number = round(($info['basic_number'] / max($info['number'], 0.0001)) * $in_number, 4);
                // 本批原材料成本：按计划入库数量比例分摊（最后一批吃尾差）
                $line_material = $planInNumber > 0 ? $materialTotal * ((float)$info['number'] / $planInNumber) : 0;
                $already_number = (float)$info['in_number'];
                if ($info['number'] > 0 && $already_number + $in_number >= $info['number'] - 0.0001) {
                    $batch_material = round($line_material * (1 - $already_number / $info['number']), 4);
                } else {
                    $batch_material = round($line_material * ($in_number / max($info['number'], 0.0001)), 4);
                }
                // 本批加工费：按本批入库数量占本次提交总入库数量的比例分摊
                $batch_fee = ($total_process_fee > 0 && $totalInNumberThis > 0)
                    ? round($total_process_fee * ($in_number / $totalInNumberThis), 4) : 0;
                // 入库成本默认由页面填入商品档案的「预估成本」并允许用户修改，填了（含 0）就以填写值为准；
                // 仅当该字段未提交/被清空时，才回退到自动计算（分摊原材料成本 + 本批加工费）
                $cost_submitted = isset($in_row['unit_cost']) && trim((string)$in_row['unit_cost']) !== '';
                if ($cost_submitted) {
                    $cost_money = round($unit_cost * $basic_number, 4);
                } else {
                    $cost_money = round($batch_material + $batch_fee, 4);
                    $unit_cost = $basic_number > 0 ? round($cost_money / $basic_number, 4) : 0;
                }
                // 优先使用提交的仓库，未选则用计划仓库
                $stores_id = isset($in_row['stores_id']) && !empty($in_row['stores_id']) ? (float)$in_row['stores_id'] : (float)$info['stores_id'];

                // 商品表库存增加
                $status = false;
                foreach ($goods_stock_in as $gk => $gv) {
                    if ($gv['id'] == $goods['id']) {
                        $goods_stock_in[$gk]['inventory'] = $gv['inventory'] + $basic_number;
                        $status = true;
                    }
                }
                if (!$status) {
                    $goods_stock_in[] = [
                        'id'        => $goods['id'],
                        'inventory' => $goods['inventory'] + $basic_number,
                    ];
                }

                // 仓库库存增加
                $where = [];
                $where['goods_code'] = $info['goods_code'];
                $where['stores_id'] = $stores_id;
                $goods_attr = 0;
                if ($info['goods_attr'] > 0) {
                    $goods_attr = $info['goods_attr'];
                    $where['attribute_id'] = $goods_attr;
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if ($stock) {
                    $status = false;
                    foreach ($editstock_in as $ek => $ev) {
                        if ($ev['id'] == $stock['id']) {
                            $editstock_in[$ek]['stock'] += $basic_number;
                            $editstock_in[$ek]['cost_money'] = round($ev['cost_money'] + $cost_money, 4);
                            $status = true;
                        }
                    }
                    if (!$status) {
                        $editstock_in[] = [
                            'id'         => $stock['id'],
                            'stock'      => round($stock['stock'] + $basic_number, 4),
                            'cost_money' => round($stock['cost_money'] + $cost_money, 4)
                        ];
                    }
                } else {
                    $addstock_in[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'     => $goods['id'],
                        'goods_code'   => $info['goods_code'],
                        'attribute_id' => $goods_attr,
                        'stores_id'    => $stores_id,
                        'stock'        => $basic_number,
                        'cost_money'   => $cost_money,
                        'createtime'   => time(),
                        'updatetime'   => time(),
                    ]);
                }

                // 收发明细
                $goods_stock_log_in[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info['process_code'],
                    'purchase_id'   => $info['id'],
                    'purchase_time' => $info['process_time'],
                    'business_type' => '17',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info['goods_attr'],
                    'goods_unit'    => (float)$info['goods_unit'],
                    'stores_id'     => (float)$stores_id,
                    'remark'        => $info['remark'],
                    'enter_number'  => $in_number,
                    'enter_basics'  => $basic_number,
                    'enter_unit'    => round($unit_cost, 4),
                    'enter_cost'    => $cost_money,
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);

                // 批次处理 (优先取页面填写的批次; 未填写时沿用单据行已有批次)
                $batch_data = isset($batch_data_map[$info_id]) ? $batch_data_map[$info_id] : [];
                if ($batch_set == 1 && $goods['batch'] == 1 && empty($batch_data['batch']) && !empty($info['batch_id'])) {
                    $batch_row = Db::name('kuerp_goods_batch')->where(['id' => $info['batch_id']])->find();
                    if ($batch_row) {
                        $batch_data = [
                            'batch_id' => $batch_row['id'],
                            'batch'    => $batch_row['batch_name'],
                        ];
                    }
                }
                if ($batch_set == 1 && $goods['batch'] == 1 && !empty($batch_data['batch'])) {
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if ($goods_attr > 0) {
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $stores_id;
                    $where['batch_id'] = isset($batch_data['batch_id']) ? $batch_data['batch_id'] : 0;
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if (empty($goods_batch_stock)) {
                        $batch_id = isset($batch_data['batch_id']) ? (float)$batch_data['batch_id'] : 0;
                        if (empty($batch_id)) {
                            $batch_id = Db::name('kuerp_goods_batch')->insertGetId(\addons\kuerp\library\Goods::setDataInfo([
                                'goods_id'     => $goods['id'],
                                'goods_code'   => $goods['goods_code'],
                                'attribute_id' => $goods_attr,
                                'stores_id'    => $stores_id,
                                'batch_name'   => $batch_data['batch'],
                                'manufacture'  => isset($batch_data['manufacture']) && $batch_data['manufacture'] !== '' ? strtotime($batch_data['manufacture']) : time(),
                                'guarantee'    => isset($batch_data['guarantee']) && $batch_data['guarantee'] !== '' ? (float)$batch_data['guarantee'] : 0,
                                'validity'     => isset($batch_data['validity']) && $batch_data['validity'] !== '' ? strtotime($batch_data['validity']) : time(),
                                'createtime'   => time(),
                                'updatetime'   => time(),
                            ]));
                        }
                        $goods_batch_add_in[] = \addons\kuerp\library\Goods::setDataInfo([
                            'batch_id'     => $batch_id,
                            'goods_id'     => $goods['id'],
                            'goods_code'   => $goods['goods_code'],
                            'attribute_id' => $goods_attr,
                            'stores_id'    => $stores_id,
                            'stock'        => $basic_number,
                            'createtime'   => time(),
                            'updatetime'   => time(),
                        ]);
                    } else {
                        if (isset($goods_batch_edit_in[$goods_batch_stock['id']])) {
                            $goods_batch_edit_in[$goods_batch_stock['id']] += $basic_number;
                        } else {
                            $goods_batch_edit_in[$goods_batch_stock['id']] = $goods_batch_stock['stock'] + $basic_number;
                        }
                    }

                    $goods_batch_log_in[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'        => $goods['id'],
                        'goods_unit'      => $info['goods_unit'],
                        'stores_id'       => $stores_id,
                        'batch_id'        => (float)(isset($batch_data['batch_id']) ? $batch_data['batch_id'] : 0),
                        'number'          => $basic_number,
                        'purchase_code'   => $info['process_code'],
                        'purchase_time'   => $info['process_time'],
                        'business_type'   => '17',
                        'remarks'         => $info['remark'],
                        'order_id'        => $info['id'],
                        'unit_price'      => $unit_cost,
                        'tax_included'    => $unit_cost,
                        'all_money'       => $cost_money,
                        'tax_money'       => 0,
                        'money_tax_price' => $cost_money,
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]);
                }

                $update_in_info[] = [
                    'id'          => $info_id,
                    'in_number'   => round($info['in_number'] + $in_number, 4),
                    'in_delta'    => $in_number,
                    'unit_cost'   => $unit_cost,
                    'cost_money'  => $cost_money,
                ];
                $in_log_names[] = $goods['goods_name'] . '×' . $in_number . '(' . Db::name('kuerp_stores')->where(['id' => $stores_id])->value('name') . ')';
            }
        }

        if (!$has_out && !$has_in) {
            $this->error('请至少填写一项实际数量');
        }

        // 判断出库状态
        $new_outbound_status = $row['outbound_status'];
        if ($has_out) {
            // 检查是否所有原材料都已全部出库
            $all_out = true;
            $info_list = Db::name('kuerp_process_info')->where(['process_code' => $row['process_code'], 'type' => '1'])->select();
            foreach ($info_list as $iv) {
                $current_out = $iv['out_number'];
                foreach ($update_out_info as $ui) {
                    if ($ui['id'] == $iv['id']) {
                        $current_out = $ui['out_number'];
                        break;
                    }
                }
                if ($current_out < $iv['number']) {
                    $all_out = false;
                    break;
                }
            }
            $new_outbound_status = $all_out ? 2 : 1;
        }

        // 判断入库状态
        $new_in_status = $row['in_status'];
        if ($has_in) {
            $all_in = true;
            $info_list_in = Db::name('kuerp_process_info')->where(['process_code' => $row['process_code'], 'type' => '2'])->select();
            foreach ($info_list_in as $iv) {
                $current_in = $iv['in_number'];
                foreach ($update_in_info as $ui) {
                    if ($ui['id'] == $iv['id']) {
                        $current_in = $ui['in_number'];
                        break;
                    }
                }
                if ($current_in < $iv['number']) {
                    $all_in = false;
                    break;
                }
            }
            $new_in_status = $all_in ? '2' : '1';
        }

        Db::startTrans();
        try {
            // 处理出库
            if ($has_out) {
                if (!empty($editstock_out)) {
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock_out);
                }
                if (!empty($goods_stock_out)) {
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock_out);
                }
                if (!empty($goods_stock_log_out)) {
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log_out);
                }
                if (!empty($goods_batch_edit_out)) {
                    foreach ($goods_batch_edit_out as $k => $v) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $k])->update(['stock' => $v]);
                    }
                }
                if (!empty($goods_batch_log_out)) {
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log_out);
                }
                if (!empty($edit_serial_out)) {
                    foreach ($edit_serial_out as $esv) {
                        Db::name('kuerp_goods_serial')->where(['serial' => $esv['serial']])->update([
                            'order_id'     => $esv['order_id'],
                            'status'       => $esv['type'],
                            'store_status' => $esv['store_status'],
                            'old_status'   => $esv['old_status'],
                            'old_store'    => $esv['old_store']
                        ]);
                    }
                }
                if (!empty($serial_log_out)) {
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log_out);
                }
                // 更新 out_number
                foreach ($update_out_info as $ui) {
                    Db::name('kuerp_process_info')->where(['id' => $ui['id']])->update(['out_number' => $ui['out_number']]);
                }
            }

            // 处理入库
            if ($has_in) {
                if (!empty($editstock_in)) {
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock_in);
                }
                if (!empty($addstock_in)) {
                    foreach ($addstock_in as $asv) {
                        $where = [];
                        $where['goods_code'] = $asv['goods_code'];
                        $where['stores_id'] = $asv['stores_id'];
                        if ($asv['attribute_id'] > 0) {
                            $where['attribute_id'] = $asv['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_stock')->where($where)->find();
                        if ($stock) {
                            Db::name('kuerp_goods_stock')->where(['id' => $stock['id']])->inc('stock', $asv['stock'])->update();
                        } else {
                            Db::name('kuerp_goods_stock')->insert($asv);
                        }
                    }
                }
                if (!empty($goods_stock_in)) {
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock_in);
                }
                if (!empty($goods_stock_log_in)) {
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log_in);
                }
                if (!empty($goods_batch_add_in)) {
                    foreach ($goods_batch_add_in as $bav) {
                        $where = [];
                        $where['goods_code'] = $bav['goods_code'];
                        $where['stores_id'] = $bav['stores_id'];
                        $where['batch_id'] = $bav['batch_id'];
                        if ($bav['attribute_id'] > 0) {
                            $where['attribute_id'] = $bav['attribute_id'];
                        }
                        $bstock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if ($bstock) {
                            Db::name('kuerp_goods_batch_stock')->where(['id' => $bstock['id']])->inc('stock', $bav['stock'])->update();
                        } else {
                            Db::name('kuerp_goods_batch_stock')->insert($bav);
                        }
                    }
                }
                if (!empty($goods_batch_edit_in)) {
                    foreach ($goods_batch_edit_in as $k => $v) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $k])->update(['stock' => $v]);
                    }
                }
                if (!empty($goods_batch_log_in)) {
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log_in);
                }
                // 更新 in_number / unit_cost / cost_money (unit_cost 取最近一次入库成本, cost_money 累计)
                foreach ($update_in_info as $ui) {
                    Db::name('kuerp_process_info')->where(['id' => $ui['id']])->inc('in_number', round($ui['in_delta'], 4))->update([
                        'unit_cost'  => $ui['unit_cost'],
                        'cost_money' => Db::raw('IFNULL(cost_money, 0) + ' . $ui['cost_money']),
                        'updatetime' => time(),
                    ]);
                }
            }

            // 加工费结算：加工费记录 + 账户流水 + 费用表
            if ($total_process_fee > 0) {
                Db::name('kuerp_process_fee')->insert(\addons\kuerp\library\Goods::setDataInfo([
                    'process_code'    => $row['process_code'],
                    'process_id'      => $row['id'],
                    'supplyer_code'   => $row['supplyer_code'],
                    'fee_time'        => strtotime($fee_time),
                    'fee_money'       => $total_process_fee,
                    'account_id'      => $account_id,
                    'expend_type_id'  => $expend_type_id,
                    'remark'          => $fee_remark,
                    'preparation_id'  => $admin['id'],
                    'createtime'      => time(),
                    'updatetime'      => time(),
                ]));

                // 账户流水
                $account = Db::name('kuerp_accounts')->where(['id' => $account_id])->field('account_coding,account_name,balance')->find();
                if ($account) {
                    Db::name('kuerp_accounts_log')->insert(\addons\kuerp\library\Goods::setDataInfo([
                        'account_coding' => $account['account_coding'],
                        'account_name'   => $account['account_name'],
                        'balance'        => $account['balance'] - $total_process_fee,
                        'expenditure'    => $total_process_fee,
                        'pay_time'       => strtotime($fee_time),
                        'bill_code'      => $row['process_code'],
                        'business_type'  => 8,
                        'supplyer_code'  => $row['supplyer_code'],
                        'remark'         => $fee_remark,
                        'createtime'     => time(),
                        'updatetime'     => time(),
                    ]));
                    Db::name('kuerp_accounts')->where(['id' => $account_id])->dec('balance', $total_process_fee)->update();
                }

                // 费用表
                Db::name('kuerp_cost')->insert(\addons\kuerp\library\Goods::setDataInfo([
                    'purchase_time'   => strtotime($fee_time),
                    'purchase_code'   => \addons\kuerp\library\Goods::getCodes('', 27),
                    'business_type'   => '5',
                    'suppliers_ids'   => Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->value('id'),
                    'suppliers_id'    => Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->value('id'),
                    'expend_type_id'  => $expend_type_id,
                    'purchase_number' => $row['process_code'],
                    'purchase_times'  => $row['process_time'],
                    'money'           => $total_process_fee,
                    'pay_money'       => $total_process_fee,
                    'remark'          => $fee_remark,
                    'preparation_id'  => $admin['id'],
                    'operation_id'    => $admin['id'],
                    'createtime'      => time(),
                    'updatetime'      => time(),
                ]));
            }

            // 更新主表状态
            $update_data = ['operation_id' => $admin['id']];
            if ($has_out) {
                $update_data['outbound_status'] = $new_outbound_status;
                $update_data['outbound_time'] = time();
            }
            if ($has_in) {
                $update_data['in_status'] = $new_in_status;
                $update_data['in_time'] = time();
                $update_data['in_number'] = Db::raw('in_number + ' . $total_in_number);
            }
            if ($total_process_fee > 0) {
                $update_data['total_process_fee'] = Db::raw('total_process_fee + ' . $total_process_fee);
            }
            $this->model->where(['id' => $ids])->update($update_data);

            // 操作记录
            if ($has_out) {
                ProcessLog::writeLog($ids, '原料出库', '加工单[' . $row['process_code'] . ']出库：' . implode('、', $out_log_names));
            }
            if ($has_in) {
                ProcessLog::writeLog($ids, '成品入库', '加工单[' . $row['process_code'] . ']入库：' . implode('、', $in_log_names));
            }
            if ($total_process_fee > 0) {
                ProcessLog::writeLog($ids, '加工费结算', '加工单[' . $row['process_code'] . ']加工费' . $total_process_fee . '元');
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $msg = '';
        if ($has_out) $msg .= '原材料出库成功';
        if ($has_in) $msg .= ($msg ? '，' : '') . '成品入库成功';
        if ($total_process_fee > 0) $msg .= ($msg ? '，' : '') . '加工费已结算';
        $this->success($msg);
    }

    /**
     * 查看详情（入库记录、加工费、入库成本）
     */
    public function detail($ids = null)
    {
        $process_id = $ids ? $ids : $this->request->get('ids');
        if (empty($process_id)) {
            $this->error('参数错误');
        }
        $row = $this->model->with(['suppliers', 'preparation', 'examine'])->where(['process.id' => $process_id])->find();
        if (!$row) {
            $this->error('加工单不存在');
        }
        $row->getRelation('suppliers')->visible(['supplyer_code', 'supplyer_name']);

        // 明细（出库原材料 + 入库成品）
        $info_list = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $row['process_code']])
            ->field('a.*,g.goods_name,g.goods_specification_id,g.vendor_name,g.packaging')
            ->order('a.type', 'ASC')
            ->select();
        foreach ($info_list as &$v) {
            $v['store_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $v['goods_unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
            $v['goods_attr_name'] = $v['goods_attr'] ? Db::name('kuerp_goods_attr')->where(['id' => $v['goods_attr']])->value('attr_code') : '';
            // 型号 / 规格2(厂商) / 规格3(封装)：取商品档案，空值统一显示 -
            $v['goods_unit_name'] = !empty($v['goods_unit_name']) ? $v['goods_unit_name'] : '-';
            $v['goods_attr_name'] = !empty($v['goods_attr_name']) ? $v['goods_attr_name'] : '-';
            $v['goods_specification_id'] = !empty($v['goods_specification_id']) ? $v['goods_specification_id'] : '-';
            $v['vendor_name'] = !empty($v['vendor_name']) ? $v['vendor_name'] : '-';
            $v['packaging'] = !empty($v['packaging']) ? $v['packaging'] : '-';
        }
        unset($v);

        // 加工费记录
        $fee_list = Db::name('kuerp_process_fee')->alias('f')
            ->join('kuerp_accounts a', 'a.id=f.account_id', 'LEFT')
            ->join('kuerp_expend_type e', 'e.id=f.expend_type_id', 'LEFT')
            ->where(['f.process_code' => $row['process_code']])
            ->field('f.*,a.account_name,e.name as expend_name')
            ->select();

        // 操作记录
        $log_list = Db::name('kuerp_process_log')->where(['process_id' => $process_id])->order('id', 'DESC')->select();
        $admin_ids = array_unique(array_column($log_list, 'admin_id'));
        $admin_list = [];
        if (!empty($admin_ids)) {
            $admin_list = Db::name('admin')->where(['id' => ['in', $admin_ids]])->column('nickname', 'id');
        }
        foreach ($log_list as &$log_v) {
            $log_v['admin_name'] = isset($admin_list[$log_v['admin_id']]) ? $admin_list[$log_v['admin_id']] : '';
        }
        unset($log_v);

        $this->view->assign("row", $row);
        $this->view->assign("info_list", $info_list);
        $this->view->assign("fee_list", $fee_list);
        $this->view->assign("log_list", $log_list);
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
        unset($v); // 解除引用，避免后续 foreach 覆盖最后一个元素

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
}
