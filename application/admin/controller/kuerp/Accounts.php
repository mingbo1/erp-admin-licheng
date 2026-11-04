<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
/**
 * 账户管理
 *
 * @icon fa fa-circle-o
 */
class Accounts extends Backend
{
    
    /**
     * Accounts模型对象
     * @var \app\admin\model\kuerp\Accounts
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Accounts;
        $this->view->assign("accountTypeIdList", $this->model->getAccountTypeIdList());
        $this->view->assign("isDefaultList", $this->model->getIsDefaultList());
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
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
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

                if(Db::name('kuerp_accounts')->where(['account_coding'=>$params['account_coding']])->find()){
                    $this->error('账户编号已被使用');
                }
                if($params['is_default']==1){
                    if(Db::name('kuerp_accounts')->where(['is_default'=>1])->find()){
                        $this->error('默认账号只能设置一个');
                    }
                }

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
        $code = \addons\kuerp\library\Goods::getCodes('',5);
        $this->view->assign("code", $code);
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

                if(Db::name('kuerp_accounts')->where(['account_coding'=>$params['account_coding'],'id'=>['<>',$ids]])->find()){
                    $this->error('账户编号已被使用');
                }
                if($params['is_default']==1){
                    if(Db::name('kuerp_accounts')->where(['is_default'=>1,'id'=>['<>',$ids]])->find()){
                        $this->error('默认账号只能设置一个');
                    }
                }

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

        $user = Db::name('kuerp_suppliers_user')->where(['sid'=>$ids])->select();
        $this->view->assign("user", $user);
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

            foreach ($list as $k => $v) {
                $arr = [
                    'kuerp_goods_purchase' => '采购单',
                    'kuerp_goods_exchange' => '购货退货单',
                    'kuerp_goods_sales' => '销货单',
                    'kuerp_goods_sales_exchange' => '销货退货单',
                    'kuerp_payment_account' => '付款单',
                    'kuerp_collection_account' => '收款单',
                    'kuerp_income' => '其他收入单',
                    'kuerp_expenditure' => '其他支出单',
                    'kuerp_accounts_transfer_info' => '资金转账单',
                ];
                $field = [
                    'kuerp_goods_purchase' =>'account',
                    'kuerp_goods_exchange' =>'account',
                    'kuerp_goods_sales' =>'account',
                    'kuerp_goods_sales_exchange' =>'account',
                    'kuerp_accounts_transfer_info' => ['accounts_out_id','accounts_enter_id'],
                ];
                foreach ($arr as $arr_k=>$arr_v){
                    if(isset($field[$arr_k])){
                        if(is_array($field[$arr_k])){
                            foreach ($field[$arr_k] as $fk=>$fv){
                                $order =Db::name($arr_k)->where([$fv=>$v['id']])->find();
                            }
                        }else{
                            $order =Db::name($arr_k)->where([$field[$arr_k]=>$v['id']])->find();
                        }
                    }else{
                        $order =Db::name($arr_k)->where(['accounts_id'=>$v['id']])->find();
                    }
                    if($order){
                        $this->error('账户'.$v['account_name'].'已产生'.$arr_v.'，不可删除');
                    }
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
            }catch (\Exception $e) {
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
