<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;

/**
 * 工序小时看板
 *
 * @icon fa fa-dashboard
 */
class ProcessBoard extends Backend
{

    /**
     * ProcessBoard模型对象
     * @var \app\admin\model\kuerp\ProcessBoard
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\ProcessBoard;
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'board_date';
            $order = 'DESC';
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
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
            if ($params) {
                $params = $this->preExcludeFields($params);
                $built = $this->buildBoardData($params, 0);

                if ($this->model->where(['board_date' => $built['main']['board_date']])->find()) {
                    $this->error('该日期的看板已存在，请直接编辑');
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->insertGetId($built['main']);
                    foreach ($built['info'] as $info_v) {
                        $info_v['board_id'] = $result;
                        Db::name('kuerp_process_board_info')->insertGetId($info_v);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", []);
        $this->view->assign("info_list", []);
        $this->view->assign("hourList", range(0, 23));
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
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $built = $this->buildBoardData($params, $ids);

                if ($this->model->where(['board_date' => $built['main']['board_date'], 'id' => ['<>', $ids]])->find()) {
                    $this->error('该日期的看板已存在');
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->where(['id' => $ids])->update($built['main']);
                    Db::name('kuerp_process_board_info')->where(['board_id' => $ids])->delete();
                    foreach ($built['info'] as $info_v) {
                        $info_v['board_id'] = $ids;
                        Db::name('kuerp_process_board_info')->insertGetId($info_v);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        $info_list = Db::name('kuerp_process_board_info')->where(['board_id' => $ids])->order('id', 'ASC')->select();
        $this->view->assign("info_list", $info_list);
        $this->view->assign("hourList", range(0, 23));
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
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_process_board_info')->where(['board_id' => $v['id']])->delete();
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
     * 看板图表(独立页, 星空背景大屏): 上方当日汇总参数, 下方 柱状图(实际合格)+折线(目标)
     */
    public function chart()
    {
        $ids = $this->request->get("ids/d", 0);
        $row = Db::name('kuerp_process_board')->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $info_list = Db::name('kuerp_process_board_info')->where(['board_id' => $ids])->order('id', 'ASC')->select();
        $this->view->assign("row", $row);
        $this->view->assign("dateText", date('Y-m-d', $row['board_date']));
        $this->view->assign("info_list", $info_list);
        return $this->view->fetch('chart');
    }

