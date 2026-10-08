<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 成本调整单
 *
 * @icon fa fa-circle-o
 */
class Adjustment extends Backend
{
    
    /**
     * Adjustment模型对象
     * @var \app\admin\model\kuerp\Adjustment
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Adjustment;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

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
                $admin = Session::get('admin');
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                if(empty($params['purchase_time'])){
                    $this->error('请填写单据日期');
                }

                $data = [];
                $price = 0;
                $goods_stock_log = [];
                foreach ($params['goods_id'] as $k=>$v){
                    if(!empty($v)){//有产品ID
                        $price+=$params['price'][$k];
                        if(!isset($params['price'][$k]) || empty($params['price'][$k])){
                            $this->error('请输入调整价格');
                        }else if(!isset($params['goods_unit'][$k]) || empty($params['goods_unit'][$k])){
                            $this->error('请选择单位');
                        }else if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }
                        $goods = Db::name('kuerp_goods')->where(['id'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }

                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'goods_id'          =>  $v,//商品信息
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],
                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'money'             =>  (float)$params['price'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);

                        $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code' => $params['purchase_code'],
                            'purchase_id'   => '',
                            'purchase_time' => strtotime($params['purchase_time']),
                            'business_type' => '7',
                            'goods_id'      => $v,
                            'goods_code'    => $goods['goods_code'],
                            'goods_unit'    => (float)$params['goods_unit'][$k],
                            'stores_id'     => (float)$params['stores_id'][$k],
                            'remark'        => $params['remark'][$k],
                            'enter_cost'    => (float)$params['price'][$k],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                    }
                }
                if(!empty($data)){
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'business_type'     =>  '1',
                        'money'             =>  $price,
                        'preparation_id'    =>  $admin['id'],
                        'remark'            =>  $params['note'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ]);
                }
                if($this->model->where(['purchase_code'=>trim($params['purchase_code'])])->find()){
                    $this->error('单据编号已占用');
                }
                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->insertGetId($row);
                    }
                    if($goods_stock_log){
                        foreach ($goods_stock_log as $goods_stock_log_k=>$goods_stock_log_v){
                            $goods_stock_log[$goods_stock_log_k]['purchase_id'] = $result;
                        }
                    }
                    Db::name('kuerp_adjustment_info')->where(['purchase_code'=>$params['purchase_code']])->delete();
                    Db::name('kuerp_adjustment_info')->insertAll($data);
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
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
        $code = \addons\kuerp\library\Goods::getCodes('',14);
        $this->view->assign("code", $code);
        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        $row = $this->model->where(['id'=>$ids])->find();
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
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                $params = $this->preExcludeFields($params);
                $admin = Session::get('admin');

                $purchase_old_code = $params['purchase_old_code'];
                unset($params['purchase_old_code']);
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                if(empty($params['purchase_time'])){
                    $this->error('请填写单据日期');
                }

                $data = [];
                $price = 0;
                $goods_stock_log = [];
                foreach ($params['goods_id'] as $k=>$v){
                    if(!empty($v)){//有产品ID
                        $price+=$params['price'][$k];
                        if(!isset($params['price'][$k]) || empty($params['price'][$k])){
                            $this->error('请输入调整价格');
                        }else if(!isset($params['goods_unit'][$k]) || empty($params['goods_unit'][$k])){
                            $this->error('请选择单位');
                        }else if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }
                        $goods = Db::name('kuerp_goods')->where(['id'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }

                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'goods_id'          =>  $v,//商品信息
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],
                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'money'             =>  (float)$params['price'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);

                        $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code' => $params['purchase_code'],
                            'purchase_id'   => '',
                            'purchase_time' => strtotime($params['purchase_time']),
                            'business_type' => '7',
                            'goods_id'      => $v,
                            'goods_code'    => $goods['goods_code'],
                            'goods_unit'    => (float)$params['goods_unit'][$k],
                            'stores_id'     => (float)$params['stores_id'][$k],
                            'remark'        => $params['remark'][$k],
                            'enter_cost'    => (float)$params['price'][$k],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                    }
                }
                if(!empty($data)){
                    $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'business_type'     =>  '1',
                        'money'             =>  $price,
                        'preparation_id'    =>  $admin['id'],
                        'remark'            =>  $params['note'],
                        'updatetime'        => time(),
                    ]);
                }
                if($this->model->where(['id'=>['<>',$ids],'purchase_code'=>trim($params['purchase_code'])])->find()){
                    $this->error('单据编号已占用');
                }
                $info_ids = Db::name('kuerp_adjustment_info')->where(['purchase_code'=>$purchase_old_code])->column('id');
                $result = false;
                Db::startTrans();
                try {
                    if(!empty($rowdata)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    if($goods_stock_log){
                        foreach ($goods_stock_log as $goods_stock_log_k=>$goods_stock_log_v){
                            $goods_stock_log[$goods_stock_log_k]['purchase_id'] = $ids;
                        }
                    }
                    Db::name('kuerp_adjustment_info')->where(['purchase_code'=>$row['purchase_code']])->delete();
                    Db::name('kuerp_goods_log')->where(['business_type'=>'7','purchase_id'=>['in',$info_ids]])->delete();
                    Db::name('kuerp_adjustment_info')->insertAll($data);
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
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

        $this->view->assign("row", $row);

        $adjustment_info = Db::name('kuerp_adjustment_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($adjustment_info as $adjustment_info_k=>$adjustment_info_v){
            $goods = Db::name('kuerp_goods')->where(['id'=>$adjustment_info_v['goods_id']])->find();
            $adjustment_info[$adjustment_info_k]['unit'] = json_decode((new GoodsPurchase())->getUnit($goods['goods_code']),true);
        }
        $this->view->assign("adjustment_info", $adjustment_info);
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
            $goods_purchase_code = [];
            foreach ($list as $list_k=>$list_v){
                if(!in_array($list_v['purchase_code'],$goods_purchase_code)){
                    $goods_purchase_code[] = $list_v['purchase_code'];
                }
            }
            $info_id = Db::name('kuerp_adjustment')->where(['purchase_code'=>['in',$goods_purchase_code]])->column('id');
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_adjustment_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
                }
                Db::name('kuerp_goods_log')->where(['business_type'=>'7','purchase_id'=>['in',$info_id]])->delete();
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
