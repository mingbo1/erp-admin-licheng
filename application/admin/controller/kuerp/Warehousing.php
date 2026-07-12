<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 其他入库单
 *
 * @icon fa fa-circle-o
 */
class Warehousing extends Backend
{
    protected $noNeedRight = ['formatinfo','checkinventory','printpage'];
    /**
     * Warehousing模型对象
     * @var \app\admin\model\kuerp\Warehousing
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Warehousing;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("suppliersList", $this->model->getSuppliersList());

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
                    ->with(['admin','examine','suppliers'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('suppliers')->visible(['supplyer_name']);
                $row->getRelation('admin')->visible(['username']);
                $row->getRelation('examine')->visible(['username']);
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

    public function add($purchase_code = null,$type=null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                $code = $params['purchase_code'];
                if($this->model->where(['purchase_code'=>trim($params['purchase_code'])])->find()){
                    $this->error('单据编号已占用');
                }
                $purchase_time = strtotime($params['purchase_time']);
                $business_type = $params['business_type'];

                if(empty($code)){
                    $this->error('请输入单据编号');
                }else if(empty($purchase_time)){
                    $this->error('请输入单据时间');
                }else if(empty($business_type)){
                    $this->error('请选择业务类型');
                }

                $supplyer = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$params['supplyer_code']])->find();
                $admin = Session::get('admin');

                $serial= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');

                $data = [];
                $all_number = 0;//总数量
                $all_price = 0;//总金额
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }
                        if(!isset($params['number'][$k]) || empty($params['number'][$k])){
                            $this->error('请输入入库数量');
                        }
                        $all_number += $params['number'][$k];
                        $all_price += $params['all_money'][$k];

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        $goods_unit = $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k];
                        if(empty($goods_unit)){
                            $this->error('请选择单位');
                        }

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

                        if($goods['batch']==1 && $batch==1) {
                            if (empty($params['batch'][$k])) {
                                $this->error('请选择生产批次');
                            }
                            if (empty($params['manufacture'][$k])) {
                                $this->error('请输入生产日期');
                            }
                            if($params['guarantee'][$k]===''){
                                $this->error('请输入保质期');
                            }
                            if ($params['guarantee'][$k]<0){
                                $this->error('保质期天数不可小于0');
                            }
                            if (empty($params['validity'][$k])) {
                                $this->error('请输入过期时间');
                            }
                        }
                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $basic_nmber = (float)$params['number'][$k];

                            $per_code = [];
                            $period = unserialize($params['period_period'][$k]);
                            foreach ($period as $period_k=>$period_v){
                                if(!in_array($period_v['serial'],$per_code)){
                                    $per_code[] = $period_v['serial'];
                                }
                            }
                            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$per_code],'store_status'=>'1'])->column('serial');
                            if($per_s){
                                $per_str = implode(',',$per_s);
                                $this->error('序列号'.$per_str.'已使用，不可重复入库');
                            }
                        }else{
                            $basic_nmber = round(($advance_rate*$params['number'][$k]),4);
                        }

                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $code,
                            'purchase_time'     =>  $purchase_time,

                            'goods_id'          =>  $goods['id'],
                            'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                            'goods_unit'        =>  $goods_unit,
                            'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                            'stock'             =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                            'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                            'basic_nmber'       =>  $basic_nmber,
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                            'unit_price'        =>  $params['unit_price'][$k]===''?null:(float)$params['unit_price'][$k],
                            'price'             =>  $params['all_money'][$k]===''?null:(float)$params['all_money'][$k],

                            'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],
                            'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                            'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                            'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                            'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                    }
                }
                if(!empty($data)){
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     => $code,
                        'purchase_time'     => $purchase_time,
                        'business_type'     => $business_type,
                        'number'            => $all_number,
                        'price'             => $all_price,
                        'suppliers_id'      => isset($supplyer['id'])?$supplyer['id']:null,
                        'preparation_id'    => $admin['id'],
                        'remarks'           => $params['note'],
                        'order_id'          => isset($params['order_id'])?$params['order_id']:null,
                        'order_type'        => isset($params['order_type'])?(empty($params['order_type'])?null:$params['order_type']):null,
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
                        Db::name('kuerp_warehousing_info')->insertAll($data);
                    }
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
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
        $code = \addons\kuerp\library\Goods::getCodes('',17);
        $this->view->assign("code", $code);

        $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        //数量价格格式化
        $price_decimal = $setList['price_decimal']['value'];

        switch ($type){
            case 1:
                //盘点盘盈
                if(!empty($purchase_code)){
                    $row = Db::name('kuerp_inventory')->where(['purchase_code'=>$purchase_code])->find();
                    $info = Db::name('kuerp_inventory_info')->where(['purchase_code'=>$purchase_code,'profit'=>['>',0]])->select();
                    foreach ($info as $info_k=>$info_v){
                        $goods = Db::name('kuerp_goods')->where(['id'=>$info_v['goods_id']])->find();
                        $info[$info_k]['goods'] = $goods;

                        //获取成本单价
                        $balance_unit=0;
                        $goodsInfo = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($goods['goods_code'],$info_v['stores_id'],$info_v['goods_attr']),true);
                        $unitData = isset($goodsInfo['unit'])?$goodsInfo['unit']:[];
                        if(empty($unitData)){
                            $this->error('商品信息获取错误');
                        }else{
                            foreach ($unitData as $unitData_k=>$unitData_v){
                                if($unitData_v['id']==$goods['unit_id']){
                                    $balance_unit = $unitData_v['purchase'];
                                }
                            }
                        }
                        $balance_cost = $balance_unit*$info_v['profit'];
                        $info[$info_k]['unit_price'] = round($balance_unit,$price_decimal);
                        $info[$info_k]['price'] = round($balance_cost,$price_decimal);
                        $info[$info_k]['unit'] = $goodsInfo;
                    }
                    $row['order_type'] = 1;
                    $this->view->assign("business_type", 1);
                    $this->view->assign("row", $row);
                    $this->view->assign("purchase_info", $info);
                }
                break;
            case 2:
                //序列号盘盈
                if(!empty($purchase_code)){
                    $row = Db::name('kuerp_inventory_serial')->where(['purchase_code'=>$purchase_code])->find();
                    $info = Db::name('kuerp_inventory_serial_info')->where(['purchase_code'=>$purchase_code,'inventory'=>['>',0]])->select();
                    foreach ($info as $info_k=>$info_v){
                        $goods = Db::name('kuerp_goods')->where(['id'=>$info_v['goods_id']])->find();
                        $info[$info_k]['goods'] = $goods;

                        //获取成本单价
                        $balance_unit=0;
                        $goodsInfo = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($goods['goods_code'],$info_v['stores_id'],$info_v['goods_attr']),true);
                        $unitData = isset($goodsInfo['unit'])?$goodsInfo['unit']:[];
                        if(empty($unitData)){
                            $this->error('商品信息获取错误');
                        }else{
                            foreach ($unitData as $unitData_k=>$unitData_v){
                                if($unitData_v['id']==$goods['unit_id']){
                                    $balance_unit = $unitData_v['purchase'];
                                }
                            }
                        }

                        $goods_code    = isset($goods['goods_code'])?$goods['goods_code']:'';
                        $stores_id     = isset($info_v['stores_id'])?$info_v['stores_id']:'';
                        $attribute_id  = isset($info_v['goods_attr'])?$info_v['goods_attr']:'';
                        $where = [];
                        $where['store_status'] = '1';
                        $where['goods_code'] = $goods_code;
                        $where['stores_id'] = $stores_id;
                        if($attribute_id){
                            $where['attribute_id'] = $attribute_id;
                        }
                        $serial = Db::name('kuerp_goods_serial')->where($where)->column('serial');

                        $serial_arr = [];$inventory = 0;
                        if(isset($info_v['period_period'])){
                            $serial_pds = unserialize($info_v['period_period']);
                            foreach ($serial_pds as $serial_pds_k=>$serial_pds_v){
                                if(!in_array($serial_pds_v['serial'],$serial)){
                                    $inventory+=1;
                                    $serial_arr[] = ['serial'=>$serial_pds_v['serial'],'remark'=>''];
                                }
                            }
                        }

                        $info[$info_k]['profit'] = $inventory;
                        $balance_cost = $balance_unit*$inventory;
                        $info[$info_k]['unit_price'] = round($balance_unit,$price_decimal);
                        $info[$info_k]['price'] = round($balance_cost,$price_decimal);
                        $info[$info_k]['unit'] = $goodsInfo;
                        $info[$info_k]['period'] = serialize($serial_arr);
                    }
                    $row['order_type'] = 2;
                    $this->view->assign("business_type", 1);
                    $this->view->assign("row", $row);
                    $this->view->assign("purchase_info", $info);
                }
                break;
            default:
                $this->view->assign("row", []);
                $this->view->assign("purchase_info", []);
                break;
        }
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
                $code = $params['purchase_code'];
                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }
                $purchase_time = strtotime($params['purchase_time']);
                $business_type = $params['business_type'];
                $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);

                if(empty($code)){
                    $this->error('请输入单据编号');
                }else if(empty($purchase_time)){
                    $this->error('请输入单据时间');
                }else if(empty($business_type)){
                    $this->error('请选择业务类型');
                }

                $supplyer = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$params['supplyer_code']])->find();
                $admin = Session::get('admin');

                $serial= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');

                $data = [];
                $all_number = 0;//总数量
                $all_price = 0;//总金额
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }
                        if(!isset($params['number'][$k]) || empty($params['number'][$k])){
                            $this->error('请输入入库数量');
                        }
                        $all_number += $params['number'][$k];
                        $all_price += $params['all_money'][$k];

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        $goods_unit = $params['goods_unit'][$k]===''?null:(float)$params['goods_unit'][$k];
                        if(empty($goods_unit)){
                            $this->error('请选择单位');
                        }

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

                        if($goods['batch']==1 && $batch==1) {
                            if (empty($params['batch'][$k])) {
                                $this->error('请选择生产批次');
                            }
                            if (empty($params['manufacture'][$k])) {
                                $this->error('请输入生产日期');
                            }
                            if($params['guarantee'][$k]===''){
                                $this->error('请输入保质期');
                            }
                            if ($params['guarantee'][$k]<0){
                                $this->error('保质期天数不可小于0');
                            }
                            if (empty($params['validity'][$k])) {
                                $this->error('请输入过期时间');
                            }
                        }
                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $serial==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $basic_nmber = (float)$params['number'][$k];

                            $per_code = [];
                            $period = unserialize($params['period_period'][$k]);
                            foreach ($period as $period_k=>$period_v){
                                if(!in_array($period_v['serial'],$per_code)){
                                    $per_code[] = $period_v['serial'];
                                }
                            }
                            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$per_code],'store_status'=>'1'])->column('serial');
                            if($per_s){
                                $per_str = implode(',',$per_s);
                                $this->error('序列号'.$per_str.'已在库，不可重复入库');
                            }
                        }else{
                            $basic_nmber = round(($advance_rate*$params['number'][$k]),4);
                        }

                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $code,
                            'purchase_time'     =>  $purchase_time,

                            'goods_id'          =>  $goods['id'],
                            'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                            'goods_unit'        =>  $goods_unit,
                            'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                            'stock'             =>  $params['allow_stock'][$k]===''?null:(float)$params['allow_stock'][$k],
                            'number'            =>  $params['number'][$k]===''?null:(float)$params['number'][$k],
                            'basic_nmber'       =>  $basic_nmber,
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                            'unit_price'        =>  $params['unit_price'][$k]===''?null:(float)$params['unit_price'][$k],
                            'price'             =>  $params['all_money'][$k]===''?null:(float)$params['all_money'][$k],

                            'remark'            =>  $params['remark'][$k]===''?null:$params['remark'][$k],
                            'batch'             =>  $params['batch'][$k]===''?null:$params['batch'][$k],
                            'batch_id'          =>  $params['batch_id'][$k]===''?null:(float)$params['batch_id'][$k],
                            'manufacture'       =>  $params['manufacture'][$k]===''?null:strtotime($params['manufacture'][$k]),
                            'guarantee'         =>  $params['guarantee'][$k]===''?null:(float)$params['guarantee'][$k],
                            'validity'          =>  $params['validity'][$k]===''?null:strtotime($params['validity'][$k]),

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                    }
                }
                if(!empty($data)){
                    $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     => $code,
                        'purchase_time'     => $purchase_time,
                        'business_type'     => $business_type,
                        'number'            => $all_number,
                        'price'             => $all_price,
                        'suppliers_id'      => isset($supplyer['id'])?$supplyer['id']:null,
                        'preparation_id'    => $admin['id'],
                        'remarks'           => $params['note'],
                        'updatetime'        => time(),
                    ]);
                }

                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }
                    Db::name('kuerp_warehousing_info')->where(['purchase_code'=>$old_purchase_code])->delete();
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    if(!empty($data)){
                        Db::name('kuerp_warehousing_info')->insertAll($data);
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

        $info = Db::name('kuerp_warehousing_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        // 批量查询动态库存（info表已有goods_id，直接用）
        $stockItems = [];
        foreach ($info as $v) {
            $gid = (int)$v['goods_id'];
            if ($gid) {
                $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
            }
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        foreach ($info as $info_k=>$info_v){
            $goods = Db::name('kuerp_goods')->where(['id'=>$info_v['goods_id']])->find();
            $info[$info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($goods['goods_code'], $info_v['stores_id'], $info_v['goods_attr']),true);
            $gid = (int)$info_v['goods_id'];
            $key = $gid . '-' . (int)$info_v['stores_id'] . '-' . (int)$info_v['goods_attr'];
            $info[$info_k]['stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
        }
        $this->view->assign("purchase_info", $info);

        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['id'=>$row['suppliers_id']])->field('supplyer_code,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);
        
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
                }elseif ($list_v['examine_status']==1 && $examine!=1){
                    try {
                        $this->examine($list_v['id'],2);
                    } catch (Exception $e) {
                    }
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_warehousing_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
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

    //审核/反审核
    public function examine($ids = null,$examine=1){
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            if($examine_set!=1){
                return ['status'=>0,'msg'=>'系统错误'];
            }
            $this->error('请选择要操作的数据');
        }
        $serial= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
        $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
        if($examine==1){
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            $editstock = [];$addstock = [];$goods_stock = [];
            $purchase_info = [];
            $goods_stock_log = [];//收发详情
            $goods_serial = [];
            $serial_log = [];//序列号跟踪
            $goods_batch_add = [];$goods_batch_edit = [];$goods_batch_log = [];
            $goods_batch_info = [];
            $per_code = [];
            $edit_batch = [];
            $edit_serial = [];
            foreach ($list as $list_k=>$list_v){
                $info_list = Db::name('kuerp_warehousing_info')->where(['purchase_code'=>$list_v['purchase_code']])->select();

                foreach ($info_list as $info_list_k=>$info_list_v){
                    $stock_number = $info_list_v['basic_nmber'];
                    $goods = Db::name('kuerp_goods')->where(['id'=>$info_list_v['goods_id']])->field('id,goods_code,attribute_id,inventory,batch,serial')->find();

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
                    //商品库存
                    $where = [];
                    $where['goods_code'] = $goods['goods_code'];
                    $where['stores_id'] = $info_list_v['stores_id'];
                    $goods_attr = 0;
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
                                $editstock[$editstock_k]['cost_money'] = (round($editstock_v['cost_money']+$info_list_v['price'],4));
                                $status = true;
                            }
                        }
                        if(!$status){
                            $editstock[] = [
                                'id'=>$stock['id'],
                                'stock'=>(round($stock['stock']+$stock_number,4)),
                                'cost_money'=>(round($stock['cost_money']+$info_list_v['price'],4))
                            ];
                        }
                    }else{
                        $addstock[] = [
                            'goods_id'  => isset($goods['id'])?$goods['id']:'',
                            'goods_code'=> $goods['goods_code'],
                            'attribute_id'  => isset($goods_attr)?$goods_attr:0,
                            'stores_id'  => $info_list_v['stores_id'],
                            'stock'  => $stock_number,
                            'cost_money'=>(round($info_list_v['price'],4)),
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ];
                    }
                    $purchase_info[] = $info_list_v['id'];
                    $supplyer = Db::name('kuerp_suppliers')->where(['id'=>$list_v['suppliers_id']])->value('id');

                    //收发详情
                    if($goods){
                        $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code' => $info_list_v['purchase_code'],
                            'purchase_id'   => $info_list_v['id'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'business_type' => $list_v['business_type']==1?8:9,
                            'customer_id'   => (float)$supplyer,
                            'goods_id'      => $goods['id'],
                            'goods_code'    => $goods['goods_code'],
                            'attribute_id'  => (float)$info_list_v['goods_attr'],
                            'goods_unit'    => (float)$info_list_v['goods_unit'],
                            'stores_id'     => (float)$info_list_v['stores_id'],
                            'remark'        => $info_list_v['remark'],
                            'enter_number'  => (float)$info_list_v['number'],
                            'enter_basics'  => (float)$info_list_v['basic_nmber'],
                            'enter_unit'    => round($info_list_v['unit_price'],4),
                            'enter_cost'    => (float)$info_list_v['price'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                    }
                    //收发详情结束

                    //批次跟踪
                    if($batch==1 && $goods['batch']==1){
                        $where = [];
                        $where['goods_id'] = $goods['id'];
                        if($goods_attr>0){
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
                                $batch_id = Db::name('kuerp_goods_batch')->where(
                                    [
                                        'goods_id' => $goods['id'],
                                        'attribute_id' => isset($goods_attr)?$goods_attr:0,
                                        'stores_id' => $info_list_v['stores_id'],
                                        'id' => $info_list_v['batch_id'],
                                    ]
                                )->value('id');
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
                                $info_list_v['batch_id'] = $batch_id;
                                $edit_batch[] = ['id'=>$info_list_v['id'],'batch_id'=>$batch_id];
                                $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id' => $batch_id,
                                    'goods_id' => $info_list_v['goods_id'],
                                    'goods_code' => $goods['goods_code'],
                                    'attribute_id' => $goods_attr,
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
                        $goods_batch_info[] = [
                            'purchase_info_id'=> $info_list_v['id'],
                            'batch_id'    => isset($info_list_v['batch_id'])?$info_list_v['batch_id']:'',
                            'goods_id'    => $goods['id'],
                            'goods_code'  => $goods['goods_code'],
                            'attribute_id'=> isset($goods_attr)?$goods_attr:0,
                            'goods_unit'  => (int)$info_list_v['goods_unit'],
                            'stores_id'   => (int)$info_list_v['stores_id'],
                            'stock'       => (float)$info_list_v['basic_nmber'],
                            'number'      => 0,
                            'batch_name'  => isset($info_list_v['batch'])?$info_list_v['batch']:'',
                            'manufacture' => ($info_list_v['manufacture']),
                            'guarantee'   => $info_list_v['guarantee'],
                            'validity'    => ($info_list_v['validity']),
                            'status'      => '0',
                            'type'        => $list_v['business_type']==1?3:4,
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ];
                        //商品批次跟踪
                        $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $goods['id'],
                            'goods_unit'    => $info_list_v['goods_unit'],
                            'stores_id'     => $info_list_v['stores_id'],
                            'batch_id'      => (float)$info_list_v['batch_id'],
                            'number'        => (float)$info_list_v['basic_nmber'],
                            'purchase_code' => $info_list_v['purchase_code'],
                            'purchase_time' => $info_list_v['purchase_time'],
                            'business_type' => $list_v['business_type']==1?7:8,
                            'remarks'       => $info_list_v['remark'],
                            'order_id'      => $info_list_v['id'],
                            'unit_price'    => $info_list_v['unit_price'],
                            'tax_included'  => $info_list_v['unit_price'],
                            'all_money'     => $info_list_v['price'],
                            'tax_money'     => 0,
                            'money_tax_price' => $info_list_v['price'],
                            'customer_id'   => $supplyer,
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                        //商品批次跟踪结束
                    }

                    //序列号跟踪
                    if($goods && $goods['serial']==1 && $serial==1 && !empty($info_list_v['period'])){
                        $period = unserialize($info_list_v['period']);
                        foreach ($period as $period_k=>$period_v){

                            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>$period_v['serial']])->field('serial,status,store_status')->find();//序列号
                            if($per_s){//仓库有记录
                                $type = Db::name('kuerp_warehousing')->where(['purchase_code'=>$list_v['purchase_code']])->value('business_type');
                                $edit_serial[] = ['serial'=>$per_s['serial'],'warehousing_id'=>$info_list_v['id'],'type'=>($type==1?7:8),'old_status'=>$per_s['status'],'old_store'=>$per_s['store_status']];
                            }else{
                                if(!in_array($period_v['serial'],$per_code)){
                                    $per_code[] = $period_v['serial'];
                                }
                                $goods_serial[] = [
                                    'warehousing_id' => $info_list_v['id'],
                                    'goods_id'=>$goods['id'],
                                    'goods_code'=>$goods['goods_code'],
                                    'stores_id'=>$info_list_v['stores_id'],
                                    'attribute_id'=>isset($goods_attr)?$goods_attr:0,
                                    'serial'=>$period_v['serial'],
                                    'remark'=>$period_v['remark'],
                                    'status'=> $list_v['business_type']==1?7:8,
                                    'store_status'=>1,
                                    'warehousing_status'=>'2',
                                    'order_id'      => $info_list_v['id'],
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ];
                            }
                            $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                                'goods_id'=>$info_list_v['goods_id'],
                                'stores_id'=>$info_list_v['stores_id'],
                                'attribute_id'=>isset($goods_attr)?$goods_attr:0,
                                'serial'=>$period_v['serial'],
                                'business_type'=>$list_v['business_type']==1?7:8,
                                'store_status'=>'1',
                                'remarks' => $period_v['remark'],
                                'purchase_id'=>$info_list_v['id'],
                                'customer_id'=>$supplyer,
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

            $per_s = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$per_code],'store_status'=>'1'])->column('serial');
            if($per_s){
                $per_str = implode(',',$per_s);
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'序列号'.$per_str.'已在库，不可重复入库'];
                }
                $this->error('序列号'.$per_str.'已在库，不可重复入库');
            }

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                if(!empty($edit_batch)){
                    foreach ($edit_batch as $edit_batch_k=>$edit_batch_v){
                        Db::name('kuerp_warehousing_info')->where(['id'=>$edit_batch_v['id']])->update(['batch_id'=>$edit_batch_v['batch_id']]);
                    }
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
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                //商品收发明细
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }


                //批次库存数据
                //批次
                if($goods_batch_info){
                    Db::name('kuerp_goods_batch_info')->insertAll($goods_batch_info);
                }
                if(!empty($goods_batch_log)){
                    Db::name('kuerp_goods_batch_log')->insertAll($goods_batch_log);
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
                //批次库存数据结束

                //序列号
                if(!empty($goods_serial)){
                    Db::name('kuerp_goods_serial')->insertAll($goods_serial);
                }
                if(!empty($edit_serial)){
                    foreach ($edit_serial as $edit_serial_k=>$edit_serial_v){
                        Db::name('kuerp_goods_serial')->where(['serial'=>$edit_serial_v['serial']])->update([
                            'warehousing_id'=>$edit_serial_v['warehousing_id'],
                            'status'=>$edit_serial_v['type'],
                            'store_status'=>1,
                            'old_status'=>$edit_serial_v['old_status'],
                            'old_store'=>$edit_serial_v['old_store']
                        ]);
                    }
                }
                //序列号跟踪
                if(!empty($serial_log)){
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
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
            $edit_arr = [];
            foreach ($list as $list_k=>$list_v){
                $info_id = Db::name('kuerp_warehousing_info')->where(['purchase_code'=>$list_v['purchase_code']])->column('id');
                $edit_arr[] = ['business_type'=>$list_v['business_type'],'ids'=>$info_id];
            }
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $editstock = [];$goods_stock = [];
            $info_list = Db::name('kuerp_warehousing_info')->where(['purchase_code'=>['in',$list]])->select();

            $purchase_info = [];$goods_batch_edit = [];
            foreach ($info_list as $list_k=>$list_v){
                $stock_number = $list_v['basic_nmber'];
                $goods = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])->field('id,attribute_id,inventory,goods_name,goods_code,batch')->find();

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

                $where = [];
                $where['goods_code'] = $goods['goods_code'];
                $where['stores_id'] = $list_v['stores_id'];
                $goods_attr = 0;
                if($list_v['goods_attr']>0){
                    $goods_attr = $list_v['goods_attr'];
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
                            $editstock[$editstock_k]['cost_money'] = (round($editstock_v['cost_money']-$list_v['price'],4));
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']-$stock_number,2)),
                            'cost_money'=>(round($stock['cost_money']-$list_v['price'],4))
                        ];
                    }
                }else{
                    $store = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->value('name');
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足,操作失败'];
                    }
                    $this->error($store.'库存不足,反审核失败');
                }
                $purchase_info[] = $list_v['id'];

                if($goods['batch']==1 && $batch==1){
                    //批次库存数据
                    $where = [];
                    $where['goods_id'] = $list_v['goods_id'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                    $where['stores_id'] = $list_v['stores_id'];
                    $where['batch_id'] = $list_v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'库存不足'];
                        }
                        $this->error('库存不足');
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                    }
                }
            }
            foreach ($editstock as $editstock_k=>$editstock_v){
                if($editstock_v['stock']<0){
                    $store = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->value('name');
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足,操作失败'];
                    }
                    $this->error($store.'库存不足,反审核失败');
                }
            }

            foreach ($goods_batch_edit as $goods_batch_edit_k=>$goods_batch_edit_v){
                if($goods_batch_edit_v<0){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'库存不足'];
                    }
                    $this->error('库存不足');
                }
            }
            //批次库存数据结束

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>0,'examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                //商品详细库存
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                //商品总库存
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }

                foreach ($edit_arr as $edit_arr_k=>$edit_arr_v){
                    //批次
                    $type = $edit_arr_v['business_type']==1?3:4;
                    Db::name('kuerp_goods_batch_info')->where(['type'=>$type,'purchase_info_id'=>['in',$edit_arr_v['ids']]])->delete();
                    $type = $edit_arr_v['business_type']==1?7:8;
                    Db::name('kuerp_goods_batch_log')->where(['business_type'=>$type,'order_id'=>['in',$edit_arr_v['ids']]])->delete();
                    Db::name('kuerp_goods_serial')->where(['status'=>$type,'warehousing_status'=>'1','order_id'=>['in',$edit_arr_v['ids']]])->delete();
                    Db::name('kuerp_goods_serial_log')->where(['business_type'=>$type,'order_id'=>['in',$edit_arr_v['ids']]])->delete();
                    $type = $edit_arr_v['business_type']==1?8:9;
                    Db::name('kuerp_goods_log')->where(['business_type'=>$type,'purchase_id'=>['in',$edit_arr_v['ids']]])->delete();

                    $serial = Db::name('kuerp_goods_serial')->where(['warehousing_status'=>'2','order_id'=>['in',$edit_arr_v['ids']]])->select();
                    foreach ($serial as $serial_k=>$serial_v){
                        Db::name('kuerp_goods_serial')->where(['id'=>$serial_v['id']])->update(['status'=>$serial_v['old_status'],'store_status'=>$serial_v['old_store']]);
                    }
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

    // 打印其他入库单详情
    public function printpage($ids = null)
    {
        $row = $this->model->where(['id' => $ids])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 供应商名称
        $supplier = Db::name('kuerp_suppliers')->where(['id' => $row['suppliers_id']])->find();
        $row['supplyer_name'] = isset($supplier['supplyer_name']) ? $supplier['supplyer_name'] : '';
        // 制单人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('username');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('username');

        // 入库单详情
        $info = Db::name('kuerp_warehousing_info')->where(['purchase_code' => $row['purchase_code']])->select();
        foreach ($info as $k => $v) {
            $goods = Db::name('kuerp_goods')->where(['id' => $v['goods_id']])->find();
            $info[$k]['goods_code'] = isset($goods['goods_code']) ? $goods['goods_code'] : '';
            $info[$k]['goods_name'] = isset($goods['goods_name']) ? $goods['goods_name'] : '';
            $info[$k]['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $info[$k]['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
        }
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $info);

        // 增加打印次数
        $this->model->where(['id' => $ids])->setInc('print_num');

        return $this->view->fetch();
    }

    public function formatInfo(){
        $data = input();
        //获取设置
        $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
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
        //需格式化字段数组
        $format = [
            'number'=>$number_decimal,
            'allow_stock'=>$number_decimal,
            'basic_nmber'=>$number_decimal,
            'unit_price'=>$price_decimal,
            'all_money'=>$price_decimal,
        ];
        foreach ($data as $k=>$v){
            if(array_key_exists($k,$format)){
                $data[$k] = (float)$data[$k];
            }
        }

        $data['basic_nmber'] = $data['number']*$data['basic_nmber'];
        $all_money = $data['number']*$data['unit_price'];
        $data['all_money'] = $all_money;

        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
            }
        }

        $arr = [];
        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
            }
            $arr[] = ['key'=>$data_k,'value'=>$data[$data_k]];
        }
        return json_encode(['data'=>$arr]);
    }

    public function checkInventory($purchase_code,$type){
        $type_arr = [];
        switch ($type){
            case 1:
                //盘点
                $info = Db::name('kuerp_inventory_info')->where(['purchase_code'=>$purchase_code])->select();
                foreach ($info as $info_k=>$info_v){
                    if($info_v['profit']>0){
                        $py = ['type'=>1,'purchase_code'=>$purchase_code,'status'=>1,'title'=>'盘盈单'];
                    }else if($info_v['profit']<0){
                        $pk = ['type'=>1,'purchase_code'=>$purchase_code,'status'=>2,'title'=>'盘亏单'];
                    }
                }
                if(isset($py)){
                    $type_arr[] = $py;
                }
                if(isset($pk)){
                    $type_arr[] = $pk;
                }
                break;
            case 2:
                //序列号盘点
                $info = Db::name('kuerp_inventory_serial_info')->where(['purchase_code'=>$purchase_code])->select();
                foreach ($info as $info_k=>$info_v){
                    if($info_v['inventory']>0){
                        $py = ['type'=>2,'purchase_code'=>$purchase_code,'status'=>1,'title'=>'盘盈单'];
                    }else if($info_v['loss']>0){
                        $pk = ['type'=>2,'purchase_code'=>$purchase_code,'status'=>2,'title'=>'盘亏单'];
                    }
                }
                if(isset($py)){
                    $type_arr[] = $py;
                }
                if(isset($pk)){
                    $type_arr[] = $pk;
                }
                break;
            default:
                $this->error('参数错误');
        }
        $this->success($type_arr);
    }
}
