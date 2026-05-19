<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 序列号盘点
 *
 * @icon fa fa-circle-o
 */
class InventorySerial extends Backend
{
    protected $noNeedRight = ['getserial','period'];
    /**
     * InventorySerial模型对象
     * @var \app\admin\model\kuerp\InventorySerial
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\InventorySerial;
        $this->view->assign("storeTypeList", $this->model->getStoreTypeList());
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

                $row->getRelation('admin')->visible(['username']);
            }
            foreach ($list as $list_k=>$list_v){
                $result = '';
                $code = Db::name('kuerp_warehousing')->where(['order_type'=>'2','order_id'=>$list_v['id']])->column('purchase_code');
                if(!empty($code)){
                    foreach ($code as $code_k=>$code_v){
                        $result .= '盘盈单：'.$code_v.'<br>';
                    }
                }
                $code = Db::name('kuerp_warehouse')->where(['order_type'=>'2','order_id'=>$list_v['id']])->column('purchase_code');
                if(!empty($code)){
                    foreach ($code as $code_k=>$code_v){
                        $result .= '盘亏单：'.$code_v.'<br>';
                    }
                }
                if($result){
                    $list[$list_k]['result'] = $result;
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($ids = null,$stores_id='',$goods_code='')
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $code = \addons\kuerp\library\Goods::getCodes('',16);
                $purchase_time = time();
                $data = [];
                $invertory = 0;$profit = 0;
                foreach ($params['goods_id'] as $k=>$v){
                    if(!empty($v)){//有产品
                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $code,
                            'purchase_time'     =>  $purchase_time,

                            'goods_type'        =>  $params['goods_type'][$k]===''?null:(float)$params['goods_type'][$k],//商品类别
                            'goods_id'          =>  $params['goods_id'][$k]===''?null:(float)$params['goods_id'][$k],
                            'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                            'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                            'stock'             =>  $params['stock'][$k]===''?null:(float)$params['stock'][$k],
                            'invertory_stock'   =>  $params['invertory_stock'][$k]===''?null:(float)$params['invertory_stock'][$k],
                            'period_period'     =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],

                            'matching'          =>  $params['matching'][$k]===''?null:(float)$params['matching'][$k],
                            'inventory'         =>  $params['inventory'][$k]===''?null:(float)$params['inventory'][$k],
                            'loss'              =>  $params['stock'][$k]===''?null:(float)$params['loss'][$k],

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                        if($params['stock'][$k]<$params['invertory_stock'][$k]){
                            $invertory += (float)($params['invertory_stock'][$k]-$params['stock'][$k]);
                        }else if($params['stock'][$k]>$params['invertory_stock'][$k]){
                            $profit += (float)($params['invertory_stock'][$k]-$params['stock'][$k]);
                        }
                    }
                }
                if(!empty($data)){
                    $list = Db::name('kuerp_goods_stock')->alias('gs')
                        ->join('kuerp_goods g','g.id=gs.goods_id')
                        ->where(['g.serial'=>'1'])
                        ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->order('gs.goods_code ASC')
                        ->select();
                    if(sizeof($list)==sizeof($data)){
                        $store_type = 1;
                    }else{
                        $store_type = 2;
                    }
                    $result = '';
                    if($invertory>0){
                        $result .= ' 盘盈：'.$invertory.';';
                    }
                    if($profit<0){
                        $result .= ' 盘亏：'.$profit.';';
                    }
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     =>  $code,
                        'purchase_time'     =>  $purchase_time,
                        'store_type'        => $store_type,
                        'result'            => $result,
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ]);
                }
                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->insertGetId($row);
                    }
                    if(!empty($data)){
                        Db::name('kuerp_inventory_serial_info')->insertAll($data);
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
        $where = [];
        if(!empty($stores_id)){
            $where['gs.stores_id'] = $stores_id;
        }
        if(!empty($goods_code)){
            $where['gs.goods_code'] = ['like','%'.$goods_code.'%'];
        }
        $list = Db::name('kuerp_goods_stock')->alias('gs')
            ->join('kuerp_goods g','g.id=gs.goods_id')
            ->where(['g.serial'=>'1'])
            ->where($where)
            ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
            ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
            ->order('gs.goods_code ASC')
            ->select();

        foreach ($list as $list_k=>$list_v){
            $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('id,goods_name,goods_code,note,goods_type_id,goods_specification_id,unit_id')->find();
            $goods_type = Db::name('kuerp_goods_type')->where(['id'=>$goods['goods_type_id']])->field('id,supplier_type_name')->find();
            $stores = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('id,name')->find();
            $list[$list_k]['goods'] = $goods;
            $list[$list_k]['goods_type'] = $goods_type;
            $list[$list_k]['stores'] = $stores;

            $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['attribute_id']])->value('attribute_id');
            $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
            if($attribute_ids){
                $attribute_ids = explode('&',$attribute_ids);
                $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                $attribute_name = implode('/',$attribute);
                $list[$list_k]['attr_name'] = $attribute_name;
            }else{
                $list[$list_k]['attr_name'] = '';
            }

            $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_code'=>$list_v['goods_code'],'attribute_id'=>$list_v['attribute_id']])->value('id');
            $list[$list_k]['attr_id'] = $goods_attr;

            $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->value('name');
            $list[$list_k]['unit_name'] = $unit;
        }
        $this->view->assign("list", $list);

        $kuerp_stores = Db::name('kuerp_stores')->where(['id'=>$stores_id])->field('id,name')->find();
        $this->assignconfig("kuerp_stores", $kuerp_stores);
        $this->view->assign("goods_code", $goods_code);
        return $this->view->fetch();
    }

    public function edit($ids = null,$stores_id='',$goods_code='')
    {
        $old_row = $row = Db::name('kuerp_inventory_serial')->where(['id'=>$ids])->find();
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
                $data = [];
                $invertory = 0;$profit = 0;
                foreach ($params['goods_id'] as $k=>$v){
                    if(!empty($v)){//有产品
                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $old_row['purchase_code'],
                            'purchase_time'     =>  $old_row['purchase_time'],

                            'goods_type'        =>  empty($params['goods_type'][$k])?null:$params['goods_type'][$k],//商品类别
                            'goods_id'          =>  empty($params['goods_id'][$k])?null:$params['goods_id'][$k],
                            'stores_id'         =>  empty($params['stores_id'][$k])?null:$params['stores_id'][$k],
                            'goods_attr'        =>  empty($params['goods_attr'][$k])?null:$params['goods_attr'][$k],
                            'stock'             =>  empty($params['stock'][$k])?null:$params['stock'][$k],
                            'invertory_stock'   =>  $params['invertory_stock'][$k]===''?null:(float)$params['invertory_stock'][$k],
                            'period_period'     =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],

                            'matching'          =>  $params['matching'][$k]===''?null:(float)$params['matching'][$k],
                            'inventory'         =>  $params['inventory'][$k]===''?null:(float)$params['inventory'][$k],
                            'loss'              =>  $params['stock'][$k]===''?null:(float)$params['loss'][$k],

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                        if($params['stock'][$k]<$params['invertory_stock'][$k]){
                            $invertory += (float)($params['invertory_stock'][$k]-$params['stock'][$k]);
                        }else if($params['stock'][$k]>$params['invertory_stock'][$k]){
                            $profit += (float)($params['invertory_stock'][$k]-$params['stock'][$k]);
                        }
                    }
                }
                if(!empty($data)){
                    $list = Db::name('kuerp_goods_stock')->alias('gs')
                        ->join('kuerp_goods g','g.id=gs.goods_id')
                        ->where(['g.serial'=>'1'])
                        ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->order('gs.goods_code ASC')
                        ->select();
                    if(sizeof($list)==sizeof($data)){
                        $store_type = 1;
                    }else{
                        $store_type = 2;
                    }
                    $result = '';
                    if($invertory>0){
                        $result .= ' 盘盈：'.$invertory.';';
                    }
                    if($profit<0){
                        $result .= ' 盘亏：'.$profit.';';
                    }
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'store_type'        => $store_type,
                        'result'            => $result,
                        'updatetime'        => time(),
                    ]);
                }

                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($row);
                    }
                    Db::name('kuerp_inventory_serial_info')->where(['purchase_code'=>$old_row['purchase_code']])->delete();
                    if(!empty($data)){
                        Db::name('kuerp_inventory_serial_info')->insertAll($data);
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
        $where = [];
        if(!empty($stores_id)){
            $where['stores_id'] = $stores_id;
        }
        if(!empty($goods_code)){
            $where['goods_code'] = ['like','%'.$goods_code.'%'];
        }
        $list = Db::name('kuerp_inventory_serial_info')->where($where)->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($list as $list_k=>$list_v){
            $goods = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])->field('id,goods_name,goods_code,note,goods_type_id,goods_specification_id,unit_id')->find();
            $goods_type = Db::name('kuerp_goods_type')->where(['id'=>$goods['goods_type_id']])->field('id,supplier_type_name')->find();
            $stores = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('id,name')->find();
            $list[$list_k]['goods'] = $goods;
            $list[$list_k]['goods_type'] = $goods_type;
            $list[$list_k]['stores'] = $stores;

            $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['goods_attr']])->value('attribute_id');
            $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
            if($attribute_ids){
                $attribute_ids = explode('&',$attribute_ids);
                $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                $attribute_name = implode('/',$attribute);
                $list[$list_k]['attr_name'] = $attribute_name;
            }else{
                $list[$list_k]['attr_name'] = '';
            }

            $list[$list_k]['attr_id'] = $list_v['goods_attr'];

            $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->value('name');
            $list[$list_k]['unit_name'] = $unit;
        }
        $row['info'] = $list;
        $this->view->assign("row", $row);

        $kuerp_stores = Db::name('kuerp_stores')->where(['id'=>$stores_id])->field('id,name')->find();
        $this->assignconfig("kuerp_stores", $kuerp_stores);
        $this->view->assign("goods_code", $goods_code);
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
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_inventory_serial_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
                }
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

    /**
     * 获取可用序列号
     * @param string goods_code 商品编号
     * @param string stores_id 仓库ID
     * @param string attribute_id 属性id
     * @param string purchase_code 购货单单号
     * @return array
     */
    public function getSerial(){
        $data = input();
        $goods_code    = isset($data['goods_code'])?$data['goods_code']:'';
        $stores_id     = isset($data['stores_id'])?$data['stores_id']:'';
        $attribute_id  = isset($data['attribute_id'])?$data['attribute_id']:'';
        $purchase_code = isset($data['purchase_code'])?$data['purchase_code']:'';
        $where = [];
        $where['store_status'] = '1';
        $where['goods_code'] = $goods_code;
        $where['stores_id'] = $stores_id;
        if($purchase_code){
            $where['purchase_code'] = $purchase_code;
        }
        if($attribute_id){
            $where['attribute_id'] = $attribute_id;
        }
        $serial = Db::name('kuerp_goods_serial')->where($where)->field('serial,remark,status')->select();
        return json_encode(['list'=>$serial]);
    }
    public function period(){
        if ($this->request->isPost()) {
            $data = input();
            $goods_code    = isset($data['goods_code'])?$data['goods_code']:'';
            $stores_id     = isset($data['stores_id'])?$data['stores_id']:'';
            $attribute_id  = isset($data['attribute_id'])?$data['attribute_id']:'';
            $where = [];
            $where['store_status'] = '1';
            $where['goods_code'] = $goods_code;
            $where['stores_id'] = $stores_id;
            if($attribute_id){
                $where['attribute_id'] = $attribute_id;
            }
            $serial = Db::name('kuerp_goods_serial')->where($where)->column('serial');

            $serial_arr = [];$serial_data = [];
            $matching = 0;$inventory=0;$loss=0;
            if(isset($data['serial_pds'])){
                $serial_pds = json_decode($data['serial_pds']);
                foreach ($serial as $serial_k=>$serial_v){
                    $status = true;
                    foreach ($serial_pds as $serial_pds_k=>$serial_pds_v){
                        if($serial_v==$serial_pds_v){//匹配
                            $matching+=1;
                            $status = false;
                            $serial_arr[] = ['serial'=>$serial_pds_v,'status'=>1];
                        }
                    }
                    if($status){
                        $loss+=1;
                        $serial_arr[] = ['serial'=>$serial_pds_v,'status'=>3];
                    }
                }
                foreach ($serial_pds as $serial_pds_k=>$serial_pds_v){
                    if(!in_array($serial_pds_v,$serial)){
                        $inventory+=1;
                        $serial_arr[] = ['serial'=>$serial_pds_v,'status'=>2];
                    }
                }
                foreach ($serial_pds as $serial_pds_k=>$serial_pds_v){
                    foreach ($serial_arr as $serial_arr_k=>$serial_arr_v){
                        if($serial_arr_v['serial']==$serial_pds_v){
                            $serial_data[] = ['serial'=>$serial_pds_v,'status'=>$serial_arr_v['status']];
                        }
                    }
                }
            }

            $this->success(['num'=>($matching+$inventory),'ser'=>serialize($serial_data),'matching'=>$matching,'inventory'=>$inventory,'loss'=>$loss]);
        }
        return $this->view->fetch();
    }
}
