<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
/**
 * 计量单位换算
 *
 * @icon fa fa-circle-o
 */
class UnitConversion extends Backend
{
    
    /**
     * UnitConversion模型对象
     * @var \app\admin\model\kuerp\UnitConversion
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\UnitConversion;

    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = Db::name('kuerp_unit')
                    ->where($where)
                    ->where(['type'=>'2','is_main'=>'1'])
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $k=>$row) {
                $unit = Db::name('kuerp_unit_conversion')->alias('uc')
                    ->join('kuerp_unit u','u.id=uc.vice_unit')
                    ->where(['uc.main_unit'=>$row['id']])
                    ->order('uc.advance_rate','ASC')
                    ->field('uc.vice_unit,u.name,uc.advance_rate')
                    ->select();
                $row['advance_rate'] = '1';
                foreach ($unit as $unit_k=>$unit_v){
                    $row['name'] .= ','.$unit_v['name'];
                    $row['advance_rate'] .= ':'.$unit_v['advance_rate'];
                }
                $list[$k] = $row;
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $basic_unit     = $params['basic_unit'];
                $sub_unit       = $params['sub_unit'];
                $advance_rate   = $params['advance_rate'];
                $data = [];
                foreach ($sub_unit as $sub_unit_k=>$sub_unit_v){
                    if(isset($advance_rate[$sub_unit_k]) && !empty($advance_rate[$sub_unit_k])){
                        $data[] = [
                            'unit' => $sub_unit_v,
                            'advance_rate' => $advance_rate[$sub_unit_k],
                        ];
                    }
                }
                $result = false;
                Db::startTrans();
                try {
                    if(!empty($basic_unit) && !empty($data)){
                        $result = Db::name('kuerp_unit')->insertGetId([
                            'name'          => $basic_unit,
                            'type'          => '2',
                            'is_main'       => '1',
                            'createtime'    => time(),
                            'updatetime'    => time()
                        ]);
                        if($result){
                            foreach ($data as $data_k=>$data_v){
                                $vice_unit = Db::name('kuerp_unit')->insertGetId([
                                    'name'          => $data_v['unit'],
                                    'type'          => '2',
                                    'is_main'       => '0',
                                    'createtime'    => time(),
                                    'updatetime'    => time()
                                ]);
                                if($vice_unit){
                                    Db::name('kuerp_unit_conversion')->insert([
                                        'unit_name'     => $basic_unit.'/'.$data_v['unit'],
                                        'main_unit'     => $result,
                                        'vice_unit'     => $vice_unit,
                                        'advance_rate'  => $data_v['advance_rate'],
                                        'createtime'    => time(),
                                        'updatetime'    => time()
                                    ]);
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
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        $row = Db::name('kuerp_unit')->where(['id'=>$ids,'type'=>'2','is_main'=>'1'])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $basic_unit     = $params['basic_unit'];
                $basic_unit_id  = $params['basic_unit_id'];
                $sub_unit       = $params['sub_unit'];
                $advance_rate   = $params['advance_rate'];
                $data = [];
                foreach ($sub_unit as $sub_unit_k=>$sub_unit_v){
                    if(isset($advance_rate[$sub_unit_k]) && !empty($advance_rate[$sub_unit_k])){
                        $data[] = [
                            'unit' => $sub_unit_v,
                            'advance_rate' => $advance_rate[$sub_unit_k],
                        ];
                    }
                }
                $uint_id = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$basic_unit_id])->column('vice_unit');
                $goods = Db::name('kuerp_goods')->where(['more_unit_id'=>$basic_unit_id])->find();
                if($goods){
                    $this->error('商品'.$goods['goods_name'].'已关联此单位，不可编辑');
                }
                $result = false;
                Db::startTrans();
                try {
                    if(!empty($basic_unit) && !empty($data)){
                        $result = Db::name('kuerp_unit')->where(['id'=>$basic_unit_id])->update([
                            'name'          => $basic_unit,
                            'type'          => '2',
                            'is_main'       => '1',
                            'updatetime'    => time()
                        ]);
                        if($result){
                            Db::name('kuerp_unit_conversion')->where(['main_unit'=>$basic_unit_id])->delete();
                            Db::name('kuerp_unit')->where(['id'=>['in',$uint_id]])->delete();
                            foreach ($data as $data_k=>$data_v){
                                $vice_unit = Db::name('kuerp_unit')->insertGetId([
                                    'name'          => $data_v['unit'],
                                    'type'          => '2',
                                    'is_main'       => '0',
                                    'createtime'    => time(),
                                    'updatetime'    => time()
                                ]);
                                if($vice_unit){
                                    Db::name('kuerp_unit_conversion')->insert([
                                        'unit_name'     => $basic_unit.'/'.$data_v['unit'],
                                        'main_unit'     => $basic_unit_id,
                                        'vice_unit'     => $vice_unit,
                                        'advance_rate'  => $data_v['advance_rate'],
                                        'createtime'    => time(),
                                        'updatetime'    => time()
                                    ]);
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
                    $this->success('编辑成功');
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $unit = Db::name('kuerp_unit_conversion')->alias('uc')
            ->join('kuerp_unit u','u.id=uc.vice_unit')
            ->where(['uc.main_unit'=>$row['id']])
            ->order('uc.advance_rate','ASC')
            ->field('uc.id,uc.vice_unit,u.name,uc.advance_rate')
            ->select();
        $this->view->assign("row", $row);
        $this->view->assign("unit", $unit);
        return $this->view->fetch();
    }

    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $list = Db::name('kuerp_unit_conversion')->where('main_unit', 'in', $ids)->select();
            foreach ($list as $k => $v) {
                $goods = Db::name('kuerp_goods')->where(['more_unit_id'=>$v['main_unit']])->find();
                if($goods){
                    $this->error('商品'.$goods['goods_name'].'已选择单位'.$v['unit_name'].'，不可删除');
                }
            }
            $list = Db::name('kuerp_unit')->where('id', 'in', $ids)->select();
            $vid = Db::name('kuerp_unit_conversion')->where('main_unit', 'in', $ids)->column('vice_unit');
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += Db::name('kuerp_unit')->where(['id'=>['in',$v['id']]])->delete();
                }
                Db::name('kuerp_unit_conversion')->where('main_unit', 'in', $ids)->delete();
                Db::name('kuerp_unit')->where(['id'=>['in',$vid]])->delete();
                Db::commit();
            }catch (Exception $e) {
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
}
