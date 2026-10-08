<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/**
 * 商品BOM模板
 *
 * @icon fa fa-cubes
 */
class GoodsBom extends Backend
{
    protected $noNeedRight = ['getbomdetail', 'chosegoods', 'chosebom', 'preview', 'matchGoods', 'createGoods', 'createBom', 'printpage'];
    /**
     * GoodsBom模型对象
     * @var \app\admin\model\kuerp\GoodsBom
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsBom;
    }

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['admin'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('admin')->visible(['nickname']);
            }
            $lists = [];
            foreach ($list as $list_k => $list_v) {
                // 获取父商品信息
                $p_info = Db::name('kuerp_goods_bom_info')->alias('a')
                    ->join('kuerp_goods g', 'g.id=a.goods_id')
                    ->where(['a.bom_id' => $list_v['id'], 'a.type' => '1'])
                    ->field('a.number,g.goods_name,g.goods_code,g.product_type')
                    ->find();
                // 获取子商品信息
                $info = Db::name('kuerp_goods_bom_info')->alias('a')
                    ->join('kuerp_goods g', 'g.id=a.goods_id')
                    ->where(['a.bom_id' => $list_v['id'], 'a.type' => '2'])
                    ->field('a.number,a.remark,g.goods_name,g.goods_code,g.product_type')
                    ->select();
                foreach ($info as $info_k => $info_v) {
                    $row_data = [];
                    $row_data['id'] = $list_v['id'];
                    $row_data['bom_name'] = $list_v['bom_name'];
                    $row_data['remark'] = $list_v['remark'];
                    $row_data['pnumber'] = $p_info ? $p_info['number'] : '';
                    $row_data['pname'] = $p_info ? $p_info['goods_name'] : '';
                    $row_data['pcode'] = $p_info ? $p_info['goods_code'] : '';
                    $row_data['number'] = $info_v['number'];
                    $row_data['name'] = $info_v['goods_name'];
                    $row_data['code'] = $info_v['goods_code'];
                    $row_data['child_remark'] = $info_v['remark'];
                    $row_data['product_type'] = $info_v['product_type'];
                    $lists[] = $row_data;
                }
                // 如果没有子商品，也显示一行
                if (empty($info)) {
                    $row_data = [];
                    $row_data['id'] = $list_v['id'];
                    $row_data['bom_name'] = $list_v['bom_name'];
                    $row_data['remark'] = $list_v['remark'];
                    $row_data['pnumber'] = $p_info ? $p_info['number'] : '';
                    $row_data['pname'] = $p_info ? $p_info['goods_name'] : '';
                    $row_data['pcode'] = $p_info ? $p_info['goods_code'] : '';
                    $row_data['number'] = '';
                    $row_data['name'] = '';
                    $row_data['code'] = '';
                    $row_data['child_remark'] = '';
                    $row_data['product_type'] = '';
                    $lists[] = $row_data;
                }
            }
            $result = array("total" => $list->total(), "rows" => $lists);
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

            // 新格式：子商品使用并行数组 child_goods_id[], child_goods_code[], child_number[], child_unit[], child_remark[]
            $childGoodsIds   = $this->request->post("child_goods_id/a");
            $childGoodsCodes = $this->request->post("child_goods_code/a");
            $childNumbers    = $this->request->post("child_number/a");
            $childUnits      = $this->request->post("child_unit/a");
            $childRemarks    = $this->request->post("child_remark/a");

            // 兼容旧格式 children[a]
            $children = $this->request->post("children/a");
            if (empty($children) && !empty($childGoodsIds)) {
                $children = [];
                foreach ($childGoodsIds as $i => $goodsId) {
                    if (!empty($goodsId) && !empty($childNumbers[$i])) {
                        $children[] = [
                            'goods_id' => $goodsId,
                            'goods_code' => isset($childGoodsCodes[$i]) ? $childGoodsCodes[$i] : '',
                            'number' => $childNumbers[$i],
                            'unit_id' => isset($childUnits[$i]) ? $childUnits[$i] : '',
                            'remark' => isset($childRemarks[$i]) ? $childRemarks[$i] : '',
                        ];
                    }
                }
            }

            if (empty($params['bom_name'])) {
                $this->error('模板名称不能为空');
            }
            if (empty($params['goods_id'])) {
                $this->error('请选择父商品');
            }
            if (empty($children) || count($children) < 1) {
                $this->error('至少需要一个子商品');
            }

            // 查询父商品信息
            $parentGoods = Db::name('kuerp_goods')->where(['id' => $params['goods_id']])->field('id,goods_code,goods_name')->find();
            if (empty($parentGoods)) {
                $this->error('父商品不存在');
            }

            Db::startTrans();
            try {
                // 插入BOM主表
                $bomData = [
                    'admin_id' => $this->auth->id,
                    'bom_name' => $params['bom_name'],
                    'goods_id' => $parentGoods['id'],
                    'goods_code' => $parentGoods['goods_code'],
                    'remark' => isset($params['remark']) ? $params['remark'] : '',
                    'createtime' => time(),
                    'updatetime' => time(),
                ];
                $bomId = Db::name('kuerp_goods_bom')->insertGetId($bomData);

                // 插入父商品行 (type=1)
                $parentRow = [
                    'admin_id' => $this->auth->id,
                    'bom_id' => $bomId,
                    'type' => '1',
                    'goods_id' => $parentGoods['id'],
                    'goods_code' => $parentGoods['goods_code'],
                    'goods_name' => $parentGoods['goods_name'],
                    'unit_id' => isset($params['unit_id']) ? $params['unit_id'] : 0,
                    'number' => 1, // 父商品数量固定为1
                    'remark' => '',
                    'createtime' => time(),
                    'updatetime' => time(),
                ];
                Db::name('kuerp_goods_bom_info')->insert($parentRow);

                // 插入子商品行 (type=2)
                foreach ($children as $child) {
                    if (empty($child['goods_id']) || empty($child['number'])) {
                        continue;
                    }
                    $childGoods = Db::name('kuerp_goods')->where(['id' => $child['goods_id']])->field('id,goods_code,goods_name,unit_id')->find();
                    if (empty($childGoods)) {
                        continue;
                    }
                    $childRow = [
                        'admin_id' => $this->auth->id,
                        'bom_id' => $bomId,
                        'type' => '2',
                        'goods_id' => $childGoods['id'],
                        'goods_code' => $childGoods['goods_code'],
                        'goods_name' => $childGoods['goods_name'],
                        'unit_id' => isset($child['unit_id']) ? $child['unit_id'] : $childGoods['unit_id'],
                        'number' => $child['number'],
                        'remark' => isset($child['remark']) ? $child['remark'] : '',
                        'createtime' => time(),
                        'updatetime' => time(),
                    ];
                    Db::name('kuerp_goods_bom_info')->insert($childRow);
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('添加失败：' . $e->getMessage());
            }
            $this->success('添加成功');
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error('未找到该记录');
        }

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");

            // 新格式：子商品使用并行数组
            $childGoodsIds   = $this->request->post("child_goods_id/a");
            $childGoodsCodes = $this->request->post("child_goods_code/a");
            $childNumbers    = $this->request->post("child_number/a");
            $childUnits      = $this->request->post("child_unit/a");
            $childRemarks    = $this->request->post("child_remark/a");

            // 兼容旧格式
            $children = $this->request->post("children/a");
            if (empty($children) && !empty($childGoodsIds)) {
                $children = [];
                foreach ($childGoodsIds as $i => $goodsId) {
                    if (!empty($goodsId) && !empty($childNumbers[$i])) {
                        $children[] = [
                            'goods_id' => $goodsId,
                            'goods_code' => isset($childGoodsCodes[$i]) ? $childGoodsCodes[$i] : '',
                            'number' => $childNumbers[$i],
                            'unit_id' => isset($childUnits[$i]) ? $childUnits[$i] : '',
                            'remark' => isset($childRemarks[$i]) ? $childRemarks[$i] : '',
                        ];
                    }
                }
            }

            if (empty($params['bom_name'])) {
                $this->error('模板名称不能为空');
            }
            if (empty($params['goods_id'])) {
                $this->error('请选择父商品');
            }
            if (empty($children) || count($children) < 1) {
                $this->error('至少需要一个子商品');
            }

            $parentGoods = Db::name('kuerp_goods')->where(['id' => $params['goods_id']])->field('id,goods_code,goods_name')->find();
            if (empty($parentGoods)) {
                $this->error('父商品不存在');
            }

            Db::startTrans();
            try {
                // 更新BOM主表
                $bomData = [
                    'bom_name' => $params['bom_name'],
                    'goods_id' => $parentGoods['id'],
                    'goods_code' => $parentGoods['goods_code'],
                    'remark' => isset($params['remark']) ? $params['remark'] : '',
                    'updatetime' => time(),
                ];
                Db::name('kuerp_goods_bom')->where(['id' => $ids])->update($bomData);

                // 删除旧明细
                Db::name('kuerp_goods_bom_info')->where(['bom_id' => $ids])->delete();

                // 重新插入父商品行 (type=1)
                $parentRow = [
                    'admin_id' => $this->auth->id,
                    'bom_id' => $ids,
                    'type' => '1',
                    'goods_id' => $parentGoods['id'],
                    'goods_code' => $parentGoods['goods_code'],
                    'goods_name' => $parentGoods['goods_name'],
                    'unit_id' => isset($params['unit_id']) ? $params['unit_id'] : 0,
                    'number' => 1,
                    'remark' => '',
                    'createtime' => time(),
                    'updatetime' => time(),
                ];
                Db::name('kuerp_goods_bom_info')->insert($parentRow);

                // 重新插入子商品行 (type=2)
                foreach ($children as $child) {
                    if (empty($child['goods_id']) || empty($child['number'])) {
                        continue;
                    }
                    $childGoods = Db::name('kuerp_goods')->where(['id' => $child['goods_id']])->field('id,goods_code,goods_name,unit_id')->find();
                    if (empty($childGoods)) {
                        continue;
                    }
                    $childRow = [
                        'admin_id' => $this->auth->id,
                        'bom_id' => $ids,
                        'type' => '2',
                        'goods_id' => $childGoods['id'],
                        'goods_code' => $childGoods['goods_code'],
                        'goods_name' => $childGoods['goods_name'],
                        'unit_id' => isset($child['unit_id']) ? $child['unit_id'] : $childGoods['unit_id'],
                        'number' => $child['number'],
                        'remark' => isset($child['remark']) ? $child['remark'] : '',
                        'createtime' => time(),
                        'updatetime' => time(),
                    ];
                    Db::name('kuerp_goods_bom_info')->insert($childRow);
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('编辑失败：' . $e->getMessage());
            }
            $this->success('编辑成功');
        }

        // 获取BOM详情
        $bomInfo = Db::name('kuerp_goods_bom_info')
            ->where(['bom_id' => $ids])
            ->order('type', 'asc')
            ->select();
        $parent = null;
        $childList = [];
        foreach ($bomInfo as $info) {
            if ($info['type'] == '1') {
                $parent = $info;
                // 补全父商品的 product_type、型号/厂商/封装
                $parentGoods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->field('goods_name,product_type,goods_specification_id,vendor_name,packaging')->find();
                if ($parentGoods) {
                    $parent['goods_name'] = $parentGoods['goods_name'];
                    $parent['product_type'] = $parentGoods['product_type'];
                    $parent['goods_specification_id'] = $parentGoods['goods_specification_id'];
                    $parent['vendor_name'] = $parentGoods['vendor_name'];
                    $parent['packaging'] = $parentGoods['packaging'];
                }
            } else {
                // 获取子商品信息用于回显
                $goods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->field('goods_name,product_type,unit_id,goods_specification_id,vendor_name,packaging')->find();
                $info['goods_name_display'] = $goods ? $goods['goods_name'] : '';
                $info['product_type'] = $goods ? $goods['product_type'] : '';
                $info['unit_id'] = $info['unit_id'] ?: ($goods ? $goods['unit_id'] : 0);
                $info['goods_specification_id'] = $goods ? $goods['goods_specification_id'] : '';
                $info['vendor_name'] = $goods ? $goods['vendor_name'] : '';
                $info['packaging'] = $goods ? $goods['packaging'] : '';
                $childList[] = $info;
            }
        }
        $this->view->assign("row", $row);
        $this->view->assign("parent", $parent);
        $this->view->assign("childList", $childList);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = null)
    {
        $ids = $ids ? $ids : $this->request->post('ids');
        if (!$ids) {
            $this->error('参数错误');
        }
        $ids = explode(',', $ids);
        Db::startTrans();
        try {
            foreach ($ids as $id) {
                // 删除明细
                Db::name('kuerp_goods_bom_info')->where(['bom_id' => $id])->delete();
                // 删除主表
                Db::name('kuerp_goods_bom')->where(['id' => $id])->delete();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('删除失败：' . $e->getMessage());
        }
        $this->success('删除成功');
    }

    /**
     * 打印BOM模板（父商品显示在头部）
     */
    public function printpage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 父商品行 (type=1)
        $parent = Db::name('kuerp_goods_bom_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.bom_id' => $ids, 'a.type' => '1'])
            ->field('a.number,g.goods_name,g.goods_code,g.goods_specification_id,g.vendor_name,g.packaging,g.unit_id as goods_unit_id,a.unit_id')
            ->find();
        if ($parent) {
            $unitId = $parent['unit_id'] > 0 ? $parent['unit_id'] : $parent['goods_unit_id'];
            $parent['unit_name'] = Db::name('kuerp_unit')->where(['id' => $unitId])->value('name');
        }
        // 子件行 (type=2)
        $children = Db::name('kuerp_goods_bom_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.bom_id' => $ids, 'a.type' => '2'])
            ->field('a.number,a.remark,g.goods_name,g.goods_code,g.goods_specification_id,g.unit_id as goods_unit_id,a.unit_id')
            ->select();
        foreach ($children as $k => $v) {
            $unitId = $v['unit_id'] > 0 ? $v['unit_id'] : $v['goods_unit_id'];
            $children[$k]['unit_name'] = Db::name('kuerp_unit')->where(['id' => $unitId])->value('name');
        }
        // 创建人
        $row['admin_name'] = Db::name('admin')->where(['id' => $row['admin_id']])->value('nickname');

        $this->view->assign('row', $row);
        $this->view->assign('parent', $parent);
        $this->view->assign('children', $children);
        return $this->view->fetch();
    }

    /**
     * 获取BOM模板详情（供计算器调用）
     */
    public function getbomdetail($id = null)
    {
        if (!$id) {
            $this->error('参数错误');
        }
        $bom = Db::name('kuerp_goods_bom')->where(['id' => $id])->find();
        if (empty($bom)) {
            $this->error('未找到该BOM模板');
        }
        // 父商品
        $parent = Db::name('kuerp_goods_bom_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.bom_id' => $id, 'a.type' => '1'])
            ->field('a.*,g.goods_name as parent_name,g.product_type,g.manager_warehouse_id')
            ->find();
        // 子商品
        $children = Db::name('kuerp_goods_bom_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.bom_id' => $id, 'a.type' => '2'])
            ->field('a.*,g.goods_name as goods_name,g.product_type,g.inventory,g.manager_warehouse_id')
            ->select();
        $this->success('', '', ['bom' => $bom, 'parent' => $parent, 'children' => $children]);
    }

    /**
     * BOM模板选择页（供组装单等单据选择BOM作为模板）
     */
    public function chosebom()
    {
        return $this->view->fetch();
    }

    /**
     * 搜索商品（供前端selectpage使用）
     */
    public function chosegoods()
    {
        $search = $this->request->request('search');
        $where = [];
        if ($search) {
            $where['goods_code|goods_name|goods_barcode'] = ['like', '%' . $search . '%'];
        }
        $list = Db::name('kuerp_goods')
            ->where($where)
            ->where(['status' => '1'])
            ->field('id,goods_code,goods_name,product_type,unit_id')
            ->limit(50)
            ->select();
        $this->success('', null, $list);
    }

    // ============================================================
    // BOM Excel 导入（含匹配预览）
    // ============================================================

    /**
     * 导入页（GET渲染视图）
     */
    public function import()
    {
        $unit = Db::name('kuerp_unit')->where(['type' => '1'])->field('id,name')->select();
        $goodsType = Db::name('kuerp_goods_type')->where(['status' => '1'])->field('id,supplier_type_name')->select();
        $suppliers = Db::name('kuerp_suppliers')->where(['status' => 1])->field('id,supplyer_name')->select();
        $stores = Db::name('kuerp_stores')->where(['status' => '1'])->field('id,name')->select();
        $this->assignconfig('import_units', $unit);
        $this->assignconfig('import_goods_types', $goodsType);
        $this->assignconfig('import_suppliers', $suppliers);
        $this->assignconfig('import_stores', $stores);
        return $this->view->fetch();
    }

    /**
     * 解析上传的Excel并返回匹配预览数据（POST）
     */
    public function preview()
    {
        $file = $this->request->request('file');
        if (!$file) {
            $this->error('请选择上传文件');
        }
        $filePath = ROOT_PATH . DS . 'public' . DS . $file;
        if (!is_file($filePath)) {
            $this->error('文件不存在');
        }
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $this->error('文件格式不支持，仅支持 xlsx / xls / csv');
        }

        try {
            $parsed = $this->parseBomExcel($filePath);
        } catch (\Exception $e) {
            $this->error('解析失败：' . $e->getMessage());
        }

        $parent = $parsed['parent'];
        $children = $parsed['children'];

        // 父商品必须已存在（按商品编号精确匹配）
        if ($parent['goods_code'] === '') {
            $this->error('父级商品编号为空，请检查模板');
        }
        $parentGoods = Db::name('kuerp_goods')
            ->where(['goods_code' => $parent['goods_code'], 'status' => '1'])
            ->field('id,goods_code,goods_name,product_type,unit_id')
            ->find();
        if (empty($parentGoods)) {
            $this->error('父商品[' . $parent['goods_code'] . ']不存在，请先在货品管理中创建该商品后再导入');
        }

        // 重复BOM校验：同一父商品只允许一个模板
        $exist = Db::name('kuerp_goods_bom')->where(['goods_id' => $parentGoods['id']])->find();
        if ($exist) {
            $this->error('父商品[' . $parentGoods['goods_code'] . ']已存在BOM模板，请先删除原模板后再导入');
        }

        // 子商品匹配
        $childrenResult = [];
        foreach ($children as $child) {
            $match = $this->matchGoodsRow($child['spec'], $child['vendor'], $child['packaging']);
            $childrenResult[] = [
                'name'          => $child['name'],
                'spec'          => $child['spec'],
                'vendor'        => $child['vendor'],
                'packaging'     => $child['packaging'],
                'number'        => $child['number'],
                'status'        => $match['status'],
                'default_goods' => $match['default_goods'],
                'candidates'    => $match['candidates'],
            ];
        }

        $data = [
            'bom_name' => $parentGoods['goods_name'] . 'BOM',
            'parent' => [
                'goods_id'     => $parentGoods['id'],
                'goods_code'   => $parentGoods['goods_code'],
                'goods_name'   => $parentGoods['goods_name'],
                'product_type' => $parentGoods['product_type'],
                'excel_name'   => $parent['name'],
                'spec'         => $parent['spec'],
                'vendor'       => $parent['vendor'],
                'packaging'    => $parent['packaging'],
            ],
            'children' => $childrenResult,
        ];
        // 注意：success($msg, $url, $data) 数据在第3参，不可直接传数组到第2参
        $this->success('', null, $data);
    }

    /**
     * 刷新/搜索子商品匹配候选（POST）
     * 用于“替换”弹窗搜索、以及“新增”商品后的刷新
     */
    public function matchGoods()
    {
        $spec = $this->request->post('spec');
        $vendor = $this->request->post('vendor');
        $packaging = $this->request->post('packaging');
        $keyword = $this->request->post('keyword');
        $result = $this->matchGoodsRow($spec, $vendor, $packaging, $keyword);
        $this->success('', null, $result);
    }

    /**
     * 批量刷新子商品匹配候选（POST rows=[{spec,vendor,packaging},...]）
     * 预览页"刷新匹配"一次请求返回所有行，避免逐行发请求
     */
    public function matchGoodsBatch()
    {
        $rows = $this->request->post('rows/a');
        $results = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $results[] = $this->matchGoodsRow(
                    isset($r['spec']) ? $r['spec'] : '',
                    isset($r['vendor']) ? $r['vendor'] : '',
                    isset($r['packaging']) ? $r['packaging'] : '',
                    ''
                );
            }
        }
        $this->success('', null, $results);
    }

    /**
     * 快速新建商品（导入弹窗内调用）
     */
    public function createGoods()
    {
        $goods_name = trim((string)$this->request->post('goods_name'));
        $spec = trim((string)$this->request->post('goods_specification_id'));
        $vendor = trim((string)$this->request->post('vendor_name'));
        $packaging = trim((string)$this->request->post('packaging'));
        $product_type = (string)$this->request->post('product_type', 'part');
        $unit_id = (int)$this->request->post('unit_id');
        $goods_type_id = (int)$this->request->post('goods_type_id');
        $supplier_id = (int)$this->request->post('supplier_id');
        $manager_warehouse_id = (int)$this->request->post('manager_warehouse_id');

        if ($goods_name === '') {
            $this->error('商品名称不能为空');
        }
        if (!in_array($product_type, ['part', 'semi_finished', 'finished'])) {
            $product_type = 'part';
        }
        if ($unit_id <= 0) {
            $this->error('请选择计量单位');
        }
        if (!Db::name('kuerp_unit')->where(['id' => $unit_id])->find()) {
            $this->error('计量单位不存在');
        }

        // 生成商品编号（沿用货品管理的编码规则）
        $code = \addons\kuerp\library\Goods::getCodes('', 6);
        if (!$code || Db::name('kuerp_goods')->where(['goods_code' => $code])->find()) {
            $code = 'SP' . str_pad((string)mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            $i = 0;
            while (Db::name('kuerp_goods')->where(['goods_code' => $code])->find() && $i < 10) {
                $code = 'SP' . str_pad((string)mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
                $i++;
            }
        }

        $data = [
            'admin_id'                => $this->auth->id,
            'goods_code'              => $code,
            'goods_name'              => $goods_name,
            'goods_barcode'           => '',
            'goods_specification_id'  => $spec,
            'goods_type_id'           => $goods_type_id,
            'location_id'             => 0,
            'supplier_id'             => $supplier_id,
            'product_type'            => $product_type,
            'manager_warehouse_id'    => $manager_warehouse_id,
            'unit_id'                 => $unit_id,
            'more_unit'               => '2',
            'more_unit_id'            => 0,
            'market_sell_price'       => 0,
            'wholesale_price'         => 0,
            'vip_price'               => 0,
            'discount'                => 0,
            'discount2'               => 0,
            'purchase'                => 0,
            'inventory'               => 0,
            'more_attribute'          => '0',
            'attribute_id'            => '',
            'note'                    => '',
            'vendor_name'             => $vendor,
            'packaging'               => $packaging,
            'is_offten'               => '0',
            'stock_warning'           => '0',
            'min_stock'               => 0,
            'max_stock'               => 0,
            'branch'                  => '0',
            'warehousing'             => 0,
            'warehouse'               => 0,
            'period'                  => 0,
            'unit_cost'               => 0,
            'price'                   => 0,
            'period_status'           => '0',
            'status'                  => '1',
            'is_edit'                 => '1',
            'auxiliary'               => 0,
            'batch'                   => 0,
            'shelf_life'              => 0,
            'early_warning'           => 0,
            'serial'                  => 0,
            'tag'                     => 'sale',
            'sale_number'             => 0,
            'is_show'                 => 1,
            'createtime'              => time(),
            'updatetime'              => time(),
        ];

        Db::startTrans();
        try {
            $goodsId = Db::name('kuerp_goods')->insertGetId($data);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('创建失败：' . $e->getMessage());
        }
        $this->success('创建成功', null, [
            'goods_id'              => $goodsId,
            'goods_code'            => $code,
            'goods_name'            => $goods_name,
            'product_type'          => $product_type,
            'unit_id'               => $unit_id,
            'goods_specification_id' => $spec,
            'vendor_name'           => $vendor,
            'packaging'             => $packaging,
        ]);
    }

    /**
     * 确认导入（最终入库，POST）
     */
    public function createBom()
    {
        $bom_name = trim((string)$this->request->post('bom_name'));
        $parentGoodsId = (int)$this->request->post('parent_goods_id');
        $children = $this->request->post('children/a');

        if ($bom_name === '') {
            $this->error('模板名称不能为空');
        }
        if ($parentGoodsId <= 0) {
            $this->error('缺少父商品');
        }
        if (empty($children) || !is_array($children)) {
            $this->error('至少需要一个子商品');
        }

        $parentGoods = Db::name('kuerp_goods')->where(['id' => $parentGoodsId])->field('id,goods_code,goods_name')->find();
        if (empty($parentGoods)) {
            $this->error('父商品不存在');
        }

        // 重复导入校验
        $exist = Db::name('kuerp_goods_bom')->where(['goods_id' => $parentGoodsId])->find();
        if ($exist) {
            $this->error('父商品[' . $parentGoods['goods_code'] . ']已存在BOM模板，请先删除原模板后再导入');
        }

        Db::startTrans();
        try {
            $bomId = Db::name('kuerp_goods_bom')->insertGetId([
                'admin_id'    => $this->auth->id,
                'bom_name'    => $bom_name,
                'goods_id'    => $parentGoods['id'],
                'goods_code'  => $parentGoods['goods_code'],
                'remark'      => '',
                'createtime'  => time(),
                'updatetime'  => time(),
            ]);

            // 父商品行 (type=1, number固定1)
            Db::name('kuerp_goods_bom_info')->insert([
                'admin_id'    => $this->auth->id,
                'bom_id'      => $bomId,
                'type'        => '1',
                'goods_id'    => $parentGoods['id'],
                'goods_code'  => $parentGoods['goods_code'],
                'goods_name'  => $parentGoods['goods_name'],
                'unit_id'     => 0,
                'number'      => 1,
                'remark'      => '',
                'createtime'  => time(),
                'updatetime'  => time(),
            ]);

            // 子商品行 (type=2)
            foreach ($children as $child) {
                if (empty($child['goods_id']) || empty($child['number'])) {
                    continue;
                }
                $childGoods = Db::name('kuerp_goods')->where(['id' => $child['goods_id']])->field('id,goods_code,goods_name,unit_id')->find();
                if (empty($childGoods)) {
                    continue;
                }
                Db::name('kuerp_goods_bom_info')->insert([
                    'admin_id'    => $this->auth->id,
                    'bom_id'      => $bomId,
                    'type'        => '2',
                    'goods_id'    => $childGoods['id'],
                    'goods_code'  => $childGoods['goods_code'],
                    'goods_name'  => $childGoods['goods_name'],
                    'unit_id'     => $childGoods['unit_id'],
                    'number'      => (float)$child['number'],
                    'remark'      => '',
                    'createtime'  => time(),
                    'updatetime'  => time(),
                ]);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('导入失败：' . $e->getMessage());
        }
        $this->success('导入成功', null, $bomId);
    }

    /**
     * 解析BOM导入Excel
     * 模板结构：说明行 → “父级商品”段（表头+1行数据） → “子级商品”段（表头+多行数据）
     */
    private function parseBomExcel($filePath)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $reader = new Csv();
        } elseif ($ext === 'xls') {
            $reader = new Xls();
        } else {
            $reader = new Xlsx();
        }

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        // 读取所有行（0-based列索引）
        $rows = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $cells = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $val = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                $cells[] = is_null($val) ? '' : trim((string)$val);
            }
            $rows[$r] = $cells;
        }

        $parentLabelRow = $this->findLabelRow($rows, '父级商品');
        $childLabelRow = $this->findLabelRow($rows, '子级商品');
        if (!$parentLabelRow || !$childLabelRow) {
            throw new \Exception('模板格式不正确，未找到"父级商品/子级商品"段落');
        }

        $parentHeader = $this->buildHeaderMap(isset($rows[$parentLabelRow + 1]) ? $rows[$parentLabelRow + 1] : []);
        $childHeader = $this->buildHeaderMap(isset($rows[$childLabelRow + 1]) ? $rows[$childLabelRow + 1] : []);

        // 父级数据：段落标签后第2行
        $parentRow = isset($rows[$parentLabelRow + 2]) ? $rows[$parentLabelRow + 2] : [];
        $parent = [
            'goods_code' => $this->mapCell($parentHeader, $parentRow, '商品编号'),
            'name'       => $this->mapCell($parentHeader, $parentRow, '名称'),
            'spec'       => $this->mapCellAny($parentHeader, $parentRow, ['型号', '规格']),
            'vendor'     => $this->mapCellAny($parentHeader, $parentRow, ['规格2/厂商', '制造商', '厂商', '规格2']),
            'packaging'  => $this->mapCellAny($parentHeader, $parentRow, ['规格3/封装', '规格3/封存', '封装', '规格3']),
            'number'     => $this->mapCell($parentHeader, $parentRow, '数量'),
        ];

        // 子级数据（注意：模板中 型号/厂商/封装 列存在新旧多种表头，须按别名取列）
        $children = [];
        for ($r = $childLabelRow + 2; $r <= $highestRow; $r++) {
            $cells = isset($rows[$r]) ? $rows[$r] : [];
            $name = $this->mapCell($childHeader, $cells, '名称');
            $spec = $this->mapCellAny($childHeader, $cells, ['型号', '规格']);
            $vendor = $this->mapCellAny($childHeader, $cells, ['规格2/厂商', '制造商', '厂商', '规格2']);
            $packaging = $this->mapCellAny($childHeader, $cells, ['规格3/封装', '规格3/封存', '封装', '规格3']);
            $number = $this->mapCell($childHeader, $cells, '数量');
            if ($name === '' && $spec === '' && $vendor === '' && $packaging === '') {
                continue; // 跳过空行
            }
            $children[] = [
                'name'      => $name,
                'spec'      => $spec,
                'vendor'    => $vendor,
                'packaging' => $packaging,
                'number'    => is_numeric($number) ? (float)$number : 1,
            ];
        }

        if (empty($children)) {
            throw new \Exception('模板中未填写子级商品');
        }

        return ['parent' => $parent, 'children' => $children];
    }

    /**
     * 按 型号/厂商/封装 三参数匹配商品，返回状态与候选
     * 匹配口径：三项完全一致才算匹配成功，其中“双方均为空”也视为一致；
     * 即 填1项另2项双方均空、或填2项1项双方均空，均算匹配成功；
     * 任一项不一致（空对有值、或值不等）则不算匹配成功，仅作为候选供人工选择
     * @param string $spec      型号
     * @param string $vendor    厂商
     * @param string $packaging 封装
     * @param string $keyword   可选搜索关键字（“替换”弹窗搜索时传入）
     * @return array ['status'=>'matched|pending|failed','default_goods'=>array|null,'candidates'=>array]
     */
    private function matchGoodsRow($spec, $vendor, $packaging, $keyword = '')
    {
        $spec = trim((string)$spec);
        $vendor = trim((string)$vendor);
        $packaging = trim((string)$packaging);
        $keyword = trim((string)$keyword);

        $goodsWhere = [
            'status'       => '1',
            'product_type' => ['in', ['part', 'semi_finished']],
        ];

        // 候选池：三参数任一 LIKE 匹配
        $whereOr = [];
        if ($spec !== '') {
            $whereOr[] = ['goods_specification_id', 'like', '%' . $spec . '%'];
        }
        if ($vendor !== '') {
            $whereOr[] = ['vendor_name', 'like', '%' . $vendor . '%'];
        }
        if ($packaging !== '') {
            $whereOr[] = ['packaging', 'like', '%' . $packaging . '%'];
        }

        $list = [];
        if (!empty($whereOr)) {
            $list = Db::name('kuerp_goods')
                ->where($goodsWhere)
                ->where(function ($query) use ($whereOr) {
                    // 注意：不能直接 whereOr($whereOr) 传三元组数组，
                    // TP5 会按数字键批量查询导致"查询表达式错误"，
                    // 须在闭包内链式调用 where/whereOr
                    foreach ($whereOr as $i => $cond) {
                        if ($i === 0) {
                            $query->where($cond[0], $cond[1], $cond[2]);
                        } else {
                            $query->whereOr($cond[0], $cond[1], $cond[2]);
                        }
                    }
                })
                ->limit(100)
                ->select();
        }

        // 完全一致候选单独精确查询：
        // 上面的候选池是三参数任一 LIKE 且 limit(100)，当“封装=插件”这类宽条件
        // 命中行数超过100时，真正的完全一致商品可能被截断在池外，
        // 导致明明三项完全一致却显示匹配失败。故按“非空项精确相等、空项双方均空”再查一次。
        $exactQuery = Db::name('kuerp_goods')->where($goodsWhere);
        $triple = [
            [$spec, 'goods_specification_id'],
            [$vendor, 'vendor_name'],
            [$packaging, 'packaging'],
        ];
        foreach ($triple as $cond) {
            list($val, $field) = $cond;
            if ($val !== '') {
                $exactQuery->where($field, '=', $val);
            } else {
                $exactQuery->where(function ($query) use ($field) {
                    $query->where($field, 'null')->whereOr($field, '');
                });
            }
        }
        $exactList = $exactQuery->select();
        if (!empty($exactList)) {
            $tmp = [];
            foreach (array_merge($exactList, $list) as $g) {
                $tmp[$g['id']] = $g;
            }
            $list = array_values($tmp);
        }

        // 关键字搜索补充候选
        if ($keyword !== '') {
            $kwList = Db::name('kuerp_goods')
                ->where($goodsWhere)
                ->where('goods_code|goods_name|goods_specification_id', 'like', '%' . $keyword . '%')
                ->limit(100)
                ->select();
            $tmp = [];
            foreach ($list as $g) {
                $tmp[$g['id']] = $g;
            }
            foreach ($kwList as $g) {
                $tmp[$g['id']] = $g;
            }
            $list = array_values($tmp);
        }

        // 逐个计算匹配字段
        // 匹配口径：三项参数完全一致才算匹配成功，其中“双方均为空格”也视为一致；
        // 任一项不一致（一侧为空另一侧有值，或均非空但不相等）则整体不算匹配成功。
        // 部分相同的商品仍进入候选列表，供“替换”弹窗人工选择。
        $candidates = [];
        foreach ($list as $g) {
            $matched = [];      // 真实匹配项（两侧均非空且相等）
            $blankAgree = 0;    // 一致项中“两侧均为空”的数量
            $disagree = false;  // 是否存在不一致项
            $pairs = [
                [$spec,      $g['goods_specification_id'], 'spec'],
                [$vendor,    $g['vendor_name'],            'vendor'],
                [$packaging, $g['packaging'],              'packaging'],
            ];
            foreach ($pairs as $pair) {
                $excelVal = $this->normStr($pair[0]);
                $goodsVal = $this->normStr($pair[1]);
                if ($excelVal === '' && $goodsVal === '') {
                    $blankAgree++; // 双方均为空 → 视为一致
                } elseif ($excelVal !== '' && $goodsVal !== '' && $excelVal === $goodsVal) {
                    $matched[] = $pair[2]; // 双方均非空且相等 → 真实匹配
                } else {
                    $disagree = true; // 一侧空一侧有值，或均非空但不等 → 不一致
                }
            }
            if (empty($matched)) {
                continue; // 无任何真实匹配依据（如三项均为空格对空格），不作为候选
            }
            $candidates[] = [
                'goods_id'              => $g['id'],
                'goods_code'            => $g['goods_code'],
                'goods_name'            => $g['goods_name'],
                'goods_specification_id'=> $g['goods_specification_id'],
                'vendor_name'           => $g['vendor_name'],
                'packaging'             => $g['packaging'],
                'product_type'          => $g['product_type'],
                'unit_id'               => $g['unit_id'],
                'matched_fields'        => $matched,
                'match_count'           => count($matched),
                'blank_agree'           => $blankAgree,
                'full_match'            => !$disagree && ($blankAgree + count($matched)) === 3,
            ];
        }

        // 排序：完全一致优先 → 真实匹配数多优先 → id小优先
        usort($candidates, function ($a, $b) {
            if ($b['full_match'] != $a['full_match']) {
                return $b['full_match'] - $a['full_match'];
            }
            if ($b['match_count'] != $a['match_count']) {
                return $b['match_count'] - $a['match_count'];
            }
            return $a['goods_id'] - $b['goods_id'];
        });

        // 状态判定：
        // - matched 自动匹配：有且仅有一个“完全一致”候选（三项全部一致，空格对空格也算一致，
        //   且至少一项真实匹配；如 填1项+另2项双方均空、填2项+1项双方均空）。
        //   多个完全一致（库中存在重复商品）时不自动匹配，须人工“替换”挑选。
        // - pending 待确定：有相似候选但无唯一完全一致项，须人工选择或“新增”。
        // - failed  匹配失败：无任何候选。
        $fullCandidates = array_values(array_filter($candidates, function ($c) {
            return !empty($c['full_match']);
        }));

        $status = 'failed';
        $default = null;
        if (!empty($candidates)) {
            $status = 'pending';
            if (count($fullCandidates) === 1) {
                $status = 'matched';
                $default = $fullCandidates[0];
            }
        }

        return [
            'status'        => $status,
            'default_goods' => $default,
            'candidates'    => array_slice($candidates, 0, 20),
        ];
    }

    /**
     * 定位段落标签所在行（如“父级商品”“子级商品”）
     */
    private function findLabelRow($rows, $label)
    {
        foreach ($rows as $r => $cells) {
            foreach ($cells as $cell) {
                if (trim((string)$cell) === $label) {
                    return $r;
                }
            }
        }
        return 0;
    }

    /**
     * 表头行 → [中文列名 => 0-based列索引] 映射
     */
    private function buildHeaderMap($headerRow)
    {
        $map = [];
        foreach ($headerRow as $idx => $val) {
            if ($val !== '') {
                $map[$val] = $idx;
            }
        }
        return $map;
    }

    /**
     * 取某单元格值（按表头映射）
     */
    private function mapCell($headerMap, $rowCells, $colName)
    {
        if (isset($headerMap[$colName]) && isset($rowCells[$headerMap[$colName]])) {
            return trim((string)$rowCells[$headerMap[$colName]]);
        }
        return '';
    }

    /**
     * 取某单元格值（按表头映射，支持多个候选列名，取第一个在表头中存在的列）
     * 不同模板中“型号”列可能是 型号 / 规格，“厂商”列可能是 厂商 / 制造商 / 规格2/厂商，“封装”列可能是 封装 / 规格3/封装
     */
    private function mapCellAny($headerMap, $rowCells, array $colNames)
    {
        foreach ($colNames as $colName) {
            if (isset($headerMap[$colName])) {
                return isset($rowCells[$headerMap[$colName]]) ? trim((string)$rowCells[$headerMap[$colName]]) : '';
            }
        }
        return '';
    }

    /**
     * 型号/厂商/封装 归一化比较：小写 + 去空白
     */
    private function normStr($s)
    {
        return preg_replace('/\s+/u', '', mb_strtolower(trim((string)$s)));
    }
}
