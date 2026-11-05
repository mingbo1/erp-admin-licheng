<?php

namespace app\admin\controller\kuerp;

use app\admin\model\kuerp\GoodsSalesOrderLog;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use think\Db;
use Exception;
use think\Session;
/**
 * 购货订单
 *
 * @icon fa fa-circle-o
 */
class GoodsPurchaseOrder extends Backend
{
    // export / parseimport 已纳入权限表（kuerp/goods_purchase_order/export|parseimport）
    protected $noNeedRight = ['checkexchange','checkpurchase','checksuppliercount','batchgeneratepurchase','generatebysupplier'];
    /** 导入模板 / 导出明细共用的隐藏选项表名 */
    const EXCEL_OPT_SHEET = 'Lists';
    /** 模板预留的可填写行数（数据自第 2 行开始） */
    const EXCEL_TEMPLATE_ROWS = 500;
    /**
     * GoodsPurchaseOrder模型对象
     * @var \app\admin\model\kuerp\GoodsPurchaseOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsPurchaseOrder;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("suppliersList", $this->model->getSuppliersList());
        $this->view->assign("storesList", $this->model->getStoresList());
        $this->assignconfig("storesList", $this->model->getStoresList());

        $this->view->assign("goodsList", $this->model->getGoodsList());
        $this->assignconfig("goodsList", $this->model->getGoodsList());

        $this->view->assign("adminList", $this->model->getAdminList());
        $this->view->assign("accountsList", $this->model->getAccountsList());

        $this->view->assign("secondExamineStatusList", $this->model->getSecondExamineStatusList());

        // 二次审核金额阈值
        $purchase_order_examine_money = Db::name('kuerp_sets')->where(['name'=>'purchase_order_examine_money'])->value('value');
        $this->assignconfig('purchase_order_examine_money', $purchase_order_examine_money ? $purchase_order_examine_money : '0');
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 单据来源标记（写入 fa_kuerp_goods_purchase_order.order_source）
     * purchase=购货订单页 / plan=计划部门预订单 / warehouse=仓库部门预订单
     * @return string
     */
    protected function orderSource()
    {
        return 'purchase';
    }

    /**
     * 列表的来源过滤，返回 null 表示不限制（购货订单页能看到全部预订单）
     * @return array|null
     */
    protected function orderSourceListFilter()
    {
        return null;
    }

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
            // 供应商搜索适配明细表：主表无supplyer_code字段，先从filter中移除，改由明细表匹配订单号
            $filterArr = (array)json_decode($this->request->request('filter', ''), true);
            $supplierFilter = '';
            if (!empty($filterArr['supplyer_code'])) {
                $supplierFilter = $filterArr['supplyer_code'];
                unset($filterArr['supplyer_code']);
                $this->request->get(['filter' => json_encode($filterArr)]);
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            // 按明细表匹配供应商对应的订单号（buildparams返回的$where是闭包，不能直接赋值，需追加到查询链上）
            if ($supplierFilter !== '') {
                $matchedCodes = Db::name('kuerp_goods_purchase_order_info')
                    ->where(['supplyer_code' => $supplierFilter])
                    ->distinct('purchase_code')
                    ->column('purchase_code');
                $this->model->where('purchase_code', 'in', $matchedCodes ?: ['']); // 无匹配结果
            }
            // 按来源部门过滤：计划预订单 / 仓库预订单各自只看自己录入的单据（购货订单页不过滤）
            $orderSource = $this->orderSourceListFilter();
            if ($orderSource !== null) {
                $this->model->where('order_source', 'in', (array)$orderSource);
            }
            $sort = 'purchase_time';$order='DESC';
            $list = $this->model
                ->with(['suppliers','preparation','examine','secondExamine'])
                ->where($where)
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('suppliers')->visible(['supplyer_code','supplyer_name']);
                $row->getRelation('preparation')->visible(['nickname']);
                $row->getRelation('examine')->visible(['nickname']);
                if ($row->getRelation('secondExamine')) {
                    $row->getRelation('secondExamine')->visible(['nickname']);
                }
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                // 获取关联采购单的完整状态信息
                $purchase_list = Db::name('kuerp_goods_purchase')
                    ->where(['id'=>['in',$purchase_id]])
                    ->field('purchase_code,examine_status,pay_status,check_status')
                    ->select();
                $purchase_codes = [];
                foreach ($purchase_list as $p) {
                    $purchase_codes[] = $p['purchase_code'];
                }
                $list[$list_k]['purchase_id'] = implode(',',$purchase_codes);
                $list[$list_k]['purchase_list'] = $purchase_list;

                // 查询该订单涉及的所有供应商（从明细表）
                $supplyerCodes = Db::name('kuerp_goods_purchase_order_info')
                    ->where(['purchase_code' => $list_v['purchase_code']])
                    ->where('supplyer_code', '<>', '')
                    ->distinct('supplyer_code')
                    ->column('supplyer_code');
                if (!empty($supplyerCodes)) {
                    $suppliersInfo = Db::name('kuerp_suppliers')
                        ->where(['supplyer_code' => ['in', $supplyerCodes]])
                        ->field('supplyer_code, supplyer_name')
                        ->select();
                    $list[$list_k]['supplier_list'] = $suppliersInfo;
                } else {
                    $list[$list_k]['supplier_list'] = [];
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($type='')
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');

                $params = $this->preExcludeFields($params);
                $admin = Session::get('admin');

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;
                $rowSupplierCodes = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];
                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }elseif ($goods['auxiliary']==1 && empty($params['goods_attr'][$k])){
                            $this->error('请选择商品属性');
                        }

                        // 行级供应商：优先取行级值，fallback 到表头值
                        $lineSupplier = isset($params['row_supplyer_code'][$k]) ? $params['row_supplyer_code'][$k] : '';
                        if (empty($lineSupplier)) {
                            $lineSupplier = $params['supplyer_code'] ?? '';
                        }
                        $rowSupplierCodes[] = $lineSupplier;

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'delivery_time'     =>  !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                            'supplyer_code'     =>  $lineSupplier,//行级供应商

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (int)$params['goods_unit'][$k],

