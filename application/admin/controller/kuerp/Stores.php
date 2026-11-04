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
/**
 * 仓库管理
 *
 * @icon fa fa-circle-o
 */
class Stores extends Backend
{
    
    /**
     * Stores模型对象
     * @var \app\admin\model\kuerp\Stores
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Stores;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("storeTypeList", $this->model->getStoreTypeList());
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
                // 跳过前10行说明，第11行为表头，第12行开始为数据
                $headerRow = 11;   // 表头所在行
                $dataStartRow = 12; // 数据开始行

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
                    $insert[] = $row;
                }
            } catch (Exception $exception) {
                $this->error($exception->getMessage());
            }
            if (!$insert) {
                $this->error(__('No rows were updated'));
            }
            foreach ($insert as $insert_k=>$insert_v){
                if(empty($insert_v['仓库编号'])){
                    unset($insert[$insert_k]);
                }
            }

            $params = [];
            $code = [];
            $now_code = [];
            foreach ($insert as $insert_k=>$insert_v){
                if(!empty($insert_v['仓库编号'])){
                    if(in_array($insert_v['仓库编号'],$code)){
                        $this->error('导入失败，仓库编号'.$insert_v['仓库编号'].'重复');
                    }
                    $now_code[] = $insert_v['仓库编号'];
                    $params[$insert_k] = [
                        'store_code'        => $insert_v['仓库编号'],
                        'name'              => $insert_v['仓库名称'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }
            }

            $editstatus = isset($_POST['editstatus'])?$_POST['editstatus']:1;
            $edit_arr = [];
            if($editstatus==0){
                $code_s = Db::name('kuerp_stores')->where(['store_code'=>['in',$now_code]])->value('store_code');
                if(!empty($code_s)){
                    $this->error('导入失败，仓库编号'.$code_s.'已存在');
                }
            }else{
                $code_list = Db::name('kuerp_stores')->where(['store_code'=>['in',$now_code]])->column('id','store_code');
                foreach ($params as $params_k=>$params_v){
                    if(isset($code_list[$params_v['store_code']])){
                        $params_v['id'] = $code_list[$params_v['store_code']];
                        $edit_arr[] = $params_v;
                        unset($params[$params_k]);
                    }
                }
            }
            Db::startTrans();
            try {
                if(!empty($params)){
                    $result = Db::name('kuerp_stores')->insertAll($params);
                }
                if(!empty($edit_arr)){
                    foreach ($edit_arr as $edit_arr_k=>$edit_arr_v){
                        $id = $edit_arr_v['id'];
                        unset($edit_arr_v['createtime']);unset($edit_arr_v['id']);
                        $result = (new \app\admin\model\kuerp\Stores())->where(['id'=>$id])->update($edit_arr_v);
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

    public function index()
    {
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
                $wheres['status'] = '1';
            }
            $list = $this->model
                ->where($where)
                ->where($wheres)
                ->order($sort, $order)
                ->paginate($limit);


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

                if(Db::name('kuerp_stores')->where(['store_code'=>$params['store_code']])->find()){
                    $this->error('编号已被使用');
                }

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
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
        $code = \addons\kuerp\library\Goods::getCodes('',4);
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

                if(Db::name('kuerp_stores')->where(['store_code'=>$params['store_code'],'id'=>['<>',$ids]])->find()){
                    $this->error('编号已被使用');
                }

                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
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

            foreach ($list as $k => $v) {
                $arr = [
                    'kuerp_goods_purchase_order_info' => '购货订单',
                    'kuerp_goods_purchase_info' => '采购单',
                    'kuerp_goods_exchange_info' => '购货退货单',
                    'kuerp_goods_sales_order_info' => '销货订单',
                    'kuerp_goods_sales_info' => '销货单',
                    'kuerp_goods_sales_exchange_info' => '销货退货单',
                    'kuerp_allocation_info' => '调拨单',
                    'kuerp_warehousing_info' => '其他入库单',
                    'kuerp_warehouse_info' => '其他出库单',
                    'kuerp_assemble_info' => '组装单',
                    'kuerp_split_info' => '拆卸单',
                ];
                foreach ($arr as $arr_k=>$arr_v){
                    if($arr_k=='kuerp_allocation_info'){
                        $order =Db::name($arr_k)->where(['stores_out|stores_enter'=>$v['id']])->find();
                    }else{
                        $order =Db::name($arr_k)->where(['stores_id'=>$v['id']])->find();
                    }
                    if($order){
                        $this->error('仓库'.$v['name'].'已产生'.$arr_v.'，不可删除');
                    }
                }
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
            }catch (\Exception $e) {
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
