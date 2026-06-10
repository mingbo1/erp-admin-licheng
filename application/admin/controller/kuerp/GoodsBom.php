<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品BOM模板
 *
 * @icon fa fa-cubes
 */
class GoodsBom extends Backend
{
    protected $noNeedRight = ['getbomdetail', 'chosegoods'];
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
                $row->getRelation('admin')->visible(['username']);
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
                // 补全父商品的 product_type
                $parentGoods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->field('goods_name,product_type')->find();
                if ($parentGoods) {
                    $parent['goods_name'] = $parentGoods['goods_name'];
                    $parent['product_type'] = $parentGoods['product_type'];
                }
            } else {
                // 获取子商品信息用于回显
                $goods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->field('goods_name,product_type,unit_id')->find();
                $info['goods_name_display'] = $goods ? $goods['goods_name'] : '';
                $info['product_type'] = $goods ? $goods['product_type'] : '';
                $info['unit_id'] = $info['unit_id'] ?: ($goods ? $goods['unit_id'] : 0);
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
            ->field('a.*,g.goods_name as parent_name,g.product_type')
            ->find();
        // 子商品
        $children = Db::name('kuerp_goods_bom_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.bom_id' => $id, 'a.type' => '2'])
            ->field('a.*,g.goods_name as goods_name,g.product_type,g.inventory')
            ->select();
        $this->success('', ['bom' => $bom, 'parent' => $parent, 'children' => $children]);
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
        $this->success('', $list);
    }
}
