<?php

namespace app\admin\controller\kuerp;

use app\admin\model\kuerp\GoodsSalesOrderLog;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 销货订单
 *
 * @icon fa fa-circle-o
 */
class GoodsSalesOrder extends Backend
{
    protected $noNeedRight = ['checkpurchase','checkexchange','ship','delDelivery','confirmReceipt','getCustomerSaleUser'];
    /**
     * GoodsSalesOrder模型对象
     * @var \app\admin\model\kuerp\GoodsSalesOrder
     */
    protected $model = null;

    /**
     * 当前登录管理员的销售人员ID（仅当管理员绑定type=sales的职员时）
     * @var int|null
     */
    protected $currentSaleUserId = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesOrder;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("suppliersList", $this->model->getSuppliersList());
        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("accountsList", $this->model->getAccountsList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("saleList", $this->model->getSaleList());

        $this->view->assign("secondExamineStatusList", $this->model->getSecondExamineStatusList());
        $this->view->assign("sourceList", $this->model->getSourceList());

        // 二次审核金额阈值
        $sales_order_examine_money = Db::name('kuerp_sets')->where(['name'=>'sales_order_examine_money'])->value('value');
        $this->assignconfig('sales_order_examine_money', $sales_order_examine_money ? $sales_order_examine_money : '0');

        // 检查当前管理员是否绑定销售类型职员，若是则只能查看自己的销货订单
        // 超级管理员不受此限制
        if (!$this->auth->isSuperAdmin()) {
            $admin = Session::get('admin');
            if ($admin) {
                $staff = Db::name('kuerp_staff_member')
                    ->where(['admin_id' => $admin['id'], 'type' => 'sales', 'status' => '1'])
                    ->find();
                if ($staff) {
                    $this->currentSaleUserId = $staff['id'];
                }
            }
        }
        $this->assignconfig('currentSaleUserId', $this->currentSaleUserId);
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
                // 普通销售员在selectpage中也只能看到自己的订单
                if (!empty($this->currentSaleUserId)) {
                    $this->model->where('sale_user', $this->currentSaleUserId);
                }
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'purchase_time';$order='DESC';
            $list = $this->model
                ->with(['customers','preparation','examine','secondExamine'])
                ->where($where);
            // 普通销售员只能查看自己的销货订单
            if (!empty($this->currentSaleUserId)) {
                $list = $list->where('sale_user', $this->currentSaleUserId);
            }
            $list = $list->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                // 制单人/审核人/二审人展示管理员昵称（中文姓名）
                $row->getRelation('preparation')->visible(['nickname']);
                $row->getRelation('examine')->visible(['nickname']);
                if ($row->getRelation('secondExamine')) {
                    $row->getRelation('secondExamine')->visible(['nickname']);
                }
            }
            // 订单状态由销货单的实际出库数量决定（不随发货/生成销货单变化）
            $orderCodes = [];
            foreach ($list as $list_v) {
                $orderCodes[] = $list_v['purchase_code'];
            }
            $statusMap = \app\admin\model\kuerp\GoodsSalesOrder::syncStatusByOutbound($orderCodes);

            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                $purchase_id = Db::name('kuerp_goods_saleord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                $purchase = Db::name('kuerp_goods_sales')->where(['id'=>['in',$purchase_id]])->column('purchase_code');
                $list[$list_k]['purchase_id'] = implode(',',$purchase);
                if(isset($statusMap[$list_v['purchase_code']])){
                    $list[$list_k]['status'] = $statusMap[$list_v['purchase_code']];
                }
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
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if ($params) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                $params = $this->preExcludeFields($params);
                // 发货方式：整单一个标记，默认直发
                $delivery_mode = $this->normalizeDeliveryMode(isset($params['delivery_mode']) ? $params['delivery_mode'] : '', 'direct');
                // 客户承担费用（生成销货单时带出）
                $customers_money = isset($params['customers_money']) ? (float)$params['customers_money'] : 0;
                $admin = Session::get('admin');

                // 普通销售员强制绑定自己为销售员，不可选择他人
                if (!empty($this->currentSaleUserId)) {
                    $params['sale_user'] = $this->currentSaleUserId;
                }

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                if(empty($params['customers_id'])){
                    $this->error('请选择客户');
                }
                $customers = Db::name('kuerp_customers')->where(['id'=>$params['customers_id']])->find();
                if(empty($customers)){
                    $this->error('客户不存在');
                }

                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;$money_tax_price=0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        $money_tax_price += (float)$params['money_tax_price'][$k];
                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }
                        //仓库取自商品档案的首选仓库（销货订单页不显示仓库列，无法手工选择）；
                        //首选仓库为空（=0）时按商品类型兜底：零件→零件仓、成品→成品仓、半成品→半成品仓。
                        //库存为 0/负数不拦截：允许先下单，待采购单/组装单/外协加工单入库后自动补平。
                        $stores_id = $this->resolveStoresId(isset($params['stores_id'][$k]) ? $params['stores_id'][$k] : 0, $goods);
                        if(empty($stores_id)){
                            $this->error('商品'.$goods['goods_name'].'未设置仓库，请先在仓库资料中启用仓库');
                        }
                        if($goods['auxiliary']==1 && empty($params['goods_attr'][$k])){
                            $this->error('请选择商品属性');
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'delivery_time'     =>  strtotime($params['delivery_time']),
                            'delivery_mode'     =>  $delivery_mode,
                            'customers_id'      =>  (int)$params['customers_id'],//客户
                            'sale_user'         =>  (int)$params['sale_user'],//销售员
                            'receiving_address' =>  $params['receiving_address'],

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  $stores_id,
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'purchase_number'   =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'cost_price'        =>  \addons\kuerp\library\Goods::getCost_Price($v,$stores_id),
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'production_remark' =>  isset($params['production_remark'][$k]) ? $params['production_remark'][$k] : '',

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],
                            'customers_money'   =>  $customers_money,

                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],

                            'library_number'    =>  (float)$params['number'][$k],
                            'library_money'     =>  (float)$params['all_money'][$k],

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ];
                    }
                }
                if(!empty($data)){
                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  strtotime($params['delivery_time']),
                        'delivery_mode'     =>  $delivery_mode,
                        'customers_id'      =>  (int)$params['customers_id'],//客户
                        'sale_user'         =>  (int)$params['sale_user'],//销售员
                        'receiving_address' =>  $params['receiving_address'],
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,

                        'tax_money'         =>  $tax_money,
                        'money_tax_price'   =>  $money_tax_price,
                        'customers_money'   =>  $customers_money,

                        'preferential_rate' =>  (float)$params['preferential_rate'],
                        'preferential_money'=>  (float)$params['preferential_money'],
                        'real_money'        =>  (float)$params['real_money'],
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

                //附件记录开始
                $enclosureLog = [];
                if(isset($params['enclosure']) && !empty($params['enclosure'])){//选择了原始单据
                    $enclosure = explode(',',$params['enclosure']);
                    $enclosurelist = Db::name('kuerp_enclosure')->where(['id'=>['in',$params['enclosure']]])->select();
                    if(sizeof($enclosurelist)!=sizeof($enclosure)){
                        $this->error('选择的原始单据有误，请重新选择');
                    }else{
                        foreach ($enclosure as $k=>$v){
                            $enclosureLog[] = [
                                'enclosure_id'  => $v,
                                'purchase_code' => $params['purchase_code'],
                                'type'          => '4',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->insertGetId($row);
                    }

                    //添加附件记录
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $result;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$params['purchase_code']])->delete();
                    Db::name('kuerp_goods_sales_order_info')->insertAll($data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    // 写入操作日志
                    GoodsSalesOrderLog::writeLog($result, '创建销货订单', '新建销货订单：' . $row['purchase_code'], 'sales_order', $result);

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
        $code = \addons\kuerp\library\Goods::getCodes('',10);
        $this->view->assign("code", $code);
        // 发货方式默认直发
        $this->view->assign("delivery_mode", 'direct');

        $last_customer = Db::name('kuerp_sets')->where(['name'=>'customer'])->value('is_checked');
        $set_customer = '';
        if($last_customer){
            $set_customer = Db::name('kuerp_goods_sales_order')->order('id','DESC')->value('customers_id');
        }
        $this->view->assign("set_customer", $set_customer);
        $address = Db::name('kuerp_customers')->where(['id'=>$set_customer])->value('getgoods_address');
        $this->view->assign("address", $address);

        $last_read = Db::name('kuerp_sets')->where(['name'=>'last_read'])->value('is_checked');
        $sale_user = '';
        if($last_read){
            $sale_user = Db::name('kuerp_goods_sales_order')->order('id','DESC')->value('sale_user');
        }
        // 录单自动填充本账号绑定的销售员（下拉仍可手动修改）
        if (!empty($this->currentSaleUserId)) {
            $sale_user = $this->currentSaleUserId;
        }
        $this->view->assign("sale_user", $sale_user);

        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 普通销售员只能编辑自己的销货订单
        if (!empty($this->currentSaleUserId) && $row['sale_user'] != $this->currentSaleUserId) {
            $this->error('无权编辑该订单');
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
            if($row['purchase_type']==1){
                $purchase_id = Db::name('kuerp_goods_saleord')->where(['order_id'=>$ids])->column('purchase_id');
                if(!empty($purchase_id)){
                    $this->error('不能编辑已生成销货单的数据');
                }
            }else{
                $purchase_id = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$ids])->column('exchange_id');
                if(!empty($purchase_id)){
                    $this->error('不能编辑已生成退货单的数据');
                }
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                // 发货方式：整单一个标记；参数缺失（如只读表单）时保留原值
                $delivery_mode = $this->normalizeDeliveryMode(isset($params['delivery_mode']) ? $params['delivery_mode'] : '', isset($row['delivery_mode']) ? $row['delivery_mode'] : 'direct');
                // 客户承担费用：表单未提交（如只读）时保留原值
                $customers_money = isset($params['customers_money']) && $params['customers_money'] !== '' ? (float)$params['customers_money'] : (isset($row['customers_money']) ? (float)$row['customers_money'] : 0);

                // 普通销售员强制绑定自己为销售员，不可选择他人
                if (!empty($this->currentSaleUserId)) {
                    $params['sale_user'] = $this->currentSaleUserId;
                }

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                if(empty($params['customers_id'])){
                    $this->error('请选择客户');
                }
                $customers = Db::name('kuerp_customers')->where(['id'=>$params['customers_id']])->find();
                if(empty($customers)){
                    $this->error('客户不存在');
                }

                $admin = Session::get('admin');

                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;$money_tax_price=0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        $money_tax_price += (float)$params['money_tax_price'][$k];

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }
                        //仓库取自商品档案的首选仓库（销货订单页不显示仓库列，无法手工选择）；
                        //首选仓库为空（=0）时按商品类型兜底：零件→零件仓、成品→成品仓、半成品→半成品仓。
                        //库存为 0/负数不拦截：允许先下单，待采购单/组装单/外协加工单入库后自动补平。
                        $stores_id = $this->resolveStoresId(isset($params['stores_id'][$k]) ? $params['stores_id'][$k] : 0, $goods);
                        if(empty($stores_id)){
                            $this->error('商品'.$goods['goods_name'].'未设置仓库，请先在仓库资料中启用仓库');
                        }
                        if($goods['auxiliary']==1 && empty($params['goods_attr'][$k])){
                            $this->error('请选择商品属性');
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'delivery_time'     =>  strtotime($params['delivery_time']),
                            'delivery_mode'     =>  $delivery_mode,
                            'customers_id'      =>  (int)$params['customers_id'],//客户
                            'sale_user'         =>  (int)$params['sale_user'],//销售员
                            'receiving_address' =>  $params['receiving_address'],

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],

                            'stores_id'         =>  $stores_id,
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'purchase_number'   =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'cost_price'        =>  \addons\kuerp\library\Goods::getCost_Price($v,$stores_id),
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'production_remark' =>  isset($params['production_remark'][$k]) ? $params['production_remark'][$k] : '',

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],
                            'customers_money'   =>  $customers_money,

                            'library_number'    =>  (float)$params['number'][$k],
                            'library_money'     =>  (float)$params['all_money'][$k],

                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],
                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                        ];
                    }
                }
                if(!empty($data)){
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  strtotime($params['delivery_time']),
                        'delivery_mode'     =>  $delivery_mode,
                        'customers_id'      =>  (int)$params['customers_id'],//客户
                        'sale_user'         =>  (int)$params['sale_user'],//销售员
                        'receiving_address' =>  $params['receiving_address'],
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,

                        'tax_money'         =>  $tax_money,
                        'money_tax_price'   =>  $money_tax_price,
                        'customers_money'   =>  $customers_money,

                        'preferential_rate' =>  $params['preferential_rate'],
                        'preferential_money'=>  $params['preferential_money'],
                        'real_money'        =>  $params['real_money'],
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                //附件记录开始
                $enclosureLog = [];
                if(isset($params['enclosure']) && !empty($params['enclosure'])){//选择了原始单据
                    $enclosure = explode(',',$params['enclosure']);
                    $enclosurelist = Db::name('kuerp_enclosure')->where(['id'=>['in',$params['enclosure']]])->select();
                    if(sizeof($enclosurelist)!=sizeof($enclosure)){
                        $this->error('选择的原始单据有误，请重新选择');
                    }else{
                        foreach ($enclosure as $k=>$v){
                            $enclosureLog[] = [
                                'enclosure_id'  => $v,
                                'purchase_code' => $params['purchase_code'],
                                'type'          => '4',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }

                    //添加附件记录
                    Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'4'])->delete();
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$row['purchase_code']])->delete();
                    Db::name('kuerp_goods_sales_order_info')->insertAll($data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    // 写入操作日志
                    GoodsSalesOrderLog::writeLog($ids, '编辑销货订单', '编辑销货订单：' . $row['purchase_code'], 'sales_order', $ids);
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

        $purchase_info = Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        // 批量查询动态库存
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsIdMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id', 'goods_code') : [];
        // 商品 型号/厂商/封装 展示字段
        $goodsSpecMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])
            ->column('goods_specification_id,vendor_name,packaging', 'goods_code') : [];
        $stockItems = [];
        foreach ($purchase_info as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? $goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsSales::getUnit($purchase_info_v['goods_code'], $purchase_info_v['stores_id'], $purchase_info_v['goods_attr']),true);
            $gid = isset($goodsIdMap[$purchase_info_v['goods_code']]) ? $goodsIdMap[$purchase_info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$purchase_info_v['stores_id'] . '-' . (int)$purchase_info_v['goods_attr'];
            $purchase_info[$purchase_info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
            $spec = isset($goodsSpecMap[$purchase_info_v['goods_code']]) ? $goodsSpecMap[$purchase_info_v['goods_code']] : [];
            $purchase_info[$purchase_info_k]['goods_specification_id'] = isset($spec['goods_specification_id']) ? $spec['goods_specification_id'] : '';
            $purchase_info[$purchase_info_k]['vendor_name'] = isset($spec['vendor_name']) ? $spec['vendor_name'] : '';
            $purchase_info[$purchase_info_k]['packaging'] = isset($spec['packaging']) ? $spec['packaging'] : '';
        }
        $this->view->assign("purchase_info", $purchase_info);
        $this->assignconfig("purchase_id", $row['id']);

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'4'])->column('enclosure_id');
        if(!empty($enclosure_id)){
            $this->assign('enclosure_count',sizeof($enclosure_id));
            $enclosure_id = implode(',',$enclosure_id);
        }else{
            $enclosure_id = '';
        }
        $this->assign('enclosure_ids',$enclosure_id);

        //审核禁止输入
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine_set==1 && $row['examine_status']==1){
            $this->assign('exclass','hiddens');
        }
        $this->assignconfig("purchase_type", $row['purchase_type']);

        //已生成销货单/退货单的订单禁止修改，表单只读展示（与edit保存时的校验保持一致）
        $has_sales_doc = 0;
        if($row['purchase_type']==1){
            $doc_id = Db::name('kuerp_goods_saleord')->where(['order_id'=>$ids])->column('purchase_id');
        }else{
            $doc_id = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$ids])->column('exchange_id');
        }
        if(!empty($doc_id)){
            $has_sales_doc = 1;
            $this->assign('exclass','hiddens');
            $this->assign('has_sales_doc', 1);
        }
        $this->assignconfig("has_sales_doc", $has_sales_doc);
        $this->assignconfig("examine_status", $row['examine_status']);

        // 查询操作日志列表
        $logList = Db::name('kuerp_goods_sales_order_log')
            ->where('order_id', $ids)
            ->order('id', 'desc')
            ->select();
        $adminIds = array_unique(array_column($logList, 'admin_id'));
        $adminMap = [];
        if (!empty($adminIds)) {
            $admins = Db::name('admin')->where('id', 'in', $adminIds)->column('nickname', 'id');
            foreach ($admins as $aid => $auser) {
                $adminMap[$aid] = $auser;
            }
        }
        foreach ($logList as &$log) {
            $log['admin_username'] = isset($adminMap[$log['admin_id']]) ? $adminMap[$log['admin_id']] : '';
        }
        $this->view->assign('logList', $logList);

        // 查询发货记录列表
        $deliveryList = Db::name('kuerp_goods_sales_order_delivery')
            ->where('order_id', $ids)
            ->order('id', 'desc')
            ->select();
        $this->view->assign('deliveryList', $deliveryList);

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
            // 普通销售员只能删除自己的销货订单
            if (!empty($this->currentSaleUserId)) {
                $this->model->where('sale_user', $this->currentSaleUserId);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            foreach ($list as $list_k=>$list_v){
                if($list_v['examine_status']==1){
                    $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                    if($checkout!==true){
                        $this->error($checkout);
                    }
                }
                if($list_v['purchase_type']==1){
                    $purchase_id = Db::name('kuerp_goods_saleord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                    if(!empty($purchase_id)){
                        $this->error('不能删除已生成销货单的数据');
                    }
                }else{
                    $purchase_id = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                    if(!empty($purchase_id)){
                        $this->error('不能删除已生成退货单的数据');
                    }
                }
            }

            foreach ($list as $list_k=>$list_v){
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
                    $purchaseCode = $v['purchase_code'];
                    $count += $v->delete();
                    // 写入操作日志
                    GoodsSalesOrderLog::writeLog($v['id'], '删除销货订单', '删除销货订单：' . $purchaseCode, 'sales_order', $v['id']);
                    Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$purchaseCode])->delete();

                    //删除附件记录
                    Db::name('kuerp_enclosure_log')->where(['purchase_code'=>$v['purchase_code'],'type'=>'4'])->delete();
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
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }
            $admin = Session::get('admin');

            // 获取二次审核金额阈值
            $threshold = Db::name('kuerp_sets')->where(['name'=>'sales_order_examine_money'])->value('value');
            $threshold = floatval($threshold);

            // 分组：低于/等于阈值的自动完成二次审核，高于阈值的需要董事长二次审核
            $belowIds = [];  // 低于阈值，自动跳过二次审核
            $aboveIds = [];  // 高于阈值，需要二次审核
            foreach ($list as $list_v){
                if ($threshold > 0 && floatval($list_v['all_money']) > $threshold){
                    $aboveIds[] = $list_v['id'];
                } else {
                    $belowIds[] = $list_v['id'];
                }
            }

            Db::startTrans();
            try {
                $result = 0;
                // 低于阈值的：审核同时自动完成二次审核
                if (!empty($belowIds)){
                    $result += $this->model->where(['id'=>['in',$belowIds],'examine_status'=>'0'])->update([
                        'examine_status'        => 1,
                        'examine_id'            => $admin['id'],
                        'examine_time'          => time(),
                        'second_examine_status' => 1,
                        'second_examine_id'     => $admin['id'],
                        'second_examine_time'   => time(),
                        'operation_id'          => $admin['id']
                    ]);
                }
                // 高于阈值的：只做一审，需要董事长二次审核
                if (!empty($aboveIds)){
                    $result += $this->model->where(['id'=>['in',$aboveIds],'examine_status'=>'0'])->update([
                        'examine_status'        => 1,
                        'examine_id'            => $admin['id'],
                        'examine_time'          => time(),
                        'operation_id'          => $admin['id']
                    ]);
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                // 写入操作日志
                foreach ($list as $logOrder) {
                    if (in_array($logOrder['id'], $belowIds)) {
                        GoodsSalesOrderLog::writeLog($logOrder['id'], '审核通过（含二审）', '销货订单审核通过（金额未超阈值，自动二审）：' . $logOrder['purchase_code'], 'sales_order', $logOrder['id']);
                    } else {
                        GoodsSalesOrderLog::writeLog($logOrder['id'], '审核通过（一审）', '销货订单一审通过（待二审）：' . $logOrder['purchase_code'], 'sales_order', $logOrder['id']);
                    }
                }
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $msg = sizeof($list).'条数据审核成功';
                if (!empty($aboveIds)){
                    $msg .= '，其中'.sizeof($aboveIds).'条金额超过阈值，需董事长二次审核';
                }
                $this->success($msg);
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('审核失败');
            }
        }else{
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->field('id,purchase_code,purchase_time,examine_id,second_examine_id,second_examine_status')->select();
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
            // 预检：已生成销货单/退货单的订单，仅自动二审（审核人=二审人，审核时金额未超阈值自动完成二审）的
            // 允许审核人直接反审核，系统级联反审核并删除其生成的销货单/退货单；手动二审的订单维持原限制。
            foreach ($list as $list_k=>$list_v){
                $sale_ids = Db::name('kuerp_goods_saleord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                $exchange_ids = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$list_v['id'],'type'=>'1'])->column('exchange_id');
                if(empty($sale_ids) && empty($exchange_ids)){
                    continue;
                }
                $isAutoSecond = ($list_v['second_examine_status']==1 && $list_v['examine_id']>0 && $list_v['examine_id']==$list_v['second_examine_id']);
                if(!$isAutoSecond){
                    if(!empty($sale_ids)){
                        if($examine_set!=1){
                            return ['status'=>0,'msg'=>'不能操作已生成销货单的数据'];
                        }
                        $this->error('不能反审核已生成销货单的数据');
                    }
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成退货单的数据');
                }
            }
            $admin = Session::get('admin');
            // cascadeDocs：订单id => 级联反审核并删除的单据编号，用于操作日志
            $cascadeDocs = [];
            Db::startTrans();
            try {
                foreach ($list as $list_k=>$list_v){
                    $sale_ids = Db::name('kuerp_goods_saleord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                    $exchange_ids = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$list_v['id'],'type'=>'1'])->column('exchange_id');
                    if(empty($sale_ids) && empty($exchange_ids)){
                        continue;
                    }
                    $cascadeDocs[$list_v['id']] = $this->cascadeRemoveDocs($sale_ids, $exchange_ids);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update([
                    'examine_status'        => 0,
                    'examine_id'            => 0,
                    'examine_time'          => '',
                    'second_examine_status' => 0,
                    'second_examine_id'     => 0,
                    'second_examine_time'   => '',
                    'operation_id'          => $admin['id']
                ]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                // 写入操作日志
                foreach ($list as $logOrder) {
                    $logNote = '销货订单反审核：' . $logOrder['purchase_code'];
                    if(!empty($cascadeDocs[$logOrder['id']])){
                        $logNote .= '（级联反审核并删除：' . implode('、', $cascadeDocs[$logOrder['id']]) . '）';
                    }
                    GoodsSalesOrderLog::writeLog($logOrder['id'], '反审核', $logNote, 'sales_order', $logOrder['id']);
                }
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

    /**
     * 级联反审核并删除销货订单生成的下游单据（销货单/退货单），返回被删除的单据编号数组
     * 仅在自动二审订单的直接反审核时调用；下游单据已对单/已生成收款单/核销单等情况
     * 会因其自身反审核校验失败而抛出异常，由上层回滚整个操作
     */
    protected function cascadeRemoveDocs($sale_ids = [], $exchange_ids = [])
    {
        $removedCodes = [];
        // 先退货单后销货单：销货单若被其生成的退货单引用，需先处理退货单
        if (!empty($exchange_ids)) {
            $exchangeController = new \app\admin\controller\kuerp\GoodsSalesExchange();
            foreach ($exchange_ids as $exchange_id) {
                $removedCode = $this->removeCascadeDoc($exchangeController, $exchange_id, '退货单');
                if ($removedCode !== '') {
                    $removedCodes[] = $removedCode;
                }
            }
        }
        if (!empty($sale_ids)) {
            $salesController = new \app\admin\controller\kuerp\GoodsSales();
            foreach ($sale_ids as $sale_id) {
                $removedCode = $this->removeCascadeDoc($salesController, $sale_id, '销货单');
                if ($removedCode !== '') {
                    $removedCodes[] = $removedCode;
                }
            }
        }
        return $removedCodes;
    }

    /**
     * 对单个下游单据执行 反审核（已审核时）→删除，返回单据编号
     */
    protected function removeCascadeDoc($controller, $doc_id, $docLabel)
    {
        $table = $docLabel == '销货单' ? 'kuerp_goods_sales' : 'kuerp_goods_sales_exchange';
        $doc = Db::name($table)->where(['id' => $doc_id])->field('purchase_code,examine_status')->find();
        if (empty($doc)) {
            return '';
        }
        if ($doc['examine_status'] == 1) {
            $this->runCascadeMethod($controller, 'examine', [$doc_id, 2], $docLabel, $doc['purchase_code']);
        }
        $this->runCascadeMethod($controller, 'del', [$doc_id], $docLabel, $doc['purchase_code']);
        return $doc['purchase_code'];
    }

    /**
     * 调用下游单据控制器方法；FastAdmin 的 success/error 均以 HttpResponseException 抛出，
     * 成功(code=1)静默返回，失败(code=0)转为普通异常由上层统一回滚并提示
     */
    protected function runCascadeMethod($controller, $method, $args, $docLabel, $docCode)
    {
        try {
            $result = call_user_func_array([$controller, $method], $args);
        } catch (\think\exception\HttpResponseException $e) {
            $json = json_decode($e->getResponse()->getContent(), true);
            if (empty($json) || !isset($json['code']) || $json['code'] != 1) {
                $msg = isset($json['msg']) && $json['msg'] !== '' ? $json['msg'] : '未知原因';
                throw new Exception($docLabel . '[' . $docCode . ']处理失败：' . $msg);
            }
            return;
        }
        // 审核开关关闭时控制器以数组返回结果而非抛异常
        if (is_array($result) && isset($result['status']) && $result['status'] == 0) {
            $msg = isset($result['msg']) && $result['msg'] !== '' ? $result['msg'] : '未知原因';
            throw new Exception($docLabel . '[' . $docCode . ']处理失败：' . $msg);
        }
    }

    // 董事长二次审核
    public function secondExamine($ids = null){
        $examine_set= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if(empty($ids)){
            $this->error('请选择要操作的数据');
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if(empty($ids)){
            $this->error('请选择要操作的数据');
        }

        $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1','second_examine_status'=>'0'])->select();
        if(empty($list)){
            $this->error('请选择已一审通过且未二审的数据');
        }

        $admin = Session::get('admin');
        Db::startTrans();
        try {
            $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1','second_examine_status'=>'0'])->update([
                'second_examine_status' => 1,
                'second_examine_id'     => $admin['id'],
                'second_examine_time'   => time(),
                'operation_id'          => $admin['id']
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if(isset($result) && $result !== false){
            // 写入操作日志
            foreach ($list as $logOrder) {
                GoodsSalesOrderLog::writeLog($logOrder['id'], '二次审核通过', '销货订单董事长二审通过：' . $logOrder['purchase_code'], 'sales_order', $logOrder['id']);
            }
            $this->success(sizeof($list).'条数据二次审核成功');
        }else{
            $this->error('二次审核失败');
        }
    }

    public function checkPurchase(){
        $order_id = isset($_POST['order_id'])?trim($_POST['order_id']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_sales_order')->where(['id'=>$order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        if($row['examine_status']!=1){
            $this->error('此销货订单未审核，不可生成销货单');
        }
        if($row['second_examine_status']!=1){
            $this->error('此销货订单未通过二次审核，不可生成销货单');
        }
        $purchase_info = Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_saleord_info')->where(['order_id'=>$purchase_info_v['id']])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此销货订单已全部生成销货单');
        }
        $this->success();
    }
    public function checkExchange(){
        $order_id = isset($_POST['order_id'])?trim($_POST['order_id']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_sales_order')->where(['id'=>$order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        if($row['examine_status']!=1){
            $this->error('此销货订单未审核，不可生成退货单');
        }
        if($row['second_examine_status']!=1){
            $this->error('此销货订单未通过二次审核，不可生成退货单');
        }
        $purchase_info = Db::name('kuerp_goods_sales_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_saleexc_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'1'])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此销货订单已全部生成退货单');
        }
        $this->success();
    }

    /**
     * 规范化发货方式：direct=直发（默认），dropship=代发
     */
    protected function normalizeDeliveryMode($value, $default = 'direct')
    {
        $value = is_string($value) ? trim($value) : '';
        if (in_array($value, ['direct', 'dropship'], true)) {
            return $value;
        }
        return in_array($default, ['direct', 'dropship'], true) ? $default : 'direct';
    }

    /**
     * 解析单据行的仓库：表单仓库 > 商品首选仓库 > 按商品类型兜底（零件仓/成品仓/半成品仓）。
     * 具体规则见 \addons\kuerp\library\Goods::resolveStoreId()。
     *
     * @param int|string $storesId  表单提交的仓库ID
     * @param array      $goods     商品档案行（需含 manager_warehouse_id、product_type）
     * @return int      0 表示一个可用仓库都没有
     */
    protected function resolveStoresId($storesId, $goods)
    {
        return \addons\kuerp\library\Goods::resolveStoreId($storesId, $goods);
    }

    /**
     * 获取客户绑定的销售员ID
     */
    public function getCustomerSaleUser()
    {
        $customers_id = isset($_POST['customers_id']) ? trim($_POST['customers_id']) : 0;
        if (empty($customers_id)) {
            return json_encode(['staff_member_id' => 0]);
        }
        $staff_member_id = Db::name('kuerp_customers')->where(['id' => $customers_id])->value('staff_member_id');
        return json_encode(['staff_member_id' => $staff_member_id ? (int)$staff_member_id : 0]);
    }

    /**
     * 发货
     */
    public function ship($ids = null)
    {
        $ids = $ids ? $ids : $this->request->param('ids');
        if (empty($ids)) {
            $this->error('参数错误');
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if ($row['examine_status'] != 1) {
            $this->error('未审核的订单不能发货');
        }
        if ($row['purchase_type'] != 1) {
            $this->error('退货订单不支持发货');
        }
        if ($row['status'] == 3) {
            $this->error('已取消的订单不能发货');
        }
        if ($row['status'] == 4) {
            $this->error('已收货的订单不能发货');
        }

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params['delivery_type'])) {
                $this->error('请选择配送方式');
            }

            $admin = Session::get('admin');
            $shippedTime = time();

            $deliveryList = [];
            $baseNo = 'DN' . date('YmdHi');

            if ($params['delivery_type'] == 'logistics') {
                // 解析快递信息 JSON，每条快递记录生成一个发货单
                $expressData = [];
                if (!empty($params['express'])) {
                    $expressData = json_decode($params['express'], true);
                }
                if (empty($expressData)) {
                    $this->error('请添加快递信息');
                }
                foreach ($expressData as $index => $express) {
                    $expressNo = isset($express['no']) ? trim($express['no']) : '';
                    if (empty($expressNo)) {
                        $this->error('请填写快递单号');
                    }
                    $expressCode = isset($express['code']) ? trim($express['code']) : '';
                    $expressCompany = '';
                    if (!empty($expressCode)) {
                        $expressCompany = Db::name('kuerp_data_express')
                            ->where(['code' => $expressCode])
                            ->value('name');
                        $expressCompany = $expressCompany ?: '';
                    }
                    $deliveryNo = $baseNo . rand(100, 999) . ($index + 1);
                    $deliveryList[] = [
                        'order_id'       => $ids,
                        'delivery_no'    => $deliveryNo,
                        'delivery_type'  => 'logistics',
                        'status'         => 'shipped',
                        'shipped_time'   => $shippedTime,
                        'admin_id'       => $admin['id'],
                        'remark'         => isset($params['remark']) ? $params['remark'] : '',
                        'express_code'   => $expressCode,
                        'express_no'     => $expressNo,
                        'express_company'=> $expressCompany,
                        'createdtime'    => $shippedTime,
                        'updatetime'     => $shippedTime,
                    ];
                }
            } else {
                // 专业配送：单条记录
                $deliveryNo = $baseNo . rand(100, 999) . '1';
                $deliveryList[] = [
                    'order_id'       => $ids,
                    'delivery_no'    => $deliveryNo,
                    'delivery_type'  => 'professional',
                    'status'         => 'shipped',
                    'shipped_time'   => $shippedTime,
                    'admin_id'       => $admin['id'],
                    'remark'         => isset($params['remark']) ? $params['remark'] : '',
                    'carrier_name'   => isset($params['carrier_name']) ? $params['carrier_name'] : '',
                    'carrier_mobile' => isset($params['carrier_mobile']) ? $params['carrier_mobile'] : '',
                    'createdtime'    => $shippedTime,
                    'updatetime'     => $shippedTime,
                ];
            }

            Db::startTrans();
            try {
                foreach ($deliveryList as $deliveryData) {
                    Db::name('kuerp_goods_sales_order_delivery')->insert($deliveryData);
                    // 写入操作日志
                    GoodsSalesOrderLog::writeLog($ids, '发货', '销货订单发货，发货单号：' . $deliveryData['delivery_no'] . '，配送方式：' . ($params['delivery_type'] == 'logistics' ? '物流配送' : '专业配送'), 'sales_order', $ids);
                }

                // 发货不改变订单出库状态：订单状态只由销货单的实际出库数量决定
                $this->model->where(['id' => $ids])->update([
                    'operation_id' => $admin['id'],
                    'updatetime'   => time(),
                ]);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            $this->success('发货成功，共生成' . count($deliveryList) . '条发货记录');
        }

        // 获取快递公司列表
        $expressList = Db::name('kuerp_data_express')->field('name,code')->order('weigh ASC')->select();
        $this->view->assign('expressList', $expressList);

        // 获取已有的发货记录
        $deliveryList = Db::name('kuerp_goods_sales_order_delivery')
            ->where(['order_id' => $ids])
            ->order('id', 'desc')
            ->select();
        $this->view->assign('deliveryList', $deliveryList);

        $this->view->assign('order', $row);

        return $this->view->fetch();
    }

    /**
     * 删除发货记录
     */
    public function delDelivery()
    {
        $id = $this->request->param('id');
        if (empty($id)) {
            $this->error('参数错误');
        }
        $delivery = Db::name('kuerp_goods_sales_order_delivery')->where(['id' => $id])->find();
        if (!$delivery) {
            $this->error('发货记录不存在');
        }
        $orderId = $delivery['order_id'];

        Db::startTrans();
        try {
            Db::name('kuerp_goods_sales_order_delivery')->where(['id' => $id])->delete();

            // 删除发货记录不改变订单出库状态：订单状态只由销货单的实际出库数量决定
            $this->model->where(['id' => $orderId])->update([
                'operation_id' => Session::get('admin')['id'] ?? 0,
                'updatetime'   => time(),
            ]);

            // 写入操作日志
            GoodsSalesOrderLog::writeLog($orderId, '删除发货', '删除发货记录：' . $delivery['delivery_no'], 'sales_order', $orderId);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('删除成功');
    }

    /**
     * 确认收货
     */
    public function confirmReceipt()
    {
        $ids = $this->request->param('ids');
        if (empty($ids)) {
            $this->error('参数错误');
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($row['examine_status'] != 1) {
            $this->error('未审核的订单不能确认收货');
        }
        if ($row['status'] == 0) {
            $this->error('未出库的订单不能确认收货');
        }
        if ($row['status'] == 3) {
            $this->error('已取消的订单不能确认收货');
        }
        if ($row['status'] == 4) {
            $this->error('该订单已确认收货');
        }

        $admin = Session::get('admin');
        Db::startTrans();
        try {
            $this->model->where(['id' => $ids])->update([
                'status'       => 4,
                'operation_id' => $admin['id'],
                'updatetime'   => time(),
            ]);

            GoodsSalesOrderLog::writeLog($ids, '确认收货', '销货订单确认收货', 'sales_order', $ids);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('确认收货成功');
    }
}
