<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
/**
 * 盘点
 *
 * @icon fa fa-circle-o
 */
class Inventory extends Backend
{
    protected $noNeedRight = ['export'];
    /**
     * Inventory模型对象
     * @var \app\admin\model\kuerp\Inventory
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Inventory;
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
                $fields = [];
                for ($currentRow = 1; $currentRow <= 1; $currentRow++) {
                    for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                        $val = (string)$currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                        $val = str_replace('*','',$val);
                        $fields[] = (string)$val;
                    }
                }

                for ($currentRow = 2; $currentRow <= $allRow; $currentRow++) {
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

            $data = [];
            $code = \addons\kuerp\library\Goods::getCodes('',15);
            $purchase_time = time();
            $invertory = 0;$profit = 0;
            foreach ($insert as $insert_k=>$insert_v){
                if(!isset($insert_v['仓库']) || !isset($insert_v['商品类别']) || !isset($insert_v['商品编号']) || !isset($insert_v['商品名称']) || !isset($insert_v['属性编号']) || !isset($insert_v['属性名称']) || !isset($insert_v['规格型号']) || !isset($insert_v['单位']) || !isset($insert_v['系统库存']) || !isset($insert_v['盘点库存']) || !isset($insert_v['商品条码'])){
                    $this->error('表格格式错误');
                }
                if($insert_v['系统库存']===''){
                    $this->error('请输入'.$insert_v['商品名称'].'系统库存');
                }
                if($insert_v['盘点库存']===''){
                    $this->error('请输入'.$insert_v['商品名称'].'盘点库存');
                }

                if(empty($insert_v['仓库'])){
                    $this->error($insert_v['商品名称'].'仓库信息错误');
                }else{
                    $store = Db::name('kuerp_stores')->where(['name'=>$insert_v['仓库']])->value('id');
                    if(empty($store)){
                        $this->error($insert_v['商品名称'].'仓库信息错误');
                    }
                }
                if(!empty($insert_v['商品类别'])){
                    $goods_type = Db::name('kuerp_goods_type')->where(['supplier_type_name'=>$insert_v['商品类别']])->value('id');
                    if(empty($goods_type)){
                        $this->error($insert_v['商品名称'].'商品类别信息错误');
                    }
                }
                if(!empty($insert_v['商品条码'])){
                    $goods = Db::name('kuerp_goods')->where(['goods_barcode'=>$insert_v['商品条码']])->value('id');
                    if(empty($goods)){
                        $this->error($insert_v['商品名称'].'信息错误');
                    }
                }
                if(!empty($insert_v['商品编号'])){
                    $goods = Db::name('kuerp_goods')->where(['goods_code'=>$insert_v['商品编号']])->value('id');
                    if(empty($goods)){
                        $this->error($insert_v['商品名称'].'信息错误');
                    }
                }
                if(!empty($insert_v['属性编号'])){
                    $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_code'=>$insert_v['商品编号'],'attr_code'=>$insert_v['属性编号']])->value('id');
                    if(empty($goods_attr)){
                        $this->error($insert_v['商品名称'].'属性信息错误');
                    }
                }
                $stock = (float)$insert_v['系统库存'];
                $invertory_stock = (float)$insert_v['盘点库存'];
                $data[] = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $code,
                    'purchase_time'     =>  $purchase_time,
                    'goods_type'        =>  !empty($insert_v['商品类别'])?$goods_type:null,//商品类别
                    'goods_id'          =>  $goods,
                    'stores_id'         =>  $store,
                    'goods_attr'        =>  !empty($insert_v['属性编号'])?$goods_attr:null,
                    'stock'             =>  $stock,
                    'invertory_stock'   =>  $invertory_stock,
                    'profit'            =>  ($invertory_stock-$stock),
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ]);
                if(($invertory_stock-$stock)>0){
                    $invertory += ($invertory_stock-$stock);
                }else if(($invertory_stock-$stock)<0){
                    $profit += ($invertory_stock-$stock);
                }
            }
            if(!empty($data)){
                $list = Db::name('kuerp_goods_stock')->alias('gs')
                    ->join('kuerp_goods g','g.id=gs.goods_id')
                    ->where(['g.serial'=>['<>','1']])
                    ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
                    ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
                    ->order('gs.goods_code ASC')
                    ->select();
                if(sizeof($list)==sizeof($data)){
                    $store_type = 1;
                }else{
                    $store_type = 2;
                }
                $result = '';
                if($invertory>0){
                    $result .= ' 盘盈：'.$invertory.';';
                }
                if($profit<0){
                    $result .= ' 盘亏：'.$profit.';';
                }
                $row = \addons\kuerp\library\Goods::setDataInfo([
                    'purchase_code'     =>  $code,
                    'purchase_time'     =>  $purchase_time,
                    'store_type'        =>  $store_type,
                    'result'            => $result,
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
                    Db::name('kuerp_inventory_info')->insertAll($data);
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
        return $this->view->fetch();
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['admin'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('admin')->visible(['username']);
            }
            foreach ($list as $list_k=>$list_v){
                $result = '';
                $code = Db::name('kuerp_warehousing')->where(['order_type'=>'1','order_id'=>$list_v['id']])->column('purchase_code');
                if(!empty($code)){
                    foreach ($code as $code_k=>$code_v){
                        $result .= '盘盈单：'.$code_v.'<br>';
                    }
                }
                $code = Db::name('kuerp_warehouse')->where(['order_type'=>'1','order_id'=>$list_v['id']])->column('purchase_code');
                if(!empty($code)){
                    foreach ($code as $code_k=>$code_v){
                        $result .= '盘亏单：'.$code_v.'<br>';
                    }
                }
                if($result){
                    $list[$list_k]['result'] = $result;
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($ids = null,$stores_id='',$goods_code='')
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $code = \addons\kuerp\library\Goods::getCodes('',15);
                $purchase_time = time();
                $data = [];

                $invertory = 0;$profit = 0;
                foreach ($params['goods_id'] as $k=>$v){
                    if(!empty($v)){//有产品
                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $code,
                            'purchase_time'     =>  $purchase_time,

                            'goods_type'        =>  $params['goods_type'][$k]===''?null:(float)$params['goods_type'][$k],//商品类别
                            'goods_id'          =>  $params['goods_id'][$k]===''?null:(float)$params['goods_id'][$k],
                            'stores_id'         =>  $params['stores_id'][$k]===''?null:(float)$params['stores_id'][$k],
                            'goods_attr'        =>  $params['goods_attr'][$k]===''?null:(float)$params['goods_attr'][$k],
                            'stock'             =>  $params['stock'][$k]===''?null:(float)$params['stock'][$k],
                            'invertory_stock'   =>  $params['invertory_stock'][$k]===''?null:(float)$params['invertory_stock'][$k],
                            'profit'            =>  $params['profit'][$k]===''?null:(float)$params['profit'][$k],

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                        if($params['profit'][$k]>0){
                            $invertory += $params['profit'][$k];
                        }else if($params['profit'][$k]<0){
                            $profit += $params['profit'][$k];
                        }
                    }
                }
                if(!empty($data)){
                    $list = Db::name('kuerp_goods_stock')->alias('gs')
                        ->join('kuerp_goods g','g.id=gs.goods_id')
                        ->where(['g.serial'=>['<>','1']])
                        ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->order('gs.goods_code ASC')
                        ->select();
                    if(sizeof($list)==sizeof($data)){
                        $store_type = 1;
                    }else{
                        $store_type = 2;
                    }
                    $result = '';
                    if($invertory>0){
                        $result .= ' 盘盈：'.$invertory.';';
                    }
                    if($profit<0){
                        $result .= ' 盘亏：'.$profit.';';
                    }
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'purchase_code'     => $code,
                        'purchase_time'     => $purchase_time,
                        'store_type'        => $store_type,
                        'result'            => $result,
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
                        Db::name('kuerp_inventory_info')->insertAll($data);
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
        $where = [];
        if(!empty($stores_id)){
            $where['gs.stores_id'] = $stores_id;
        }
        if(!empty($goods_code)){
            $where['gs.goods_code'] = ['like','%'.$goods_code.'%'];
        }
        $list = Db::name('kuerp_goods_stock')->alias('gs')
            ->join('kuerp_goods g','g.id=gs.goods_id')
            ->where(['g.serial'=>['<>','1']])
            ->where($where)
            ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
            ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
            ->order('gs.goods_code ASC')
            ->select();

        foreach ($list as $list_k=>$list_v){
            $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('id,goods_name,goods_code,note,goods_type_id,goods_specification_id,unit_id')->find();
            $goods_type = Db::name('kuerp_goods_type')->where(['id'=>$goods['goods_type_id']])->field('id,supplier_type_name')->find();
            $stores = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('id,name')->find();
            $list[$list_k]['goods'] = $goods;
            $list[$list_k]['goods_type'] = $goods_type;
            $list[$list_k]['stores'] = $stores;

            $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['attribute_id']])->value('attribute_id');
            $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
            if($attribute_ids){
                $attribute_ids = explode('&',$attribute_ids);
                $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                $attribute_name = implode('/',$attribute);
                $list[$list_k]['attr_name'] = $attribute_name;
            }else{
                $list[$list_k]['attr_name'] = '';
            }

            $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_code'=>$list_v['goods_code'],'attribute_id'=>$list_v['attribute_id']])->value('id');
            $list[$list_k]['attr_id'] = $goods_attr;

            $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->value('name');
            $list[$list_k]['unit_name'] = $unit;
        }
        $this->view->assign("list", $list);

        $kuerp_stores = Db::name('kuerp_stores')->where(['id'=>$stores_id])->field('id,name')->find();
        $this->assignconfig("kuerp_stores", $kuerp_stores);
        $this->view->assign("goods_code", $goods_code);

        return $this->view->fetch();
    }

    public function edit($ids = null,$stores_id='',$goods_code='')
    {
        $old_row = $row = Db::name('kuerp_inventory')->where(['id'=>$ids])->find();
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

                $code = Db::name('kuerp_warehousing')->where(['order_type'=>'1','order_id'=>$old_row['id']])->column('purchase_code');
                if($code){
                    $this->error('此盘点单已生成盘盈单，不可编辑');
                }
                $code = Db::name('kuerp_warehouse')->where(['order_type'=>'1','order_id'=>$old_row['id']])->column('purchase_code');
                if($code){
                    $this->error('此盘点单已生成盘亏单，不可编辑');
                }

                $data = [];
                $invertory = 0;$profit = 0;
                foreach ($params['goods_id'] as $k=>$v){
                    if(!empty($v)){//有产品
                        $data[] = \addons\kuerp\library\Goods::setDataInfo([
                            'purchase_code'     =>  $old_row['purchase_code'],
                            'purchase_time'     =>  $old_row['purchase_time'],

                            'goods_type'        =>  empty($params['goods_type'][$k])?null:$params['goods_type'][$k],//商品类别
                            'goods_id'          =>  empty($params['goods_id'][$k])?null:$params['goods_id'][$k],
                            'stores_id'         =>  empty($params['stores_id'][$k])?null:$params['stores_id'][$k],
                            'goods_attr'        =>  empty($params['goods_attr'][$k])?null:$params['goods_attr'][$k],
                            'stock'             =>  empty($params['stock'][$k])?null:$params['stock'][$k],
                            'invertory_stock'   =>  empty($params['invertory_stock'][$k])?null:$params['invertory_stock'][$k],
                            'profit'            =>  empty($params['profit'][$k])?null:$params['profit'][$k],

                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ]);
                        if($params['profit'][$k]>0){
                            $invertory += $params['profit'][$k];
                        }else if($params['profit'][$k]<0){
                            $profit += $params['profit'][$k];
                        }
                    }
                }
                if(!empty($data)){
                    $list = Db::name('kuerp_goods_stock')->alias('gs')
                        ->join('kuerp_goods g','g.id=gs.goods_id')
                        ->where(['g.serial'=>['<>','1']])
                        ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
                        ->order('gs.goods_code ASC')
                        ->select();
                    if(sizeof($list)==sizeof($data)){
                        $store_type = 1;
                    }else{
                        $store_type = 2;
                    }
                    $result = '';
                    if($invertory>0){
                        $result .= ' 盘盈：'.$invertory.';';
                    }
                    if($profit<0){
                        $result .= ' 盘亏：'.$profit.';';
                    }
                    $row = \addons\kuerp\library\Goods::setDataInfo([
                        'store_type'        => $store_type,
                        'result'            => $result,
                        'updatetime'        => time(),
                    ]);
                }

                $result = false;
                Db::startTrans();
                try {
                    if(!empty($row)){
                        $result = $this->model->where(['id'=>$ids])->update($row);
                    }
                    Db::name('kuerp_inventory_info')->where(['purchase_code'=>$old_row['purchase_code']])->delete();
                    if(!empty($data)){
                        Db::name('kuerp_inventory_info')->insertAll($data);
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
        $where = [];
        if(!empty($stores_id)){
            $where['stores_id'] = $stores_id;
        }
        if(!empty($goods_code)){
            $goods = Db::name('kuerp_goods')->where(['goods_code'=>['like','%'.$goods_code.'%']])->column('id');
            $where['goods_id'] = ['in',$goods];
        }
        $list = Db::name('kuerp_inventory_info')->where($where)->where(['purchase_code'=>$row['purchase_code']])->select();
        foreach ($list as $list_k=>$list_v){
            $goods = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])->field('id,goods_name,goods_code,note,goods_type_id,goods_specification_id,unit_id')->find();
            $goods_type = Db::name('kuerp_goods_type')->where(['id'=>$goods['goods_type_id']])->field('id,supplier_type_name')->find();
            $stores = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('id,name')->find();
            $list[$list_k]['goods'] = $goods;
            $list[$list_k]['goods_type'] = $goods_type;
            $list[$list_k]['stores'] = $stores;

            $attribute_id = Db::name('kuerp_goods_attr')->where(['id'=>$list_v['goods_attr']])->value('attribute_id');
            $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$attribute_id])->value('attr_ids');
            if($attribute_ids){
                $attribute_ids = explode('&',$attribute_ids);
                $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                $attribute_name = implode('/',$attribute);
                $list[$list_k]['attr_name'] = $attribute_name;
            }else{
                $list[$list_k]['attr_name'] = '';
            }

            $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_code'=>$goods['goods_code'],'attribute_id'=>$list_v['goods_attr']])->value('id');
            $list[$list_k]['attr_id'] = $goods_attr;

            $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->value('name');
            $list[$list_k]['unit_name'] = $unit;
        }
        $row['info'] = $list;
        $this->view->assign("row", $row);

        $kuerp_stores = Db::name('kuerp_stores')->where(['id'=>$stores_id])->field('id,name')->find();
        $this->assignconfig("kuerp_stores", $kuerp_stores);
        $this->view->assign("goods_code", $goods_code);
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
                    Db::name('kuerp_inventory_info')->where(['purchase_code'=>$v['purchase_code']])->delete();
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

    public function export(){

        $list = Db::name('kuerp_goods_stock')->alias('gs')
            ->join('kuerp_goods g','g.id=gs.goods_id')
            ->where(['g.serial'=>['<>','1']])
            ->group('gs.goods_code,gs.attribute_id,gs.stores_id')
            ->field('sum(gs.stock) as stock,sum(gs.cost_money) as cost_money,gs.goods_code,gs.attribute_id,gs.stores_id')
            ->order('gs.goods_code ASC')
            ->select();

        foreach ($list as $list_k=>$list_v){
            $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('id,goods_barcode,goods_name,goods_code,note,goods_type_id,goods_specification_id,unit_id')->find();
            $goods_type = Db::name('kuerp_goods_type')->where(['id'=>$goods['goods_type_id']])->field('id,supplier_type_name')->find();
            $stores = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('id,name')->find();
            $list[$list_k]['goods'] = $goods;
            $list[$list_k]['goods_type'] = $goods_type;
            $list[$list_k]['stores'] = $stores;

            $attribute_ids = Db::name('kuerp_aux_combination')->where(['id'=>$list_v['attribute_id']])->value('attr_ids');
            if($attribute_ids){
                $attribute_ids = explode('&',$attribute_ids);
                $attribute = Db::name('kuerp_auxiliary')->where(['id'=>['in',$attribute_ids]])->column('name');
                $attribute_name = implode('/',$attribute);
                $list[$list_k]['attr_name'] = $attribute_name;
            }else{
                $list[$list_k]['attr_name'] = '';
            }

            $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_code'=>$list_v['goods_code'],'attribute_id'=>$list_v['attribute_id']])->value('attr_code');
            $list[$list_k]['attr_code'] = $goods_attr;

            $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->value('name');
            $list[$list_k]['unit_name'] = $unit;
        }

        $lists = [];
        foreach ($list as $list_k=>$list_v){
            $lists[] = [
                'stores_name' => $list_v['stores']['name'],
                'type_name' => $list_v['goods_type']['supplier_type_name'],
                'goods_code' => $list_v['goods']['goods_code'],
                'goods_name' => $list_v['goods']['goods_name'],
                'attr_code' => $list_v['attr_code'],
                'attr_name' => $list_v['attr_name'],
                'goods_spec' => $list_v['goods']['goods_specification_id'],
                'unit_name' => $list_v['unit_name'],
                'stock' => $list_v['stock'],
                'pstock' => '',
                'goods_barcode' => $list_v['goods']['goods_barcode'],
            ];
        }

        $spreadsheet = new Spreadsheet();
        // 获取活动工作薄
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("盘点");
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(35);

        $sheet->getCell('A1')->setValue('仓库');
        $sheet->getCell('B1')->setValue('商品类别');
        $sheet->getCell('C1')->setValue('商品编号');
        $sheet->getCell('D1')->setValue('商品名称');
        $sheet->getCell('E1')->setValue('属性编号');
        $sheet->getCell('F1')->setValue('属性名称');
        $sheet->getCell('G1')->setValue('规格型号');
        $sheet->getCell('H1')->setValue('单位');
        $sheet->getCell('I1')->setValue('系统库存');
        $sheet->getCell('J1')->setValue('盘点库存');
        $sheet->getCell('K1')->setValue('商品条码');

        foreach ($lists as $k => $val) {
            $k = $k + 2;
            $sheet->getCell('A' . $k)->setValue($val['stores_name']);
            $sheet->getCell('B' . $k)->setValue($val['type_name']);
            $sheet->getCell('C' . $k)->setValue($val['goods_code']);
            $sheet->getCell('D' . $k)->setValue($val['goods_name']);
            $sheet->getCell('E' . $k)->setValue($val['attr_code']);
            $sheet->getCell('F' . $k)->setValue($val['attr_name']);
            $sheet->getCell('G' . $k)->setValue($val['goods_spec']);
            $sheet->getCell('H' . $k)->setValue($val['unit_name']);
            $sheet->getCell('I' . $k)->setValue($val['stock']);
            $sheet->getCell('J' . $k)->setValue($val['pstock']);
            $sheet->getCell('K' . $k)->setValue($val['goods_barcode']);
        }
        $title = "盘点" . date("YmdHis");
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $title . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spreadsheet,'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