    /**
     * 组装主表+明细数据（服务端权威计算）
     * 明细行为并行数组: time_range/target/assemble_code/model/first_pass_qty/cumulative_qty/defect_qty/rework_qty/remark
     * 累计目标逐行累加; 累计合格取提交值(前端级联计算,留空则自动补=前行累计+本行一次合格);
     * 差异=累计合格-累计目标(不足为负数); 状态: 累计合格>=累计目标 → OK, 否则 NG
     * 日汇总: 一次合格/返工合格/总合格/一次不良/目标合计 + 一次良率/最终良率/返工后不良
     */
    protected function buildBoardData($params, $boardId = 0)
    {
        if (empty($params['board_date'])) {
            $this->error('请选择看板日期');
        }
        $board_date = strtotime($params['board_date']);
        if ($board_date === false) {
            $this->error('看板日期格式不正确');
        }
        // 归一为当天零点, 同一日期只允许一条看板
        $board_date = strtotime(date('Y-m-d', $board_date));

        $fields = ['time_range', 'target', 'assemble_code', 'model', 'first_pass_qty', 'cumulative_qty', 'defect_qty', 'rework_qty', 'remark'];
        foreach ($fields as $field) {
            if (!isset($params[$field]) || !is_array($params[$field])) {
                $params[$field] = [];
            }
        }
        $count = max(count($params['time_range']), count($params['target']), count($params['first_pass_qty']), count($params['cumulative_qty']));

        $data = [];
        $total_target = 0;
        $first_pass_total = 0;
        $rework_total = 0;
        $first_pass_defect = 0;
        $cum_target = 0;
        $prev_cum_qty = 0;
        for ($k = 0; $k < $count; $k++) {
            $time_range = isset($params['time_range'][$k]) ? trim($params['time_range'][$k]) : '';
            $target = isset($params['target'][$k]) && $params['target'][$k] !== '' ? (float)$params['target'][$k] : 0;
            $assemble_code = isset($params['assemble_code'][$k]) ? trim($params['assemble_code'][$k]) : '';
            $model = isset($params['model'][$k]) ? trim($params['model'][$k]) : '';
            $first_pass_qty = isset($params['first_pass_qty'][$k]) && $params['first_pass_qty'][$k] !== '' ? (float)$params['first_pass_qty'][$k] : 0;
            $cumulative_qty = isset($params['cumulative_qty'][$k]) && $params['cumulative_qty'][$k] !== '' ? (float)$params['cumulative_qty'][$k] : $prev_cum_qty + $first_pass_qty;
            $defect_qty = isset($params['defect_qty'][$k]) && $params['defect_qty'][$k] !== '' ? (float)$params['defect_qty'][$k] : 0;
            $rework_qty = isset($params['rework_qty'][$k]) && $params['rework_qty'][$k] !== '' ? (float)$params['rework_qty'][$k] : 0;
            $remark = isset($params['remark'][$k]) ? trim($params['remark'][$k]) : '';

            // 跳过全空行(模板行/未填行)
            if ($time_range === '' && $target == 0 && $first_pass_qty == 0 && $defect_qty == 0 && $rework_qty == 0 && $assemble_code === '' && $model === '' && $remark === '') {
                continue;
            }

            $cum_target += $target;
            $variance = round($cumulative_qty - $cum_target, 4);
            $status = $cumulative_qty >= $cum_target ? 'OK' : 'NG';

            $data[] = [
                'board_id'           => $boardId,
                'time_range'         => $time_range,
                'target'             => $target,
                'cumulative_target'  => round($cum_target, 4),
                'assemble_code'      => $assemble_code,
                'model'              => $model,
                'first_pass_qty'     => $first_pass_qty,
                'cumulative_qty'     => round($cumulative_qty, 4),
                'variance'           => $variance,
                'status'             => $status,
                'defect_qty'         => $defect_qty,
                'rework_qty'         => $rework_qty,
                'remark'             => $remark,
                'createtime'         => time(),
                'updatetime'         => time(),
            ];

            $total_target += $target;
            $first_pass_total += $first_pass_qty;
            $rework_total += $rework_qty;
            $first_pass_defect += $defect_qty;
            $prev_cum_qty = $cumulative_qty;
        }
        if (empty($data)) {
            $this->error('请至少填写一个小时的时段数据');
        }

        $qualified_total = $first_pass_total + $rework_total;
        $yield_base = $first_pass_total + $first_pass_defect;

        $admin = Session::get('admin');
        $row = [
            'board_date'        => $board_date,
            'total_target'      => round($total_target, 4),
            'first_pass_total'  => round($first_pass_total, 4),
            'rework_total'      => round($rework_total, 4),
            'qualified_total'   => round($qualified_total, 4),
            'first_pass_yield'  => $yield_base > 0 ? round($first_pass_total / $yield_base, 6) : 0,
            'final_yield'       => $yield_base > 0 ? round($qualified_total / $yield_base, 6) : 0,
            'first_pass_defect' => round($first_pass_defect, 4),
            'rework_defect'     => round(max($first_pass_defect - $rework_total, 0), 4),
            'note'              => isset($params['note']) ? trim($params['note']) : '',
            'admin_id'          => isset($admin['id']) ? $admin['id'] : 0,
        ];
        if ($boardId > 0) {
            $row['updatetime'] = time();
        } else {
            $row['createtime'] = time();
            $row['updatetime'] = time();
        }
        return ['main' => $row, 'info' => $data];
    }
}
