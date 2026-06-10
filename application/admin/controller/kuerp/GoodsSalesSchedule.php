<?php

namespace app\admin\controller\kuerp;

use app\admin\model\Admin;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 销货排期
 *
 * @icon fa fa-calendar
 */
class GoodsSalesSchedule extends Backend
{
    protected $noNeedRight = ['getunit','formatinfo','getbranch','gettime','printpage','getschedulegoods'];
    /**
     * GoodsSalesSchedule模型对象
     * @var \app\admin\model\kuerp\GoodsSalesSchedule
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesSchedule;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("customersList", $this->model->getCustomersList());
        $this->assignconfig("customersList", $this->model->getCustomersList());
        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("saleList", $this->model->getSaleList());
    }

    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'purchase_time';$order='DESC';
            $list = $this->model
                ->with(['customers','preparation','examine','staffmember'])
                ->where($where)
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                $row->getRelation('preparation')->visible(['username']);
                $row->getRelation('examine')->visible(['username']);
                if ($row->getRelation('staffmember')) {
                    $row->getRelation('staffmember')->visible(['name']);
                }
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加销货排期（仅录入客户、商品和数量，不含金额字段）
     */
    public function add($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
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
                $number = 0;$goods_serial_all = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){
                        $number += (float)$params['number'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
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
                        }

                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
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
                            $status = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code]])->column('serial');
                            if($status){
                                $this->error('商品序列号'.$status[0].'已占用');
                            }
                        }

                        // 销货排期明细（只含业务字段，不含金额）
                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],
                            'sale_user'         =>  (int)$params['sale_user'],

                            'goods_code'        =>  $v,
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],

                            'stores_id'         =>  $params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],

                            'batch_id'          => $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'batch_name'        => $params['batch'][$k]===''?null:$params['batch'][$k],
                            'manufacture'       => strtotime($params['manufacture'][$k]),
                            'guarantee'         => $params['guarantee'][$k],
                            'validity'          => strtotime($params['validity'][$k]),

                            'createtime'        => time(),
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                        ];
                    }
                }
                if(!empty($data)){
                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  '1',
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'customers_id'      =>  (int)$params['customers_id'],
                        'sale_user'         =>  (int)$params['sale_user'],
                        'receiving_address' =>  $params['receiving_address'],
                        'number'            =>  $number,
                        'note'              =>  $params['note'],
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
                        $result = $this->model->insertGetId($row);
                    }
                    foreach ($data as $data_k=>$data_v){
                        Db::name('kuerp_goods_sales_schedule_info')->insertGetId($data_v);
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
        $code = \addons\kuerp\library\Goods::getCodes('',8);
        $this->view->assign("code", $code);

        // 如果来自销货排期，预填商品信息
        $schedule_id = $this->request->param('schedule_id');
        $schedule_goods = [];
        if ($schedule_id) {
            $schedule_goods = $this->getScheduleGoodsData($schedule_id);
        }

        $this->view->assign("row", []);
        $this->view->assign("schedule_goods", $schedule_goods);
        return $this->view->fetch();
    }

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
                $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);
                $info_id = Db::name('kuerp_goods_sales_schedule_info')->where(['purchase_code'=>$old_purchase_code])->column('id');

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$goods_serial_all=[];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){
                        $number+=$params['number'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
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
                        }

                        if(isset($params['period_period'][$k]) && !empty($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
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
                            $status = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code],'purchase_id'=>['not in',$info_id]])->find();
                            if($status){
                                $this->error('商品序列号已占用');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],
                            'sale_user'         =>  (int)$params['sale_user'],

                            'goods_code'        =>  $v,
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],

                            'batch_id'          => $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'batch_name'        => $params['batch'][$k]===''?null:$params['batch'][$k],
                            'manufacture'       => strtotime($params['manufacture'][$k]),
                            'guarantee'         => $params['guarantee'][$k],
                            'validity'          => strtotime($params['validity'][$k]),

                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                        ];
                    }
                }
                if(!empty($data)){
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  '1',
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'customers_id'      =>  (int)$params['customers_id'],
                        'sale_user'         =>  (int)$params['sale_user'],
                        'receiving_address' =>  $params['receiving_address'],
                        'number'            =>  $number,
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    Db::name('kuerp_goods_sales_schedule_info')->where(['id'=>['in',$info_id]])->delete();
                    foreach ($data as $data_k=>$data_v){
                        Db::name('kuerp_goods_sales_schedule_info')->insertGetId($data_v);
                    }
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

        $purchase_info = Db::name('kuerp_goods_sales_schedule_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['unit'] = json_decode($this->getUnit($purchase_info_v['goods_code']),true);
            $purchase_info[$purchase_info_k]['serial'] = $purchase_info_v['period'];
        }
        $this->view->assign("purchase_info", $purchase_info);

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
                    Db::name('kuerp_goods_sales_schedule_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
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

    //获取单位价格信息
    public function getUnit($goods_code='',$store='',$attr=''){
        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:0;
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])->field('batch,serial,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse')->find();
        }
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
                    $aux_combination[$aux_combination_k] = isset($auxiliary[$aux_combination_v])?$auxiliary[$aux_combination_v]:'';
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
            $where['attribute_id'] = $attr;
        }
        $stock = Db::name('kuerp_goods_stock')->where($where)->value('stock');
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
            $goods_price = Db::name('kuerp_goods_price')->where(['unit'=>['in',$unit_ids],'goods_id'=>$goods_id])->select();
            foreach ($goods_price as $goods_price_k=>$goods_price_v){
                foreach ($unit as $unit_k=>$unit_v){
                    if($goods_price_v['unit']==$row['unit_id']){
                        $goods_price[$goods_price_k]['advance_rate'] = 1;
                    }else if($goods_price_v['unit']==$unit_v['id']){
                        $goods_price[$goods_price_k]['advance_rate'] = $unit_v['advance_rate'];
                    }
                }
            }
        }else{
            $unit_ids = [$row['unit_id']];
        }
        $unit = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($goods_price)){
                foreach ($goods_price as $goods_price_k=>$goods_price_v){
                    if($unit_v['id']==$goods_price_v['unit']){
                        $unit[$unit_k]['is_default'] = $goods_price_v['is_default'];
                        $unit[$unit_k]['purchase'] = $goods_price_v['purchase'];
                        if($goods_price_v['is_default']!=1){
                            $unit[$unit_k]['basic_nmber'] = $goods_price_v['advance_rate'];
                        }else{
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = 0;
                }
            }
        }
        foreach ($unit as $unit_k=>$unit_v){
            $unit[$unit_k]['tax'] = 0;
            $unit[$unit_k]['tax_rate'] = 0;
            $unit[$unit_k]['purchase'] = 0;
            $unit[$unit_k]['tax_included'] = 0;
        }
        $data = [];
        foreach ($unit_ids as $unit_ids_k=>$unit_ids_v){
            foreach ($unit as $unit_k=>$unit_v){
                if($unit_ids_v==$unit_v['id']){
                    if($unit_v['is_default']==1){
                        $unit_v['stock'] = is_null($stock)?0:$stock;
                    }else{
                        $unit_v['stock'] = round($stock/$unit_v['basic_nmber'],2);
                    }
                    $unit_v['tax_money'] = 0;
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode(['unit'=>$data,'attr'=>$goods_attr,'goods'=>$row]);
    }

    public function formatInfo(){
        $data = input();
        $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        $number_decimal = $setList['number_decimal']['value'];

        $format = [
            'number'=>$number_decimal,
            'allow_stock'=>$number_decimal,
            'basic_nmber'=>$number_decimal,
        ];
        foreach ($data as $k=>$v){
            if(array_key_exists($k,$format)){
                $data[$k] = (float)$data[$k];
            }
        }

        $data['basic_nmber'] = $data['number']*$data['basic_nmber'];

        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
            }
        }

        $arr = [];
        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
                if($format[$data_k]>0){
                    $price = explode('.',$data[$data_k]);
                    if(isset($price[1])){
                        $cha = $format[$data_k]-strlen($price[1]);
                    }else{
                        $cha = $format[$data_k];
                        $data[$data_k] = $data[$data_k].'.';
                    }
                    $str = '';
                    for ($a=0;$a<$cha;$a++){
                        $str .= '0';
                    }
                    $data[$data_k] .= $str;
                }
            }
            $arr[] = ['key'=>$data_k,'value'=>$data[$data_k]];
        }
        return json_encode(['data'=>$arr]);
    }

    //获取批次
    public function getBranch($goods_code=''){
        $data = input();
        $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('batch,shelf_life')->find();

        $batch_id = Db::name('kuerp_goods_batch_info')->where(['goods_code'=>$data['goods_code']])->column('batch_id');
        $batch_ids = Db::name('kuerp_goods_batch_stock')->where(['goods_code'=>$data['goods_code']])->column('batch_id');
        foreach ($batch_id as $batch_id_k=>$batch_id_v){
            if(in_array($batch_id_v,$batch_ids)){
                unset($batch_id[$batch_id_k]);
            }
        }
        if(!empty($batch_id)){
            Db::name('kuerp_goods_batch')->where(['goods_code'=>$data['goods_code'],'id'=>['not in',$batch_id]])->delete();
        }

        $where = [];
        $batch_id = Db::name('kuerp_goods_batch_stock')->where(['goods_code'=>$data['goods_code']])->where(['stock'=>['<>',0]])->group('batch_id')->column('batch_id');
        $where['id'] = ['in',$batch_id];
        $where['goods_code'] = $data['goods_code'];

        $goods_batch = Db::name('kuerp_goods_batch')->where($where)->field('id,batch_name,manufacture,guarantee,validity')->select();
        foreach ($goods_batch as $goods_batch_k=>$goods_batch_v){
            $goods_batch[$goods_batch_k]['manufacture'] = date('Y-m-d',$goods_batch_v['manufacture']);
            $goods_batch[$goods_batch_k]['validity'] = date('Y-m-d',$goods_batch_v['validity']);
        }
        return json_encode(['goods'=>$row,'batch'=>$goods_batch]);
    }

    public function getTime($manufacture='',$shelf_life='',$validity=''){
        $shelf_life = (float)$shelf_life;
        if(!empty($manufacture) && !empty($shelf_life)){
            $validity = date('Y-m-d',strtotime($manufacture)+$shelf_life*86400);
        }elseif(!empty($validity) && !empty($shelf_life)){
            $manufacture = date('Y-m-d',strtotime($validity)-$shelf_life*86400);
        }elseif(!empty($validity) && !empty($manufacture)){
            $shelf_life = ceil((strtotime($validity)-strtotime($manufacture))/86400);
        }
        return json_encode(['manufacture'=>$manufacture,'shelf_life'=>$shelf_life,'validity'=>$validity]);
    }

    /**
     * 获取排期商品数据（供其他页面调用预填）
     */
    public function getScheduleGoods($schedule_id = null)
    {
        if (empty($schedule_id)) {
            $schedule_id = $this->request->param('schedule_id');
        }
        if (empty($schedule_id)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $row = $this->model->where(['id' => $schedule_id])->find();
        if (!$row) {
            return json(['code' => 0, 'msg' => '排期不存在']);
        }

        $info_list = Db::name('kuerp_goods_sales_schedule_info')
            ->where(['purchase_code' => $row['purchase_code']])
            ->select();

        $goods_list = [];
        foreach ($info_list as $v) {
            $goods = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->field('goods_name,note')->find();
            $goods_list[] = [
                'goods_code'   => $v['goods_code'],
                'goods_name'   => $goods ? $goods['goods_name'] : '',
                'goods_note'   => $goods ? $goods['note'] : '',
                'goods_unit'   => $v['goods_unit'],
                'goods_attr'   => $v['goods_attr'],
                'stores_id'    => $v['stores_id'],
                'number'       => $v['number'],
                'default_unit' => $v['default_unit'],
                'basic_nmber'  => $v['basic_nmber'],
                'remark'       => $v['remark'],
            ];
        }

        return json([
            'code' => 1,
            'msg' => [
                'schedule' => [
                    'customers_id'      => $row['customers_id'],
                    'sale_user'         => $row['sale_user'],
                    'receiving_address' => $row['receiving_address'],
                    'note'              => $row['note'],
                ],
                'goods' => $goods_list,
            ]
        ]);
    }

    /**
     * 内部获取排期商品数据
     */
    private function getScheduleGoodsData($schedule_id)
    {
        $row = $this->model->where(['id' => $schedule_id])->find();
        if (!$row) return [];

        $info_list = Db::name('kuerp_goods_sales_schedule_info')
            ->where(['purchase_code' => $row['purchase_code']])
            ->select();

        $goods_list = [];
        foreach ($info_list as $v) {
            $goods = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->field('goods_name,note')->find();
            $goods_list[] = [
                'goods_code'   => $v['goods_code'],
                'goods_name'   => $goods ? $goods['goods_name'] : '',
                'goods_note'   => $goods ? $goods['note'] : '',
                'goods_unit'   => $v['goods_unit'],
                'goods_attr'   => $v['goods_attr'],
                'stores_id'    => $v['stores_id'],
                'number'       => $v['number'],
                'default_unit' => $v['default_unit'],
                'basic_nmber'  => $v['basic_nmber'],
                'remark'       => $v['remark'],
            ];
        }
        return $goods_list;
    }

    // 打印销货排期详情
    public function printpage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $customer_name = Db::name('kuerp_customers')->where(['id' => $row['customers_id']])->value('customer_name');
        $row['customer_name'] = $customer_name ? $customer_name : '';
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('username');
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('username');

        $purchase_info = Db::name('kuerp_goods_sales_schedule_info')->where(['purchase_code' => $row['purchase_code']])->select();
        foreach ($purchase_info as $k => $v) {
            $purchase_info[$k]['goods_name'] = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->value('goods_name');
            $purchase_info[$k]['goods_note'] = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->value('note');
            $purchase_info[$k]['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $purchase_info[$k]['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
        }
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);
        $this->model->where(['id' => $ids])->setInc('print_num');
        return $this->view->fetch();
    }
}