                            'stores_id'         =>  (int)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'purchase_number'   =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],
                            'sales_order_id'    =>  $params['sales_order_id'][$k],

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

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
                    // 合并所有行的sales_order_id（取第一个非空的）
                    $mainSalesOrderId = '';
                    foreach ($data as $d) {
                        if (!empty($d['sales_order_id'])) {
                            $mainSalesOrderId = $d['sales_order_id'];
                            break;
                        }
                    }
                    // 计算表头供应商：单一供应商时自动填充，多供应商时留空
                    $uniqueSuppliers = array_unique(array_filter($rowSupplierCodes));
                    $headerSupplierCode = count($uniqueSuppliers) === 1 ? reset($uniqueSuppliers) : '';
                    $row = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'order_source'      =>  $this->orderSource(),
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                        'supplyer_code'     =>  $headerSupplierCode,
                        'supplier_count'    =>  count($uniqueSuppliers),
                        'sales_order_id'    =>  $mainSalesOrderId,
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,
                        'tax_money'         =>  $tax_money,
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
                                'type'          => '1',
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

                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$params['purchase_code']])->delete();
                    Db::name('kuerp_goods_purchase_order_info')->insertAll($data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if (isset($result) && $result !== false) {
                    // 写入销货订单日志：生成采购订单
                    if (!empty($mainSalesOrderId)) {
                        GoodsSalesOrderLog::writeLogByCode($mainSalesOrderId, '生成采购订单', '生成采购订单：' . $params['purchase_code'], 'purchase_order', $result);
                    }
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
        $code = \addons\kuerp\library\Goods::getCodes('',7);
        $this->view->assign("code", $code);

        $GoodsPurchaseOrder = [];
        if($type==1){
            $GoodsPurchaseOrder = Session::get('GoodsPurchaseOrder');
            if($GoodsPurchaseOrder){
                $GoodsPurchaseOrder = unserialize($GoodsPurchaseOrder);
                foreach ($GoodsPurchaseOrder as $GoodsPurchaseOrder_k=>$GoodsPurchaseOrder_v){
                    $GoodsPurchaseOrder[$GoodsPurchaseOrder_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($GoodsPurchaseOrder_v['goods_code']),true);
                    $GoodsPurchaseOrder[$GoodsPurchaseOrder_k]['goods'] = Db::name('kuerp_goods')->where(['goods_code'=>$GoodsPurchaseOrder_v['goods_code']])->find();
                }
            }
        }

        // 从销货排期生成：预填BOM零件商品
        $scheduleBomGoods = [];
        $scheduleSalesOrderId = ''; // 关联的销货订单号
        $schedule_id = $this->request->param('schedule_id');
        if (!empty($schedule_id)) {
            $schedule = Db::name('kuerp_goods_sales')->where(['id' => $schedule_id, 'purchase_type' => '1'])->find();
            if ($schedule) {
                // 获取关联的销货订单号
                $salesOrderCode = $schedule['purchase_order_id']; // purchase_order_id 存储的是销货订单编号

                // 获取排期商品明细
                $scheduleInfo = Db::name('kuerp_goods_sales_info')
                    ->where(['purchase_code' => $schedule['purchase_code']])
                    ->select();

                $numberDecimal = 2;
                $setData = Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
                if ($setData) $numberDecimal = (int)$setData;

                $partMap = [];
                $noBomMap = []; // 无BOM模板的排期商品（成品/半成品整件采购）：自身缺货检测
                foreach ($scheduleInfo as $info) {
                    $goodsCode = $info['goods_code'];
                    $basicNmber = (float)$info['basic_nmber'];
                    $storesId = (int)$info['stores_id'];

                    $goods = Db::name('kuerp_goods')->where(['goods_code' => $goodsCode])->field('id, goods_name, unit_id')->find();
                    if (empty($goods)) continue;
                    $goodsId = $goods['id'];

                    $bomList = Db::name('kuerp_goods_bom')->where(['goods_id' => $goodsId])->select();
                    if (empty($bomList)) {
                        // 无BOM则无法组装，只能整件采购：记录自身需求，后面按库存缺口生成采购明细
                        if (!isset($noBomMap[$goodsId])) {
                            $noBomMap[$goodsId] = [
                                'goods_code' => $goodsCode,
                                'goods_name' => $goods['goods_name'],
                                'number' => 0,
                                'stores_id' => $storesId,
                                'unit_id' => $goods['unit_id'],
                            ];
                        }
                        $noBomMap[$goodsId]['number'] += $basicNmber;
                        continue;
                    }

                    foreach ($bomList as $bom) {
                        // 子件检测扩展到零件+半成品+成品
                        $children = Db::name('kuerp_goods_bom_info')->alias('a')
                            ->join('kuerp_goods g', 'g.id=a.goods_id')
                            ->where(['a.bom_id' => $bom['id'], 'a.type' => '2', 'g.product_type' => ['in', ['part', 'semi_finished', 'finished']]])
                            ->field('a.goods_id, a.goods_code, a.number as bom_number, g.goods_name, g.unit_id')
                            ->select();

                        foreach ($children as $child) {
                            $childGoodsId = $child['goods_id'];
                            $requiredQty = (float)$child['bom_number'] * $basicNmber;

                            if (!isset($partMap[$childGoodsId])) {
                                $partMap[$childGoodsId] = [
                                    'goods_code' => $child['goods_code'],
                                    'goods_name' => $child['goods_name'],
                                    'number' => 0,
                                    'stores_id' => $storesId,
                                    'unit_id' => $child['unit_id'],
                                ];
                            }
                            $partMap[$childGoodsId]['number'] += $requiredQty;
                        }
                    }
                }

                // 采购时只考虑一个仓库，优先选择有库存的零件仓库
                // 查询各零件在各零件仓库的库存，为每个零件选择库存最多的仓库
                if (!empty($partMap)) {
                    $partGoodsIds = array_keys($partMap);
                    $partStoreIds = Db::name('kuerp_stores')->where(['store_type' => 'part'])->column('id');
                    $stockMap = []; // 零件现有库存映射 goods_id => [stores_id => stock]
                    if (!empty($partStoreIds)) {
                        $stockData = Db::name('kuerp_goods_stock')
                            ->where(['goods_id' => ['in', $partGoodsIds]])
                            ->where(['stores_id' => ['in', $partStoreIds]])
                            ->field('goods_id, stores_id, stock')
                            ->select();
                        $stockMap = []; // goods_id => [stores_id => stock]
                        foreach ($stockData as $sd) {
                            $gid = $sd['goods_id'];
                            if (!isset($stockMap[$gid])) $stockMap[$gid] = [];
                            $stockMap[$gid][$sd['stores_id']] = (float)$sd['stock'];
                        }
                        // 为每个零件选择库存最多的仓库
                        foreach ($partMap as $goodsId => &$p) {
                            if (isset($stockMap[$goodsId]) && !empty($stockMap[$goodsId])) {
                                // 有库存数据：选库存最多的零件仓库
                                arsort($stockMap[$goodsId]);
                                $bestKeys = array_keys($stockMap[$goodsId]);
                                $p['stores_id'] = reset($bestKeys);
                            } elseif (!empty($partStoreIds)) {
                                // 无库存数据：兜底使用第一个零件仓库，而非父商品排期的非零件仓库
                                $p['stores_id'] = (int)reset($partStoreIds);
                            }
                        }
                        unset($p);
                    }
                }

                // 批量读取零件的默认供应商
                $partGoodsCodes = array_unique(array_merge(array_column($partMap, 'goods_code'), array_column($noBomMap, 'goods_code')));
                $goodsSupplierMap = [];
                if (!empty($partGoodsCodes)) {
                    $goodsData = Db::name('kuerp_goods')
                        ->where(['goods_code' => ['in', $partGoodsCodes]])
                        ->field('goods_code, supplier_id')
                        ->select();
                    $supplierIds = array_unique(array_filter(array_column($goodsData, 'supplier_id')));
                    $supplierCodeMap = [];
                    if (!empty($supplierIds)) {
                        $suppliers = Db::name('kuerp_suppliers')
                            ->where(['id' => ['in', $supplierIds]])
                            ->field('id, supplyer_code')
                            ->select();
                        foreach ($suppliers as $s) {
                            $supplierCodeMap[$s['id']] = $s['supplyer_code'];
                        }
                    }
                    foreach ($goodsData as $gd) {
                        if (!empty($gd['supplier_id']) && isset($supplierCodeMap[$gd['supplier_id']])) {
                            $goodsSupplierMap[$gd['goods_code']] = $supplierCodeMap[$gd['supplier_id']];
                        }
                    }
                }

                foreach ($partMap as $goodsId => $p) {
                    // 计算缺口数量：需求 - 各零件仓库现有库存之和，库存足够则无需采购
                    $totalStock = 0;
                    if (isset($stockMap[$goodsId])) {
                        foreach ($stockMap[$goodsId] as $stk) {
                            $totalStock += $stk;
                        }
                    }
                    $shortage = round($p['number'], $numberDecimal) - round($totalStock, $numberDecimal);
                    if ($shortage <= 0) {
                        continue; // 现有库存已满足需求，不生成采购明细
                    }
                    $scheduleBomGoods[] = [
                        'goods_code' => $p['goods_code'],
                        'goods_name' => $p['goods_name'],
                        'number' => round($shortage, $numberDecimal),
                        'stores_id' => $p['stores_id'],
                        'unit_id' => $p['unit_id'],
                        'supplyer_code' => isset($goodsSupplierMap[$p['goods_code']]) ? $goodsSupplierMap[$p['goods_code']] : '',
                    ];
                }

                // 无BOM模板的排期商品：自身库存缺口（全部仓库合计）生成整件采购明细
                if (!empty($noBomMap)) {
                    $noBomIds = array_keys($noBomMap);
                    $selfStockData = Db::name('kuerp_goods_stock')
                        ->where(['goods_id' => ['in', $noBomIds]])
                        ->field('goods_id, stock')
                        ->select();
                    $selfStockMap = [];
                    foreach ($selfStockData as $sd) {
                        $sgid = $sd['goods_id'];
                        if (!isset($selfStockMap[$sgid])) $selfStockMap[$sgid] = 0;
                        $selfStockMap[$sgid] += (float)$sd['stock'];
                    }
                    foreach ($noBomMap as $gid => $p) {
                        $totalStock = isset($selfStockMap[$gid]) ? $selfStockMap[$gid] : 0;
                        $shortage = round($p['number'], $numberDecimal) - round($totalStock, $numberDecimal);
                        if ($shortage <= 0) {
                            continue; // 现有库存已满足需求，不生成采购明细
                        }
                        $scheduleBomGoods[] = [
                            'goods_code' => $p['goods_code'],
                            'goods_name' => $p['goods_name'],
                            'number' => round($shortage, $numberDecimal),
                            'stores_id' => $p['stores_id'],
                            'unit_id' => $p['unit_id'],
                            'supplyer_code' => isset($goodsSupplierMap[$p['goods_code']]) ? $goodsSupplierMap[$p['goods_code']] : '',
                        ];
                    }
                }

                // 从商品资料自动读取默认供应商，留空行让用户手动补选
                // 传递销货订单号用于关联
                if (!empty($salesOrderCode)) {
                    $scheduleSalesOrderId = $salesOrderCode;
                }
            }
        }

        $last_customer = Db::name('kuerp_sets')->where(['name'=>'customer'])->value('is_checked');
        $set_customer = '';
        if($last_customer){
            $set_customer = Db::name('kuerp_goods_purchase_order')->order('id','DESC')->value('supplyer_code');
        }
        $this->view->assign("set_customer", $set_customer);
        $kuerp_suppliers = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$set_customer])->field('supplyer_code,supplyer_name')->find();
        $this->assignconfig("kuerp_suppliers", $kuerp_suppliers);

        $this->view->assign("list", $GoodsPurchaseOrder);
        $this->assignconfig("list", $GoodsPurchaseOrder);

        // 传递BOM预填数据和销货订单关联信息到前端
        $this->view->assign("scheduleBomGoods", $scheduleBomGoods);
        $this->assignconfig("scheduleBomGoods", $scheduleBomGoods);
        $this->view->assign("scheduleSalesOrderId", $scheduleSalesOrderId);
        $this->assignconfig("scheduleSalesOrderId", $scheduleSalesOrderId);

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
            $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
            if($checkout!==true){
                $this->error($checkout);
            }
            $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
            if($row['examine_status']==1 && $examine==1){
                $this->error('不能编辑已审核的数据');
            }
            if($row['purchase_type']==1){
                $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$ids])->column('purchase_id');
                if(!empty($purchase_id)){
                    $this->error('不能编辑已生成采购单的数据');
                }
            }else{
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$ids])->column('exchange_id');
                if(!empty($purchase_id)){
                    $this->error('不能编辑已生成退货单的数据');
                }
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }

                $admin = Session::get('admin');

                $data = [];
                $number = 0;$all_money = 0;$tax_money=0;
                $rowSupplierCodes = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){//有产品编号
                        $number+=$params['number'][$k];
                        $all_money+=$params['all_money'][$k];
                        $tax_money += (float)$params['tax_money'][$k];

                        if(!isset($params['stores_id'][$k]) || empty($params['stores_id'][$k])){
                            $this->error('请选择仓库');
                        }

                        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$v])->find();
                        if(empty($goods)){
                            $this->error('商品不存在');
                        }elseif ($goods['auxiliary']==1 && empty($params['goods_attr'][$k])){
                            $this->error('请选择商品属性');
                        }

                        // 行级供应商：优先取行级值，fallback 到表头值
                        $lineSupplier = isset($params['row_supplyer_code'][$k]) ? $params['row_supplyer_code'][$k] : '';
                        if (empty($lineSupplier)) {
                            $lineSupplier = $params['supplyer_code'] ?? '';
                        }
                        $rowSupplierCodes[] = $lineSupplier;

                        $data[] = [
                            'purchase_code'     =>  $params['purchase_code'],
                            'purchase_type'     =>  $params['purchase_type'],
                            'purchase_time'     =>  strtotime($params['purchase_time']),
                            'delivery_time'     =>  !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                            'supplyer_code'     =>  $lineSupplier,//行级供应商

                            'goods_code'        =>  $v,//商品信息
                            'goods_note'        =>  $params['goods_note'][$k],
                            'goods_unit'        =>  (float)$params['goods_unit'][$k],

                            'stores_id'         =>  (float)$params['stores_id'][$k],
                            'allow_stock'       =>  (float)$params['allow_stock'][$k],
                            'number'            =>  (float)$params['number'][$k],
                            'purchase_number'   =>  (float)$params['number'][$k],
                            'default_unit'      =>  (float)$params['default_unit'][$k],
                            'basic_nmber'       =>  (float)$params['basic_nmber'][$k],
                            'unit_price'        =>  (float)$params['unit_price'][$k],
                            'discount'          =>  (float)$params['discount'][$k],
                            'dis_money'         =>  (float)$params['dis_money'][$k],
                            'all_money'         =>  (float)$params['all_money'][$k],
                            'remark'            =>  $params['remark'][$k],

                            'library_number'    =>  (float)$params['number'][$k],
                            'library_money'     =>  (float)$params['all_money'][$k],

                            'preferential_rate' =>  (float)$params['preferential_rate'],
                            'preferential_money'=>  (float)$params['preferential_money'],
                            'real_money'        =>  (float)$params['real_money'],

                            'goods_attr'        =>  (float)$params['goods_attr'][$k],
                            'tax_included'      =>  (float)$params['tax_included'][$k],
                            'tax_rate'          =>  (float)$params['tax_rate'][$k],
                            'tax_money'         =>  (float)$params['tax_money'][$k],
                            'money_tax_price'   =>  (float)$params['money_tax_price'][$k],

                            'preparation_id'    =>  $admin['id'],
                            'operation_id'      =>  $admin['id'],

                            'sales_order_id'    => $params['sales_order_id'][$k],

                            'createtime'        => $row['createtime'],
                            'updatetime'        => time(),
                        ];
                    }
                }
                if(!empty($data)){
                    // 合并所有行的sales_order_id（取第一个非空的）
                    $mainSalesOrderId = '';
                    foreach ($data as $d) {
                        if (!empty($d['sales_order_id'])) {
                            $mainSalesOrderId = $d['sales_order_id'];
                            break;
                        }
                    }
                    // 计算表头供应商：单一供应商时自动填充，多供应商时留空
                    $uniqueSuppliers = array_unique(array_filter($rowSupplierCodes));
                    $headerSupplierCode = count($uniqueSuppliers) === 1 ? reset($uniqueSuppliers) : '';
                    $rowdata = [
                        'purchase_code'     =>  $params['purchase_code'],
                        'purchase_type'     =>  $params['purchase_type'],
                        'purchase_time'     =>  strtotime($params['purchase_time']),
                        'delivery_time'     =>  !empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null,
                        'supplyer_code'     =>  $headerSupplierCode,
                        'supplier_count'    =>  count($uniqueSuppliers),
                        'sales_order_id'    =>  $mainSalesOrderId,
                        'number'            =>  $number,
                        'all_money'         =>  $all_money,
                        'tax_money'         =>  $tax_money,
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
                                'type'          => '1',
                            ];
                        }
                    }
                    unset($params['enclosure']);
                }
                //附件记录结束

                $result = false;
                Db::startTrans();
                try {
                    //关闭审核功能需先反审核一次已审核数据
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }

                    //添加附件记录
                    Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'1'])->delete();
                    if(!empty($enclosureLog)){
                        foreach ($enclosureLog as $enclosureLog_k=>$enclosureLog_v){
                            $enclosureLog[$enclosureLog_k]['order_id'] = $ids;
                        }
                        Db::name('kuerp_enclosure_log')->insertAll($enclosureLog);
                    }
                    //添加附件记录结束

                    $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->delete();
                    Db::name('kuerp_goods_purchase_order_info')->insertAll($data);
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

        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        // 批量查询动态库存
        $goodsCodes = array_column($purchase_info, 'goods_code');
        $goodsIdMap = $goodsCodes ? Db::name('kuerp_goods')->where(['goods_code' => ['in', $goodsCodes]])->column('id', 'goods_code') : [];
        $stockItems = [];
        foreach ($purchase_info as $v) {
            $gid = isset($goodsIdMap[$v['goods_code']]) ? $goodsIdMap[$v['goods_code']] : 0;
            if ($gid) $stockItems[] = ['goods_id' => $gid, 'stores_id' => (int)$v['stores_id'], 'attribute_id' => (int)$v['goods_attr']];
        }
        $stockMap = \addons\kuerp\library\Goods::getAvailableStockBatch($stockItems, '');

        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purchase_info[$purchase_info_k]['unit'] = json_decode(\addons\kuerp\library\GoodsPurchase::getUnit($purchase_info_v['goods_code'], $purchase_info_v['stores_id'], $purchase_info_v['goods_attr']),true);
            $gid = isset($goodsIdMap[$purchase_info_v['goods_code']]) ? $goodsIdMap[$purchase_info_v['goods_code']] : 0;
            $key = $gid . '-' . (int)$purchase_info_v['stores_id'] . '-' . (int)$purchase_info_v['goods_attr'];
            $purchase_info[$purchase_info_k]['allow_stock'] = isset($stockMap[$key]) ? $stockMap[$key] : 0;
        }
        $this->view->assign("purchase_info", $purchase_info);
        $this->assignconfig("purchase_id", $row['id']);

        // 按供应商分组，查询每个分组的采购单生成状态，并收集各行数据
        $supplierGroups = [];
        foreach ($purchase_info as $info) {
            $sc = !empty($info['supplyer_code']) ? $info['supplyer_code'] : '未指定供应商';
            if (!isset($supplierGroups[$sc])) {
                $supplierName = '';
                if (!empty($info['supplyer_code'])) {
                    $supplierName = Db::name('kuerp_suppliers')
                        ->where(['supplyer_code' => $info['supplyer_code']])
                        ->value('supplyer_name');
                }
                $supplierGroups[$sc] = [
                    'supplyer_code' => $info['supplyer_code'],
                    'supplyer_name' => $supplierName ?: $sc,
                    'goods_count' => 0,
                    'generated' => false,
                    'purchase_code' => '',
                    'purchase_id' => 0,
                    'total_money'  => 0,
                    'items'        => [],
                ];
            }
            $supplierGroups[$sc]['goods_count']++;
            $supplierGroups[$sc]['total_money'] += (float)($info['money_tax_price'] ?? 0);
            $supplierGroups[$sc]['items'][] = $info;

            // 检查该行是否已生成采购单
            $purordInfo = Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $info['id']])
                ->find();
            if (!empty($purordInfo)) {
                $purchaseInfo = Db::name('kuerp_goods_purchase_info')
                    ->where(['id' => $purordInfo['purchase_id']])
                    ->field('purchase_code')
                    ->find();
                if (!empty($purchaseInfo)) {
                    $supplierGroups[$sc]['generated'] = true;
                    $supplierGroups[$sc]['purchase_code'] = $purchaseInfo['purchase_code'];
                    // 查询采购单主表ID，用于"查看采购单"链接跳转
                    $purchaseHeaderId = Db::name('kuerp_goods_purchase')
                        ->where(['purchase_code' => $purchaseInfo['purchase_code']])
                        ->value('id');
                    $supplierGroups[$sc]['purchase_id'] = $purchaseHeaderId ?: 0;
                }
            }
        }
        $this->view->assign("supplierGroups", $supplierGroups);
        $this->assignconfig("supplierGroups", $supplierGroups);

        //查询原始单据
        $enclosure_id = Db::name('kuerp_enclosure_log')->where(['order_id'=>$ids,'type'=>'1'])->column('enclosure_id');
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
        $this->assignconfig("purchase_type", $row['purchase_type']);

        //明细导入能否使用：条件与 edit() POST 的拦截逐条对齐（结账 / 已审核 / 已生成采购单 / 已生成退货单），
        //由服务端算好原因下发，避免前端重复实现业务规则后与后端跑偏。空串 = 可导入。
        $importBlockReason = '';
        $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($row['purchase_time']);
        if ($checkout !== true) {
            $importBlockReason = $checkout;
        } elseif ($examine_set == 1 && $row['examine_status'] == 1) {
            $importBlockReason = '已审核的单据不能修改明细，请先反审核';
        } elseif ($row['purchase_type'] == 1) {
            if (Db::name('kuerp_goods_purord')->where(['order_id' => $ids])->count()) {
                $importBlockReason = '该购货订单已生成采购单，不能修改明细';
            }
        } elseif (Db::name('kuerp_goods_excpur')->where(['purchase_id' => $ids])->count()) {
            $importBlockReason = '该购货订单已生成退货单，不能修改明细';
        }
        $this->assignconfig('importBlockReason', $importBlockReason);

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
            // 部门隔离：只能删除本部门录入的预订单
            $orderSource = $this->orderSourceListFilter();
            if ($orderSource !== null) {
                foreach ($list as $list_v) {
                    if (!in_array($list_v['order_source'], (array)$orderSource)) {
                        $this->error('单据[' . $list_v['purchase_code'] . ']不属于当前部门，不能删除');
                    }
                }
            }
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
            foreach ($list as $list_k=>$list_v){
                if($list_v['purchase_type']==1){
                    $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                    if(!empty($purchase_id)){
                        $this->error('不能删除已生成采购单的数据');
                    }
                }else{
                    $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                    if(!empty($purchase_id)){
                        $this->error('不能删除已生成退货单的数据');
                    }
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$v['purchase_code']])->delete();

                    //删除附件记录
                    Db::name('kuerp_enclosure_log')->where(['purchase_code'=>$v['purchase_code'],'type'=>'1'])->delete();
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
            $threshold = Db::name('kuerp_sets')->where(['name'=>'purchase_order_examine_money'])->value('value');
            $threshold = floatval($threshold);

            // 分组：低于/等于阈值的自动完成二次审核，高于阈值的需要董事长二次审核
            $belowIds = [];
            $aboveIds = [];
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

                // 审核通过后：向关联的销货订单写入操作日志
                foreach ($list as $examinedOrder) {
                    if (!empty($examinedOrder['sales_order_id'])) {
                        GoodsSalesOrderLog::writeLogByCode($examinedOrder['sales_order_id'], '采购订单审核通过', '购货订单[' . $examinedOrder['purchase_code'] . ']审核通过', 'purchase_order', $examinedOrder['id']);
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
            $list = $this->model->where(['id'=>['in',$ids],'examine_status'=>'1'])->field('id,purchase_code,sales_order_id,purchase_id,purchase_time')->select();
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
            foreach ($list as $list_k=>$list_v){
                $purchase_id = Db::name('kuerp_goods_purord')->where(['order_id'=>$list_v['id']])->column('purchase_id');
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成采购单的数据');
                }
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id'],'type'=>'1'])->column('exchange_id');
                if(!empty($purchase_id)){
                    if($examine_set!=1){
                        return ['status'=>0,'msg'=>'不能操作已生成退货单的数据'];
                    }
                    $this->error('不能反审核已生成退货单的数据');
                }
            }
            $admin = Session::get('admin');
            Db::startTrans();
            try {
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

                // 反审核后：向关联的销货订单写入操作日志
                foreach ($list as $unexaminedOrder) {
                    if (!empty($unexaminedOrder['sales_order_id'])) {
                        GoodsSalesOrderLog::writeLogByCode($unexaminedOrder['sales_order_id'], '采购订单反审核', '购货订单[' . $unexaminedOrder['purchase_code'] . ']反审核', 'purchase_order', $unexaminedOrder['id']);
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
                $this->success(sizeof($list).'条数据反审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('反审核失败');
            }
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
            $this->success(sizeof($list).'条数据二次审核成功');
        }else{
            $this->error('二次审核失败');
        }
    }

    // 检测供应商数量（用于前端判断是否需要弹窗）
    public function checkSupplierCount(){
        $order_id = isset($_POST['order_id']) ? trim($_POST['order_id']) : 0;
        if (empty($order_id)) {
            $this->error('参数错误');
        }
        $order = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])
            ->field('purchase_code, supplyer_code, supplier_count')
            ->find();
        if (empty($order)) {
            $this->error('参数错误');
        }
        // 从明细表统计供应商
        $supplyerCodes = Db::name('kuerp_goods_purchase_order_info')
            ->where(['purchase_code' => $order['purchase_code']])
            ->where('supplyer_code', '<>', '')
            ->distinct('supplyer_code')
            ->column('supplyer_code');
        $supplierCount = count($supplyerCodes);

        // 构建分组摘要
        $groups = [];
        if ($supplierCount > 1) {
            foreach ($supplyerCodes as $sc) {
                $items = Db::name('kuerp_goods_purchase_order_info')
                    ->where(['purchase_code' => $order['purchase_code'], 'supplyer_code' => $sc])
                    ->select();
                $itemCount = count($items);
                $totalMoney = 0;
                foreach ($items as $item) {
                    $totalMoney += (float)$item['all_money'];
                }
                $supplierName = Db::name('kuerp_suppliers')
                    ->where(['supplyer_code' => $sc])
                    ->value('supplyer_name');
                $groups[] = [
                    'supplyer_code' => $sc,
                    'supplyer_name' => $supplierName ?: $sc,
                    'item_count' => $itemCount,
                    'total_money' => round($totalMoney, 2),
                ];
            }
        }
        $this->success('', ['supplier_count' => $supplierCount, 'groups' => $groups]);
    }

    // 按供应商分组批量生成采购单
    public function batchGeneratePurchase(){
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (empty($order_id)) {
            $this->error('参数错误');
        }

        $order = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order['examine_status'] != '1') {
            $this->error('订单未审核，不可生成采购单');
        }

        $lines = Db::name('kuerp_goods_purchase_order_info')
            ->where(['purchase_code' => $order['purchase_code']])
            ->select();

        // 过滤已全部转换的行
        $lines = array_filter($lines, function($line) {
            $converted = Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $line['id']])
                ->sum('number');
            return ($line['number'] - $converted) > 0;
        });

        if (empty($lines)) {
            $this->error('此购货订单已全部生成采购单');
        }

        // 按供应商分组
        $groups = [];
        foreach ($lines as $line) {
            $sc = !empty($line['supplyer_code']) ? $line['supplyer_code'] : '未指定供应商';
            $groups[$sc][] = $line;
        }

        // 单供应商 → 返回重定向URL（走原来流程）
        if (count($groups) <= 1) {
            $this->success('', ['redirect' => true, 'url' => "kuerp/goods_purchase/add?ids={$order_id}"]);
            return;
        }

        // 多供应商 → 批量生成
        $results = [];
        Db::startTrans();
        try {
            // 订单全部明细价税合计与表头优惠，用于按组占比分摊优惠金额
            $orderTotalMoneyTaxPrice = (float)Db::name('kuerp_goods_purchase_order_info')
                ->where(['purchase_code' => $order['purchase_code']])
                ->sum('money_tax_price');
            $orderPreferentialRate = (float)($order['preferential_rate'] ?? 0);
            $orderPreferentialMoney = (float)($order['preferential_money'] ?? 0);

            foreach ($groups as $supplierCode => $items) {
                $purchaseCode = \addons\kuerp\library\Goods::getCodes('', 8);

                $totalNumber = 0;
                $totalAllMoney = 0;
                $totalTaxMoney = 0;
                $totalMoneyTaxPrice = 0;

                $detailData = [];
                foreach ($items as $item) {
                    $remaining = $item['number'] - Db::name('kuerp_goods_purord_info')
                        ->where(['order_id' => $item['id']])
                        ->sum('number');
                    if ($remaining <= 0) continue;

                    // 按剩余数量占订单数量的比例缩放金额（订单明细的 all_money/tax_money/money_tax_price 均已含行折扣）
                    $ratio = $item['number'] > 0 ? ($remaining / $item['number']) : 0;
                    $totalNumber += $remaining;
                    $lineAllMoney = round($item['all_money'] * $ratio, 2);
                    $totalAllMoney += $lineAllMoney;
                    $lineTaxMoney = round($item['tax_money'] * $ratio, 2);
                    $totalTaxMoney += $lineTaxMoney;
                    $lineMoneyTaxPrice = round($item['money_tax_price'] * $ratio, 2);
                    $totalMoneyTaxPrice += $lineMoneyTaxPrice;
                    $lineDisMoney = round($item['dis_money'] * $ratio, 2);

                    $detailData[] = [
                        'purchase_code'     => $purchaseCode,
                        'supplyer_code'     => $supplierCode,
                        'goods_code'        => $item['goods_code'],
                        'goods_unit'        => $item['goods_unit'],
                        'stores_id'         => $item['stores_id'],
                        'number'            => $remaining,
                        'default_unit'      => $item['default_unit'],
                        'basic_nmber'       => $item['basic_nmber'],
                        'unit_price'        => $item['unit_price'],
                        'tax_included'      => $item['tax_included'],
                        'discount'          => $item['discount'],
                        'dis_money'         => $lineDisMoney,
                        'all_money'         => $lineAllMoney,
                        'tax_rate'          => $item['tax_rate'],
                        'tax_money'         => $lineTaxMoney,
                        'money_tax_price'   => $lineMoneyTaxPrice,
                        'preferential_rate' => $item['preferential_rate'],
                        'preferential_money'=> $item['preferential_money'],
                        'order_info_id'     => $item['id'],
                        'purchase_order_id' => $order['purchase_code'],
                        'sales_order_id'    => $item['sales_order_id'],
                        'goods_attr'        => $item['goods_attr'],
                        'goods_note'        => $item['goods_note'],
                        'allow_stock'       => $item['allow_stock'],
                        'purchase_time'     => $item['purchase_time'],
                        'delivery_time'     => $item['delivery_time'],
                        'remark'            => $item['remark'],
                        'preparation_id'    => $this->auth->id,
                        'operation_id'      => $this->auth->id,
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }

                // 获取真实的supplyer_code（处理"未指定供应商"的情况）
                $realSupplierCode = $supplierCode === '未指定供应商' ? '' : $supplierCode;

                // 表头优惠金额按本组价税合计占比分摊
                $groupPreferential = 0;
                if ($orderTotalMoneyTaxPrice > 0 && $orderPreferentialMoney > 0) {
                    $groupPreferential = round($orderPreferentialMoney * ($totalMoneyTaxPrice / $orderTotalMoneyTaxPrice), 2);
                }
                // 实付金额 = 本组价税合计 - 本组分摊优惠
                $realMoney = round($totalMoneyTaxPrice - $groupPreferential, 2);

                // 生成采购单默认未付款，本次欠款 = 实付金额；总欠款 = 本次欠款 + 供应商已有欠款
                $giveAccount = Db::name('kuerp_suppliers')->where(['supplyer_code' => $realSupplierCode])->value('need_give_account');
                $giveAccount = $giveAccount ? (float)$giveAccount : 0;

                // 插入采购单主表
                $purchaseMainId = Db::name('kuerp_goods_purchase')->insertGetId([
                    'purchase_code'     => $purchaseCode,
                    'purchase_type'     => $order['purchase_type'],
                    'purchase_time'     => time(),
                    'delivery_time'     => $order['delivery_time'],
                    'supplyer_code'     => $realSupplierCode,
                    'number'            => $totalNumber,
                    'all_money'         => $totalAllMoney,
                    'tax_money'         => $totalTaxMoney,
                    'preferential_rate' => $orderPreferentialRate,
                    'preferential_money'=> $groupPreferential,
                    'real_money'        => $realMoney,
                    'arrears'           => $realMoney,
                    'all_arrears'       => round($realMoney + $giveAccount, 2),
                    'pay_money'         => 0,
                    'purchase_order_id' => $order['purchase_code'],
                    'sales_order_id'    => $order['sales_order_id'],
                    'preparation_id'    => $this->auth->id,
                    'operation_id'      => $this->auth->id,
                    'pay_status'        => '0',
                    'examine_status'    => '0',
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);

                // 插入采购单明细
                foreach ($detailData as $dv) {
                    $orderInfoId = $dv['order_info_id'];
                    unset($dv['order_info_id']);
                    $detailId = Db::name('kuerp_goods_purchase_info')->insertGetId($dv);
                    if (!empty($orderInfoId)) {
                        Db::name('kuerp_goods_purord_info')->insert([
                            'order_id'        => $orderInfoId,
                            'purchase_id'     => $detailId,
                            'number'          => $dv['number'],
                            'all_money'       => $dv['all_money'],
                            'tax_rate'        => $dv['tax_rate'],
                            'tax_money'       => $dv['tax_money'],
                            'money_tax_price' => $dv['money_tax_price'],
                            'status'          => '0',
                        ]);
                    }
                }

                // 插入 purord 关联
                Db::name('kuerp_goods_purord')->insert([
                    'order_id'    => $order_id,
                    'purchase_id' => $purchaseMainId,
                ]);

                // 写入销货订单日志
                if (!empty($order['sales_order_id'])) {
                    \app\admin\model\kuerp\GoodsSalesOrderLog::writeLogByCode(
                        $order['sales_order_id'],
                        '生成采购单',
                        "生成采购单：{$purchaseCode}（供应商：{$realSupplierCode}）",
                        'purchase',
                        $purchaseMainId
                    );
                }

                $results[] = [
                    'purchase_code' => $purchaseCode,
                    'supplier_code' => $realSupplierCode,
                    'item_count'    => count($detailData),
                ];
            }

            // 更新订单表头
            Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->update([
                'supplier_count' => count($groups),
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('生成采购单失败：' . $e->getMessage());
        }

        $this->success('成功生成' . count($results) . '个采购单', ['results' => $results]);
    }

    // 按单个供应商生成采购单
    public function generateBySupplier(){
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $supplyer_code = isset($_POST['supplyer_code']) ? trim($_POST['supplyer_code']) : '';

        if (empty($order_id)) {
            $this->error('参数错误');
        }

        $order = Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order['examine_status'] != '1') {
            $this->error('订单未审核，不可生成采购单');
        }

        // 查询该供应商的订单明细行
        $lines = Db::name('kuerp_goods_purchase_order_info')
            ->where(['purchase_code' => $order['purchase_code']])
            ->where(function($query) use ($supplyer_code) {
                if (!empty($supplyer_code)) {
                    $query->where(['supplyer_code' => $supplyer_code]);
                } else {
                    $query->where('supplyer_code', '=', '')->whereOr('supplyer_code', 'is', null);
                }
            })
            ->select();

        if (empty($lines)) {
            $this->error('该供应商没有可生成采购单的商品');
        }

        // 过滤已全部转换的行
        $lines = array_filter($lines, function($line) {
            $converted = Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $line['id']])
                ->sum('number');
            return ($line['number'] - $converted) > 0;
        });

        if (empty($lines)) {
            $this->error('该供应商的商品已全部生成采购单');
        }

        $purchaseCode = \addons\kuerp\library\Goods::getCodes('', 8);
        $totalNumber = 0;
        $totalAllMoney = 0;
        $totalTaxMoney = 0;
        $totalMoneyTaxPrice = 0;

        // 订单全部明细价税合计与表头优惠，用于按占比分摊优惠金额
        $orderTotalMoneyTaxPrice = (float)Db::name('kuerp_goods_purchase_order_info')
            ->where(['purchase_code' => $order['purchase_code']])
            ->sum('money_tax_price');
        $orderPreferentialRate = (float)($order['preferential_rate'] ?? 0);
        $orderPreferentialMoney = (float)($order['preferential_money'] ?? 0);

        $detailData = [];
        foreach ($lines as $item) {
            $remaining = $item['number'] - Db::name('kuerp_goods_purord_info')
                ->where(['order_id' => $item['id']])
                ->sum('number');
            if ($remaining <= 0) continue;

            // 按剩余数量占订单数量的比例缩放金额（订单明细的 all_money/tax_money/money_tax_price 均已含行折扣）
            $ratio = $item['number'] > 0 ? ($remaining / $item['number']) : 0;
            $totalNumber += $remaining;
            $lineAllMoney = round($item['all_money'] * $ratio, 2);
            $totalAllMoney += $lineAllMoney;
            $lineTaxMoney = round($item['tax_money'] * $ratio, 2);
            $totalTaxMoney += $lineTaxMoney;
            $lineMoneyTaxPrice = round($item['money_tax_price'] * $ratio, 2);
            $totalMoneyTaxPrice += $lineMoneyTaxPrice;
            $lineDisMoney = round($item['dis_money'] * $ratio, 2);

            $detailData[] = [
                'purchase_code'     => $purchaseCode,
                'supplyer_code'     => $supplyer_code,
                'goods_code'        => $item['goods_code'],
                'goods_unit'        => $item['goods_unit'],
                'stores_id'         => $item['stores_id'],
                'number'            => $remaining,
                'default_unit'      => $item['default_unit'],
                'basic_nmber'       => $item['basic_nmber'],
                'unit_price'        => $item['unit_price'],
                'tax_included'      => $item['tax_included'],
                'discount'          => $item['discount'],
                'dis_money'         => $lineDisMoney,
                'all_money'         => $lineAllMoney,
                'tax_rate'          => $item['tax_rate'],
                'tax_money'         => $lineTaxMoney,
                'money_tax_price'   => $lineMoneyTaxPrice,
                'preferential_rate' => $item['preferential_rate'],
                'preferential_money'=> $item['preferential_money'],
                'order_info_id'     => $item['id'],
                'purchase_order_id' => $order['purchase_code'],
                'sales_order_id'    => $item['sales_order_id'],
                'goods_attr'        => $item['goods_attr'],
                'goods_note'        => $item['goods_note'],
                'allow_stock'       => $item['allow_stock'],
                'purchase_time'     => $item['purchase_time'],
                'delivery_time'     => $item['delivery_time'],
                'remark'            => $item['remark'],
                'preparation_id'    => $this->auth->id,
                'operation_id'      => $this->auth->id,
                'createtime'        => time(),
                'updatetime'        => time(),
            ];
        }

        if (empty($detailData)) {
            $this->error('没有可生成的数据');
        }

        // 表头优惠金额按本组价税合计占比分摊
        $groupPreferential = 0;
        if ($orderTotalMoneyTaxPrice > 0 && $orderPreferentialMoney > 0) {
            $groupPreferential = round($orderPreferentialMoney * ($totalMoneyTaxPrice / $orderTotalMoneyTaxPrice), 2);
        }
        // 实付金额 = 本组价税合计 - 本组分摊优惠
        $realMoney = round($totalMoneyTaxPrice - $groupPreferential, 2);

        // 生成采购单默认未付款，本次欠款 = 实付金额；总欠款 = 本次欠款 + 供应商已有欠款
        $giveAccount = Db::name('kuerp_suppliers')->where(['supplyer_code' => $supplyer_code])->value('need_give_account');
        $giveAccount = $giveAccount ? (float)$giveAccount : 0;

        Db::startTrans();
        try {
            $purchaseMainId = Db::name('kuerp_goods_purchase')->insertGetId([
                'purchase_code'     => $purchaseCode,
                'purchase_type'     => $order['purchase_type'],
                'purchase_time'     => time(),
                'delivery_time'     => $order['delivery_time'],
                'supplyer_code'     => $supplyer_code,
                'number'            => $totalNumber,
                'all_money'         => $totalAllMoney,
                'tax_money'         => $totalTaxMoney,
                'preferential_rate' => $orderPreferentialRate,
                'preferential_money'=> $groupPreferential,
                'real_money'        => $realMoney,
                'arrears'           => $realMoney,
                'all_arrears'       => round($realMoney + $giveAccount, 2),
                'pay_money'         => 0,
                'purchase_order_id' => $order['purchase_code'],
                'sales_order_id'    => $order['sales_order_id'],
                'preparation_id'    => $this->auth->id,
                'operation_id'      => $this->auth->id,
                'pay_status'        => '0',
                'examine_status'    => '0',
                'createtime'        => time(),
                'updatetime'        => time(),
            ]);

            foreach ($detailData as $dv) {
                $orderInfoId = $dv['order_info_id'];
                unset($dv['order_info_id']);
                $detailId = Db::name('kuerp_goods_purchase_info')->insertGetId($dv);
                if (!empty($orderInfoId)) {
                    Db::name('kuerp_goods_purord_info')->insert([
                        'order_id'        => $orderInfoId,
                        'purchase_id'     => $detailId,
                        'number'          => $dv['number'],
                        'all_money'       => $dv['all_money'],
                        'tax_rate'        => $dv['tax_rate'],
                        'tax_money'       => $dv['tax_money'],
                        'money_tax_price' => $dv['money_tax_price'],
                        'status'          => '0',
                    ]);
                }
            }

            Db::name('kuerp_goods_purord')->insert([
                'order_id'    => $order_id,
                'purchase_id' => $purchaseMainId,
            ]);

            // 更新订单表头供应商计数
            $allSuppliers = Db::name('kuerp_goods_purchase_order_info')
                ->where(['purchase_code' => $order['purchase_code']])
                ->where('supplyer_code', '<>', '')
                ->distinct('supplyer_code')
                ->column('supplyer_code');
            Db::name('kuerp_goods_purchase_order')->where(['id' => $order_id])->update([
                'supplier_count' => count($allSuppliers) ?: 1,
            ]);

            if (!empty($order['sales_order_id'])) {
                \app\admin\model\kuerp\GoodsSalesOrderLog::writeLogByCode(
                    $order['sales_order_id'],
                    '生成采购单',
                    "生成采购单：{$purchaseCode}（供应商：{$supplyer_code}）",
                    'purchase',
                    $purchaseMainId
                );
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('生成采购单失败：' . $e->getMessage());
        }

        $this->success('成功生成采购单：' . $purchaseCode);
    }

    //检测可生成采购单
    public function checkPurchase(){
        $order_id = isset($_POST['order_id'])?trim($_POST['order_id']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_purchase_order')->where(['id'=>$order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine==1){
            if($row['examine_status']!=1){
                $this->error('此购货订单未审核，不可生成采购单');
            }
            if($row['second_examine_status']!=1){
                $this->error('此购货订单未通过二次审核，不可生成采购单');
            }
        }
        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_purord_info')->where(['order_id'=>$purchase_info_v['id']])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此购货订单已全部生成采购单');
        }
        $this->success();
    }
    public function checkExchange(){
        $order_id = isset($_POST['order_id'])?trim($_POST['order_id']):0;
        if(empty($order_id)){
            $this->error('参数错误');
        }
        $row = Db::name('kuerp_goods_purchase_order')->where(['id'=>$order_id])
            ->field('examine_status,second_examine_status,purchase_code')
            ->find();
        if(empty($row)){
            $this->error('参数错误');
        }
        $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
        if($examine==1){
            if($row['examine_status']!=1){
                $this->error('此购货订单未审核，不可生成退货单');
            }
            if($row['second_examine_status']!=1){
                $this->error('此购货订单未通过二次审核，不可生成退货单');
            }
        }
        $purchase_info = Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
            $purord_info = Db::name('kuerp_goods_excpur_info')->where(['purchase_id'=>$purchase_info_v['id'],'type'=>'1'])->field('sum(number) as number,sum(money) as money')->select();
            $gp_number = isset($purord_info[0]['number'])?$purord_info[0]['number']:0;
            if($purchase_info_v['number']-$gp_number<=0){
                unset($purchase_info[$purchase_info_k]);
            }
        }
        if(empty($purchase_info)){
            $this->error('此购货订单已全部生成退货单');
        }
        $this->success();
    }

    /**
     * 购货订单明细Excel模板的列定义（导出与导入共用同一套定义）
     * alias 用于导入时容错匹配表头；required 的列在表头带 * 标记
     */
    private function excelColumns()
    {
        return [
            ['field' => 'goods_code',     'title' => '商品编号', 'alias' => ['商品编号', '商品编码', 'goods_code'],              'required' => true],
            ['field' => 'goods_name',     'title' => '商品名称', 'alias' => ['商品名称', '商品名', 'goods_name'],                'required' => false],
            ['field' => 'unit_name',      'title' => '商品单位', 'alias' => ['商品单位', '单位', 'unit'],                        'required' => true],
            ['field' => 'store_name',     'title' => '仓库',     'alias' => ['仓库', '仓库名称', 'stores'],                      'required' => true],
            ['field' => 'supplier_label', 'title' => '供应商',   'alias' => ['供应商', '供应商名称', 'supplier'],                'required' => false],
            ['field' => 'number',         'title' => '数量',     'alias' => ['数量', 'number'],                                  'required' => true],
            ['field' => 'unit_price',     'title' => '采购单价', 'alias' => ['采购单价', '单价', '不含税单价', 'unit_price'],    'required' => false],
            ['field' => 'discount',       'title' => '折扣率%',  'alias' => ['折扣率', '折扣', 'discount'],                      'required' => false],
            ['field' => 'tax_rate',       'title' => '税率%',    'alias' => ['税率', 'tax_rate'],                                'required' => false],
            ['field' => 'remark',         'title' => '备注',     'alias' => ['备注', 'remark'],                                  'required' => false],
        ];
    }

    /**
     * 表头归一化：去掉空白与 * 必填标记、去掉结尾的 % 单位，便于容错匹配
     */
    private function normalizeExcelHeader($title)
    {
        $title = (string)$title;
        $title = preg_replace('/[\s\*\x{3000}]+/u', '', $title);
        return rtrim($title, '%');
    }

    /**
     * 解析表头行，返回 内部字段 => 列号(从1开始)
     */
    private function mapExcelColumns($sheet, $headerRow)
    {
        $byAlias = [];
        foreach ($this->excelColumns() as $col) {
            foreach (array_merge([$col['title']], $col['alias']) as $alias) {
                $byAlias[$this->normalizeExcelHeader($alias)] = $col['field'];
            }
        }
        $map    = [];
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($i = 1; $i <= $maxCol; $i++) {
            $head = $this->normalizeExcelHeader($sheet->getCellByColumnAndRow($i, $headerRow)->getValue());
            if ($head !== '' && isset($byAlias[$head]) && !isset($map[$byAlias[$head]])) {
                $map[$byAlias[$head]] = $i;
            }
        }
        return $map;
    }

    /**
     * Excel 单元格值转字符串：避免纯数字的商品编号被读成科学计数法或丢掉前导零
     */
    private function excelCellString($value)
    {
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_float($value)) {
            return (floor($value) == $value)
                ? number_format($value, 0, '', '')
                : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        return trim((string)$value);
    }

    /**
     * 导出购货订单明细Excel（与导入共用同一份模板）
     *
     * 商品编号 / 商品单位 / 仓库 / 供应商 四列为下拉选择，下拉数据全部取自系统资料；
     * 资料变动后重新点一次「导出」即可得到最新模板。
     * 明细为空时导出的即为空白模板。
     */
    public function export()
    {
        $rows = $this->request->post('rows');
        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        }
        if (!is_array($rows)) {
            $rows = [];
        }
        $purchaseCode = trim((string)$this->request->post('purchase_code', ''));

        $columns = $this->excelColumns();

        // ---------- 下拉数据源（与页面上的下拉保持同一取数口径：仅启用状态） ----------
        $goodsList = Db::name('kuerp_goods')->where(['status' => '1'])
            ->field('goods_code,goods_name')->order('goods_code', 'ASC')->select();
        $storeList = Db::name('kuerp_stores')->where(['status' => '1'])
            ->field('id,name')->order('id', 'ASC')->select();
        $supplierList = Db::name('kuerp_suppliers')->where(['status' => '1'])
            ->field('supplyer_code,supplyer_name')->order('supplyer_code', 'ASC')->select();

        // 单位名称去重（同名单位可能对应多条单位记录）
        $unitNames = [];
        foreach (Db::name('kuerp_unit')->field('id,name')->order('id', 'ASC')->select() as $unit) {
            $unitName = trim((string)$unit['name']);
            if ($unitName !== '' && !in_array($unitName, $unitNames, true)) {
                $unitNames[] = $unitName;
            }
        }

        $numberDecimal = (int)Db::name('kuerp_sets')->where(['name' => 'number_decimal'])->value('value');
        $priceDecimal  = (int)Db::name('kuerp_sets')->where(['name' => 'price_decimal'])->value('value');
        $numberFormat  = '0' . ($numberDecimal > 0 ? '.' . str_repeat('0', $numberDecimal) : '');
        $priceFormat   = '0' . ($priceDecimal > 0 ? '.' . str_repeat('0', $priceDecimal) : '');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('微软雅黑')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('购货订单明细');

        // 列号 <-> 字段 的映射，避免正文里出现魔法字母
        $letterByField = [];
        $fieldByLetter = [];
        foreach ($columns as $k => $col) {
            $letter = Coordinate::stringFromColumnIndex($k + 1);
            $letterByField[$col['field']] = $letter;
            $fieldByLetter[$letter]       = $col['field'];
        }
        $lastColLetter = Coordinate::stringFromColumnIndex(count($columns));

        // ---------- 隐藏的选项表（下拉数据源） ----------
        $opt = $spreadsheet->createSheet();
        $opt->setTitle(self::EXCEL_OPT_SHEET);
        $opt->fromArray(['商品编号', '商品名称', '商品单位', '仓库', '供应商', '供应商编号'], null, 'A1');
        foreach ($goodsList as $k => $goods) {
            $opt->setCellValueExplicit('A' . ($k + 2), (string)$goods['goods_code'], DataType::TYPE_STRING);
            $opt->setCellValueExplicit('B' . ($k + 2), (string)$goods['goods_name'], DataType::TYPE_STRING);
        }
        foreach ($unitNames as $k => $unitName) {
            $opt->setCellValueExplicit('C' . ($k + 2), $unitName, DataType::TYPE_STRING);
        }
        foreach ($storeList as $k => $store) {
            $opt->setCellValueExplicit('D' . ($k + 2), (string)$store['name'], DataType::TYPE_STRING);
        }
        foreach ($supplierList as $k => $supplier) {
            $opt->setCellValueExplicit('E' . ($k + 2), trim($supplier['supplyer_code'] . ' ' . $supplier['supplyer_name']), DataType::TYPE_STRING);
            $opt->setCellValueExplicit('F' . ($k + 2), (string)$supplier['supplyer_code'], DataType::TYPE_STRING);
        }
        $opt->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // ---------- 明细表 ----------
        foreach ($columns as $col) {
            $sheet->setCellValue($letterByField[$col['field']] . '1', $col['title'] . ($col['required'] ? '*' : ''));
        }

        $firstDataRow = 2;
        $lastDataRow  = $firstDataRow + max(count($rows), self::EXCEL_TEMPLATE_ROWS) - 1;

        // 下拉校验
        $optRange = [
            'goods_code'     => self::EXCEL_OPT_SHEET . '!$A$2:$A$' . (count($goodsList) + 1),
            'unit_name'      => self::EXCEL_OPT_SHEET . '!$C$2:$C$' . (count($unitNames) + 1),
            'store_name'     => self::EXCEL_OPT_SHEET . '!$D$2:$D$' . (count($storeList) + 1),
            'supplier_label' => self::EXCEL_OPT_SHEET . '!$E$2:$E$' . (count($supplierList) + 1),
        ];
        $optPrompt = [
            'goods_code'     => ['商品编号', '请从下拉列表中选择商品编号；下拉数据取自系统商品资料'],
            'unit_name'      => ['商品单位', '请从下拉列表中选择；留空则按商品的默认单位'],
            'store_name'     => ['仓库', '请从下拉列表中选择；留空则按商品的默认仓库'],
            'supplier_label' => ['供应商', '请从下拉列表中选择；留空则按商品绑定的默认供应商'],
        ];
        foreach ($fieldByLetter as $letter => $field) {
            if (!isset($optRange[$field])) {
                continue;
            }
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setOperator('');   // 列表校验不需要 operator，避免写出多余的 operator="between"
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorStyle(DataValidation::STYLE_WARNING);
            $validation->setErrorTitle('提示');
            $validation->setError('请从下拉列表中选择，或确认后继续。');
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle($optPrompt[$field][0]);
            $validation->setPrompt($optPrompt[$field][1]);
            $validation->setFormula1($optRange[$field]);
            $sheet->setDataValidation($letter . $firstDataRow . ':' . $letter . $lastDataRow, $validation);
        }

        // 数量 / 金额类：数字校验 + 数字格式
        $numericRules = [
            $letterByField['number']     => [$numberFormat, '请填写大于 0 的数字。', DataValidation::OPERATOR_GREATERTHAN, '0'],
            $letterByField['unit_price'] => [$priceFormat,  '请填写不小于 0 的数字。', DataValidation::OPERATOR_GREATERTHANOREQUAL, '0'],
            $letterByField['discount']   => [$priceFormat,  '请填写 0 到 100 之间的数字（百分数）。', DataValidation::OPERATOR_BETWEEN, '0', '100'],
            $letterByField['tax_rate']   => [$priceFormat,  '请填写 0 到 100 之间的数字（百分数）。', DataValidation::OPERATOR_BETWEEN, '0', '100'],
        ];
        foreach ($numericRules as $letter => $rule) {
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_DECIMAL);
            $validation->setOperator($rule[2]);
            $validation->setAllowBlank(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorStyle(DataValidation::STYLE_WARNING);
            $validation->setErrorTitle('提示');
            $validation->setError($rule[1]);
            $validation->setFormula1($rule[3]);
            if (isset($rule[4])) {
                $validation->setFormula2($rule[4]);
            }
            $sheet->setDataValidation($letter . $firstDataRow . ':' . $letter . $lastDataRow, $validation);
            $sheet->getStyle($letter . $firstDataRow . ':' . $letter . $lastDataRow)
                ->getNumberFormat()->setFormatCode($rule[0]);
        }

        // 商品名称列：公式自动带出，导入时以商品编号为准
        $goodsRange = self::EXCEL_OPT_SHEET . '!$A$2:$B$' . (count($goodsList) + 1);
        for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
            $sheet->setCellValue(
                $letterByField['goods_name'] . $r,
                '=IFERROR(VLOOKUP($' . $letterByField['goods_code'] . $r . ',' . $goodsRange . ',2,0),"")'
            );
        }

        // 回填当前明细
        $r = $firstDataRow;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $goodsCode = trim((string)(isset($row['goods_code']) ? $row['goods_code'] : ''));
            if ($goodsCode === '') {
                continue;
            }
            $sheet->setCellValueExplicit($letterByField['goods_code'] . $r, $goodsCode, DataType::TYPE_STRING);
            foreach (['unit_name', 'store_name', 'supplier_label', 'remark'] as $field) {
                $val = isset($row[$field]) ? trim((string)$row[$field]) : '';
                if ($val !== '') {
                    $sheet->setCellValueExplicit($letterByField[$field] . $r, $val, DataType::TYPE_STRING);
                }
            }
            foreach (['number', 'unit_price', 'discount', 'tax_rate'] as $field) {
                $val = isset($row[$field]) ? $row[$field] : '';
                if ($val !== '' && $val !== null && is_numeric($val)) {
                    $sheet->setCellValue($letterByField[$field] . $r, (float)$val);
                }
            }
            $r++;
        }

        // 表头样式
        $sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C8DBC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getStyle('A' . $firstDataRow . ':' . $lastColLetter . $lastDataRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        ]);

        $widths = [
            'goods_code' => 16, 'goods_name' => 34, 'unit_name' => 10, 'store_name' => 14,
            'supplier_label' => 30, 'number' => 12, 'unit_price' => 12, 'discount' => 10,
            'tax_rate' => 10, 'remark' => 26,
        ];
        foreach ($letterByField as $field => $letter) {
            $sheet->getColumnDimension($letter)->setWidth(isset($widths[$field]) ? $widths[$field] : 14);
        }
        $sheet->freezePane('A' . $firstDataRow);

        // ---------- 填写说明 ----------
        $note = $spreadsheet->createSheet();
        $note->setTitle('填写说明');
        $noteRows = [
            '购货订单明细  导入 / 导出 模板说明',
            '1、本模板同时用于「导出」与「导入」，导出与导入必须使用同一份模板。',
            '2、导入会【替换】购货订单编辑页当前的全部明细行，请先确认页面上的明细已导出备份。',
            '3、带 * 的列为必填：商品编号、商品单位、仓库、数量。',
            '4、商品编号、商品单位、仓库、供应商 四列均为下拉选择，下拉数据取自系统资料；',
            '     若下拉中没有需要的选项，请先在系统对应模块中新增资料，然后重新点击「导出」生成新模板。',
            '5、商品名称列由公式自动带出，无需填写；导入时以商品编号为准。',
            '6、采购单价为不含税单价；折扣率、税率填百分数（如 13 表示 13%）。',
            '     这三列留空时，按商品档案 / 供应商的默认值自动计算。',
            '7、含税单价、购货金额、税额、价税合计由系统自动计算，无需填写。',
            '8、商品单位 / 仓库 / 供应商 留空时，分别按商品的默认单位、默认仓库、默认供应商处理。',
            '9、商品编号不存在、仓库名称不正确、数量为空或非数字时，导入会被拒绝并提示具体行号。',
            '10、明细行数超过模板预留行数时，可复制最后一行的公式继续填写（商品名称列公式会自动跟随）。',
        ];
        foreach ($noteRows as $k => $line) {
            $note->setCellValue('A' . ($k + 1), $line);
        }
        $note->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $note->getColumnDimension('A')->setWidth(105);

        $spreadsheet->setActiveSheetIndex(0);

        // ---------- 输出下载 ----------
        $title = '购货订单导入模板';
        if ($purchaseCode !== '') {
            $safeCode = str_replace(['"', "'", '\\', '/', "\r", "\n", ';', ':'], '', $purchaseCode);
            if ($safeCode !== '') {
                $title = '购货订单明细_' . $safeCode;
            }
        }
        $stamp = date('YmdHis');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="purchase_order_' . $stamp . '.xlsx";filename*=UTF-8\'\'' . rawurlencode($title . '_' . $stamp . '.xlsx'));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    /**
     * 解析导入的Excel（与「导出」共用同一份模板），返回明细行供前端替换表格
     *
     * 只做解析与校验，不写库；由前端把结果填进表单后再走正常的保存流程。
     */
    public function parseimport()
    {
        $file = $this->request->file('file');
        if (!$file) {
            $this->error('请选择要导入的Excel文件');
        }
        $fileInfo = $file->getInfo();
        $fileName = isset($fileInfo['name']) ? $fileInfo['name'] : '';
        $tmpPath  = isset($fileInfo['tmp_name']) ? $fileInfo['tmp_name'] : '';
        if (!in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            $this->error('仅支持 .xlsx / .xls 格式的Excel文件');
        }
        if (!$tmpPath || !is_file($tmpPath)) {
            $this->error('文件上传失败，请重新选择文件');
        }
        if (filesize($tmpPath) > 10 * 1024 * 1024) {
            $this->error('文件过大，请确认使用的是本系统导出的模板（不超过10M）');
        }

        try {
            $reader = IOFactory::createReaderForFile($tmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpPath);
        } catch (Exception $e) {
            $this->error('Excel文件解析失败，请确认文件未损坏：' . $e->getMessage());
        }

        $sheet = $spreadsheet->getSheetByName('购货订单明细');
        if (!$sheet) {
            $sheet = $spreadsheet->getSheet(0);
        }

        // 定位表头行（容错：允许表头前有标题行）
        $maxCol    = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $headerRow = 0;
        $scanTo    = min(10, $sheet->getHighestDataRow());
        for ($r = 1; $r <= $scanTo && !$headerRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                if ($this->normalizeExcelHeader($sheet->getCellByColumnAndRow($c, $r)->getValue()) === '商品编号') {
                    $headerRow = $r;
                    break;
                }
            }
        }
        if (!$headerRow) {
            $this->error('未识别到表头，请使用系统「导出」生成的模板填写后再导入');
        }

        $map = $this->mapExcelColumns($sheet, $headerRow);
        if (!isset($map['goods_code']) || !isset($map['number'])) {
            $this->error('模板缺少必填列（商品编号 / 数量），请使用系统「导出」生成的模板');
        }

        // ---------- 参照资料 ----------
        $goodsMap = [];
        foreach (Db::name('kuerp_goods')->where(['status' => '1'])
            ->field('id,goods_code,goods_name,unit_id,more_unit,manager_warehouse_id')->select() as $goods) {
            $goodsMap[$goods['goods_code']] = $goods;
        }
        $storeByName = [];
        $storeById   = [];
        foreach (Db::name('kuerp_stores')->where(['status' => '1'])->field('id,name')->select() as $store) {
            $storeByName[trim($store['name'])] = (int)$store['id'];
            $storeById[(int)$store['id']]      = $store['name'];
        }
        $unitByName = [];
        foreach (Db::name('kuerp_unit')->field('id,name')->select() as $unit) {
            $unitByName[trim($unit['name'])][] = (int)$unit['id'];
        }
        $convByMain = [];
        foreach (Db::name('kuerp_unit_conversion')->field('main_unit,vice_unit')->select() as $conv) {
            $convByMain[(int)$conv['main_unit']][] = (int)$conv['vice_unit'];
        }
        $supplierByLabel = [];
        $supplierCodes   = [];
        foreach (Db::name('kuerp_suppliers')->where(['status' => '1'])->field('supplyer_code,supplyer_name')->select() as $supplier) {
            $supplierByLabel[trim($supplier['supplyer_code'] . ' ' . $supplier['supplyer_name'])] = $supplier['supplyer_code'];
            $supplierCodes[$supplier['supplyer_code']] = true;
        }

        $cellValue = function ($rowIdx, $field) use ($sheet, $map) {
            if (!isset($map[$field])) {
                return '';
            }
            return $sheet->getCellByColumnAndRow($map[$field], $rowIdx)->getValue();
        };
        $cellText = function ($rowIdx, $field) use ($cellValue) {
            return $this->excelCellString($cellValue($rowIdx, $field));
        };

        // ---------- 逐行解析 ----------
        $rows     = [];
        $errors   = [];
        $warnings = [];
        $lastRow  = $sheet->getHighestDataRow();

        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            $goodsCode = $cellText($r, 'goods_code');
            if ($goodsCode === '') {
                continue;   // 空行跳过
            }
            $goods = isset($goodsMap[$goodsCode]) ? $goodsMap[$goodsCode] : null;
            if (!$goods) {
                $errors[] = '第' . $r . '行：商品编号「' . $goodsCode . '」不存在';
                continue;
            }

            $numberRaw = $cellText($r, 'number');
            if ($numberRaw === '' || !is_numeric($numberRaw) || (float)$numberRaw <= 0) {
                $errors[] = '第' . $r . '行：数量必须为大于 0 的数字';
                continue;
            }

            // 仓库：留空则回退到商品的默认仓库
            $storeName = $cellText($r, 'store_name');
            if ($storeName !== '') {
                if (!isset($storeByName[$storeName])) {
                    $errors[] = '第' . $r . '行：仓库「' . $storeName . '」不存在，请使用下拉选项';
                    continue;
                }
                $storesId = $storeByName[$storeName];
            } else {
                $storesId = (int)$goods['manager_warehouse_id'];
                if (!$storesId || !isset($storeById[$storesId])) {
                    $errors[] = '第' . $r . '行：未填写仓库，且商品「' . $goodsCode . '」未设置默认仓库';
                    continue;
                }
                $warnings[] = '第' . $r . '行：未填写仓库，已按商品默认仓库「' . $storeById[$storesId] . '」处理';
            }

            // 单位：在该商品的可用单位里按名称匹配；留空或匹配不到则交给前端按默认单位处理
            $unitName = $cellText($r, 'unit_name');
            $unitId   = 0;
            if ($unitName !== '') {
                $validIds = [(int)$goods['unit_id']];
                if ((string)$goods['more_unit'] === '1' && isset($convByMain[(int)$goods['unit_id']])) {
                    foreach ($convByMain[(int)$goods['unit_id']] as $viceId) {
                        $validIds[] = $viceId;
                    }
                }
                foreach (isset($unitByName[$unitName]) ? $unitByName[$unitName] : [] as $candidate) {
                    if (in_array($candidate, $validIds, true)) {
                        $unitId = $candidate;
                        break;
                    }
                }
                if (!$unitId) {
                    $warnings[] = '第' . $r . '行：单位「' . $unitName . '」不适用于商品「' . $goodsCode . '」，已改用默认单位';
                }
            }

            // 供应商：支持「编号 名称」整串、单独编号；留空则按商品默认供应商
            $supplierLabel = $cellText($r, 'supplier_label');
            $supplyerCode  = '';
            if ($supplierLabel !== '') {
                if (isset($supplierByLabel[$supplierLabel])) {
                    $supplyerCode = $supplierByLabel[$supplierLabel];
                } elseif (isset($supplierCodes[$supplierLabel])) {
                    $supplyerCode = $supplierLabel;
                } else {
                    $codePart = explode(' ', $supplierLabel);
                    $codePart = trim($codePart[0]);
                    if ($codePart !== '' && isset($supplierCodes[$codePart])) {
                        $supplyerCode = $codePart;
                    } else {
                        $warnings[] = '第' . $r . '行：供应商「' . $supplierLabel . '」不存在，已忽略（按商品默认供应商处理）';
                    }
                }
            }

            // 价格类：允许留空，填了就必须是非负数字（容忍写成 13%）
            $prices     = ['unit_price' => '', 'discount' => '', 'tax_rate' => ''];
            $priceError = '';
            foreach (['unit_price' => '采购单价', 'discount' => '折扣率', 'tax_rate' => '税率'] as $field => $label) {
                $raw = trim(rtrim(trim($cellText($r, $field)), '%'));
                if ($raw === '') {
                    continue;
                }
                if (!is_numeric($raw)) {
                    $priceError = '第' . $r . '行：' . $label . '「' . $cellText($r, $field) . '」不是数字';
                    break;
                }
                if ((float)$raw < 0) {
                    $priceError = '第' . $r . '行：' . $label . '不能为负数';
                    break;
                }
                $prices[$field] = (float)$raw;
            }
            if ($priceError !== '') {
                $errors[] = $priceError;
                continue;
            }

            $rows[] = [
                'goods_code'    => $goodsCode,
                'goods_name'    => $goods['goods_name'],
                'goods_unit'    => $unitId,
                'stores_id'     => $storesId,
                'supplyer_code' => $supplyerCode,
                'number'        => (float)$numberRaw,
                'unit_price'    => $prices['unit_price'],
                'discount'      => $prices['discount'],
                'tax_rate'      => $prices['tax_rate'],
                'remark'        => mb_substr($cellText($r, 'remark'), 0, 255),
            ];
        }

        $spreadsheet->disconnectWorksheets();

        if (!empty($errors)) {
            $msg = implode('；', array_slice($errors, 0, 8));
            if (count($errors) > 8) {
                $msg .= '；……共 ' . count($errors) . ' 处问题';
            }
            $this->error($msg);
        }
        if (empty($rows)) {
            $this->error('未读取到有效明细行，请使用系统「导出」生成的模板填写后再导入');
        }

        $this->success('解析成功', null, [
            'rows'     => $rows,
            'warnings' => array_slice($warnings, 0, 10),
        ]);
    }
}
