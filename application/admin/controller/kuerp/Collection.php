<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 收款单
 *
 * @icon fa fa-circle-o
 */
class Collection extends Backend
{

    /**
     * Collection模型对象
     * @var \app\admin\model\kuerp\Collection
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Collection;
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("accountsList", $this->model->getAccountsList());
        $this->view->assign("saleList", $this->model->getSaleList());
        $this->view->assign("settlementtype", $this->model->getSettlementtype());
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
                ->with(['admin','staffmember'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('admin')->visible(['nickname']);
                $row->getRelation('staffmember')->visible(['name']);
            }
            $lists = [];
            $moneys = 0;$write_off = 0;
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list_v['print_num'] = 0;
                }
                $customers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->value('customer_name');
                $staff_member = Db::name('kuerp_staff_member')->where(['id'=>$list_v['staff_member_id']])->value('name');
                $preparation = Db::name('admin')->where(['id'=>$list_v['preparation_id']])->value('nickname');
                $examine = Db::name('admin')->where(['id'=>$list_v['examine_id']])->value('nickname');

                $account = Db::name('kuerp_collection_account')->alias('co')
                    ->join('kuerp_accounts a','a.id=co.accounts_id')
                    ->join('kuerp_settlementtype s','s.id=co.settlementtype','left')
                    ->where(['co.collection_id'=>$list_v['id']])
                    ->field('co.money,a.account_name,s.name,co.number,co.remark')
                    ->select();
                $moneys += $list_v['money'];$write_off += $list_v['write_off'];
                foreach ($account as $account_k=>$account_v){
                    $lists[] = [
                        'id'            => $list_v['id'],
                        'purchase_code' => $list_v['purchase_code'],
                        'purchase_time' => date('Y-m-d',$list_v['purchase_time']),
                        'customers_id'  => $customers,
                        'accounts_id'   => $account_v['account_name'],
                        'money'         => $account_v['money'],
                        'settlementtype'=> $account_v['name'],
                        'number'        => $account_v['number'],
                        'remark'        => $account_v['remark'],
                        'moneys'        => $list_v['money'],
                        'write_off'     => $list_v['write_off'],
                        'discount'      => $list_v['discount'],
                        'advance'       => $list_v['advance'],
                        'staff_member'  => $staff_member,
                        'examine'       => $examine,
                        'preparation'   => $preparation,
                        'remarks'       => $list_v['remarks'],
                        'print_num'     => $list_v['print_num'],
                    ];
                }
            }

            $result = array("total" => $list->total(), "rows" => $lists, "moneys" => $moneys, "write_off" => $write_off);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $checkout = (new \addons\kuerp\library\Checkout())->checkOutTime($params['purchase_time']);
                if($checkout!==true){
                    $this->error($checkout);
                }
                if(empty($params['customers_id'])){
                    $this->error('请选择客户');
                }
                $examine= Db::name('kuerp_sets')->where(['name'=>'examine'])->value('is_checked');
                $params = $this->preExcludeFields($params);

                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                //账户信息
                $account = [];
                $account_id = [];
                foreach ($params['accounts_id'] as $k=>$v){
                    if(!empty($v)){//有账户
                        $accounts = Db::name('kuerp_accounts')->where(['id'=>$v])->find();
                        if(empty($accounts)){
                            $this->error('账户信息错误');
                        }
                        if(in_array($v,$account_id)){
                            $this->error('不可多次选择相同的结算账户');
                        }else{
                            $account_id[] = $v;
                        }
                        $account[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'    => $params['purchase_code'],
                            'purchase_time'    => strtotime($params['purchase_time']),
                            'accounts_id'      => $v,
                            'money'            => isset($params['money'][$k])?(float)$params['money'][$k]:0,
                            'settlementtype'   => isset($params['settlementtype'][$k])?(float)$params['settlementtype'][$k]:0,
                            'number'           => isset($params['number'][$k])?$params['number'][$k]:'',
                            'remark'           => isset($params['remark'][$k])?$params['remark'][$k]:null,
                        ]);
                    }
                }
                $account_money = 0;//账户收支总金额
                foreach ($account as $account_k=>$account_v){
                    $account_money += $account_v['money'];
                }
                //账户信息结束
                //源单信息
                $sale = [];
                $sale_id = [];
                $log_arr = [];
                $log_add = [];
                foreach ($params['purchase_number'] as $k=>$v){
                    if(!empty($v) && !empty($params['purchase_type'][$k])){//有单号
                        $order = Db::name('kuerp_writeoff_source')->where(['id'=>$params['purchase_type'][$k]])->find();
                        if(empty($order)){
                            $this->error('源单信息查询错误');
                        }
                        if(in_array($v,$sale_id)){
                            $this->error('不可多次选择相同的源单');
                        }else{
                            $sale_id[] = $v;
                        }
                        $thisrow = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'    => $params['purchase_code'],
                            'purchase_time'    => strtotime($params['purchase_time']),
                            'purchase_number'  => $order['purchase_code'],
                            'business_name'    => isset($params['business_type'][$k])?$params['business_type'][$k]:null,
                            'business_type'    => $order['purchase_type'],
                            'times'            => $order['purchase_time'],
                            'money'            => $order['price'],
                            'write_off'        => isset($params['write_off'][$k])?(float)$params['write_off'][$k]:0,
                            'write_off_money'  => isset($params['write_off_money'][$k])?(float)$params['write_off_money'][$k]:0,
                            'write_off_price'  => isset($params['write_off_price'][$k])?(float)$params['write_off_price'][$k]:0,
                            'remark'           => $order['remark'],
                            'source_id'        => $order['id'],
                            'type'             => $order['type'],
                        ]);
                        $sale[] = $thisrow;

                        $where = [];
                        $where['source_id'] = $order['id'];
                        $log = Db::name('kuerp_writeoff_log')->where($where)->find();
                        if($log){
                            $status = true;
                            foreach ($log_arr as $log_arr_k=>$log_arr_v){
                                if($log_arr_v['id']==$log['id']){
                                    $status = false;
                                    $log_arr[$log_arr_k]['money'] += $thisrow['write_off_price'];
                                    $log_arr[$log_arr_k]['write_off'] -= $thisrow['write_off_price'];
                                }
                            }
                            if($status){
                                $log_arr[] = [
                                    'id' => $log['id'],
                                    'write_off' => $log['write_off']-$thisrow['write_off_price'],
                                    'money' => $log['money']+$thisrow['write_off_price'],
                                ];
                            }
                        }else{
                            $log_add[] = [
                                'source_id' => $order['id'],
                                'write_off' => $thisrow['write_off_money']-$thisrow['write_off_price'],
                                'money' => $thisrow['write_off_price'],
                            ];
                        }
                    }
                }
                $sale_money = 0;//账户收支总金额
                foreach ($sale as $sale_k=>$sale_v){
                    $sale_money += $sale_v['write_off_price'];
                }

                $business_type = !empty($sale)?2:1;
                $row = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'business_type'     => $business_type,
                    'customers_id'      =>  $params['customers_id'],
                    'money'             =>  $account_money,
                    'write_off'         =>  $sale_money,
                    'discount'          =>  isset($params['discount'])?(float)$params['discount']:0,
                    'advance'           =>  isset($params['advance'])?(float)$params['advance']:0,
                    'staff_member_id'   =>  isset($params['staff_member_id'])?(float)$params['staff_member_id']:null,
                    'remarks'           =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);

                if(empty($account)){
                    $this->error('请填写账户结算信息');
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

                    foreach ($account as $account_k=>$account_v){
                        $account[$account_k]['collection_id'] = $result;
                    }
                    Db::name('kuerp_collection_account')->insertAll($account);

                    foreach ($sale as $sale_k=>$sale_v){
                        $sale[$sale_k]['collection_id'] = $result;
                    }
                    Db::name('kuerp_collection_sale')->insertAll($sale);

                    if(!empty($log_arr)){
                        (new \app\admin\model\kuerp\WriteoffLog())->saveAll($log_arr);
                    }
                    if(!empty($log_add)){
                        (new \app\admin\model\kuerp\WriteoffLog())->insertAll($log_add);
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
        $code = \addons\kuerp\library\Goods::getCodes('',21);
        $this->view->assign("code", $code);
        // 从销货对账「去收款」进入：预选客户 + 自动填入该对账单所含销货单/退货单作为源单
        $reconId = (int)$this->request->get('recon_id');
        if ($reconId) {
            $preset = $this->buildReconSources($reconId);
            if (empty($preset)) {
                $this->error('对账单不存在');
            }
            $this->view->assign('row', ['customers_id' => $preset['customers_id']]);
            $this->assignconfig('reconId', $reconId);
            $this->assignconfig('presetSources', $preset['sources']);
        }
        return $this->view->fetch();
    }

    /**
     * 销货对账「去收款」的预填源单
     *
     * 取该对账单所含销货单/退货单在 kuerp_writeoff_source 中尚未核销的源单，
     * 字段结构与 Writeoff::chosereceivable 勾选后回传的数组保持一致，
     * 前端可复用同一段落行代码。
     *
     * @param int $reconId
     * @return array 对账单不存在时返回 []
     */
    protected function buildReconSources($reconId)
    {
        $recon = Db::name('kuerp_sales_recon')->where(['id' => $reconId])->field('id,recon_code,customers_id')->find();
        if (!$recon) {
            return [];
        }
        $codes = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $reconId])->column('doc_code');
        // 兜底：历史单据可能因审核入口不同（如从计划排期审核）没有核销源单，这里按单据明细补建，
        // 否则「去收款」预填不到任何源单（见 SalesRecon 的差异金额回写同款逻辑）
        $this->ensureReconSources($reconId, $codes);
        $sources = [];
        if (!empty($codes)) {
            // 与 Writeoff::chosereceivable 同口径：排除已核销为 0 的源单
            $list = Db::name('kuerp_writeoff_source')
                ->where(['purchase_code' => ['in', $codes], 'purchase_type' => '3'])
                ->where(function ($query) {
                    $query->where('id', 'NOT IN', function ($query) {
                        $query->name('kuerp_writeoff_log')->field('source_id');
                    })->whereOr('id', 'IN', function ($query) {
                        $query->name('kuerp_writeoff_log')->where(['write_off' => ['<>', '0']])->field('source_id');
                    });
                })
                ->order(['purchase_time' => 'ASC', 'id' => 'ASC'])
                ->select();
            foreach ($list as $row) {
                $log = Db::name('kuerp_writeoff_log')->where(['source_id' => $row['id']])->field('money,write_off')->find();
                if ($row['type'] == '1') {
                    $typeText = '普通销售';
                } elseif ($row['type'] == '4') {
                    $typeText = '核销';
                } else {
                    $typeText = '销售退货';
                }
                $sources[] = [
                    'purchase_number'  => $row['purchase_code'],
                    'purchase_type_id' => $row['id'],
                    'business_type'    => $typeText,
                    'times'            => $row['purchase_time'] ? date('Y-m-d', $row['purchase_time']) : '',
                    'sale_money'       => $row['price'],
                    'write_off'        => $log ? $log['money'] : 0,
                    'write_off_price'  => $log ? $log['write_off'] : $row['write_off'],
                    'purchase_numbers' => $row['purchase_code'],
                    'remark'           => $row['remark'],
                    'type'             => $row['type'],
                ];
            }
        }
        return ['customers_id' => $recon['customers_id'], 'sources' => $sources];
    }

    /**
     * 补齐对账单所含单据缺失的核销源单
     *
     * 源单本应在销货单/销货退货单审核时写入，历史数据里存在审核入口没走该逻辑的情况
     * （如早期从计划排期审核），导致销货对账「去收款」查不到单源。
     * 这里只补缺、不改已有源单，金额口径与 SalesRecon::writebackDiff 一致：
     * 销货单 price = real_money + customers_money + diff_money，pay = pay_money
     * 退货单 price = -(real_money + customers_money) + diff_money，pay = -retreat_money
     *
     * @param int   $reconId
     * @param array $codes 对账单所含单据编号
     */
    protected function ensureReconSources($reconId, $codes)
    {
        if (empty($codes)) {
            return;
        }
        $docs = Db::name('kuerp_sales_recon_doc')->where(['recon_id' => $reconId])->field('doc_code,doc_type')->select();
        foreach ($docs as $doc) {
            $code = $doc['doc_code'];
            $type = $doc['doc_type'] == '2' ? '2' : '1';
            $exists = Db::name('kuerp_writeoff_source')
                ->where(['purchase_code' => $code, 'purchase_type' => '3', 'type' => $type])
                ->count();
            if ($exists) {
                continue;
            }
            if ($type === '1') {
                $row = Db::name('kuerp_goods_sales')->where(['purchase_code' => $code, 'examine_status' => '1'])->find();
                if (!$row) {
                    continue;
                }
                $price = round((float)$row['real_money'] + (float)$row['customers_money'] + (float)$row['diff_money'], 4);
                $payed = round((float)$row['pay_money'], 4);
            } else {
                $row = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code' => $code, 'examine_status' => '1'])->find();
                if (!$row) {
                    continue;
                }
                $price = round(-((float)$row['real_money'] + (float)$row['customers_money']) + (float)$row['diff_money'], 4);
                $payed = round(-(float)$row['retreat_money'], 4);
            }
            Db::name('kuerp_writeoff_source')->insert([
                'purchase_type' => '3',
                'type'          => $type,
                'customers_id'  => $row['customers_id'],
                'purchase_code' => $row['purchase_code'],
                'purchase_time' => $row['purchase_time'],
                'price'         => $price,
                'money'         => $payed,
                'write_off'     => round($price - $payed, 4),
                'remark'        => isset($row['note']) ? $row['note'] : '',
            ]);
        }
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

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                if(empty($params['customers_id'])){
                    $this->error('请选择客户');
                }
                if(empty($params['purchase_code'])){
                    $this->error('请填写单据编号');
                }
                $admin = Session::get('admin');

                //账户信息
                $account = [];
                $account_id = [];
                foreach ($params['accounts_id'] as $k=>$v){
                    if(!empty($v)){//有账户
                        $accounts = Db::name('kuerp_accounts')->where(['id'=>$v])->find();
                        if(empty($accounts)){
                            $this->error('账户信息错误');
                        }
                        if(in_array($v,$account_id)){
                            $this->error('不可多次选择相同的结算账户');
                        }else{
                            $account_id[] = $v;
                        }
                        $account[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'    => $params['purchase_code'],
                            'purchase_time'    => strtotime($params['purchase_time']),
                            'collection_id'    => $ids,
                            'accounts_id'      => $v,
                            'money'            => isset($params['money'][$k])?(float)$params['money'][$k]:0,
                            'settlementtype'   => isset($params['settlementtype'][$k])?(float)$params['settlementtype'][$k]:0,
                            'number'           => isset($params['number'][$k])?$params['number'][$k]:'',
                            'remark'           => isset($params['remark'][$k])?$params['remark'][$k]:null,
                        ]);
                    }
                }
                $account_money = 0;//账户收支总金额
                foreach ($account as $account_k=>$account_v){
                    $account_money += $account_v['money'];
                }
                //账户信息结束

                //源单信息
                $sale = [];
                $sale_id = [];
                $log_arr = [];
                $log_add = [];
                foreach ($params['purchase_number'] as $k=>$v){
                    if(!empty($v) && !empty($params['purchase_type'][$k])){//有单号
                        $order = Db::name('kuerp_writeoff_source')->where(['id'=>$params['purchase_type'][$k]])->find();
                        if(empty($order)){
                            $this->error('源单信息查询错误');
                        }
                        if(in_array($v,$sale_id)){
                            $this->error('不可多次选择相同的源单');
                        }else{
                            $sale_id[] = $v;
                        }
                        $thisrow = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'    => $params['purchase_code'],
                            'purchase_time'    => strtotime($params['purchase_time']),
                            'purchase_number'  => $order['purchase_code'],
                            'collection_id'    => $ids,
                            'business_name'    => isset($params['business_type'][$k])?$params['business_type'][$k]:null,
                            'business_type'    => $order['purchase_type'],
                            'times'            => $order['purchase_time'],
                            'money'            => $order['price'],
                            'write_off'        => isset($params['write_off'][$k])?(float)$params['write_off'][$k]:0,
                            'write_off_money'  => isset($params['write_off_money'][$k])?(float)$params['write_off_money'][$k]:0,
                            'write_off_price'  => isset($params['write_off_price'][$k])?(float)$params['write_off_price'][$k]:0,
                            'remark'           => $order['remark'],
                            'source_id'        => $order['id'],
                            'type'             => $order['type'],
                        ]);
                        $sale[] = $thisrow;

                        $where = [];
                        $where['source_id'] = $order['id'];
                        $log = Db::name('kuerp_writeoff_log')->where($where)->find();
                        if($log){
                            $status = true;
                            foreach ($log_arr as $log_arr_k=>$log_arr_v){
                                if($log_arr_v['id']==$log['id']){
                                    $status = false;
                                    $log_arr[$log_arr_k]['money'] += $thisrow['write_off_price']-(float)$params['write_off_prices'][$k];
                                    $log_arr[$log_arr_k]['write_off'] -= $thisrow['write_off_price']+(float)$params['write_off_prices'][$k];
                                }
                            }
                            if($status){
                                $log_arr[] = [
                                    'id' => $log['id'],
                                    'write_off' => $log['write_off']-$thisrow['write_off_price']+(float)$params['write_off_prices'][$k],
                                    'money' => $log['money']+$thisrow['write_off_price']-(float)$params['write_off_prices'][$k],
                                ];
                            }
                        }else{
                            $log_add[] = [
                                'source_id' => $order['id'],
                                'write_off' => $thisrow['write_off_money']-$thisrow['write_off_price']+(float)$params['write_off_prices'][$k],
                                'money' => $thisrow['write_off_price']-(float)$params['write_off_prices'][$k],
                            ];
                        }
                    }
                }
                $sale_money = 0;//账户收支总金额
                foreach ($sale as $sale_k=>$sale_v){
                    $sale_money += $sale_v['write_off_price'];
                }
                $business_type = !empty($sale)?2:1;
                $rowdata = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $params['purchase_code'],
                    'purchase_time'     =>  strtotime($params['purchase_time']),
                    'customers_id'      =>  $params['customers_id'],
                    'business_type'     => $business_type,
                    'money'             =>  $account_money,
                    'write_off'         =>  $sale_money,
                    'discount'          =>  isset($params['discount'])?(float)$params['discount']:0,
                    'advance'           =>  isset($params['advance'])?(float)$params['advance']:0,
                    'staff_member_id'   =>  isset($params['staff_member_id'])?(float)$params['staff_member_id']:null,
                    'remarks'           =>  $params['note'],
                    'preparation_id'    =>  $admin['id'],
                    'operation_id'      =>  $admin['id'],
                    'updatetime'        => time(),
                ]);

                if(empty($account)){
                    $this->error('请填写账户结算信息');
                }

                if($this->model->where(['purchase_code'=>trim($params['purchase_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('单据编号已占用');
                }

                //详情
                $info_list = Db::name('kuerp_writeoff_info')->where(['collection_id'=>$ids])->select();
                $writeoff_log = [];
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$info_list_v['source_id']])->find();
                    if($log){
                        $addstatus = true;
                        foreach ($writeoff_log as $writeoff_log_k=>$writeoff_log_v){
                            if($writeoff_log_v['id']==$log['id']){
                                $addstatus = false;
                                $writeoff_log[$writeoff_log_k]['write_off'] = $writeoff_log_v['write_off']+$info_list_v['write_off_price'];
                                $writeoff_log[$writeoff_log_k]['money'] = $writeoff_log_v['money']-$info_list_v['money'];
                            }
                        }
                        if($addstatus){
                            $writeoff_log[] = [
                                'id' => $log['id'],
                                'write_off'=>($log['write_off']+$info_list_v['write_off_price']),
                                'money'=>($log['money']-$info_list_v['write_off_price'])
                            ];
                        }
                    }
                }
                foreach ($log_arr as $log_arr_k=>$log_arr_v){
                    $log = Db::name('kuerp_writeoff_log')->where(['id'=>$log_arr_v['id']])->find();
                    if($log){
                        $addstatus = true;
                        foreach ($writeoff_log as $writeoff_log_k=>$writeoff_log_v){
                            if($writeoff_log_v['id']==$log['id']){
                                $addstatus = false;
                                $writeoff_log[$writeoff_log_k]['write_off'] = $writeoff_log_v['write_off']-$log_arr_v['write_off'];
                                $writeoff_log[$writeoff_log_k]['money'] = $writeoff_log_v['money']+$log_arr_v['money'];
                            }
                        }
                        if($addstatus){
                            $writeoff_log[] = [
                                'id' => $log['id'],
                                'write_off'=>($log['write_off']-$log_arr_v['write_off']),
                                'money'=>($log['money']+$log_arr_v['write_off'])
                            ];
                        }
                    }
                }
                $result = false;
                Db::startTrans();
                try {
                    if($examine!=1 && $row['examine_status']==1){
                        $this->examine($ids,2);
                    }
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($rowdata);
                    }
                    Db::name('kuerp_collection_account')->where(['collection_id'=>$ids])->delete();
                    Db::name('kuerp_collection_sale')->where(['collection_id'=>$ids])->delete();

                    Db::name('kuerp_collection_account')->insertAll($account);
                    Db::name('kuerp_collection_sale')->insertAll($sale);

                    if(!empty($log_add)){
                        (new \app\admin\model\kuerp\WriteoffLog())->insertAll($log_add);
                    }
                    if(!empty($writeoff_log)){
                        (new \app\admin\model\kuerp\WriteoffLog())->saveAll($writeoff_log);
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

        $account = Db::name('kuerp_collection_account')->where(['collection_id'=>$row['id']])->select();
        $this->view->assign("account", $account);

        $sale = Db::name('kuerp_collection_sale')->where(['collection_id'=>$row['id']])->select();
        $this->view->assign("sale", $sale);

        $give_account = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->value('need_receive_account');
        if($row['examine_status']==1){
            $this->assignconfig("give_account", $give_account-$row['money']);
            $this->view->assign("give_account", (float)$give_account);
        }else{
            $this->assignconfig("give_account", $give_account);
            $this->view->assign("give_account", $give_account);
        }

        $kuerp_customers = Db::name('kuerp_customers')->where(['id'=>$row['customers_id']])->field('id,customer_name')->find();
        $this->assignconfig("kuerp_customers", $kuerp_customers);

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
            $del_id = [];
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
                        $del_id[] = $list_v['id'];
                    } catch (Exception $e) {
                    }
                }else{
                    $del_id[] = $list_v['id'];
                }
            }
            $list = $this->model->where(['id'=>['in',$del_id]])->select();
            //详情
            $info_list = Db::name('kuerp_collection_sale')->where(['collection_id'=>['in',$del_id]])->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                foreach ($info_list as $info_list_k=>$info_list_v){
                    $log = Db::name('kuerp_writeoff_log')->where(['source_id'=>$info_list_v['source_id']])->find();
                    if($log){
                        Db::name('kuerp_writeoff_log')->where(['id'=>$log['id']])->update([
                            'write_off'=>($log['write_off']+$info_list_v['write_off_price']),
                            'money'=>($log['money']-$info_list_v['write_off_price'])
                        ]);
                    }
                }
                Db::name('kuerp_collection_account')->where(['collection_id'=>['in',$del_id]])->delete();
                Db::name('kuerp_collection_sale')->where(['collection_id'=>['in',$del_id]])->delete();
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
        $admin = Session::get('admin');
        if($examine==1){
            $list = Db::name('kuerp_collection')->where(['id'=>['in',$ids],'examine_status'=>'0'])->select();
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
            $success_count = 0;
            foreach ($list as $list_k=>$list_v){
                //账户信息
                $accountData = [];$log = [];
                $account = Db::name('kuerp_collection_account')->where(['collection_id'=>$list_v['id']])->select();
                foreach ($account as $account_k=>$account_v){
                    $accounts = Db::name('kuerp_accounts')->where(['id'=>$account_v['accounts_id']])->find();
                    $log[] = [
                        'account_coding'    => $accounts['account_coding'],
                        'account_name'      => $accounts['account_name'],
                        'balance'           => $accounts['balance']+$account_v['money'],
                        'income'            => $account_v['money'],
                        'pay_time'          => $list_v['purchase_time'],
                        'bill_code'         => $account_v['purchase_code'],
                        'business_type'     => '7',
                        'customers_id'      => $list_v['customers_id'],
                        'payment'           => $list_v['staff_member_id'],
                        'remark'            => $list_v['remarks'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                    $accountData[] = [
                        'balance'  => $account_v['money'],
                        'account'  => $accounts['id'],
                    ];
                }
                //账户信息结束

                $info_list = Db::name('kuerp_collection_sale')->where(['collection_id'=>['in',$ids]])->select();
                $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->find();
                $gave_money = $suppliers['need_receive_account'] - $list_v['money'];

                foreach ($info_list as $info_list_k=>$info_list_v){
                    if($info_list_v['business_type']==3 && $info_list_v['type']==1){
                        //普通销售
                        $order = Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_list_v['purchase_number']])->find();
                        if($order){
                            //应收总额统一口径：优惠后价税合计 + 客户承担费用 + 对账差异
                            $all_money = $order['real_money']+$order['customers_money']+(float)$order['diff_money'];
                            $pay_money = $order['pay_money']+$info_list_v['write_off_price'];
                            if($all_money<$pay_money){
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$info_list_v['purchase_number'].'核销金额不能大于未核销金额'];
                                }
                                $this->error($info_list_v['purchase_number'].'核销金额不能大于未核销金额');
                            }
                        }
                    }else if($info_list_v['business_type']==3 && $info_list_v['type']==2){
                        //销售退货
                        $order = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_list_v['purchase_number']])->find();
                        if($order){
                            //应收总额统一口径：优惠后价税合计 + 客户承担费用 - 对账差异（退货金额越大冲减越多）
                            $all_money = $order['real_money']+$order['customers_money']-(float)$order['diff_money'];
                            $pay_money = $order['retreat_money']+(-$info_list_v['write_off_price']);
                            if($all_money<$pay_money){
                                if($examine_set!=1){
                                    return ['status'=>0,'msg'=>$info_list_v['purchase_number'].'核销金额不能大于未核销金额'];
                                }
                                $this->error($info_list_v['purchase_number'].'核销金额不能大于未核销金额');
                            }
                        }
                    }
                }

                //客户对账单
                if(empty($info_list)){
                    $accounts_receivable = [];
                    if(!empty($list_v['customers_id'])){
                        $accounts_receivable[] = \addons\kuerp\library\Goods::setDataInfo([
                            'customers_id'      => $list_v['customers_id'],
                            'purchase_time'     => $list_v['purchase_time'],
                            'purchase_code'     => $list_v['purchase_code'],
                            'business_type'     => '9',
                            'advance'           => $list_v['money'],
                            'remark'            => $list_v['remarks'],
                            'real_money'        => $list_v['money'],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                    }
                }else{
                    $accounts_receivable = [];
                    if(!empty($list_v['customers_id'])){
                        $accounts_receivable[] = \addons\kuerp\library\Goods::setDataInfo([
                            'customers_id'      => $list_v['customers_id'],
                            'purchase_time'     => $list_v['purchase_time'],
                            'purchase_code'     => $list_v['purchase_code'],
                            'business_type'     => '9',
                            'receivable'        => -$list_v['money'],
                            'remark'            => $list_v['remarks'],
                            'real_money'        => $list_v['money'],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                    }
                }

                Db::startTrans();
                try {

                    if($list_v['business_type']==1){
                        Db::name('kuerp_writeoff_source')->insert([
                            'purchase_type' => 1,
                            'type' => 1,
                            'customers_id' => $list_v['customers_id'],
                            'purchase_code' => $list_v['purchase_code'],
                            'purchase_time' => $list_v['purchase_time'],
                            'price' => $list_v['advance'],
                            'money' => 0,
                            'write_off' => $list_v['advance'],
                            'remark' => $list_v['remarks'],
                        ]);
                    }

                    foreach ($info_list as $info_list_k=>$info_list_v){
                        $income = Db::name('kuerp_sales_invoicing')->where(['bill_code'=>$info_list_v['purchase_number']])->find();
                        if($info_list_v['business_type']==3 && $info_list_v['type']==1){
                            //普通销售
                            $order = Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_list_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_sales_payment_log')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                    'customers_id'   => $order['customers_id'],
                                    'business_type'  => '3',
                                    'bill_time'      => time(),
                                    'bill_code'      => $order['purchase_code'],
                                    'bill_codes'     => $info_list_v['purchase_code'],
                                    'pay_money'      => $info_list_v['write_off_price'],
                                    'reamrk'         => $list_v['remarks'],
                                    'createtime'     => time(),
                                ]));
                                $more_money = ($order['real_money']+$order['customers_money']+(float)$order['diff_money'])-$info_list_v['write_off_price']-$order['pay_money'];
                                $status = $more_money<=0?1:2;
                                $pay_money = $order['pay_money']+$info_list_v['write_off_price'];
                                Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_list_v['purchase_number']])
                                    ->update(['pay_money'=>$pay_money,'pay_status'=>$status]);
                                Db::name('kuerp_collection_sale')->where(['id'=>$info_list_v['id']])->update([
                                    'old_money'=>$order['pay_money'],
                                    'old_status'=>$order['pay_status'],
                                    'old_status_i'=>$income ? $income['status'] : null
                                ]);
                                if($income){
                                    Db::name('kuerp_sales_invoicing')->where(['bill_code'=>$info_list_v['purchase_number']])
                                        ->update(['status'=>$status]);
                                    Db::name('kuerp_sales_invoicing_log')->where(['bill_code'=>$info_list_v['purchase_number']])
                                        ->update(['status'=>$status]);
                                }
                            }
                        }
                        if($info_list_v['business_type']==3 && $info_list_v['type']==2){
                            //销售退货
                            $order = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_list_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_sales_payment_log')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                    'customers_id'   => $order['customers_id'],
                                    'business_type'  => '4',
                                    'bill_time'      => time(),
                                    'bill_code'      => $order['purchase_code'],
                                    'bill_codes'     => $info_list_v['purchase_code'],
                                    'pay_money'      => $info_list_v['write_off_price'],
                                    'reamrk'         => $list_v['remarks'],
                                    'createtime'     => time(),
                                ]));
                                $more_money = ($order['real_money']+$order['customers_money']-(float)$order['diff_money'])+$info_list_v['write_off_price']-$order['retreat_money'];
                                $status = $more_money==0?1:2;
                                $pay_money = $order['retreat_money']+(-$info_list_v['write_off_price']);
                                Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_list_v['purchase_number']])
                                    ->update(['retreat_money'=>$pay_money,'retreat_status'=>$status]);
                                Db::name('kuerp_collection_sale')->where(['id'=>$info_list_v['id']])->update([
                                    'old_money'=>$order['retreat_money'],
                                    'old_status'=>$order['retreat_status'],
                                    'old_status_i'=>$income ? $income['status'] : null
                                ]);
                                if($income){
                                    $status += 3;
                                    Db::name('kuerp_sales_invoicing')->where(['bill_code'=>$info_list_v['purchase_number']])
                                        ->update(['status'=>$status]);
                                    Db::name('kuerp_sales_invoicing_log')->where(['bill_code'=>$info_list_v['purchase_number']])
                                        ->update(['status'=>$status]);
                                }
                            }
                        }
                    }

                    if(!empty($accounts_receivable)){
                        Db::name('kuerp_accounts_receivable')->insertAll($accounts_receivable);
                    }

                    foreach ($accountData as $accountData_k=>$accountData_v){
                        if($accountData_v['balance']>0){
                            Db::name('kuerp_accounts')->where(['id'=>$accountData_v['account']])->inc('balance',$accountData_v['balance'])->update();
                        }else if($accountData_v['balance']<0){
                            Db::name('kuerp_accounts')->where(['id'=>$accountData_v['account']])->dec('balance',-$accountData_v['balance'])->update();
                        }
                    }
                    if(!empty($log)){
                        Db::name('kuerp_accounts_log')->insertAll($log);
                    }
                    Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->update(['need_receive_account'=>$gave_money]);
                    $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'0'])->update(['examine_status'=>1,'examine_id'=>$admin['id'],'examine_time'=>time(),'operation_id'=>$admin['id']]);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if(isset($result) && $result !== false){
                    $success_count += 1;
                    // 收款后同步所属销货对账单的收款状态（到期提醒、差异校验依赖该字段）
                    try {
                        $reconCodes = Db::name('kuerp_collection_sale')->where(['collection_id'=>$list_v['id']])->column('purchase_number');
                        \app\admin\model\kuerp\SalesRecon::recalcPayByDocCodes($reconCodes);
                    } catch (\Exception $e) {
                    }
                }
            }
            if($success_count>0){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success($success_count.'条数据审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('审核失败');
            }
        }else{
            $list = Db::name('kuerp_collection')->where(['id'=>['in',$ids],'examine_status'=>'1'])->select();
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
                $this->error('请选择需要反审核的数据');
            }
            $success_count = 0;
            foreach ($list as $list_k=>$list_v){
                //账户信息
                $accountData = [];$log = [];
                $account = Db::name('kuerp_collection_account')->where(['collection_id'=>$list_v['id']])->select();
                foreach ($account as $account_k=>$account_v){
                    $accounts = Db::name('kuerp_accounts')->where(['id'=>$account_v['accounts_id']])->find();
                    $accountData[] = [
                        'balance'  => $account_v['money'],
                        'account'  => $accounts['id'],
                    ];
                    $log[] = $account_v['purchase_code'];
                }
                //账户信息结束
                $info_list = Db::name('kuerp_collection_sale')->where(['collection_id'=>$list_v['id']])->select();
                $suppliers = Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->find();
                $gave_money = $suppliers['need_receive_account'] + $list_v['money'];
                Db::startTrans();
                try {
                    Db::name('kuerp_accounts_receivable')->where(['purchase_code'=>$list_v['purchase_code'],'business_type'=>'9'])->delete();
                    Db::name('kuerp_writeoff_source')->where(['purchase_code'=>$list_v['purchase_code'],'purchase_type'=>'1','type'=>'1'])->delete();
                    Db::name('kuerp_customers')->where(['id'=>$list_v['customers_id']])->update(['need_receive_account'=>$gave_money]);

                    foreach ($info_list as $info_list_k=>$info_list_v){
                        if($info_list_v['business_type']==3 && $info_list_v['type']==1){
                            //普通购货
                            $order = Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_list_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_goods_sales')->where(['purchase_code'=>$info_list_v['purchase_number']])
                                    ->update(['pay_money'=>$info_list_v['old_money'],'pay_status'=>$info_list_v['old_status']]);
                            }
                        }
                        if($info_list_v['business_type']==3 && $info_list_v['type']==2){
                            //购货退货
                            $order = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_list_v['purchase_number']])->find();
                            if($order){
                                Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$info_list_v['purchase_number']])
                                    ->update(['retreat_money'=>$info_list_v['old_money'],'retreat_status'=>$info_list_v['old_status']]);
                            }
                        }
                        $income = Db::name('kuerp_sales_invoicing')->where(['bill_code'=>$info_list_v['purchase_number']])->find();
                        if($income){
                            if($info_list_v['business_type']==3 && $info_list_v['type']==2){
                                $info_list_v['old_status'] += 3;
                            }
                            Db::name('kuerp_sales_invoicing')->where(['bill_code'=>$info_list_v['purchase_number']])
                                ->update(['status'=>$info_list_v['old_status']]);
                            Db::name('kuerp_sales_invoicing_log')->where(['bill_code'=>$info_list_v['purchase_number']])
                                ->update(['status'=>$info_list_v['old_status']]);
                        }
                        Db::name('kuerp_sales_payment_log')->where(['bill_codes'=>$info_list_v['purchase_code']])->delete();
                    }
                    foreach ($accountData as $accountData_k=>$accountData_v){
                        if($accountData_v['balance']>0){
                            Db::name('kuerp_accounts')->where(['id'=>$accountData_v['account']])->dec('balance',$accountData_v['balance'])->update();
                        }else if($accountData_v['balance']<0){
                            Db::name('kuerp_accounts')->where(['id'=>$accountData_v['account']])->inc('balance',-$accountData_v['balance'])->update();
                        }
                    }
                    if(!empty($log)){
                        Db::name('kuerp_accounts_log')->where(['bill_code'=>['in',$log],'business_type'=>'7'])->delete();
                    }
                    $result = $this->model->where(['id'=>$list_v['id'],'examine_status'=>'1'])->update(['examine_status'=>'0','examine_id'=>null,'examine_time'=>null,'operation_id'=>$admin['id']]);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if(isset($result) && $result !== false){
                    $success_count += 1;
                    // 反审核后同步所属销货对账单的收款状态
                    try {
                        $reconCodes = Db::name('kuerp_collection_sale')->where(['collection_id'=>$list_v['id']])->column('purchase_number');
                        \app\admin\model\kuerp\SalesRecon::recalcPayByDocCodes($reconCodes);
                    } catch (\Exception $e) {
                    }
                }
            }
            if($success_count>0){
                if($examine_set!=1){
                    return ['status'=>1,'msg'=>'操作成功'];
                }
                $this->success($success_count.'条数据反审核成功');
            }else{
                if($examine_set!=1){
                    return ['status'=>0,'msg'=>'操作失败'];
                }
                $this->error('反审核失败');
            }
        }
    }

}
