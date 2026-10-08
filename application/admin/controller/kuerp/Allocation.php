<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 调拨单
 *
 * @icon fa fa-circle-o
 */
class Allocation extends Backend
{
    protected $noNeedRight = ['getunit','getserial'];
    /**
     * Allocation模型对象
     * @var \app\admin\model\kuerp\Allocation
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Allocation;
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

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

            $wheres = [];
            $searchdata = json_decode(input('filter'),true);
            if($searchdata){
                foreach ($searchdata as $k => &$v) {
                    if(!empty($v)){
                        if(strpos($v,' - ') !== false && strpos($k,'time') !== false){
                            $str = explode(' - ',$v);
                            $wheres['allocation.'.$k] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                        }else{
                            $wheres['allocation.'.$k] = ["LIKE", "%{$v}%"];
                        }
                    }
                }
            }

            $list = $this->model
                ->with(['admin'])
                ->where($where)->where($wheres)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $row) {
                $row->getRelation('admin')->visible(['nickname']);
            }

            $store = Db::name('kuerp_stores')->column('name','id');
            $lists = [];
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list_v['print_num'] = 0;
                }
                $info = Db::name('kuerp_allocation_info')->alias('ai')
                    ->join('kuerp_goods g','g.id=ai.goods_id')
                    ->join('kuerp_goods_type gt','gt.id=g.goods_type_id')
                    ->join('kuerp_unit u','u.id=ai.goods_unit')
                    ->where(['ai.allocation_id'=>$list_v['id']])
                    ->field('gt.supplier_type_name as type_name,g.goods_name,ai.goods_note,ai.number,u.name as unit_name,ai.remarks as remark,ai.stores_out,ai.stores_enter')
                    ->select();
                $list_v['preparation_id'] = Db::name('admin')->where(['id'=>$list_v['preparation_id']])->value('nickname');
                $list_v['operation_id']   = Db::name('admin')->where(['id'=>$list_v['operation_id']])->value('nickname');
                $list_v['examine_id']   = Db::name('admin')->where(['id'=>$list_v['examine_id']])->value('nickname');
                foreach ($info as $k=>$v){
                    $v['stores_out'] = isset($store[$v['stores_out']])?$store[$v['stores_out']]:'';
                    $v['stores_enter'] = isset($store[$v['stores_enter']])?$store[$v['stores_enter']]:'';
                    $v['id'] = $list_v['id'];
                    $v['purchase_code'] = $list_v['purchase_code'];
                    $v['purchase_time'] = $list_v['purchase_time'];
                    $v['preparation_id'] = $list_v['preparation_id'];
                    $v['operation_id'] = $list_v['operation_id'];
                    $v['examine_id'] = $list_v['examine_id'];
                    $v['print_num'] = $list_v['print_num'];
                    $v['remarks'] = $list_v['remarks'];
                    $lists[] = $v;
                }
            }
            $result = array("total" => $list->total(), "rows" => $lists);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if ($params) {
                $params = $this->preExcludeFields($params);
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $goods_serial_all = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        if(!isset($params['stores_out'][$k]) || empty($params['stores_out'][$k])){
                            $this->error('请选择调出仓库');
                        }else if(!isset($params['stores_enter'][$k]) || empty($params['stores_enter'][$k])){
                            $this->error('请选择调入仓库');
                        }else if($params['stores_out'][$k]==$params['stores_enter'][$k]){
                            $this->error('调出仓库不能和调入仓库相同');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();
                            if(empty($batch_data)){
                                $this->error('生产批次不存在');
                            }
                        }

                        $serial_code = [];
                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $period_period = unserialize($params['period_period'][$k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                if(in_array($period_period_v['serial'],$goods_serial_all)){
                                    $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                }else{
                                    $goods_serial_all[] = $period_period_v['serial'];
                                }
                                $serial_code[] = $period_period_v;
                            }
                        }
                        $data[] = [
                            'goods_id'          =>  $goods['id'],//商品信息
                            'goods_attr'        =>  (float)$params['goods_attr'][$k],//商品属性
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],//商品单位
                            'stores_out'        =>  (float)$params['stores_out'][$k],//调出仓库
                            'stores_enter'      =>  (float)$params['stores_enter'][$k],//调入仓库
                            'number'            =>  (float)$params['number'][$k],//数量
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'goods_batch_id'    =>  (float)$params['batch_id'][$k],
                            'goods_batch_name'  =>  $params['batch'][$k],
                            'goods_serial_id'   =>  !empty($serial_code)?serialize($serial_code):null,
                            'remarks'           =>  $params['remark'][$k],
                            'goods_note'        =>  $params['goods_note'][$k],
                        ];
                    }
                }
                if(!empty($data)){
                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'remarks'           =>  $params['note'],//备注
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code'])])->find()){
                    $this->error('单据编号已占用');
                }
                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->insertGetId(\addons\kuerp\library\Goods::setDataInfo($row));
                    }
                    if($result){
                        foreach ($data as $data_k=>$data_v){
                            $data_v['allocation_id'] = $result;
                            $data_v = \addons\kuerp\library\Goods::setDataInfo($data_v);
                            $data[$data_k] = $data_v;
                        }
                        Db::name('kuerp_allocation_info')->insertAll($data);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    if($examine!=1){
                        try {
                            $exa = $this->examine($result,1);
                        } catch (Exception $e) {
                        }
                        if(!isset($exa) || $exa['status']!=1){
                            $exa = $this->examine($result,1);
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
        $code = \addons\kuerp\library\Goods::getCodes('',13);
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
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
            if($checkout!==true){
                $this->error($checkout);
            }
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if($row['examine_status']==1 && $examine==1){
                $this->error('不能编辑已审核的数据');
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $goods_serial_all = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        if(!isset($params['stores_out'][$k]) || empty($params['stores_out'][$k])){
                            $this->error('请选择调出仓库');
                        }else if(!isset($params['stores_enter'][$k]) || empty($params['stores_enter'][$k])){
                            $this->error('请选择调入仓库');
                        }else if($params['stores_out'][$k]==$params['stores_enter'][$k]){
                            $this->error('调出仓库不能和调入仓库相同');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();
                            if(empty($batch_data)){
                                $this->error('生产批次不存在');
                            }
                        }

                        $serial_code = [];
                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $period_period = unserialize($params['period_period'][$k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                if(in_array($period_period_v['serial'],$goods_serial_all)){
                                    $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                }else{
                                    $goods_serial_all[] = $period_period_v['serial'];
                                }
                                $serial_code[] = $period_period_v;
                            }
                        }
                        $data[] = [
                            'goods_id'          =>  $goods['id'],//商品信息
                            'goods_attr'        =>  (float)$params['goods_attr'][$k],//商品属性
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],//商品单位
                            'stores_out'        =>  (float)$params['stores_out'][$k],//调出仓库
                            'stores_enter'      =>  (float)$params['stores_enter'][$k],//调入仓库
                            'number'            =>  (float)$params['number'][$k],//数量
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'goods_batch_id'    =>  (float)$params['batch_id'][$k],
                            'goods_batch_name'  =>  $params['batch'][$k],
                            'goods_serial_id'   =>  !empty($serial_code)?serialize($serial_code):null,
                            'remarks'           =>  $params['remark'][$k],
                            'goods_note'        =>  $params['goods_note'][$k],
                        ];
                    }
                }
                if(!empty($data)){
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'remarks'           =>  $params['note'],//备注
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }
                $result = false;
                if($examine!=1 && $row['examine_status']==1){
                    $this->examine($ids,2);
                }
                Db::startTrans();
                try {
                    $result = $this->model->where(['id'=>$ids])->update($rowdata);

                    if($result){
                        Db::name('kuerp_allocation_info')->where(['allocation_id'=>$ids])->delete();
                        foreach ($data as $data_k=>$data_v){
                            $data_v['allocation_id'] = $ids;
                            $data_v = \addons\kuerp\library\Goods::setDataInfo($data_v);
                            $data[$data_k] = $data_v;
                        }
                        Db::name('kuerp_allocation_info')->insertAll($data);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    if($examine!=1){
                        try {
                            $exa = $this->examine($ids,1);
                        } catch (Exception $e) {
                        }
                        if(!isset($exa) || $exa['status']!=1){
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

        $info = Db::name('kuerp_allocation_info')->where(['allocation_id'=>$ids])->select();
        foreach ($info as $info_k=>$info_v){
            $info[$info_k]['unit'] = json_decode($this->getUnit('','','',$info_v['goods_id']),true);
            if(!empty($info_v['goods_serial_id'])){
                $info[$info_k]['serial'] = serialize($info_v['goods_serial_id']);
            }
            if(!empty($info_v['goods_batch_id'])){
                $goods_batch = Db::name('kuerp_goods_batch')->where(['id'=>$info_v['goods_batch_id']])->field('manufacture,guarantee,validity')->find();
                if($goods_batch){
                    $info[$info_k]['manufacture'] = $goods_batch['manufacture'];
                    $info[$info_k]['guarantee'] = $goods_batch['guarantee'];
                    $info[$info_k]['validity'] = $goods_batch['validity'];
                }
            }
        }
        $this->view->assign("info_list", $info);

        //审核禁止输入
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine_set==1 && $row['examine_status']==1){
            $this->assign('exclass','hiddens');
        }
        $this->assignconfig("examine_status", $row['examine_status']);
        return $this->view->fetch();
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
        // 制单人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname');

        // 获取调拨单详情
        $info_list = Db::name('kuerp_allocation_info')
            ->where(['allocation_id' => $ids])
            ->select();

        foreach ($info_list as $k => $v) {
            $info_list[$k]['goods_name'] = Db::name('kuerp_goods')
                ->where(['id' => $v['goods_id']])
                ->value('goods_name');
            $info_list[$k]['goods_code'] = Db::name('kuerp_goods')
                ->where(['id' => $v['goods_id']])
                ->value('goods_code');
            $info_list[$k]['stores_out_name'] = Db::name('kuerp_stores')
                ->where(['id' => $v['stores_out']])
                ->value('name');
            $info_list[$k]['stores_enter_name'] = Db::name('kuerp_stores')
                ->where(['id' => $v['stores_enter']])
                ->value('name');
            $info_list[$k]['unit_name'] = Db::name('kuerp_unit')
                ->where(['id' => $v['goods_unit']])
                ->value('name');
            // 属性名称
            $info_list[$k]['attr_name'] = '';
            if (!empty($v['goods_attr'])) {
                $attribute_id = Db::name('kuerp_goods_attr')
                    ->where(['id' => $v['goods_attr']])
                    ->value('attribute_id');
                if ($attribute_id) {
                    $attribute_ids = Db::name('kuerp_aux_combination')
                        ->where(['id' => $attribute_id])
                        ->value('attr_ids');
                    if ($attribute_ids) {
                        $attr_ids_arr = explode('&', $attribute_ids);
                        $attr_names = Db::name('kuerp_auxiliary')
                            ->where(['id' => ['in', $attr_ids_arr]])
                            ->column('name');
                        $info_list[$k]['attr_name'] = implode('/', $attr_names);
                    }
                }
            }
        }

        $this->view->assign("row", $row);
        $this->view->assign("info_list", $info_list);

        // 增加打印次数
        $this->model->where(['id' => $ids])->setInc('print_num');

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
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');

            foreach ($list as $list_k=>$list_v){
                if($list_v['examine_status']==1){
                    $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                    if($checkout!==true){
                        $this->error($checkout);
                    }
                }
                if($list_v['examine_status']==1 && $examine==1){
                    $this->error('不能删除已审核的数据');
                }
            }

            $count = 0;
            $error = [];
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    if ($v['examine_status']==1 && $examine!=1){
                        $status = $this->examine($list_v['id'],2);
                        if($status['status']==1){
                            $count += $v->delete();
                            Db::name('kuerp_allocation_info')->where(['allocation_id'=>$v['id']])->delete();
                        }else{
                            $error[] = $v['purchase_code'];
                        }
                    }else{
                        $count += $v->delete();
                        Db::name('kuerp_allocation_info')->where(['allocation_id'=>$v['id']])->delete();
                    }
                }
                Db::commit();
            }catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $msg = $count.'条数据删除成功';
                if(!empty($error)){
                    $error = implode(',',$error);
                    $msg .= $error.'删除失败';
                }
                $this->success($msg);
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    //审核/反审核
    public function examine($ids = null,$examine=1){
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            if($examine_set!=1){
                return ['status'=>0,'msg'=>'系统错误'];
            }
            $this->error('请选择要操作的数据');
        }

        //获取设置
        $set= \think\Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        //数量价格格式化
        $number_decimal = $setList['number_decimal']['value'];
        $price_decimal  = $setList['price_decimal']['value'];
        $batch   = $setList['batch']['is_checked'];
        $serial  = $setList['serial']['is_checked'];

        if($examine==1){
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'请选择需要审核的数据'];
                }
                $this->error('请选择需要审核的数据');
            }

            $info_list = Db::name('kuerp_allocation_info')->where(['allocation_id'=>['in',$ids]])->select();

            $goods_stock_add = [];$goods_stock_edit = [];
            $goods_batch_add = [];$goods_batch_edit = [];
            $serial_edit = [];$goods_batch_log = [];
            $goods_stock_log = [];
            foreach ($info_list as $list_k=>$list_v){

                $goods = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])->field('id,goods_code,goods_name,attribute_id,auxiliary,serial,inventory,unit_id,batch')->find();
                //先通过单位计算需修改多单位商品的库存数量
                if($goods['unit_id']==$list_v['goods_unit']){
                    $stock_number = $list_v['number'];
                }else{
                    $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$list_v['goods_unit']])->value('advance_rate');
                    if(empty($unit_conversion)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                        }
                        $this->error('商品单位信息查询失败');
                    }else{
                        $stock_number = round(($list_v['number']*$unit_conversion),$number_decimal);
                    }
                }
                if($goods['auxiliary']==1 && empty($list_v['goods_attr'])){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'请选择商品属性'];
                    }
                    $this->error('请选择商品属性');
                }
                if(!empty($list_v['goods_attr'])){
                    $attr_id = $list_v['goods_attr'];
                }
                //查询调出仓库库存
                $where = [];
                $where['goods_id'] = $goods['id'];
                if(isset($attr_id)){
                    $where['attribute_id'] = $attr_id;
                }
                $where['stores_id'] = $list_v['stores_out'];
                $goods_stock = Db::name('kuerp_goods_stock')->where($where)->find();
                //减调出仓库库存
                if(empty($goods_stock)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                    }
                    $this->error('商品'.$goods['goods_name'].'库存不足');
                }else if($goods_stock['stock']<$stock_number){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                    }
                    $this->error('商品'.$goods['goods_name'].'库存不足');
                }else{
                    if(isset($goods_stock_edit[$goods_stock['id']])){
                        $goods_stock_edit[$goods_stock['id']] -= $stock_number;
                    }else{
                        $goods_stock_edit[$goods_stock['id']] = $goods_stock['stock']-$stock_number;
                    }
                }
                //收发详情
                $allocation = Db::name('kuerp_allocation')->where(['id'=>$list_v['allocation_id']])->field('purchase_code,purchase_time,remarks')->find();
                //获取成本单价
                $balance_unit=0;
                $goodsInfo = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($goods['goods_code'],$list_v['stores_out'],$list_v['goods_attr']),true);
                $unitData = isset($goodsInfo['unit'])?$goodsInfo['unit']:[];
                if(empty($unitData)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品信息获取错误'];
                    }
                    $this->error('商品信息获取错误');
                }else{
                    foreach ($unitData as $unitData_k=>$unitData_v){
                        if($unitData_v['id']==$goods['unit_id']){
                            $balance_unit = $unitData_v['purchase'];
                        }
                    }
                }
                //获取成本单价结束
                if($goods){
                    //调出
                    $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code' => $allocation['purchase_code'],
                        'purchase_id'   => $list_v['id'],
                        'purchase_time' => $allocation['purchase_time'],
                        'business_type' => '6',
                        'goods_id'      => $goods['id'],
                        'goods_code'    => $goods['goods_code'],
                        'attribute_id'  => (float)$list_v['goods_attr'],
                        'goods_unit'    => (float)$list_v['goods_unit'],
                        'stores_id'     => (float)$list_v['stores_out'],
                        'remark'        => $list_v['remarks'],
                        'enter_number'  => null,
                        'enter_basics'  => null,
                        'enter_unit'    => null,
                        'enter_cost'    => null,
                        'out_number'    => (float)$list_v['number'],
                        'out_basics'    => (float)$stock_number,
                        'out_unit'      => $balance_unit,
                        'out_cost'      => round($balance_unit*$stock_number,4),
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                    //调入
                    $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code' => $allocation['purchase_code'],
                        'purchase_id'   => $list_v['id'],
                        'purchase_time' => $allocation['purchase_time'],
                        'business_type' => '6',
                        'goods_id'      => $goods['id'],
                        'goods_code'    => $goods['goods_code'],
                        'attribute_id'  => (float)$list_v['goods_attr'],
                        'goods_unit'    => (float)$list_v['goods_unit'],
                        'stores_id'     => (float)$list_v['stores_enter'],
                        'remark'        => $list_v['remarks'],
                        'enter_number'  => (float)$list_v['number'],
                        'enter_basics'  => (float)$stock_number,
                        'enter_unit'    => $balance_unit,
                        'enter_cost'    => round($balance_unit*$stock_number,4),
                        'out_number'    => null,
                        'out_basics'    => null,
                        'out_unit'      => null,
                        'out_cost'      => null,
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }
                //收发详情结束
                //调入仓库
                $where = [];
                $where['goods_id'] = $goods['id'];
                if(isset($attr_id)){
                    $where['attribute_id'] = $attr_id;
                }
                $where['stores_id'] = $list_v['stores_enter'];
                $goods_stock = Db::name('kuerp_goods_stock')->where($where)->find();
                if(empty($goods_stock)){//调入仓库无数据，新增仓储数据
                    $where['stores_id'] = $list_v['stores_out'];
                    $goods_stocks = Db::name('kuerp_goods_stock')->where($where)->find();
                    if(isset($goods_stock_add[$goods_stocks['id']])){
                        $goods_stock_add[$goods_stocks['id']]['stock'] += $stock_number;
                    }else{
                        $goods_stocks_id = $goods_stocks['id'];
                        unset($goods_stocks['id']);
                        $goods_stocks['stock'] = $stock_number;
                        $goods_stocks['stores_id'] = $list_v['stores_enter'];
                        $goods_stocks['createtime'] = time();
                        $goods_stocks['updatetime'] = time();
                        $goods_stock_add[$goods_stocks_id] = $goods_stocks;
                    }
                }else{
                    if(isset($goods_stock_edit[$goods_stock['id']])){
                        $goods_stock_edit[$goods_stock['id']] += $stock_number;
                    }else{
                        $goods_stock_edit[$goods_stock['id']] = $goods_stock['stock']+$stock_number;
                    }
                }
                foreach ($goods_stock_edit as $k=>$v){
                    if($v<0){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                        }
                        $this->error('商品'.$goods['goods_name'].'库存不足');
                    }
                }

                if($goods['batch']==1 && $batch==1){//启用批次管理

                    //调出商品批次跟踪
                    $allocation = Db::name('kuerp_allocation')->where(['id'=>$list_v['allocation_id']])->field('purchase_code,purchase_time,remarks')->find();
                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'goods_unit'    => $list_v['goods_unit'],
                        'stores_id'     => $list_v['stores_out'],
                        'batch_id'      => (float)$list_v['goods_batch_id'],
                        'number'        => (float)$list_v['number'],
                        'purchase_code' => $allocation['purchase_code'],
                        'purchase_time' => $allocation['purchase_time'],
                        'business_type' => '6',
                        'remarks'       => $allocation['remarks'],
                        'order_id'      => $list_v['id'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                    //调出商品批次跟踪结束
                    //调入商品批次跟踪
                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'goods_unit'    => $list_v['goods_unit'],
                        'stores_id'     => $list_v['stores_enter'],
                        'batch_id'      => (float)$list_v['goods_batch_id'],
                        'number'        => (float)$list_v['number'],
                        'purchase_code' => $allocation['purchase_code'],
                        'purchase_time' => $allocation['purchase_time'],
                        'business_type' => '6',
                        'remarks'       => $allocation['remarks'],
                        'order_id'      => $list_v['id'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                    //调入商品批次跟踪结束

                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if(!empty($list_v['goods_attr'])){
                        $where['attribute_id'] = $list_v['goods_attr'];
                    }
                    $where['stores_id'] = $list_v['stores_out'];
                    $where['batch_id'] = $list_v['goods_batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'批次信息查询失败'];
                        }
                        $this->error('商品'.$goods['goods_name'].'批次信息查询失败');
                    }else if($goods_batch_stock['stock']<$stock_number){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足'];
                        }
                        $this->error('商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足');
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                    }
                    foreach ($goods_batch_edit as $k=>$v){
                        if($v<0){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足'];
                            }
                            $this->error('商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足');
                        }
                    }

                    $where['stores_id'] = $list_v['stores_enter'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){//调入批次仓库无数据，新增仓储数据
                        $where['stores_id'] = $list_v['stores_out'];
                        $goods_stocks = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if(isset($goods_batch_add[$goods_stocks['id']])){
                            $goods_batch_add[$goods_stocks['id']]['stock'] += $stock_number;
                        }else{
                            $goods_stocks_id = $goods_stocks['id'];
                            unset($goods_stocks['id']);
                            $goods_stocks['stock'] = $stock_number;
                            $goods_stocks['stores_id'] = $list_v['stores_enter'];
                            $goods_stocks['createtime'] = time();
                            $goods_stocks['updatetime'] = time();
                            $goods_batch_add[$goods_stocks_id] = $goods_stocks;
                        }
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] += $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']+$stock_number;
                        }
                    }
                }
                if($goods['serial']==1 && $serial==1){//启用序列号管理
                    if(empty($list_v['goods_serial_id'])){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'请选择序列号'];
                        }
                        $this->error('请选择序列号');
                    }
                    $goods_serial = unserialize($list_v['goods_serial_id']);
                    $serial_number = [];
                    foreach ($goods_serial as $goods_serial_k=>$goods_serial_v){
                        $serial_number[] = $goods_serial_v['serial'];
                    }
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    $where['attribute_id'] = $list_v['goods_attr'];
                    $where['stores_id'] = $list_v['stores_out'];
                    $where['serial'] = ['in',$serial_number];
                    $where['store_status'] = '1';
                    $goods_number_stock = Db::name('kuerp_goods_serial')->where($where)->column('id');//需修改仓库的序列号ID
                    if(sizeof($goods_number_stock)!=sizeof($serial_number)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'序列号存在未在库，请重新选择'];
                        }
                        $this->error('商品'.$goods['goods_name'].'序列号存在未在库，请重新选择');
                    }
                    foreach ($goods_number_stock as $k=>$v){
                        if(!isset($serial_edit[$v])){
                            $serial_edit[$v] = ['id'=>$v,'stores_id'=>$list_v['stores_enter']];
                        }
                    }
                }
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                if(!empty($goods_batch_log)){
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
                }

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])
                    ->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                if(!empty($goods_stock_add)){
                    Db::name('kuerp_goods_stock')->insertAll($goods_stock_add);
                }
                if(!empty($goods_stock_edit)){
                    foreach ($goods_stock_edit as $k=>$v){
                        Db::name('kuerp_goods_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                if(!empty($goods_batch_add)){
                    foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                        $where = [];
                        $where['goods_code'] = $goods_batch_add_v['goods_code'];
                        $where['stores_id'] = $goods_batch_add_v['stores_id'];
                        $where['batch_id'] = $goods_batch_add_v['batch_id'];
                        if($goods_batch_add_v['attribute_id']>0){
                            $where['attribute_id'] = $goods_batch_add_v['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if($stock){
                            Db::name('kuerp_goods_batch_stock')->where(['id'=>$stock['id']])->inc('stock',$goods_batch_add_v['stock'])->update();
                        }else{
                            Db::name('kuerp_goods_batch_stock')->insert($goods_batch_add_v);
                        }
                    }
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                if(!empty($serial_edit)){
                    (new \app\admin\model\kuerp\GoodsSerial())->saveAll($serial_edit);
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('审核失败');
            }
        }else{
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('请选择需要反审核的数据');
            }
            $info_list = Db::name('kuerp_allocation_info')->where(['allocation_id'=>['in',$ids]])->select();

            $goods_stock_add = [];$goods_stock_edit = [];
            $goods_batch_add = [];$goods_batch_edit = [];
            $serial_edit = [];$info_ids = [];
            foreach ($info_list as $list_k=>$list_v){
                $info_ids[] = $list_v['id'];
                $goods = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])->field('auxiliary,serial,id,goods_name,attribute_id,inventory,unit_id,batch')->find();
                //先通过单位计算需修改多单位商品的库存数量
                if($goods['unit_id']==$list_v['goods_unit']){
                    $stock_number = $list_v['number'];
                }else{
                    $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$list_v['goods_unit']])->value('advance_rate');
                    if(empty($unit_conversion)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                        }
                        $this->error('商品单位信息查询失败');
                    }else{
                        $stock_number = round(($list_v['number']*$unit_conversion),$number_decimal);
                    }
                }
                if($goods['auxiliary']==1 && empty($list_v['goods_attr'])){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'请选择商品属性'];
                    }
                    $this->error('请选择商品属性');
                }
                if(!empty($list_v['goods_attr'])){
                    $attr_id = $list_v['goods_attr'];
                }
                //查询调入仓库库存
                $where = [];
                $where['goods_id'] = $goods['id'];
                if(isset($attr_id)){
                    $where['attribute_id'] = $attr_id;
                }
                $where['stores_id'] = $list_v['stores_enter'];
                $goods_stock = Db::name('kuerp_goods_stock')->where($where)->find();
                //减调入仓库库存
                if(empty($goods_stock)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                    }
                    $this->error('商品'.$goods['goods_name'].'库存不足');
                }else if($goods_stock['stock']<$stock_number){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                    }
                    $this->error('商品'.$goods['goods_name'].'库存不足');
                }else{
                    if(isset($goods_stock_edit[$goods_stock['id']])){
                        $goods_stock_edit[$goods_stock['id']] -= $stock_number;
                    }else{
                        $goods_stock_edit[$goods_stock['id']] = $goods_stock['stock']-$stock_number;
                    }
                }
                //调出仓库
                $where = [];
                $where['goods_id'] = $goods['id'];
                if(isset($attr_id)){
                    $where['attribute_id'] = $attr_id;
                }
                $where['stores_id'] = $list_v['stores_out'];
                $goods_stock = Db::name('kuerp_goods_stock')->where($where)->find();
                if(empty($goods_stock)){//调出仓库无数据，新增仓储数据
                    $where['stores_id'] = $list_v['stores_enter'];
                    $goods_stocks = Db::name('kuerp_goods_stock')->where($where)->find();
                    if(isset($goods_stock_add[$goods_stocks['id']])){
                        $goods_stock_add[$goods_stocks['id']]['stock'] += $stock_number;
                    }else{
                        $goods_stocks_id = $goods_stocks['id'];
                        unset($goods_stocks['id']);
                        $goods_stocks['stock'] = $stock_number;
                        $goods_stocks['stores_id'] = $list_v['stores_out'];
                        $goods_stocks['createtime'] = time();
                        $goods_stocks['updatetime'] = time();
                        $goods_stock_add[$goods_stocks_id] = $goods_stocks;
                    }
                }else{
                    if(isset($goods_stock_edit[$goods_stock['id']])){
                        $goods_stock_edit[$goods_stock['id']] += $stock_number;
                    }else{
                        $goods_stock_edit[$goods_stock['id']] = $goods_stock['stock']+$stock_number;
                    }
                }
                foreach ($goods_stock_edit as $k=>$v){
                    if($v<0){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                        }
                        $this->error('商品'.$goods['goods_name'].'库存不足');
                    }
                }

                if($goods['batch']==1 && $batch==1){//启用批次管理
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if(!empty($list_v['goods_attr'])){
                        $where['attribute_id'] = $list_v['goods_attr'];
                    }
                    $where['stores_id'] = $list_v['stores_enter'];
                    $where['batch_id'] = $list_v['goods_batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'批次信息查询失败'];
                        }
                        $this->error('商品'.$goods['goods_name'].'批次信息查询失败');
                    }else if($goods_batch_stock['stock']<$stock_number){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足'];
                        }
                        $this->error('商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足');
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                    }
                    foreach ($goods_batch_edit as $k=>$v){
                        if($v<0){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足'];
                            }
                            $this->error('商品'.$goods['goods_name'].'批次'.$list_v['goods_batch_name'].'库存不足');
                        }
                    }

                    $where['stores_id'] = $list_v['stores_out'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){//调出批次仓库无数据，新增仓储数据
                        $where['stores_id'] = $list_v['stores_enter'];
                        $goods_stocks = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if(isset($goods_batch_add[$goods_stocks['id']])){
                            $goods_batch_add[$goods_stocks['id']]['stock'] += $stock_number;
                        }else{
                            $goods_stocks_id = $goods_stocks['id'];
                            unset($goods_stocks['id']);
                            $goods_stocks['stock'] = $stock_number;
                            $goods_stocks['stores_id'] = $list_v['stores_out'];
                            $goods_stocks['createtime'] = time();
                            $goods_stocks['updatetime'] = time();
                            $goods_batch_add[$goods_stocks_id] = $goods_stocks;
                        }
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] += $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']+$stock_number;
                        }
                    }
                }
                if($goods['serial']==1 && $serial==1){//启用序列号管理
                    if(empty($list_v['goods_serial_id'])){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'请选择序列号'];
                        }
                        $this->error('请选择序列号');
                    }
                    $goods_serial = unserialize($list_v['goods_serial_id']);
                    $serial_number = [];
                    foreach ($goods_serial as $goods_serial_k=>$goods_serial_v){
                        $serial_number[] = $goods_serial_v['serial'];
                    }
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    $where['attribute_id'] = $list_v['goods_attr'];
                    $where['stores_id'] = $list_v['stores_enter'];
                    $where['serial'] = ['in',$serial_number];
                    $where['store_status'] = '1';
                    $goods_number_stock = Db::name('kuerp_goods_serial')->where($where)->column('id');//需修改仓库的序列号ID
                    if(sizeof($goods_number_stock)!=sizeof($serial_number)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'序列号存在未在库，请重新选择'];
                        }
                        $this->error('商品'.$goods['goods_name'].'序列号存在未在库，请重新选择');
                    }
                    foreach ($goods_number_stock as $k=>$v){
                        if(!isset($serial_edit[$v])){
                            $serial_edit[$v] = ['id'=>$v,'stores_id'=>$list_v['stores_out']];
                        }
                    }
                }
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                //收发详情
                if(!empty($info_ids)){
                    Db::name('kuerp_goods_log')->where(['business_type'=>'6','purchase_id'=>['in',$info_ids]])->delete();
                }
                //删除批次跟踪
                if(!empty($info_ids)){
                    Db::name('kuerp_goods_batch_log')->where(['business_type'=>'6','order_id'=>['in',$info_ids]])->delete();
                }

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])
                    ->update(['examine_status'=>'0','examine_id'=>'','examine_time'=>'','operation_id'=>$admin['id']]);
                if(!empty($goods_stock_add)){
                    Db::name('kuerp_goods_stock')->insertAll($goods_stock_add);
                }
                if(!empty($goods_stock_edit)){
                    foreach ($goods_stock_edit as $k=>$v){
                        Db::name('kuerp_goods_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                if(!empty($goods_batch_add)){
                    foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                        $where = [];
                        $where['goods_code'] = $goods_batch_add_v['goods_code'];
                        $where['stores_id'] = $goods_batch_add_v['stores_id'];
                        $where['batch_id'] = $goods_batch_add_v['batch_id'];
                        if($goods_batch_add_v['attribute_id']>0){
                            $where['attribute_id'] = $goods_batch_add_v['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if($stock){
                            Db::name('kuerp_goods_batch_stock')->where(['id'=>$stock['id']])->inc('stock',$goods_batch_add_v['stock'])->update();
                        }else{
                            Db::name('kuerp_goods_batch_stock')->insert($goods_batch_add_v);
                        }
                    }
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                if(!empty($serial_edit)){
                    (new \app\admin\model\kuerp\GoodsSerial())->saveAll($serial_edit);
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据反审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('反审核失败');
            }
        }
    }
    //获取单位价格信息
    public function getUnit($goods_code='',$store='',$attr='',$goods_id=''){
        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])
                ->field('id,note,market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,goods_specification_id,vendor_name,packaging')
                ->find();
        }else{
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])
                ->field('id,note,market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,goods_specification_id,vendor_name,packaging')
                ->find();
        }
        $goods_id = isset($row['id'])?$row['id']:0;
        if(empty($row)){
            return [];
        }

        $auxiliary = Db::name('kuerp_auxiliary')->where(['status'=>1])->column('name','id');
        $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id])->select();
        foreach ($goods_attr as $goods_attr_k=>$goods_attr_v){
            $aux_combination = Db::name('kuerp_aux_combination')->where(['id'=>$goods_attr_v['attribute_id']])->value('attr_ids');
            $goods_attr[$goods_attr_k]['attr_ids'] = [];
            $goods_attr[$goods_attr_k]['attr_name'] = '';
            if($aux_combination){
                $goods_attr[$goods_attr_k]['attr_id'] = $aux_combination;
                $aux_combination = explode('&',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_ids'] = $aux_combination;
                foreach ($aux_combination as $aux_combination_k=>$aux_combination_v){
                    $aux_combination[$aux_combination_k] = $auxiliary[$aux_combination_v];
                }
                $aux_combination = implode('/',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_name'] = $aux_combination;
            }
        }


        $where = [];
        $where['goods_id'] = $goods_id;
        if($store){
            $where['stores_id'] = $store;
        }else{
            $where['stores_id'] = $row['manager_warehouse_id'];
        }
        if($attr){
            $attribute_id = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->value('attribute_id');
            $where['attribute_id'] = $attribute_id;
        }
        $stock = Db::name('kuerp_goods_stock')->where($where)->value('stock');
        $row['stock'] = $stock;
        if($row['more_unit']==1){
            $unit = Db::name('kuerp_unit_conversion')->alias('uc')
                ->join('kuerp_unit u','u.id=uc.vice_unit')
                ->where(['uc.main_unit'=>$row['unit_id']])
                ->order('uc.advance_rate','ASC')
                ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                ->select();
            $unit_ids = [$row['unit_id']];
            foreach ($unit as $unit_k=>$unit_v){
                $unit_ids[] = $unit_v['id'];
            }
        }else{
            $unit_ids = [$row['unit_id']];
        }

        $unit = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        return json_encode(['unit'=>$unit,'attr'=>$goods_attr,'goods'=>$row]);
    }
    /**
     * 获取可用序列号
     * @param string goods_code 商品编号
     * @param string stores_id 仓库ID
     * @param string attribute_id 属性id
     * @return array
     */
    public function getSerial(){
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
        $serial = Db::name('kuerp_goods_serial')->where($where)->field('serial,remark,status')->select();
        return json_encode(['list'=>$serial]);
    }
}
