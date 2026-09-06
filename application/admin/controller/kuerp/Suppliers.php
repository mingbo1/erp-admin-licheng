<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as PHPExcel_Shared_Date;
use fast\Tree;
/**
 * 供应商管理
 *
 * @icon fa fa-circle-o
 */
class Suppliers extends Backend
{

    /**
     * Suppliers模型对象
     * @var \app\admin\model\kuerp\Suppliers
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Suppliers;
        $this->view->assign("statusList", $this->model->getStatusList());

        $categorylists = collection(model('\app\admin\model\kuerp\Supplyertype')->where(['status'=>'1'])->field('id,pid,supplier_type_name as name')->select())->toArray();
        Tree::instance()->init($categorylists);
        $categorylists = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'name');
        $categorylist = array('' => '==请选择==');
        foreach ($categorylists as $k => $v) {
            $categorylist[$v['id']] = $v['name'];
        }
        $this->view->assign('supplyertype', $categorylist);
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
                // 跳过前14行说明，第15行为表头，第16行开始为数据（与客户导入模板结构一致）
                $headerRow = 15;    // 表头所在行
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
            $cate = [];
            foreach ($insert as $insert_k=>$insert_v){
                if(empty($insert_v['联系人']) || (empty($insert_v['手机']) && empty($insert_v['座机']))){
                    unset($insert[$insert_k]);
                }else{
                    if(!empty($insert_v['供应商类别']) && !in_array($insert_v['供应商类别'],$cate)){
                        $cate[] = $insert_v['供应商类别'];
                    }
                }
            }

            $catelist = Db::name('kuerp_supplyertype')->where(['supplier_type_name'=>['in',$cate]])->column('supplier_type_name');
            if(sizeof($catelist)!=sizeof($cate)){
                foreach ($cate as $cate_k=>$cate_v){
                    if(!in_array($cate_v,$catelist)){
                        $this->error('供应商类别'.$cate_v.'不存在，导入失败');
                    }
                }
            }
            $catelist = Db::name('kuerp_supplyertype')->where(['supplier_type_name'=>['in',$cate]])->column('id','supplier_type_name');

            $params = [];
            $nowsp = '';
            $supplyer_code = [];
            $auto_code = ''; // 供应商编号自动生成起点（为空则查询库内最大编号+1）
            foreach ($insert as $insert_k=>$insert_v){
                if(!empty($insert_v['供应商名称'])){
                    // 供应商编号为空时自动生成，与手动新增供应商编号规则一致
                    if(empty($insert_v['供应商编号'])){
                        $auto_code = ($auto_code==='') ? \addons\kuerp\library\Goods::getCodes('',2) : \addons\kuerp\library\Goods::getCodes($auto_code,2);
                        if(empty($auto_code)){
                            $this->error('供应商编号自动生成失败，请检查编号规则配置');
                        }
                        $insert_v['供应商编号'] = $auto_code;
                    }
                    $nowsp = $insert_k;
                    if(in_array($insert_v['供应商编号'],$supplyer_code)){
                        $this->error('导入失败，供应商编号'.$insert_v['供应商编号'].'重复');
                    }
                    $supplyer_code[] = $insert_v['供应商编号'];
                    $params[$insert_k] = [
                        'supplyer_code'     => $insert_v['供应商编号'],
                        'supplyer_name'     => $insert_v['供应商名称'],
                        'supplyertype_id'   => isset($catelist[$insert_v['供应商类别']])?$catelist[$insert_v['供应商类别']]:0,
                        'account_time'      => !empty($insert_v['余额日期'])?$insert_v['余额日期']:time(),
                        'opening_payable'   => $insert_v['期初应付款'],
                        'opening_prepayment'=> $insert_v['期初预付款'],
                        'need_give_account' => round(((float)$insert_v['期初应付款']-(float)$insert_v['期初预付款']),2),
                        // 增值税税率清洗: 模板填"13%"这种带百分号文本，列是double(15,4) unsigned，
                        // 直接入库会报 1265 Data truncated 警告。去掉%转数字(13=13%，与手动新增语义一致)，
                        // 空值保留''由下方unset逻辑跳过。
                        'vat_rate'          => ($insert_v['增值税税率']!=='' && $insert_v['增值税税率']!==null)
                                                ? (float)str_replace('%','',(string)$insert_v['增值税税率'])
                                                : '',
                        'identification'    => $insert_v['纳税人识别号'],
                        'deposit_bank'      => $insert_v['开户银行'],
                        'bank_number'       => $insert_v['银行账号'],
                        'note'              => $insert_v['备注'],

                        'first_connect_name'=> $insert_v['联系人'],
                        'first_connect_mobile'=> $insert_v['手机'],
                        'first_connect_phone'=> $insert_v['座机'],
                        'qq_wx_email'       => $insert_v['QQ/微信'],
                        'connect_address'   => $insert_v['联系地址'],

                        'createtime'        => time(),
                        'updatetime'        => time(),
                        'userlist'          =>[]
                    ];
                }

                if(isset($params[$nowsp]['userlist'])){
                    $params[$nowsp]['userlist'][] = [
                        'supplyer_code'         => $params[$nowsp]['supplyer_code'],
                        'first_connect_name'    => $insert_v['联系人'],
                        'first_connect_mobile'  => $insert_v['手机'],
                        'first_connect_phone'   => $insert_v['座机'],
                        'qq_wx_email'           => $insert_v['QQ/微信'],
                        'connect_address'       => $insert_v['联系地址'],
                        'is_first'              => ($nowsp==$insert_k)?1:0,
                        'createtime'            => time(),
                        'updatetime'            => time(),
                    ];
                }
            }

            $editstatus = isset($_POST['editstatus'])?$_POST['editstatus']:1;
            $edit_arr = [];
            if($editstatus==0){
                $code = Db::name('kuerp_suppliers')->where(['supplyer_code'=>['in',$supplyer_code]])->value('supplyer_code');
                if(!empty($code)){
                    $this->error('导入失败，供应商编号'.$code.'已存在');
                }
            }else{
                $code_list = Db::name('kuerp_suppliers')->where(['supplyer_code'=>['in',$supplyer_code]])->column('id','supplyer_code');
                foreach ($params as $params_k=>$params_v){
                    if(isset($code_list[$params_v['supplyer_code']])){
                        $params_v['id'] = $code_list[$params_v['supplyer_code']];
                        $edit_arr[] = $params_v;
                        unset($params[$params_k]);
                    }
                }
            }

            Db::startTrans();
            try {
                foreach ($params as $params_k=>$params_v){
                    $userarr = $params_v['userlist'];unset($params_v['userlist']);
                    $result = Db::name('kuerp_suppliers')->insertGetId($params_v);

                    $business_type = 5;
                    if($params_v['need_give_account']>0){//应付
                        Db::name('kuerp_writeoff_source')->insert([
                            'purchase_type' => 5,
                            'type' => 3,
                            'suppliers_code' => $params_v['supplyer_code'],
                            'purchase_code' => $params_v['supplyer_code'],
                            'purchase_time' => $params_v['account_time'],
                            'price' => $params_v['need_give_account'],
                            'write_off' => $params_v['need_give_account'],
                        ]);
                    }else if($params_v['need_give_account']<0){//预付
                        Db::name('kuerp_writeoff_source')->insert([
                            'purchase_type' => 6,
                            'type' => 3,
                            'suppliers_code' => $params_v['supplyer_code'],
                            'purchase_code' => $params_v['supplyer_code'],
                            'purchase_time' => $params_v['account_time'],
                            'price' => $params_v['need_give_account'],
                            'write_off' => $params_v['need_give_account'],
                        ]);
                        $business_type = 6;
                    }
                    Db::name('kuerp_payment_log')->insert([
                        'suppliers_code' => $params_v['supplyer_code'],
                        'business_type' => $business_type,
                        'bill_time' => $params_v['account_time'],
                        'bill_code' => $params_v['supplyer_code'],
                        'give_money' => $params_v['need_give_account'],
                    ]);

                    if(!empty($userarr)){
                        foreach ($userarr as $data_k=>$data_v){
                            $userarr[$data_k]['sid'] = $result;
                        }
                        Db::name('kuerp_suppliers_user')->insertAll($userarr);
                    }
                }
                if(!empty($edit_arr)){
                    foreach ($edit_arr as $edit_arr_k=>$edit_arr_v){

                        $business_type = 5;
                        Db::name('kuerp_writeoff_source')->where(['suppliers_code'=>$edit_arr_v['supplyer_code'],'type'=>3])->delete();
                        Db::name('kuerp_payment_log')->where(['bill_code'=>$edit_arr_v['supplyer_code'],'business_type'=>['in',[5,6]]])->delete();
                        if($edit_arr_v['need_give_account']>0){//应收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 5,
                                'type' => 3,
                                'suppliers_code' => $edit_arr_v['supplyer_code'],
                                'purchase_code' => $edit_arr_v['supplyer_code'],
                                'purchase_time' => $edit_arr_v['account_time'],
                                'price' => $edit_arr_v['need_give_account'],
                                'write_off' => $edit_arr_v['need_give_account'],
                            ]);
                        }else if($edit_arr_v['need_give_account']<0){//预收
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 6,
                                'type' => 3,
                                'suppliers_code' => $edit_arr_v['supplyer_code'],
                                'purchase_code' => $edit_arr_v['supplyer_code'],
                                'purchase_time' => $edit_arr_v['account_time'],
                                'price' => $edit_arr_v['need_give_account'],
                                'write_off' => $edit_arr_v['need_give_account'],
                            ]);
                            $business_type = 6;
                        }
                        Db::name('kuerp_payment_log')->insert([
                            'suppliers_code' => $edit_arr_v['supplyer_code'],
                            'business_type' => $business_type,
                            'bill_time' => $edit_arr_v['account_time'],
                            'bill_code' => $edit_arr_v['supplyer_code'],
                            'give_money' => $edit_arr_v['need_give_account'],
                        ]);

                        $userarr = $edit_arr_v['userlist'];unset($edit_arr_v['userlist']);
                        $id = $edit_arr_v['id'];
                        unset($edit_arr_v['createtime']);unset($edit_arr_v['id']);
                        $result = (new \app\admin\model\kuerp\Suppliers())->where(['id'=>$id])->update($edit_arr_v);
                        Db::name('kuerp_suppliers_user')->where(['sid'=>$id])->delete();
                        if(!empty($userarr)){
                            foreach ($userarr as $data_k=>$data_v){
                                $userarr[$data_k]['sid'] = $id;
                            }
                            Db::name('kuerp_suppliers_user')->insertAll($userarr);
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
                $wheres['suppliers.status'] = '1';
            }
            $list = $this->model
                ->with(['supplyertype'])
                ->where($where)->where($wheres)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('supplyertype')->visible(['supplier_type_name']);
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
                $params['need_give_account'] = round(((float)$params['opening_payable']-(float)$params['opening_prepayment']),2);
                $params['opening_payable'] = $params['opening_payable']===''?null:(float)$params['opening_payable'];
                $params['opening_prepayment'] = $params['opening_prepayment']===''?null:(float)$params['opening_prepayment'];

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
                $params['first_connect_name'] = $user['name'][$default];
                $params['first_connect_mobile'] = isset($user['mobile'][$default])?$user['mobile'][$default]:'';
                $params['first_connect_phone'] = isset($user['phone'][$default])?$user['phone'][$default]:'';
                $params['qq_wx_email'] = isset($user['qqnum'][$default])?$user['qqnum'][$default]:'';
                $params['connect_address'] = isset($user['address'][$default])?$user['address'][$default]:'';

                if(Db::name('kuerp_suppliers')->where(['supplyer_code'=>trim($params['supplyer_code'])])->find()){
                    $this->error('供应商编号已占用');
                }

                if($params['vat_rate']===''){
                    unset($params['vat_rate']);
                }

                $result = false;
                Db::startTrans();
                try {
                    $params['receive_account'] = $params['opening_prepayment']-$params['opening_prepayment'];
                    $result = Db::name('kuerp_suppliers')->insertGetId($params);
                    $business_type = 5;
                    if($params['need_give_account']>0){//应付
                        Db::name('kuerp_writeoff_source')->insert([
                            'purchase_type' => 5,
                            'type' => 3,
                            'suppliers_code' => $params['supplyer_code'],
                            'purchase_code' => $params['supplyer_code'],
                            'purchase_time' => $params['account_time'],
                            'price' => $params['need_give_account'],
                            'write_off' => $params['need_give_account'],
                            'remark' => $params['note'],
                        ]);
                    }else if($params['need_give_account']<0){//预付
                        Db::name('kuerp_writeoff_source')->insert([
                            'purchase_type' => 6,
                            'type' => 3,
                            'suppliers_code' => $params['supplyer_code'],
                            'purchase_code' => $params['supplyer_code'],
                            'purchase_time' => $params['account_time'],
                            'price' => -$params['need_give_account'],
                            'write_off' => -$params['need_give_account'],
                            'remark' => $params['note'],
                        ]);
                        $business_type = 6;
                    }
                    Db::name('kuerp_payment_log')->insert([
                        'suppliers_code' => $params['supplyer_code'],
                        'business_type' => $business_type,
                        'bill_time' => $params['account_time'],
                        'bill_code' => $params['supplyer_code'],
                        'give_money' => $params['need_give_account'],
                    ]);

                    foreach ($data as $data_k=>$data_v){
                        $data[$data_k]['supplyer_code'] = trim($params['supplyer_code']);
                        $data[$data_k]['sid'] = $result;
                    }
                    Db::name('kuerp_suppliers_user')->insertAll($data);
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
        $code = \addons\kuerp\library\Goods::getCodes('',2);
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
                $params['opening_payable'] = $params['opening_payable']===''?null:(float)$params['opening_payable'];
                $params['opening_prepayment'] = $params['opening_prepayment']===''?null:(float)$params['opening_prepayment'];
                $params['need_give_account'] = $row['need_give_account'] - ($row['opening_payable']-$row['opening_prepayment']) + ((float)$params['opening_payable']-(float)$params['opening_prepayment']);

                $data = [];
                $user = $this->request->post("user/a");
                if(empty($user['first'])){
                    $this->error('请填写联系人');
                }else{
                    $default = '';
                    foreach ($user['first'] as $user_first_k=>$user_first_v){
                        if(!isset($user['name'][$user_first_k]) || empty($user['name'][$user_first_k])){
                            $this->error('请完善联系人信息');
                        }
                        if($user_first_v==1 && $default!==''){
                            $this->error('只能选择一个首要联系人');
                        }
                        if($user_first_v==1){
                            $default = $user_first_k;
                        }
                        $data[] = [
                            'id' => !empty($user['id'][$user_first_k])?$user['id'][$user_first_k]:'',
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
                $default = $default===''?0:$default;
                $data[$default]['is_first'] = 1;
                $params['first_connect_name'] = $user['name'][$default];
                $params['first_connect_mobile'] = isset($user['mobile'][$default])?$user['mobile'][$default]:'';
                $params['first_connect_phone'] = isset($user['phone'][$default])?$user['phone'][$default]:'';
                $params['qq_wx_email'] = isset($user['qqnum'][$default])?$user['qqnum'][$default]:'';
                $params['connect_address'] = isset($user['address'][$default])?$user['address'][$default]:'';

                if(Db::name('kuerp_suppliers')->where(['supplyer_code'=>trim($params['supplyer_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('供应商编号已占用');
                }

                if($params['vat_rate']===''){
                    unset($params['vat_rate']);
                }
                $userids = Db::name('kuerp_suppliers_user')->where(['sid'=>$ids])->column('id');

                $WriteoffSource = Db::name('kuerp_writeoff_source')->where(['suppliers_code'=>$row['supplyer_code'],'type'=>3])->value('price');
                if(((float)$row['opening_payable']-(float)$row['opening_prepayment'])==$WriteoffSource && ((float)$row['opening_payable']-(float)$row['opening_prepayment'])!=((float)$params['opening_payable']-(float)$params['opening_prepayment'])){
                    $order = Db::name('kuerp_writeoff')->where(['suppliers_id|suppliers_out_id|suppliers_enter_id'=>$row['id']])->find();
                    if($order){
                        return ['status'=>400,'msg'=>'已关联核销单，不可编辑金额'];
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    $params['receive_account'] = $params['opening_prepayment']-$params['opening_prepayment'];
                    $result = Db::name('kuerp_suppliers')->where(['id'=>$ids])->update($params);
                    foreach ($data as $data_k=>$data_v){
                        $data[$data_k]['supplyer_code'] = trim($params['supplyer_code']);
                        $data[$data_k]['sid'] = $ids;
                    }

                    $WriteoffSource = Db::name('kuerp_writeoff_source')->where(['suppliers_code'=>$row['supplyer_code'],'type'=>3])->find();
                    $PaymentLog = Db::name('kuerp_payment_log')->where(['bill_code'=>$row['supplyer_code'],'business_type'=>['in',[5,6]]])->find();
                    $params['need_give_account'] = ((float)$params['opening_payable']-(float)$params['opening_prepayment']);
                    if($WriteoffSource){
                        $business_type = 5;
                        if($params['need_give_account']>0){//应付
                            Db::name('kuerp_writeoff_source')->where(['id'=>$WriteoffSource['id']])->update([
                                'purchase_type' => '5',
                                'suppliers_code' => $params['supplyer_code'],
                                'purchase_code' => $params['supplyer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => $params['need_give_account'],
                                'write_off' => $params['need_give_account'],
                                'remark' => $params['note'],
                            ]);
                        }else if($params['need_give_account']<0){//预付
                            Db::name('kuerp_writeoff_source')->where(['id'=>$WriteoffSource['id']])->update([
                                'purchase_type' => '6',
                                'suppliers_code' => $params['supplyer_code'],
                                'purchase_code' => $params['supplyer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => -$params['need_give_account'],
                                'write_off' => -$params['need_give_account'],
                                'remark' => $params['note'],
                            ]);
                            $business_type = 6;
                        }
                        Db::name('kuerp_payment_log')->where(['id'=>$PaymentLog['id']])->update([
                            'suppliers_code' => $params['supplyer_code'],
                            'business_type' => $business_type,
                            'bill_time' => $params['account_time'],
                            'bill_code' => $params['supplyer_code'],
                            'give_money' => ((float)$params['opening_payable']-(float)$params['opening_prepayment']),
                        ]);
                    }else{
                        $business_type = 5;
                        if($params['need_give_account']>0){//应付
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 5,
                                'type' => 3,
                                'suppliers_code' => $params['supplyer_code'],
                                'purchase_code' => $params['supplyer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => $params['need_give_account'],
                                'write_off' => $params['need_give_account'],
                                'remark' => $params['note'],
                            ]);
                        }else if($params['need_give_account']<0){//预付
                            Db::name('kuerp_writeoff_source')->insert([
                                'purchase_type' => 6,
                                'type' => 3,
                                'suppliers_code' => $params['supplyer_code'],
                                'purchase_code' => $params['supplyer_code'],
                                'purchase_time' => $params['account_time'],
                                'price' => -$params['need_give_account'],
                                'write_off' => -$params['need_give_account'],
                                'remark' => $params['note'],
                            ]);
                            $business_type = 6;
                        }
                        Db::name('kuerp_payment_log')->insert([
                            'suppliers_code' => $params['supplyer_code'],
                            'business_type' => $business_type,
                            'bill_time' => $params['account_time'],
                            'bill_code' => $params['supplyer_code'],
                            'give_money' => $params['need_give_account'],
                        ]);
                    }

                    foreach ($data as $data_k=>$data_v){
                        if(!empty($data_v['id'])){
                            $id = $data_v['id'];unset($data_v['id']);
                            Db::name('kuerp_suppliers_user')->where(['id'=>$id])->update($data_v);
                            unset($data[$data_k]);
                            if(in_array($id,$userids)){
                                $arr = array_flip($userids);
                                unset($arr[$id]);
                                $userids = array_flip($arr);
                            }
                        }else{
                            unset($data[$data_k]['id']);
                        }
                    }
                    if(!empty($userids)){
                        Db::name('kuerp_suppliers_user')->where(['id'=>['in',$userids]])->delete();
                    }
                    if(!empty($data)){
                        Db::name('kuerp_suppliers_user')->insertAll($data);
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

        $user = Db::name('kuerp_suppliers_user')->where(['sid'=>$ids])->select();
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
                $order_info = Db::name('kuerp_goods_purchase_info')->where(['supplyer_code'=>$list_v['supplyer_code']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('供应商'.$list_v['supplyer_name'].'已有购货单，不可删除');
                }
                $order_info = Db::name('kuerp_goods_exchange_info')->where(['supplyer_code'=>$list_v['supplyer_code']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('供应商'.$list_v['supplyer_name'].'已有购货退货单，不可删除');
                }
                $order_info = Db::name('kuerp_warehousing')->where(['suppliers_id'=>$list_v['id']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('供应商'.$list_v['supplyer_name'].'已有其他入库单，不可删除');
                }
                $order_info = Db::name('kuerp_writeoff')->where(['suppliers_id|suppliers_out_id|suppliers_enter_id'=>$list_v['id']])->field('id')->find();
                if(!empty($order_info)){
                    $this->error('供应商'.$list_v['supplyer_name'].'已有核销单，不可删除');
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += Db::name('kuerp_suppliers')->where(['id'=>$v['id']])->delete();
                    Db::name('kuerp_suppliers_user')->where(['supplyer_code'=>$v['supplyer_code']])->delete();
                    Db::name('kuerp_writeoff_source')->where(['suppliers_code'=>$v['supplyer_code'],'type'=>3])->delete();
                    Db::name('kuerp_payment_log')->where(['bill_code'=>$v['supplyer_code'],'business_type'=>['in',[5,6]]])->delete();
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

    public function destroy($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $this->model->where($pk, 'in', $ids);
        }
        $count = 0;
        Db::startTrans();
        try {
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $k => $v) {
                $count += $v->delete(true);
                Db::name('kuerp_suppliers_user')->where(['supplyer_code'=>$v['supplyer_code']])->delete();
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
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }
}
