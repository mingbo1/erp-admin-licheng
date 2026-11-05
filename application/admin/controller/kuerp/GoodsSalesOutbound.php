<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 销货单出库
 * 仓库人员专用：与销货单共用数据，仅不显示金额字段
 * 仓库人员只需了解出库数量，无需了解金额信息
 * @icon fa fa-truck
 */
class GoodsSalesOutbound extends Backend
{
    // outbound/saveOutbound 已移出 noNeedRight：出库须由计划部门申请、仓库在"出库管理"审核执行（saveOutbound 仅供出库管理内部调用）
    protected $noNeedRight = ['getunit','formatinfo2','getbranch','gettime','printpage','checkpurchase','getsuppliers','getunits','getreceivingaddress'];

    /**
     * GoodsSales模型对象（与销货单共用同一张表）
     * @var \app\admin\model\kuerp\GoodsSales
     */
    protected $model = null;

    // 金额字段列表（仓库人员不可见）
    private $hiddenAmountFields = ['all_money','preferential_rate','preferential_money','real_money','tax_money','money_tax_price','purchase_money','customers_money','pay_money','arrears','all_arrears','cost_data'];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSales;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());

        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("saleList", $this->model->getSaleList());

        $this->view->assign("accountsList", $this->model->getAccountsList());
        $accountsList = $this->model->getAccountsList();
        foreach ($accountsList as $accountsList_k=>$accountsList_v){
            if($accountsList_v['is_default']==1){
                $this->view->assign("accountsDefault", $accountsList_v['id']);
                $this->assignconfig("accountsDefault", $accountsList_v['id']);
            }
        }
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
            $sort = 'purchase_time';$order='DESC';
            // 出库状态为派生筛选（按销货单号汇总明细已出库数量），不能进 filter，走独立参数
            $outboundFilter = \addons\kuerp\library\Goods::outboundStatusCodes($this->request->get('outbound_status', ''));
            $query = $this->model
                ->with(['customers','preparation','examine','secondExamine'])
                ->where($where)
                ->where('examine_status', 1);
            if ($outboundFilter !== null) {
                $query->where('purchase_code', $outboundFilter['mode'], $outboundFilter['codes']);
            }
            $list = $query
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                $row->getRelation('preparation')->visible(['nickname']);
                $row->getRelation('examine')->visible(['nickname']);
                if ($row->getRelation('secondExamine')) {
                    $row->getRelation('secondExamine')->visible(['nickname']);
                }
                // 隐藏所有金额字段（仓库人员不可见）
                $row->hidden($this->hiddenAmountFields);
            }
            // 收集交货时间为空的销货订单号，批量查询订单的交货时间
            $emptyDeliveryCodes = [];
            foreach ($list as $list_v) {
                if (empty($list_v['delivery_time']) && !empty($list_v['purchase_order_id'])) {
                    $emptyDeliveryCodes[] = $list_v['purchase_order_id'];
                }
            }
            $orderDeliveryMap = [];
            if (!empty($emptyDeliveryCodes)) {
                $orderDeliveryMap = Db::name('kuerp_goods_sales_order')
                    ->where('purchase_code', 'in', array_unique($emptyDeliveryCodes))
                    ->column('delivery_time', 'purchase_code');
            }
            // 发货方式（代发/直发）：有销货订单关联时以订单为准
            $orderCodes = [];
            foreach ($list as $list_v) {
                if (!empty($list_v['purchase_order_id'])) {
                    $orderCodes[] = $list_v['purchase_order_id'];
                }
            }
            $orderDeliveryModeMap = [];
            if (!empty($orderCodes)) {
                $orderDeliveryModeMap = Db::name('kuerp_goods_sales_order')
                    ->where('purchase_code', 'in', array_unique($orderCodes))
                    ->column('delivery_mode', 'purchase_code');
            }
            // 出库状态：按销货单号汇总 计划数量/已出库数量（0未出库/1部分出库/2全部出库）
            $pageCodes = [];
            $pageSalesIds = [];
            foreach ($list as $list_v) {
                $pageCodes[] = $list_v['purchase_code'];
                $pageSalesIds[] = $list_v['id'];
            }
            $outSumMap = [];
            $lineMap = [];
            if (!empty($pageCodes)) {
                // 明细行原样取出：既用于汇总数量，也用于按 商品+仓库+属性 计算可用库存能否满足待出库数量
                $lineRows = Db::name('kuerp_goods_sales_info')
                    ->where('purchase_code', 'in', $pageCodes)
                    ->field('purchase_code, goods_code, stores_id, goods_attr, number, out_number')
                    ->select();
                foreach ($lineRows as $lineRow) {
                    $lineCode = $lineRow['purchase_code'];
                    if (!isset($outSumMap[$lineCode])) {
                        $outSumMap[$lineCode] = ['total_number' => 0, 'total_out' => 0];
                        $lineMap[$lineCode] = [];
                    }
                    $outSumMap[$lineCode]['total_number'] += (float)$lineRow['number'];
                    $outSumMap[$lineCode]['total_out'] += (float)$lineRow['out_number'];
                    $lineMap[$lineCode][] = $lineRow;
                }
            }
            // 状态列（进度优先）：全部已出库 / 部分已出库 / 全部可出库 / 部分可出库 / 库存不足
            $stockStatusMap = $this->calcStockStatus($outSumMap, $lineMap);
            // 申请状态：每个销货单取最新一条出库申请 + 待审核申请条数（同一销货单可并存多条待审）
            $applyStatusMap = [];
            $applyPendingMap = [];
            $applyCountMap = [];
            if (!empty($pageSalesIds)) {
                $applyRows = Db::name('kuerp_goods_outbound_apply')
                    ->where('sales_id', 'in', $pageSalesIds)
                    ->order('id', 'DESC')
                    ->select();
                foreach ($applyRows as $applyRow) {
                    if (!isset($applyStatusMap[$applyRow['sales_id']])) {
                        $applyStatusMap[$applyRow['sales_id']] = $applyRow['status'];
                    }
                    $applyCountMap[$applyRow['sales_id']] = isset($applyCountMap[$applyRow['sales_id']]) ? $applyCountMap[$applyRow['sales_id']] + 1 : 1;
                    if ($applyRow['status'] == '0') {
                        $applyPendingMap[$applyRow['sales_id']] = isset($applyPendingMap[$applyRow['sales_id']]) ? $applyPendingMap[$applyRow['sales_id']] + 1 : 1;
                    }
                }
            }
            foreach ($list as $list_k=>$list_v){
                // 出库状态：0未出库/1部分出库/2全部出库
                $outbound_status = 0;
                if (isset($outSumMap[$list_v['purchase_code']])) {
                    $total_number = (float)$outSumMap[$list_v['purchase_code']]['total_number'];
                    $total_out = (float)$outSumMap[$list_v['purchase_code']]['total_out'];
                    if ($total_out <= 0) {
                        $outbound_status = 0;
                    } elseif ($total_out < $total_number) {
                        $outbound_status = 1;
                    } else {
                        $outbound_status = 2;
                    }
                }
                $list[$list_k]['outbound_status'] = $outbound_status;
                // 状态列（进度优先）：0全部可出库/1部分可出库/2库存不足/3部分已出库/4全部已出库
                $list[$list_k]['stock_status'] = isset($stockStatusMap[$list_v['purchase_code']]) ? $stockStatusMap[$list_v['purchase_code']] : 0;
                // 申请状态：''无申请/'0'待审核/'1'已同意出库/'2'已拒绝/'3'已撤销
                $list[$list_k]['apply_status'] = isset($applyStatusMap[$list_v['id']]) ? $applyStatusMap[$list_v['id']] : '';
                // 待审核申请条数（同一销货单可并存多条）
                $list[$list_k]['apply_pending_count'] = isset($applyPendingMap[$list_v['id']]) ? (int)$applyPendingMap[$list_v['id']] : 0;
                // 历次申请记录条数（各状态合计，>0 时列表显示「申请记录」按钮）
                $list[$list_k]['apply_count'] = isset($applyCountMap[$list_v['id']]) ? (int)$applyCountMap[$list_v['id']] : 0;
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                $purchase_id = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                $purchase = Db::name('kuerp_goods_sales_exchange')->where(['id'=>['in',$purchase_id]])->column('purchase_code');
                $list[$list_k]['returns_order_id'] = implode(',',$purchase);
                // 如果交货时间为空，从关联的销货订单获取
                if (empty($list_v['delivery_time']) && !empty($list_v['purchase_order_id']) && isset($orderDeliveryMap[$list_v['purchase_order_id']])) {
                    $list_v['delivery_time'] = $orderDeliveryMap[$list_v['purchase_order_id']];
                }
                if (!empty($list_v['purchase_order_id']) && isset($orderDeliveryModeMap[$list_v['purchase_order_id']])) {
                    $list[$list_k]['delivery_mode'] = $orderDeliveryModeMap[$list_v['purchase_order_id']];
                } elseif (empty($list_v['delivery_mode'])) {
                    $list[$list_k]['delivery_mode'] = 'direct';
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 计算列表「状态」列（进度优先）：
     *   已出库 >= 计划数量            → 4 全部已出库
     *   已出库 > 0 且 < 计划数量      → 3 部分已出库
     *   已出库 = 0，库存 >= 待出库数量 → 0 全部可出库
     *   已出库 = 0，0 < 库存 < 待出库 → 1 部分可出库
     *   已出库 = 0，库存 <= 0         → 2 库存不足
     * 库存按行（商品+仓库+属性）取可用库存（已扣除其他订单锁定），逐行相加后与待出库数量比较。
     * @param array $outSumMap [purchase_code => ['total_number'=>x,'total_out'=>y]]
     * @param array $lineMap   [purchase_code => [明细行, ...]]
     * @return array [purchase_code => status]
     */
    private function calcStockStatus(array $outSumMap, array $lineMap)
    {
        $result = [];
        $stockItems = [];
        $goodsIdMap = [];
        $goodsCodes = [];
        foreach ($lineMap as $code => $lines) {
            foreach ($lines as $line) {
                if (!empty($line['goods_code'])) {
                    $goodsCodes[] = $line['goods_code'];
                }
            }
        }
        if (!empty($goodsCodes)) {
            $goodsIdMap = Db::name('kuerp_goods')->where('goods_code', 'in', array_unique($goodsCodes))->column('id', 'goods_code');
        }
        $seenKeys = [];
        foreach ($lineMap as $code => $lines) {
            foreach ($lines as $line) {
                $gid = isset($goodsIdMap[$line['goods_code']]) ? (int)$goodsIdMap[$line['goods_code']] : 0;
                if (empty($gid) || (float)$line['number'] <= (float)$line['out_number']) {
                    continue;
                }
                $dedupKey = $code . '-' . $gid . '-' . (int)$line['stores_id'] . '-' . (int)$line['goods_attr'];
                if (isset($seenKeys[$dedupKey])) {
                    continue;
                }
                $seenKeys[$dedupKey] = 1;
                $stockItems[] = [
                    'goods_id'     => $gid,
                    'stores_id'    => (int)$line['stores_id'],
                    'attribute_id' => (int)$line['goods_attr'],
                ];
            }
        }
        // 可用库存按 商品-仓库-属性 取值（与申请/出库弹窗口径一致，sales_order_id 传空 = 扣除所有锁定）
        $stockMap = !empty($stockItems) ? \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '') : [];

        foreach ($outSumMap as $code => $sumRow) {
            $lines = isset($lineMap[$code]) ? $lineMap[$code] : [];
            $totalNumber = (float)$sumRow['total_number'];
            $totalOut = (float)$sumRow['total_out'];
            if ($totalNumber <= 0) {
                $result[$code] = 0;      // 无明细数量，视为无可出库内容
                continue;
            }
            if ($totalOut >= $totalNumber) {
                $result[$code] = 4;      // 全部已出库
                continue;
            }
            if ($totalOut > 0) {
                $result[$code] = 3;      // 部分已出库（进度优先，不再看库存）
                continue;
            }
            // 未出库：按可用库存与待出库数量判断可出库程度
            // 同一 商品+仓库+属性 的多行合并共用一份库存，避免同一批库存被重复计入
            $needByKey = [];
            foreach ($lines as $line) {
                $remain = (float)$line['number'] - (float)$line['out_number'];
                if ($remain <= 0) {
                    continue;
                }
                if (empty($line['goods_code'])) {
                    continue;
                }
                $gid = isset($goodsIdMap[$line['goods_code']]) ? (int)$goodsIdMap[$line['goods_code']] : 0;
                $key = $gid . '-' . (int)$line['stores_id'] . '-' . (int)$line['goods_attr'];
                $needByKey[$key] = (isset($needByKey[$key]) ? $needByKey[$key] : 0) + $remain;
            }
            $needQty = 0;
            $stockQty = 0;
            foreach ($needByKey as $key => $remain) {
                $stock = isset($stockMap[$key]) ? (float)$stockMap[$key] : 0;
                $needQty += $remain;
                $stockQty += min($stock, $remain);
            }
            if ($needQty <= 0) {
                $result[$code] = 0;
            } elseif ($stockQty <= 0) {
                $result[$code] = 2;      // 库存不足
            } elseif ($stockQty >= $needQty) {
                $result[$code] = 0;      // 全部可出库
            } else {
                $result[$code] = 1;      // 部分可出库
            }
        }
        return $result;
    }

    /**
     * 仓库人员无权新增销货单（销货单由销售部门创建）
     */
    public function add($ids = null,$order_id=null)
    {
        $this->error("仓库人员无权新增销货单，请联系销售部门");
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
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
            if($checkout!==true){
                $this->error($checkout);
            }
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if($row['examine_status']==1 && $examine==1){
                $this->error('不能编辑已审核的数据');
            }
            if($row['check_status']==1){
                $this->error('不能编辑已对单的数据');
            }
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $old_purchase_code = $params['old_purchase_code'];unset($params['old_purchase_code']);
                $info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$old_purchase_code])->column('id');

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');
                if($params['now_pay_money']>0){
                    if(empty($params['account'])){
                        $this->error('请选择结算账户');
                    }
                    $all_now_pay_money = $now_pay_money = trim($params['now_pay_money']);
                }else{
                    $all_now_pay_money = $now_pay_money = 0;
                }

                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                $batch= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                $auxiliary_status= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;$money_tax_price=0;
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        $money_tax_price += (float)$params['money_tax_price'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        unset($pay_money);unset($pay_status);
                        if($now_pay_money>0){
                            if($now_pay_money-$params['money_tax_price'][$k]>=0){
                                $pay_status = 1;
                                $pay_money = $params['money_tax_price'][$k];
                                $now_pay_money -= $pay_money;
                            }else{
                                $pay_status = 2;
                                $pay_money = $now_pay_money;
                                $now_pay_money -= $now_pay_money;
                            }
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->field('id,serial,batch,auxiliary')->find();
                        $batch_data = Db::name('kuerp_goods_batch')->where(['id'=>$params['batch_id'][$k]])->find();
                        if ($goods['auxiliary']==1 && empty($params['goods_attr'][$k]) && $auxiliary_status==1){
                            return ['status'=>500,'msg'=>'请选择商品'.$v.'属性'];
                        }
                        $goods_batch_info = [];
                        if($goods['batch']==1 && $batch==1){
                            if(empty($params['batch'][$k])){
                                $this->error('请选择生产批次');
                            }
                            if(empty($batch_data)){
                                $this->error('生产批次不存在');
                            }
                            $goods_batch_info = [
                                'batch_id'    => isset($batch_data['id'])?$batch_data['id']:'',
                                'goods_id'    => $goods['id'],
                                'goods_code'  => $params['goods_code'][$k],
                                'attribute_id'=>isset($params['goods_attr'][$k])?$params['goods_attr'][$k]:0,
                                'goods_unit'  =>  (int)$params['goods_unit'][$k],
                                'stores_id'   => $params['stores_id'][$k],
                                'stock'       => (float)$params['number'][$k],
                                'number'      => (float)$params['number'][$k],
                                'batch_name'  => isset($batch_data['batch_name'])?$batch_data['batch_name']:'',
                                'manufacture' => strtotime($params['manufacture'][$k]),
                                'guarantee'   => $params['guarantee'][$k],
                                'validity'    => strtotime($params['validity'][$k]),
                                'status'      => '0',
                                'type'        => 2,
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                        }

                        $serial_code = [];
                        if(isset($params['period_period'][$k]) && $goods['serial']==1 && $set==1){
                            if(empty($params['period_period'][$k])){
                                $this->error('请输入商品序列号');
                            }
                            $period_period = unserialize($params['period_period'][$k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                $serial_code[] = $period_period_v['serial'];
                            }
                            $where = [];
                            $where['serial'] = ['in',$serial_code];
                            if(!empty($params['purchase_id'][$k])){
                                $where['purchase_code'] = $params['purchase_id'][$k];
                            }
                            $goods_serial = Db::name('kuerp_goods_serial')->where($where)->column('serial');
                            if(empty($goods_serial)){
                                $this->error('商品序列号不存在');
                            }
                            foreach ($serial_code as $serial_code_k=>$serial_code_v){
                                if(!in_array($serial_code_v,$goods_serial)){
                                    $this->error($serial_code_v.'商品序列号不存在');
                                }
                            }
                            $goods_serials = Db::name('kuerp_goods_serial')->where(['status'=>['in',[2,3]],'serial'=>['in',$serial_code],'purchase_exchange_id'=>['not in',$info_id]])->column('serial');
                            if(!empty($goods_serials)){
                                $goods_serials = implode(',',$goods_serials);
                                $this->error('商品序列号'.$goods_serials.'已经待出库，不可重复出库');
                            }
                        }

                        if(!empty($params['info_id'][$k])){
                            $unit_id_order = Db::name('kuerp_goods_sales_order_info')->where(['id'=>$params['info_id'][$k]])->value('goods_unit');
                            if(!empty($unit_id_order) && $unit_id_order!=$params['goods_unit'][$k]){
                                $this->error('选择的商品单位与源购货订单不一致，保存失败');
                            }
                        }

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'customers_id'      =>  (int)$params['customers_id'],//客户
                            'sale_user'         =>  (int)$params['sale_user'],//销售员
                            'receiving_address' =>  $params['receiving_address'],

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'order_info_id'     =>  (float)$params['info_id'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'cost_price'        =>  0,
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'production_remark' =>  isset($params['production_remark'][$k]) ? $params['production_remark'][$k] : '',

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'batch_id'          => isset($batch_data['id'])?$batch_data['id']:'',
                            'batch_name'        => isset($batch_data['batch_name'])?$batch_data['batch_name']:'',
                            'manufacture'       => strtotime($params['manufacture'][$k]),
                            'guarantee'         => $params['guarantee'][$k],
                            'validity'          => strtotime($params['validity'][$k]),

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],
                            'purchase_money'    =>  (float)$params['purchase_money'][$k],
                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],

                            'pay_status'        =>  isset($pay_status)?$pay_status:'0',
                            'pay_money'         =>  isset($pay_money)?$pay_money:0,
                            'account'           =>  isset($pay_status)?$params['account']:0,

                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                            'period'            =>  $params['period_period'][$k]===''?null:$params['period_period'][$k],
                            'serial_code'       => $serial_code,
                            'goods_batch_info'  => $goods_batch_info,
                        ];
                    }
                }
                $preferential_money = (float)$params['preferential_money'];
                if(!empty($data)){
                    if($all_now_pay_money>0){//当次支付金额
                        $more_money = $all_now_pay_money+$preferential_money;
                        $need_money = $money_tax_price+(float)$params['customers_money'];
                        $more_money = round($more_money,4);
                        $need_money = round($need_money,4);
                        if($more_money==$need_money){
                            $pay_status = 1;
                            $pay_money = ($money_tax_price+(float)$params['customers_money'])-$preferential_money;
                        }else{
                            $pay_status = 2;
                            $pay_money = $all_now_pay_money;
                        }
                    }
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'customers_id'      =>  (int)$params['customers_id'],//客户
                        'sale_user'         =>  (int)$params['sale_user'],//销售员
                        'receiving_address' =>  $params['receiving_address'],
                        'customers_money'   =>  (float)$params['customers_money'],

                        'number'            =>  $number,
                        'all_money'         =>  $all_money,

                        'tax_money'         =>  $tax_money,//税
                        'money_tax_price'   =>  $money_tax_price,//价税合计

                        'preferential_rate' =>  (float)$params['preferential_rate'],
                        'preferential_money'=>  (float)$params['preferential_money'],
                        'real_money'        =>  (float)$params['real_money'],
                        'purchase_money'    =>  (float)$params['costs'],
                        'note'              =>  $params['note'],
                        'preparation_id'    =>  $admin['id'],
                        'operation_id'      =>  $admin['id'],

                        'arrears'           =>  $params['arrears'],
                        'all_arrears'       =>  $params['add_arrears'],

                        'pay_status'        =>  isset($pay_status)?$pay_status:'0',
                        'pay_money'         =>  isset($pay_money)?$pay_money:0,
                        'account'           =>  isset($pay_status)?$params['account']:0,
                        'cost_data'         =>  isset($params['cost_data'])?$params['cost_data']:null,
                        'updatetime'        => time(),
                    ];
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }
                $info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->column('id');

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
                                'type'          => '5',
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
                    Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'5'])->delete();
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    if(!empty($info_id)){
                        Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$info_id]])->delete();
                    }

                    $purchase_exchange_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$old_purchase_code])->column('id');
                    Db::name('kuerp_goods_serial')->where(['sale_id'=>['in',$purchase_exchange_id],'status'=>'3'])->update([
                        'status'=>'1',
                    ]);
                    Db::name('kuerp_goods_batch_sale_info')->where(['purchase_info_id'=>['in',$purchase_exchange_id]])->delete();

                    Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->delete();

                    foreach ($data as $data_k=>$data_v){
                        $goods_serial = $data_v['serial_code'];
                        unset($data_v['serial_code']);

                        $goods_batch_info = $data_v['goods_batch_info'];
                        unset($data_v['goods_batch_info']);

                        $purchase_id = Db::name('kuerp_goods_sales_info')->insertGetId($data_v);

                        //批次
                        if($goods_batch_info){
                            $goods_batch_info['purchase_info_id'] = $purchase_id;
                            Db::name('kuerp_goods_batch_sale_info')->insert($goods_batch_info);
                        }

                        if(!empty($goods_serial)){
                            Db::name('kuerp_goods_serial')->where(['serial'=>['in',$goods_serial]])->update([
                                'status'=>'3',
                                'sale_id' => $purchase_id,
                                'sale_code' => $data_v['purchase_code']
                            ]);
                        }

                        if(!empty($data_v['order_info_id'])){
                            Db::name('kuerp_goods_saleord_info')->insert([
                                'order_id'=>$data_v['order_info_id'],
                                'purchase_id'=>$purchase_id,
                                'number'=>$data_v['number'],
                                'money'=>$data_v['pay_money'],
                                'all_money'=>$data_v['all_money'],
                                'tax_rate'=>$data_v['tax_rate'],
                                'tax_money'=>$data_v['tax_money'],
                                'money_tax_price'=>$data_v['money_tax_price']
                            ]);
                        }
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
        // 隐藏金额字段（仓库人员不可见）
        $row->hidden($this->hiddenAmountFields);
        $row = $this->appendDeliveryModeText($row);
        $this->view->assign("row", $row);

        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        // 明细隐藏金额字段
        $detailHiddenFields = ['all_money','unit_price','cost_price','tax_included','discount','dis_money','tax_rate','tax_money','money_tax_price','preferential_rate','preferential_money','real_money','purchase_money','customers_money','pay_money'];
        // 批量查询动态库存：收集所有商品的goods_id，一次查询全部可用库存
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsIdMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id', 'goods_code') : [];
        $stockItems = [];
        foreach ($purchase_info as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? $goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        // 批量查询库位层级（完整路径如"A-1库"）
        $locationMap = [];
        $goodsIdToLocId = []; // goods_id => location_id
        if ($goodsCodes) {
            $goodsLocData = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->where('location_id', '>', 0)->column('location_id', 'id');
            foreach ($goodsLocData as $gid => $lid) {
                $goodsIdToLocId[(int)$gid] = (int)$lid;
            }
            $locIds = array_unique(array_values($goodsIdToLocId));
            if ($locIds) {
                $locList = Db::name('kuerp_goods_location')->where(['id' => ['in', $locIds], 'status' => '1'])->field('id,location_name,path')->select();
                $allPids = [];
                foreach ($locList as $loc) {
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            if ((int)$pid > 0) $allPids[] = (int)$pid;
                        }
                    }
                }
                $ancestorMap = [];
                if ($allPids) {
                    $ancestorMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($allPids)]])->column('location_name', 'id');
                }
                foreach ($locList as $loc) {
                    $names = [];
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            $pid = (int)$pid;
                            if ($pid > 0 && isset($ancestorMap[$pid])) $names[] = $ancestorMap[$pid];
                        }
                    }
                    $names[] = $loc['location_name'];
                    $locationMap[$loc['id']] = implode('-', $names);
                }
            }
        }

        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['serial'] = $purchase_info_v['period'];
            $purchase_info[$purchase_info_k]['unit'] = json_decode($this->getUnit($purchase_info_v['goods_code'], $purchase_info_v['stores_id'], $purchase_info_v['goods_attr']),true);
            $gid = isset($goodsIdMap[$purchase_info_v['goods_code']]) ? $goodsIdMap[$purchase_info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$purchase_info_v['stores_id'] . '-' . (int)$purchase_info_v['goods_attr'];
            $purchase_info[$purchase_info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
            $locId = isset($goodsIdToLocId[$gid]) ? $goodsIdToLocId[$gid] : 0;
            $purchase_info[$purchase_info_k]['location_name'] = ($locId && isset($locationMap[$locId])) ? $locationMap[$locId] : '';
            foreach ($detailHiddenFields as $f) {
                unset($purchase_info[$purchase_info_k][$f]);
            }
        }
        $this->view->assign("purchase_info", $purchase_info);

        $give_account = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->value('need_receive_account');
        if($row['examine_status']==1){
            $this->assignconfig("give_account", $give_account-$row['arrears']);
            $this->view->assign("give_account", (float)$give_account);
        }else{
            $this->assignconfig("give_account", $give_account);
            $this->view->assign("give_account", (float)$give_account+$row['arrears']);
        }

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'5'])->column('enclosure_id');
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
        $this->assignconfig("examine_status", $row['examine_status']);
        $this->assignconfig("suppliersList", $this->model->getSuppliersList());
        $this->assignconfig("expendTypeList", $this->model->getExpendTypeList());

        // 获取审核人姓名
        $examine_name = '';
        if (!empty($row['examine_id'])) {
            $examine_name = Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname');
        }
        $this->view->assign("examine_name", $examine_name);

        // 获取来源销货订单的商品列表
        $order_goods_list = [];
        if (!empty($row['purchase_order_id'])) {
            $order_goods_list = Db::name('kuerp_goods_sales_order_info')
                ->where(['purchase_code' => $row['purchase_order_id']])
                ->select();
            $detailHiddenFields = ['all_money','unit_price','cost_price','tax_included','discount','dis_money','tax_rate','tax_money','money_tax_price','preferential_rate','preferential_money','real_money','purchase_money','customers_money','pay_money'];
            foreach ($order_goods_list as $k => $v) {
                $order_goods_list[$k]['goods_name'] = Db::name('kuerp_goods')
                    ->where(['goods_code' => $v['goods_code']])
                    ->value('goods_name');
                $order_goods_list[$k]['unit_name'] = Db::name('kuerp_unit')
                    ->where(['id' => $v['goods_unit']])
                    ->value('name');
                foreach ($detailHiddenFields as $f) {
                    unset($order_goods_list[$k][$f]);
                }
            }
        }
        $this->view->assign("order_goods_list", $order_goods_list);

        return $this->view->fetch();
    }

    //对单/反对单
    public function counter($ids = null,$counter=1){
        if($counter==1){
            $list = $this->model->where(['id'=>['in',$ids],'check_status'=>'0'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                $this->error('请选择需要对单的数据');
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>['in',$ids],'check_status'=>'0'])->update(['check_status'=>1,'operation_id'=>$admin['id']]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                $this->success(sizeof($list).'条数据对单成功');
            }else{
                $this->error('对单失败');
            }
        }else{
            $list = $this->model->where(['id'=>['in',$ids],'check_status'=>'1'])->select();
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
            }
            if(empty($list)){
                $this->error('请选择需要反对单的数据');
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>['in',$ids],'check_status'=>'1'])->update(['check_status'=>'0','operation_id'=>$admin['id']]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if(isset($result) && $result !== false){
                $this->success(sizeof($list).'条数据反对单成功');
            }else{
                $this->error('反对单失败');
            }
        }
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
            $set_discount = Db::name('kuerp_sets')->where(['name'=>'discount'])->value('is_checked');

            foreach ($list as $list_k=>$list_v){
                if($list_v['check_status']==1){
                    $this->error('不能反审核已对单的数据');
                }
            }
            $purchase_code = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->column('purchase_code');
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'系统错误'];
                }
                $this->error('请选择需要审核的数据');
            }
            // 审核仅设置审核状态，不做库存变动（库存变动由销货出库操作完成）
            $admin = Session::get('admin');
            $examine_ids = [];
            foreach ($list as $list_v){
                $examine_ids[] = $list_v['id'];
            }
            Db::startTrans();
            try {
                $this->model->where(['id'=>['in',$examine_ids],'examine_status'=>'0'])->update([
                    'examine_status' => 1,
                    'examine_id'     => $admin['id'],
                    'examine_time'   => time(),
                    'operation_id'   => $admin['id'],
                ]);
                Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$purchase_code]])->update([
                    'examine_status' => 1,
                    'examine_id'     => $admin['id'],
                    'examine_time'   => time(),
                ]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>$e->getMessage()];
                }
                $this->error($e->getMessage());
            }
            if($examine_set!=1){
                return ['status'=>1,'msg'=>'审核成功'];
            }
            $this->success('审核成功（库存变动请通过销货出库操作完成）');
            $account_list = [];
            foreach ($account_list_obj as $account_list_obj_k=>$account_list_obj_v){
                $account_list[$account_list_obj_v['id']] = $account_list_obj_v;
            }
            $examine_ids = [];
            $accountData = [];
            $arrears = [];
            $log = [];
            $payment_log = [];$invoicing = [];
            foreach ($list as $list_k=>$list_v){
                $examine_ids[] = $list_v['id'];
                if($list_v['arrears']>0){
                    $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->field('id,need_receive_account')->find();
                    if($suppliers){
                        $status = false;
                        foreach ($arrears as $arrears_k=>$arrears_v){
                            if($arrears_v['id']==$suppliers['id']){
                                $arrears[$arrears_k]['need_receive_account'] = $list_v['arrears']+$arrears_v['need_receive_account'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $arrears[] = [
                                'id'  => $suppliers['id'],
                                'need_receive_account'  => $list_v['arrears']+$suppliers['need_receive_account'],
                            ];
                        }
                    }
                }
                if($list_v['pay_money']>0){
                    $accountData[] = [
                        'balance'  => $list_v['pay_money'],
                        'account'  => $list_v['account'],
                    ];
                    $log[] = [
                        'account_coding'    => $account_list[$list_v['account']]['account_coding'],
                        'account_name'      => $account_list[$list_v['account']]['account_name'],
                        'balance'           => $account_list[$list_v['account']]['balance']-$list_v['pay_money'],
                        'income'            => $list_v['pay_money'],
                        'pay_time'          => $list_v['purchase_time'],
                        'bill_code'         => $list_v['purchase_code'],
                        'business_type'     => 5,
                        'customers_id'      => $list_v['customers_id'],
                        'remark'            => $list_v['note'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }
                $payment_log[] = [
                    'customers_id'   => $list_v['customers_id'],
                    'sale_user'      => $list_v['sale_user'],
                    'business_type'  => 1,
                    'bill_time'      => $list_v['purchase_time'],
                    'bill_code'      => $list_v['purchase_code'],
                    'bill_codes'     => $list_v['purchase_code'],
                    'purchase_money' => $list_v['all_money'],
                    'discount_money' => $list_v['preferential_money'],
                    'real_money'     => $list_v['real_money'],
                    'money_tax_price'=> $list_v['money_tax_price'],
                    'pay_money'      => $list_v['pay_money'],
                    'give_money'     => $list_v['arrears'],
                    'reamrk'         => $list_v['note'],
                    'createtime'     => time(),
                ];
                if($set_discount==1){
                    $money = $list_v['real_money'];
                }else{
                    $money = $list_v['money_tax_price'];
                }
                $invoicing[] = [
                    'customers_id'   => $list_v['customers_id'],
                    'status'         => $list_v['pay_status'],
                    'bill_time'      => $list_v['purchase_time'],
                    'bill_code'      => $list_v['purchase_code'],
                    'bill_codes'     => $list_v['purchase_code'],
                    'money'          => $list_v['real_money'],
                    'tax'            => $list_v['tax_money'],
                    'real_money'     => $money,
                    'pay_money'      => 0,
                    'give_money'     => $money,
                    'createtime'     => time(),
                ];
            }
            $editstock = [];$addstock = [];$goods_stock = [];
            $info_list = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$purchase_code]])->select();
            foreach ($info_list as $info_list_k=>$info_list_v){
                try {
                    unset($info_list[$info_list_k]['period']);
                } catch (Exception $e) {
                }
            }

            $purchase_info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$purchase_code]])->column('id');

            $purchase_info = [];
            $goods_stock_log = [];//收发详情
            $serial_log = [];//序列号跟踪
            foreach ($info_list as $list_k=>$list_v){
                //序列号跟踪
                $goods_serial = Db::name('kuerp_goods_serial')->where(['sale_id'=>$list_v['id']])->field('goods_id,serial,stores_id,attribute_id')->select();
                foreach ($goods_serial as $goods_serial_k=>$goods_serial_v){
                    $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'=>$goods_serial_v['goods_id'],
                        'stores_id'=>$goods_serial_v['stores_id'],
                        'attribute_id'=>$goods_serial_v['attribute_id'],
                        'serial'=>$goods_serial_v['serial'],
                        'business_type'=>'4',
                        'store_status'=>'2',
                        'remarks' => $list_v['remark'],
                        'purchase_id'=>$list_v['id'],
                        'customer_id'=>$list_v['customers_id'],
                        'purchase_code'=>$list_v['purchase_code'],
                        'purchase_time' => $list_v['purchase_time'],
                        'createtime'  => time(),
                        'updatetime'  => time(),
                    ]);
                }
                //序列号跟踪

                $info_list[$list_k]['sales_order_id']    = $list_v['id'];
                $info_list[$list_k]['createtime']        = time();
                $info_list[$list_k]['updatetime']        = time();
                $stock_number = $list_v['basic_nmber'];
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('id,attribute_id,inventory,unit_id')->find();
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
                //商品库存
                $where = [];
                $where['goods_code'] = $list_v['goods_code'];
                $where['stores_id'] = $list_v['stores_id'];
                if($list_v['goods_attr']>0){
                    $goods_attr = $list_v['goods_attr'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }

                //获取成本单价
                $balance_cost=0;$balance_unit=0;
                $goodsInfo = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($list_v['goods_code'],$list_v['stores_id'],$list_v['goods_attr']),true);
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

                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] -= $stock_number;
                            $status = true;
                            $balance_cost   = (round($balance_unit*$stock_number,4));
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']-$stock_number,4))
                        ];
                        $balance_cost   = (round($balance_unit*$stock_number,4));
                    }
                }else{
                    $store = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->value('name');
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'商品'.$store.'库存不足'];
                    }
                    $this->error('商品'.$store.'库存不足');
                }
                $purchase_info[] = $list_v['id'];

                //收发详情
                if($goods){
                    $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code' => $list_v['purchase_code'],
                        'purchase_id'   => $list_v['id'],
                        'purchase_time' => $list_v['purchase_time'],
                        'business_type' => '4',
                        'customer_id'   => $list_v['customers_id'],
                        'goods_id'      => $goods['id'],
                        'goods_code'    => $list_v['goods_code'],
                        'attribute_id'  => (float)$list_v['goods_attr'],
                        'goods_unit'    => (float)$list_v['goods_unit'],
                        'stores_id'     => (float)$list_v['stores_id'],
                        'remark'        => $list_v['remark'],
                        'out_number'    => (float)$list_v['number'],
                        'out_basics'    => (float)$list_v['basic_nmber'],
                        'out_unit'      => $balance_unit,
                        'out_cost'      => $balance_cost,
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }
                //收发详情结束
            }
            foreach ($editstock as $editstock_k=>$editstock_v){
                if($editstock_v['stock']<0){
                    $store = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->value('name');
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>$goods['goods_name'].'库存不足,操作失败'];
                    }
                    $this->error($store.'库存不足,操作失败');
                }
            }

            $order_count = [];
            $code_id = Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->column('order_id');
            $purchase_order_info = Db::name('kuerp_goods_sales_order_info')
                ->field('id,purchase_code')
                ->where(['id'=>['in',$code_id]])
                ->select();
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $purord_info = Db::name('kuerp_goods_saleord_info')
                    ->where(['order_id'=>$purchase_order_info_v['id'],'purchase_id'=>['in',$purchase_info]])->field('sum(number) as number,sum(money) as money,sum(all_money) as all_money')->find();
                $purchase_order_info[$purchase_order_info_k]['pay_count'] = isset($purord_info['number'])?$purord_info['number']:0;
                $purchase_order_info[$purchase_order_info_k]['pay_money'] = isset($purord_info['money'])?$purord_info['money']:0;
                $purchase_order_info[$purchase_order_info_k]['all_money'] = isset($purord_info['all_money'])?$purord_info['all_money']:0;
            }
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $status = false;
                foreach ($order_count as $order_count_k=>$order_count_v){
                    if($order_count_v['purchase_code']==$purchase_order_info_v['purchase_code']){
                        $order_count[$order_count_k]['store_number'] += $purchase_order_info_v['pay_count'];
                        $status = true;
                    }
                }
                if(!$status){
                    $order_count[] = [
                        'purchase_code'  => $purchase_order_info_v['purchase_code'],
                        'store_number'  => $purchase_order_info_v['pay_count'],
                    ];
                }
            }
            //订单状态只由销货单的实际出库数量决定，这里只补订单id
            if(!empty($order_count)){
                foreach ($order_count as $order_count_k=>$order_count_v){
                    $number = Db::name('kuerp_goods_sales_order')->where(['purchase_code'=>$order_count_v['purchase_code']])->field('id')->find();
                    if(empty($number)){
                        unset($order_count[$order_count_k]);
                        continue;
                    }
                    $order_count[$order_count_k]['id'] = $number['id'];
                }
            }

            //批次库存数据
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
            $goods_serial = Db::name('kuerp_goods_sales_info')->where(['id'=>['in',$purchase_info_id]])->select();

            $goods_batch_add = [];$goods_batch_edit = [];$goods_batch_log = [];
            foreach ($goods_serial as $k=>$v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v['goods_code']])->field('id,goods_name,attribute_id,inventory,unit_id,batch')->find();
                if($goods['batch']==1){
                    //先通过单位计算需修改多单位商品的库存数量
                    if($goods['unit_id']==$v['goods_unit']){
                        $stock_number = $v['number'];
                    }else{
                        $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$v['goods_unit']])->value('advance_rate');
                        if(empty($unit_conversion)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                            }
                            $this->error('商品单位信息查询失败');
                        }else{
                            $stock_number = round(($v['number']*$unit_conversion),$number_decimal);
                        }
                    }

                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if($v['goods_attr']){
                        $where['attribute_id'] = $v['goods_attr'];
                    }
                    $where['stores_id'] = $v['stores_id'];
                    $where['batch_id'] = $v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $status = false;
                        foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                            if($goods_batch_add_v['batch_id']==$v['batch_id'] && $goods_batch_add_v['goods_id']==$v['goods_id'] && $goods_batch_add_v['attribute_id']==$v['attribute_id'] && $goods_batch_add_v['stores_id']==$v['stores_id']){
                                $goods_batch_add[$goods_batch_add_k]['stock'] -= $stock_number;
                                $status = true;
                            }
                        }
                        if(!$status){
                            $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                                'batch_id' => $v['batch_id'],
                                'goods_id' => $goods['id'],
                                'goods_code' => $v['goods_code'],
                                'attribute_id' => $v['goods_attr'],
                                'stores_id' => $v['stores_id'],
                                'stock' => $stock_number,
                                'createtime' => time(),
                                'updatetime' => time(),
                            ]);
                        }
                    }else{
                        if(isset($goods_batch_edit[$goods_batch_stock['id']])){
                            $goods_batch_edit[$goods_batch_stock['id']] -= $stock_number;
                        }else{
                            $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock']-$stock_number;
                        }
                    }
                    foreach ($goods_batch_edit as $k2=>$v2){
                        if($v2<0){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品'.$goods['goods_name'].'库存不足'];
                            }
                            $this->error('商品'.$goods['goods_name'].'库存不足');
                        }
                    }

                    //商品批次跟踪
                    $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                        'goods_id'      => $goods['id'],
                        'goods_unit'    => $v['goods_unit'],
                        'stores_id'     => $v['stores_id'],
                        'batch_id'      => (float)$v['batch_id'],
                        'number'        => (float)$v['number'],
                        'purchase_code' => $v['purchase_code'],
                        'purchase_time' => $v['purchase_time'],
                        'business_type' => '4',
                        'remarks'       => $v['remark'],
                        'order_id'      => $v['id'],
                        'unit_price'    => $v['unit_price'],
                        'tax_included'  => $v['tax_included'],
                        'all_money'     => $v['all_money'],
                        'tax_money'     => $v['tax_money'],
                        'money_tax_price' => $v['money_tax_price'],
                        'customer_id'   => $v['customers_id'],
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }
                //商品批次跟踪结束
            }
            //批次库存数据结束

            //应收账款明细表
            $ex_list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
            $accounts_receivable = [];
            foreach ($ex_list as $ex_list_k=>$ex_list_v){
                $accounts_receivable[] = \addons\kuerp\library\Goods::setDataInfo([
                    'customers_id'      => $ex_list_v['customers_id'],
                    'purchase_time'     => $ex_list_v['purchase_time'],
                    'purchase_code'     => $ex_list_v['purchase_code'],
                    'business_type'     => '6',
                    'receivable'        => $ex_list_v['arrears'],
                    'staff_member_id'   => $ex_list_v['sale_user'],
                    'remark'            => $ex_list_v['note'],
                    'sale_money'        => $ex_list_v['money_tax_price'],
                    'receivable_money'  => round(($ex_list_v['real_money']+$ex_list_v['customers_money']),4),
                    'amount'            => $ex_list_v['preferential_money'],
                    'cost'              => $ex_list_v['customers_money'],
                    'real_money'        => $ex_list_v['pay_money'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);
            }

            $admin = Session::get('admin');

            //采购销售费用
            $cost_data = [];
            foreach ($list as $list_k=>$list_v){
                if(!empty($list_v['cost_data'])){
                    $cost_datas = unserialize($list_v['cost_data']);
                    foreach ($cost_datas as $cost_datas_k=>$cost_datas_v){
                        $list_v['supplyer_code'] = isset($list_v['supplyer_code'])?$list_v['supplyer_code']:'';
                        $cost_data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_time'     => time(),
                            'purchase_code'     => \addons\kuerp\library\Goods::getCodes('',27),
                            'business_type'     => '2',
                            'suppliers_ids'     => isset($list_v['customers_id'])?$list_v['customers_id']:null,
                            'suppliers_id'      => $cost_datas_v['suppliers_id'],
                            'expend_type_id'    => $cost_datas_v['expend_type_id'],
                            'purchase_number'   => $list_v['purchase_code'],
                            'purchase_times'    => $list_v['purchase_time'],
                            'money'             => $cost_datas_v['money'],
                            'pay_money'         => $cost_datas_v['money'],
                            'remark'            => $cost_datas_v['remark'],
                            'preparation_id'    => $admin['id'],
                            'operation_id'      => $admin['id'],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                    }
                }
            }

            //销售利润表
            $profit = [];
            foreach ($list as $list_k=>$list_v){
                $amount = null;
                $amounts = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$list_v['purchase_code']])->sum('dis_money');
                if($amounts>0){
                    $amount = $amounts;
                }
                $profit[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_type' => '1',
                    'purchase_code' => $list_v['purchase_code'],
                    'purchase_time' => $list_v['purchase_time'],
                    'customers_id'  => $list_v['customers_id'],
                    'sale_user'     => $list_v['sale_user'],
                    'preparation_id'=> $list_v['preparation_id'],
                    'examine_id'    => $admin['id'],
                    'number'        => $list_v['number'],
                    'amount'        => $amount,
                    'all_money'     => $list_v['all_money'],
                    'tax_money'     => $list_v['tax_money'],
                    'money_tax_price'=> $list_v['money_tax_price'],
                    'preferential_money'=> $list_v['preferential_money'],
                    'purchase_money'=> $list_v['purchase_money'],
                    'real_money'    => $list_v['real_money'],
                    'customers_money'=> $list_v['customers_money'],
                    'need_money'    => $list_v['real_money']+$list_v['customers_money'],
                    'pay_money'     => $list_v['pay_money'],
                    'remark'        => $list_v['note'],
                ]);
            }

            //核销单源单
            $writeoff_source = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['arrears']>0){
                    $writeoff_source[] = [
                        'purchase_type' => 3,
                        'type' => 1,
                        'customers_id' => $list_v['customers_id'],
                        'purchase_code' => $list_v['purchase_code'],
                        'purchase_time' => $list_v['purchase_time'],
                        'price' => $list_v['real_money'],
                        'money' => $list_v['pay_money'],
                        'write_off' => $list_v['arrears'],
                        'remark' => $list_v['note'],
                    ];
                }
            }

            Db::startTrans();
            try {
                if(!empty($writeoff_source)){
                    Db::name('kuerp_writeoff_source')->insertAll($writeoff_source);
                }

                if(!empty($profit)){
                    Db::name('kuerp_goods_sales_profit')->insertAll($profit);
                }
                if(!empty($cost_data)){
                    Db::name('kuerp_cost')->insertAll($cost_data);
                }
                if(!empty($accounts_receivable)){
                    Db::name('kuerp_accounts_receivable')->insertAll($accounts_receivable);
                }

                //序列号跟踪
                if(!empty($serial_log)){
                    Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                }
                if(!empty($goods_stock_log)){
                    Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                }
                //批次库存数据
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

                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_serial')->where(['sale_id'=>['in',$purchase_info_id]])->update(['store_status'=>'2']);
                    Db::name('kuerp_goods_batch_sale_info')->where(['purchase_info_id'=>['in',$purchase_info_id]])->update(['status'=>'1']);
                }
                if(!empty($order_count)){
                    $goodsSalesOrder = (new \app\admin\model\kuerp\GoodsSalesOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsSalesOrder->where(['id'=>$order_count_v['id']])->inc('store_number',$order_count_v['store_number'])->update();
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsSalesOrder->saveAll($order_count);
                }
                if(!empty($payment_log)){
                    (new \app\admin\model\kuerp\SalesPaymentLog())->saveAll($payment_log);
                }
                if(!empty($invoicing)){
                    (new \app\admin\model\kuerp\SalesInvoicing())->saveAll($invoicing);
                }
                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                if(!empty($log)){
                    Db::name('kuerp_accounts_log')->insertAll($log);
                }
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($arrears)){
                    (new \app\admin\model\kuerp\Customers())->saveAll($arrears);
                }
                Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->update(['status'=>'1','times'=>time()]);
                foreach ($info_list as $info_list_k=>$info_list_v){
                    if(isset($info_list_v['id'])){
                        unset($info_list[$info_list_k]['id']);
                    }
                    if(isset($info_list_v['out_number'])){
                        unset($info_list[$info_list_k]['out_number']);
                    }
                }
                Db::name('kuerp_goods_sales_summary')->insertAll($info_list);
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['id'=>$accountData_v['account']])->inc('balance',$accountData_v['balance'])->update();
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
                $this->success(sizeof($list).'条数据审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('审核失败');
            }
        }else{
            $plist = $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
            if(empty($list)){
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('请选择需要反审核的数据');
            }
            foreach ($list as $list_k=>$list_v){
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($list_v['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                if($list_v['check_status']==1){
                    $this->error('不能反审核已对单的数据');
                }
                // 已有出库记录(出库申请已执行)时不允许反审核：反审核按整单恢复库存，不看 out_number，会超回库存
                $out_total = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$list_v['purchase_code']])->sum('out_number');
                if($out_total > 0){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能反审核已有出库记录的数据'];
                    }
                    $this->error('该销货单已有出库记录（出库申请已执行），不能反审核');
                }
                $purchase_id = Db::name('kuerp_goods_saleexc')->where(['purchase_id'=>$list_v['id'],'type'=>'2'])->column('exchange_id');
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成退货单的数据');
                }
                $purchase_id = Db::name('kuerp_collection_sale')->where(['purchase_number'=>$list_v['purchase_code']])->find();
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成收款单的数据'];
                    }
                    $this->error('不能反审核已生成收款单的数据');
                }
                $purchase_id = Db::name('kuerp_writeoff_info')->where(['purchase_number'=>$list_v['purchase_code']])->find();
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成核销单的数据'];
                    }
                    $this->error('不能反审核已生成核销单的数据');
                }
            }
            $arrears = [];
            foreach ($list as $list_k=>$list_v){
                if($list_v['arrears']>0){
                    $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->field('id,need_receive_account')->find();
                    if($suppliers){
                        $status = false;
                        foreach ($arrears as $arrears_k=>$arrears_v){
                            if($arrears_v['id']==$suppliers['id']){
                                $arrears[$arrears_k]['need_receive_account'] = $arrears_v['need_receive_account']-$list_v['arrears'];
                                $status = true;
                            }
                        }
                        if(!$status){
                            $arrears[] = [
                                'id'  => $suppliers['id'],
                                'need_receive_account'  => $suppliers['need_receive_account']-$list_v['arrears'],
                            ];
                        }
                    }
                }
            }
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $account_list_obj = Db::name('kuerp_accounts')->field('id,account_coding,account_name,balance')->select();
            $account_list = [];
            foreach ($account_list_obj as $account_list_obj_k=>$account_list_obj_v){
                $account_list[$account_list_obj_v['id']] = $account_list_obj_v;
            }

            $log = Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$list],'business_type'=>'5'])->select();
            $accountData = [];
            foreach ($log as $list_k=>$list_v){
                $accountData[] = [
                    'balance'  => $list_v['income'],
                    'account'  => $list_v['account_coding'],
                ];
            }

            $editstock = [];$goods_stock = [];
            $info_list = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$list]])->select();

            $purchase_info_id = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>['in',$list]])->column('id');

            $purchase_info = [];
            foreach ($info_list as $list_k=>$list_v){
                $info_list[$list_k]['createtime']        = time();
                $info_list[$list_k]['updatetime']        = time();
                $stock_number = $list_v['basic_nmber'];
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('id,attribute_id,inventory,goods_name')->find();
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
                $where['goods_code'] = $list_v['goods_code'];
                $where['stores_id'] = $list_v['stores_id'];
                if($list_v['goods_attr']>0){
                    $goods_attr = $list_v['goods_attr'];
                    if($goods_attr>0){
                        $where['attribute_id'] = $goods_attr;
                    }
                }
                $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id')->find();
                if($stock){
                    $status = false;
                    foreach ($editstock as $editstock_k=>$editstock_v){
                        if($editstock_v['id']==$stock['id']){
                            $editstock[$editstock_k]['stock'] += $stock_number;
                            $status = true;
                        }
                    }
                    if(!$status){
                        $editstock[] = [
                            'id'=>$stock['id'],
                            'stock'=>(round($stock['stock']+$stock_number,2))
                        ];
                    }
                }else{
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'系统数据错误，操作失败'];
                    }
                    $this->error('系统数据错误,反审核失败');
                }
                $purchase_info[] = $list_v['id'];
            }

            $order_count = [];
            $code_id = Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->column('order_id');
            $purchase_order_info = Db::name('kuerp_goods_sales_order_info')
                ->field('id,purchase_code')
                ->where(['id'=>['in',$code_id]])
                ->select();
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $purord_info = Db::name('kuerp_goods_saleord_info')
                    ->where(['order_id'=>$purchase_order_info_v['id'],'purchase_id'=>['in',$purchase_info]])->field('sum(number) as number,sum(money) as money,sum(all_money) as all_money')->find();
                $purchase_order_info[$purchase_order_info_k]['pay_count'] = isset($purord_info['number'])?$purord_info['number']:0;
                $purchase_order_info[$purchase_order_info_k]['pay_money'] = isset($purord_info['money'])?$purord_info['money']:0;
                $purchase_order_info[$purchase_order_info_k]['all_money'] = isset($purord_info['all_money'])?$purord_info['all_money']:0;
            }
            foreach ($purchase_order_info as $purchase_order_info_k=>$purchase_order_info_v){
                $status = false;
                foreach ($order_count as $order_count_k=>$order_count_v){
                    if($order_count_v['purchase_code']==$purchase_order_info_v['purchase_code']){
                        $order_count[$order_count_k]['store_number'] += $purchase_order_info_v['pay_count'];
                        $status = true;
                    }
                }
                if(!$status){
                    $order_count[] = [
                        'purchase_code'  => $purchase_order_info_v['purchase_code'],
                        'store_number'  => $purchase_order_info_v['pay_count'],
                    ];
                }
            }
            //订单状态只由销货单的实际出库数量决定，这里只补订单id
            if(!empty($order_count)){
                foreach ($order_count as $order_count_k=>$order_count_v){
                    $number = Db::name('kuerp_goods_sales_order')->where(['purchase_code'=>$order_count_v['purchase_code']])->field('id')->find();
                    if(empty($number)){
                        unset($order_count[$order_count_k]);
                        continue;
                    }
                    $order_count[$order_count_k]['id'] = $number['id'];
                }
            }

            //批次库存数据
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
            $goods_serial = Db::name('kuerp_goods_sales_info')->where(['id'=>['in',$purchase_info_id]])->select();

            $goods_batch_add = [];$goods_batch_edit = [];
            foreach ($goods_serial as $k=>$v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v['goods_code']])->field('id,goods_name,attribute_id,inventory,unit_id,batch')->find();
                if($goods['batch']==1){
                    //先通过单位计算需修改多单位商品的库存数量
                    if($goods['unit_id']==$v['goods_unit']){
                        $stock_number = $v['number'];
                    }else{
                        $unit_conversion = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$v['goods_unit']])->value('advance_rate');
                        if(empty($unit_conversion)){
                            if($examine_set!=1){
                                return ['status'=>0,'msg'=>'商品单位信息查询失败'];
                            }
                            $this->error('商品单位信息查询失败');
                        }else{
                            $stock_number = round(($v['number']*$unit_conversion),$number_decimal);
                        }
                    }

                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    if($v['goods_attr']){
                        $where['attribute_id'] = $v['goods_attr'];
                    }
                    $where['stores_id'] = $v['stores_id'];
                    $where['batch_id'] = $v['batch_id'];
                    $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($where)->find();
                    if(empty($goods_batch_stock)){
                        $status = false;
                        foreach ($goods_batch_add as $goods_batch_add_k=>$goods_batch_add_v){
                            if($goods_batch_add_v['batch_id']==$v['batch_id'] && $goods_batch_add_v['goods_id']==$goods['id'] && $goods_batch_add_v['attribute_id']==$v['attribute_id'] && $goods_batch_add_v['stores_id']==$v['stores_id']){
                                $goods_batch_add[$goods_batch_add_k]['stock'] += $stock_number;
                                $status = true;
                            }
                        }
                        if(!$status){
                            $goods_batch_add[] = \addons\kuerp\library\Goods::setDataInfo([
                                'batch_id' => $v['batch_id'],
                                'goods_id' => $goods['id'],
                                'goods_code' => $v['goods_code'],
                                'attribute_id' => $v['goods_attr'],
                                'stores_id' => $v['stores_id'],
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
                }
            }
            //批次库存数据结束

            //应收账款明细表
            $ex_list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->column('purchase_code');

            $admin = Session::get('admin');
            Db::startTrans();
            try {
                //销售利润表
                Db::name('kuerp_writeoff_source')->where(['purchase_code'=>['in',$list],'purchase_type'=>'3','type'=>'1'])->delete();

                foreach ($plist as $plist_k=>$plist_v){
                    Db::name('kuerp_goods_sales_profit')->where(['purchase_type'=>'1','purchase_code'=>$plist_v['purchase_code']])->delete();
                }
                Db::name('kuerp_cost')->where(['business_type'=>'2','purchase_number'=>['in',$ex_list]])->delete();
                Db::name('kuerp_accounts_receivable')->where(['business_type'=>'6','purchase_code'=>['in',$ex_list]])->delete();

                //序列号跟踪
                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_serial_log')->where(['business_type'=>'4','purchase_id'=>['in',$purchase_info_id]])->delete();
                }
                //收发详情
                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_log')->where(['business_type'=>'4','purchase_id'=>['in',$purchase_info_id]])->delete();
                }
                //批次库存数据
                //删除批次跟踪
                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_batch_log')->where(['business_type'=>'4','order_id'=>['in',$purchase_info_id]])->delete();
                }
                if(!empty($goods_batch_edit)){
                    foreach ($goods_batch_edit as $k=>$v){
                        Db::name('kuerp_goods_batch_stock')->where(['id'=>$k])->update(['stock'=>$v]);
                    }
                }
                //批次库存数据结束

                if(!empty($purchase_info_id)){
                    Db::name('kuerp_goods_serial')->where(['sale_id'=>['in',$purchase_info_id]])->update(['store_status'=>'1']);
                    Db::name('kuerp_goods_batch_sale_info')->where(['purchase_info_id'=>['in',$purchase_info_id]])->update(['status'=>'0']);
                }
                if(!empty($order_count)){
                    $goodsSalesOrder = (new \app\admin\model\kuerp\GoodsSalesOrder());
                    foreach ($order_count as $order_count_k=>$order_count_v){
                        $goodsSalesOrder->where(['id'=>$order_count_v['id']])->dec('store_number',$order_count_v['store_number'])->update();
                        unset($order_count[$order_count_k]['store_number']);
                    }
                    $goodsSalesOrder->saveAll($order_count);
                }
                (new \app\admin\model\kuerp\SalesPaymentLog())->where(['bill_codes'=>['in',$list]])->delete();
                (new \app\admin\model\kuerp\SalesInvoicing())->where(['bill_codes'=>['in',$list]])->delete();

                $result = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->update(['examine_status'=>0,'examine_id'=>0,'examine_time'=>'','operation_id'=>$admin['id']]);
                Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$list],'business_type'=>5])->delete();
                Db::name('kuerp_goods_sales_summary')->where(['purchase_code'=>['in',$list]])->delete();
                if(!empty($accountData)){
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        Db::name('kuerp_accounts')->where(['account_coding'=>$accountData_v['account']])->dec('balance',$accountData_v['balance'])->update();
                    }
                }
                if(!empty($editstock)){
                    (new \app\admin\model\kuerp\GoodsStock())->saveAll($editstock);
                }
                if(!empty($goods_stock)){
                    (new \app\admin\model\kuerp\Goods())->saveAll($goods_stock);
                }
                if(!empty($arrears)){
                    (new \app\admin\model\kuerp\Customers())->saveAll($arrears);
                }
                Db::name('kuerp_goods_saleord_info')->where(['purchase_id'=>['in',$purchase_info]])->update(['status'=>'0']);
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

    //检测可生成退货单
    public function checkPurchase(){
        $order_id = isset($_POST['exchange'])?trim($_POST['exchange']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_sales')->where(['id'=>$order_id])
            ->field('examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        if($row['examine_status']!=1){
            $this->error('此采购单未审核，不可生成退货单');
        }
        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_saleexc_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'2'])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此销货单已全部生成退货单');
        }
        $this->success();
    }
    //获取应付款余额
    public function getSuppliers($customers_id){
        if(empty($customers_id)){
            return 0;
        }else{
            $give_account = Db::name('kuerp_customers')->where(['id'=>$customers_id])->value('need_receive_account');
            return (float)$give_account;
        }
    }
    //获取单位信息
    public function getUnits($goods_code='',$store=''){
        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->field('id,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,warehousing,warehouse,goods_specification_id,vendor_name,packaging')->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = $_POST['goods_id'];
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])->field('unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,warehousing,warehouse,goods_specification_id,vendor_name,packaging')->find();
        }
        if(empty($row)){
            //商品档案已删除的孤儿单据行：统一返回JSON串，避免调用方 json_decode() 收到数组报错
            return json_encode([]);
        }
        if($store){
            $stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$row['goods_code'],'stores_id'=>$store])->value('stock');
        }else{
            $stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$row['goods_code'],'stores_id'=>$row['manager_warehouse_id']])->value('stock');
        }
        if($row['more_unit']==1){
            $unit_ids_arr = Db::name('kuerp_unit_conversion')->where(['id'=>$row['more_unit_id']])->field('main_unit,vice_unit,advance_rate')->find();
            $unit_ids = [$unit_ids_arr['main_unit'],$unit_ids_arr['vice_unit']];
            $goods_price = Db::name('kuerp_goods_price')->where(['unit'=>['in',$unit_ids],'goods_id'=>$goods_id])->select();
        }else{
            $unit_ids = [$row['unit_id']];
        }
        $unit = Db::name('kuerp_unit')->where(['id'=>['in',$unit_ids]])->field('id,name')->select();
        foreach ($unit as $unit_k=>$unit_v){
            if(isset($goods_price)){
                foreach ($goods_price as $goods_price_k=>$goods_price_v){
                    if($unit_v['id']==$goods_price_v['unit']){
                        $unit[$unit_k]['is_default'] = $goods_price_v['is_default'];
                        if($unit_v['id']==$unit_ids_arr['vice_unit']){
                            $unit[$unit_k]['purchase'] = round(($goods_price_v['purchase']/$unit_ids_arr['advance_rate']),2);
                            $unit[$unit_k]['basic_nmber'] = $unit_ids_arr['advance_rate'];
                        }else{
                            $unit[$unit_k]['purchase'] = $goods_price_v['purchase'];
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $row['purchase'];
                }
            }
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
                    $data[] = $unit_v;
                }
            }
        }
        return json_encode($data);
    }

    //获取单位价格信息
    public function getUnit($goods_code='',$store='',$attr='',$customers_id=''){
        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');

        if($goods_code){
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])
                ->field('market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse,goods_specification_id,vendor_name,packaging')
                ->find();
            $goods_id = isset($row['id'])?$row['id']:0;
        }else{
            $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:0;
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_id])
                ->field('market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,warehousing,warehouse,goods_specification_id,vendor_name,packaging')
                ->find();
        }
        if(empty($row)){
            //商品档案已删除的孤儿单据行：统一返回JSON串，避免调用方 json_decode() 收到数组报错
            return json_encode(['unit'=>[],'attr'=>[],'goods'=>[]]);
        }

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

        $field = 'market_sell_price';
        if($customers_id){
            $customers = Db::name('kuerp_customers')->where(['id'=>$customers_id])->find();
            if($customers){
                switch ($customers['customerlevel_id']){
                    case 1:
                        $field = 'market_sell_price';
                        break;
                    case 2://批发价已下线，批发客户按零售价取价
                        $field = 'market_sell_price';
                        break;
                    case 3:
                        $field = 'vip_price';
                        break;
                    case 4:
                        $field = 'discount';
                        break;
                    case 5:
                        $field = 'discount2';
                        break;
                    default:
                        break;
                }
            }
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
            //$attribute_id = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->value('attribute_id');
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
                        if($field=='discount' || $field=='discount2'){
                            $unit[$unit_k]['purchase'] = round( ($goods_price_v['market_sell_price']*$goods_price_v[$field]/100),$price_decimal );
                        }else{
                            $unit[$unit_k]['purchase'] = $goods_price_v[$field];
                        }
                        if($goods_price_v['is_default']!=1){
                            $unit[$unit_k]['basic_nmber'] = $goods_price_v['advance_rate'];
                        }else{
                            $unit[$unit_k]['basic_nmber'] = 1;
                        }
                    }
                }
            }else{
                if($row['auxiliary']==1 && !empty($attr)){
                    $purchase_data = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_id,'id'=>$attr])->find();
                    if($field=='discount' || $field=='discount2'){
                        $purchase = round( ($purchase_data['market_sell_price']*$purchase_data[$field]/100),$price_decimal );
                    }else{
                        $purchase = $purchase_data[$field];
                    }
                }else{
                    if($field=='discount' || $field=='discount2'){
                        $purchase = round( ($row['market_sell_price']*$row[$field]/100),$price_decimal );
                    }else{
                        $purchase = $row[$field];
                    }
                }
                if($unit_v['id']==$row['unit_id']){
                    $unit[$unit_k]['is_default'] = 1;
                    $unit[$unit_k]['basic_nmber'] = 1;
                    $unit[$unit_k]['purchase'] = $purchase;
                }
            }
            $unit[$unit_k]['purchase'] = isset($unit[$unit_k]['purchase'])?$unit[$unit_k]['purchase']:0;
            $unit[$unit_k]['basic_nmber'] = isset($unit[$unit_k]['basic_nmber'])?$unit[$unit_k]['basic_nmber']:1;
            $unit[$unit_k]['is_default'] = isset($unit[$unit_k]['is_default'])?$unit[$unit_k]['is_default']:2;
        }

        $set= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        foreach ($unit as $unit_k=>$unit_v){
            $tax = $set['value'];
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1){
                $unit[$unit_k]['tax'] = 1;
                $unit[$unit_k]['tax_rate'] = $tax;
            }else{
                $unit[$unit_k]['tax'] = 0;
                $unit[$unit_k]['tax_rate'] = 0;
            }
            if(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes==1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']/(100+$tax)*100),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']),4);
            }elseif(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes!=1){
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*(100+$tax)/100),4);
            }else{
                $unit[$unit_k]['purchase'] = round(($unit_v['purchase']),4);
                $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']),4);
            }
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
                    $unit_v['tax_money'] = round(($unit_v['tax_included']-$unit_v['purchase']),4);
                    $data[] = $unit_v;
                }
            }
        }
        // 仓库人员无需看到金额，清空价格相关字段
        foreach ($data as $k => $v) {
            $data[$k]['purchase'] = 0;
            $data[$k]['tax'] = 0;
            $data[$k]['tax_rate'] = 0;
            $data[$k]['tax_included'] = 0;
            $data[$k]['tax_money'] = 0;
        }
        if (isset($row['purchase'])) $row['purchase'] = 0;
        return json_encode(['unit'=>$data,'attr'=>$goods_attr,'goods'=>$row]);
    }

    public function formatInfo2(){
        $data = input();

        $row = Db::name('kuerp_goods')->where(['goods_code'=>$data['goods_code']])->field('batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id')->find();
        if(empty($row)){
            return [];
        }else{
            $goods_unit = $data['goods_unit'];
            if($row['more_unit']==1){//多单位，多属性以单位为准
                $goods_price = Db::name('kuerp_goods_price')->where(['goods_code'=>$data['goods_code'],'unit'=>$goods_unit])->find();
            }elseif ($row['more_unit']!=1 && $row['auxiliary']==1){//单一单位，多属性以属性价格为准
                $goods_price = Db::name('kuerp_goods_attr')->where(['id'=>$data['attr']])->find();
            }
        }

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

        //税率
        $taxes= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        if(!isset($data['tax_rate']) || (isset($data['tax_rate']) && $data['tax_rate']==='')){
            $data['tax_rate'] = $taxes['value'];
        }
        $taxes_status = Db::name('kuerp_sets')->where(['name'=>'taxes'])->value('is_checked');//是否启用税金
        if($taxes_status!=1){
            $data['tax_rate'] = 0;
            $data['tax_money'] = 0;
            $data['tax_included'] = $data['unit_price'];
            $data['money_tax_price'] = $data['all_money'];
        }

        //需格式化字段数组
        $format = [
            'number'=>$number_decimal,
            'allow_stock'=>$number_decimal,
            'basic_nmber'=>$number_decimal,
            'unit_price'=>$price_decimal,
            'tax_included'=>$price_decimal,
            'discount'=>$price_decimal,
            'dis_money'=>$price_decimal,
            'all_money'=>$price_decimal,
            'tax_rate'=>$price_decimal,
            'tax_money'=>$price_decimal,
            'money_tax_price'=>$price_decimal,
            'advance_rate'=>$number_decimal,
        ];
        foreach ($data as $k=>$v){
            if(array_key_exists($k,$format)){
                $data[$k] = (float)$data[$k];
            }
        }

        $field = 'market_sell_price';
        $customers_id = isset($data['customers_id'])?$data['customers_id']:'';
        if($customers_id){
            $customers = Db::name('kuerp_customers')->where(['id'=>$customers_id])->find();
            if($customers){
                switch ($customers['customerlevel_id']){
                    case 1:
                        $field = 'market_sell_price';
                        break;
                    case 2://批发价已下线，批发客户按零售价取价
                        $field = 'market_sell_price';
                        break;
                    case 3:
                        $field = 'vip_price';
                        break;
                    case 4:
                        $field = 'discount';
                        break;
                    case 5:
                        $field = 'discount2';
                        break;
                    default:
                        break;
                }
            }
        }

        $data['tax_included'] = isset($data['tax_included'])?$data['tax_included']:$data['unit_price']*(100+$data['tax_rate'])/100;
        $data['tax_rate'] = isset($data['tax_rate'])?$data['tax_rate']:0;
        $data['discount'] = isset($data['discount'])?$data['discount']:0;
        $data['dis_money'] = isset($data['dis_money'])?$data['dis_money']:0;

        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');//价格含税
        $now_field = $data['now_field'];
        switch ($now_field){
            case 'goods_unit':
                //商品单位
                if($taxes){
                    $data['tax_included'] = isset($goods_price[$field])?$goods_price[$field]:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price[$field])?$goods_price[$field]:$data['unit_price'];//单价
                    $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                }
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'attr':
                //商品属性
                if($taxes){
                    $data['tax_included'] = isset($goods_price[$field])?$goods_price[$field]:$data['tax_included'];//含税单价
                    $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                }else{
                    $data['unit_price'] = isset($goods_price[$field])?$goods_price[$field]:$data['unit_price'];//单价
                    $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                }
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'number':
                //数量
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'unit_price':
                //单价
                $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_included':
                //含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_rate':
                //税率
                $data['tax_included'] = $data['unit_price']*(100+$data['tax_rate'])/100;//含税单价
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'tax_money':
                //税额
                $data['money_tax_price'] = $data['all_money'] + $data['tax_money'];//价税合计减折扣额
                break;
            case 'discount':
                //折扣率
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'dis_money':
                //折扣额
                $data['money_tax_price'] = $data['tax_included']*$data['number'];//价税合计未减折扣额
                $data['discount'] = (100*$data['dis_money']+$data['dis_money']*$data['tax_rate'])/$data['money_tax_price'];//折扣率
                $data['money_tax_price'] = $data['money_tax_price'] - ($data['money_tax_price'] * ($data['discount'])/100);//价税合计减折扣额
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                break;
            case 'all_money':
                //购货金额
                $data['money_tax_price'] = $data['all_money']*(100+$data['tax_rate'])/100;//价税合计
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                $money_tax_price = $data['money_tax_price']*(100+$data['discount'])/100;//价税合计未减折扣额
                $data['tax_included'] = $money_tax_price/$data['number'];//含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                break;
            case 'money_tax_price':
                //价税合计
                $data['all_money'] = $data['money_tax_price']*100/(100+$data['tax_rate']);//购货金额
                $data['tax_money'] = $data['all_money']*$data['tax_rate']/100;//税
                $money_tax_price = $data['money_tax_price']*(100+$data['discount'])/100;//价税合计未减折扣额
                $data['tax_included'] = $money_tax_price/$data['number'];//含税单价
                $data['unit_price'] = $data['tax_included']*100/(100+$data['tax_rate']);//单价
                $data['dis_money'] = $data['money_tax_price']/(100+$data['tax_rate'])*$data['discount'];//折扣额
                break;
            default:
                return [];
                break;
        }

        $data['advance_rate'] = 1;
        if($row['more_unit']==1){
            $advance_rate = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$row['more_unit_id'],'vice_unit'=>$goods_price['unit']])->value('advance_rate');
            if(!empty($advance_rate)){
                $data['advance_rate'] = $advance_rate;
            }
        }
        $data['basic_nmber'] = $data['number']*$data['advance_rate'];
        foreach ($data as $data_k=>$data_v){
            if(array_key_exists($data_k,$format)){
                $data[$data_k] = round($data[$data_k],$format[$data_k]);
            }
        }

        if(isset($data['tax_rate'])){
            $data['tax_money'] = $data['money_tax_price']-$data['all_money'];
        }else{
            $data['tax_money'] = 0;
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
        $goods_batch = Db::name('kuerp_goods_batch')->where(['goods_code'=>$data['goods_code']])->field('id,batch_name,manufacture,guarantee,validity')->select();
        foreach ($goods_batch as $goods_batch_k=>$goods_batch_v){
            $goods_batch[$goods_batch_k]['manufacture'] = date('Y-m-d',$goods_batch_v['manufacture']);
            $goods_batch[$goods_batch_k]['validity'] = date('Y-m-d',$goods_batch_v['validity']);
        }
        return json_encode(['goods'=>$row,'batch'=>$goods_batch]);
    }
    public function getTime($manufacture='',$shelf_life='',$validity=''){
        if(!empty($manufacture) && !empty($shelf_life)){
            $validity = date('Y-m-d',strtotime($manufacture)+$shelf_life*86400);
        }elseif(!empty($validity) && !empty($shelf_life)){
            $manufacture = date('Y-m-d',strtotime($validity)-$shelf_life*86400);
        }elseif(!empty($validity) && !empty($manufacture)){
            $shelf_life = ceil((strtotime($validity)-strtotime($manufacture))/86400);
        }
        return json_encode(['manufacture'=>$manufacture,'shelf_life'=>$shelf_life,'validity'=>$validity]);
    }
    //获取客户收货地址
    public function getReceivingAddress(){
        $customers_id = isset($_POST['customers_id'])?trim($_POST['customers_id']):0;
        if(empty($customers_id)){
            return '';
        }
        $address = Db::name('kuerp_customers')->where(['id'=>$customers_id])->value('getgoods_address');
        return (empty($address)?'':$address);
    }

    // 打印销货单详情
    public function printpage($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 客户名称
        $customer_name = Db::name('kuerp_customers')->where(['id' => $row['customers_id']])->value('customer_name');
        $row['customer_name'] = $customer_name ? $customer_name : '';
        // 制单人
        $row['preparation_name'] = Db::name('admin')->where(['id' => $row['preparation_id']])->value('nickname');
        // 审核人
        $row['examine_name'] = Db::name('admin')->where(['id' => $row['examine_id']])->value('nickname');
        // 销售员
        $row['sale_user_name'] = Db::name('kuerp_staff_member')->where(['id' => $row['sale_user']])->value('name');

        // 隐藏金额字段（仓库人员不可见）
        $row->hidden($this->hiddenAmountFields);

        // 销货单详情
        $purchase_info = Db::name('kuerp_goods_sales_info')->where(['purchase_code' => $row['purchase_code']])->select();
        // 批量查询库位层级
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $locationMap = [];
        $goodsIdToLocId = [];
        if ($goodsCodes) {
            $goodsLocData = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->where('location_id', '>', 0)->column('location_id', 'id');
            foreach ($goodsLocData as $gid => $lid) $goodsIdToLocId[(int)$gid] = (int)$lid;
            $locIds = array_unique(array_values($goodsIdToLocId));
            if ($locIds) {
                $locList = Db::name('kuerp_goods_location')->where(['id' => ['in', $locIds], 'status' => '1'])->field('id,location_name,path')->select();
                $allPids = [];
                foreach ($locList as $loc) {
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            if ((int)$pid > 0) $allPids[] = (int)$pid;
                        }
                    }
                }
                $ancestorMap = [];
                if ($allPids) {
                    $ancestorMap = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($allPids)]])->column('location_name', 'id');
                }
                foreach ($locList as $loc) {
                    $names = [];
                    if ($loc['path']) {
                        foreach (array_filter(explode(',', trim($loc['path'], ','))) as $pid) {
                            $pid = (int)$pid;
                            if ($pid > 0 && isset($ancestorMap[$pid])) $names[] = $ancestorMap[$pid];
                        }
                    }
                    $names[] = $loc['location_name'];
                    $locationMap[$loc['id']] = implode('-', $names);
                }
            }
        }
        $detailHiddenFields = ['all_money','unit_price','cost_price','tax_included','discount','dis_money','tax_rate','tax_money','money_tax_price','preferential_rate','preferential_money','real_money','purchase_money','customers_money','pay_money'];
        foreach ($purchase_info as $k => $v) {
            $goods = Db::name('kuerp_goods')->where(['goods_code' => $v['goods_code']])->field('id,goods_name')->find();
            $purchase_info[$k]['goods_name'] = $goods ? $goods['goods_name'] : '';
            $gid = $goods ? (int)$goods['id'] : 0;
            $locId = isset($goodsIdToLocId[$gid]) ? $goodsIdToLocId[$gid] : 0;
            $purchase_info[$k]['location_name'] = ($locId && isset($locationMap[$locId])) ? $locationMap[$locId] : '';
            $purchase_info[$k]['stores_name'] = Db::name('kuerp_stores')->where(['id' => $v['stores_id']])->value('name');
            $purchase_info[$k]['unit_name'] = Db::name('kuerp_unit')->where(['id' => $v['goods_unit']])->value('name');
            $purchase_info[$k]['out_number'] = isset($v['out_number']) ? (float)$v['out_number'] : 0;
            foreach ($detailHiddenFields as $f) {
                unset($purchase_info[$k][$f]);
            }
        }
        $row = $this->appendDeliveryModeText($row);
        $this->view->assign("row", $row);
        $this->view->assign("purchase_info", $purchase_info);

        // 增加打印次数
        $this->model->where(['id' => $ids])->setInc('print_num');

        return $this->view->fetch();
    }

    // 董事长二次审核
    public function secondExamine($ids = null){
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
            $this->success(sizeof($list).'条数据二次审核成功');
        }else{
            $this->error('二次审核失败');
        }
    }

    /**
     * 出库页面 - 已被"申请出库→出库管理审核"流程取代（UI 入口已删，仅保留方法，权限已收口）
     */
    public function outbound($ids = null)
    {
        list($row, $infoList) = $this->buildOutboundRows($ids);
        $this->view->assign('row', $row);
        $this->view->assign('infoList', $infoList);
        return $this->view->fetch();
    }

    /**
     * 出库申请页面 - 计划部门填写本次各产品出库数量提交申请
     * GET 渲染申请弹窗；POST 落库为待审核申请（fa_kuerp_goods_outbound_apply），由仓库在"出库管理"审核
     */
    /**
     * 解析申请出库时填写的快递信息：
     *   POST express_company[]（快递公司编码） + express_no[]（快递单号），按行成对填写，空行跳过。
     * 返回 [['code'=>..,'company'=>..,'no'=>..], ...]，未填写时返回空数组。
     */
    private function parseApplyExpress()
    {
        $companies = $this->request->post('express_company/a');
        $nos = $this->request->post('express_no/a');
        $companies = is_array($companies) ? $companies : [];
        $nos = is_array($nos) ? $nos : [];
        $count = max(count($companies), count($nos));
        if ($count <= 0) {
            return [];
        }
        $codeMap = Db::name('kuerp_data_express')->column('name', 'code');
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $code = isset($companies[$i]) ? trim((string)$companies[$i]) : '';
            $no = isset($nos[$i]) ? trim((string)$nos[$i]) : '';
            if ($code === '' && $no === '') {
                continue;
            }
            if ($code === '') {
                $this->error('请选择快递公司（快递单号：' . $no . '）');
            }
            if ($no === '') {
                $this->error('请填写快递单号（快递公司：' . (isset($codeMap[$code]) ? $codeMap[$code] : $code) . '）');
            }
            if (!isset($codeMap[$code])) {
                $this->error('快递公司不存在：' . $code);
            }
            $rows[] = ['code' => $code, 'company' => $codeMap[$code], 'no' => $no];
        }
        return $rows;
    }

    public function apply($ids = null)
    {
        if ($this->request->isPost()) {
            $ids = $ids ? $ids : $this->request->post('ids');
            if (empty($ids)) {
                $this->error('参数错误');
            }
            $row = $this->model->where('id', $ids)->find();
            if (!$row) {
                $this->error('销货单不存在');
            }
            if ($row['examine_status'] != 1) {
                $this->error('请先审核单据，再申请出库');
            }
            // 必须先在销货排期完成「排产」，才允许申请出库
            if ((string)$row['production_status'] !== '1') {
                $this->error('该销货单尚未排产，请先在销货排期中完成排产后申请出库');
            }
            // 审核开关未开启时，销货单审核时已变动库存，再走出库会二次扣库存，禁止申请
            $examine_set = Db::name('kuerp_sets')->where(['name' => 'examine'])->value('is_checked');
            if ($examine_set != 1) {
                $this->error('审核模式未开启（审核时已变动库存），无需出库申请');
            }
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
            if ($checkout !== true) {
                $this->error($checkout);
            }
            // 同一销货单允许多条申请并存；每行可申请数量 = 计划数量 - 已出库数量 - 待审核申请占用
            // 已全部出库不允许再申请
            $sumRow = Db::name('kuerp_goods_sales_info')->where(['purchase_code' => $row['purchase_code']])->field('SUM(number) AS total_number, SUM(out_number) AS total_out')->find();
            if ($sumRow && (float)$sumRow['total_out'] >= (float)$sumRow['total_number']) {
                $this->error('该销货单已全部出库，无需再申请');
            }

            $out_data = $this->request->post('out_data/a');
            if (empty($out_data)) {
                $this->error('请填写出库申请数量');
            }
            $infoList = Db::name('kuerp_goods_sales_info')
                ->where(['purchase_code' => $row['purchase_code']])
                ->select();
            $infoMap = [];
            foreach ($infoList as $info) {
                $infoMap[$info['id']] = $info;
            }
            // 各明细行被待审核申请占用的数量
            $pendingQtyMap = $this->pendingApplyQtyMap($row['id']);
            $goodsIdMap = Db::name('kuerp_goods')->column('id', 'goods_code');
            $applyInfoRows = [];
            foreach ($out_data as $info_id => $qty) {
                $qty = (float)$qty;
                if ($qty <= 0) {
                    continue;
                }
                if (!isset($infoMap[$info_id])) {
                    $this->error('出库申请数据有误，请刷新后重试');
                }
                $info = $infoMap[$info_id];
                $existing_out = isset($info['out_number']) ? (float)$info['out_number'] : 0;
                $pending_qty = isset($pendingQtyMap[$info['id']]) ? (float)$pendingQtyMap[$info['id']] : 0;
                $max_out = (float)$info['number'] - $existing_out - $pending_qty;
                if ($max_out <= 0) {
                    $this->error($info['goods_code'] . ' 已无可申请数量（待审核申请已占用 ' . $pending_qty . '）');
                }
                if ($qty > $max_out) {
                    $this->error($info['goods_code'] . ' 申请出库数量不能超过' . $max_out);
                }
                $applyInfoRows[] = [
                    'sales_info_id' => $info['id'],
                    'goods_id'      => isset($goodsIdMap[$info['goods_code']]) ? (int)$goodsIdMap[$info['goods_code']] : 0,
                    'goods_code'    => $info['goods_code'],
                    'stores_id'     => $info['stores_id'],
                    'number'        => $qty,
                    'createtime'    => time(),
                ];
            }
            if (empty($applyInfoRows)) {
                $this->error('请填写出库申请数量');
            }

            // 快递信息（可选）：本次出库可填写多个快递公司 + 快递单号
            $expressRows = $this->parseApplyExpress();
            $expressJson = empty($expressRows) ? null : json_encode($expressRows, JSON_UNESCAPED_UNICODE);

            $admin = Session::get('admin');
            $applyCode = \addons\kuerp\library\Goods::getCodes('', 29);
            if (empty($applyCode)) {
                $this->error('出库申请单号生成失败（缺少编号规则），请检查单据编号设置');
            }
            Db::startTrans();
            try {
                $applyId = Db::name('kuerp_goods_outbound_apply')->insertGetId([
                    'apply_code'     => $applyCode,
                    'sales_id'       => $row['id'],
                    'purchase_code'  => $row['purchase_code'],
                    'customers_id'   => $row['customers_id'],
                    'apply_admin_id' => $admin ? (int)$admin['id'] : 0,
                    'apply_time'     => time(),
                    'status'         => '0',
                    'express_data'   => $expressJson,
                    'createtime'     => time(),
                    'updatetime'     => time(),
                ]);
                foreach ($applyInfoRows as $k => $infoRow) {
                    $applyInfoRows[$k]['apply_id'] = $applyId;
                }
                Db::name('kuerp_goods_outbound_apply_info')->insertAll($applyInfoRows);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('出库申请提交成功，请等待仓库审核');
        }

        // GET：渲染申请弹窗（与出库弹窗同一明细装配）+ 历次申请记录 + 每行待审占用/还可申请数量
        // view=1 为只读查看模式（已全部出库时列表上的「申请记录」按钮用它打开，只看记录、不能提交申请）
        $isView = (bool)$this->request->param('view/d');
        list($row, $infoList) = $this->buildOutboundRows($ids);
        // 未排产不允许发起申请（只读查看历次申请/出库记录不受限）
        if (!$isView && (string)$row['production_status'] !== '1') {
            $this->error('该销货单尚未排产，请先在销货排期中完成排产后申请出库');
        }
        $pendingQtyMap = $this->pendingApplyQtyMap($row['id']);
        foreach ($infoList as $k => $v) {
            $pending_qty = isset($pendingQtyMap[$v['id']]) ? (float)$pendingQtyMap[$v['id']] : 0;
            $infoList[$k]['pending_qty'] = $pending_qty;
            $infoList[$k]['can_apply'] = max(0, (float)$v['number'] - (float)$v['out_number'] - $pending_qty);
        }
        // 是否已全部出库（全部出库后只能看记录，提交申请后端会拒绝）
        $sumRow = Db::name('kuerp_goods_sales_info')->where(['purchase_code' => $row['purchase_code']])->field('SUM(number) AS total_number, SUM(out_number) AS total_out')->find();

        // 历次出库申请记录（该销货单全部申请，最新在前），与销货单详情底部的出库记录同源
        $applyHistory = \app\admin\model\kuerp\GoodsOutboundApply::buildHistory($row['id']);
        $this->view->assign('applyHistory', $applyHistory);
        // 快递公司下拉（申请时填写快递信息用）
        $expressList = Db::name('kuerp_data_express')->field('name,code')->order('weigh ASC')->select();
        $this->view->assign('expressList', $expressList);
        $this->assignconfig('expressList', $expressList);

        $this->view->assign('row', $row);
        $this->view->assign('infoList', $infoList);
        $this->view->assign('isView', $isView);
        $this->view->assign('allOut', ($sumRow && (float)$sumRow['total_out'] >= (float)$sumRow['total_number']) ? 1 : 0);
        return $this->view->fetch('apply');
    }

    /**
     * 各明细行被待审核出库申请占用的数量 {sales_info_id: qty}（同一销货单可并存多条待审申请）
     */
    private function pendingApplyQtyMap($salesId)
    {
        $rows = Db::name('kuerp_goods_outbound_apply_info')->alias('ai')
            ->join('kuerp_goods_outbound_apply a', 'a.id = ai.apply_id')
            ->where(['a.sales_id' => $salesId, 'a.status' => '0'])
            ->field('ai.sales_info_id, SUM(ai.number) AS pending_qty')
            ->group('ai.sales_info_id')
            ->select();
        $map = [];
        foreach ($rows as $v) {
            $map[$v['sales_info_id']] = (float)$v['pending_qty'];
        }
        return $map;
    }

    /**
     * 撤销出库申请 - 参数兼容三种：
     *  apply_id：撤销该条申请（申请弹窗历次记录行使用）
     *  sales_id：撤销该销货单最新一条待审核申请（销货单出库列表行按钮使用）
     *  ids：旧参数，两种语义都可能——先当申请ID（须为待审），查不到/非待审再当销货单ID
     *  ⚠️ 申请ID与销货单ID是两个独立自增序列，直接拿 ids 当申请ID查会撤错单（撞号时），
     *     所以列表按钮改为显式传 sales_id，弹窗传 apply_id。
     */
    public function applyCancel($ids = null)
    {
        $applyId = (int)$this->request->post('apply_id');
        $salesId = (int)$this->request->post('sales_id');
        $ids = $ids ? $ids : $this->request->post('ids');
        if ($applyId) {
            $row = Db::name('kuerp_goods_outbound_apply')->where(['id' => $applyId, 'status' => '0'])->find();
        } elseif ($salesId) {
            $row = Db::name('kuerp_goods_outbound_apply')->where(['sales_id' => $salesId, 'status' => '0'])->order('id', 'DESC')->find();
        } elseif (empty($ids)) {
            $this->error('请选择要操作的数据');
        } else {
            $row = Db::name('kuerp_goods_outbound_apply')->where(['id' => $ids, 'status' => '0'])->find();
            if (!$row) {
                $row = Db::name('kuerp_goods_outbound_apply')->where(['sales_id' => $ids, 'status' => '0'])->order('id', 'DESC')->find();
            }
        }
        if (!$row || $row['status'] != '0') {
            $this->error('该出库申请不存在或不是待审核状态');
        }
        $admin = Session::get('admin');
        $updated = Db::name('kuerp_goods_outbound_apply')
            ->where(['id' => $row['id'], 'status' => '0'])
            ->update([
                'status'           => '3',
                'examine_admin_id' => $admin ? (int)$admin['id'] : 0,
                'examine_time'     => time(),
                'updatetime'       => time(),
            ]);
        if (!$updated) {
            $this->error('该申请已被处理，无法撤销');
        }
        $this->success('已撤销出库申请[' . $row['apply_code'] . ']');
    }

    /**
     * 出库/申请弹窗共用的数据装配：销货单主行 + 明细行（商品/仓库/单位/属性/批次名称、可用库存），并隐藏金额字段
     */
    private function buildOutboundRows($ids)
    {
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('销货单不存在');
        }
        if ($row['examine_status'] != 1) {
            $this->error('请先审核单据，再进行出库操作');
        }

        // 隐藏金额字段
        $row->hidden($this->hiddenAmountFields);
        // 获取客户名称
        $customer_name = Db::name('kuerp_customers')->where('id', $row['customers_id'])->value('customer_name');
        $row['customer_name'] = $customer_name ?: '';
        $row = $this->appendDeliveryModeText($row);
        $this->view->assign('row', $row);

        // 获取明细
        $infoList = Db::name('kuerp_goods_sales_info')
            ->where(['purchase_code' => $row['purchase_code']])
            ->select();

        $storesMap = Db::name('kuerp_stores')->column('name', 'id');
        $unitMap = Db::name('kuerp_unit')->column('name', 'id');
        // 商品名称 / ID / 型号 / 规格2(厂商) / 规格3(封装)，按商品编号索引
        $goodsMap = [];
        $goodsIdMap = [];
        $goodsSpecMap = [];
        $goodsRows = Db::name('kuerp_goods')->field('id,goods_code,goods_name,goods_specification_id,vendor_name,packaging')->select();
        foreach ($goodsRows as $g) {
            $goodsMap[$g['goods_code']] = $g['goods_name'];
            $goodsIdMap[$g['goods_code']] = (int)$g['id'];
            $goodsSpecMap[$g['goods_code']] = $g;
        }

        // 批量查询可用库存（按仓库+商品+属性）
        $stockItems = [];
        foreach ($infoList as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? (int)$goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        $detailHiddenFields = ['all_money','unit_price','cost_price','tax_included','discount','dis_money','tax_rate','tax_money','money_tax_price','preferential_rate','preferential_money','real_money','purchase_money','customers_money','pay_money'];

        foreach ($infoList as $k => $v) {
            $infoList[$k]['goods_name'] = isset($goodsMap[$v['goods_code']]) ? $goodsMap[$v['goods_code']] : '';
            // 型号 / 规格2(厂商) / 规格3(封装)：取商品档案，商品已删除时留空
            $infoList[$k]['goods_specification_id'] = isset($goodsSpecMap[$v['goods_code']]) ? $goodsSpecMap[$v['goods_code']]['goods_specification_id'] : '';
            $infoList[$k]['vendor_name'] = isset($goodsSpecMap[$v['goods_code']]) ? $goodsSpecMap[$v['goods_code']]['vendor_name'] : '';
            $infoList[$k]['packaging'] = isset($goodsSpecMap[$v['goods_code']]) ? $goodsSpecMap[$v['goods_code']]['packaging'] : '';
            $infoList[$k]['stores_name'] = isset($storesMap[$v['stores_id']]) ? $storesMap[$v['stores_id']] : '';
            $infoList[$k]['unit_name'] = isset($unitMap[$v['goods_unit']]) ? $unitMap[$v['goods_unit']] : '';
            $infoList[$k]['out_number'] = isset($v['out_number']) ? (float)$v['out_number'] : 0;
            // 可用库存
            $gid = isset($goodsIdMap[$v['goods_code']]) ? (int)$goodsIdMap[$v['goods_code']] : 0;
            $skey = $gid . '-' . (int)$v['stores_id'] . '-' . (int)$v['goods_attr'];
            $infoList[$k]['allow_stock'] = isset($stockMap[$skey]) ? $stockMap[$skey] : 0;
            // 属性名称
            if (!empty($v['goods_attr'])) {
                $attr = Db::name('kuerp_goods_attr')->where(['id' => $v['goods_attr']])->value('attr_code');
                $infoList[$k]['attr_name'] = $attr ? $attr : '';
            } else {
                $infoList[$k]['attr_name'] = '';
            }
            // 批次名称
            if (!empty($v['batch_id'])) {
                $batch = Db::name('kuerp_goods_batch')->where(['id' => $v['batch_id']])->value('batch_name');
                $infoList[$k]['batch_name'] = $batch ? $batch : '';
            } else {
                $infoList[$k]['batch_name'] = '';
            }
            // 隐藏金额字段
            foreach ($detailHiddenFields as $f) {
                if (isset($infoList[$k][$f])) {
                    unset($infoList[$k][$f]);
                }
            }
        }
        return [$row, $infoList];
    }

    /**
     * 出库前库存校验（仅实际出库时调用，下单不做校验）
     * 同一 商品+仓库+属性 的多行合并汇总后再比较，避免同一份库存被多行重复计入。
     * @param array $infoMap 销货明细 [info_id => row]
     * @param array $out_data {sales_info_id: 本次出库数量}
     */
    private function checkOutboundStock(array $infoMap, array $out_data)
    {
        $needMap = []; // key => ['goods_code','stores_id','attribute_id','need']
        foreach ($out_data as $info_id => $out_number) {
            $out_number = (float)$out_number;
            if ($out_number <= 0 || !isset($infoMap[$info_id])) {
                continue;
            }
            $info = $infoMap[$info_id];
            // 与扣减库存时同一换算口径
            $basic_number = round(($info['basic_nmber'] / max($info['number'], 0.0001)) * $out_number, 4);
            if ($basic_number <= 0) {
                continue;
            }
            $attribute_id = (int)$info['goods_attr'];
            $key = $info['goods_code'] . '-' . (int)$info['stores_id'] . '-' . $attribute_id;
            if (!isset($needMap[$key])) {
                $needMap[$key] = [
                    'goods_code'   => $info['goods_code'],
                    'stores_id'    => (int)$info['stores_id'],
                    'attribute_id' => $attribute_id,
                    'need'         => 0,
                ];
            }
            $needMap[$key]['need'] += $basic_number;
        }
        if (empty($needMap)) {
            return;
        }

        $goodsCodes = array_unique(array_column($needMap, 'goods_code'));
        $goodsMap = Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id,goods_name', 'goods_code');
        $storesMap = Db::name('kuerp_stores')->column('name', 'id');

        $stockItems = [];
        foreach ($needMap as $k => $item) {
            if (!isset($goodsMap[$item['goods_code']])) {
                $this->error('商品' . $item['goods_code'] . '不存在');
            }
            $needMap[$k]['goods_id'] = (int)$goodsMap[$item['goods_code']]['id'];
            $needMap[$k]['goods_name'] = $goodsMap[$item['goods_code']]['goods_name'];
            $stockItems[] = [
                'goods_id'     => $needMap[$k]['goods_id'],
                'stores_id'    => $item['stores_id'],
                'attribute_id' => $item['attribute_id'],
            ];
        }
        // 可用库存 = 物理库存 - 所有生效锁定（sales_order_id 传空，与出库管理、申请弹窗口径一致）
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        $shortage = [];
        foreach ($needMap as $item) {
            $skey = $item['goods_id'] . '-' . $item['stores_id'] . '-' . $item['attribute_id'];
            $available = isset($stockMap[$skey]) ? (float)$stockMap[$skey] : 0;
            if ($available + 0.00005 < $item['need']) {
                $storesName = isset($storesMap[$item['stores_id']]) ? $storesMap[$item['stores_id']] : ('仓库' . $item['stores_id']);
                $shortage[] = $item['goods_name'] . '(' . $item['goods_code'] . ')【' . $storesName . '】可用库存 '
                    . rtrim(rtrim(sprintf('%.4f', $available), '0'), '.')
                    . '，本次需出库 ' . rtrim(rtrim(sprintf('%.4f', $item['need']), '0'), '.');
            }
        }
        if (!empty($shortage)) {
            $this->error('库存不足，不能出库：' . implode('；', $shortage));
        }
    }

    /**
     * 保存实际出库 - 处理库存变动
     * $outData 供出库管理(GoodsOutboundApply::examine)内部调用时直接传入 {sales_info_id:出库数量}，
     * 不传则与原行为一致读取 POST out_data
     * $checkStock 出库前是否校验可用库存（默认校验）。下单（销货预订单/销货单）不校验库存，
     *             库存为 0/负数也可下单；只有真正出库（仓库在「出库管理」审核出库）才校验。
     */
    public function saveOutbound($ids = null, $outData = null, $checkStock = true)
    {
        $ids = $ids ? $ids : $this->request->post('ids');
        if (empty($ids)) {
            $this->error('参数错误');
        }
        $row = $this->model->where(['id' => $ids])->find();
        if (!$row) {
            $this->error('销货单不存在');
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

        $out_data = is_array($outData) ? $outData : $this->request->post('out_data/a');

        if (empty($out_data)) {
            $this->error('请填写出库数量');
        }

        $has_out = false;
        $total_out = 0; // 本次出库总计（用于同步订单状态）
        $editstock = [];
        $newstock = []; // 该仓库还没有库存行时新建（允许负库存）
        $goods_stock = [];
        $goods_stock_log = [];
        $goods_batch_edit = [];
        $goods_batch_add = [];
        $goods_batch_log = [];
        $edit_serial = [];
        $serial_log = [];
        $update_out_info = [];

        $infoList = Db::name('kuerp_goods_sales_info')
            ->where(['purchase_code' => $row['purchase_code']])
            ->select();
        $infoMap = [];
        foreach ($infoList as $info) {
            $infoMap[$info['id']] = $info;
        }

        // 出库前库存校验：按 商品+仓库+属性 汇总本次出库的基本单位数量，可用库存不足则禁止出库
        // （可用库存口径与出库管理/出库申请弹窗展示的「可用库存」一致，已扣除其他订单锁定）
        if ($checkStock) {
            $this->checkOutboundStock($infoMap, $out_data);
        }

        foreach ($out_data as $info_id => $out_number) {
            $out_number = (float)$out_number;
            if ($out_number <= 0) {
                continue;
            }
            if (!isset($infoMap[$info_id])) {
                continue;
            }
            $info = $infoMap[$info_id];

            $existing_out = isset($info['out_number']) ? (float)$info['out_number'] : 0;
            $max_out = $info['number'] - $existing_out;
            if ($out_number > $max_out) {
                $this->error($info['goods_code'] . ' 实际出库数量不能超过' . $max_out);
            }

            $has_out = true;
            $total_out += $out_number;
            $goods = Db::name('kuerp_goods')->where(['goods_code' => $info['goods_code']])->field('id,goods_name,inventory,unit_id,serial,batch')->find();
            if (!$goods) {
                $this->error('商品' . $info['goods_code'] . '不存在');
            }

            // 计算基本数量（按比例）
            $basic_number = round(($info['basic_nmber'] / max($info['number'], 0.0001)) * $out_number, 4);

            // 获取成本单价
            $balance_cost = 0;
            $balance_unit = 0;
            $goodsInfo = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($info['goods_code'], $info['stores_id'], $info['goods_attr']), true);
            $unitData = isset($goodsInfo['unit']) ? $goodsInfo['unit'] : [];
            if (!empty($unitData)) {
                foreach ($unitData as $unitData_v) {
                    if ($unitData_v['id'] == $goods['unit_id']) {
                        $balance_unit = $unitData_v['purchase'];
                    }
                }
            }

            // 商品表库存扣减
            $status = false;
            foreach ($goods_stock as $gk => $gv) {
                if ($gv['id'] == $goods['id']) {
                    $goods_stock[$gk]['inventory'] = $gv['inventory'] - $basic_number;
                    $status = true;
                }
            }
            if (!$status) {
                $goods_stock[] = [
                    'id'        => $goods['id'],
                    'inventory' => $goods['inventory'] - $basic_number,
                ];
            }
            // 允许负库存：库存为 0/负数时照常出库（库存在仓库行上扣成负数，采购/组装/外协入库后补平），
            // 故此处不再校验 inventory < 0。

            // 仓库库存扣减
            $where = [];
            $where['goods_code'] = $info['goods_code'];
            $where['stores_id'] = $info['stores_id'];
            $goods_attr = 0;
            if ($info['goods_attr'] > 0) {
                $goods_attr = $info['goods_attr'];
                $where['attribute_id'] = $goods_attr;
            }
            $stock = Db::name('kuerp_goods_stock')->where($where)->field('stock,id')->find();
            if ($stock) {
                $status = false;
                foreach ($editstock as $ek => $ev) {
                    if ($ev['id'] == $stock['id']) {
                        $editstock[$ek]['stock'] -= $basic_number;
                        $status = true;
                    }
                }
                if (!$status) {
                    $editstock[] = [
                        'id'    => $stock['id'],
                        'stock' => round($stock['stock'] - $basic_number, 4)
                    ];
                }
                $balance_cost = round($balance_unit * $basic_number, 4);
            } else {
                // 该仓库还没有该商品的库存行：直接建一条负库存记录（允许负库存），
                // 同一单据多行同商品+仓库+属性时累加到同一条。
                $newstock_key = $goods['id'] . '#' . (int)$info['stores_id'] . '#' . (int)$goods_attr;
                if (isset($newstock[$newstock_key])) {
                    $newstock[$newstock_key]['stock'] = round($newstock[$newstock_key]['stock'] - $basic_number, 4);
                } else {
                    $newstock[$newstock_key] = [
                        'admin_id'     => $admin['id'],
                        'goods_id'     => $goods['id'],
                        'goods_code'   => $info['goods_code'],
                        'attribute_id' => $goods_attr > 0 ? $goods_attr : null,
                        'stores_id'    => (int)$info['stores_id'],
                        'stock'        => round(0 - $basic_number, 4),
                        'status'       => '1',
                        'period'       => 0,
                        'unit_cost'    => 0,
                        'price'        => 0,
                        'createtime'   => time(),
                        'updatetime'   => time(),
                    ];
                }
                $balance_cost = round($balance_unit * $basic_number, 4);
            }
            // 允许负库存：不再校验 editstock 扣减后是否小于 0。

            // 收发明细
            $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                'purchase_code' => $info['purchase_code'],
                'purchase_id'   => $info['id'],
                'purchase_time' => $info['purchase_time'],
                'business_type' => '4',
                'customer_id'   => $info['customers_id'],
                'goods_id'      => $goods['id'],
                'goods_code'    => $info['goods_code'],
                'attribute_id'  => (float)$info['goods_attr'],
                'goods_unit'    => (float)$info['goods_unit'],
                'stores_id'     => (float)$info['stores_id'],
                'remark'        => $info['remark'],
                'out_number'    => $out_number,
                'out_basics'    => $basic_number,
                'out_unit'      => $balance_unit,
                'out_cost'      => $balance_cost,
                'createtime'    => time(),
                'updatetime'    => time(),
            ]);

            // 批次跟踪
            if ($batch_set == 1 && $goods['batch'] == 1 && !empty($info['batch_id'])) {
                $bwhere = [];
                $bwhere['goods_id'] = $goods['id'];
                if ($goods_attr > 0) {
                    $bwhere['attribute_id'] = $goods_attr;
                }
                $bwhere['stores_id'] = $info['stores_id'];
                $bwhere['batch_id'] = $info['batch_id'];
                $goods_batch_stock = Db::name('kuerp_goods_batch_stock')->where($bwhere)->find();
                if (empty($goods_batch_stock)) {
                    $this->error($goods['goods_name'] . $info['batch_name'] . '批次库存不足');
                } else {
                    if (isset($goods_batch_edit[$goods_batch_stock['id']])) {
                        $goods_batch_edit[$goods_batch_stock['id']] -= $basic_number;
                    } else {
                        $goods_batch_edit[$goods_batch_stock['id']] = $goods_batch_stock['stock'] - $basic_number;
                    }
                }
                foreach ($goods_batch_edit as $bv) {
                    if ($bv < 0) {
                        $this->error($goods['goods_name'] . $info['batch_name'] . '批次库存不足');
                    }
                }
                $goods_batch_log[] = \addons\kuerp\library\Goods::setDataInfo([
                    'goods_id'        => $goods['id'],
                    'goods_unit'      => $info['goods_unit'],
                    'stores_id'       => $info['stores_id'],
                    'batch_id'        => (float)$info['batch_id'],
                    'number'          => $basic_number,
                    'purchase_code'   => $info['purchase_code'],
                    'purchase_time'   => $info['purchase_time'],
                    'business_type'   => '4',
                    'remarks'         => $info['remark'],
                    'order_id'        => $info['id'],
                    'unit_price'      => $info['unit_price'],
                    'tax_included'    => $info['tax_included'],
                    'all_money'       => $info['all_money'],
                    'tax_money'       => $info['tax_money'],
                    'money_tax_price' => $info['money_tax_price'],
                    'customer_id'     => $info['customers_id'],
                    'createtime'      => time(),
                    'updatetime'      => time(),
                ]);
            }

            // 序列号跟踪
            if ($goods && $goods['serial'] == 1 && $serial_set == 1 && !empty($info['period'])) {
                $period = @unserialize($info['period']);
                if ($period) {
                    $out_count = 0;
                    foreach ($period as $period_v) {
                        if ($out_count >= $out_number) break;
                        $per_s = Db::name('kuerp_goods_serial')->where(['serial' => $period_v['serial']])->field('serial,status,store_status')->find();
                        if ($per_s) {
                            $edit_serial[] = ['serial' => $per_s['serial'], 'sale_id' => $info['id'], 'type' => 4, 'store_status' => '2', 'old_status' => $per_s['status'], 'old_store' => $per_s['store_status']];
                        }
                        $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                            'goods_id'      => $goods['id'],
                            'stores_id'     => $info['stores_id'],
                            'attribute_id'  => $goods_attr,
                            'serial'        => $period_v['serial'],
                            'business_type' => '4',
                            'store_status'  => '2',
                            'remarks'       => isset($period_v['remark']) ? $period_v['remark'] : '',
                            'purchase_id'   => $info['id'],
                            'customer_id'   => $info['customers_id'],
                            'purchase_code' => $info['purchase_code'],
                            'purchase_time' => $info['purchase_time'],
                            'createtime'    => time(),
                            'updatetime'    => time(),
                        ]);
                        $out_count++;
                    }
                }
            }

            $update_out_info[] = [
                'id'         => $info_id,
                'out_number' => round($existing_out + $out_number, 4),
            ];
        }

        if (!$has_out) {
            $this->error('没有有效的出库数据');
        }

        // 数据库事务
        Db::startTrans();
        try {
            // 更新商品库存
            if (!empty($goods_stock)) {
                foreach ($goods_stock as $gv) {
                    Db::name('kuerp_goods')->where(['id' => $gv['id']])->update(['inventory' => $gv['inventory']]);
                }
            }

            // 更新仓库库存
            if (!empty($editstock)) {
                foreach ($editstock as $ev) {
                    Db::name('kuerp_goods_stock')->where(['id' => $ev['id']])->update(['stock' => $ev['stock']]);
                }
            }
            // 新建负库存行（该仓库原本没有该商品的库存行）
            if (!empty($newstock)) {
                foreach ($newstock as $ns) {
                    Db::name('kuerp_goods_stock')->insert($ns);
                }
            }

            // 记录收发明细
            if (!empty($goods_stock_log)) {
                foreach ($goods_stock_log as $gsl) {
                    Db::name('kuerp_goods_log')->insert($gsl);
                }
            }

            // 更新批次库存
            if (!empty($goods_batch_edit)) {
                foreach ($goods_batch_edit as $bid => $stock_val) {
                    Db::name('kuerp_goods_batch_stock')->where(['id' => $bid])->update(['stock' => $stock_val]);
                }
            }
            if (!empty($goods_batch_add)) {
                foreach ($goods_batch_add as $bav) {
                    Db::name('kuerp_goods_batch_stock')->insert($bav);
                }
            }

            // 记录批次日志
            if (!empty($goods_batch_log)) {
                foreach ($goods_batch_log as $bl) {
                    Db::name('kuerp_goods_batch_log')->insert($bl);
                }
            }

            // 更新序列号
            if (!empty($edit_serial)) {
                foreach ($edit_serial as $es) {
                    Db::name('kuerp_goods_serial')->where(['serial' => $es['serial']])->update(['store_status' => '2']);
                }
            }

            // 记录序列号日志
            if (!empty($serial_log)) {
                foreach ($serial_log as $sl) {
                    Db::name('kuerp_goods_serial_log')->insert($sl);
                }
            }

            // 更新明细的 out_number
            if (!empty($update_out_info)) {
                foreach ($update_out_info as $uoi) {
                    Db::name('kuerp_goods_sales_info')->where(['id' => $uoi['id']])->update(['out_number' => $uoi['out_number']]);
                }
            }

            // 更新主表操作信息
            $this->model->where(['id' => $ids])->update([
                'operation_id' => $admin['id'],
                'updatetime'   => time(),
            ]);

            // 同步关联销货订单的出库状态：由该订单下所有销货单的实际出库数量决定
            if (!empty($row['purchase_order_id'])) {
                \app\admin\model\kuerp\GoodsSalesOrder::syncStatusByOutbound($row['purchase_order_id']);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('出库成功');
    }

    /**
     * 为详情/打印页补充发货方式展示文本（有销货订单关联时以订单为准）
     */
    private function appendDeliveryModeText($row)
    {
        $mode = isset($row['delivery_mode']) ? $row['delivery_mode'] : 'direct';
        if (!empty($row['purchase_order_id'])) {
            $order_mode = Db::name('kuerp_goods_sales_order')
                ->where(['purchase_code' => $row['purchase_order_id']])
                ->value('delivery_mode');
            if (in_array($order_mode, ['direct', 'dropship'], true)) {
                $mode = $order_mode;
            }
        }
        if (!in_array($mode, ['direct', 'dropship'], true)) {
            $mode = 'direct';
        }
        $row['delivery_mode'] = $mode;
        return $row;
    }
}
