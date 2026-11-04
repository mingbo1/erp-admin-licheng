<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\exception\PDOException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as PHPExcel_Shared_Date;
use fast\Tree;
/**
 * 客户管理
 *
 * @icon fa fa-circle-o
 */
class Customers extends Backend
{

    /**
     * Customers模型对象
     * @var \app\admin\model\kuerp\Customers
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Customers;
        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("saleList", $this->model->getSaleList());

        $categorylists = collection(model('\app\admin\model\kuerp\Customertype')->where(['status'=>'1'])->field('id,pid,type_name as name')->select())->toArray();
        Tree::instance()->init($categorylists);
        $categorylists = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'name');
        $categorylist = array('' => '==请选择==');
        foreach ($categorylists as $k => $v) {
            $categorylist[$v['id']] = $v['name'];
        }
        $this->view->assign('customertype', $categorylist);

        $levellists = collection(model('\app\admin\model\kuerp\Customerlevel')->field('id,name')->select())->toArray();
        $levellist = array('' => '==请选择==');
        foreach ($levellists as $k => $v) {
            $levellist[$v['id']] = $v['name'];
        }
        $this->view->assign('levellists', $levellist);
    }

    public function import()
    {
        if ($this->request->isPost()){
            $file = $this->request->request('file');
            if (!$file) {
                $this->error(__('Parameter %s can not be empty', 'file'));
            }
            $filePath = ROOT_PATH . DS . 'public' . DS . $file;
            if (!is_file($filePath)) {
                $this->error(__('No results were found'));
            }
            //实例化reader
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
                $this->error(__('Unknown data format'));
            }
            if ($ext === 'csv') {
                $file = fopen($filePath, 'r');
                $filePath = tempnam(sys_get_temp_dir(), 'import_csv');
                $fp = fopen($filePath, "w");
                $n = 0;
                while ($line = fgets($file)) {
                    $line = rtrim($line, "\n\r\0");
                    $encoding = mb_detect_encoding($line, ['utf-8', 'gbk', 'latin1', 'big5']);
                    if ($encoding != 'utf-8') {
                        $line = mb_convert_encoding($line, 'utf-8', $encoding);
                    }
                    if ($n == 0 || preg_match('/^".*"$/', $line)) {
                        fwrite($fp, $line . "\n");
                    } else {
                        fwrite($fp, '"' . str_replace(['"', ','], ['""', '","'], $line) . "\"\n");
                    }
                    $n++;
                }
                fclose($file) || fclose($fp);

                $reader = new Csv();
            } elseif ($ext === 'xls') {
                $reader = new Xls();
            } else {
                $reader = new Xlsx();
            }

            //导入文件首行类型,默认是注释,如果需要使用字段名称请使用name
            $importHeadType = isset($this->importHeadType) ? $this->importHeadType : 'comment';

            $table = $this->model->getQuery()->getTable();
            $database = \think\Config::get('database.database');
            $fieldArr = [];
            $list = db()->query("SELECT COLUMN_NAME,COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?", [$table, $database]);
            foreach ($list as $k => $v) {
                if ($importHeadType == 'comment') {
                    $fieldArr[$v['COLUMN_COMMENT']] = $v['COLUMN_NAME'];
                } else {
                    $fieldArr[$v['COLUMN_NAME']] = $v['COLUMN_NAME'];
                }
            }

            //加载文件
            $insert = [];
            try {
                if (!$PHPExcel = $reader->load($filePath)) {
                    $this->error(__('Unknown data format'));
                }
                $currentSheet = $PHPExcel->getSheet(0);  //读取文件中的第一个工作表
                $allColumn = $currentSheet->getHighestDataColumn(); //取得最大的列号
                $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
                $maxColumnNumber = Coordinate::columnIndexFromString($allColumn);
                // 跳过前14行说明，第15行为表头，第16行开始为数据
                $headerRow = 15;   // 表头所在行
                $dataStartRow = 16; // 数据开始行

                $fields = [];
                for ($currentRow = $headerRow; $currentRow <= $headerRow; $currentRow++) {
                    for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                        $val = (string)$currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                        $val = str_replace('*','',$val);
                        $fields[] = (string)$val;
                    }
                }

                for ($currentRow = $dataStartRow; $currentRow <= $allRow; $currentRow++) {
                    $values = [];
                    for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                        $val = (string)$currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                        $values[] = is_null($val) ? '' : $val;
                    }
                    $row = array_combine($fields, $values);
                    if( !empty($row['余额日期'])){
                        $row['余额日期'] =  strtotime(gmdate('Y-m-d H:i:s',PHPExcel_Shared_Date::excelToTimeStamp($row['余额日期'])));
                    }
                    $insert[] = $row;
                }
            } catch (Exception $exception) {
                $this->error($exception->getMessage());
            }
            if (!$insert) {
                $this->error(__('No rows were updated'));
            }
            $cate = [];$level = [];$saler = [];
            foreach ($insert as $insert_k=>$insert_v){
                if(empty($insert_v['客户编号']) || empty($insert_v['客户名称']) || empty($insert_v['联系人'])){
                    unset($insert[$insert_k]);
                }else{
                    if(!empty($insert_v['客户类别']) && !in_array($insert_v['客户类别'],$cate)){
                        $cate[] = $insert_v['客户类别'];
                    }
                    if(!empty($insert_v['客户等级'] ?? '') && !in_array($insert_v['客户等级'],$level)){
                        $level[] = $insert_v['客户等级'];
                    }
                    if(!empty($insert_v['销售人员']) && !in_array($insert_v['销售人员'],$saler)){
                        $saler[] = $insert_v['销售人员'];
                    }
                }
            }

            $catelist = Db::name('kuerp_customertype')->where(['type_name'=>['in',$cate]])->column('id','type_name');
            $catename = Db::name('kuerp_customertype')->where(['type_name'=>['in',$cate]])->column('type_name');
            if(sizeof($catename)!=sizeof($cate)){
                foreach ($cate as $cate_k=>$cate_v){
                    if(!in_array($cate_v,$catename)){
                        $this->error('客户分类'.$cate_v.'不存在，导入失败');
                    }
                }
            }

            $levellist = Db::name('kuerp_customerlevel')->where(['name'=>['in',$level]])->column('id','name');
            $levelname = Db::name('kuerp_customerlevel')->where(['name'=>['in',$level]])->column('name');
            if(sizeof($levelname)!=sizeof($level)){
                foreach ($level as $level_k=>$level_v){
                    if(!in_array($level_v,$levelname)){
                        $this->error('客户等级'.$level_v.'不存在，导入失败');
                    }
                }
            }

            $salerlist = Db::name('kuerp_staff_member')->where(['men_code'=>['in',$saler]])->column('id','men_code');
            $salername = Db::name('kuerp_staff_member')->where(['men_code'=>['in',$saler]])->column('men_code');
            if(sizeof($salername)!=sizeof($saler)){
                foreach ($saler as $saler_k=>$saler_v){
                    if(!in_array($saler_v,$salername)){
                        $this->error('编号为'.$saler_v.'的职员不存在，导入失败');
                    }
                }
            }

            $params = [];
            $nowsp = '';
            $code = [];
            $now_code = [];
            foreach ($insert as $insert_k=>$insert_v){
                if(!empty($insert_v['客户编号']) && !empty($insert_v['客户名称'])){
                    $nowsp = $insert_k;
                    if(in_array($insert_v['客户编号'],$code)){
                        $this->error('导入失败，客户编号'.$insert_v['客户编号'].'重复');
                    }
                    $now_code[] = $insert_v['客户编号'];
                    $params[$insert_k] = [
                        'customer_code'     => $insert_v['客户编号'],
                        'customer_name'     => $insert_v['客户名称'],
                        'customertype_id'   => isset($catelist[$insert_v['客户类别']])?$catelist[$insert_v['客户类别']]:null,
                        'customerlevel_id'  => isset($levellist[$insert_v['客户等级'] ?? ''])?$levellist[$insert_v['客户等级']]:1,
                        'staff_member_id'   => isset($salerlist[$insert_v['销售人员']])?$salerlist[$insert_v['销售人员']]:null,

                        'account_time'      => !empty($insert_v['余额日期'])?$insert_v['余额日期']:time(),
                        'receive_account'   => $insert_v['期初应收款'],
                        'opening_prepayment'=> $insert_v['期初预收款'],
                        'need_receive_account' => round(((float)$insert_v['期初应收款']-(float)$insert_v['期初预收款']),4),
                        'identification'    => $insert_v['纳税人识别号'],
                        'deposit_bank'      => $insert_v['开户银行'],
                        'bank_number'       => $insert_v['银行账号'],

                        'connet_name'       => $insert_v['联系人'],
                        'customer_mobile'   => $insert_v['手机'],
                        'customer_phone'    => $insert_v['座机'],
                        'qq_wx_email'       => $insert_v['QQ/微信'],
                        'getgoods_address'  => $insert_v['联系地址'],

                        'createtime'        => time(),
                        'updatetime'        => time(),
                        'userlist'          =>[],
                        'first'             =>0
                    ];
                    // 账期天数（信用账期）：表里有这一列才写，避免旧模板导入把已设置的账期清 0
                    foreach (['信用账期天数', '信用账期', '账期天数'] as $cdKey) {
                        if (array_key_exists($cdKey, $insert_v) && $insert_v[$cdKey] !== '') {
                            $params[$insert_k]['credit_days'] = max(0, (int)$insert_v[$cdKey]);
                            break;
                        }
                    }
                }
                if(isset($params[$nowsp]['userlist']) && (!empty($insert_v['联系人']))){
                    $is_first = 0;
                    if($insert_v['首要联系人']=='是' && $params[$nowsp]['first']==0){
                        $is_first = 1;$params[$nowsp]['first'] = 1;
                    }
                    $params[$nowsp]['userlist'][] = [
                        'customers_code'        => $params[$nowsp]['customer_code'],
                        'first_connect_name'    => $insert_v['联系人'],
                        'first_connect_mobile'  => $insert_v['手机'],
                        'first_connect_phone'   => $insert_v['座机'],
                        'qq_wx_email'           => $insert_v['QQ/微信'],
                        'connect_address'       => $insert_v['联系地址'],
                        'is_first'              => $is_first,
                        'createtime'            => time(),
                        'updatetime'            => time(),
                    ];
                }
            }

            $editstatus = isset($_POST['editstatus'])?$_POST['editstatus']:1;
            $edit_arr = [];
            if($editstatus==0){
                $code_s = Db::name('kuerp_customers')->where(['customer_code'=>['in',$now_code]])->value('customer_code');
                if(!empty($code_s)){
                    $this->error('导入失败，客户编号'.$code_s.'已存在');
                }
            }else{
                $code_list = Db::name('kuerp_customers')->where(['customer_code'=>['in',$now_code]])->column('id','customer_code');
                foreach ($params as $params_k=>$params_v){
                    if(isset($code_list[$params_v['customer_code']])){
                        $params_v['id'] = $code_list[$params_v['customer_code']];
                        $edit_arr[] = $params_v;
                        unset($params[$params_k]);
                    }
                }
            }
            Db::startTrans();
            try {
                if(!empty($params)){
                    foreach ($params as $params_k=>$params_v){
                        $userarr = $params_v['userlist'];unset($params_v['userlist']);unset($params_v['first']);
                        $result = Db::name('kuerp_customers')->insertGetId($params_v);

                        $business_type = 5;
                        if($params_v['need_receive_account']>0){//应收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 5,
                                'type' => 3,
                                'customers_id' => $result,
                                'purchase_code' => $params_v['customer_code'],
                                'purchase_time' => $params_v['account_time'],
                                'price' => $params_v['need_receive_account'],
                                'write_off' => $params_v['need_receive_account'],
                            ]);
                        }else if($params_v['need_receive_account']<0){//预收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 6,
                                'type' => 3,
                                'customers_id' => $result,
                                'purchase_code' => $params_v['customer_code'],
                                'purchase_time' => $params_v['account_time'],
                                'price' => $params_v['need_receive_account'],
                                'write_off' => $params_v['need_receive_account'],
                            ]);
                            $business_type = 6;
                        }
                        Db::name('kuerp_sales_payment_log')->insert([
                            'customers_id' => $result,
                            'business_type' => $business_type,
                            'bill_time' => $params_v['account_time'],
                            'bill_code' => $params_v['customer_code'],
                            'give_money' => $params_v['need_receive_account'],
                        ]);

                        if(!empty($userarr)){
                            foreach ($userarr as $data_k=>$data_v){
                                $userarr[$data_k]['sid'] = $result;
                            }
                            Db::name('kuerp_customers_user')->insertAll($userarr);
                        }
                    }
                }
                if(!empty($edit_arr)){
                    foreach ($edit_arr as $edit_arr_k=>$edit_arr_v){

                        $business_type = 5;
                        Db::name('kuerp_writeoff_source')->where(['customers_id'=>$edit_arr_v['id'],'type'=>3])->delete();
                        Db::name('kuerp_sales_payment_log')->where(['bill_code'=>$edit_arr_v['customer_code'],'business_type'=>['in',[5,6]]])->delete();
                        if($edit_arr_v['need_receive_account']>0){//应收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 5,
                                'type' => 3,
                                'customers_id' => $edit_arr_v['id'],
                                'purchase_code' => $edit_arr_v['customer_code'],
                                'purchase_time' => $edit_arr_v['account_time'],
                                'price' => $edit_arr_v['need_receive_account'],
                                'write_off' => $edit_arr_v['need_receive_account'],
                            ]);
                        }else if($edit_arr_v['need_receive_account']<0){//预收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 6,
                                'type' => 3,
                                'customers_id' => $edit_arr_v['id'],
                                'purchase_code' => $edit_arr_v['customer_code'],
                                'purchase_time' => $edit_arr_v['account_time'],
                                'price' => $edit_arr_v['need_receive_account'],
                                'write_off' => $edit_arr_v['need_receive_account'],
                            ]);
                            $business_type = 6;
                        }
                        Db::name('kuerp_sales_payment_log')->insert([
                            'customers_id' => $edit_arr_v['id'],
                            'business_type' => $business_type,
                            'bill_time' => $edit_arr_v['account_time'],
                            'bill_code' => $edit_arr_v['customer_code'],
                            'give_money' => $edit_arr_v['need_receive_account'],
                        ]);

                        $userarr = $edit_arr_v['userlist'];unset($edit_arr_v['userlist']);unset($edit_arr_v['first']);
                        $id = $edit_arr_v['id'];
                        unset($edit_arr_v['createtime']);unset($edit_arr_v['id']);
                        $result = (new \app\admin\model\kuerp\Customers())->where(['id'=>$id])->update($edit_arr_v);
                        Db::name('kuerp_customers_user')->where(['sid'=>$id])->delete();
                        if(!empty($userarr)){
                            foreach ($userarr as $data_k=>$data_v){
                                $userarr[$data_k]['sid'] = $id;
                            }
                            Db::name('kuerp_customers_user')->insertAll($userarr);
                        }
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success(['update'=>sizeof($edit_arr),'add'=>sizeof($params)]);
        }
        return $this->view->fetch();
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
            $data = input();$wheres = [];
            if(isset($data['keywords'])){
                $wheres['customers.status'] = '1';
            }
            $list = $this->model
                ->with(['customertype','customerlevel','saler'])
                ->where($where)->where($wheres)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('customerlevel')->visible(['name']);
                $row->getRelation('customertype')->visible(['type_name']);
                $row->getRelation('saler')->visible(['name']);
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
            if ($params) {
                $params = $this->preExcludeFields($params);

                $params['account_time'] = strtotime($params['account_time']);
                $params['need_receive_account'] = round(((float)$params['receive_account']-(float)$params['opening_prepayment']),4);

                $data = [];
                $user = $this->request->post("user/a");
                if(empty($user['first'])){
                    $this->error('请填写联系人');
                }else{
                    $default = '';
                    foreach ($user['first'] as $user_first_k=>$user_first_v){
                        if(isset($user['name'][$user_first_k]) && !empty($user['name'][$user_first_k])){
                            if($user_first_v==1 && $default!==''){
                                $this->error('只能选择一个首要联系人');
                            }
                            if($user_first_v==1){
                                $default = $user_first_k;
                            }
                            $data[] = [
                                'first_connect_name' => $user['name'][$user_first_k],
                                'first_connect_mobile' => $user['mobile'][$user_first_k],
                                'first_connect_phone' => $user['phone'][$user_first_k],
                                'qq_wx_email' => $user['qqnum'][$user_first_k],
                                'connect_address' => $user['address'][$user_first_k],
                                'is_first' => $user['first'][$user_first_k],
                                'createtime' => time(),
                                'updatetime' => time(),
                            ];
                        }
                    }
                    if(empty($data)){
                        $this->error('请完善联系人信息');
                    }
                }
                $default = $default===''?0:$default;
                $data[$default]['is_first'] = 1;
                $params['connet_name'] = $user['name'][$default];
                $params['customer_mobile'] = isset($user['mobile'][$default])?$user['mobile'][$default]:'';
                $params['customer_phone'] = isset($user['phone'][$default])?$user['phone'][$default]:'';
                $params['qq_wx_email'] = isset($user['qqnum'][$default])?$user['qqnum'][$default]:'';
                $params['getgoods_address'] = isset($user['address'][$default])?$user['address'][$default]:'';

                if(Db::name('kuerp_customers')->where(['customer_code'=>trim($params['customer_code'])])->find()){
                    $this->error('客户编号已占用');
                }

                $result = false;
                Db::startTrans();
                try {
                    $params['createtime'] = time();$params['updatetime'] = time();
                    $result = Db::name('kuerp_customers')->insertGetId($params);
                    $business_type = 5;
                    if($params['need_receive_account']>0){//应收
                        Db::name('kuerp_writeoff_source')->insert([
                            'purchase_type' => 5,
                            'type' => 3,
                            'customers_id' => $result,
                            'purchase_code' => $params['customer_code'],
                            'purchase_time' => $params['account_time'],
                            'price' => $params['need_receive_account'],
                            'write_off' => $params['need_receive_account'],
                            'remark' => $params['note'],
                        ]);
                    }else if($params['need_receive_account']<0){//预收
                        Db::name('kuerp_writeoff_source')->insert([
                            'purchase_type' => 6,
                            'type' => 3,
                            'customers_id' => $result,
                            'purchase_code' => $params['customer_code'],
                            'purchase_time' => $params['account_time'],
                            'price' => -$params['need_receive_account'],
                            'write_off' => -$params['need_receive_account'],
                            'remark' => $params['note'],
                        ]);
                        $business_type = 6;
                    }
                    Db::name('kuerp_sales_payment_log')->insert([
                        'customers_id' => $result,
                        'business_type' => $business_type,
                        'bill_time' => $params['account_time'],
                        'bill_code' => $params['customer_code'],
                        'give_money' => $params['need_receive_account'],
                    ]);

                    foreach ($data as $data_k=>$data_v){
                        $data[$data_k]['customers_code'] = trim($params['customer_code']);
                        $data[$data_k]['sid'] = $result;
                    }
                    Db::name('kuerp_customers_user')->insertAll($data);
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
        $code = \addons\kuerp\library\Goods::getCodes('',1);
        $this->view->assign("code", $code);
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
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

                $params['account_time'] = strtotime($params['account_time']);
                $params['need_receive_account'] = $row['need_receive_account'] - ($row['receive_account']-$row['opening_prepayment']) + ((float)$params['receive_account']-(float)$params['opening_prepayment']);

                $data = [];
                $user = $this->request->post("user/a");
                if(empty($user['first'])){
                    $this->error('请填写联系人');
                }else{
                    $default = '';
                    foreach ($user['first'] as $user_first_k=>$user_first_v){
                        if(isset($user['name'][$user_first_k]) && !empty($user['name'][$user_first_k])){
                            if($user_first_v==1 && $default!==''){
                                $this->error('只能选择一个首要联系人');
                            }
                            if($user_first_v==1){
                                $default = $user_first_k;
                            }
                            $data[] = [
                                'first_connect_name' => $user['name'][$user_first_k],
                                'first_connect_mobile' => $user['mobile'][$user_first_k],
                                'first_connect_phone' => $user['phone'][$user_first_k],
                                'qq_wx_email' => $user['qqnum'][$user_first_k],
                                'connect_address' => $user['address'][$user_first_k],
                                'is_first' => $user['first'][$user_first_k],
                                'createtime' => time(),
                                'updatetime' => time(),
                            ];
                        }
                    }
                    if(empty($data)){
                        $this->error('请完善联系人信息');
                    }
                }
                $default = $default===''?0:$default;
                $data[$default]['is_first'] = 1;
                $params['connet_name'] = $user['name'][$default];
                $params['customer_mobile'] = isset($user['mobile'][$default])?$user['mobile'][$default]:'';
                $params['customer_phone'] = isset($user['phone'][$default])?$user['phone'][$default]:'';
                $params['qq_wx_email'] = isset($user['qqnum'][$default])?$user['qqnum'][$default]:'';
                $params['getgoods_address'] = isset($user['address'][$default])?$user['address'][$default]:'';

                if(Db::name('kuerp_customers')->where(['customer_code'=>trim($params['customer_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('客户编号已占用');
                }
                $WriteoffSource = Db::name('kuerp_writeoff_source')->where(['customers_id'=>$row['id'],'type'=>3])->value('price');
                if(((float)$row['receive_account']-(float)$row['opening_prepayment'])==$WriteoffSource && ((float)$row['receive_account']-(float)$row['opening_prepayment'])!=((float)$params['receive_account']-(float)$params['opening_prepayment'])){
                    $order = Db::name('kuerp_writeoff')->where(['customers_id|customers_out_id|customers_enter_id'=>$row['id']])->find();
                    if($order){
                        return ['status'=>400,'msg'=>'已关联核销单，不可编辑金额'];
                    }
                }
                $result = false;
                Db::startTrans();
                try {
                    Db::name('kuerp_customers_user')->where(['sid'=>$ids])->delete();
                    $params['updatetime'] = time();
                    $result = Db::name('kuerp_customers')->where(['id'=>$ids])->update($params);

                    $WriteoffSource = Db::name('kuerp_writeoff_source')->where(['customers_id'=>$ids,'type'=>3])->find();
                    $PaymentLog = Db::name('kuerp_sales_payment_log')->where(['bill_code'=>$row['customer_code'],'business_type'=>['in',[5,6]]])->find();
                    $params['need_receive_account'] = ((float)$params['receive_account']-(float)$params['opening_prepayment']);
                    if($WriteoffSource){
                        $business_type = 5;
                        if($params['need_receive_account']>0){//应收
                            Db::name('kuerp_writeoff_source')->where(['id'=>$WriteoffSource['id']])->update([
                                'purchase_type' => 5,
                                'type' => 3,
                                'customers_id' => $ids,
                                'purchase_code' => $params['customer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => $params['need_receive_account'],
                                'write_off' => $params['need_receive_account'],
                                'remark' => $params['note'],
                            ]);
                        }else if($params['need_receive_account']<0){//预收
                            Db::name('kuerp_writeoff_source')->where(['id'=>$WriteoffSource['id']])->update([
                                'purchase_type' => 6,
                                'type' => 3,
                                'customers_id' => $ids,
                                'purchase_code' => $params['customer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => -$params['need_receive_account'],
                                'write_off' => -$params['need_receive_account'],
                                'remark' => $params['note'],
                            ]);
                            $business_type = 6;
                        }
                        Db::name('kuerp_sales_payment_log')->where(['id'=>$PaymentLog['id']])->update([
                            'customers_id' => $ids,
                            'business_type' => $business_type,
                            'bill_time' => $params['account_time'],
                            'bill_code' => $params['customer_code'],
                            'give_money' => $params['need_receive_account'],
                        ]);
                    }else{
                        $business_type = 5;
                        if($params['need_receive_account']>0){//应收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 5,
                                'type' => 3,
                                'customers_id' => $ids,
                                'purchase_code' => $params['customer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => $params['need_receive_account'],
                                'write_off' => $params['need_receive_account'],
                                'remark' => $params['note'],
                            ]);
                        }else if($params['need_receive_account']<0){//预收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 6,
                                'type' => 3,
                                'customers_id' => $ids,
                                'purchase_code' => $params['customer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => -$params['need_receive_account'],
                                'write_off' => -$params['need_receive_account'],
                                'remark' => $params['note'],
                            ]);
                            $business_type = 6;
                        }
                        Db::name('kuerp_sales_payment_log')->insert([
                            'customers_id' => $ids,
                            'business_type' => $business_type,
                            'bill_time' => $params['account_time'],
                            'bill_code' => $params['customer_code'],
                            'give_money' => $params['need_receive_account'],
                        ]);
                    }

                    foreach ($data as $data_k=>$data_v){
                        $data[$data_k]['customers_code'] = trim($params['customer_code']);
                        $data[$data_k]['sid'] = $ids;
                    }
                    Db::name('kuerp_customers_user')->insertAll($data);
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

        $user = Db::name('kuerp_customers_user')->where(['sid'=>$ids])->select();
        $this->view->assign("user", $user);
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
            foreach ($list as $list_k=>$list_v){
                $order_info = Db::name('kuerp_goods_sales_info')->where(['customers_id'=>$list_v['id']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('客户'.$list_v['customer_name'].'已有采购单，不可删除');
                }
                $order_info = Db::name('kuerp_goods_sales_exchange_info')->where(['customers_id'=>$list_v['id']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('客户'.$list_v['customer_name'].'已有购货退货单，不可删除');
                }
                $order_info = Db::name('kuerp_warehouse')->where(['customers_id'=>$list_v['id']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('客户'.$list_v['customer_name'].'已有其他入库单，不可删除');
                }
                $order_info = Db::name('kuerp_writeoff')->where(['customers_id|customers_out_id|customers_enter_id'=>$list_v['id']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('客户'.$list_v['customer_name'].'已有核销单，不可删除');
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += Db::name('kuerp_customers')->where(['id'=>$v['id']])->delete();
                    Db::name('kuerp_customers_user')->where(['sid'=>$v['id']])->delete();
                    Db::name('kuerp_writeoff_source')->where(['customers_id'=>$v['id'],'type'=>3])->delete();
                    Db::name('kuerp_sales_payment_log')->where(['bill_code'=>$v['customer_code'],'business_type'=>['in',[5,6]]])->delete();
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
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
}
