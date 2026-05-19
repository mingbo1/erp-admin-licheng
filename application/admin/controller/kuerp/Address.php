<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
/**
 * 收货地址管理
 *
 * @icon fa fa-circle-o
 */
class Address extends Backend
{
    protected $noNeedRight = ['area'];
    /**
     * Address模型对象
     * @var \app\admin\model\kuerp\Address
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Address;
        $this->view->assign("isDefaultList", $this->model->getIsDefaultList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if($params['is_default']==1){
                    if($this->model->where(['is_default'=>1])->find()){
                        $this->error('默认地址只能设置一个');
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->insertGetId($params);
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
            if ($params) {
                $params = $this->preExcludeFields($params);

                if($params['is_default']==1){
                    if($this->model->where(['is_default'=>1,'id'=>['<>',$ids]])->find()){
                        $this->error('默认地址只能设置一个');
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->where(['id'=>$ids])->update($params);
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
        return $this->view->fetch();
    }

    /**
     * 读取省市区数据,联动列表
     */
    public function area()
    {
        $params = $this->request->get("row/a");
        if (!empty($params)) {
            $province = isset($params['province']) ? $params['province'] : '';
            $city = isset($params['city']) ? $params['city'] : '';
        } else {
            $province = $this->request->get('province', '');
            $city = $this->request->get('city', '');
        }
        $where = ['pid' => 0, 'level' => 1];
        $provincelist = null;
        if ($province !== '') {
            $where['pid'] = $province;
            $where['level'] = 2;
            if ($city !== '') {
                $where['pid'] = $city;
                $where['level'] = 3;
            }
        }
        $provincelist = Db::name('kuerp_area')->where($where)->field('id as value,name')->select();
        $this->success('', '', $provincelist);
    }
}
