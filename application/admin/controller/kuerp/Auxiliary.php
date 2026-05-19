<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use fast\Tree;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
/**
 * 商品辅助属性
 *
 * @icon fa fa-circle-o
 */
class Auxiliary extends Backend
{
    
    /**
     * Auxiliary模型对象
     * @var \app\admin\model\kuerp\Auxiliary
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Auxiliary;
        $this->view->assign("statusList", $this->model->getStatusList());

        $this->model = new \app\admin\model\kuerp\Auxiliary();

        $tree = Tree::instance();
        $tree->init(collection($this->model->order('weigh desc,id desc')->field('id,pid,status,weigh,name as title,image,createtime')->select())->toArray(), 'pid');
        $this->catelist = $tree->getTreeList($tree->getTreeArray(0), 'title');
        $catedata = [0 => ['type' => 'all', 'title' => __('None')]];
        foreach ($this->catelist as $k => $v) {
            $catedata[$v['id']] = $v;
        }
        $this->view->assign("parentList", $catedata);
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
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $search = $this->request->request("search");
            $type = $this->request->request("type");

            //构造父类select列表选项数据
            $list = [];
            foreach ($this->catelist as $k => $v) {
                if ($search) {
                    if ($v['type'] == $type && stripos($v['title'], $search) !== false) {
                        if ($type == "all" || $type == null) {
                            $list = $this->catelist;
                        } else {
                            $list[] = $v;
                        }
                    }
                } else {
                    if ($type == "all" || $type == null) {
                        $list = $this->catelist;
                    } elseif ($v['type'] == $type) {
                        $list[] = $v;
                    }
                }
            }

            $total = count($list);
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if($params['pid']>0){
                $row = $this->model->find($params['pid']);
                if(empty($row)){
                    $this->error('分类不存在');
                }
                $params['path'] = $row['path'].$params['pid'].',';
            }else{
                $params['path'] = ',0,';
            }
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
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
            $params = $this->request->post("row/a");
            if($params['pid']>0){
                if($params['pid']==$ids){
                    $this->error('不能选择自己作为父级');
                }
                $prow = $this->model->find($params['pid']);
                if(empty($prow)){
                    $this->error('分类不存在');
                }
                if(strpos($prow['path'],','.$ids.',') !== false){
                    $this->error('不能选择自己的下级作为父级');
                }
                $params['path'] = $prow['path'].$params['pid'].',';
            }else{
                $params['path'] = ',0,';
            }
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
