<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 拆卸单
 *
 * @icon fa fa-circle-o
 */
class Split extends Backend
{
    protected $noNeedRight = ['savetemp'];
    /**
     * Split模型对象
     * @var \app\admin\model\kuerp\Split
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Split;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());
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
                $p_info = Db::name('kuerp_split_info')->alias('a')
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


                $info = Db::name('kuerp_split_info')->alias('a')
                    ->join('kuerp_goods g','g.id=a.goods_id')
                    ->where(['a.purchase_code'=>$list_v['purchase_code'],'a.type'=>'2'])
                    ->field('a.number,g.goods_name,a.goods_attr,a.unit_cost,a.cost_money,a.goods_unit')
                    ->select();
                foreach ($info as $info_k=>$info_v){
                    $row = [];
                    $row['id'] = $list_v['id'];
                    $row['preparation'] = $preparation;
                    $row['examine'] = $examine;
                    $row['examine_time'] = $examine_time;
                    $row['remark'] = $list_v['remark'];
                    $row['print_num'] = $list_v['print_num'];

                    $row['purchase_code'] = $list_v['purchase_code'];
                    $row['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                    $row['pnumber'] = $p_info['number'];
                    $row['pname'] = $p_info['goods_name'].' '.$p_info['attr_name'];
                    $row['unit_name'] = $p_info['unit_name'];
                    // $row['money'] = $list_v['money'];
                    // $row['punit_cost'] = $p_info['unit_cost'];
                    // $row['pcost_money'] = $p_info['cost_money'];

                    $info_v['attr_name'] = '';
                    if(!empty($info_v['goods_attr'])){
                        $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$info_v['goods_attr']])->value('attribute_id');
                        $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
                        if($attribute_ids){
                            $attribute_ids = explode('&',$attribute_ids);
                            $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                            $info_v['attr_name'] = implode('/',$attribute);
                        }
                    }
                    $info_v['unit_name'] = Db::name('kuerp_unit')->where(['id'=>$info_v['goods_unit']])->value('name');

                    $row['znumber'] = $info_v['number'];
                    $row['zname'] = $info_v['goods_name'].' '.$info_v['attr_name'];
                    // $row['zunit_cost'] = $info_v['unit_cost'];
                    // $row['zcost_money'] = $info_v['cost_money'];
                    $row['zunit_name'] = $info_v['unit_name'];

                    $lists[] = $row;
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
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
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

                        if($k>0){
                            //子件
                            if($v==$params['goods_code'][0]){
                                $this->error('子件不能和组合件为同一商品');
                            }
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
                                'business_type'     =>  '1',
                                'type'              =>  '2',

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
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $p = 1;
                        }else{
                            //组合件
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
                                'business_type'     =>  '1',
                                'type'              =>  '1',

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
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $z = 1;
                        }
                    }
                }
                if($z==2){
                    $this->error('组合件信息不能为空');
                }
                if($p==2){
                    $this->error('组合子件信息不能为空');
                }
                if(!empty($data)){
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'business_type'     =>  '1',
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
                        $purchase_id = Db::name('kuerp_split_info')->insertGetId($data_v);
                        //批次
                        if($goods_batch_info){
                            $goods_batch_info['split_id'] = $purchase_id;
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
        $code = \addons\kuerp\library\Goods::getCodes('',20);
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

                        if($k>0){
                            //子件
                            if($v==$params['goods_code'][0]){
                                $this->error('子件不能和组合件为同一商品');
                            }
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
                                    'type'        => '2',
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
                                'business_type'     =>  '1',
                                'type'              =>  '2',

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
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $p = 1;
                        }else{
                            //组合件
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
                                    'type'        => '2',
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
                                'business_type'     =>  '1',
                                'type'              =>  '1',

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
                                'goods_batch'       => $goods_batch,
                                'goods_batch_info'  => $goods_batch_info
                            ]);
                            $z = 1;
                        }
                    }
                }
                if($z==2){
                    $this->error('组合件信息不能为空');
                }
                if($p==2){
                    $this->error('组合子件信息不能为空');
                }
                if(!empty($data)){
                    $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'business_type'     =>  '1',
                        'money'             =>  $params['costs']===''?null:(float)$params['costs'],
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
                $goods_purchase_info = Db::name('kuerp_split_info')->where(['purchase_code'=>$old_purchase_code])->column('id');
                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    Db::name('kuerp_split_info')->where(['purchase_code'=>$old_purchase_code])->delete();
                    Db::name('kuerp_goods_batch_info')->where(['split_id'=>['in',$goods_purchase_info]])->delete();
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
                        $purchase_id = Db::name('kuerp_split_info')->insertGetId($data_v);

                        //批次
                        if($goods_batch_info){
                            $goods_batch_info['split_id'] = $purchase_id;
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
                                $this->del($result);
                            } catch (Exception $e) {
                            }
                            $this->error('添加失败，请刷新页面重试');
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

        $info = Db::name('kuerp_split_info')->alias('a')
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
            $info[$info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($info_v['goods_code'], $info_v['stores_id'], $info_v['goods_attr']),true);
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
                if(!in_array($list_v['id'],$goods_purchase_id)){
                    $goods_purchase_id[] = $list_v['id'];
                }
                if(!in_array($list_v['purchase_code'],$goods_purchase_code)){
                    $goods_purchase_code[] = $list_v['purchase_code'];
                }
            }
            $goods_purchase_info = Db::name('kuerp_split_info')->where(['purchase_code'=>['in',$goods_purchase_code]])->column('id');

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_split_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
                }
                Db::name('kuerp_goods_batch_info')->where(['split_id'=>['in',$goods_purchase_info]])->delete();
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
    //审核/反审核
    public function examine($ids = null,$examine=1){
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
                $this->error('请选择需要审核的数据');
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
            $info_list = Db::name('kuerp_split_info')->where(['purchase_code'=>['in',$purchase_code]])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                $goods = Db::name('kuerp_goods')->where(['id'=>$info_list_v['goods_id']])->find();
                $stock_number = $info_list_v['basic_number'];//基础数量
                if($info_list_v['type']==2){//子件
                    //商品表库存
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
                    //商品表库存结束

                    //商品库存表库存
                    $where = [];
                    $where['goods_code'] = $info_list_v['goods_code'];
                    $where['stores_id'] = $info_list_v['stores_id'];
                    if($info_list_v['goods_attr']>0){
                        $goods_attr = $info_list_v['goods_attr'];
                        if($goods_attr>0){
                            $where['attribute_id'] = $goods_attr;
                        }
                    }
                    $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                    if($stock){
                        $status = false;
                        foreach ($editstock as $editstock_k=>$editstock_v){
                            if($editstock_v['id']==$stock['id']){
                                $editstock[$editstock_k]['stock'] += $stock_number;
                                $editstock[$editstock_k]['cost_money'] = (round($editstock_v['cost_money']+$info_list_v['cost_money'],4));
                                $status = true;
                            }
                        }
                        if(!$status){
                            $editstock[] = [
                                'id'=>$stock['id'],
                                'stock'=>(round($stock['stock']+$stock_number,4)),
                                'cost_money'=>(round($stock['cost_money']+$info_list_v['cost_money'],4))
                            ];
                        }
                    }else{
                        $addstock[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'  => isset($goods['id'])?$goods['id']:'',
                            'goods_code'=> $info_list_v['goods_code'],
                            'attribute_id'  => isset($goods_attr)?$goods_attr:0,
                            'stores_id'  => $info_list_v['stores_id'],
                            'stock'  => $stock_number,
                            'cost_money'=>(round($info_list_v['cost_money'],4)),
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                    }
                    //商品库存表库存结束

                    //收发详情
                    $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code' => $info_list_v['purchase_code'],
                        'purchase_id'   => $info_list_v['id'],
                        'purchase_time' => $info_list_v['purchase_time'],
                        'business_type' => '14',
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
                        'out_number'  => null,
                        'out_basics'  => null,
                        'out_unit'    => null,
                        'out_cost'    => null,
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                    //收发详情结束

                    //批次跟踪
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
                        //商品批次跟踪
                        $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $goods['id'],
                            'goods_unit'    => $info_list_v['goods_unit'],
                            'stores_id'     => $info_list_v['stores_id'],
                            'batch_id'      => (float)$info_list_v['batch_id'],
                            'number'        => (float)$info_list_v['basic_number'],
                            'purchase_code' => $info_list_v['purchase_code'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'business_type' => 13,
                            'remarks'       => $info_list_v['remark'],
                            'order_id'      => $info_list_v['id'],
                            // 'unit_price'    => $info_list_v['unit_cost'],
                            // 'tax_included'  => $info_list_v['unit_cost'],
                            'all_money'     => $info_list_v['cost_money'],
                            'tax_money'     => 0,
                            'money_tax_price' => $info_list_v['cost_money'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                        //商品批次跟踪结束
                    }

                    //序列号跟踪
                    if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                        $period = unserialize($info_list_v['period']);
                        foreach ($period as $period_k=>$period_v){

                            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->field('serial,status,store_status')->find();//序列号
                            if($per_s){//仓库有记录
                                $edit_serial[] = ['serial'=>$per_s['serial'],'split_id'=>$info_list_v['id'],'type'=>13,'store_status'=>1,'old_status'=>$per_s['status'],'old_store'=>$per_s['store_status']];
                            }else{
                                if(!in_array($period_v['serial'],$per_code)){
                                    $per_code[] = $period_v['serial'];
                                }
                                $goods_serial[] = \addons\kuerp\library\Goods::setDataInfo([
                                    'split_id' => $info_list_v['id'],
                                    'goods_id'=>$goods['id'],
                                    'goods_code'=>$goods['goods_code'],
                                    'stores_id'=>$info_list_v['stores_id'],
                                    'attribute_id'=>isset($goods_attr)?$goods_attr:null,
                                    'serial'=>$period_v['serial'],
                                    'remark'=>$period_v['remark'],
                                    'status'=> '13',
                                    'store_status'=>'1',
                                    'split_status'=>'1',
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
                                'business_type'=>'13',
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
                    //序列号跟踪
                }else{
                    //组合件
                    //商品表库存
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
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                            }
                            $this->error($goods['goods_name'].'库存不足');
                        }
                    }
                    //商品表库存结束

                    //商品库存表库存
                    $where = [];
                    $where['goods_code'] = $info_list_v['goods_code'];
                    $where['stores_id'] = $info_list_v['stores_id'];
                    if($info_list_v['goods_attr']>0){
                        $goods_attr = $info_list_v['goods_attr'];
                        if($goods_attr>0){
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
                                'stock'=>(round($stock['stock']-$stock_number,4)),
                                'cost_money'=>(round($stock['cost_money']-$info_list_v['cost_money'],4))
                            ];
                        }
                        foreach ($editstock as $editstock_k=>$editstock_v){
                            if($editstock_v['stock']<0){
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                                }
                                $this->error($goods['goods_name'].'库存不足');
                            }
                        }
                    }else{
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                        }
                        $this->error($goods['goods_name'].'库存不足');
                    }
                    //商品库存表库存结束

                    //收发详情
                    $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code' => $info_list_v['purchase_code'],
                        'purchase_id'   => $info_list_v['id'],
                        'purchase_time' => $info_list_v['purchase_time'],
                        'business_type' => '15',
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
                        'out_number'  => (float)$info_list_v['number'],
                        'out_basics'  => (float)$stock_number,
                        // 'out_unit'    => round($info_list_v['unit_cost'],4),
                        // 'out_cost'    => (float)$info_list_v['cost_money'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                    //收发详情结束

                    //批次跟踪
                    if($batch_set==1 && $goods['batch']==1){
                        $where = [];
                        $where['goods_id'] = $goods['id'];
                        if($goods_attr>0){
                            $where['attribute_id'] = $goods_attr;
                        }
                        $where['stores_id'] = $info_list_v['stores_id'];
                        $where['batch_id'] = $info_list_v['batch_id'];
                        $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                        if(empty($goods_batch_stock)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>$goods['goods_name'].$info_list_v['batch'].'库存不足'];
                            }
                            $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                        }else{
                            if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                                $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                            }else{
                                $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                            }
                            foreach ($goods_batch_edit as $goods_batch_edit_k=>$goods_batch_edit_v){
                                if($goods_batch_edit_v<0){
                                    if($examine_set!=1){
                                        return ['status'=>0,'msg'=>$goods['goods_name'].$info_list_v['batch'].'库存不足'];
                                    }
                                    $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                                }
                            }
                        }
                        //商品批次跟踪
                        $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $goods['id'],
                            'goods_unit'    => $info_list_v['goods_unit'],
                            'stores_id'     => $info_list_v['stores_id'],
                            'batch_id'      => (float)$info_list_v['batch_id'],
                            'number'        => (float)$info_list_v['basic_number'],
                            'purchase_code' => $info_list_v['purchase_code'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'business_type' => '14',
                            'remarks'       => $info_list_v['remark'],
                            'order_id'      => $info_list_v['id'],
                            // 'unit_price'    => $info_list_v['unit_cost'],
                            // 'tax_included'  => $info_list_v['unit_cost'],
                            // 'all_money'     => $info_list_v['cost_money'],
                            'tax_money'     => '0',
                            'money_tax_price' => $info_list_v['cost_money'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                        //商品批次跟踪结束
                    }

                    //序列号跟踪
                    if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                        $period = unserialize($info_list_v['period']);
                        foreach ($period as $period_k=>$period_v){

                            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->field('serial,status,store_status')->find();//序列号
                            if($per_s){//仓库有记录
                                $edit_serial[] = ['serial'=>$per_s['serial'],'split_id'=>$info_list_v['id'],'type'=>14,'store_status'=>'2','old_status'=>$per_s['status'],'old_store'=>$per_s['store_status']];
                            }else{
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$goods['goods_name'].'序列号'.$period_v['serial'].'不存在'];
                                }
                                $this->error($goods['goods_name'].'序列号'.$period_v['serial'].'不存在');
                            }
                            $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                                'goods_id'=>$info_list_v['goods_id'],
                                'stores_id'=>$info_list_v['stores_id'],
                                'attribute_id'=>isset($goods_attr)?$goods_attr:0,
                                'serial'=>$period_v['serial'],
                                'business_type'=>'14',
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
                    //序列号跟踪
                }
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                //库存表修改库存
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                //库存表添加库存
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
                //商品列表库存
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                //商品收发明细
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                //批次库存新增
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
                //批次库存更新
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                //批次记录
                if(!empty($goods_batch_log)){
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
                }
                //序列号添加
                if(!empty($goods_serial)){
                    Db::name('kuerp_goods_serial')->insertAll($goods_serial);
                }
                //序列号更新
                if(!empty($edit_serial)){
                    foreach ($edit_serial as $edit_serial_k=>$edit_serial_v){
                        Db::name('kuerp_goods_serial')->where(['serial'=>$edit_serial_v['serial']])->update([
                            'split_id'=>$edit_serial_v['split_id'],
                            'status'=>$edit_serial_v['type'],
                            'store_status'=>$edit_serial_v['store_status'],
                            'old_status'=>$edit_serial_v['old_status'],
                            'old_store'=>$edit_serial_v['old_store']
                        ]);
                    }
                }
                //序列号跟踪
                if(!empty($serial_log)){
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
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
            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要反审核的数据');
            }

            $editstock = [];//库存表修改库存
            $goods_stock = [];//商品列表库存
            $goods_batch_edit = [];//批次库存更新
            $info_list = Db::name('kuerp_split_info')->where(['purchase_code'=>['in',$purchase_code]])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                $goods = Db::name('kuerp_goods')->where(['id'=>$info_list_v['goods_id']])->find();
                $stock_number = $info_list_v['basic_number'];//基础数量
                if($info_list_v['type']==2){
                    //字件
                    //商品表库存
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
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                            }
                            $this->error($goods['goods_name'].'库存不足');
                        }
                    }
                    //商品表库存结束

                    //商品库存表库存
                    $where = [];
                    $where['goods_code'] = $info_list_v['goods_code'];
                    $where['stores_id'] = $info_list_v['stores_id'];
                    if($info_list_v['goods_attr']>0){
                        $goods_attr = $info_list_v['goods_attr'];
                        if($goods_attr>0){
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
                                'stock'=>(round($stock['stock']-$stock_number,4)),
                                'cost_money'=>(round($stock['cost_money']-$info_list_v['cost_money'],4))
                            ];
                        }
                        foreach ($editstock as $editstock_k=>$editstock_v){
                            if($editstock_v['stock']<0){
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                                }
                                $this->error($goods['goods_name'].'库存不足');
                            }
                        }
                    }else{
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足'];
                        }
                        $this->error($goods['goods_name'].'库存不足');
                    }
                    //商品库存表库存结束

                    //批次跟踪
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
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>$goods['goods_name'].$info_list_v['batch'].'库存不足'];
                            }
                            $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                        }else{
                            if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                                $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                            }else{
                                $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                            }
                            foreach ($goods_batch_edit as $goods_batch_edit_k=>$goods_batch_edit_v){
                                if($goods_batch_edit_v<0){
                                    if($examine_set!=1){
                                        return ['status'=>0,'msg'=>$goods['goods_name'].$info_list_v['batch'].'库存不足'];
                                    }
                                    $this->error($goods['goods_name'].$info_list_v['batch'].'库存不足');
                                }
                            }
                        }
                    }

                    //序列号跟踪
                    if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                        $period = unserialize($info_list_v['period']);
                        foreach ($period as $period_k=>$period_v){
                            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->find();//序列号
                            if($per_s){//仓库有记录
                                $edit_serial[] = ['serial'=>$per_s['serial'],'split_id'=>$info_list_v['id'],'type'=>$per_s['old_status'],'store_status'=>$per_s['old_store']];
                            }else{
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$goods['goods_name'].'序列号'.$period_v['serial'].'不存在'];
                                }
                                $this->error($goods['goods_name'].'序列号'.$period_v['serial'].'不存在');
                            }
                        }
                    }
                    //序列号跟踪
                }else{
                    //组合件
                    //商品表库存
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
                    //商品表库存结束

                    //商品库存表库存
                    $where = [];
                    $where['goods_code'] = $info_list_v['goods_code'];
                    $where['stores_id'] = $info_list_v['stores_id'];
                    if($info_list_v['goods_attr']>0){
                        $goods_attr = $info_list_v['goods_attr'];
                        if($goods_attr>0){
                            $where['attribute_id'] = $goods_attr;
                        }
                    }
                    $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id,cost_money')->find();
                    if($stock){
                        $status = false;
                        foreach ($editstock as $editstock_k=>$editstock_v){
                            if($editstock_v['id']==$stock['id']){
                                $editstock[$editstock_k]['stock'] += $stock_number;
                                $editstock[$editstock_k]['cost_money'] = (round($editstock_v['cost_money']+$info_list_v['cost_money'],4));
                                $status = true;
                            }
                        }
                        if(!$status){
                            $editstock[] = [
                                'id'=>$stock['id'],
                                'stock'=>(round($stock['stock']+$stock_number,4)),
                                'cost_money'=>(round($stock['cost_money']+$info_list_v['cost_money'],4))
                            ];
                        }
                    }
                    //商品库存表库存结束

                    //批次跟踪
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

                    //序列号跟踪
                    if($goods && $goods['serial']==1 && $serial_set==1 && !empty($info_list_v['period'])){
                        $period = unserialize($info_list_v['period']);
                        foreach ($period as $period_k=>$period_v){
                            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->find();//序列号
                            if($per_s){//仓库有记录
                                $edit_serial[] = ['serial'=>$per_s['serial'],'split_id'=>$info_list_v['id'],'type'=>$per_s['old_status'],'store_status'=>$per_s['old_store']];
                            }else{
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$goods['goods_name'].'序列号'.$period_v['serial'].'不存在'];
                                }
                                $this->error($goods['goods_name'].'序列号'.$period_v['serial'].'不存在');
                            }
                        }
                    }
                    //序列号跟踪
                }
            }

            $info_id = Db::name('kuerp_split_info')->where(['purchase_code'=>['in',$purchase_code]])->column('id');
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

                Db::name('kuerp_goods_log')->where(['business_type'=>['in',[15,14]],'purchase_id'=>['in',$info_id]])->delete();

                Db::name('kuerp_goods_batch_log')->where(['business_type'=>['in',[13,14]],'order_id'=>['in',$info_id]])->delete();

                Db::name('kuerp_goods_serial')->where(['split_id'=>['in',$info_id],'split_status'=>'1'])->delete();
                $serial = Db::name('kuerp_goods_serial')->where(['split_id'=>['in',$info_id],'split_status'=>'2'])->select();
                foreach ($serial as $serial_k=>$serial_v){
                    Db::name('kuerp_goods_serial')->where(['id'=>$serial_v['id']])->update(['status'=>$serial_v['old_status'],'store_status'=>$serial_v['old_store']]);
                }
                Db::name('kuerp_goods_serial_log')->where(['business_type'=>['in',[13,14]],'order_id'=>['in',$info_id]])->delete();

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>0,'examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
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
                $team = Db::name('kuerp_split_temp')->where(['temp_name'=>$team_name])->find();
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
                        $result = Db::name('kuerp_split_temp')->insertGetId($row);
                    }
                    foreach ($data as $data_k=>$data_v){
                        $data_v['purchase_id'] = $result;
                        Db::name('kuerp_split_info_temp')->insertGetId($data_v);
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
