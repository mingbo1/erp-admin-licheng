<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
/**
 * 客户物料编码
 *
 * @icon fa fa-circle-o
 */
class CustomerGoodscoding extends Backend
{
    
    /**
     * CustomerGoodscoding模型对象
     * @var \app\admin\model\kuerp\CustomerGoodscoding
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\CustomerGoodscoding;

        $goods = Db::name('kuerp_goods')
            ->field('id,goods_code,goods_name,unit_id,more_unit,more_unit_id')
            ->select();
        $unit_id = [];
        foreach ($goods as $goods_k=>$goods_v){
            $unit_id[] = $goods_v['unit_id'];
        }
        $unit_name = Db::name('kuerp_unit')->where(['id'=>['in',$unit_id]])->column('name','id');
        foreach ($goods as $goods_k=>$goods_v){
            $goods[$goods_k]['unit_name'] = isset($unit_name[$goods_v['unit_id']])?$unit_name[$goods_v['unit_id']]:'';
        }
        $this->view->assign("goods", $goods);
        $this->assignconfig("goods", json_encode($goods));
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
                $fields = [];
                for ($currentRow = 2; $currentRow <= 2; $currentRow++) {
                    for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                        $val = (string)$currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                        $val = str_replace('*','',$val);
                        $fields[] = (string)$val;
                    }
                }

                for ($currentRow = 3; $currentRow <= $allRow; $currentRow++) {
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

            $params = [];
            $goods_code = [];
            $customer = [];
            $customer_code = [];
            foreach ($insert as $insert_k=>$insert_v){
                if(!empty($insert_v['客户编号']) && !empty($insert_v['商品编号']) && !empty($insert_v['客户物料编码'])){
                    if(!in_array($insert_v['商品编号'],$goods_code)){
                        $goods_code[] = $insert_v['商品编号'];
                    }
                    if(!in_array($insert_v['客户编号'],$customer)){
                        $customer[] = $insert_v['客户编号'];
                    }
                    if(!in_array($insert_v['客户物料编码'],$customer_code)){
                        $customer_code[] = $insert_v['客户物料编码'];
                    }else{
                        $this->error('导入失败，物料编码'.$insert_v['客户物料编码'].'重复');
                    }
                    $params[$insert_k] = [
                        'customers_id'      => $insert_v['客户编号'],
                        'goods_code'        => $insert_v['商品编号'],
                        'customer_code'     => $insert_v['客户物料编码'],
                        'createtime'        => time(),
                        'updatetime'        => time(),
                    ];
                }
            }

            $code_s = Db::name('kuerp_customers')->where(['customer_code'=>['in',$customer]])->column('customer_code');
            if(sizeof($code_s)!=sizeof($customer)){
                foreach ($customer as $customer_k=>$customer_v){
                    if(!in_array($customer_v,$code_s)){
                        $this->error('导入失败，客户'.$customer_v.'不存在');
                    }
                }
            }
            $code_s = Db::name('kuerp_customers')->where(['customer_code'=>['in',$customer]])->column('id','customer_code');
            foreach ($params as $params_k=>$params_v){
                $params[$params_k]['customers_id'] = $code_s[$params_v['customers_id']];
            }

            $goods = Db::name('kuerp_goods')->where(['goods_code'=>['in',$goods_code]])
                ->field('id,goods_name,unit_id,more_unit,more_unit_id,goods_code')
                ->select();
            $unit_id = []; $unit_ids = [];
            foreach ($goods as $goods_k=>$goods_v){
                if($goods_v['more_unit']==2){
                    $unit_id[] = $goods_v['unit_id'];
                }else{
                    $unit_ids[] = $goods_v['more_unit_id'];
                }
            }
            $more_unit_arr = Db::name('kuerp_unit_conversion')->where(['id'=>['in',$unit_ids]])->column('main_unit','id');
            $code_arr = [];
            foreach ($goods as $goods_k=>$goods_v){
                if($goods_v['more_unit']==2){
                    $code_arr[$goods_v['goods_code']] = $goods_v['unit_id'];
                }else{
                    $code_arr[$goods_v['goods_code']] = $more_unit_arr[$goods_v['more_unit_id']];
                }
            }
            foreach ($params as $params_k=>$params_v){
                $params[$params_k]['unit_id'] = $code_arr[$params_v['goods_code']];
            }

            $editstatus = isset($_POST['editstatus'])?$_POST['editstatus']:1;
            $edit_arr = [];
            if($editstatus==0){
                foreach ($params as $params_k=>$params_v){
                    $code = $this->model->where(['customers_id'=>$params_v['customers_id'],'goods_code'=>$params_v['goods_code']])->value('customer_code');
                    if(!empty($code)){
                        $this->error('导入失败，物料编码'.$params_v['customer_code'].'已录入');
                    }
                }
                $code = $this->model->where(['customer_code'=>['in',$customer_code]])->value('id');
                if(!empty($code)){
                    $this->error('导入失败，物料编码'.$code.'已存在');
                }
            }else{
                foreach ($params as $params_k=>$params_v){
                    $code = $this->model->where(['customers_id'=>$params_v['customers_id'],'goods_code'=>$params_v['goods_code']])->find();
                    if(!empty($code)){
                        $params_v['id'] = $code['id'];
                        $edit_arr[] = $params_v;
                    }
                }
            }
            Db::startTrans();
            try {
                if(!empty($params)){
                    $this->model->insertAll($params);
                }
                if(!empty($edit_arr)){
                    foreach ($edit_arr as $edit_arr_k=>$edit_arr_v){
                        $id = $edit_arr_v['id'];
                        unset($edit_arr_v['createtime']);unset($edit_arr_v['id']);
                        $result = (new \app\admin\model\kuerp\CustomerGoodscoding())->where(['id'=>$id])->update($edit_arr_v);
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

            $list = $this->model
                    ->with(['goods','customers','unit'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('customers')->visible(['customer_name']);
                $row->getRelation('goods')->visible(['goods_name']);
                $row->getRelation('unit')->visible(['name']);
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

                if(empty($params['customers_id'])){
                    $this->error('请选择客户');
                }
                $data = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){
                        if(!isset($params['customer_code'][$k])){
                            $this->error('请填写物料编码');
                        }
                        $data[] = [
                            'customers_id'=>$params['customers_id'],
                            'goods_code'=>$params['goods_code'][$k],
                            'unit_id'=>$params['unit_id'][$k],
                            'customer_code'=>$params['customer_code'][$k],
                            'createtime'=>time(),
                            'updatetime'=>time(),
                        ];
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->insertAll($data);
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

                if(empty($params['customers_id'])){
                    $this->error('请选择客户');
                }
                $data = [];
                foreach ($params['goods_code'] as $k=>$v){
                    if(!empty($v)){
                        if(!isset($params['customer_code'][$k])){
                            $this->error('请填写物料编码');
                        }
                        $data = [
                            'customers_id'=>$params['customers_id'],
                            'goods_code'=>$params['goods_code'][$k],
                            'unit_id'=>$params['unit_id'][$k],
                            'customer_code'=>$params['customer_code'][$k],
                            'createtime'=>time(),
                            'updatetime'=>time(),
                        ];
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->where(['id'=>$ids])->update($data);
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
        $row['unit_name'] = Db::name('kuerp_unit')->where(['id'=>$row['unit_id']])->value('name');
        return $this->view->fetch();
    }
}
