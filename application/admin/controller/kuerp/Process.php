<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
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
    protected $noNeedRight = ['getunit', 'getinfo', 'printpage', 'outbound', 'canceloutbound'];

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
                $row->getRelation('preparation')->visible(['username']);
                if ($row->getRelation('examine')) {
                    $row->getRelation('examine')->visible(['username']);
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

        $this->view->assign("row", $row);

        $info = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $row['process_code']])
            ->field('a.*,g.goods_name')
            ->select();
        foreach ($info as $info_k => $info_v) {
            $info[$info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($info_v['goods_code']), true);
            // DB→表单字段映射
            $info[$info_k]['unit_price'] = $info_v['unit_cost'];
            $info[$info_k]['all_money'] = $info_v['cost_money'];
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
            // ===== 审核: 出库扣库存 =====
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
                                'process_id' => $info_list_v['id'],
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
                            'process_id'   => $edit_serial_v['process_id'],
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
                    'examine_status' => 1,
                    'examine_id'     => $admin['id'],
                    'examine_time'   => time(),
                    'operation_id'   => $admin['id'],
                    'in_status'      => '0',
                ]);
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
            // ===== 反审核: 退回库存 =====
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
            }
            $process_code = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->column('process_code');
            if (empty($list)) {
                if ($examine_set != 1) {
                    return ['status' => 0, 'msg' => '操作失败'];
                }
                $this->error('请选择需要反审核的数据');
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
                $serial = Db::name('kuerp_goods_serial')->where(['status' => '14', 'process_id' => ['in', $info_ids]])->select();
                foreach ($serial as $serial_k => $serial_v) {
                    Db::name('kuerp_goods_serial')->where(['id' => $serial_v['id']])->update([
                        'status'       => $serial_v['old_status'],
                        'store_status' => $serial_v['old_store']
                    ]);
                }
                Db::name('kuerp_goods_serial_log')->where(['business_type' => '16', 'order_id' => ['in', $info_ids]])->delete();

                $result = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1'])->update([
                    'examine_status' => 0,
                    'examine_id'     => 0,
                    'examine_time'   => '',
                    'operation_id'   => $admin['id']
                ]);
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
     * 出库: 扣减原材料库存
     * (原审核时的库存扣减逻辑移至此处, 审核仅变更状态)
     */
    public function outbound($ids = null)
    {
        $ids = $ids ? $ids : $this->request->post("ids");
        if (empty($ids)) {
            $this->error('请选择要操作的数据');
        }

        $serial_set = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');
        $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');

        $list = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1', 'outbound_status' => '0'])->select();
        foreach ($list as $list_k => $list_v) {
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['process_time']);
            if ($checkout !== true) {
                $this->error($checkout);
            }
        }
        if (empty($list)) {
            $this->error('请选择已审核且未出库的数据');
        }

        $process_code = $this->model->where(['id' => ['in', $ids]])->column('process_code');

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
                            'process_id' => $info_list_v['id'],
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
                        'process_id'   => $edit_serial_v['process_id'],
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

            $result = $this->model->where(['id' => ['in', $ids]])->update([
                'outbound_status' => 1,
                'outbound_time'   => time(),
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success(sizeof($list) . '条数据出库成功');
    }

    /**
     * 取消出库: 恢复原材料库存
     */
    public function cancelOutbound($ids = null)
    {
        $ids = $ids ? $ids : $this->request->post("ids");
        if (empty($ids)) {
            $this->error('请选择要操作的数据');
        }

        $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');

        $list = $this->model->where(['id' => ['in', $ids], 'examine_status' => '1', 'outbound_status' => '1', 'in_status' => '0'])->select();
        if (empty($list)) {
            $this->error('请选择已审核已出库且未入库的数据');
        }

        $process_code = $this->model->where(['id' => ['in', $ids]])->column('process_code');

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
            $serial = Db::name('kuerp_goods_serial')->where(['status' => '14', 'process_id' => ['in', $info_ids]])->select();
            foreach ($serial as $serial_k => $serial_v) {
                Db::name('kuerp_goods_serial')->where(['id' => $serial_v['id']])->update([
                    'status'       => $serial_v['old_status'],
                    'store_status' => $serial_v['old_store']
                ]);
            }
            Db::name('kuerp_goods_serial_log')->where(['business_type' => '16', 'order_id' => ['in', $info_ids]])->delete();

            $result = $this->model->where(['id' => ['in', $ids]])->update([
                'outbound_status' => 0,
                'outbound_time'   => null,
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success(sizeof($list) . '条数据取消出库成功');
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

        // 供应商名称
        $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $row['supplyer_code']])->field('supplyer_code,supplyer_name')->find();
        $row['supplyer_name'] = $supplier ? $supplier['supplyer_name'] : '';

        // 制单人 / 审核人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('username');
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('username');

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

    /**
     * 外协入库
     * 成品入库 + 记录加工费
     */
    public function processIn($ids = null)
    {
        // GET 请求: 加载入库页面
        if (!$this->request->isPost()) {
            $process_id = $ids ? $ids : $this->request->get('ids');
            if (empty($process_id)) {
                $this->error('参数错误');
            }
            $process = $this->model->where(['id' => $process_id])->find();
            if (!$process) {
                $this->error('加工单不存在');
            }
            $this->view->assign("process", $process);
            $this->assignconfig("processId", $process['id']);
            $this->assignconfig("processCode", $process['process_code']);
            $this->assignconfig("processTime", $process['process_time']);

            // 供应商名称
            $supplier = Db::name('kuerp_suppliers')->where(['supplyer_code' => $process['supplyer_code']])->field('supplyer_code,supplyer_name')->find();
            $this->assignconfig("supplier", $supplier);

            $this->view->assign("accountsList", $this->model->getAccountsList());
            $this->assignconfig("expendTypeLists", $this->model->getExpendTypeList());
            return $this->view->fetch('process_in');
        }

        $process_id = $this->request->post('process_id');
        $in_goods = $this->request->post('in_goods/a');
        $total_process_fee = (float)$this->request->post('total_process_fee');
        $account_id = $this->request->post('account_id');
        $expend_type_id = $this->request->post('expend_type_id');
        $fee_time = $this->request->post('fee_time');
        $remark = $this->request->post('remark');

        if (empty($process_id)) {
            $this->error('参数错误');
        }
        if (empty($in_goods)) {
            $this->error('请填写入库成品信息');
        }

        $process = $this->model->where(['id' => $process_id])->find();
        if (!$process) {
            $this->error('加工单不存在');
        }
        if ($process['examine_status'] != 1) {
            $this->error('加工单未审核，不可入库');
        }
        if ($process['in_status'] == 2) {
            $this->error('加工单已全部入库');
        }

        $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($process['process_time']);
        if ($checkout !== true) {
            $this->error($checkout);
        }

        $admin = Session::get('admin');
        $serial_set = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');
        $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');

        // 查询成品明细
        $product_list = Db::name('kuerp_process_info')->where(['process_code' => $process['process_code'], 'type' => '2'])->select();
        $product_map = [];
        foreach ($product_list as $p) {
            $product_map[$p['id']] = $p;
        }

        $editstock = [];
        $addstock = [];
        $goods_stock = [];
        $goods_stock_log = [];
        $goods_batch_add = [];
        $goods_batch_edit = [];
        $goods_batch_log = [];
        $per_code = [];
        $goods_serial = [];
        $edit_serial = [];
        $serial_log = [];
        $total_in_number = 0;
        $has_any_in = false;

        $batch_data_map = $this->request->post('batch_data/a');
        $serial_data_map = $this->request->post('serial_data/a');

        foreach ($in_goods as $in_goods_k => $in_goods_v) {
            $info_id = $in_goods_v['info_id'];
            $in_number = (float)$in_goods_v['in_number'];
            $unit_cost = (float)$in_goods_v['unit_cost'];

            if ($in_number <= 0) {
                continue; // 跳过未入库的行
            }

            if (!isset($product_map[$info_id])) {
                $this->error('成品信息不存在');
            }

            $product = $product_map[$info_id];
            // 优先使用提交的仓库，未选则用计划仓库
            $stores_id = isset($in_goods_v['stores_id']) && !empty($in_goods_v['stores_id']) ? (float)$in_goods_v['stores_id'] : (float)$product['stores_id'];
            $max_in = $product['number'] - $product['in_number'];
            if ($in_number > $max_in) {
                $this->error('入库数量不能超过' . $max_in);
            }

            $has_any_in = true;
            $total_in_number += $in_number;

            $goods = Db::name('kuerp_goods')->where(['id' => $product['goods_id']])->field('id,goods_code,goods_name,inventory,batch,serial')->find();
            $stock_number = $in_number * $product['basic_number'] / ($product['number'] > 0 ? $product['number'] : 1);
            $cost_money = round($unit_cost * $in_number, 4);

            // 商品总库存增加
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

            // 仓库库存增加
            $where = [];
            $where['goods_code'] = $product['goods_code'];
            $where['stores_id'] = $stores_id;
            $goods_attr = 0;
            if ($product['goods_attr'] > 0) {
                $goods_attr = $product['goods_attr'];
                $where['attribute_id'] = $goods_attr;
            }
            $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
            if ($stock) {
                $status = false;
                foreach ($editstock as $editstock_k => $editstock_v) {
                    if ($editstock_v['id'] == $stock['id']) {
                        $editstock[$editstock_k]['stock'] += $stock_number;
                        $editstock[$editstock_k]['cost_money'] = round($editstock_v['cost_money'] + $cost_money, 4);
                        $status = true;
                    }
                }
                if (!$status) {
                    $editstock[] = [
                        'id'         => $stock['id'],
                        'stock'      => round($stock['stock'] + $stock_number, 4),
                        'cost_money' => round($stock['cost_money'] + $cost_money, 4)
                    ];
                }
            } else {
                $addstock[] = \addons\kuerp\library\Goods::setDataInfo([
                    'goods_id'     => $goods['id'],
                    'goods_code'   => $product['goods_code'],
                    'attribute_id' => $goods_attr,
                    'stores_id'    => $stores_id,
                    'stock'        => $stock_number,
                    'cost_money'   => $cost_money,
                    'createtime'   => time(),
                    'updatetime'   => time(),
                ]);
            }

            // 收发明细
            $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                'purchase_code' => $product['process_code'],
                'purchase_id'   => $product['id'],
                'purchase_time' => $product['process_time'],
                'business_type' => '17',
                'goods_id'      => $goods['id'],
                'goods_code'    => $goods['goods_code'],
                'attribute_id'  => (float)$product['goods_attr'],
                'goods_unit'    => (float)$product['goods_unit'],
                'stores_id'     => (float)$stores_id,
                'remark'        => $product['remark'],
                'enter_number'  => $in_number,
                'enter_basics'  => (float)$stock_number,
                'enter_unit'    => round($unit_cost, 4),
                'enter_cost'    => $cost_money,
                'createtime'    => time(),
                'updatetime'    => time(),
            ]);

            // 批次处理
            $batch_data = isset($batch_data_map[$info_id]) ? $batch_data_map[$info_id] : [];
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
                            'manufacture'  => isset($batch_data['manufacture']) ? strtotime($batch_data['manufacture']) : time(),
                            'guarantee'    => isset($batch_data['guarantee']) ? $batch_data['guarantee'] : 0,
                            'validity'     => isset($batch_data['validity']) ? strtotime($batch_data['validity']) : time(),
                            'createtime'   => time(),
                            'updatetime'   => time(),
                        ]));
                    }
                    $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                        'batch_id'     => $batch_id,
                        'goods_id'     => $goods['id'],
                        'goods_code'   => $goods['goods_code'],
                        'attribute_id' => $goods_attr,
                        'stores_id'    => $stores_id,
                        'stock'        => $stock_number,
                        'createtime'   => time(),
                        'updatetime'   => time(),
                    ]);
                } else {
                    if (isset($goods_batch_edit[$goods_batch_stock['id']])) {
                        $goods_batch_edit[$goods_batch_stock['id']] += $stock_number;
                    } else {
                        $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock'] + $stock_number;
                    }
                }

                $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                    'goods_id'        => $goods['id'],
                    'goods_unit'      => $product['goods_unit'],
                    'stores_id'       => $stores_id,
                    'batch_id'        => (float)(isset($batch_data['batch_id']) ? $batch_data['batch_id'] : 0),
                    'number'          => $stock_number,
                    'purchase_code'   => $product['process_code'],
                    'purchase_time'   => $product['process_time'],
                    'business_type'   => '17',
                    'remarks'         => $product['remark'],
                    'order_id'        => $product['id'],
                    'unit_price'      => $unit_cost,
                    'tax_included'    => $unit_cost,
                    'all_money'       => $cost_money,
                    'tax_money'       => 0,
                    'money_tax_price' => $cost_money,
                    'createtime'      => time(),
                    'updatetime'      => time(),
                ]);
            }

            // 序列号处理
            $serial_data = isset($serial_data_map[$info_id]) ? $serial_data_map[$info_id] : [];
            if ($goods && $goods['serial'] == 1 && $serial_set == 1 && !empty($serial_data['period'])) {
                $period = unserialize($serial_data['period']);
                foreach ($period as $period_k => $period_v) {
                    $per_s = Db::name('kuerp_goods_serial')->where(['serial' => $period_v['serial']])->field('serial,status,store_status')->find();
                    if ($per_s) {
                        if ($per_s['store_status'] == 1) {
                            $this->error('序列号' . $period_v['serial'] . '已在库，不可重复入库');
                        }
                        $edit_serial[] = [
                            'serial'       => $per_s['serial'],
                            'process_id'   => $product['id'],
                            'type'         => 15,
                            'store_status' => '1',
                            'old_status'   => $per_s['status'],
                            'old_store'    => $per_s['store_status']
                        ];
                    } else {
                        if (!in_array($period_v['serial'], $per_code)) {
                            $per_code[] = $period_v['serial'];
                        }
                        $goods_serial[] = \addons\kuerp\library\Goods::setDataInfo([
                            'process_id'    => $product['id'],
                            'goods_id'      => $goods['id'],
                            'goods_code'    => $goods['goods_code'],
                            'stores_id'     => $stores_id,
                            'attribute_id'  => $goods_attr,
                            'serial'        => $period_v['serial'],
                            'remark'        => $period_v['remark'],
                            'status'        => '15',
                            'store_status'  => '1',
                            'process_status' => '2',
                            'order_id'      => $product['id'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                    }
                    $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'stores_id'     => $stores_id,
                        'attribute_id'  => $goods_attr,
                        'serial'        => $period_v['serial'],
                        'business_type' => '17',
                        'store_status'  => '1',
                        'remarks'       => $period_v['remark'],
                        'purchase_id'   => $product['id'],
                        'purchase_code' => $product['process_code'],
                        'purchase_time' => $product['process_time'],
                        'order_id'      => $product['id'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }
            }
        }

        if (!$has_any_in) {
            $this->error('请至少入库一件成品');
        }

        // 验证序列号不重复
        if (!empty($per_code)) {
            $exist_serial = Db::name('kuerp_goods_serial')->where(['serial' => ['in', $per_code], 'store_status' => '1'])->column('serial');
            if ($exist_serial) {
                $this->error('序列号' . implode(',', $exist_serial) . '已在库，不可重复入库');
            }
        }

        // 加工费校验
        if ($total_process_fee > 0) {
            if (empty($account_id)) {
                $this->error('请输入结算账户');
            }
            if (empty($expend_type_id)) {
                $this->error('请选择支出类别');
            }
            if (empty($fee_time)) {
                $this->error('请输入费用日期');
            }
        }

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
            if (!empty($goods_stock)) {
                (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
            }
            if (!empty($goods_stock_log)) {
                Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
            }

            // 批次
            if (!empty($goods_batch_add)) {
                foreach ($goods_batch_add as $goods_batch_add_k => $goods_batch_add_v) {
                    $where = [];
                    $where['goods_code'] = $goods_batch_add_v['goods_code'];
                    $where['stores_id'] = $goods_batch_add_v['stores_id'];
                    $where['batch_id'] = $goods_batch_add_v['batch_id'];
                    if ($goods_batch_add_v['attribute_id'] > 0) {
                        $where['attribute_id'] = $goods_batch_add_v['attribute_id'];
                    }
                    $bstock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if ($bstock) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $bstock['id']])->inc('stock', $goods_batch_add_v['stock'])->update();
                    } else {
                        Db::name('kuerp_goods_batch_stock')->insert($goods_batch_add_v);
                    }
                }
            }
            if (!empty($goods_batch_edit)) {
                foreach ($goods_batch_edit as $k => $v) {
                    Db::name('kuerp_goods_batch_stock')->where(['id' => $k])->update(['stock' => $v]);
                }
            }
            if (!empty($goods_batch_log)) {
                Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
            }

            // 序列号
            if (!empty($goods_serial)) {
                Db::name('kuerp_goods_serial')->insertAll($goods_serial);
            }
            if (!empty($edit_serial)) {
                foreach ($edit_serial as $edit_serial_k => $edit_serial_v) {
                    Db::name('kuerp_goods_serial')->where(['serial' => $edit_serial_v['serial']])->update([
                        'process_id'    => $edit_serial_v['process_id'],
                        'status'        => $edit_serial_v['type'],
                        'store_status'  => $edit_serial_v['store_status'],
                        'old_status'    => $edit_serial_v['old_status'],
                        'old_store'     => $edit_serial_v['old_store']
                    ]);
                }
            }
            if (!empty($serial_log)) {
                Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
            }

            // 更新成品明细的 in_number / unit_cost / cost_money
            foreach ($in_goods as $in_goods_k => $in_goods_v) {
                $info_id = $in_goods_v['info_id'];
                $in_number = (float)$in_goods_v['in_number'];
                $unit_cost = (float)$in_goods_v['unit_cost'];
                if ($in_number > 0) {
                    $cost_money = round($unit_cost * $in_number, 4);
                    Db::name('kuerp_process_info')->where(['id' => $info_id])->inc('in_number', $in_number)->update([
                        'unit_cost'  => $unit_cost,
                        'cost_money' => Db::raw('cost_money + ' . $cost_money),
                        'updatetime' => time(),
                    ]);
                }
            }

            // 记录加工费
            if ($total_process_fee > 0) {
                Db::name('kuerp_process_fee')->insert(\addons\kuerp\library\Goods::setDataInfo([
                    'process_code'    => $process['process_code'],
                    'process_id'      => $process['id'],
                    'supplyer_code'   => $process['supplyer_code'],
                    'fee_time'        => strtotime($fee_time),
                    'fee_money'       => $total_process_fee,
                    'account_id'      => $account_id,
                    'expend_type_id'  => $expend_type_id,
                    'remark'          => $remark,
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
                        'bill_code'      => $process['process_code'],
                        'business_type'  => 8,
                        'supplyer_code'  => $process['supplyer_code'],
                        'remark'         => $remark,
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
                    'suppliers_ids'   => Db::name('kuerp_suppliers')->where(['supplyer_code' => $process['supplyer_code']])->value('id'),
                    'suppliers_id'    => Db::name('kuerp_suppliers')->where(['supplyer_code' => $process['supplyer_code']])->value('id'),
                    'expend_type_id'  => $expend_type_id,
                    'purchase_number' => $process['process_code'],
                    'purchase_times'  => $process['process_time'],
                    'money'           => $total_process_fee,
                    'pay_money'       => $total_process_fee,
                    'remark'          => $remark,
                    'preparation_id'  => $admin['id'],
                    'operation_id'    => $admin['id'],
                    'createtime'      => time(),
                    'updatetime'      => time(),
                ]));
            }

            // 更新主表入库状态
            $all_products = Db::name('kuerp_process_info')->where(['process_code' => $process['process_code'], 'type' => '2'])->select();
            $all_done = true;
            foreach ($all_products as $ap) {
                if ($ap['in_number'] < $ap['number']) {
                    $all_done = false;
                    break;
                }
            }

            $this->model->where(['id' => $process_id])->update([
                'in_number'         => Db::raw('in_number + ' . $total_in_number),
                'total_process_fee' => Db::raw('total_process_fee + ' . $total_process_fee),
                'in_status'         => $all_done ? '2' : '1',
                'in_time'           => time(),
                'updatetime'        => time(),
            ]);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('外协入库成功');
    }

    /**
     * 获取加工单明细数据 (processIn 页面 AJAX)
     */
    public function getInfo()
    {
        $process_code = $this->request->post('process_code');
        if (empty($process_code)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        $info_list = Db::name('kuerp_process_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.process_code' => $process_code])
            ->field('a.*,g.goods_name')
            ->order('a.type', 'ASC')
            ->select();

        $out = []; // type=1 出库原材料
        $in = [];  // type=2 入库成品
        foreach ($info_list as $v) {
            $v['store_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            if ($v['type'] == '1') {
                $out[] = $v;
            } else {
                $in[] = $v;
            }
        }
        return json(['code' => 1, 'data' => ['out' => $out, 'in' => $in]]);
    }

    /**
     * 获取商品单位/库存/成本信息 (AJAX)
     * 基于 GoodsPurchase::getUnit, 但将 purchase(采购价) 替换为库存实际成本
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

            // 替换每个单位的 purchase 为库存实际成本
            foreach ($data['unit'] as $k => $v) {
                if ($v['is_default'] == 1) {
                    // 默认单位: 成本 = 库存总成本/总库存
                    $data['unit'][$k]['purchase'] = $unit_cost;
                    $data['unit'][$k]['tax_included'] = $unit_cost;
                    $data['unit'][$k]['tax_rate'] = 0;
                    $data['unit'][$k]['tax_money'] = 0;
                } else {
                    // 辅助单位: 成本 = 默认单位成本 × 换算率
                    $data['unit'][$k]['purchase'] = round($unit_cost * $v['basic_nmber'], 4);
                    $data['unit'][$k]['tax_included'] = round($unit_cost * $v['basic_nmber'], 4);
                    $data['unit'][$k]['tax_rate'] = 0;
                    $data['unit'][$k]['tax_money'] = 0;
                }
            }
        }

        return json_encode($data);
    }
}
