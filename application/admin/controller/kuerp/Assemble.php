<?php

namespace app\admin\controller\kuerp;

use app\admin\model\kuerp\GoodsSalesOrderLog;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 组装单
 *
 * @icon fa fa-circle-o
 */
class Assemble extends Backend
{
    protected $noNeedRight = ['savetemp', 'outboundedit', 'saveoutbound', 'printpage'];
    /**
     * Assemble模型对象
     * @var \app\admin\model\kuerp\Assemble
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Assemble;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("inboundStatusList", $this->model->getInboundStatusList());
        $this->view->assign("outboundStatusList", $this->model->getOutboundStatusList());

        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        //一键生成采购单按钮权限（fa_auth_rule: kuerp/assemble/setdata）
        $this->view->assign("canShortagePurchase", $this->auth->check('kuerp/assemble/setdata', $this->auth->id));
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
            $lists = [];
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list_v['print_num'] = 0;
                }
                $p_info = Db::name('kuerp_assemble_info')->alias('a')
                    ->join('kuerp_goods g','g.id=a.goods_id')
                    ->where(['a.purchase_code'=>$list_v['purchase_code'],'a.type'=>'1'])
                    ->field('a.number,g.goods_name,a.goods_attr,a.unit_cost,a.cost_money,a.goods_unit')->find();
                $p_info['attr_name'] = '';
                if(!empty($p_info['goods_attr'])){
                    $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$p_info['goods_attr']])->value('attribute_id');
                    $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
                    if($attribute_ids){
                        $attribute_ids = explode('&',$attribute_ids);
                        $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                        $p_info['attr_name'] = implode('/',$attribute);
                    }
                }
                $p_info['unit_name'] = Db::name('kuerp_unit')->where(['id'=>$p_info['goods_unit']])->value('name');

                $preparation = Db::name('admin')->where(['id'=>$list_v['preparation_id']])->value('nickname');
                if($list_v['examine_id']){
                    $examine = Db::name('admin')->where(['id'=>$list_v['examine_id']])->value('nickname');
                    $examine_time = date('Y-m-d',$list_v['examine_time']);
                }else{
                    $examine = '';
                    $examine_time = '';
                }

                // 列表只展示组合件信息，不展示子件/子件数量/子件单位
                $row = [];
                $row['id'] = $list_v['id'];
                $row['preparation'] = $preparation;
                $row['examine'] = $examine;
                $row['examine_time'] = $examine_time;
                $row['remark'] = $list_v['remark'];
                $row['print_num'] = $list_v['print_num'];
                $row['examine_status'] = $list_v['examine_status'];
                $row['inbound_status'] = $list_v['inbound_status'];
                $row['outbound_status'] = $list_v['outbound_status'];
                $row['sales_order_id'] = $list_v['sales_order_id'];

                $row['purchase_code'] = $list_v['purchase_code'];
                $row['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                $row['pnumber'] = $p_info['number'];
                $row['pname'] = $p_info['goods_name'].' '.$p_info['attr_name'];
                $row['unit_name'] = $p_info['unit_name'];
                $row['money'] = $list_v['money'];
                $row['punit_cost'] = $p_info['unit_cost'];
                $row['pcost_money'] = $p_info['cost_money'];

                $lists[] = $row;
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
                $serial_set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');//序列号设置
                $batch_set= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');//批次设置
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $goods_serial_all = [];//全部序列号,重复判断使用
                $p = 2;$z = 2;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        if(!isset($params['goods_unit'][$k]) || empty($params['goods_unit'][$k])){
                            $this->error('请选择单位');
                        }
                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        $goods_unit = $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k];
                        $advance_rate = 1;//换算进率
                        if($goods['more_unit']==1) {
                            if($goods_unit!=$goods['unit_id']){
                                //所选单位不等于商品默认单位，需查询进率换算基础数量
                                $more_unit = Db::name('kuerp_unit_conversion')
                                    ->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$goods_unit])
                                    ->field('id,advance_rate')
                                    ->find();
                                if(empty($more_unit)){
                                    $this->error('商品单位信息查询失败');
                                }else{
                                    $advance_rate = $more_unit['advance_rate'];
                                }
                            }
                        }
                        $basic_number = round(($advance_rate*$params['number'][$k]),4);

                        if($k==0){
                            //组合件
                            //批次
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();//已有批次
                            $goods_batch = [];$goods_batch_info = [];
                            if($goods['batch']==1 && $batch_set==1){
                                if(empty($params['batch'][$k])){
                                    if($batch_data){
                                        $this->error('请选择生产批次');
                                    }else{
                                        $this->error('请输入生产批次');
                                    }
                                }
                                if(empty($params['manufacture'][$k])){
                                    $this->error('请输入生产日期');
                                }
                                if($params['guarantee'][$k]===''){
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k]<0){
                                    $this->error('保质期天数不可小于0');
                                }
                                if(empty($params['validity'][$k])){
                                    $this->error('请输入过期时间');
                                }
                                if(empty($batch_data)){//新增生产批次
                                    $goods_batch = \addons\kuerp\library\Goods::setDataInfo([
                                        'goods_id'    => $goods['id'],
                                        'goods_code'  => $params['goods_code'][$k],
                                        'attribute_id'=>isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                        'stores_id'   => $params['stores_id'][$k],
                                        'batch_name'  => $params['batch'][$k],
                                        'manufacture' => strtotime($params['manufacture'][$k]),
                                        'guarantee'   => $params['guarantee'][$k],
                                        'validity'    => strtotime($params['validity'][$k]),
                                        'createtime'  => time(),
                                        'updatetime'  => time(),
                                    ]);
                                }
                                $goods_batch_info = \addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id'    => isset($batch_data['id'])?$batch_data['id']:'',
                                    'goods_id'    => $goods['id'],
                                    'goods_code'  => $params['goods_code'][$k],
                                    'attribute_id'=> isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                    'goods_unit'  => (int)$params['goods_unit'][$k],
                                    'stores_id'   => $params['stores_id'][$k],
                                    'stock'       => (float)$params['number'][$k],
                                    'number'      => (float)$params['number'][$k],
                                    'batch_name'  => isset($batch_data['batch'])?$batch_data['batch']:'',
                                    'manufacture' => strtotime($params['manufacture'][$k]),
                                    'guarantee'   => $params['guarantee'][$k],
                                    'validity'    => strtotime($params['validity'][$k]),
                                    'status'      => '0',
                                    'type'        => 2,
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                            }
                            //序列号
                            $goods_serial='';
                            if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial_set==1){
                                if(empty($params['period_period'][$k])){
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k=>$period_period_v){
                                    if(in_array($period_period_v['serial'],$goods_serial_all)){
                                        $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                    }else{
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code]])->column('serial');
                                if($serial){
                                    $this->error('商品序列号'.$serial[0].'已占用');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'     =>  $params['purchase_code'],
                                'purchase_time'     =>  strtotime($params['purchase_time']),
                                'business_type'     =>  1,
                                'type'              =>  1,

                                'goods_id'          =>  $goods['id'],//商品信息
                                'goods_code'        =>  $goods['goods_code'],
                                'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                                'goods_unit'        =>  $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k],

                                'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                                'allow_stock'       =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                                'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                                'period'            =>  $goods_serial,
                                'basic_number'      =>  $basic_number,
                                // 'unit_cost'         =>  $params['unit_cost'][$k]===''?null:(float)$params['unit_cost'][$k],
                                // 'cost_money'        =>  $params['cost_money'][$k]===''?null:(float)$params['cost_money'][$k],
                                'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],

                                'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                                'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                                'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                                'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                                'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                                'preparation_id'    =>  $admin['id'],
                                'operation_id'      =>  $admin['id'],

                                'sales_order_id'    =>  isset($params['sales_order_id']) ? $params['sales_order_id'] : '',
                                'createtime'        => time(),
                                'updatetime'        => time(),
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $p = 1;
                        }else{
                            //子件
                            if($v==$params['goods_code'][0]){
                                $this->error('子件不能和组合件为同一商品');
                            }
                            //批次
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();//已有批次
                            $goods_batch = [];$goods_batch_info = [];
                            if($goods['batch']==1 && $batch_set==1){
                                if(empty($params['batch'][$k])){
                                    $this->error('请选择生产批次');
                                }
                                if(empty($params['manufacture'][$k])){
                                    $this->error('请输入生产日期');
                                }
                                if($params['guarantee'][$k]===''){
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k]<0){
                                    $this->error('保质期天数不可小于0');
                                }
                                if(empty($params['validity'][$k])){
                                    $this->error('请输入过期时间');
                                }
                                if(empty($batch_data)){
                                    $this->error('商品批次信息查询失败');
                                }
                                $goods_batch_info = \addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id'    => isset($batch_data['id'])?$batch_data['id']:'',
                                    'goods_id'    => $goods['id'],
                                    'goods_code'  => $params['goods_code'][$k],
                                    'attribute_id'=> isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                    'goods_unit'  => (int)$params['goods_unit'][$k],
                                    'stores_id'   => $params['stores_id'][$k],
                                    'stock'       => (float)$params['number'][$k],
                                    'number'      => (float)$params['number'][$k],
                                    'batch_name'  => isset($batch_data['batch'])?$batch_data['batch']:'',
                                    'manufacture' => strtotime($params['manufacture'][$k]),
                                    'guarantee'   => $params['guarantee'][$k],
                                    'validity'    => strtotime($params['validity'][$k]),
                                    'status'      => '0',
                                    'type'        => 2,
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                            }
                            //序列号
                            $goods_serial='';
                            if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial_set==1){
                                if(empty($params['period_period'][$k])){
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k=>$period_period_v){
                                    if(in_array($period_period_v['serial'],$goods_serial_all)){
                                        $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                    }else{
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code],'store_status'=>'1'])->column('serial');
                                if(sizeof($serial)!=sizeof($serial_code)){
                                    foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                        if(in_array($serial_code_v,$serial)){
                                            $this->error('商品序列号'.$serial_code_v.'不在库，不可出库');
                                        }
                                    }
                                    $this->error('商品序列号不在库，不可出库');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'     =>  $params['purchase_code'],
                                'purchase_time'     =>  strtotime($params['purchase_time']),
                                'business_type'     =>  1,
                                'type'              =>  2,

                                'goods_id'          =>  $goods['id'],//商品信息
                                'goods_code'        =>  $goods['goods_code'],
                                'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                                'goods_unit'        =>  $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k],

                                'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                                'allow_stock'       =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                                'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                                'period'            =>  $goods_serial,
                                'basic_number'      =>  $basic_number,
                                // 'unit_cost'         =>  $params['unit_cost'][$k]===''?null:(float)$params['unit_cost'][$k],
                                // 'cost_money'        =>  $params['cost_money'][$k]===''?null:(float)$params['cost_money'][$k],
                                'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],

                                'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                                'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                                'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                                'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                                'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                                'preparation_id'    =>  $admin['id'],
                                'operation_id'      =>  $admin['id'],

                                'sales_order_id'    =>  isset($params['sales_order_id']) ? $params['sales_order_id'] : '',
                                'createtime'        => time(),
                                'updatetime'        => time(),
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $z = 1;
                        }
                    }
                }
                if($p==2){
                    $this->error('组合件信息不能为空');
                }
                if($z==2){
                    $this->error('组合子件信息不能为空');
                }
                if(!empty($data)){
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'business_type'     =>  1,
                        'money'             =>  $params['costs']===''?null:(float)$params['costs'],
                        'sales_order_id'    =>  isset($params['sales_order_id']) ? $params['sales_order_id'] : '',
                        'remark'            =>  $params['note']===''?null:$params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ]);
                }else{
                    $this->error('组件信息不能为空');
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
                    foreach ($data as $data_k=>$data_v){
                        $goods_batch = $data_v['goods_batch'];
                        unset($data_v['goods_batch']);
                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);
                        $goods_batch_id = 0;
                        if(!empty($goods_batch)){
                            $goods_batch_id = Db::name('kuerp_goods_batch')->insertGetId($goods_batch);
                        }
                        if($goods_batch_id>0){
                            $goods_batch_info['batch_id'] = $goods_batch_id;
                            $goods_batch_info['batch_name'] = $goods_batch['batch_name'];

                            $data_v['batch_id'] = $goods_batch_id;
                            $data_v['batch'] = $goods_batch['batch_name'];
                        }
                        $purchase_id = Db::name('kuerp_assemble_info')->insertGetId($data_v);
                        //批次
                        if($goods_batch_info){
                            $goods_batch_info['assemble_id'] = $purchase_id;
                            Db::name('kuerp_goods_batch_info')->insert($goods_batch_info);
                        }
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    // 写入销货订单日志：生成组装单
                    if (!empty($row['sales_order_id'])) {
                        GoodsSalesOrderLog::writeLogByCode($row['sales_order_id'], '生成组装单', '生成组装单：' . $row['purchase_code'], 'assemble', $result);
                    }
                    if($examine!=1){
                        try {
                            $exa = $this->examine($result,1);
                        } catch (Exception $e) {
                        }
                        if(!isset($exa) || $exa['status']!=1){
                            try {
                                $this->del($result);
                            } catch (Exception $e) {
                            }
                            if(isset($exa['msg'])){
                                $this->error($exa['msg']);
                            }
                            $this->error('操作失败');
                        }
                    }
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        // 从销货排期生成组装单：预填组合件和BOM子件
        $schedule_id = $this->request->get('schedule_id');
        $scheduleGoodsCode = $this->request->get('goods_code');
        if (!empty($schedule_id) && !empty($scheduleGoodsCode)) {
            // 查询排期信息获取销货订单号
            $salesOrderId = '';
            $scheduleNumber = 1;
            $scheduleStoresId = '';
            $scheduleBasicNmber = 1;
            $scheduleGoodsUnit = '';
            $scheduleDefaultUnit = '';
            $scheduleGoodsAttr = '';
            $schedule = Db::name('kuerp_goods_sales')->where(['id' => $schedule_id])->find();
            if ($schedule) {
                $salesOrderId = $schedule['purchase_order_id'];

                // 查该排期下该商品的信息（仓库、数量等）
                $scheduleInfo = Db::name('kuerp_goods_sales_info')
                    ->where(['purchase_code' => $schedule['purchase_code'], 'goods_code' => $scheduleGoodsCode])
                    ->find();
                $scheduleNumber = $scheduleInfo ? (float)$scheduleInfo['number'] : 1;
                $scheduleStoresId = $scheduleInfo ? (int)$scheduleInfo['stores_id'] : '';
                $scheduleBasicNmber = $scheduleInfo ? (float)$scheduleInfo['basic_nmber'] : 1;
                $scheduleGoodsUnit = $scheduleInfo ? (int)$scheduleInfo['goods_unit'] : '';
                $scheduleDefaultUnit = $scheduleInfo ? (int)$scheduleInfo['default_unit'] : '';
                $scheduleGoodsAttr = $scheduleInfo ? (int)$scheduleInfo['goods_attr'] : '';
            }

            // 查该商品的BOM模板，计算子件零件
            $bomChildren = [];
            if (!empty($scheduleGoodsCode)) {
                $goods = Db::name('kuerp_goods')->where(['goods_code' => $scheduleGoodsCode])->find();
                if ($goods) {
                    $bomList = Db::name('kuerp_goods_bom')->where(['goods_id' => $goods['id']])->select();
                    foreach ($bomList as $bom) {
                        $children = Db::name('kuerp_goods_bom_info')->alias('a')
                            ->join('kuerp_goods g', 'g.id=a.goods_id')
                            ->where([
                                'a.bom_id'      => $bom['id'],
                                'a.type'        => '2',
                                'g.product_type'=> ['in', ['part', 'semi_finished']],
                            ])
                            ->field('a.goods_id, a.goods_code, a.number as bom_number, g.goods_name, g.unit_id, g.manager_warehouse_id')
                            ->select();
                        foreach ($children as $child) {
                            $childQty = (float)$child['bom_number'] * (float)$scheduleBasicNmber;
                            if (isset($bomChildren[$child['goods_id']])) {
                                $bomChildren[$child['goods_id']]['number'] += $childQty;
                            } else {
                                $bomChildren[$child['goods_id']] = [
                                    'goods_code'  => $child['goods_code'],
                                    'goods_name'  => $child['goods_name'],
                                    'number'      => $childQty,
                                    'unit_id'     => $child['unit_id'],
                                    'stores_id'   => $child['manager_warehouse_id'],
                                ];
                            }
                        }
                    }
                }
            }
            $bomChildren = array_values($bomChildren);

            $this->assignconfig('scheduleSalesOrderId', $salesOrderId);
            $this->assignconfig('scheduleGoodsCode', $scheduleGoodsCode);
            $this->assignconfig('scheduleGoodsName', isset($goods) ? $goods['goods_name'] : '');
            $this->assignconfig('scheduleStoresId', isset($scheduleStoresId) ? $scheduleStoresId : '');
            $this->assignconfig('scheduleNumber', isset($scheduleNumber) ? $scheduleNumber : '');
            $this->assignconfig('scheduleGoodsUnit', isset($scheduleGoodsUnit) ? $scheduleGoodsUnit : '');
            $this->assignconfig('scheduleDefaultUnit', isset($scheduleDefaultUnit) ? $scheduleDefaultUnit : '');
            $this->assignconfig('scheduleGoodsAttr', isset($scheduleGoodsAttr) ? $scheduleGoodsAttr : '');
            $this->assignconfig('scheduleBomChildren', $bomChildren);
        }

        $code = \addons\kuerp\library\Goods::getCodes('',19);
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
            $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if ($params) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                $params = $this->preExcludeFields($params);
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');
                $serial_set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');//序列号设置
                $batch_set= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');//批次设置
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $goods_serial_all = [];//全部序列号,重复判断使用
                $p = 2;$z = 2;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        if(!isset($params['goods_unit'][$k]) || empty($params['goods_unit'][$k])){
                            $this->error('请选择单位');
                        }
                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        $goods_unit = $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k];
                        $advance_rate = 1;//换算进率
                        if($goods['more_unit']==1) {
                            if($goods_unit!=$goods['unit_id']){
                                //所选单位不等于商品默认单位，需查询进率换算基础数量
                                $more_unit = Db::name('kuerp_unit_conversion')
                                    ->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$goods_unit])
                                    ->field('id,advance_rate')
                                    ->find();
                                if(empty($more_unit)){
                                    $this->error('商品单位信息查询失败');
                                }else{
                                    $advance_rate = $more_unit['advance_rate'];
                                }
                            }
                        }
                        $basic_number = round(($advance_rate*$params['number'][$k]),4);

                        if($k==0){
                            //组合件
                            //批次
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();//已有批次
                            $goods_batch = [];$goods_batch_info = [];
                            if($goods['batch']==1 && $batch_set==1){
                                if(empty($params['batch'][$k])){
                                    if($batch_data){
                                        $this->error('请选择生产批次');
                                    }else{
                                        $this->error('请输入生产批次');
                                    }
                                }
                                if(empty($params['manufacture'][$k])){
                                    $this->error('请输入生产日期');
                                }
                                if($params['guarantee'][$k]===''){
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k]<0){
                                    $this->error('保质期天数不可小于0');
                                }
                                if(empty($params['validity'][$k])){
                                    $this->error('请输入过期时间');
                                }
                                if(empty($batch_data)){//新增生产批次
                                    $goods_batch = \addons\kuerp\library\Goods::setDataInfo([
                                        'goods_id'    => $goods['id'],
                                        'goods_code'  => $params['goods_code'][$k],
                                        'attribute_id'=>isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                        'stores_id'   => $params['stores_id'][$k],
                                        'batch_name'  => $params['batch'][$k],
                                        'manufacture' => strtotime($params['manufacture'][$k]),
                                        'guarantee'   => $params['guarantee'][$k],
                                        'validity'    => strtotime($params['validity'][$k]),
                                        'createtime'  => time(),
                                        'updatetime'  => time(),
                                    ]);
                                }
                                $goods_batch_info = \addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id'    => isset($batch_data['id'])?$batch_data['id']:'',
                                    'goods_id'    => $goods['id'],
                                    'goods_code'  => $params['goods_code'][$k],
                                    'attribute_id'=> isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                    'goods_unit'  => (int)$params['goods_unit'][$k],
                                    'stores_id'   => $params['stores_id'][$k],
                                    'stock'       => (float)$params['number'][$k],
                                    'number'      => (float)$params['number'][$k],
                                    'batch_name'  => isset($batch_data['batch'])?$batch_data['batch']:'',
                                    'manufacture' => strtotime($params['manufacture'][$k]),
                                    'guarantee'   => $params['guarantee'][$k],
                                    'validity'    => strtotime($params['validity'][$k]),
                                    'status'      => '0',
                                    'type'        => 2,
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                            }
                            //序列号
                            $goods_serial='';
                            if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial_set==1){
                                if(empty($params['period_period'][$k])){
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k=>$period_period_v){
                                    if(in_array($period_period_v['serial'],$goods_serial_all)){
                                        $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                    }else{
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code]])->column('serial');
                                if($serial){
                                    $this->error('商品序列号'.$serial[0].'已占用');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'     =>  $params['purchase_code'],
                                'purchase_time'     =>  strtotime($params['purchase_time']),
                                'business_type'     =>  1,
                                'type'              =>  1,

                                'goods_id'          =>  $goods['id'],//商品信息
                                'goods_code'        =>  $goods['goods_code'],
                                'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                                'goods_unit'        =>  $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k],

                                'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                                'allow_stock'       =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                                'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                                'period'            =>  $goods_serial,
                                'basic_number'      =>  $basic_number,
                                // 'unit_cost'         =>  $params['unit_cost'][$k]===''?null:(float)$params['unit_cost'][$k],
                                // 'cost_money'        =>  $params['cost_money'][$k]===''?null:(float)$params['cost_money'][$k],
                                'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],

                                'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                                'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                                'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                                'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                                'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                                'preparation_id'    =>  $admin['id'],
                                'operation_id'      =>  $admin['id'],

                                'sales_order_id'    =>  isset($params['sales_order_id']) ? $params['sales_order_id'] : '',
                                'createtime'        => time(),
                                'updatetime'        => time(),
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $p = 1;
                        }else{
                            //子件
                            if($v==$params['goods_code'][0]){
                                $this->error('子件不能和组合件为同一商品');
                            }
                            //批次
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();//已有批次
                            $goods_batch = [];$goods_batch_info = [];
                            if($goods['batch']==1 && $batch_set==1){
                                if(empty($params['batch'][$k])){
                                    $this->error('请选择生产批次');
                                }
                                if(empty($params['manufacture'][$k])){
                                    $this->error('请输入生产日期');
                                }
                                if($params['guarantee'][$k]===''){
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k]<0){
                                    $this->error('保质期天数不可小于0');
                                }
                                if(empty($params['validity'][$k])){
                                    $this->error('请输入过期时间');
                                }
                                if(empty($batch_data)){
                                    $this->error('商品批次信息查询失败');
                                }
                                $goods_batch_info = \addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id'    => isset($batch_data['id'])?$batch_data['id']:'',
                                    'goods_id'    => $goods['id'],
                                    'goods_code'  => $params['goods_code'][$k],
                                    'attribute_id'=> isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                    'goods_unit'  => (int)$params['goods_unit'][$k],
                                    'stores_id'   => $params['stores_id'][$k],
                                    'stock'       => (float)$params['number'][$k],
                                    'number'      => (float)$params['number'][$k],
                                    'batch_name'  => isset($batch_data['batch'])?$batch_data['batch']:'',
                                    'manufacture' => strtotime($params['manufacture'][$k]),
                                    'guarantee'   => $params['guarantee'][$k],
                                    'validity'    => strtotime($params['validity'][$k]),
                                    'status'      => '0',
                                    'type'        => 2,
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                            }
                            //序列号
                            $goods_serial='';
                            if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial_set==1){
                                if(empty($params['period_period'][$k])){
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k=>$period_period_v){
                                    if(in_array($period_period_v['serial'],$goods_serial_all)){
                                        $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                    }else{
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code],'store_status'=>'1'])->column('serial');
                                if(sizeof($serial)!=sizeof($serial_code)){
                                    foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                        if(in_array($serial_code_v,$serial)){
                                            $this->error('商品序列号'.$serial_code_v.'不在库，不可出库');
                                        }
                                    }
                                    $this->error('商品序列号不在库，不可出库');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'     =>  $params['purchase_code'],
                                'purchase_time'     =>  strtotime($params['purchase_time']),
                                'business_type'     =>  1,
                                'type'              =>  2,

                                'goods_id'          =>  $goods['id'],//商品信息
                                'goods_code'        =>  $goods['goods_code'],
                                'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                                'goods_unit'        =>  $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k],

                                'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                                'allow_stock'       =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                                'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                                'period'            =>  $goods_serial,
                                'basic_number'      =>  $basic_number,
                                // 'unit_cost'         =>  $params['unit_cost'][$k]===''?null:(float)$params['unit_cost'][$k],
                                // 'cost_money'        =>  $params['cost_money'][$k]===''?null:(float)$params['cost_money'][$k],
                                'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],

                                'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                                'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                                'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                                'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                                'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                                'preparation_id'    =>  $admin['id'],
                                'operation_id'      =>  $admin['id'],

                                'sales_order_id'    =>  isset($params['sales_order_id']) ? $params['sales_order_id'] : '',
                                'createtime'        => time(),
                                'updatetime'        => time(),
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $z = 1;
                        }
                    }
                }
                if($p==2){
                    $this->error('组合件信息不能为空');
                }
                if($z==2){
                    $this->error('组合子件信息不能为空');
                }
                if(!empty($data)){
                    $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'business_type'     =>  1,
                        'money'             =>  $params['costs']===''?null:(float)$params['costs'],
                        'sales_order_id'    =>  isset($params['sales_order_id']) ? $params['sales_order_id'] : '',
                        'remark'            =>  $params['note']===''?null:$params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'updatetime'        => time(),
                    ]);
                }else{
                    $this->error('组件信息不能为空');
                }
                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }
                $goods_purchase_info = Db::name('kuerp_assemble_info')->where(['purchase_code'=>$old_purchase_code])->column('id');
                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    Db::name('kuerp_assemble_info')->where(['purchase_code'=>$old_purchase_code])->delete();
                    Db::name('kuerp_goods_batch_info')->where(['assemble_id'=>['in',$goods_purchase_info]])->delete();
                    foreach ($data as $data_k=>$data_v){
                        $goods_batch = $data_v['goods_batch'];
                        unset($data_v['goods_batch']);
                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);
                        $goods_batch_id = 0;
                        if(!empty($goods_batch)){
                            $goods_batch_id = Db::name('kuerp_goods_batch')->insertGetId($goods_batch);
                        }
                        if($goods_batch_id>0){
                            $goods_batch_info['batch_id'] = $goods_batch_id;
                            $goods_batch_info['batch_name'] = $goods_batch['batch_name'];

                            $data_v['batch_id'] = $goods_batch_id;
                            $data_v['batch'] = $goods_batch['batch_name'];
                        }
                        $purchase_id = Db::name('kuerp_assemble_info')->insertGetId($data_v);

                        //批次
                        if($goods_batch_info){
                            $goods_batch_info['assemble_id'] = $purchase_id;
                            Db::name('kuerp_goods_batch_info')->insert($goods_batch_info);
                        }
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    if($examine!=1){
                        try {
                            $exa = $this->examine($ids,1);
                        } catch (Exception $e) {
                        }
                        if(!isset($exa) || $exa['status']!=1){
                            try {
                                $this->del($ids);
                            } catch (Exception $e) {
                            }
                            if(isset($exa['msg'])){
                                $this->error($exa['msg']);
                            }
                            $this->error('编辑失败，请刷新页面重试');
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

        $info = Db::name('kuerp_assemble_info')->alias('a')
            ->join('kuerp_goods g','g.id=a.goods_id')
            ->where(['a.purchase_code'=>$row['purchase_code']])
            ->field('a.*,g.goods_name')
            ->select();
        // 批量查询动态库存
        $goodsCodes = array_column($info, 'goods_code');
        $goodsIdMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id', 'goods_code') : [];
        $stockItems = [];
        foreach ($info as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? $goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        foreach ($info as $info_k=>$info_v){
            $info[$info_k]['unit'] = json_decode((new GoodsPurchase())->getUnit($info_v['goods_code'], $info_v['stores_id'], $info_v['goods_attr']),true);
            $gid = isset($goodsIdMap[$info_v['goods_code']]) ? $goodsIdMap[$info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$info_v['stores_id'] . '-' . (int)$info_v['goods_attr'];
            $info[$info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
        }
        $this->view->assign("info_list", $info);

        //审核禁止输入
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine_set==1 && $row['examine_status']==1){
            $this->assign('exclass','hiddens');
        }
        $this->assignconfig("examine_status", $row['examine_status']);
        $this->assignconfig("assembleSalesOrderId", isset($row['sales_order_id']) ? $row['sales_order_id'] : '');

        return $this->view->fetch();
    }
    /**
     * 一键生成采购单：暂存子件缺口数量，供 goods_purchase_order/add?type=1 预填
     */
    public function setdata(){
        $params = $this->request->post("row/a");
        if ($params) {
            if($params[0]){
                $params = json_decode($params[0],true);
            }else{
                $params = [];
            }
            if(empty($params)){
                $this->error('参数错误');
            }else{
                Session::set('GoodsPurchaseOrder',serialize($params));
                $this->success();
            }
        }
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
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $goods_purchase_id = [];$goods_purchase_code = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['examine_status']==1){
                    $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                    if($checkout!==true){
                        $this->error($checkout);
                    }
                }
                if($list_v['examine_status']==1 && $examine==1){
                    $this->error('不能删除已审核的数据');
                }elseif ($list_v['examine_status']==1 && $examine!=1){
                    try {
                        $this->examine($list_v['id'],2);
                    } catch (Exception $e) {
                    }
                }
                if($list_v['inbound_status']==1 && $examine==1){
                    $this->error('已入库审核的数据，请先反审核入库再删除');
                }elseif ($list_v['inbound_status']==1 && $examine!=1){
                    try {
                        $this->examineInbound($list_v['id'],2);
                    } catch (Exception $e) {
                    }
                }
                if(!in_array($list_v['id'],$goods_purchase_id)){
                    $goods_purchase_id[] = $list_v['id'];
                }
                if(!in_array($list_v['purchase_code'],$goods_purchase_code)){
                    $goods_purchase_code[] = $list_v['purchase_code'];
                }
            }
            $goods_purchase_info = Db::name('kuerp_assemble_info')->where(['purchase_code'=>['in',$goods_purchase_code]])->column('id');

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_assemble_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
                }
                Db::name('kuerp_goods_batch_info')->where(['assemble_id'=>['in',$goods_purchase_info]])->delete();
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
    //子件出库审核/反审核
    public function examine($ids = null,$examine=1){
        $ids = $ids ? $ids : $this->request->param('ids');
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            if($examine_set!=1){
                return ['status'=>0,'msg'=>'系统错误'];
            }
            $this->error('请选择要操作的数据');
        }

        $serial_set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');//序列号设置
        $batch_set= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');//批次设置

        if($examine==1){
            // 子件出库审核
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要子件出库审核的数据');
            }

            // 手动审核模式：仅设置审核状态，不做库存变动（实际出库通过 outboundEdit 页面填写）
            if($examine_set==1){
                $admin = Session::get('admin');
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                if($result !== false){
                    // 写入销货订单操作日志：子件出库
                    $examinedList = $this->model->where(['id'=>['in',$ids]])->field('id,purchase_code,sales_order_id')->select();
                    foreach ($examinedList as $eo) {
                        if (!empty($eo['sales_order_id'])) {
                            GoodsSalesOrderLog::writeLogByCode($eo['sales_order_id'], '组装单子件出库', '组装单[' . $eo['purchase_code'] . ']子件出库审核通过', 'assemble', $eo['id']);
                        }
                    }
                    $this->success(sizeof($list).'条数据子件出库审核成功');
                }else{
                    $this->error('子件出库审核失败');
                }
            }

            $editstock = [];//库存表修改库存
            $addstock = [];//库存表添加库存
            $goods_stock = [];//商品列表库存
            $goods_stock_log = [];//商品收发明细
            $goods_batch_edit = [];//批次库存更新
            $goods_batch_log = [];//批次记录
            $edit_serial = [];//序列号更新
            $serial_log = [];//序列号跟踪
            // 只查询组合件(type=1)
            $info_list = Db::name('kuerp_assemble_info')->where(['purchase_code'=>['in',$purchase_code],'type'=>'2'])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                $goods = Db::name('kuerp_goods')->where(['id'=>$info_list_v['goods_id']])->find();
                $stock_number = $info_list_v['basic_number'];//基础数量

                // 子件出库 - 库存减少
                // 商品表库存
                $status = false;
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['id']==$goods['id']){
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']-$stock_number;
                        $status = true;
                    }
                }
                if(!$status){
                    $goods_stock[] = [
                        'id'=>$goods['id'],
                        'inventory'=>$goods['inventory']-$stock_number,
                    ];
                }
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['inventory']<0){
                        $this->error($goods['goods_name'].'库存不足');
                    }
                }

                // 商品库存表库存
                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                if($info_list_v['goods_attr']>0){
                    $goods_attr = $info_list_v['goods_attr'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] -= $stock_number;
                            $editstock[$editstock_k]['cost_money'] = (round($editstock_v['cost_money']-$info_list_v['cost_money'],4));
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>round($stock['stock']-$stock_number,4),
                            'cost_money'=>round($stock['cost_money']-$info_list_v['cost_money'],4)
                        ];
                    }
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['stock']<0){
                            $this->error($goods['goods_name'].'库存不足');
                        }
                    }
                }else{
                    $this->error($goods['goods_name'].'库存不足');
                }

                // 收发详情 - 出库
                $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info_list_v['purchase_code'],
                    'purchase_id'   => $info_list_v['id'],
                    'purchase_time' => $info_list_v['purchase_time'],
                    'business_type' => '13',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info_list_v['goods_attr'],
                    'goods_unit'    => (float)$info_list_v['goods_unit'],
                    'stores_id'     => (float)$info_list_v['stores_id'],
                    'remark'        => $info_list_v['remark'],
                    'enter_number'  => null,
                    'enter_basics'  => null,
                    'enter_unit'    => null,
                    'enter_cost'    => null,
                    'out_number'    => (float)$info_list_v['number'],
                    'out_basics'    => (float)$stock_number,
                    // 'out_unit'      => round($info_list_v['unit_cost'],4),
                    // 'out_cost'      => (float)$info_list_v['cost_money'],
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);

                // 批次跟踪
                if($batch_set==1 && $goods['batch']==1){
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                        foreach ($goods_batch_edit as $goods_batch_edit_k=>$goods_batch_edit_v){
                            if($goods_batch_edit_v<0){
                                $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                            }
                        }
                    }
                    // 商品批次跟踪
                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'goods_unit'    => $info_list_v['goods_unit'],
                        'stores_id'     => $info_list_v['stores_id'],
                        'batch_id'      => (float)$info_list_v['batch_id'],
                        'number'        => (float)$info_list_v['basic_number'],
                        'purchase_code' => $info_list_v['purchase_code'],
                        'purchase_time' => $info_list_v['purchase_time'],
                        'business_type' => '12',
                        'remarks'       => $info_list_v['remark'],
                        'order_id'      => $info_list_v['id'],
                        // 'unit_price'    => $info_list_v['unit_cost'],
                        // 'tax_included'  => $info_list_v['unit_cost'],
                        // 'all_money'     => $info_list_v['cost_money'],
                        'tax_money'     => 0,
                        'money_tax_price' => $info_list_v['cost_money'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }

                // 序列号跟踪 - 出库
                if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                    $period = unserialize($info_list_v['period']);
                    foreach ($period as $period_k=>$period_v){
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->field('serial,status,store_status')->find();
                        if($per_s){
                            $edit_serial[] = ['serial'=>$per_s['serial'],'assemble_id'=>$info_list_v['id'],'type'=>12,'store_status'=>'2','old_status'=>$per_s['status'],'old_store'=>$per_s['store_status']];
                        }else{
                            $this->error($goods['goods_name'].'序列号'.$period_v['serial'].'不存在');
                        }
                        $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'=>$info_list_v['goods_id'],
                            'stores_id'=>$info_list_v['stores_id'],
                            'attribute_id'=>isset($goods_attr)?$goods_attr:0,
                            'serial'=>$period_v['serial'],
                            'business_type'=>'12',
                            'store_status'=>'2',
                            'remarks' => $period_v['remark'],
                            'purchase_id'=>$info_list_v['id'],
                            'purchase_code'=>$info_list_v['purchase_code'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'order_id'      => $info_list_v['id'],
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                    }
                }
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                // 库存表修改库存
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                // 库存表添加库存
                if(!empty($addstock)){
                    foreach ($addstock as $addstock_k=>$addstock_v){
                        $where = [];
                        $where['goods_code'] = $addstock_v['goods_code'];
                        $where['stores_id'] = $addstock_v['stores_id'];
                        if($addstock_v['attribute_id']>0){
                            $where['attribute_id'] = $addstock_v['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_stock')->where($where)->find();
                        if($stock){
                            Db::name('kuerp_goods_stock')->where(['id'=>$stock['id']])->inc('stock',$addstock_v['stock'])->update();
                        }else{
                            Db::name('kuerp_goods_stock')->insert($addstock_v);
                        }
                    }
                }
                // 商品列表库存
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                // 商品收发明细
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                // 批次库存更新
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                // 批次记录
                if(!empty($goods_batch_log)){
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
                }
                // 序列号更新
                if(!empty($edit_serial)){
                    foreach ($edit_serial as $edit_serial_k=>$edit_serial_v){
                        Db::name('kuerp_goods_serial')->where(['serial'=>$edit_serial_v['serial']])->update([
                            'assemble_id'=>$edit_serial_v['assemble_id'],
                            'status'=>$edit_serial_v['type'],
                            'store_status'=>$edit_serial_v['store_status'],
                            'old_status'=>$edit_serial_v['old_status'],
                            'old_store'=>$edit_serial_v['old_store']
                        ]);
                    }
                }
                // 序列号跟踪
                if(!empty($serial_log)){
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                Db::commit();
                // 写入销货订单操作日志 + 消费锁定
                $examinedList2 = $this->model->where(['id'=>['in',$ids]])->field('id,purchase_code,sales_order_id')->select();
                foreach ($examinedList2 as $eo) {
                    if (!empty($eo['sales_order_id'])) {
                        // 写入销货订单操作日志
                        GoodsSalesOrderLog::writeLogByCode($eo['sales_order_id'], '组装单子件出库', '组装单[' . $eo['purchase_code'] . ']子件出库审核通过', 'assemble', $eo['id']);

                        // 消费该组装单出库子件的锁定
                        $childItems = Db::name('kuerp_assemble_info')
                            ->where(['purchase_code' => $eo['purchase_code'], 'type' => '2'])
                            ->field('goods_id,goods_code,stores_id,goods_attr,basic_number')
                            ->select();
                        foreach ($childItems as $ci) {
                            $attrId = $ci['goods_attr'] ? (int)$ci['goods_attr'] : 0;
                            \addons\kuerp\library\Goods::consumeLock(
                                $eo['sales_order_id'],
                                $ci['goods_id'],
                                $ci['stores_id'],
                                (float)$ci['basic_number'],
                                $attrId
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据子件出库审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('子件出库审核失败');
            }
        }else{
            // 子件出库反审核
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                // 如果入库已审核，不允许反审核出库
                if($list_v['inbound_status']==1){
                    $this->error('单据编号'.$list_v['purchase_code'].'组合件已入库审核，请先反审核入库再反审核子件出库');
                }
            }
            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要子件反审核出库的数据');
            }

            // 手动审核模式：仅反转审核状态，不做库存变动（实际出库通过 outboundEdit 页面填写）
            if($examine_set==1){
                $admin = Session::get('admin');
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>0,'examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                if($result !== false){
                    if($examine_set!=1){
                        return ['status'=>1,'msg'=>'操作成功'];
                    }
                    $this->success(sizeof($list).'条数据子件出库反审核成功');
                }else{
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'操作失败'];
                    }
                    $this->error('子件出库反审核失败');
                }
            }

            $editstock = [];//库存表修改库存
            $goods_stock = [];//商品列表库存
            $goods_batch_edit = [];//批次库存更新
            // 只查询组合件(type=1)
            $info_list = Db::name('kuerp_assemble_info')->where(['purchase_code'=>['in',$purchase_code],'type'=>'2'])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                $goods = Db::name('kuerp_goods')->where(['id'=>$info_list_v['goods_id']])->find();
                $stock_number = $info_list_v['basic_number'];

                // 反审核：库存恢复（入库）
                $status = false;
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['id']==$goods['id']){
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']+$stock_number;
                        $status = true;
                    }
                }
                if(!$status){
                    $goods_stock[] = [
                        'id'=>$goods['id'],
                        'inventory'=>$goods['inventory']+$stock_number,
                    ];
                }

                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                if($info_list_v['goods_attr']>0){
                    $goods_attr = $info_list_v['goods_attr'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] += $stock_number;
                            $editstock[$editstock_k]['cost_money'] = round($editstock_v['cost_money']+$info_list_v['cost_money'],4);
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>round($stock['stock']+$stock_number,4),
                            'cost_money'=>round($stock['cost_money']+$info_list_v['cost_money'],4)
                        ];
                    }
                }

                // 批次跟踪 - 恢复
                if($batch_set==1 && $goods['batch']==1){
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(!empty($goods_batch_stock)){
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] += $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']+$stock_number;
                        }
                    }
                }

                // 序列号跟踪 - 恢复
                if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                    $period = unserialize($info_list_v['period']);
                    foreach ($period as $period_k=>$period_v){
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->find();
                        if($per_s){
                            $edit_serial[] = ['serial'=>$per_s['serial'],'assemble_id'=>$info_list_v['id'],'type'=>$per_s['old_status'],'store_status'=>$per_s['old_store']];
                        }else{
                            $this->error($goods['goods_name'].'序列号'.$period_v['serial'].'不存在');
                        }
                    }
                }
            }

            $info_id = Db::name('kuerp_assemble_info')->where(['purchase_code'=>['in',$purchase_code],'type'=>'2'])->column('id');
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }

                // 只删除出库(type=1)相关日志
                Db::name('kuerp_goods_log')->where(['business_type'=>'13','purchase_id'=>['in',$info_id]])->delete();
                Db::name('kuerp_goods_batch_log')->where(['business_type'=>'12','order_id'=>['in',$info_id]])->delete();
                Db::name('kuerp_goods_serial_log')->where(['business_type'=>'12','order_id'=>['in',$info_id]])->delete();

                // 恢复序列号
                if(!empty($edit_serial)){
                    foreach ($edit_serial as $edit_serial_k=>$edit_serial_v){
                        Db::name('kuerp_goods_serial')->where(['serial'=>$edit_serial_v['serial']])->update([
                            'status'=>$edit_serial_v['type'],
                            'store_status'=>$edit_serial_v['store_status']
                        ]);
                    }
                }

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>0,'examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                Db::commit();
                // 写入销货订单操作日志：取消组装单
                $examinedList3 = $this->model->where(['id'=>['in',$ids]])->field('id,purchase_code,sales_order_id')->select();
                foreach ($examinedList3 as $eo) {
                    if (!empty($eo['sales_order_id'])) {
                        GoodsSalesOrderLog::writeLogByCode($eo['sales_order_id'], '取消组装单', '组装单[' . $eo['purchase_code'] . ']子件出库反审核', 'assemble', $eo['id']);
                    }
                }
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if($result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据子件出库反审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('子件出库反审核失败');
            }
        }
    }

    //组合件入库审核/反审核
    public function examineInbound($ids = null,$examine=1){
        $ids = $ids ? $ids : $this->request->param('ids');
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            if($examine_set!=1){
                return ['status'=>0,'msg'=>'系统错误'];
            }
            $this->error('请选择要操作的数据');
        }

        $serial_set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');//序列号设置
        $batch_set= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');//批次设置

        if($examine==1){
            // 组合件入库审核 - 需先完成出库审核
            $list = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                // 必须先完成出库审核才能做入库审核
                if($list_v['examine_status']!=1){
                    $this->error('单据编号'.$list_v['purchase_code'].'子件未出库审核，请先进行子件出库审核');
                }
            }
            $purchase_code = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'0'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要组合件入库审核的数据');
            }

            // 手动审核模式：仅设置审核状态，不做库存变动（实际入库通过 outboundEdit 页面填写）
            if($examine_set==1){
                $admin = Session::get('admin');
                $result = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'0'])->update(['inbound_status'=>1,'operation_id'=>$admin['id']]);
                if($result !== false){
                    // 写入销货订单日志：成品入库
                    foreach ($list as $logOrder) {
                        if (!empty($logOrder['sales_order_id'])) {
                            GoodsSalesOrderLog::writeLogByCode($logOrder['sales_order_id'], '组装单成品入库', '组装单[' . $logOrder['purchase_code'] . ']组合件入库审核通过', 'assemble', $logOrder['id']);
                        }
                    }
                    $this->success(sizeof($list).'条数据组合件入库审核成功');
                }else{
                    $this->error('组合件入库审核失败');
                }
            }

            $editstock = [];//库存表修改库存
            $addstock = [];//库存表添加库存
            $goods_stock = [];//商品列表库存
            $goods_stock_log = [];//商品收发明细
            $goods_batch_add = [];//批次库存新增
            $goods_batch_edit = [];//批次库存更新
            $goods_batch_log = [];//批次记录
            $per_code = [];//序列号
            $goods_serial = [];//序列号添加
            $edit_serial = [];//序列号更新
            $serial_log = [];//序列号跟踪
            $edit_batch = [];//批次id更新
            // 只查询组合件(type=1)
            $info_list = Db::name('kuerp_assemble_info')->where(['purchase_code'=>['in',$purchase_code],'type'=>'1'])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                $goods = Db::name('kuerp_goods')->where(['id'=>$info_list_v['goods_id']])->find();
                $stock_number = $info_list_v['basic_number'];

                // 组合件入库 - 库存增加
                $status = false;
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['id']==$goods['id']){
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']+$stock_number;
                        $status = true;
                    }
                }
                if(!$status){
                    $goods_stock[] = [
                        'id'=>$goods['id'],
                        'inventory'=>$goods['inventory']+$stock_number,
                    ];
                }

                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                if($info_list_v['goods_attr']>0){
                    $goods_attr = $info_list_v['goods_attr'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] += $stock_number;
                            $editstock[$editstock_k]['cost_money'] = round($editstock_v['cost_money']+$info_list_v['cost_money'],4);
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>round($stock['stock']+$stock_number,4),
                            'cost_money'=>round($stock['cost_money']+$info_list_v['cost_money'],4)
                        ];
                    }
                }else{
                    $addstock[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'  => isset($goods['id'])?$goods['id']:'',
                        'goods_code'=> $info_list_v['goods_code'],
                        'attribute_id'  => isset($goods_attr)?$goods_attr:0,
                        'stores_id'  => $info_list_v['stores_id'],
                        'stock'  => $stock_number,
                        'cost_money'=>round($info_list_v['cost_money'],4),
                        'createtime'  => time(),
                        'updatetime'  => time(),
                    ]);
                }

                // 收发详情 - 入库
                $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info_list_v['purchase_code'],
                    'purchase_id'   => $info_list_v['id'],
                    'purchase_time' => $info_list_v['purchase_time'],
                    'business_type' => '12',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info_list_v['goods_attr'],
                    'goods_unit'    => (float)$info_list_v['goods_unit'],
                    'stores_id'     => (float)$info_list_v['stores_id'],
                    'remark'        => $info_list_v['remark'],
                    'enter_number'  => (float)$info_list_v['number'],
                    'enter_basics'  => (float)$stock_number,
                    // 'enter_unit'    => round($info_list_v['unit_cost'],4),
                    // 'enter_cost'    => (float)$info_list_v['cost_money'],
                    'out_number'    => null,
                    'out_basics'    => null,
                    'out_unit'      => null,
                    'out_cost'      => null,
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);

                // 批次跟踪
                if($batch_set==1 && $goods['batch']==1){
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $status = false;
                        foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                            if($goods_batch_add_v['batch_id']==$info_list_v['batch_id'] && $goods_batch_add_v['goods_id']==$info_list_v['goods_id'] && $goods_batch_add_v['attribute_id']==$goods_attr && $goods_batch_add_v['stores_id']==$info_list_v['stores_id']){
                                $goods_batch_add[$goods_batch_add_k]['stock'] += $stock_number;
                                $status = true;
                            }
                        }
                        if(!$status){
                            $batch_id = $info_list_v['batch_id'];
                            if(empty($batch_id)){
                                $batch_id = Db::name('kuerp_goods_batch')->insertGetId(\addons\kuerp\library\Goods::setDataInfo([
                                    'goods_id' => $info_list_v['goods_id'],
                                    'goods_code' => $goods['goods_code'],
                                    'attribute_id' => isset($goods_attr)?$goods_attr:0,
                                    'stores_id' => $info_list_v['stores_id'],
                                    'batch_name' => $info_list_v['batch'],
                                    'manufacture' => $info_list_v['manufacture'],
                                    'guarantee' => $info_list_v['guarantee'],
                                    'validity' => $info_list_v['validity'],
                                    'createtime' => time(),
                                    'updatetime' => time(),
                                ]));
                            }
                            $edit_batch[] = ['id'=>$info_list_v['id'],'batch_id'=>$batch_id];
                            $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                                'batch_id' => $batch_id,
                                'goods_id' => $info_list_v['goods_id'],
                                'goods_code' => $goods['goods_code'],
                                'attribute_id' => isset($goods_attr)?$goods_attr:0,
                                'stores_id' => $info_list_v['stores_id'],
                                'stock' => $stock_number,
                                'createtime' => time(),
                                'updatetime' => time(),
                            ]);
                        }
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] += $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']+$stock_number;
                        }
                    }
                    // 商品批次跟踪
                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'goods_unit'    => $info_list_v['goods_unit'],
                        'stores_id'     => $info_list_v['stores_id'],
                        'batch_id'      => (float)$info_list_v['batch_id'],
                        'number'        => (float)$info_list_v['basic_number'],
                        'purchase_code' => $info_list_v['purchase_code'],
                        'purchase_time' => $info_list_v['purchase_time'],
                        'business_type' => '11',
                        'remarks'       => $info_list_v['remark'],
                        'order_id'      => $info_list_v['id'],
                        // 'unit_price'    => $info_list_v['unit_cost'],
                        // 'tax_included'  => $info_list_v['unit_cost'],
                        // 'all_money'     => $info_list_v['cost_money'],
                        'tax_money'     => 0,
                        'money_tax_price' => $info_list_v['cost_money'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }

                // 序列号跟踪 - 入库
                if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                    $period = unserialize($info_list_v['period']);
                    foreach ($period as $period_k=>$period_v){
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->field('serial,status,store_status')->find();
                        if($per_s){
                            $edit_serial[] = ['serial'=>$per_s['serial'],'assemble_id'=>$info_list_v['id'],'type'=>11,'store_status'=>1,'old_status'=>$per_s['status'],'old_store'=>$per_s['store_status']];
                        }else{
                            if(!in_array($period_v['serial'],$per_code)){
                                $per_code[] = $period_v['serial'];
                            }
                            $goods_serial[] = \addons\kuerp\library\Goods::setDataInfo([
                                'assemble_id' => $info_list_v['id'],
                                'goods_id'=>$goods['id'],
                                'goods_code'=>$goods['goods_code'],
                                'stores_id'=>$info_list_v['stores_id'],
                                'attribute_id'=>isset($goods_attr)?$goods_attr:0,
                                'serial'=>$period_v['serial'],
                                'remark'=>$period_v['remark'],
                                'status'=> '11',
                                'store_status'=>'1',
                                'assemble_status'=>'2',
                                'order_id'      => $info_list_v['id'],
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ]);
                        }
                        $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'=>$info_list_v['goods_id'],
                            'stores_id'=>$info_list_v['stores_id'],
                            'attribute_id'=>isset($goods_attr)?$goods_attr:0,
                            'serial'=>$period_v['serial'],
                            'business_type'=>'11',
                            'store_status'=>'1',
                            'remarks' => $period_v['remark'],
                            'purchase_id'=>$info_list_v['id'],
                            'purchase_code'=>$info_list_v['purchase_code'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'order_id'      => $info_list_v['id'],
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                    }
                }
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($addstock)){
                    foreach ($addstock as $addstock_k=>$addstock_v){
                        $where = [];
                        $where['goods_code'] = $addstock_v['goods_code'];
                        $where['stores_id'] = $addstock_v['stores_id'];
                        if($addstock_v['attribute_id']>0){
                            $where['attribute_id'] = $addstock_v['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_stock')->where($where)->find();
                        if($stock){
                            Db::name('kuerp_goods_stock')->where(['id'=>$stock['id']])->inc('stock',$addstock_v['stock'])->update();
                        }else{
                            Db::name('kuerp_goods_stock')->insert($addstock_v);
                        }
                    }
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                // 批次库存新增
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
                // 批次库存更新
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                // 批次记录
                if(!empty($goods_batch_log)){
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
                }
                // 更新批次id
                if(!empty($edit_batch)){
                    foreach ($edit_batch as $edit_batch_k=>$edit_batch_v){
                        Db::name('kuerp_assemble_info')->where(['id'=>$edit_batch_v['id']])->update(['batch_id'=>$edit_batch_v['batch_id']]);
                    }
                }
                // 序列号添加
                if(!empty($goods_serial)){
                    Db::name('kuerp_goods_serial')->insertAll($goods_serial);
                }
                // 序列号更新
                if(!empty($edit_serial)){
                    foreach ($edit_serial as $edit_serial_k=>$edit_serial_v){
                        Db::name('kuerp_goods_serial')->where(['serial'=>$edit_serial_v['serial']])->update([
                            'assemble_id'=>$edit_serial_v['assemble_id'],
                            'status'=>$edit_serial_v['type'],
                            'store_status'=>$edit_serial_v['store_status'],
                            'old_status'=>$edit_serial_v['old_status'],
                            'old_store'=>$edit_serial_v['old_store']
                        ]);
                    }
                }
                // 序列号跟踪
                if(!empty($serial_log)){
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                }
                $result = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'0'])->update(['inbound_status'=>1,'operation_id'=>$admin['id']]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if($result !== false){
                // 写入销货订单日志：成品入库
                foreach ($list as $logOrder) {
                    if (!empty($logOrder['sales_order_id'])) {
                        GoodsSalesOrderLog::writeLogByCode($logOrder['sales_order_id'], '组装单成品入库', '组装单[' . $logOrder['purchase_code'] . ']组合件入库审核通过', 'assemble', $logOrder['id']);
                    }
                }
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据组合件入库审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('组合件入库审核失败');
            }
        }else{
            // 组合件入库反审核
            $list = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'1'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            $purchase_code = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'1'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要组合件反审核入库的数据');
            }

            // 手动审核模式：仅反转审核状态，不做库存变动（实际入库通过 outboundEdit 页面填写）
            if($examine_set==1){
                $admin = Session::get('admin');
                $result = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'1'])->update(['inbound_status'=>0,'operation_id'=>$admin['id']]);
                if($result !== false){
                    if($examine_set!=1){
                        return ['status'=>1,'msg'=>'操作成功'];
                    }
                    $this->success(sizeof($list).'条数据组合件入库反审核成功');
                }else{
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'操作失败'];
                    }
                    $this->error('组合件入库反审核失败');
                }
            }

            $editstock = [];//库存表修改库存
            $goods_stock = [];//商品列表库存
            $goods_batch_edit = [];//批次库存更新
            // 只查询组合件(type=1)
            $info_list = Db::name('kuerp_assemble_info')->where(['purchase_code'=>['in',$purchase_code],'type'=>'1'])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                $goods = Db::name('kuerp_goods')->where(['id'=>$info_list_v['goods_id']])->find();
                $stock_number = $info_list_v['basic_number'];

                // 反审核：库存减少（恢复）
                $status = false;
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['id']==$goods['id']){
                        $goods_stock[$goods_stock_k]['inventory'] = $goods_stock_v['inventory']-$stock_number;
                        $status = true;
                    }
                }
                if(!$status){
                    $goods_stock[] = [
                        'id'=>$goods['id'],
                        'inventory'=>$goods['inventory']-$stock_number,
                    ];
                }
                foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
                    if($goods_stock_v['inventory']<0){
                        $this->error($goods['goods_name'].'库存不足');
                    }
                }

                $where = [];
                $where['goods_code'] = $info_list_v['goods_code'];
                $where['stores_id'] = $info_list_v['stores_id'];
                if($info_list_v['goods_attr']>0){
                    $goods_attr = $info_list_v['goods_attr'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] -= $stock_number;
                            $editstock[$editstock_k]['cost_money'] = round($editstock_v['cost_money']-$info_list_v['cost_money'],4);
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>round($stock['stock']-$stock_number,4),
                            'cost_money'=>round($stock['cost_money']-$info_list_v['cost_money'],4)
                        ];
                    }
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['stock']<0){
                            $this->error($goods['goods_name'].'库存不足');
                        }
                    }
                }else{
                    $this->error($goods['goods_name'].'库存不足');
                }

                // 批次跟踪 - 恢复
                if($batch_set==1 && $goods['batch']==1){
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if(isset($goods_attr) && $goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $where['batch_id'] = $info_list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                        foreach ($goods_batch_edit as $goods_batch_edit_k=>$goods_batch_edit_v){
                            if($goods_batch_edit_v<0){
                                $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                            }
                        }
                    }
                }

                // 序列号跟踪 - 恢复
                if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                    $period = unserialize($info_list_v['period']);
                    foreach ($period as $period_k=>$period_v){
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->find();
                        if($per_s){
                            $edit_serial[] = ['serial'=>$per_s['serial'],'assemble_id'=>$info_list_v['id'],'type'=>$per_s['old_status'],'store_status'=>$per_s['old_store']];
                        }else{
                            $this->error($goods['goods_name'].'序列号'.$period_v['serial'].'不存在');
                        }
                    }
                }
            }

            $info_id = Db::name('kuerp_assemble_info')->where(['purchase_code'=>['in',$purchase_code],'type'=>'1'])->column('id');
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }

                // 只删除入库(type=2)相关日志
                Db::name('kuerp_goods_log')->where(['business_type'=>'12','purchase_id'=>['in',$info_id]])->delete();
                Db::name('kuerp_goods_batch_log')->where(['business_type'=>'11','order_id'=>['in',$info_id]])->delete();
                // 删除入库新增的序列号 (assemble_status=2)
                Db::name('kuerp_goods_serial')->where(['assemble_id'=>['in',$info_id],'assemble_status'=>'2'])->delete();
                // 恢复序列号
                if(!empty($edit_serial)){
                    foreach ($edit_serial as $edit_serial_k=>$edit_serial_v){
                        Db::name('kuerp_goods_serial')->where(['serial'=>$edit_serial_v['serial']])->update([
                            'status'=>$edit_serial_v['type'],
                            'store_status'=>$edit_serial_v['store_status']
                        ]);
                    }
                }
                Db::name('kuerp_goods_serial_log')->where(['business_type'=>'11','order_id'=>['in',$info_id]])->delete();

                $result = $this->model->where(['id'=>['in',$ids],'inbound_status'=>'1'])->update(['inbound_status'=>0,'operation_id'=>$admin['id']]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if($result !== false){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success(sizeof($list).'条数据组合件入库反审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('组合件入库反审核失败');
            }
        }
    }

    //实际出库/入库编辑页面
    public function outboundEdit($ids = null)
    {
        $ids = $ids ? $ids : $this->request->get('ids');
        if (empty($ids)) {
            $this->error('参数错误');
        }
        $row = $this->model->where(['id' => $ids])->find();
        if (!$row) {
            $this->error('组装单不存在');
        }
        if ($row['examine_status'] != 1) {
            $this->error('请先审核单据');
        }

        // 获取详情（提前查询，用于判断是否已完成全部出库和入库）
        $info_list = Db::name('kuerp_assemble_info')->alias('a')
            ->join('kuerp_goods g', 'g.id=a.goods_id')
            ->where(['a.purchase_code' => $row['purchase_code']])
            ->field('a.*,g.goods_name')
            ->order('a.type', 'ASC')
            ->select();
        foreach ($info_list as &$v) {
            $v['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $v['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
            // 属性名称
            $v['attr_name'] = '';
            if (!empty($v['goods_attr'])) {
                $attribute_id = Db::name('kuerp_goods_attr')->where(['id' => $v['goods_attr']])->value('attribute_id');
                if ($attribute_id) {
                    $attribute_ids = Db::name('kuerp_aux_combination')->where(['id' => $attribute_id])->value('attr_ids');
                    if ($attribute_ids) {
                        $attr_ids_arr = explode('&', $attribute_ids);
                        $attr_names = Db::name('kuerp_auxiliary')->where(['id' => ['in', $attr_ids_arr]])->column('name');
                        $v['attr_name'] = implode('/', $attr_names);
                    }
                }
            }
        }
        unset($v);

        // 检查是否已完成全部出库和入库（基于实际数量，而非审核状态）
        $all_out_done = true;
        $all_in_done = true;
        foreach ($info_list as $v) {
            if ($v['type'] == '2' && $v['out_number'] < $v['number']) {
                $all_out_done = false;
            }
            if ($v['type'] == '1' && $v['in_number'] < $v['number']) {
                $all_in_done = false;
            }
        }
        if ($all_out_done && $all_in_done) {
            $this->error('该单据已完成全部出库和入库');
        }

        // 分离子件(type=2)和组合件(type=1)
        $out_items = []; // 子件(出库)
        $in_item = [];   // 组合件(入库)
        foreach ($info_list as $v) {
            if ($v['type'] == '2') {
                $out_items[] = $v;
            } else {
                $in_item[] = $v;
            }
        }

        $this->view->assign("row", $row);
        $this->view->assign("out_items", $out_items);
        $this->view->assign("in_items", $in_item);
        $this->assignconfig("assembleId", $row['id']);
        $this->assignconfig("purchaseCode", $row['purchase_code']);
        $this->assignconfig("outboundStatus", $row['outbound_status']);
        $this->assignconfig("inboundStatus", $row['inbound_status']);

        return $this->view->fetch('outbound_edit');
    }

    //保存实际出库/入库
    public function saveOutbound($ids = null)
    {
        $ids = $ids ? $ids : $this->request->post('ids');
        if (empty($ids)) {
            $this->error('参数错误');
        }
        $row = $this->model->where(['id' => $ids])->find();
        if (!$row) {
            $this->error('组装单不存在');
        }
        if ($row['examine_status'] != 1) {
            $this->error('请先审核单据');
        }

        $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
        if ($checkout !== true) {
            $this->error($checkout);
        }

        $serial_set = Db::name('kuerp_sets')->where(['name' => 'serial'])->value('is_checked');
        $batch_set = Db::name('kuerp_sets')->where(['name' => 'batch'])->value('is_checked');
        $admin = Session::get('admin');

        // 接收子件实际出库数量
        $out_data = $this->request->post('out_data/a'); // ['info_id' => out_number, ...]
        // 接收组合件实际入库数量
        $in_data = $this->request->post('in_data/a');   // ['info_id' => in_number, ...]

        $has_out = false;
        $has_in = false;

        // 操作顺序校验：子件出库需要 examine_status==1（子件出库审核通过）
        if (!empty($out_data) && $row['examine_status'] != 1) {
            $this->error('请先进行子件出库审核，再填写实际出库数量');
        }
        // 操作顺序校验：组合件入库需要 inbound_status==1（组合件入库审核通过）
        if (!empty($in_data) && $row['inbound_status'] != 1) {
            $this->error('请先进行组合件入库审核，再填写实际入库数量（操作顺序：先子件出库审核 → 再组合件入库审核）');
        }

        // ===== 处理子件出库（type=2）=====
        $editstock_out = [];
        $addstock_out = [];
        $goods_stock_out = [];
        $goods_stock_log_out = [];
        $goods_batch_edit_out = [];
        $goods_batch_log_out = [];
        $edit_serial_out = [];
        $serial_log_out = [];
        $update_out_info = []; // 更新 out_number 的记录

        if (!empty($out_data) && $row['outbound_status'] != 2) {
            foreach ($out_data as $info_id => $out_number) {
                $out_number = (float)$out_number;
                if ($out_number <= 0) {
                    continue;
                }
                $info = Db::name('kuerp_assemble_info')->where(['id' => $info_id, 'type' => '2', 'purchase_code' => $row['purchase_code']])->find();
                if (!$info) {
                    continue;
                }
                // 本次可出库上限 = 计划数量 - 已出库数量
                $max_out = $info['number'] - $info['out_number'];
                if ($out_number > $max_out) {
                    $this->error('子件实际出库数量不能超过' . $max_out);
                }

                $has_out = true;
                $goods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->find();
                $basic_number = round(($info['basic_number'] / max($info['number'], 0.0001)) * $out_number, 4);

                // 商品表库存扣减
                $status = false;
                foreach ($goods_stock_out as $gk => $gv) {
                    if ($gv['id'] == $goods['id']) {
                        $goods_stock_out[$gk]['inventory'] = $gv['inventory'] - $basic_number;
                        $status = true;
                    }
                }
                if (!$status) {
                    $goods_stock_out[] = [
                        'id'        => $goods['id'],
                        'inventory' => $goods['inventory'] - $basic_number,
                    ];
                }

                // 检查库存充足
                foreach ($goods_stock_out as $gv) {
                    if ($gv['inventory'] < 0) {
                        $this->error($goods['goods_name'] . '库存不足');
                    }
                }

                // 仓库库存扣减
                $where = [];
                $where['goods_code'] = $info['goods_code'];
                $where['stores_id'] = $info['stores_id'];
                $goods_attr = 0;
                if ($info['goods_attr'] > 0) {
                    $goods_attr = $info['goods_attr'];
                    $where['attribute_id'] = $goods_attr;
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if ($stock) {
                    $status = false;
                    foreach ($editstock_out as $ek => $ev) {
                        if ($ev['id'] == $stock['id']) {
                            $editstock_out[$ek]['stock'] -= $basic_number;
                            $editstock_out[$ek]['cost_money'] = round($ev['cost_money'] - $info['cost_money'], 4);
                            $status = true;
                        }
                    }
                    if (!$status) {
                        $editstock_out[] = [
                            'id'         => $stock['id'],
                            'stock'      => round($stock['stock'] - $basic_number, 4),
                            'cost_money' => round($stock['cost_money'] - $info['cost_money'], 4)
                        ];
                    }
                } else {
                    $this->error($goods['goods_name'] . '库存不足');
                }

                // 收发明细
                $goods_stock_log_out[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info['purchase_code'],
                    'purchase_id'   => $info['id'],
                    'purchase_time' => $info['purchase_time'],
                    'business_type' => '13',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info['goods_attr'],
                    'goods_unit'    => (float)$info['goods_unit'],
                    'stores_id'     => (float)$info['stores_id'],
                    'remark'        => $info['remark'],
                    'enter_number'  => null,
                    'enter_basics'  => null,
                    'enter_unit'    => null,
                    'enter_cost'    => null,
                    'out_number'    => $out_number,
                    'out_basics'    => $basic_number,
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);

                // 批次跟踪
                if ($batch_set == 1 && $goods['batch'] == 1) {
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if ($goods_attr > 0) {
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info['stores_id'];
                    $where['batch_id'] = $info['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if (empty($goods_batch_stock)) {
                        $this->error($goods['goods_name'] . $info['batch'] . '库存不足');
                    } else {
                        if (isset($goods_batch_edit_out[$goods_batch_stock['id']])) {
                            $goods_batch_edit_out[$goods_batch_stock['id']] -= $basic_number;
                        } else {
                            $goods_batch_edit_out[$goods_batch_stock['id']] = $goods_batch_stock['stock'] - $basic_number;
                        }
                        foreach ($goods_batch_edit_out as $bv) {
                            if ($bv < 0) {
                                $this->error($goods['goods_name'] . $info['batch'] . '批次库存不足');
                            }
                        }
                    }
                    $goods_batch_log_out[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'        => $goods['id'],
                        'goods_unit'      => $info['goods_unit'],
                        'stores_id'       => $info['stores_id'],
                        'batch_id'        => (float)$info['batch_id'],
                        'number'          => $basic_number,
                        'purchase_code'   => $info['purchase_code'],
                        'purchase_time'   => $info['purchase_time'],
                        'business_type'   => '12',
                        'remarks'         => $info['remark'],
                        'order_id'        => $info['id'],
                        'tax_money'       => 0,
                        'money_tax_price' => $info['cost_money'],
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]);
                }

                // 序列号跟踪
                if ($goods && $goods['serial'] == 1 && $serial_set == 1 && !empty($info['period'])) {
                    $period = unserialize($info['period']);
                    $out_count = 0;
                    foreach ($period as $period_k => $period_v) {
                        if ($out_count >= $out_number) break;
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial' => $period_v['serial']])->field('serial,status,store_status')->find();
                        if ($per_s) {
                            $edit_serial_out[] = ['serial' => $per_s['serial'], 'assemble_id' => $info['id'], 'type' => 12, 'store_status' => '2', 'old_status' => $per_s['status'], 'old_store' => $per_s['store_status']];
                        } else {
                            $this->error($goods['goods_name'] . '序列号' . $period_v['serial'] . '不存在');
                        }
                        $serial_log_out[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $info['goods_id'],
                            'stores_id'     => $info['stores_id'],
                            'attribute_id'  => isset($goods_attr) ? $goods_attr : 0,
                            'serial'        => $period_v['serial'],
                            'business_type' => '12',
                            'store_status'  => '2',
                            'remarks'       => $period_v['remark'],
                            'purchase_id'   => $info['id'],
                            'purchase_code' => $info['purchase_code'],
                            'purchase_time' => $info['purchase_time'],
                            'order_id'      => $info['id'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                        $out_count++;
                    }
                }

                $update_out_info[] = [
                    'id'         => $info_id,
                    'out_number' => round($info['out_number'] + $out_number, 4),
                ];
            }
        }

        // ===== 处理组合件入库（type=1）=====
        $editstock_in = [];
        $addstock_in = [];
        $goods_stock_in = [];
        $goods_stock_log_in = [];
        $goods_batch_add_in = [];
        $goods_batch_edit_in = [];
        $goods_batch_log_in = [];
        $per_code_in = [];
        $goods_serial_in = [];
        $edit_serial_in = [];
        $serial_log_in = [];
        $edit_batch_in = [];
        $update_in_info = [];

        if (!empty($in_data)) {
            foreach ($in_data as $info_id => $in_number) {
                $in_number = (float)$in_number;
                if ($in_number <= 0) {
                    continue;
                }
                $info = Db::name('kuerp_assemble_info')->where(['id' => $info_id, 'type' => '1', 'purchase_code' => $row['purchase_code']])->find();
                if (!$info) {
                    continue;
                }
                $max_in = $info['number'] - $info['in_number'];
                if ($in_number > $max_in) {
                    $this->error('组合件实际入库数量不能超过' . $max_in);
                }

                $has_in = true;
                $goods = Db::name('kuerp_goods')->where(['id' => $info['goods_id']])->find();
                $basic_number = round(($info['basic_number'] / max($info['number'], 0.0001)) * $in_number, 4);

                // 商品表库存增加
                $status = false;
                foreach ($goods_stock_in as $gk => $gv) {
                    if ($gv['id'] == $goods['id']) {
                        $goods_stock_in[$gk]['inventory'] = $gv['inventory'] + $basic_number;
                        $status = true;
                    }
                }
                if (!$status) {
                    $goods_stock_in[] = [
                        'id'        => $goods['id'],
                        'inventory' => $goods['inventory'] + $basic_number,
                    ];
                }

                // 仓库库存增加
                $where = [];
                $where['goods_code'] = $info['goods_code'];
                $where['stores_id'] = $info['stores_id'];
                $goods_attr = 0;
                if ($info['goods_attr'] > 0) {
                    $goods_attr = $info['goods_attr'];
                    $where['attribute_id'] = $goods_attr;
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                if ($stock) {
                    $status = false;
                    foreach ($editstock_in as $ek => $ev) {
                        if ($ev['id'] == $stock['id']) {
                            $editstock_in[$ek]['stock'] += $basic_number;
                            $editstock_in[$ek]['cost_money'] = round($ev['cost_money'] + $info['cost_money'], 4);
                            $status = true;
                        }
                    }
                    if (!$status) {
                        $editstock_in[] = [
                            'id'         => $stock['id'],
                            'stock'      => round($stock['stock'] + $basic_number, 4),
                            'cost_money' => round($stock['cost_money'] + $info['cost_money'], 4)
                        ];
                    }
                } else {
                    $addstock_in[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'     => $goods['id'],
                        'goods_code'   => $info['goods_code'],
                        'attribute_id' => $goods_attr,
                        'stores_id'    => $info['stores_id'],
                        'stock'        => $basic_number,
                        'cost_money'   => round($info['cost_money'], 4),
                        'createtime'   => time(),
                        'updatetime'   => time(),
                    ]);
                }

                // 收发明细
                $goods_stock_log_in[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code' => $info['purchase_code'],
                    'purchase_id'   => $info['id'],
                    'purchase_time' => $info['purchase_time'],
                    'business_type' => '12',
                    'goods_id'      => $goods['id'],
                    'goods_code'    => $goods['goods_code'],
                    'attribute_id'  => (float)$info['goods_attr'],
                    'goods_unit'    => (float)$info['goods_unit'],
                    'stores_id'     => (float)$info['stores_id'],
                    'remark'        => $info['remark'],
                    'enter_number'  => $in_number,
                    'enter_basics'  => $basic_number,
                    'out_number'    => null,
                    'out_basics'    => null,
                    'out_unit'      => null,
                    'out_cost'      => null,
                    'createtime'    => time(),
                    'updatetime'    => time(),
                ]);

                // 批次跟踪
                if ($batch_set == 1 && $goods['batch'] == 1) {
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if ($goods_attr > 0) {
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $info['stores_id'];
                    $where['batch_id'] = $info['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if (empty($goods_batch_stock)) {
                        $status = false;
                        foreach ($goods_batch_add_in as $bak => $bav) {
                            if ($bav['batch_id'] == $info['batch_id'] && $bav['goods_id'] == $info['goods_id'] && $bav['attribute_id'] == $goods_attr && $bav['stores_id'] == $info['stores_id']) {
                                $goods_batch_add_in[$bak]['stock'] += $basic_number;
                                $status = true;
                            }
                        }
                        if (!$status) {
                            $batch_id = $info['batch_id'];
                            if (empty($batch_id)) {
                                $batch_id = Db::name('kuerp_goods_batch')->insertGetId(\addons\kuerp\library\Goods::setDataInfo([
                                    'goods_id'     => $info['goods_id'],
                                    'goods_code'   => $goods['goods_code'],
                                    'attribute_id' => $goods_attr,
                                    'stores_id'    => $info['stores_id'],
                                    'batch_name'   => $info['batch'],
                                    'manufacture'  => $info['manufacture'],
                                    'guarantee'    => $info['guarantee'],
                                    'validity'     => $info['validity'],
                                    'createtime'   => time(),
                                    'updatetime'   => time(),
                                ]));
                            }
                            $edit_batch_in[] = ['id' => $info['id'], 'batch_id' => $batch_id];
                            $goods_batch_add_in[] = \addons\kuerp\library\Goods::setDataInfo([
                                'batch_id'     => $batch_id,
                                'goods_id'     => $info['goods_id'],
                                'goods_code'   => $goods['goods_code'],
                                'attribute_id' => $goods_attr,
                                'stores_id'    => $info['stores_id'],
                                'stock'        => $basic_number,
                                'createtime'   => time(),
                                'updatetime'   => time(),
                            ]);
                        }
                    } else {
                        if (isset($goods_batch_edit_in[$goods_batch_stock['id']])) {
                            $goods_batch_edit_in[$goods_batch_stock['id']] += $basic_number;
                        } else {
                            $goods_batch_edit_in[$goods_batch_stock['id']] = $goods_batch_stock['stock'] + $basic_number;
                        }
                    }
                    $goods_batch_log_in[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'        => $goods['id'],
                        'goods_unit'      => $info['goods_unit'],
                        'stores_id'       => $info['stores_id'],
                        'batch_id'        => (float)$info['batch_id'],
                        'number'          => $basic_number,
                        'purchase_code'   => $info['purchase_code'],
                        'purchase_time'   => $info['purchase_time'],
                        'business_type'   => '11',
                        'remarks'         => $info['remark'],
                        'order_id'        => $info['id'],
                        'tax_money'       => 0,
                        'money_tax_price' => $info['cost_money'],
                        'createtime'      => time(),
                        'updatetime'      => time(),
                    ]);
                }

                // 序列号跟踪
                if ($goods && $goods['serial'] == 1 && $serial_set == 1 && !empty($info['period'])) {
                    $period = unserialize($info['period']);
                    $in_count = 0;
                    foreach ($period as $period_k => $period_v) {
                        if ($in_count >= $in_number) break;
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial' => $period_v['serial']])->field('serial,status,store_status')->find();
                        if ($per_s) {
                            $edit_serial_in[] = ['serial' => $per_s['serial'], 'assemble_id' => $info['id'], 'type' => 11, 'store_status' => 1, 'old_status' => $per_s['status'], 'old_store' => $per_s['store_status']];
                        } else {
                            if (!in_array($period_v['serial'], $per_code_in)) {
                                $per_code_in[] = $period_v['serial'];
                            }
                            $goods_serial_in[] = \addons\kuerp\library\Goods::setDataInfo([
                                'assemble_id'     => $info['id'],
                                'goods_id'        => $goods['id'],
                                'goods_code'      => $goods['goods_code'],
                                'stores_id'       => $info['stores_id'],
                                'attribute_id'    => isset($goods_attr) ? $goods_attr : 0,
                                'serial'          => $period_v['serial'],
                                'remark'          => $period_v['remark'],
                                'status'          => '11',
                                'store_status'    => '1',
                                'assemble_status' => '2',
                                'order_id'        => $info['id'],
                                'createtime'      => time(),
                                'updatetime'      => time(),
                            ]);
                        }
                        $serial_log_in[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $info['goods_id'],
                            'stores_id'     => $info['stores_id'],
                            'attribute_id'  => isset($goods_attr) ? $goods_attr : 0,
                            'serial'        => $period_v['serial'],
                            'business_type' => '11',
                            'store_status'  => '1',
                            'remarks'       => $period_v['remark'],
                            'purchase_id'   => $info['id'],
                            'purchase_code' => $info['purchase_code'],
                            'purchase_time' => $info['purchase_time'],
                            'order_id'      => $info['id'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                        $in_count++;
                    }
                }

                $update_in_info[] = [
                    'id'        => $info_id,
                    'in_number' => round($info['in_number'] + $in_number, 4),
                ];
            }
        }

        // 判断出库状态
        $new_outbound_status = $row['outbound_status'];
        if ($has_out) {
            // 检查是否所有子件都已全部出库
            $all_out = true;
            $info_list = Db::name('kuerp_assemble_info')->where(['purchase_code' => $row['purchase_code'], 'type' => '2'])->select();
            foreach ($info_list as $iv) {
                $current_out = $iv['out_number'];
                foreach ($update_out_info as $ui) {
                    if ($ui['id'] == $iv['id']) {
                        $current_out = $ui['out_number'];
                        break;
                    }
                }
                if ($current_out < $iv['number']) {
                    $all_out = false;
                    break;
                }
            }
            $new_outbound_status = $all_out ? 2 : 1;
        }

        // 判断入库状态
        $new_inbound_status = $row['inbound_status'];
        if ($has_in) {
            $all_in = true;
            $info_list_in = Db::name('kuerp_assemble_info')->where(['purchase_code' => $row['purchase_code'], 'type' => '1'])->select();
            foreach ($info_list_in as $iv) {
                $current_in = $iv['in_number'];
                foreach ($update_in_info as $ui) {
                    if ($ui['id'] == $iv['id']) {
                        $current_in = $ui['in_number'];
                        break;
                    }
                }
                if ($current_in < $iv['number']) {
                    $all_in = false;
                    break;
                }
            }
            $new_inbound_status = $all_in ? 1 : $row['inbound_status'];
        }

        if (!$has_out && !$has_in) {
            $this->error('请至少填写一项实际数量');
        }

        Db::startTrans();
        try {
            // 处理出库
            if ($has_out) {
                if (!empty($editstock_out)) {
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock_out);
                }
                if (!empty($goods_stock_out)) {
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock_out);
                }
                if (!empty($goods_stock_log_out)) {
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log_out);
                }
                if (!empty($goods_batch_edit_out)) {
                    foreach ($goods_batch_edit_out as $k => $v) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $k])->update(['stock' => $v]);
                    }
                }
                if (!empty($goods_batch_log_out)) {
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log_out);
                }
                if (!empty($edit_serial_out)) {
                    foreach ($edit_serial_out as $esv) {
                        Db::name('kuerp_goods_serial')->where(['serial' => $esv['serial']])->update([
                            'assemble_id'  => $esv['assemble_id'],
                            'status'       => $esv['type'],
                            'store_status' => $esv['store_status'],
                            'old_status'   => $esv['old_status'],
                            'old_store'    => $esv['old_store']
                        ]);
                    }
                }
                if (!empty($serial_log_out)) {
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log_out);
                }
                // 更新 out_number
                foreach ($update_out_info as $ui) {
                    Db::name('kuerp_assemble_info')->where(['id' => $ui['id']])->update(['out_number' => $ui['out_number']]);
                }
            }

            // 处理入库
            if ($has_in) {
                if (!empty($editstock_in)) {
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock_in);
                }
                if (!empty($addstock_in)) {
                    foreach ($addstock_in as $asv) {
                        $where = [];
                        $where['goods_code'] = $asv['goods_code'];
                        $where['stores_id'] = $asv['stores_id'];
                        if ($asv['attribute_id'] > 0) {
                            $where['attribute_id'] = $asv['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_stock')->where($where)->find();
                        if ($stock) {
                            Db::name('kuerp_goods_stock')->where(['id' => $stock['id']])->inc('stock', $asv['stock'])->update();
                        } else {
                            Db::name('kuerp_goods_stock')->insert($asv);
                        }
                    }
                }
                if (!empty($goods_stock_in)) {
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock_in);
                }
                if (!empty($goods_stock_log_in)) {
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log_in);
                }
                if (!empty($goods_batch_add_in)) {
                    foreach ($goods_batch_add_in as $bav) {
                        $where = [];
                        $where['goods_code'] = $bav['goods_code'];
                        $where['stores_id'] = $bav['stores_id'];
                        $where['batch_id'] = $bav['batch_id'];
                        if ($bav['attribute_id'] > 0) {
                            $where['attribute_id'] = $bav['attribute_id'];
                        }
                        $stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if ($stock) {
                            Db::name('kuerp_goods_batch_stock')->where(['id' => $stock['id']])->inc('stock', $bav['stock'])->update();
                        } else {
                            Db::name('kuerp_goods_batch_stock')->insert($bav);
                        }
                    }
                }
                if (!empty($goods_batch_edit_in)) {
                    foreach ($goods_batch_edit_in as $k => $v) {
                        Db::name('kuerp_goods_batch_stock')->where(['id' => $k])->update(['stock' => $v]);
                    }
                }
                if (!empty($goods_batch_log_in)) {
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log_in);
                }
                if (!empty($edit_batch_in)) {
                    foreach ($edit_batch_in as $ebv) {
                        Db::name('kuerp_assemble_info')->where(['id' => $ebv['id']])->update(['batch_id' => $ebv['batch_id']]);
                    }
                }
                if (!empty($goods_serial_in)) {
                    Db::name('kuerp_goods_serial')->insertAll($goods_serial_in);
                }
                if (!empty($edit_serial_in)) {
                    foreach ($edit_serial_in as $esv) {
                        Db::name('kuerp_goods_serial')->where(['serial' => $esv['serial']])->update([
                            'assemble_id'  => $esv['assemble_id'],
                            'status'       => $esv['type'],
                            'store_status' => $esv['store_status'],
                            'old_status'   => $esv['old_status'],
                            'old_store'    => $esv['old_store']
                        ]);
                    }
                }
                if (!empty($serial_log_in)) {
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log_in);
                }
                foreach ($update_in_info as $ui) {
                    Db::name('kuerp_assemble_info')->where(['id' => $ui['id']])->update(['in_number' => $ui['in_number']]);
                }
            }

            // 更新主表状态
            $update_data = ['operation_id' => $admin['id']];
            if ($has_out) {
                $update_data['outbound_status'] = $new_outbound_status;
                $update_data['outbound_time'] = time();
            }
            if ($has_in) {
                $update_data['inbound_status'] = $new_inbound_status;
                $update_data['in_time'] = time();
            }
            $this->model->where(['id' => $ids])->update($update_data);

            Db::commit();

            // 消费锁定：手动出库的子件扣减对应销售订单的锁定库存
            if ($has_out && !empty($row['sales_order_id']) && !empty($out_data)) {
                foreach ($out_data as $info_id => $out_number) {
                    $out_number = (float)$out_number;
                    if ($out_number <= 0) continue;
                    $info = Db::name('kuerp_assemble_info')->where(['id' => $info_id, 'type' => '2', 'purchase_code' => $row['purchase_code']])->find();
                    if (!$info) continue;
                    $basic_number = round(($info['basic_number'] / max($info['number'], 0.0001)) * $out_number, 4);
                    $attrId = $info['goods_attr'] ? (int)$info['goods_attr'] : 0;
                    \addons\kuerp\library\Goods::consumeLock(
                        $row['sales_order_id'],
                        $info['goods_id'],
                        $info['stores_id'],
                        $basic_number,
                        $attrId
                    );
                }
            }
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        // 写入销货订单日志：成品入库
        if ($has_in && !empty($row['sales_order_id'])) {
            GoodsSalesOrderLog::writeLogByCode($row['sales_order_id'], '组装单成品入库', '组装单[' . $row['purchase_code'] . ']组合件入库审核通过', 'assemble', $ids);
        }
        $msg = '';
        if ($has_out) $msg .= '子件出库成功';
        if ($has_in) $msg .= ($msg ? '，' : '') . '组合件入库成功';
        $this->success($msg);
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

        // 获取组装单详情(组合件 type=1 和 子件 type=2)
        $assemble_info = Db::name('kuerp_assemble_info')
            ->where(['purchase_code' => $row['purchase_code']])
            ->select();

        $detailHiddenFields = ['unit_cost', 'cost_money'];
        foreach ($assemble_info as $k => $v) {
            $assemble_info[$k]['goods_name'] = Db::name('kuerp_goods')
                ->where(['id' => $v['goods_id']])
                ->value('goods_name');
            $assemble_info[$k]['stores_name'] = Db::name('kuerp_stores')
                ->where(['id' => $v['stores_id']])
                ->value('name');
            $assemble_info[$k]['unit_name'] = Db::name('kuerp_unit')
                ->where(['id' => $v['goods_unit']])
                ->value('name');
            // 属性名称
            $assemble_info[$k]['attr_name'] = '';
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
                        $assemble_info[$k]['attr_name'] = implode('/', $attr_names);
                    }
                }
            }
            foreach ($detailHiddenFields as $f) {
                unset($assemble_info[$k][$f]);
            }
        }

        $this->view->assign("row", $row);
        $this->view->assign("assemble_info", $assemble_info);

        // 增加打印次数
        $this->model->where(['id' => $ids])->setInc('print_num');

        return $this->view->fetch();
    }

    //保存模板
    public function savetemp($ids = null)
    {
        if ($this->request->isPost()) {
            $data = input();
            $team_name = isset($data['temp_name'])?trim($data['temp_name']):'';
            $params = isset($data['row'])?$data['row']:'';
            if(empty($team_name)){
                $this->error('请输入模板名称');
            }
            if ($params) {
                $team = Db::name('kuerp_assemble_temp')->where(['temp_name'=>$team_name])->find();
                if($team){
                    $this->error('模板名称不能重复');
                }
                $params = $this->preExcludeFields($params);
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');
                $serial= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');//序列号设置
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');//批次设置

                $data = [];
                $goods_serial_all = [];//全部序列号,重复判断使用
                $p = 2;$z = 2;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        if(!isset($params['goods_unit'][$k]) || empty($params['goods_unit'][$k])){
                            $this->error('请选择单位');
                        }
                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        $goods_unit = $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k];
                        $advance_rate = 1;//换算进率
                        if($goods['more_unit']==1) {
                            if($goods_unit!=$goods['unit_id']){
                                //所选单位不等于商品默认单位，需查询进率换算基础数量
                                $more_unit = Db::name('kuerp_unit_conversion')
                                    ->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$goods_unit])
                                    ->field('id,advance_rate')
                                    ->find();
                                if(empty($more_unit)){
                                    $this->error('商品单位信息查询失败');
                                }else{
                                    $advance_rate = $more_unit['advance_rate'];
                                }
                            }
                        }
                        $basic_number = round(($advance_rate*$params['number'][$k]),4);

                        if($k==0){
                            //组合件
                            //批次
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();//已有批次
                            if($goods['batch']==1 && $batch==1){
                                if(empty($params['batch'][$k])){
                                    if($batch_data){
                                        $this->error('请选择生产批次');
                                    }else{
                                        $this->error('请输入生产批次');
                                    }
                                }
                                if(empty($params['manufacture'][$k])){
                                    $this->error('请输入生产日期');
                                }
                                if($params['guarantee'][$k]===''){
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k]<0){
                                    $this->error('保质期天数不可小于0');
                                }
                                if(empty($params['validity'][$k])){
                                    $this->error('请输入过期时间');
                                }
                            }
                            //序列号
                            $goods_serial='';
                            if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial==1){
                                if(empty($params['period_period'][$k])){
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k=>$period_period_v){
                                    if(in_array($period_period_v['serial'],$goods_serial_all)){
                                        $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                    }else{
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code]])->column('serial');
                                if($serial){
                                    $this->error('商品序列号'.$serial[0].'已占用');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'     =>  $params['purchase_code'],
                                'purchase_time'     =>  strtotime($params['purchase_time']),
                                'business_type'     =>  1,
                                'type'              =>  1,

                                'goods_id'          =>  $goods['id'],//商品信息
                                'goods_code'        =>  $goods['goods_code'],
                                'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                                'goods_unit'        =>  $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k],

                                'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                                'allow_stock'       =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                                'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                                'period'            =>  $goods_serial,
                                'basic_number'      =>  $basic_number,
                                'unit_cost'         =>  $params['unit_cost'][$k]===''?null:(float)$params['unit_cost'][$k],
                                'cost_money'        =>  $params['cost_money'][$k]===''?null:(float)$params['cost_money'][$k],
                                'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],

                                'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                                'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                                'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                                'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                                'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                                'preparation_id'    =>  $admin['id'],
                                'operation_id'      =>  $admin['id'],

                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]);
                            $p = 1;
                        }else{
                            //子件
                            //批次
                            $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();//已有批次
                            if($goods['batch']==1 && $batch==1){
                                if(empty($params['batch'][$k])){
                                    $this->error('请选择生产批次');
                                }
                                if(empty($params['manufacture'][$k])){
                                    $this->error('请输入生产日期');
                                }
                                if($params['guarantee'][$k]===''){
                                    $this->error('请输入保质期');
                                }
                                if ($params['guarantee'][$k]<0){
                                    $this->error('保质期天数不可小于0');
                                }
                                if(empty($params['validity'][$k])){
                                    $this->error('请输入过期时间');
                                }
                                if(empty($batch_data)){
                                    $this->error('商品批次信息查询失败');
                                }
                            }
                            //序列号
                            $goods_serial='';
                            if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial==1){
                                if(empty($params['period_period'][$k])){
                                    $this->error('请输入商品序列号');
                                }
                                $goods_serial = $params['period_period'][$k];
                                $period_period = unserialize($params['period_period'][$k]);
                                $serial_code = [];
                                foreach ($period_period as $period_period_k=>$period_period_v){
                                    if(in_array($period_period_v['serial'],$goods_serial_all)){
                                        $this->error('商品序列号'.$period_period_v['serial'].'重复');
                                    }else{
                                        $goods_serial_all[] = $period_period_v['serial'];
                                    }
                                    $serial_code[] = $period_period_v['serial'];
                                }
                                $serial = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code],'store_status'=>'1'])->column('serial');
                                if(sizeof($serial)!=sizeof($serial_code)){
                                    foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                        if(in_array($serial_code_v,$serial)){
                                            $this->error('商品序列号'.$serial_code_v.'不在库，不可出库');
                                        }
                                    }
                                    $this->error('商品序列号不在库，不可出库');
                                }
                                $basic_number = (float)$params['number'][$k];
                            }

                            $data[] = \addons\kuerp\library\Goods::setDataInfo([
                                'purchase_code'     =>  $params['purchase_code'],
                                'purchase_time'     =>  strtotime($params['purchase_time']),
                                'business_type'     =>  1,
                                'type'              =>  2,

                                'goods_id'          =>  $goods['id'],//商品信息
                                'goods_code'        =>  $goods['goods_code'],
                                'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                                'goods_unit'        =>  $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k],

                                'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                                'allow_stock'       =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                                'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                                'period'            =>  $goods_serial,
                                'basic_number'      =>  $basic_number,
                                // 'unit_cost'         =>  $params['unit_cost'][$k]===''?null:(float)$params['unit_cost'][$k],
                                // 'cost_money'        =>  $params['cost_money'][$k]===''?null:(float)$params['cost_money'][$k],
                                'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],

                                'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                                'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                                'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                                'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                                'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                                'preparation_id'    =>  $admin['id'],
                                'operation_id'      =>  $admin['id'],

                                'createtime'        => time(),
                                'updatetime'        => time(),
                            ]);
                            $z = 1;
                        }
                    }
                }
                if($p==2){
                    $this->error('组合件信息不能为空');
                }
                if($z==2){
                    $this->error('组合子件信息不能为空');
                }
                if(!empty($data)){
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'temp_name'         =>  $team_name,
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'business_type'     =>  1,
                        'money'             =>  $params['costs']===''?null:(float)$params['costs'],
                        'remark'            =>  $params['note']===''?null:$params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ]);
                }else{
                    $this->error('组件信息不能为空');
                }

                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = Db::name('kuerp_assemble_temp')->insertGetId($row);
                    }
                    foreach ($data as $data_k=>$data_v){
                        $data_v['purchase_id'] = $result;
                        Db::name('kuerp_assemble_info_temp')->insertGetId($data_v);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    $this->success($result);
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
    }
}
