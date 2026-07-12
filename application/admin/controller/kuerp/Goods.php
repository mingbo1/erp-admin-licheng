<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use function GuzzleHttp\Psr7\str;
use think\Db;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use think\Loader;
use fast\Tree;
/**
 * 商品管理
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{
    protected $noNeedRight = ['chackstatus','auxiliary','period','chosegoods','gettype','getunit','getsaleunit',
        'getserial','getmenu','getsuppliers','chosesale','getgoods','getsetdata'];
    /**
     * Goods模型对象
     * @var \app\admin\model\kuerp\Goods
     */

    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Goods;
        $this->view->assign("moreUnitList", $this->model->getMoreUnitList());
        $this->view->assign("isOfftenList", $this->model->getIsOfftenList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isShowList", $this->model->getIsShowList());
        $this->view->assign("productTypeList", $this->model->getProductTypeList());
        $stores = Db::name('kuerp_stores')->where(['status'=>1])->select();
        $this->view->assign("stores", $stores);
        $this->assignconfig("stores", json_encode($stores));

        $categorylists = collection(model('\app\admin\model\kuerp\GoodsType')->where(['status'=>'1'])->field('id,pid,supplier_type_name as name')->select())->toArray();
        Tree::instance()->init($categorylists);
        $categorylists = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'name');
        $categorylist = array('' => '==请选择==');
        foreach ($categorylists as $k => $v) {
            $categorylist[$v['id']] = $v['name'];
        }
        $this->view->assign('categorylist', $categorylist);

        $locationlists = collection(model('\app\admin\model\kuerp\GoodsLocation')->where(['status'=>'1'])->field('id,pid,location_name as name')->select())->toArray();
        Tree::instance()->init($locationlists);
        $locationlists = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'name');
        $locationlist = array('' => '==请选择==');
        foreach ($locationlists as $k => $v) {
            $locationlist[$v['id']] = $v['name'];
        }
        $this->view->assign('locationlist', $locationlist);

        //商品属性数据
        $auxiliary = Db::name('kuerp_auxiliary')->where(['status'=>1])->column('name','id');
        $this->assignconfig("auxiliary", json_encode($auxiliary));
        $auxiliary = Db::name('kuerp_auxiliary')->where(['status'=>1])->field('id,pid,name')->select();
        $auxiliary = \addons\kuerp\library\Goods::array2tree($auxiliary);
        $this->view->assign("auxiliary", $auxiliary);

        //多单位列表数据
        $list = Db::name('kuerp_unit')->where(['type'=>'2','is_main'=>'1'])->select();
        foreach ($list as $k=>$row) {
            $unit = Db::name('kuerp_unit_conversion')->alias('uc')
                ->join('kuerp_unit u','u.id=uc.vice_unit')
                ->where(['uc.main_unit'=>$row['id']])
                ->order('uc.advance_rate','ASC')
                ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                ->select();
            $row['advance_rate'] = '1';
            $row['unit_list'] = $unit;
            $row['unit_name'] = $row['name'];
            foreach ($unit as $unit_k=>$unit_v){
                $row['name'] .= ','.$unit_v['name'];
                $row['advance_rate'] .= ':'.$unit_v['advance_rate'];
            }
            $list[$k] = $row;
        }
        $this->view->assign("unit_conversion", $list);
        $this->assignconfig("unit_conversion", json_encode($list));
    }

    public function import()
    {
        if ($this->request->isPost()) {
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
            $database = Config::get('database.database');
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

                $titles = [];
                for ($currentRow = 1; $currentRow <= 1; $currentRow++) {
                    for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                        $val = (string)$currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                        $titles[] = $val;
                    }
                }


                $fields = [];
                for ($currentRow = 2; $currentRow <= 2; $currentRow++) {
                    for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                        $val = (string)$currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                        $val = str_replace('*','',$val);
                        if(!empty($val)){
                            $fields[] = $val;
                        }
                    }
                }

                foreach ($fields as $fields_k=>$fields_v){
                    if($fields_v=='期初数量'){
                        $fields[$fields_k] = '期初数量-'.(isset($titles[$fields_k])?$titles[$fields_k]:'');
                    }
                    if($fields_v=='单位成本'){
                        $fields[$fields_k] = '单位成本-'.(isset($titles[$fields_k-1])?$titles[$fields_k-1]:'');
                    }
                }

                for ($currentRow = 3; $currentRow <= $allRow; $currentRow++) {
                    $values = [];
                    for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                        $val = (string)$currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                        $values[] = is_null($val) ? '' : trim($val);
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

            //分类数据
            $cate = [];
            $catedata = [];
            //计量单位
            $unit = [];$unit_info = [];
            $unit_con = [];
            //商品编号
            $goods_code = [];
            //仓库
            $stores = [];
            $attr = [];
            $row = $insert[0];
            //属性
            $sku = !empty($_POST['skulist'])?$_POST['skulist']:[];
            if(!empty($sku)){
                $auxiliary = Db::name('kuerp_auxiliary')->where(['id'=>['in',$sku]])->column('id','name');
                if(sizeof($auxiliary) != sizeof($sku)){
                    $this->error('商品属性查询错误，导入失败');
                }
                foreach ($auxiliary as $auxiliary_k=>$auxiliary_v){
                    if(isset($row[$auxiliary_k])){
                        $attr[] = $auxiliary_k;
                    }else{
                        $this->error('请输入属性‘'.$auxiliary_k.'’信息');
                    }
                }
            }
            if(!empty($attr)){
                $auxiliary = Db::name('kuerp_auxiliary')->where(['name'=>['in',$attr]])->column('id','name');
                if(sizeof($attr)!=sizeof($auxiliary)){
                    foreach ($attr as $attr_k=>$attr_v){
                        if(!isset($auxiliary[$attr_v])){
                            $this->error('辅助属性'.$attr_v.'不存在');
                        }
                    }
                }
                $attr_arr = [];
                foreach ($attr as $attr_k=>$attr_v){
                    foreach ($insert as $insert_k=>$insert_v){
                        if(!empty($insert_v[$attr_v]) && !in_array($insert_v[$attr_v],$attr_arr)){
                            $attr_arr[] = $insert_v[$attr_v];
                        }
                        if(!isset($insert[$insert_k]['auxiliary_type'])){
                            $insert[$insert_k]['auxiliary_type'] = [];
                        }
                        if(!empty($insert_v[$attr_v]) && !in_array($insert_v[$attr_v],$insert[$insert_k]['auxiliary_type'])){
                            $insert[$insert_k]['auxiliary_type'][] = $auxiliary[$attr_v];
                        }
                        $insert[$insert_k]['attr_arr'] = [];
                    }
                }
                if(!empty($attr_arr)){
                    $auxiliary = Db::name('kuerp_auxiliary')->where(['name'=>['in',$attr_arr]])->column('id','name');
                    if(sizeof($attr_arr)!=sizeof($auxiliary)){
                        foreach ($attr_arr as $attr_arr_k=>$attr_arr_v){
                            if(!isset($auxiliary[$attr_arr_v])){
                                $this->error('属性'.$attr_arr_v.'不存在');
                            }
                        }
                    }
                    foreach ($attr as $attr_k=>$attr_v){
                        foreach ($insert as $insert_k=>$insert_v){
                            if(!empty($insert_v[$attr_v])){
                                $insert[$insert_k]['attr_arr'][] = $auxiliary[$insert_v[$attr_v]];
                            }
                        }
                    }
                }
            }
            foreach ($insert as $insert_k=>$insert_v){
                if(!empty($insert_v['auxiliary_type'])){
                    $insert[$insert_k]['auxiliary_type'] = serialize($insert_v['auxiliary_type']);
                    $insert[$insert_k]['auxiliary_type_c'] = serialize($insert_v['attr_arr']);
                    $insert[$insert_k]['auxiliary'] = 1;
                }else{
                    $insert[$insert_k]['auxiliary_type'] = '';
                    $insert[$insert_k]['auxiliary_type_c'] = '';
                    $insert[$insert_k]['auxiliary'] = 0;
                }
                if(isset($insert_v['attr_arr']) && !empty($insert_v['attr_arr'])){
                    $attr_arr = $insert_v['attr_arr'];
                    asort($attr_arr);
                    $attr_arr = implode('&',$attr_arr);
                    $attr_id = Db::name('kuerp_aux_combination')->where(['attr_ids'=>$attr_arr,'attr_code'=>$insert_v['属性编号']])->value('id');
                    if($attr_id){
                        $insert[$insert_k]['attr_arr'] = $attr_id;
                    }else{
                        $insert[$insert_k]['attr_arr'] = Db::name('kuerp_aux_combination')->insertGetId(\addons\kuerp\library\Goods::setDataInfo([
                            'attr_ids'=>$attr_arr,
                            'attr_code'=>$insert_v['属性编号'],
                            'createtime'=>time(),
                            'updatetime'=>time(),
                        ]));
                    }
                }else{
                    $insert[$insert_k]['attr_arr'] = '';
                }
            }

            foreach ($insert as $insert_k=>$insert_v){
                //商品编号
                if(!empty($insert_v['商品编号'])){
                    if(!in_array($insert_v['商品编号'],$goods_code)){
                        $goods_code[] = $insert_v['商品编号'];
                    }
                }
                if(!empty($insert_v['首选仓库'])){
                    if(!in_array($insert_v['首选仓库'],$stores)){
                        $stores[] = $insert_v['首选仓库'];
                    }
                }
                //分类数据
                if(empty($insert_v['商品编号']) && empty($insert_v['商品/属性条码'])){
                    unset($insert[$insert_k]);
                }else{
                    if(!empty($insert_v['商品类别'])){
                        $nowcate = trim($insert_v['商品类别']);
                        $catedata[] = $nowcate;
                        if(strpos($nowcate,'/') !== false){
                            $caterow = explode('/',$nowcate);
                            foreach ($caterow as $caterow_k=>$caterow_v){
                                if(!in_array($caterow_v,$cate) && !empty($caterow_v)){
                                    $cate[] = $caterow_v;
                                }
                            }
                        }else{
                            if(!in_array($nowcate,$cate) && !empty($nowcate)){
                                $cate[] = $nowcate;
                            }
                        }
                    }
                }
                //计量单位
                if(!empty($insert_v['计量单位'])){
                    $nowunit = trim($insert_v['计量单位']);
                    if(strpos($nowunit,'/') !== false){
                        if(empty($insert_v['换算率'])){
                            $this->error('多计量单位的换算率必填');
                        }
                        $unitrow = explode('/',$nowunit);
                        $advance_rate = explode('/',trim($insert_v['换算率']));
                        if(sizeof($unitrow)!=sizeof($advance_rate)){
                            $this->error('多计量单位的换算率与计量单位不匹配');
                        }
                        $unit_con[] = [
                            'unit'=>$nowunit,
                            'advance_rate'=>trim($insert_v['换算率']),
                        ];
                        $unit_conversion = true;
                        $unit_data = [];
                        foreach ($unitrow as $unitrow_k=>$unitrow_v){
                            if($unitrow_k>0){
                                $unit_name = $unitrow[0].'/'.$unitrow_v;
                                $con = Db::name('kuerp_unit_conversion')
                                    ->where(['unit_name'=>$unit_name,'advance_rate'=>$advance_rate[$unitrow_k]])->find();
                                if(!$con){
                                    $unit_conversion = false;
                                }else{
                                    if($unitrow_k==1){
                                        $unit_data[$unitrow[0]] = $con['main_unit'];
                                    }
                                    $unit_data[$unitrow_v] = $con['vice_unit'];
                                }
                            }
                        }
                        if($unit_conversion){
                            $unit_name = $unitrow[0].'/'.$unitrow[1];
                            $unit_id = Db::name('kuerp_unit_conversion')
                                ->where(['unit_name'=>$unit_name,'advance_rate'=>$advance_rate[1]])->value('id');
                            $unit_info[$nowunit] = $unit_id;
                        }else{
                            $main_unit = 0;$vice_unit=0;$unit_data=[];
                            foreach ($unitrow as $unitrow_k=>$unitrow_v){
                                if($unitrow_k<1){
                                    $unit_row = ['name'=>$unitrow_v,'type'=>2,'is_main'=>1,'createtime'=>time(),'updatetime'=>time()];
                                }else{
                                    $unit_row = ['name'=>$unitrow_v,'type'=>2,'is_main'=>0,'createtime'=>time(),'updatetime'=>time()];
                                }
                                $unit_id = Db::name('kuerp_unit')->insertGetId($unit_row);
                                $unit_data[$unitrow_v] = $unit_id;
                                if($unitrow_k<1){
                                    $main_unit = $unit_id;
                                }else{
                                    $unit_name = $unitrow[0].'/'.$unitrow_v;
                                    $unit_conversion_data = [
                                        'unit_name' => $unit_name,
                                        'main_unit' => $main_unit,
                                        'vice_unit' => $unit_id,
                                        'advance_rate' => $advance_rate[$unitrow_k],
                                        'createtime' => time(),
                                        'updatetime' => time(),
                                    ];
                                    $unit_conversion_id = Db::name('kuerp_unit_conversion')->insertGetId($unit_conversion_data);
                                    if($unitrow_k==1){
                                        $unit_info[$nowunit] = $unit_conversion_id;
                                    }
                                }
                            }
                        }
                        $insert[$insert_k]['unit_info'] = $unit_data;
                    }else{
                        $unit_id = Db::name('kuerp_unit')->where(['name'=>$nowunit,'type'=>'1'])->value('id');
                        if($unit_id){
                            $unit_info[$nowunit] = $unit_id;
                        }else{
                            $unitdata = [
                                'name'=>$nowunit,
                                'createtime'=>time(),
                                'updatetime'=>time(),
                            ];
                            $unit_id = Db::name('kuerp_unit')->insertGetId($unitdata);
                            $unit_info[$nowunit] = $unit_id;
                        }
                        $insert[$insert_k]['unit_info'][$nowunit] = $unit_id;
                    }
                }
            }
            $catelist = Db::name('kuerp_goods_type')->where(['supplier_type_name'=>['in',$cate]])->column('supplier_type_name');
            $catearr = [];
            foreach ($cate as $cate_k=>$cate_v){
                if(!in_array($cate_v,$catelist)){
                    $catearr[] = $cate_v;
                }
            }
            //添加分类，如不填则返回错误
            if(!empty($catearr)){
                //$this->error('分类不存在');
                foreach ($catedata as $cate_k=>$cate_v){
                    if(strpos($cate_v,'/') !== false){
                        $caterow = explode('/',$cate_v);
                        foreach ($caterow as $caterow_k=>$caterow_v){
                            if(in_array($caterow_v,$catearr) && !empty($caterow_v)){
                                \addons\kuerp\library\Goods::addGoodsCate($caterow);
                                break;
                            }
                        }
                    }else{
                        if(in_array($cate_v,$catearr) && !empty($cate_v)){
                            \addons\kuerp\library\Goods::addGoodsCate([$cate_v]);
                        }
                    }
                }
            }
            $catelist = Db::name('kuerp_goods_type')->where(['supplier_type_name'=>['in',$cate]])->column('id','supplier_type_name');

            //仓库  不能新增，缺少仓库抛出
            $titles = array_filter($titles);
            foreach ($titles as $titles_k=>$titles_v){
                if(!in_array($titles_v,$stores) && $titles_v!='基础信息' && $titles_v!='价格管理'){
                    $stores[] = $titles_v;
                }
            }
            $storesarr = Db::name('kuerp_stores')->where(['name'=>['in',$stores]])->column('name');
            if(sizeof($storesarr)!=sizeof($stores)){
                foreach ($stores as $stores_k=>$stores_v){
                    if(!in_array($stores_v,$storesarr)){
                        $this->error($stores_v.'仓库不存在');
                    }
                }
            }
            $stores_list = Db::name('kuerp_stores')->where(['name'=>['in',$stores]])->column('id','name');

            $goodscode = Db::name('kuerp_goods')->where(['goods_code'=>['in',$goods_code]])->column('goods_code');
            $goodslist = Db::name('kuerp_goods')->where(['goods_code'=>['in',$goods_code]])->column('id','goods_code');//编号相同商品

            $params = [];
            $nowsp = '';
            $supplyer_code = [];
            foreach ($insert as $insert_k=>$insert_v){
                if(!empty($insert_v['商品编号'])){

                    if(!isset($insert_v['预计采购价']) || empty($insert_v['预计采购价'])){
                        $this->error('请输入商品预计采购价');
                    }
                    if(!isset($insert_v['零售价']) || empty($insert_v['零售价'])){
                        $this->error('请输入商品零售价');
                    }
                    if(!isset($insert_v['计量单位']) || empty($insert_v['计量单位'])){
                        $this->error('请输入商品计量单位');
                    }

                    $this->chackstatus($insert_v['商品编号']);
                    $nowsp = $insert_k;
                    if(in_array($insert_v['商品编号'],$supplyer_code)){
                        $k = array_search($insert_v['商品编号'],$supplyer_code);
                        unset($params[$k]);
                    }
                    $supplyer_code[] = $insert_v['商品编号'];
                    $nowcate = explode('/',$insert_v['商品类别']);

                    $goods_price = [];

                    $unit = explode('/',$insert_v['计量单位']);
                    $market_sell_price = explode('/',$insert_v['零售价']);
                    $wholesale_price = explode('/',$insert_v['批发价']);
                    $vip_price = explode('/',$insert_v['会员价']);
                    $discount = explode('/',$insert_v['折扣率一']);
                    $discount2 = explode('/',$insert_v['折扣率二']);
                    $purchase = explode('/',$insert_v['预计采购价']);

                    foreach ($unit as $unit_k=>$unit_v){
                        $goods_price[] = [
                            'goods_code'        => $insert_v['商品编号'],
                            'goods_name'        => $insert_v['商品名称'],
                            'unit'              => $insert_v['unit_info'][$unit_v],
                            'market_sell_price' => isset($market_sell_price[$unit_k])?((float)$market_sell_price[$unit_k]):0,
                            'wholesale_price'   => isset($wholesale_price[$unit_k])?((float)$wholesale_price[$unit_k]):0,
                            'vip_price'         => isset($vip_price[$unit_k])?((float)$vip_price[$unit_k]):0,
                            'discount'          => isset($discount[$unit_k])?((float)$discount[$unit_k]):0,
                            'discount2'         => isset($discount2[$unit_k])?((float)$discount2[$unit_k]):0,
                            'purchase'          => isset($purchase[$unit_k])?((float)$purchase[$unit_k]):0,
                            'is_default'        => $unit_k<1?1:0,
                            'createtime'        => time(),
                            'updatetime'        => time(),
                        ];
                    }

                    $inventory = 0;//总库存
                    $price = 0;
                    $stores_data = [];
                    foreach ($insert_v as $insert_v_k=>$insert_v_v){
                        if(strpos($insert_v_k,'期初数量') !== false){
                            $stores_name = explode('-',$insert_v_k);
                            $insert_v_v = (float)$insert_v_v;
                            $insert_v['单位成本-'.$stores_name[1]] = (float)$insert_v['单位成本-'.$stores_name[1]];
                            if($insert_v_v>0){
                                $params[$insert_k]['period_status'] = 1;
                                $stores_data[] = [
                                    'goods_code'   => $insert_v['商品编号'],
                                    'attribute_id' => (!empty($insert_v['attr_arr'])?$insert_v['attr_arr']:null),
                                    'stores_id' => $stores_list[$stores_name[1]],
                                    'stock' => $insert_v_v,
                                    'period' => $insert_v_v,
                                    'unit_cost' => $insert_v['单位成本-'.$stores_name[1]],
                                    'price' => round(($insert_v_v*$insert_v['单位成本-'.$stores_name[1]]),2),
                                    'createtime' => time(),
                                    'updatetime' => time(),
                                ];

                                $inventory += $insert_v_v;
                                $price += round(($insert_v_v*$insert_v['单位成本-'.$stores_name[1]]),2);
                            }
                        }
                    }
                    $unit_cost = $inventory>0?round($price/$inventory,2):0;

                    $params[$insert_k] = [
                        'auxiliary'             => $insert_v['auxiliary'],
                        'auxiliary_type'        => $insert_v['auxiliary_type'],
                        'auxiliary_type_c'      => $insert_v['auxiliary_type_c'],
                        'goods_barcode'         => $insert_v['商品/属性条码'],

                        'goods_code'            => $insert_v['商品编号'],
                        'goods_name'            => $insert_v['商品名称'],
                        'goods_type_id'         => isset($catelist[$nowcate[sizeof($nowcate)-1]])?$catelist[$nowcate[sizeof($nowcate)-1]]:0,
                        'goods_specification_id'=> $insert_v['规格型号'],
                        'manager_warehouse_id'  => isset($stores_list[$insert_v['首选仓库']])?$stores_list[$insert_v['首选仓库']]:0,
                        'unit_id'               => strpos($insert_v['计量单位'],'/') !== false?$insert_v['unit_info'][$unit[0]]:$insert_v['unit_info'][$insert_v['计量单位']],
                        'note'                  => $insert_v['备注'],
                        'attribute_id'          => !empty($insert_v['attr_arr'])?$insert_v['attr_arr']:null,

                        'purchase'              => $goods_price[0]['purchase'],
                        'market_sell_price'     => $goods_price[0]['market_sell_price'],
                        'wholesale_price'       => $goods_price[0]['wholesale_price'],
                        'vip_price'             => $goods_price[0]['vip_price'],
                        'discount'              => $goods_price[0]['discount'],
                        'discount2'             => $goods_price[0]['discount2'],

                        'inventory'             => $inventory,
                        'period'                => $inventory,
                        'unit_cost'             => $unit_cost,
                        'price'                 => $price,

                        'createtime'            => time(),
                        'updatetime'            => time(),
                        'goods_price'          =>$goods_price,
                        'stores_data'          =>$stores_data
                    ];
                    foreach ($insert_v as $insert_v_k=>$insert_v_v){
                        if(strpos($insert_v_k,'期初数量') !== false && !empty($insert_v_v)){
                            $params[$insert_k]['period_status'] = 1;
                        }
                    }
                    if(strpos($insert_v['计量单位'],'/') !== false){
                        $params[$insert_k]['more_unit'] = 1;
                        $params[$insert_k]['more_unit_id'] = $insert_v['unit_info'][$unit[0]];
                    }else{
                        $params[$insert_k]['more_unit'] = 2;
                        $params[$insert_k]['more_unit_id'] = 0;
                    }

                    $row = $params[$nowsp]['goods_price'][0];
                    $row['attribute_id'] = $params[$nowsp]['attribute_id'];
                    unset($row['unit_id']);unset($row['is_default']);
                    $params[$nowsp]['attr_price'] = [];
                    $row['attr_code']         = isset($insert_v['属性编号'])?$insert_v['属性编号']:null;
                    $params[$nowsp]['attr_price'][] = $row;
                }else{
                    if(isset($params[$nowsp]['goods_price']) && !empty($insert_v['商品/属性条码'])){

                        if(!empty($insert_v['auxiliary_type_c'])){
                            $s = unserialize($insert_v['auxiliary_type_c']);
                            $auxiliary_type_c = unserialize($params[$nowsp]['auxiliary_type_c']);
                            foreach ($s as $k=>$v){
                                if(!empty($v) && !in_array($v,$auxiliary_type_c)){
                                    $auxiliary_type_c[] = $v;
                                    $params[$nowsp]['auxiliary_type_c'] = serialize($auxiliary_type_c);
                                }
                            }
                        }

                        if(strpos($insert[$nowsp]['计量单位'],'/') !== false){
                            $unit = explode('/',$insert[$nowsp]['计量单位']);
                            $market_sell_price = explode('/',$insert[$nowsp]['零售价']);
                            $wholesale_price = explode('/',$insert[$nowsp]['批发价']);
                            $vip_price = explode('/',$insert[$nowsp]['会员价']);
                            $discount = explode('/',$insert[$nowsp]['折扣率一']);
                            $discount2 = explode('/',$insert[$nowsp]['折扣率二']);
                            $purchase = explode('/',$insert[$nowsp]['预计采购价']);
                        }else{
                            $unit = explode('/',$insert[$nowsp]['计量单位']);
                            $market_sell_price = explode('/',$insert_v['零售价']);
                            $wholesale_price = explode('/',$insert_v['批发价']);
                            $vip_price = explode('/',$insert_v['会员价']);
                            $discount = explode('/',$insert_v['折扣率一']);
                            $discount2 = explode('/',$insert_v['折扣率二']);
                            $purchase = explode('/',$insert_v['预计采购价']);
                        }

                        $params[$nowsp]['attr_price'][] = [
                            'goods_code'        => $insert[$nowsp]['商品编号'],
                            'goods_name'        => $insert[$nowsp]['商品名称'],
                            'unit'              => (isset($params[$nowsp]['attr_price'][0]['unit'])?$params[$nowsp]['attr_price'][0]['unit']:0),
                            'market_sell_price' => $market_sell_price[0],
                            'wholesale_price'   => $wholesale_price[0],
                            'vip_price'         => $vip_price[0],
                            'discount'          => $discount[0],
                            'discount2'         => $discount2[0],
                            'purchase'          => $purchase[0],
                            'createtime'        => time(),
                            'updatetime'        => time(),
                            'attribute_id'      => !empty($insert_v['attr_arr'])?$insert_v['attr_arr']:null,
                            'attr_code'         => isset($insert_v['属性编号'])?$insert_v['属性编号']:null,
                        ];

                        foreach ($insert_v as $insert_v_k=>$insert_v_v){
                            if(strpos($insert_v_k,'期初数量') !== false){
                                $stores_name = explode('-',$insert_v_k);
                                $insert_v_v = (float)$insert_v_v;
                                $insert_v['单位成本-'.$stores_name[1]] = (float)$insert_v['单位成本-'.$stores_name[1]];
                                if($insert_v_v>0){
                                    $params[$nowsp]['period_status'] = 1;
                                    $params[$nowsp]['stores_data'][] = [
                                        'goods_code'        => $insert[$nowsp]['商品编号'],
                                        'attribute_id' => !empty($insert_v['attr_arr'])?$insert_v['attr_arr']:null,
                                        'stores_id' => $stores_list[$stores_name[1]],
                                        'stock' => $insert_v_v,
                                        'period' => $insert_v_v,
                                        'unit_cost' => $insert_v['单位成本-'.$stores_name[1]],
                                        'price' => round(($insert_v_v*$insert_v['单位成本-'.$stores_name[1]]),2),
                                        'createtime' => time(),
                                        'updatetime' => time(),
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            $editstatus = isset($_POST['editstatus'])?$_POST['editstatus']:1;
            $update = [];
            $add_code = [];
            foreach ($params as $params_k=>$params_v){
                $params_v['serial'] = 0;$params[$params_k]['serial'] = 0;
                $inventory = 0;//总库存
                $price = 0;
                foreach ($params_v['stores_data'] as $k=>$v){
                    $inventory += $v['period'];
                    $price += $v['price'];
                }
                $unit_cost = $inventory>0?round($price/$inventory,2):0;
                $params[$params_k]['inventory'] = $inventory;
                $params[$params_k]['period'] = $inventory;
                $params[$params_k]['unit_cost'] = $unit_cost;
                $params[$params_k]['price'] = $price;
                //如果更新
                if($editstatus==1){
                    if(in_array($params_v['goods_code'],$goodscode)){
                        $params_v['id'] = $goodslist[$params_v['goods_code']];
                        $update[] = $params_v;
                        unset($params[$params_k]);
                    }else{
                        $add_code[] = $params_v['goods_code'];
                    }
                }else{
                    $add_code[] = $params_v['goods_code'];
                }
            }

            Db::startTrans();
            try {
                Db::name('kuerp_goods_attr')->where(['goods_code'=>['in',$goods_code]])->delete();
                Db::name('kuerp_goods_price')->where(['goods_code'=>['in',$goods_code]])->delete();
                Db::name('kuerp_goods_stock')->where(['goods_code'=>['in',$goods_code]])->delete();
                Db::name('kuerp_goods_period')->where(['goods_code'=>['in',$goods_code]])->delete();

                if(!empty($add_code)){
                    Db::name('kuerp_goods')->where(['goods_code'=>['in',$add_code]])->delete();
                }

                if(!empty($params)){
                    foreach ($params as $params_k=>$params_v){
                        $goods_price = $params_v['goods_price'];unset($params_v['goods_price']);
                        $stores_data = $params_v['stores_data'];unset($params_v['stores_data']);
                        $attr_price  = $params_v['attr_price'];unset($params_v['attr_price']);
                        $result = Db::name('kuerp_goods')->insertGetId($params_v);
                        if(!empty($goods_price)){
                            foreach ($goods_price as $data_k=>$data_v){
                                $goods_price[$data_k]['goods_id'] = $result;
                            }
                            Db::name('kuerp_goods_price')->insertAll($goods_price);
                        }
                        if(!empty($stores_data)){
                            foreach ($stores_data as $data_k=>$data_v){
                                $stores_data[$data_k]['goods_id'] = $result;
                            }
                            Db::name('kuerp_goods_stock')->insertAll($stores_data);
                            Db::name('kuerp_goods_period')->insertAll($stores_data);
                        }
                        if(!empty($attr_price)){
                            foreach ($attr_price as $data_k=>$data_v){
                                $attr_price[$data_k]['goods_id'] = $result;
                            }
                            Db::name('kuerp_goods_attr')->insertAll($attr_price);
                        }
                    }
                }
                if(!empty($update)){
                    foreach ($update as $params_k=>$params_v){
                        $goods_price = $params_v['goods_price'];unset($params_v['goods_price']);unset($update[$params_k]['goods_price']);
                        $stores_data = $params_v['stores_data'];unset($params_v['stores_data']);unset($update[$params_k]['stores_data']);
                        $attr_price  = $params_v['attr_price'];unset($params_v['attr_price']);unset($update[$params_k]['attr_price']);
                        if(!empty($goods_price)){
                            foreach ($goods_price as $data_k=>$data_v){
                                $goods_price[$data_k]['goods_id'] = $params_v['id'];
                            }
                            Db::name('kuerp_goods_price')->insertAll($goods_price);
                        }
                        if(!empty($stores_data)){
                            foreach ($stores_data as $data_k=>$data_v){
                                $stores_data[$data_k]['goods_id'] =$params_v['id'];
                            }
                            Db::name('kuerp_goods_stock')->insertAll($stores_data);
                            Db::name('kuerp_goods_period')->insertAll($stores_data);
                        }
                        if(!empty($attr_price)){
                            foreach ($attr_price as $data_k=>$data_v){
                                $attr_price[$data_k]['goods_id'] = $params_v['id'];
                            }
                            Db::name('kuerp_goods_attr')->insertAll($attr_price);
                        }
                    }
                    if($editstatus==1 && !empty($update)){
                        $this->model->saveAll($update);
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success(['update'=>sizeof($update),'add'=>sizeof($params)]);
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
            $data = input();$wheres = [];
            if(isset($data['keywords'])){
                $wheres['goods.status'] = '1';
            }
            $list = $this->model
                ->with(['goodstype','goodslocation'])
                ->where($where)->where($wheres)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $list_k=>$list_v){
                $list_v->goods_image = $list_v->goods_image ? cdnurl($list_v->goods_image, true) : '';
                $unit = Db::name('kuerp_unit')->where(['id'=>$list_v['unit_id']])->field('id,name')->find();
                $list[$list_k]['unit'] = $unit;
                if($unit){
                    $unit_name = $unit['name'];
                    $rowadvance_rate = '1';
                    if($list_v['more_unit']==1){
                        $more_unit = Db::name('kuerp_unit_conversion')->alias('uc')
                            ->join('kuerp_unit u','u.id=uc.vice_unit')
                            ->where(['uc.main_unit'=>$unit['id']])
                            ->order('uc.advance_rate','ASC')
                            ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                            ->select();
                        foreach ($more_unit as $unit_k=>$unit_v){
                            $unit_name .= ','.$unit_v['name'];
                            $rowadvance_rate .= ':'.$unit_v['advance_rate'];
                        }
                        $list[$list_k]['unitconversion'] = $unit_name.'('.$rowadvance_rate.')';
                    }else{
                        $list[$list_k]['unitconversion'] = '';
                    }
                }
                if(empty($unit)){
                    $list[$list_k]['unit'] = ['name'=>''];
                }
            }
            foreach ($list as $row) {
                $row->getRelation('goodstype')->visible(['supplier_type_name']);
                $row->getRelation('goodslocation')->visible(['location_name']);
            }
            $number_decimal = Db::name('kuerp_sets')->where(['name'=>'number_decimal'])->value('value');
            $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');
            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['market_sell_price'] = round($list_v['market_sell_price'],$price_decimal);
                $list[$list_k]['wholesale_price'] = round($list_v['wholesale_price'],$price_decimal);
                $list[$list_k]['vip_price'] = round($list_v['vip_price'],$price_decimal);
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
            $branch = $this->request->post("branch/a");
            $stock = $this->request->post("stock/a");
            $more_unit = $this->request->post("more_unit/a");
            $auxiliary = $this->request->post("auxiliary/a");

            if ($params) {
                $params = $this->preExcludeFields($params);

                if(Db::name('kuerp_goods')->where(['goods_code'=>trim($params['goods_code'])])->find()){
                    $this->error('商品编号已占用');
                }
                if(!empty($params['goods_barcode'])){
                    if(Db::name('kuerp_goods')->where(['goods_barcode'=>trim($params['goods_barcode'])])->find()){
                        $this->error('商品条码已占用');
                    }
                }

                $auxiliary_type = isset($params['auxiliary_type'])?$params['auxiliary_type']:[];
                if(isset($params['auxiliary_type']) && !empty($params['auxiliary_type'])){
                    $params['auxiliary_type'] = serialize($params['auxiliary_type']);
                }
                $auxiliary_type_c = isset($params['auxiliary_type_c'])?$params['auxiliary_type_c']:[];
                if(isset($params['auxiliary_type_c']) && !empty($params['auxiliary_type_c'])){
                    $params['auxiliary_type_c'] = serialize($params['auxiliary_type_c']);
                }

                //启用辅助属性
                $aux_combination = [];
                $auxiliary_code = [];
                $set= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                if(isset($params['auxiliary']) && isset($params['auxiliary']) && $params['auxiliary']==1 && $set==1){
                    $status = false;
                    foreach ($auxiliary_type_c as $k=>$v){
                        if(!empty($v)){
                            $status = true;
                        }
                    }
                    if(!$status){
                        $this->error('请选择商品属性');
                    }
                    foreach ($auxiliary['code'] as $k=>$v){
                        if(!empty($v)){
                            $auxiliary_row = [];
                            foreach ($auxiliary_type as $type_k=>$type_v){
                                $auxiliary_row[] = $auxiliary[$type_v][$k];
                            }
                            $auxiliary_row = array_filter($auxiliary_row);
                            if(empty($auxiliary_row)){
                                $this->error('商品属性解析失败，请刷新后重试');
                            }
                            asort($auxiliary_row);
                            $aux_combination[] = ['ids'=>implode('&',$auxiliary_row),'code'=>$v];
                        }
                    }
                }
                $combination_add = [];
                $combination_list = [];$combination_code = [];
                foreach ($aux_combination as $aux_combination_k=>$aux_combination_v){
                    $combination_id = Db::name('kuerp_aux_combination')->where(['attr_ids'=>$aux_combination_v['ids']])->value('id');
                    if(empty($combination_id)){
                        $combination_id = Db::name('kuerp_aux_combination')->insertGetId([
                            'attr_ids'  => $aux_combination_v['ids'],
                            'attr_code' => $aux_combination_v['code'],
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                        $combination_add[] = $combination_id;
                    }
                    $combination_list[$aux_combination_v['ids']] = $combination_id;
                    $combination_code[$aux_combination_v['code']] = $combination_id;
                }
                $goods_attr = [];
                if(isset($params['auxiliary']) && isset($params['auxiliary']) && $params['auxiliary']==1 && $set==1){
                    foreach ($auxiliary['code'] as $k=>$v){
                        if(!empty($v)){
                            $auxiliary_code[] = $v;
                            $unit = (isset($more_unit['id'][0])?$more_unit['id'][0]:$params['unit_id']);
                            $unit = $unit===''?0:$unit;
                            $row = [
                                'goods_code'=>$params['goods_code'],
                                'goods_name'=>$params['goods_name'],
                                'content'=>$params['content'],
                                'goods_barcode'=>$params['goods_barcode'],
                                'goods_id'=>'',
                                'attribute_id'=>$combination_code[$v],
                                'attr_code'=>$v,
                                'barcode'=>$auxiliary['barcode'][$k],
                                'unit'=>$unit,
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                            if($auxiliary['market_sell_price'][$k]!==''){
                                $row['market_sell_price'] = $auxiliary['market_sell_price'][$k];
                            }
                            if($auxiliary['wholesale_price'][$k]!==''){
                                $row['wholesale_price'] = $auxiliary['wholesale_price'][$k];
                            }
                            // if($auxiliary['vip_price'][$k]!==''){
                            //     $row['vip_price'] = $auxiliary['vip_price'][$k];
                            // }
                            // if($auxiliary['discount'][$k]!==''){
                            //     $row['discount'] = $auxiliary['discount'][$k];
                            // }
                            // if($auxiliary['discount2'][$k]!==''){
                            //     $row['discount2'] = $auxiliary['discount2'][$k];
                            // }
                            if($auxiliary['purchase'][$k]!==''){
                                $row['purchase'] = $auxiliary['purchase'][$k];
                            }
                            $goods_attr[] = $row;
                        }
                    }
                    $status = Db::name('kuerp_goods_attr')->where(['attr_code'=>['in',$auxiliary_code]])->find();
                    if($status){
                        $this->error('商品属性编号已占用');
                    }
                }else{
                    $params['auxiliary'] = 0;
                }

                //启用批次保质期管理
                $goods_batch = [];
                $set= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                if(isset($params['batch']) && isset($params['batch']) && $params['batch']==1 && $set==1){
                    foreach ($stock['stores_id'] as $k=>$v){
                        if(!empty($v)){
                            if(empty($stock['batch'][$k])){
                                $this->error('请输入批次');
                            }elseif(empty($stock['manufacture'][$k])){
                                $this->error('请输入生产日期');
                            }elseif ($stock['guarantee'][$k]===''){
                                $this->error('请输入保质期(天)');
                            }elseif ($stock['guarantee'][$k]<0){
                                $this->error('保质期天数不可小于0');
                            }elseif (empty($stock['validity'][$k])){
                                $this->error('请输入有效期至');
                            }
                            if(isset($stock['attr_id'])){
                                if(!empty($stock['attr_id'][$k])){
                                    $this_attr = explode('&',$stock['attr_id'][$k]);
                                    asort($this_attr);
                                    $stock['attr_id'][$k] = implode('&',$this_attr);
                                }
                                $attribute_id = isset($combination_list[$stock['attr_id'][$k]])?$combination_list[$stock['attr_id'][$k]]:0;
                            }else{
                                $attribute_id = 0;
                            }
                            $goods_batch[] = [
                                'goods_id'    => '',
                                'goods_code'  => $params['goods_code'],
                                'attribute_id'=> $attribute_id,
                                'stores_id'   => $v,
                                'stock'       => $stock['period'][$k],
                                'number'      => $stock['period'][$k],
                                'batch_name'  => $stock['batch'][$k],
                                'manufacture' => strtotime($stock['manufacture'][$k]),
                                'guarantee'   => $stock['guarantee'][$k],
                                'validity'    => strtotime($stock['validity'][$k]),
                                'type'        => 1,
                                'unit_cost'      => $stock['unit_cost'][$k],
                                'price'  => $stock['price'][$k],
                                'address'=>$stock['address'][$k],
                                'registration'=>$stock['registration'][$k],
                                'production'=>$stock['production'][$k],

                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                        }
                    }
                }else{
                    $params['batch'] = 0;
                }

                //多单位&价格设置
                $goods_price = [];
                if(isset($params['more_unit']) && isset($params['more_unit']) && $params['more_unit']==1){//开启多单位
                    if(isset($more_unit['more_unit_id']) && !empty($more_unit['more_unit_id'])){
                        $params['more_unit_id'] = $more_unit['more_unit_id'];
                    }else{
                        $this->error('请选择多计量单位');
                    }
                    $params['warehousing'] = $more_unit['warehousing'];
                    $params['warehouse'] = $more_unit['warehouse'];
                    $params['market_sell_price'] = (float)$more_unit['market_sell_price'][0];
                    $params['wholesale_price'] = (float)$more_unit['wholesale_price'][0];
                    $params['vip_price'] = (float)$more_unit['vip_price'][0];
                    $params['discount'] = (float)$more_unit['discount'][0];
                    $params['discount2'] = (float)$more_unit['discount2'][0];
                    $params['purchase'] = (float)$more_unit['purchase'][0];
                    $params['unit_id'] = (float)$more_unit['id'][0];


                    foreach ($more_unit['id'] as $more_unit_k=>$more_unit_v){
                        $goods_price[] = [
                            'goods_code'=>$params['goods_code'],
                            'goods_name'=>$params['goods_name'],
                            'goods_barcode'=>$params['goods_barcode'],
                            'unit'=>$more_unit_v,
                            'market_sell_price'=>(float)$more_unit['market_sell_price'][$more_unit_k],
                            'wholesale_price'=>(float)$more_unit['wholesale_price'][$more_unit_k],
                            'vip_price'=>(float)$more_unit['vip_price'][$more_unit_k],
                            'discount'=>(float)$more_unit['discount'][$more_unit_k],
                            'discount2'=>(float)$more_unit['discount2'][$more_unit_k],
                            'purchase'=>(float)$more_unit['purchase'][$more_unit_k],
                            'is_default'=>($more_unit_k<1?'1':'0'),
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ];
                    }
                }else{
                    $params['more_unit'] = 2;
                }

                //预警设置
                $goods_stock_warning = [];
                if(!empty($branch) && isset($params['branch']) && $params['branch']==1){
                    foreach ($branch['stores'] as $k=>$v){
                        if(!empty($v) && !empty($branch['min_stock'][$k]) && !empty($branch['max_stock'][$k])){
                            $goods_stock_warning[] = [
                                'goods_id'    => '',
                                'goods_code'  => $params['goods_code'],
                                'stores_id'=>$v,
                                'min_stock'=>$branch['min_stock'][$k],
                                'max_stock'=>$branch['max_stock'][$k],
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                        }
                    }
                }else{
                    $params['branch'] = 0;
                }

                $stores = [];
                $goods_stock = [];$goods_stock_log = [];
                if(isset($_POST['setqc']) && $_POST['setqc']==1){//期初设置
                    $params['period_status'] = 1;
                    foreach ($stock['stores_id'] as $stock_k=>$stock_v){
                        if(isset($stock['attr_id'])){
                            if(!empty($stock['attr_id'][$stock_k])){
                                $this_attr = explode('&',$stock['attr_id'][$stock_k]);
                                asort($this_attr);
                                $stock['attr_id'][$stock_k] = implode('&',$this_attr);
                            }
                            $attribute_id = isset($combination_list[$stock['attr_id'][$stock_k]])?$combination_list[$stock['attr_id'][$stock_k]]:0;
                        }else{
                            $attribute_id = 0;
                        }
                        if(!empty($stock_v)){
                            $stores[] = $stock_v;
                            $goods_stock[] = [
                                'goods_code'=>$params['goods_code'],
                                'stores_id'=>$stock_v,
                                'attribute_id'=>$attribute_id,
                                'address'=>$stock['address'][$stock_k],
                                'registration'=>$stock['registration'][$stock_k],
                                'production'=>$stock['production'][$stock_k],
                                'period'=>(float)$stock['period'][$stock_k],
                                'stock'=>(float)$stock['period'][$stock_k],
                                'unit_cost'=>(float)$stock['unit_cost'][$stock_k],
                                'price'=>(float)$stock['price'][$stock_k],
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                            $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                                'business_type' => '1',
                                'goods_id'      => '',
                                'goods_code'    => $params['goods_code'],
                                'attribute_id'  => $attribute_id,
                                'goods_unit'    => $params['unit_id'],
                                'stores_id'     => $stock_v,
                                'remark'        => $params['note'],
                                'balance_basics'=> (float)$stock['period'][$stock_k],
                                'balance_unit'  => (float)$stock['unit_cost'][$stock_k],
                                'balance_cost'  => (float)$stock['price'][$stock_k],
                                'createtime'    => time(),
                                'updatetime'    => time(),
                            ]);
                        }
                    }
//                    if(sizeof($stores)>0 && sizeof($stores)!=sizeof(array_unique($stores))){
//                        $this->error('期初设置不能选择重复的仓库');
//                    }
                    $params['inventory'] = array_sum($stock['period']);
                    $params['period'] = array_sum($stock['period']);
                    $params['price'] = array_sum($stock['price']);
                    $params['unit_cost'] = $params['period']>0?(round(($params['price']/$params['period']),2)):0;
                }else{
                    $params['period_status'] = 0;
                }

                //启用商品序列号
                $goods_serial = [];
                $serial_log = [];
                $serial_code = [];
                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                if(isset($params['serial']) && isset($params['serial']) && $params['serial']==1 && $set==1){
                    foreach ($stock['stores_id'] as $stock_k=>$stock_v){
                        if(!empty($stock_v) && !empty($stock['period_period'][$stock_k])){
                            if(isset($stock['attr_id'])){
                                if(!empty($stock['attr_id'][$stock_k])){
                                    $this_attr = explode('&',$stock['attr_id'][$stock_k]);
                                    asort($this_attr);
                                    $stock['attr_id'][$stock_k] = implode('&',$this_attr);
                                }
                                $attribute_id = isset($combination_list[$stock['attr_id'][$stock_k]])?$combination_list[$stock['attr_id'][$stock_k]]:0;
                            }else{
                                $attribute_id = 0;
                            }
                            $period_period = unserialize($stock['period_period'][$stock_k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                $serial_code[] = $period_period_v['serial'];
                                $goods_serial[] = \addons\kuerp\library\Goods::setDataInfo([
                                    'goods_code'=>$params['goods_code'],
                                    'stores_id'=>$stock_v,
                                    'attribute_id'=>$attribute_id,
                                    'serial'=>$period_period_v['serial'],
                                    'remark'=>$period_period_v['remark'],
                                    'store_status'=>'1',
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                                $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                                    'stores_id'=>$stock_v,
                                    'attribute_id'=>$attribute_id,
                                    'serial'=>$period_period_v['serial'],
                                    'business_type'=>'1',
                                    'store_status'=>'1',
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                            }
                        }
                    }
                    $status = Db::name('kuerp_goods_serial')->where(['serial'=>['in',$serial_code]])->find();
                    if($status){
                        $this->error('商品序列号已占用');
                    }
                }else{
                    $params['serial'] = 0;
                }

                if($params['more_unit']==1 && empty($params['more_unit_id'])){
                    $this->error('请选择商品多单位');
                }elseif ($params['more_unit']==1 && !empty($params['more_unit_id'])){
                    $unit = Db::name('kuerp_unit')->where(['id'=>$params['more_unit_id'],'type'=>'2'])->find();
                    if(empty($unit)){
                        $this->error('请选择正确的商品多单位');
                    }
                }
                if($params['more_unit']==2 && empty($params['unit_id'])){
                    $this->error('请选择商品单位');
                }elseif ($params['more_unit']==2 && !empty($params['unit_id'])){
                    $unit = Db::name('kuerp_unit')->where(['id'=>$params['unit_id'],'type'=>'1'])->find();
                    if(empty($unit)){
                        $this->error('请选择正确的商品单位');
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    $params['createtime'] = time();
                    $params['updatetime'] = time();
                    $result = Db::name('kuerp_goods')->insertGetId($params);

                    if(!empty($goods_attr)){
                        foreach ($goods_attr as $k=>$v){
                            $goods_attr[$k]['goods_id'] = $result;
                            unset($goods_attr[$k]['content']);
                            $goods_attr_id = Db::name('kuerp_goods_attr')->insertGetId($goods_attr[$k]);
                            //序列号跟踪
                            if(!empty($serial_log)){
                                foreach ($serial_log as $k2=>$v2){
                                    if($v2['attribute_id']==$v['attribute_id'])
                                        $serial_log[$k2]['attribute_id'] = (float)$goods_attr_id;
                                }
                            }
                            if(!empty($goods_stock_log)){
                                foreach ($goods_stock_log as $k2=>$v2){
                                    if($v2['attribute_id']==$v['attribute_id'])
                                        $goods_stock_log[$k2]['attribute_id'] = (float)$goods_attr_id;
                                }
                            }
                            if(!empty($goods_serial)){
                                foreach ($goods_serial as $k2=>$v2){
                                    if($v2['attribute_id']==$v['attribute_id'])
                                        $goods_serial[$k2]['attribute_id'] = (float)$goods_attr_id;
                                }
                            }
                            if(!empty($goods_stock)){
                                foreach ($goods_stock as $k2=>$v2){
                                    if($v2['attribute_id']==$v['attribute_id']){
                                        $goods_stock[$k2]['attribute_id'] = (float)$goods_attr_id;
                                    }
                                }
                            }
                            if(!empty($goods_stock)){
                                foreach ($goods_stock as $k2=>$v2){
                                    if($v2['attribute_id']==$v['attribute_id'])
                                        $goods_stock[$k2]['attribute_id'] = (float)$goods_attr_id;
                                }
                            }
                            if(!empty($goods_batch)){
                                foreach ($goods_batch as $k2=>$v2){
                                    if($v2['attribute_id']==$v['attribute_id'])
                                        $goods_batch[$k2]['attribute_id'] = (float)$goods_attr_id;
                                }
                            }
                        }
                    }

                    //序列号跟踪
                    if(!empty($serial_log)){
                        foreach ($serial_log as $k=>$v){
                            $serial_log[$k]['goods_id'] = $result;
                        }
                        Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                    }
                    if(!empty($goods_batch)){
                        foreach ($goods_batch as $k=>$v){
                            $address = $v['address'];
                            $registration = $v['registration'];
                            $production = $v['production'];
                            unset($v['address']);unset($v['registration']);unset($v['production']);

                            $v['goods_id'] = $result;
                            $batch_id = Db::name('kuerp_goods_batch')->insertGetId(\addons\kuerp\library\Goods::setDataInfo([
                                'goods_id' => $result,
                                'goods_code' => $v['goods_code'],
                                'attribute_id' => $v['attribute_id'],
                                'stores_id' => $v['stores_id'],
                                'batch_name' => $v['batch_name'],
                                'manufacture' => $v['manufacture'],
                                'guarantee' => $v['guarantee'],
                                'validity' => $v['validity'],
                                'address' => $address,
                                'registration' => $registration,
                                'production' => $production,
                                'createtime' => time(),
                                'updatetime' => time(),
                            ]));
                            Db::name('kuerp_goods_batch_stock')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'batch_id' => $batch_id,
                                'goods_id' => $result,
                                'goods_code' => $v['goods_code'],
                                'attribute_id' => $v['attribute_id'],
                                'stores_id' => $v['stores_id'],
                                'stock' => $v['stock'],
                                'createtime' => time(),
                                'updatetime' => time(),
                            ]));
                            Db::name('kuerp_goods_batch_log')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                'goods_id'      => $result,
                                'goods_unit'    => $params['unit_id'],
                                'stores_id'     => $v['stores_id'],
                                'batch_id'      => (float)$batch_id,
                                'number'        => (float)$v['stock'],
                                'unit_price'    => (float)$v['unit_cost'],
                                'tax_included'  => (float)$v['unit_cost'],
                                'all_money'     => (float)$v['price'],
                                'money_tax_price'  => (float)$v['price'],
                                'business_type' => '1',
                                'order_id'      => $result,
                                'createtime'    => time(),
                                'updatetime'    => time(),
                            ]));
                        }
                    }

                    if(!empty($goods_price)){
                        foreach ($goods_price as $k=>$v){
                            $goods_price[$k]['goods_id'] = $result;
                        }
                        Db::name('kuerp_goods_price')->insertAll($goods_price);
                    }

                    if(!empty($goods_stock_warning)){
                        foreach ($goods_stock_warning as $k=>$v){
                            $goods_stock_warning[$k]['goods_id'] = $result;
                        }
                        Db::name('kuerp_goods_stock_warning')->insertAll($goods_stock_warning);
                    }

                    if(!empty($goods_stock)){
                        foreach ($goods_stock as $k=>$v){
                            $goods_stock[$k]['goods_id'] = $result;
                        }
                        Db::name('kuerp_goods_period')->insertAll($goods_stock);
                        foreach ($goods_stock as $k=>$v){
                            $goods_stock[$k]['cost_money'] = $v['price'];
                        }
                        Db::name('kuerp_goods_stock')->insertAll($goods_stock);
                    }
                    //商品收发详情
                    if(!empty($goods_stock_log)){
                        foreach ($goods_stock_log as $k=>$v){
                            $goods_stock_log[$k]['goods_id'] = $result;
                        }
                        Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                    }

                    if(!empty($goods_serial)){
                        foreach ($goods_serial as $k=>$v){
                            $goods_serial[$k]['goods_id'] = $result;
                        }
                        Db::name('kuerp_goods_serial')->insertAll($goods_serial);
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
        $code = \addons\kuerp\library\Goods::getCodes('',6);
        $this->view->assign("code", $code);
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $rows = $this->model->get($ids);
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
            $branch = $this->request->post("branch/a");
            $stock = $this->request->post("stock/a");
            $more_unit = $this->request->post("more_unit/a");
            $auxiliary = $this->request->post("auxiliary/a");

            if ($params) {
                $params = $this->preExcludeFields($params);
                if(empty($params['goods_code'])){
                    $this->error('请输入商品编号');
                }
                if(Db::name('kuerp_goods')->where(['goods_code'=>trim($params['goods_code']),'id'=>['<>',$ids]])->find()){
                    $this->error('商品编号已占用');
                }
                if(!empty($params['goods_barcode'])){
                    if(Db::name('kuerp_goods')->where(['goods_barcode'=>trim($params['goods_barcode']),'id'=>['<>',$ids]])->find()){
                        $this->error('商品条码已占用');
                    }
                }

                $auxiliary_type = isset($params['auxiliary_type'])?$params['auxiliary_type']:[];
                if(isset($params['auxiliary_type']) && !empty($params['auxiliary_type'])){
                    $params['auxiliary_type'] = serialize($params['auxiliary_type']);
                }
                $auxiliary_type_c = isset($params['auxiliary_type_c'])?$params['auxiliary_type_c']:[];
                if(isset($params['auxiliary_type_c']) && !empty($params['auxiliary_type_c'])){
                    $params['auxiliary_type_c'] = serialize($params['auxiliary_type_c']);
                }

                //启用辅助属性
                $aux_combination = [];
                $auxiliary_code = [];
                $set= Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');
                if(isset($params['auxiliary']) && isset($params['auxiliary']) && $params['auxiliary']==1 && $set==1){
                    $status = false;
                    foreach ($auxiliary_type_c as $k=>$v){
                        if(!empty($v)){
                            $status = true;
                        }
                    }
                    if(!$status){
                        $this->error('请选择商品属性');
                    }
                    foreach ($auxiliary['code'] as $k=>$v){
                        if(!empty($v)){
                            $auxiliary_row = [];
                            foreach ($auxiliary_type as $type_k=>$type_v){
                                $auxiliary_row[] = $auxiliary[$type_v][$k];
                            }
                            $auxiliary_row = array_filter($auxiliary_row);
                            if(empty($auxiliary_row)){
                                $this->error('商品属性解析失败，请刷新后重试');
                            }
                            asort($auxiliary_row);
                            $aux_combination[] = ['ids'=>implode('&',$auxiliary_row),'code'=>$v];
                        }
                    }
                }
                $combination_add = [];
                $combination_list = [];$combination_code = [];
                foreach ($aux_combination as $aux_combination_k=>$aux_combination_v){
                    $combination_id = Db::name('kuerp_aux_combination')->where(['attr_ids'=>$aux_combination_v['ids']])->value('id');
                    if(empty($combination_id)){
                        $combination_id = Db::name('kuerp_aux_combination')->insertGetId([
                            'attr_ids'  => $aux_combination_v['ids'],
                            'attr_code' => $aux_combination_v['code'],
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ]);
                        $combination_add[] = $combination_id;
                    }
                    $combination_list[$aux_combination_v['ids']] = $combination_id;
                    $combination_code[$aux_combination_v['code']] = $combination_id;
                }
                $goods_attr = [];
                if(isset($params['auxiliary']) && isset($params['auxiliary']) && $params['auxiliary']==1 && $set==1){
                    foreach ($auxiliary['code'] as $k=>$v){
                        if(!empty($v)){
                            $auxiliary_code[] = $v;
                            if(isset($more_unit['id'][0]) && !empty($more_unit['id'][0])){
                                $unit = $more_unit['id'][0];
                            }else{
                                $unit = $params['unit_id'];
                            }
                            $auxiliary_row = [
                                'goods_code'=>$params['goods_code'],
                                'goods_name'=>$params['goods_name'],
                                'goods_barcode'=>$params['goods_barcode'],
                                'goods_id'=>'',
                                'attribute_id'=>$combination_code[$v],
                                'attr_code'=>$v,
                                'barcode'=>$auxiliary['barcode'][$k],
                                'unit'=>$unit,
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                            if($auxiliary['market_sell_price'][$k]!==''){
                                $auxiliary_row['market_sell_price'] = $auxiliary['market_sell_price'][$k];
                            }
                            if($auxiliary['wholesale_price'][$k]!==''){
                                $auxiliary_row['wholesale_price'] = $auxiliary['wholesale_price'][$k];
                            }
                            // if($auxiliary['vip_price'][$k]!==''){
                            //     $auxiliary_row['vip_price'] = $auxiliary['vip_price'][$k];
                            // }
                            // if($auxiliary['discount'][$k]!==''){
                            //     $auxiliary_row['discount'] = $auxiliary['discount'][$k];
                            // }
                            // if($auxiliary['discount2'][$k]!==''){
                            //     $auxiliary_row['discount2'] = $auxiliary['discount2'][$k];
                            // }
                            if($auxiliary['purchase'][$k]!==''){
                                $auxiliary_row['purchase'] = $auxiliary['purchase'][$k];
                            }
                            $goods_attr[] = $auxiliary_row;
                        }
                    }
                    $status = Db::name('kuerp_goods_attr')->where(['attr_code'=>['in',$auxiliary_code],'goods_id'=>['<>',$ids]])->find();
                    if($status){
                        $this->error('商品属性编号已占用');
                    }
                }else{
                    $params['auxiliary'] = 0;
                }

                //启用批次保质期管理
                $goods_batch = [];
                $set= Db::name('kuerp_sets')->where(['name'=>'batch'])->value('is_checked');
                if(isset($params['batch']) && isset($params['batch']) && $params['batch']==1 && $set==1){
                    foreach ($stock['stores_id'] as $k=>$v){
                        if(isset($stock['attr_id'])){
                            if(!empty($stock['attr_id'][$k])){
                                $this_attr = explode('&',$stock['attr_id'][$k]);
                                asort($this_attr);
                                $stock['attr_id'][$k] = implode('&',$this_attr);
                            }
                            $attribute_id = isset($combination_list[$stock['attr_id'][$k]])?$combination_list[$stock['attr_id'][$k]]:0;
                        }else{
                            $attribute_id = 0;
                        }
                        if(!empty($v)){
                            if(empty($stock['batch'][$k])){
                                $this->error('请输入批次');
                            }elseif(empty($stock['manufacture'][$k])){
                                $this->error('请输入生产日期');
                            }elseif ($stock['guarantee'][$k]===''){
                                $this->error('请输入保质期(天)');
                            }elseif ($stock['guarantee'][$k]<0){
                                $this->error('保质期天数不可小于0');
                            }elseif (empty($stock['validity'][$k])){
                                $this->error('请输入有效期至');
                            }
                            $goods_batch[] = [
                                'goods_id'    => '',
                                'goods_code'  => $params['goods_code'],
                                'attribute_id'=> $attribute_id,
                                'stores_id'   => $v,
                                'stock'       => $stock['period'][$k],
                                'number'      => $stock['period'][$k],
                                'batch_name'  => $stock['batch'][$k],
                                'manufacture' => strtotime($stock['manufacture'][$k]),
                                'guarantee'   => $stock['guarantee'][$k],
                                'validity'    => strtotime($stock['validity'][$k]),
                                'type'        => 1,
                                'unit_cost'      => $stock['unit_cost'][$k],
                                'price'  => $stock['price'][$k],
                                'address'=>$stock['address'][$k],
                                'registration'=>$stock['registration'][$k],
                                'production'=>$stock['production'][$k],

                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                        }
                    }
                }else{
                    $params['batch'] = 0;
                }

                //多单位&价格设置
                $goods_price = [];
                if(isset($params['more_unit']) && isset($params['more_unit']) && $params['more_unit']==1){//开启多单位
                    if(isset($more_unit['more_unit_id']) && !empty($more_unit['more_unit_id'])){
                        $params['more_unit_id'] = $more_unit['more_unit_id'];
                    }else{
                        $this->error('请选择多计量单位');
                    }
                    $params['warehousing'] = $more_unit['warehousing'];
                    $params['warehouse'] = $more_unit['warehouse'];
                    $params['market_sell_price'] = (float)$more_unit['market_sell_price'][0];
                    $params['wholesale_price'] = (float)$more_unit['wholesale_price'][0];
                    $params['vip_price'] = (float)$more_unit['vip_price'][0];
                    $params['discount'] = (float)$more_unit['discount'][0];
                    $params['discount2'] = (float)$more_unit['discount2'][0];
                    $params['purchase'] = (float)$more_unit['purchase'][0];
                    $params['unit_id'] = (float)$more_unit['id'][0];


                    foreach ($more_unit['id'] as $more_unit_k=>$more_unit_v){
                        $goods_price[] = [
                            'goods_code'=>$params['goods_code'],
                            'goods_name'=>$params['goods_name'],
                            'goods_barcode'=>$params['goods_barcode'],
                            'unit'=>$more_unit_v,
                            'market_sell_price'=>(float)$more_unit['market_sell_price'][$more_unit_k],
                            'wholesale_price'=>(float)$more_unit['wholesale_price'][$more_unit_k],
                            'vip_price'=>(float)$more_unit['vip_price'][$more_unit_k],
                            'discount'=>(float)$more_unit['discount'][$more_unit_k],
                            'discount2'=>(float)$more_unit['discount2'][$more_unit_k],
                            'purchase'=>(float)$more_unit['purchase'][$more_unit_k],
                            'is_default'=>($more_unit_k<1?'1':'0'),
                            'createtime'  => time(),
                            'updatetime'  => time(),
                        ];
                    }
                }else{
                    $params['more_unit'] = 2;
                }

                //预警设置
                $goods_stock_warning = [];
                if(!empty($branch) && isset($params['branch']) && $params['branch']==1){
                    foreach ($branch['stores'] as $k=>$v){
                        if(!empty($v) && !empty($branch['min_stock'][$k]) && !empty($branch['max_stock'][$k])){
                            $goods_stock_warning[] = [
                                'goods_id'    => '',
                                'goods_code'  => $params['goods_code'],
                                'stores_id'=>$v,
                                'min_stock'=>$branch['min_stock'][$k],
                                'max_stock'=>$branch['max_stock'][$k],
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                        }
                    }
                }else{
                    $params['branch'] = 0;
                }

                $stores = [];
                $goods_stock = [];$goods_stock_log = [];
                if(isset($_POST['setqc']) && $_POST['setqc']==1){//期初设置
                    $params['period_status'] = 1;
                    foreach ($stock['stores_id'] as $stock_k=>$stock_v){
                        if(!empty($stock_v)){
                            $stores[] = $stock_v;
                            if(isset($stock['attr_id']) && !empty($stock['attr_id'][$stock_k])){
                                $this_attr = explode('&',$stock['attr_id'][$stock_k]);
                                asort($this_attr);
                                $stock['attr_id'][$stock_k] = implode('&',$this_attr);
                            }
                            $attribute_id = isset($stock['attr_id'])? isset($combination_list[$stock['attr_id'][$stock_k]])?$combination_list[$stock['attr_id'][$stock_k]]:0 :null;
                            $goods_stock[] = [
                                'goods_code'=>$params['goods_code'],
                                'stores_id'=>$stock_v,
                                'attribute_id'=>$attribute_id,
                                'address'=>$stock['address'][$stock_k],
                                'registration'=>$stock['registration'][$stock_k],
                                'production'=>$stock['production'][$stock_k],
                                'period'=>$stock['period'][$stock_k],
                                'stock'=>$stock['period'][$stock_k],
                                'unit_cost'=>$stock['unit_cost'][$stock_k],
                                'price'=>$stock['price'][$stock_k],
                                'createtime'  => time(),
                                'updatetime'  => time(),
                            ];
                            $goods_stock_log[] = \addons\kuerp\library\Goods::setDataInfo([
                                'business_type' => '1',
                                'goods_id'      => '',
                                'goods_code'    => $params['goods_code'],
                                'attribute_id'  => $attribute_id,
                                'goods_unit'    => $params['unit_id'],
                                'stores_id'     => $stock_v,
                                'remark'        => $params['note'],
                                'balance_basics'=> (float)$stock['period'][$stock_k],
                                'balance_unit'  => (float)$stock['unit_cost'][$stock_k],
                                'balance_cost'  => (float)$stock['price'][$stock_k],
                                'createtime'    => time(),
                                'updatetime'    => time(),
                            ]);
                        }
                    }
                    $params['inventory'] = array_sum($stock['period']);
                    $params['period'] = array_sum($stock['period']);
                    $params['price'] = array_sum($stock['price']);
                    $params['unit_cost'] = $params['period']>0?(round(($params['price']/$params['period']),2)):0;
                }else{
                    $params['period_status'] = 0;
                }

                //启用商品序列号
                $goods_serial = [];
                $serial_log = [];
                $serial_code = [];
                $set= Db::name('kuerp_sets')->where(['name'=>'serial'])->value('is_checked');
                if(isset($params['serial']) && isset($params['serial']) && $params['serial']==1 && $set==1){
                    foreach ($stock['stores_id'] as $stock_k=>$stock_v){
                        if(isset($stock['attr_id'])){
                            if(!empty($stock['attr_id'][$stock_k])){
                                $this_attr = explode('&',$stock['attr_id'][$stock_k]);
                                asort($this_attr);
                                $stock['attr_id'][$stock_k] = implode('&',$this_attr);
                            }
                            $attribute_id = isset($combination_list[$stock['attr_id'][$stock_k]])?$combination_list[$stock['attr_id'][$stock_k]]:0;
                        }else{
                            $attribute_id = 0;
                        }
                        if(!empty($stock_v) && !empty($stock['period_period'][$stock_k])){
                            $period_period = unserialize($stock['period_period'][$stock_k]);
                            foreach ($period_period as $period_period_k=>$period_period_v){
                                $serial_code[] = $params['goods_code'];
                                $goods_serial[] = \addons\kuerp\library\Goods::setDataInfo([
                                    'goods_code'=>$params['goods_code'],
                                    'stores_id'=>$stock_v,
                                    'attribute_id'=>$attribute_id,
                                    'serial'=>$period_period_v['serial'],
                                    'remark'=>$period_period_v['remark'],
                                    'store_status'=>'1',
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                                $serial_log[] = \addons\kuerp\library\Goods::setDataInfo([
                                    'stores_id'=>$stock_v,
                                    'attribute_id'=>$attribute_id,
                                    'serial'=>$period_period_v['serial'],
                                    'business_type'=>'1',
                                    'store_status'=>'1',
                                    'createtime'  => time(),
                                    'updatetime'  => time(),
                                ]);
                            }
                        }
                    }
                    $status = Db::name('kuerp_goods_serial')->where(['id'=>['in',$serial_code]])->find();
                    if($status){
                        $this->error('商品序列号已占用');
                    }
                }else{
                    $params['serial'] = 0;
                }
                $goods_code = $rows['goods_code'];
                $goods_purchase_order_info = Db::name('kuerp_goods_purchase_order_info')->where(['goods_code'=>$goods_code])->field('id')->find();
                $goods_purchase_info = Db::name('kuerp_goods_purchase_info')->where(['goods_code'=>$goods_code])->field('id')->find();
                $goods_exchange_info = Db::name('kuerp_goods_exchange_info')->where(['goods_code'=>$goods_code])->field('id')->find();
                $goods_sales_order_info = Db::name('kuerp_goods_sales_order_info')->where(['goods_code'=>$goods_code])->field('id')->find();
                $goods_sales_info = Db::name('kuerp_goods_sales_info')->where(['goods_code'=>$goods_code])->field('id')->find();
                $sales_exchange_info = Db::name('kuerp_goods_sales_exchange_info')->where(['goods_code'=>$goods_code])->field('id')->find();
                $warehousing_info = Db::name('kuerp_warehousing_info')->where(['goods_id'=>$ids])->field('id')->find();
                $warehouse_info = Db::name('kuerp_warehouse_info')->where(['goods_id'=>$ids])->field('id')->find();
                $assemble_info = Db::name('kuerp_assemble_info')->where(['goods_id'=>$ids])->value('id');
                $split_info = Db::name('kuerp_split_info')->where(['goods_id'=>$ids])->value('id');
                $inventory_info = Db::name('kuerp_inventory_info')->where(['goods_id'=>$ids])->value('id');
                $inventory_serial_info = Db::name('kuerp_inventory_serial_info')->where(['goods_id'=>$ids])->value('id');
                if(!empty($assemble_info) || !empty($split_info) || !empty($inventory_info) || !empty($inventory_serial_info) || !empty($warehousing_info) || !empty($warehouse_info) || !empty($goods_purchase_order_info) || !empty($goods_purchase_info) || !empty($goods_exchange_info) || !empty($goods_sales_order_info) || !empty($goods_sales_info) || !empty($sales_exchange_info)){
                    if($rows['goods_code']!=$params['goods_code']){
                        $this->error('此商品已参与核算，不可编辑商品编号');
                    }
                    if($rows['goods_barcode']!=$params['goods_barcode']){
                        $this->error('此商品已参与核算，不可编辑商品条码');
                    }
                    if($rows['more_unit']!=$params['more_unit']){
                        $this->error('此商品已参与核算，不可编辑商品单位');
                    }else{
                        if($rows['more_unit']==1 && $rows['more_unit_id']!=$params['more_unit_id']){
                            $this->error('此商品已参与核算，不可编辑商品单位');
                        }
                        if($rows['more_unit']!=1 && $rows['unit_id']!=$params['unit_id']){
                            $this->error('此商品已参与核算，不可编辑商品单位');
                        }
                    }
                    if($rows['period_status']!=$params['period_status']){
                        $this->error('此商品已参与核算，不可编辑商品期初数据');
                    }else{
                        if($params['period_status']==1){
                            if($rows['period']!=$params['period'] || $rows['unit_cost']!=$params['unit_cost'] || $rows['price']!=$params['price'] || $rows['period_status']!=$params['period_status']){
                                $this->error('此商品已参与核算，不可编辑商品期初数据');
                            }
                        }
                    }
                    if($rows['auxiliary']!=$params['auxiliary']){
                        if($params['auxiliary']==1 && ($rows['auxiliary_type']!=$params['auxiliary_type'] || $rows['auxiliary_type_c']!=$params['auxiliary_type_c'])){
                            $this->error('此商品已参与核算，不可编辑商品属性');
                        }
                    }
                    if($params['batch']!=1){
                        $params['shelf_life'] = 0;$params['early_warning'] = 0;
                    }
                    if($rows['batch']!=$params['batch'] || $rows['shelf_life']!=$params['shelf_life'] || $rows['early_warning']!=$params['early_warning']){
                        $this->error('此商品已参与核算，不可编辑商品批次信息');
                    }
                    if($rows['serial']!=$params['serial']){
                        $this->error('此商品已参与核算，不可编辑商品序列号信息');
                    }
                }

                $check = self::checkRow($ids);
                $detail = $params;
                if(isset($check['status'])){
                    if($row['goods_code']!=$detail['goods_code']){
                        $msg = '此商品已参与核算，不可编辑商品编号';
                    }
                    if($row['more_unit']!=$detail['more_unit']){
                        $msg = '此商品已参与核算，不可编辑商品单位';
                    }else{
                        if($row['more_unit']==1 && $row['more_unit_id']!=$detail['more_unit_id']){
                            $msg = '此商品已参与核算，不可编辑商品单位';
                        }
                        if($row['more_unit']!=1 && $row['unit_id']!=$detail['unit_id']){
                            $msg = '此商品已参与核算，不可编辑商品单位';
                        }
                    }
                    if($row['period_status']!=$detail['period_status']){
                        $msg = '此商品已参与核算，不可编辑商品期初数据';
                    }else{
                        if($detail['period_status']==1){
                            if($row['period']!=$detail['period'] || $row['unit_cost']!=$detail['unit_cost'] || $row['price']!=$detail['price'] || $row['period_status']!=$detail['period_status']){
                                $msg = '此商品已参与核算，不可编辑商品期初数据';
                            }
                        }
                    }
                    if($row['auxiliary']!=$detail['auxiliary']){
                        $msg = '此商品已参与核算，不可编辑商品属性';
                    }
                    if($row['auxiliary']==$detail['auxiliary'] && $detail['auxiliary']==1 && ($row['auxiliary_type']!=$detail['auxiliary_type'] || $row['auxiliary_type_c']!=$detail['auxiliary_type_c'])){
                        $msg = '此商品已参与核算，不可编辑商品属性';
                    }
                    if($detail['batch']!=1){
                        $detail['shelf_life'] = 0;$detail['early_warning'] = 0;
                    }
                    if($row['batch']!=$detail['batch'] || $row['shelf_life']!=$detail['shelf_life'] || $row['early_warning']!=$detail['early_warning']){
                        $msg = '此商品已参与核算，不可编辑商品批次信息';
                    }
                    if($row['serial']!=$detail['serial']){
                        $msg = '此商品已参与核算，不可编辑商品序列号信息';
                    }
                    if(isset($msg)){
                        return ['status'=>500,'msg'=>$msg];
                    }
                }

                if($params['more_unit']==1 && empty($params['more_unit_id'])){
                    $this->error('请选择商品多单位');
                }elseif ($params['more_unit']==1 && !empty($params['more_unit_id'])){
                    $unit = Db::name('kuerp_unit')->where(['id'=>$params['more_unit_id'],'type'=>'2'])->find();
                    if(empty($unit)){
                        $this->error('请选择正确的商品多单位');
                    }
                }
                if($params['more_unit']==2 && empty($params['unit_id'])){
                    $this->error('请选择商品单位');
                }elseif ($params['more_unit']==2 && !empty($params['unit_id'])){
                    $unit = Db::name('kuerp_unit')->where(['id'=>$params['unit_id'],'type'=>'1'])->find();
                    if(empty($unit)){
                        $this->error('请选择正确的商品单位');
                    }
                }

                if($check!==true && $goods_attr){
                    $goods_attrs = $goods_attr;
                    $attr_list = Db::name('kuerp_goods_attr')->where(['goods_id'=>$ids])->select();
                    foreach ($attr_list as $attr_list_k=>$attr_list_v){
                        foreach ($goods_attrs as $goods_attr_k=>$goods_attr_v){
                            if($goods_attr_v['attribute_id']==$attr_list_v['attribute_id'] && $goods_attr_v['attr_code']==$attr_list_v['attr_code']){
                                unset($goods_attrs[$goods_attr_k]);unset($attr_list[$attr_list_k]);$goods_attr[$goods_attr_k]['id'] = $attr_list_v['id'];
                            }
                        }
                    }
                    if(!empty($attr_list) || !empty($goods_attrs)){
                        $this->error('此商品已参与核算，不可编辑商品属性');
                    }
                }

                $old_goods_period = Db::name('kuerp_goods_period')->where(['goods_id'=>['in',$ids]])->select();
                $status = false;
                Db::startTrans();
                try {
                    $where = ['goods_id'=>['in',$ids]];
                    $params['updatetime'] = time();
                    $serial = Db::name('kuerp_goods_serial_log')->where(['goods_id'=>$row['id'],'business_type'=>'1'])->column('serial');
                    $goods_batch_id = Db::name('kuerp_goods_batch_log')->where(['goods_id'=>$row['id'],'business_type'=>'1'])->column('batch_id');
                    Db::name('kuerp_goods_stock_warning')->where($where)->delete();
                    $result = $ids;
                    if($check===true){
                        $stock_ids = Db::name('kuerp_goods_stock')->where($where)->column('id');
                        Db::name('kuerp_goods_batch')->where($where)->where(['id'=>['in',$goods_batch_id]])->delete();
                        Db::name('kuerp_goods_price')->where($where)->delete();
                        Db::name('kuerp_goods_serial')->where($where)->where(['serial'=>['in',$serial]])->delete();
                        Db::name('kuerp_goods_period')->where($where)->delete();
                        Db::name('kuerp_goods_log')->where($where)->delete();
                        Db::name('kuerp_goods_serial_log')->where($where)->where(['business_type'=>'1'])->delete();
                        Db::name('kuerp_goods_batch_stock')->where($where)->where(['batch_id'=>['in',$goods_batch_id]])->delete();
                        Db::name('kuerp_goods_batch_log')->where($where)->where(['business_type'=>'1'])->delete();

                        $goods_attr_data = [];
                        if(!empty($goods_attr)){
                            foreach ($goods_attr as $k=>$v){
                                $goods_attr[$k]['goods_id'] = $result;

                                $goodsAttr = Db::name('kuerp_goods_attr')
                                    ->where([
                                        'goods_code' => $v['goods_code'],
                                        'attribute_id' => $goods_attr[$k]['attribute_id'],
                                    ])->find();
                                if($goodsAttr){
                                    $goods_attr_id = $goodsAttr['id'];
                                    Db::name('kuerp_goods_attr')->where(['id'=>$goodsAttr['id']])->update($goods_attr[$k]);
                                }else{
                                    $goods_attr_id = Db::name('kuerp_goods_attr')->insertGetId($goods_attr[$k]);
                                }
                                $goods_attr_data[] = $goods_attr_id;

                                //序列号跟踪
                                if(!empty($serial_log)){
                                    foreach ($serial_log as $k2=>$v2){
                                        if($v2['attribute_id']==$v['attribute_id']){
                                            $serial_log[$k2]['attribute_id'] = (float)$goods_attr_id;
                                        }
                                    }
                                }
                                if(!empty($goods_stock_log)){
                                    foreach ($goods_stock_log as $k2=>$v2){
                                        if($v2['attribute_id']==$v['attribute_id'])
                                        {
                                            $goods_stock_log[$k2]['attribute_id'] = (float)$goods_attr_id;
                                        }
                                    }
                                }
                                if(!empty($goods_serial)){
                                    foreach ($goods_serial as $k2=>$v2){
                                        if($v2['attribute_id']==$v['attribute_id'])
                                        {
                                            $goods_serial[$k2]['attribute_id'] = (float)$goods_attr_id;
                                        }
                                    }
                                }
                                if(!empty($goods_stock)){
                                    foreach ($goods_stock as $k2=>$v2){
                                        if($v2['attribute_id']==$v['attribute_id'])
                                        {
                                            $goods_stock[$k2]['attribute_id'] = (float)$goods_attr_id;
                                        }
                                    }
                                }
                                if(!empty($goods_batch)){
                                    foreach ($goods_batch as $k2=>$v2){
                                        if($v2['attribute_id']==$v['attribute_id'])
                                        {
                                            $goods_batch[$k2]['attribute_id'] = (float)$goods_attr_id;
                                        }
                                    }
                                }
                            }
                        }

                        foreach ($goods_stock as $k2=>$v2){
                            if(isset($params['auxiliary']) && isset($params['auxiliary']) && $params['auxiliary']==1 && $set==1 && empty($v2['attribute_id'])){
                                exception('期初属性选择错误');
                            }
                        }
                        Db::name('kuerp_goods_attr')->where($where)->where(['id'=>['not in',$goods_attr_data]])->delete();

                        //序列号跟踪
                        if(!empty($serial_log)){
                            foreach ($serial_log as $k=>$v){
                                $serial_log[$k]['goods_id'] = $result;
                            }
                            Db::name('kuerp_goods_serial_log')->insertAll($serial_log);
                        }

                        if(!empty($goods_batch)){
                            foreach ($goods_batch as $k=>$v){
                                $v['goods_id'] = $result;
                                $stock = $v['stock'];$number = $v['number'];$type = $v['type'];
                                unset($v['stock']);unset($v['number']);unset($v['type']);
                                $unit_cost = (float)$v['unit_cost'];$price = (float)$v['price'];unset($v['unit_cost']);unset($v['price']);
                                $batch_id = Db::name('kuerp_goods_batch')->insertGetId($v);
                                $v['stock'] = $stock; $v['number'] = $number; $v['type'] = $type;
                                Db::name('kuerp_goods_batch_stock')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                    'batch_id' => $batch_id,
                                    'goods_id' => $result,
                                    'goods_code' => $v['goods_code'],
                                    'attribute_id' => $v['attribute_id'],
                                    'stores_id' => $v['stores_id'],
                                    'stock' => $v['stock'],
                                    'createtime' => time(),
                                    'updatetime' => time(),
                                ]));
                                Db::name('kuerp_goods_batch_log')->insert(\addons\kuerp\library\Goods::setDataInfo([
                                    'goods_id'      => $result,
                                    'goods_unit'    => $params['unit_id'],
                                    'stores_id'     => $v['stores_id'],
                                    'batch_id'      => (float)$batch_id,
                                    'number'        => (float)$v['stock'],
                                    'unit_price'    => $unit_cost,
                                    'tax_included'  => $unit_cost,
                                    'all_money'     => $price,
                                    'money_tax_price'  => $price,
                                    'business_type' => '1',
                                    'order_id'      => $result,
                                    'createtime'    => time(),
                                    'updatetime'    => time(),
                                ]));
                            }
                        }

                        if(!empty($goods_price)){
                            foreach ($goods_price as $k=>$v){
                                $goods_price[$k]['goods_id'] = $result;
                            }
                            Db::name('kuerp_goods_price')->insertAll($goods_price);
                        }

                        //商品收发详情
                        if(!empty($goods_stock_log)){
                            foreach ($goods_stock_log as $k=>$v){
                                $goods_stock_log[$k]['goods_id'] = $result;
                            }
                            Db::name('kuerp_goods_log')->insertAll($goods_stock_log);
                        }

                        $stock_number = 0;
                        foreach ($goods_stock as $k=>$v){
                            $goods_stock[$k]['goods_id'] = $result;
                            $stock_number += $v['stock'];
                        }
                        Db::name('kuerp_goods_period')->insertAll($goods_stock);
                        foreach ($goods_stock as $k=>$v){
                            $goods_stock[$k]['cost_money'] = $v['price'];
                        }

                        foreach ($old_goods_period as $k=>$v){
                            $map = [];
                            $map['goods_code'] = $v['goods_code'];
                            $map['attribute_id'] = $v['attribute_id'];
                            $map['stores_id'] = $v['stores_id'];
                            $old_stock = Db::name('kuerp_goods_stock')->where($map)->find();
                            if($old_stock){
                                foreach ($stock_ids as $stock_ids_k=>$stock_ids_v){
                                    if($stock_ids_v==$old_stock['id']){
                                        unset($stock_ids[$stock_ids_k]);
                                    }
                                }
                                $stock_number = $old_stock['stock']-$v['period'];
                                if(!empty($goods_stock)){
                                    foreach ($goods_stock as $k2=>$v2){
                                        if($v['goods_code']==$v2['goods_code'] && $v['attribute_id']==$v2['attribute_id'] && $v['stores_id']==$v2['stores_id']){
                                            $stock_number += $v2['stock'];
                                            unset($goods_stock[$k2]);
                                        }
                                    }
                                }
                                if($stock_number<0){
                                    exception('库存不足');
                                }
                                Db::name('kuerp_goods_stock')->where(['id'=>$old_stock['id']])->update(['stock'=>$stock_number]);
                            }
                        }
                        if(!empty($goods_stock)){
                            foreach ($goods_stock as $k=>$v){
                                $map = [];
                                $map['goods_code'] = $v['goods_code'];
                                $map['attribute_id'] = $v['attribute_id'];
                                $map['stores_id'] = $v['stores_id'];
                                $old_stock = Db::name('kuerp_goods_stock')->where($map)->find();
                                if($old_stock){
                                    foreach ($stock_ids as $stock_ids_k=>$stock_ids_v){
                                        if($stock_ids_v==$old_stock['id']){
                                            unset($stock_ids[$stock_ids_k]);
                                        }
                                    }
                                    Db::name('kuerp_goods_stock')->where(['id'=>$old_stock['id']])->inc('stock',$v['stock'])->update();
                                }else{
                                    Db::name('kuerp_goods_stock')->insert($v);
                                }
                            }
                        }
                        if(!empty($stock_ids)){
                            Db::name('kuerp_goods_stock')->where(['goods_id'=>$row['id'],'id'=>['in',$stock_ids]])->delete();
                        }
                        if(!empty($goods_serial)){
                            foreach ($goods_serial as $k=>$v){
                                $goods_serial[$k]['goods_id'] = $result;
                            }
                            Db::name('kuerp_goods_serial')->insertAll($goods_serial);
                        }
                    }else{
                        $price_ids = [];
                        foreach ($goods_price as $k=>$v){
                            $v['goods_id'] = $ids;
                            $price = Db::name('kuerp_goods_price')->where(['goods_code'=>$v['goods_code'],'unit'=>$v['unit']])->find();
                            if($price){
                                $price_id = $price['id'];
                                Db::name('kuerp_goods_price')->where(['goods_code'=>$v['goods_code'],'unit'=>$v['unit']])->update($v);
                            }else{
                                $price_id = Db::name('kuerp_goods_price')->insertGetId($v);
                            }
                            $price_ids[] = $price_id;
                        }
                        Db::name('kuerp_goods_price')->where(['goods_id'=>$ids,'id'=>['not in',$price_ids]])->delete();

                        if($goods_attr){
                            foreach ($goods_attr as $goods_attr_k=>$goods_attr_v){
                                if(isset($goods_attr_v['id'])){
                                    $goods_attr_v['goods_id'] = $result;
                                    Db::name('kuerp_goods_attr')->where(['id'=>$goods_attr_v['id']])->update($goods_attr_v);
                                }
                            }
                        }
                    }

                    if(!empty($goods_stock_warning)){
                        foreach ($goods_stock_warning as $k=>$v){
                            $goods_stock_warning[$k]['goods_id'] = $result;
                        }
                        Db::name('kuerp_goods_stock_warning')->insertAll($goods_stock_warning);
                    }

                    $all_stock = Db::name('kuerp_goods_stock')->where(['goods_code'=>$params['goods_code']])->sum('stock');
                    $params['inventory'] = $all_stock;
                    $status = Db::name('kuerp_goods')->where(['id'=>$ids])->update($params);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($status !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        if(!empty($row['auxiliary_type'])){
            $row['auxiliary_type'] = unserialize($row['auxiliary_type']);
        }else{
            $row['auxiliary_type'] = [];
        }
        if(!empty($row['auxiliary_type_c'])){
            $row['auxiliary_type_c'] = unserialize($row['auxiliary_type_c']);
        }else{
            $row['auxiliary_type_c'] = [];
        }

        $this->view->assign("row", $row);

        $unit_lists = [];
        if($row['more_unit']==1){
            $unit_list = Db::name('kuerp_unit_conversion')
                ->where(['main_unit'=>$row['unit_id']])
                ->order('advance_rate','ASC')
                ->field('vice_unit,advance_rate')
                ->select();
            $unit_id = [$row['unit_id']];
            foreach ($unit_list as $unit_list_k=>$unit_list_v){
                $unit_id[] = $unit_list_v['vice_unit'];
            }
            $unit_lists = Db::name('kuerp_unit')->where(['id'=>['in',$unit_id]])->field('id,name')->select();
            foreach ($unit_lists as $unit_k=>$unit_v){
                if($unit_v['id']==$row['unit_id']){
                    $unit_lists[$unit_k]['advance_rate'] = 1;
                }else{
                    foreach ($unit_list as $unit_list_k=>$unit_list_v){
                        if($unit_v['id']==$unit_list_v['vice_unit']){
                            $unit_lists[$unit_k]['advance_rate'] = $unit_list_v['advance_rate'];
                        }
                    }
                }
            }
        }
        $this->view->assign("unit", $unit_lists);

        $goods_price = Db::name('kuerp_goods_price')->alias('g')
            ->join('kuerp_unit u','u.id=g.unit')
            ->where(['g.goods_id'=>$row['id']])
            ->field('g.unit,g.market_sell_price,g.wholesale_price,g.vip_price,g.discount,g.discount2,g.purchase,g.is_default,u.name')
            ->order('g.is_default DESC')->select();
        foreach ($unit_lists as $k=>$v){
            $add = true;
            foreach ($goods_price as $k2=>$v2){
                if($v['id']==$v2['unit']){
                    $add = false;
                }
            }
            if($add){
                $goods_price[] = [
                    'unit' => $v['id'],
                    'market_sell_price' => '',
                    'wholesale_price' => '',
                    'vip_price' => '',
                    'discount' => '',
                    'discount2' => '',
                    'purchase' => '',
                    'is_default' => 0,
                    'name' => $v['name']
                ];
            }
        }
        $this->view->assign("goods_price", $goods_price);

        $goods_stock = Db::name('kuerp_goods_stock_warning')->where(['goods_id'=>$row['id']])->select();
        $goods_stock_arr = [];
        foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
            $goods_stock_arr[$goods_stock_v['stores_id']] = $goods_stock_v;
        }
        $this->view->assign("stock_warning", $goods_stock_arr);

        $auxiliary = Db::name('kuerp_auxiliary')->where(['status'=>1])->column('name','id');
        $goods_attr = Db::name('kuerp_goods_attr')->where(['goods_id'=>$row['id']])->select();
        foreach ($goods_attr as $goods_attr_k=>$goods_attr_v){
            $aux_combination = Db::name('kuerp_aux_combination')->where(['id'=>$goods_attr_v['attribute_id']])->value('attr_ids');
            $goods_attr[$goods_attr_k]['attr_ids'] = [];
            $goods_attr[$goods_attr_k]['attr_name'] = '';
            if($aux_combination){
                $goods_attr[$goods_attr_k]['attr_id'] = $aux_combination;
                $aux_combination = explode('&',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_ids'] = $aux_combination;
                foreach ($aux_combination as $aux_combination_k=>$aux_combination_v){
                    $aux_combination[$aux_combination_k] = isset($auxiliary[$aux_combination_v])?$auxiliary[$aux_combination_v]:'';
                }
                $aux_combination = implode('/',$aux_combination);
                $goods_attr[$goods_attr_k]['attr_name'] = $aux_combination;
            }
        }
        
                    // dump($goods_attr);die;
        $this->view->assign("goods_attr", $goods_attr);

        $goods_batch = Db::name('kuerp_goods_batch')->where(['goods_id'=>$row['id']])->select();
        $this->view->assign("attr_arr", $goods_batch);

        $goods_stock = Db::name('kuerp_goods_period')->where(['goods_id'=>$row['id']])->select();
        foreach ($goods_stock as $goods_stock_k=>$goods_stock_v){
            foreach ($goods_batch as $goods_batch_k=>$goods_batch_v){
                if($goods_batch_v['attribute_id']==$goods_stock_v['attribute_id'] && $goods_batch_v['stores_id']==$goods_stock_v['stores_id']){
                    $goods_batch_v['manufacture'] = date('Y-m-d',$goods_batch_v['manufacture']);
                    $goods_batch_v['validity'] = date('Y-m-d',$goods_batch_v['validity']);
                    $goods_stock[$goods_stock_k]['batch'] = $goods_batch_v;
                }
            }
            $attribute_id = Db::name('kuerp_goods_attr')->where(['goods_id'=>$goods_stock_v['goods_id'],'id'=>$goods_stock_v['attribute_id']])->value('id');
            $serier = Db::name('kuerp_goods_serial')->where(['goods_id'=>$goods_stock_v['goods_id'],'attribute_id'=>$attribute_id])->field('serial,remark')->select();
            if($serier){
                $goods_stock[$goods_stock_k]['period'] = sizeof($serier);
                $goods_stock[$goods_stock_k]['serier'] = serialize($serier);
            }
        }
        $this->view->assign("goods_stock", $goods_stock);

        return $this->view->fetch();
    }

    private function checkRow($id=null,$code=null){
        if(empty($id) && empty($code)){
            return ['status'=>500,'msg'=>'未查询到商品数据'];
        }
        if(!empty($id)){
            $row = Db::name('kuerp_goods')->where(['id'=>$id])->find();
        }else{
            $row = Db::name('kuerp_goods')->where(['goods_code'=>$code])->find();
        }
        if(empty($row)){
            return ['status'=>400,'msg'=>'未查询到商品数据'];
        }
        $goods_code = $row['goods_code'];

        $order_info = Db::name('kuerp_goods_purchase_order_info')->where(['goods_code'=>$goods_code])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有购货订单'];
        }
        $order_info = Db::name('kuerp_goods_purchase_info')->where(['goods_code'=>$goods_code])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有购货单'];
        }
        $order_info = Db::name('kuerp_goods_exchange_info')->where(['goods_code'=>$goods_code])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有购货退货单'];
        }
        $order_info = Db::name('kuerp_goods_sales_order_info')->where(['goods_code'=>$goods_code])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有销货订单'];
        }
        $order_info = Db::name('kuerp_goods_sales_info')->where(['goods_code'=>$goods_code])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有销货单'];
        }
        $order_info = Db::name('kuerp_goods_sales_exchange_info')->where(['goods_code'=>$goods_code])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有销货退货单'];
        }

        $order_info = Db::name('kuerp_warehouse_info')->where(['goods_id'=>$id])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有其他出库单'];
        }
        $order_info = Db::name('kuerp_warehousing_info')->where(['goods_id'=>$id])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有其他出库单'];
        }

        $order_info = Db::name('kuerp_assemble_info')->where(['goods_id'=>$id])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有组装单'];
        }
        $order_info = Db::name('kuerp_split_info')->where(['goods_id'=>$id])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有拆卸单'];
        }
        $order_info = Db::name('kuerp_inventory_info')->where(['goods_id'=>$id])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有盘点单'];
        }
        $order_info = Db::name('kuerp_inventory_serial_info')->where(['goods_id'=>$id])->value('id');
        if(!empty($order_info)){
            return ['status'=>500,'msg'=>$goods_code.'已有序列号盘点单'];
        }
        return true;
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
                $this->chackstatus($list_v['goods_code']);
            }

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                $where = ['goods_id'=>['in',$ids]];
                Db::name('kuerp_goods_attr')->where($where)->delete();
                Db::name('kuerp_goods_batch')->where($where)->delete();
                Db::name('kuerp_goods_price')->where($where)->delete();
                Db::name('kuerp_goods_stock_warning')->where($where)->delete();
                Db::name('kuerp_goods_serial')->where($where)->delete();
                Db::name('kuerp_goods_stock')->where($where)->delete();
                Db::name('kuerp_goods_period')->where($where)->delete();
                Db::name('kuerp_goods_log')->where($where)->delete();
                Db::name('kuerp_goods_serial_log')->where($where)->delete();
                Db::name('kuerp_goods_batch_stock')->where($where)->delete();
                Db::name('kuerp_goods_batch_log')->where($where)->delete();

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

    private function chackstatus($goods_code){
        $goods_purchase_order_info = Db::name('kuerp_goods_purchase_order_info')->where(['goods_code'=>$goods_code])->field('id')->find();
        $goods_purchase_info = Db::name('kuerp_goods_purchase_info')->where(['goods_code'=>$goods_code])->field('id')->find();
        if(!empty($goods_purchase_order_info) || !empty($goods_purchase_info)){
            $this->error('此商品已参与核算，不可编辑');
        }
    }

    public function auxiliary(){
        $post = $this->request->post();

        $list = $spec_name = Db::name('kuerp_auxiliary')->where(['id'=>['in',$post['typearr']]])->select();
        foreach ($list as $list_k=>$list_v){
            $list[$list_k]['spec'] = Db::name('kuerp_auxiliary')->where(['pid'=>$list_v['id'],'id'=>['in',$post['typesarr']]])->column('name','id');
            $spec_name[$list_k]['spec'] = Db::name('kuerp_auxiliary')->where(['pid'=>$list_v['id'],'id'=>['in',$post['typesarr']]])->column('id');
        }
        $lists = \addons\kuerp\library\Goods::combineDika($list);
        foreach ($lists as $lists_k=>$lists_v){
            $lists[$lists_k]['code'] = $post['code'].'_'.($lists_k+1);
            $lists[$lists_k]['spec_name'] = implode('/',$lists_v['spec']);
            $lists[$lists_k]['spec_id'] = $spec_id = explode('&',$lists_v['id']);
            $lists[$lists_k]['spec_arr'] = implode('/',$spec_id);
            foreach ($spec_id as $spec_id_k=>$spec_id_v){
                $lists[$lists_k]['spec_id'][$spec_id_k] =  $spec_id_v;
            }
        }
        $this->success(['lists'=>$lists,'spec_name'=>$spec_name]);
    }

    public function period(){
        if ($this->request->isPost()) {
            $data = $_POST;
            if(isset($data['period_period'])){
                $this->success(['ser'=>unserialize($data['period_period'])]);
            }elseif (isset($data['chose_serial'])){
                $chose_serial = $data['chose_serial'];
                $serial_data = [];
                foreach ($chose_serial as $chose_serial_k=>$chose_serial_v){
                    $serial_data[] = ['serial'=>$chose_serial_v,'remark'=>''];
                }
                $this->success(['num'=>sizeof($serial_data),'ser'=>serialize($serial_data)]);
            }
            $params = $this->request->post("stock/a");
            $data = [];
            foreach ($params['serial'] as $k=>$v){
                if(!empty($v)){
                    $data[] = [
                        'serial' => $v,
                        'remark' => (isset($params['remark'][$k])?$params['remark'][$k]:''),
                    ];
                }
            }
            $this->success(['num'=>sizeof($data),'ser'=>serialize($data)]);
        }
        return $this->view->fetch();
    }

    //弹窗选择商品
    public function chosegoods(){
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $data = input();
            $where = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['keyword'])){
                    $where['goods_code|goods_name|goods_barcode'] = ['like','%'.$filter['keyword'].'%'];
                }
                if(!empty($filter['type'])){
                    $where['goods_type_id'] = ['in',$filter['type']];
                }
                if(!empty($filter['product_type'])){
                    $where['product_type'] = $filter['product_type'];
                }
                if(!empty($filter['location_id'])){
                    $where['location_id'] = $filter['location_id'];
                }
            }
            if(!empty($data['goods_name'])){
                $where['goods_code|goods_name|goods_barcode'] = ['like','%'.$data['goods_name'].'%'];
            }
            $where['status'] = '1';
            $goods = Db::name('kuerp_goods')->where($where)->select();
            $auxiliary = Db::name('kuerp_sets')->where(['name'=>'auxiliary'])->value('is_checked');

            // ===== 批量预查询：收集所有需要的ID =====
            $allUnitIds = [];
            $allTypeIds = [];
            $allLocationIds = [];
            $auxGoodsCodes = []; // 有辅助属性的商品编码

            foreach ($goods as $g) {
                if ($g['unit_id']) $allUnitIds[] = $g['unit_id'];
                if ($g['goods_type_id']) $allTypeIds[] = $g['goods_type_id'];
                if ($g['location_id']) $allLocationIds[] = $g['location_id'];
                if ($auxiliary == 1 && $g['auxiliary']) $auxGoodsCodes[] = $g['goods_code'];
            }

            // 批量查询：单位
            $unitMap = [];
            if ($allUnitIds) {
                $units = Db::name('kuerp_unit')->where(['id' => ['in', array_unique($allUnitIds)]])->field('id,name')->select();
                foreach ($units as $u) $unitMap[$u['id']] = $u;
            }

            // 批量查询：商品类别
            $typeMap = [];
            if ($allTypeIds) {
                $types = Db::name('kuerp_goods_type')->where(['id' => ['in', array_unique($allTypeIds)]])->field('id,supplier_type_name as name')->select();
                foreach ($types as $t) $typeMap[$t['id']] = $t;
            }

            // 批量查询：库位 + 层级路径解析（使用path字段构建完整层级名）
            $locationMap = [];
            if ($allLocationIds) {
                $allLocationIds = array_unique($allLocationIds);
                $locations = Db::name('kuerp_goods_location')->where(['id' => ['in', $allLocationIds]])->field('id,location_name,pid,path')->select();
                // 收集所有祖先ID
                $allAncestorIds = [];
                foreach ($locations as $loc) {
                    if ($loc['path']) {
                        $pids = array_filter(explode(',', trim($loc['path'], ',')));
                        foreach ($pids as $pid) $allAncestorIds[] = (int)$pid;
                    }
                }
                $ancestorMap = [];
                if ($allAncestorIds) {
                    $ancestors = Db::name('kuerp_goods_location')->where(['id' => ['in', array_unique($allAncestorIds)]])->field('id,location_name')->select();
                    foreach ($ancestors as $a) $ancestorMap[$a['id']] = $a['location_name'];
                }
                // 构建完整层级名
                foreach ($locations as $loc) {
                    $nameChain = [];
                    if ($loc['path']) {
                        $pids = array_filter(explode(',', trim($loc['path'], ',')));
                        foreach ($pids as $pid) {
                            $pid = (int)$pid;
                            if ($pid > 0 && isset($ancestorMap[$pid])) {
                                $nameChain[] = $ancestorMap[$pid];
                            }
                        }
                    }
                    $nameChain[] = $loc['location_name'];
                    $locationMap[$loc['id']] = [
                        'id'   => $loc['id'],
                        'name' => implode('-', $nameChain), // e.g. "A-1库"
                    ];
                }
            }

            // 批量查询：辅助属性（商品属性、属性组合、辅助资料名称）
            $attrByGoods = []; // goods_code => [attr rows]
            $allCombinationIds = [];
            if ($auxGoodsCodes) {
                $allAttrs = Db::name('kuerp_goods_attr')->where(['goods_code' => ['in', $auxGoodsCodes]])->field('id,goods_code,attribute_id,attr_code')->select();
                foreach ($allAttrs as $a) {
                    $a['__goods_code'] = $a['goods_code'];
                    $attrByGoods[$a['goods_code']][] = $a;
                    if ($a['attribute_id']) $allCombinationIds[] = $a['attribute_id'];
                }
            }
            $combinationMap = []; // combination_id => attr_ids array
            $allAuxIds = [];
            if ($allCombinationIds) {
                $combinations = Db::name('kuerp_aux_combination')->where(['id' => ['in', array_unique($allCombinationIds)]])->field('id,attr_ids')->select();
                foreach ($combinations as $c) {
                    if ($c['attr_ids']) {
                        $ids = explode('&', $c['attr_ids']);
                        $combinationMap[$c['id']] = $ids;
                        foreach ($ids as $aid) $allAuxIds[] = (int)$aid;
                    }
                }
            }
            $auxNameMap = []; // auxiliary_id => name
            if ($allAuxIds) {
                $auxNames = Db::name('kuerp_auxiliary')->where(['id' => ['in', array_unique($allAuxIds)]])->column('name', 'id');
                foreach ($auxNames as $aid => $aname) $auxNameMap[$aid] = $aname;
            }

            // ===== 组装数据 =====
            $goods_list = [];
            foreach ($goods as $goods_v) {
                $goods_v['goods_image'] = $goods_v['goods_image'] ? cdnurl($goods_v['goods_image'], true) : '';
                $goods_v['goods_attr'] = '';
                $goods_v['attr_code'] = '';
                $goods_v['attr_name'] = '';
                $goods_v['stock'] = $goods_v['inventory'];
                $goods_v['number'] = '';
                $goods_list[] = $goods_v;

                // 展开辅助属性变体
                if ($auxiliary == 1 && $goods_v['auxiliary'] && isset($attrByGoods[$goods_v['goods_code']])) {
                    foreach ($attrByGoods[$goods_v['goods_code']] as $attr) {
                        $attrName = '';
                        $combId = $attr['attribute_id'];
                        if (isset($combinationMap[$combId])) {
                            $names = [];
                            foreach ($combinationMap[$combId] as $aid) {
                                if (isset($auxNameMap[$aid])) $names[] = $auxNameMap[$aid];
                            }
                            $attrName = implode('/', $names);
                        }
                        $variant = $goods_v;
                        $variant['stock'] = '';
                        $variant['attr_code'] = $attr['attr_code'];
                        $variant['goods_attr'] = $attr['id'];
                        $variant['attr_name'] = $attrName;
                        $variant['number'] = '';
                        $goods_list[] = $variant;
                    }
                }
            }

            // 分页
            $page = isset($data['page']) ? ((int)$data['page']) : 1;
            $limit = isset($data['limit']) ? ((int)$data['limit']) : 10;
            $total = count($goods_list);
            $start = ($page - 1) * $limit;
            $list = array_slice($goods_list, $start, $limit);

            // ===== 填充关联数据（从批量查询的map中取） =====
            foreach ($list as $list_k => $list_v) {
                // 单位
                $uid = $list_v['unit_id'];
                $list[$list_k]['unit'] = isset($unitMap[$uid]) ? $unitMap[$uid] : ['id' => '', 'name' => ''];

                // 商品类别
                $tid = $list_v['goods_type_id'];
                $list[$list_k]['goods_type'] = isset($typeMap[$tid]) ? $typeMap[$tid] : ['id' => '', 'name' => ''];

                // 库位（完整层级名）
                $lid = $list_v['location_id'];
                $list[$list_k]['goods_location'] = isset($locationMap[$lid]) ? $locationMap[$lid] : ['id' => '', 'name' => ''];
            }

            // 批量查询物理库存和锁定数量（仅对基础商品，不包括属性变体）
            $goodsIds = [];
            foreach ($list as $list_v) {
                if (!empty($list_v['id']) && empty($list_v['goods_attr'])) {
                    $goodsIds[] = (int)$list_v['id'];
                }
            }
            $stockMap = [];
            $lockMap = [];
            if (!empty($goodsIds)) {
                $goodsIds = array_unique($goodsIds);
                $stockData = Db::name('kuerp_goods_stock')
                    ->where(['goods_id' => ['in', $goodsIds]])
                    ->field('goods_id, SUM(stock) as total_stock')
                    ->group('goods_id')
                    ->select();
                foreach ($stockData as $sd) {
                    $stockMap[$sd['goods_id']] = (float)$sd['total_stock'];
                }
                $lockData = Db::name('kuerp_goods_lock')
                    ->where(['goods_id' => ['in', $goodsIds], 'status' => '1'])
                    ->field('goods_id, SUM(lock_quantity) as total_lock, SUM(used_quantity) as total_used')
                    ->group('goods_id')
                    ->select();
                foreach ($lockData as $ld) {
                    $lockMap[$ld['goods_id']] = (float)$ld['total_lock'] - (float)$ld['total_used'];
                }
            }

            // 给每行补充锁定和可用库存字段（仅基础商品，属性变体跳过）
            foreach ($list as $list_k => $list_v) {
                $isVariant = !empty($list_v['goods_attr']);
                if ($isVariant) {
                    $list[$list_k]['locked_quantity'] = '';
                    $list[$list_k]['available_stock'] = '';
                } else {
                    $gid = (int)$list_v['id'];
                    $physical = isset($stockMap[$gid]) ? $stockMap[$gid] : 0;
                    $locked = isset($lockMap[$gid]) ? $lockMap[$gid] : 0;
                    $list[$list_k]['stock'] = $physical;
                    $list[$list_k]['locked_quantity'] = round($locked, 2);
                    $list[$list_k]['available_stock'] = round($physical - $locked, 2);
                }
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        $storeList = Db::name('kuerp_stores')->select();
        $this->view->assign("storeList", $storeList);
        $goods_type = Db::name('kuerp_goods_type')->select();
        $this->view->assign("goods_type", $goods_type);
        $goods_location = Db::name('kuerp_goods_location')->select();
        $this->view->assign("goods_location", $goods_location);
        return $this->view->fetch();
    }
    //树形商品分类
    public function gettype(){
        $goods_type = Db::name('kuerp_goods_type')->field('id,supplier_type_name as title,pid')->select();
        $nodeList = [];
        foreach ($goods_type as $k => $v) {
            $nodeList[] = array('id' => $v['id'], 'parent' => $v['pid'] ? $v['pid'] : '#', 'text' => __($v['title']), 'type' => 'menu', 'state' => false);
        }
        $this->success('', null, $nodeList);
    }
    //获取单位价格信息
    public function getUnit(){
        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');
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
        $price_decimal  = $setList['price_decimal']['value'];

        $input = input();
        $whereData = json_decode($input['where'],true);

        foreach ($whereData as $goods_list_k=>$goods_list_v){
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_list_v['goods_id']])
                ->field('manager_warehouse_id,goods_name,market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,id,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life,product_type,location_id')
                ->find();
            $goods_id = isset($row['id'])?$row['id']:0;

            $field = 'market_sell_price';

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
            if(!empty($goods_list_v['goods_attr'])){
                $where['attribute_id'] = $goods_list_v['goods_attr'];
            }
            $stock = Db::name('kuerp_goods_stock')->where($where)->value('stock');
            if(isset($goods_price)){
                unset($goods_price);
            }
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
                    $unit[$unit_k]['purchase'] = round(($unit_v['purchase']*$unit_v['basic_nmber']/(100+$tax)*100),4);
                    $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
                }elseif(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes!=1){
                    $unit[$unit_k]['purchase'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
                    $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*$unit_v['basic_nmber']*(100+$tax)/100),4);
                }else{
                    $unit[$unit_k]['purchase'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
                    $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
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
            $whereData[$goods_list_k]['unit'] = $data;
            $whereData[$goods_list_k]['attr'] = $goods_attr;
            $whereData[$goods_list_k]['goods'] = $row;
        }
        return json_encode($whereData);
    }
    //获取单位价格信息
    public function getSaleUnit($customers_id=''){
        $taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');
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
        $price_decimal  = $setList['price_decimal']['value'];

        $input = input();
        $whereData = json_decode($input['where'],true);

        $field = 'market_sell_price';
        if($customers_id){
            $customers = Db::name('kuerp_customers')->where(['id'=>$customers_id])->find();
            if($customers){
                switch ($customers['customerlevel_id']){
                    case 1:
                        $field = 'market_sell_price';
                        break;
                    case 2:
                        $field = 'wholesale_price';
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
        foreach ($whereData as $goods_list_k=>$goods_list_v){
            $row = Db::name('kuerp_goods')->where(['id'=>$goods_list_v['goods_id']])
                ->field('id,goods_name,market_sell_price,wholesale_price,vip_price,discount,discount2,batch,serial,auxiliary,unit_id,more_unit,more_unit_id,purchase,goods_code,manager_warehouse_id,batch,shelf_life')
                ->find();
            $goods_id = isset($row['id'])?$row['id']:0;

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
            $where['stores_id'] = $row['manager_warehouse_id'];
            if(!empty($goods_list_v['goods_attr'])){
                $where['attribute_id'] = $goods_list_v['goods_attr'];
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
                    $unit[$unit_k]['purchase'] = round(($unit_v['purchase']*$unit_v['basic_nmber']/(100+$tax)*100),4);
                    $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
                }elseif(isset($set['is_checked']) && isset($set['value']) && $set['is_checked']==1 && $taxes!=1){
                    $unit[$unit_k]['purchase'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
                    $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*$unit_v['basic_nmber']*(100+$tax)/100),4);
                }else{
                    $unit[$unit_k]['purchase'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
                    $unit[$unit_k]['tax_included'] = round(($unit_v['purchase']*$unit_v['basic_nmber']),4);
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
            $whereData[$goods_list_k]['unit'] = $data;
            $whereData[$goods_list_k]['attr'] = $goods_attr;
            $whereData[$goods_list_k]['goods'] = $row;
        }
        return json_encode($whereData);
    }
    /**
     * 获取可用序列号
     * @param string goods_code 商品编号
     * @param string stores_id 仓库ID
     * @param string attribute_id 属性id
     * @param string purchase_code 购货单单号
     * @return array
     */
    public function getSerial(){
        $data = input();
        $goods_code    = isset($data['goods_code'])?$data['goods_code']:'';
        $stores_id     = isset($data['stores_id'])?$data['stores_id']:'';
        $attribute_id  = isset($data['attribute_id'])?$data['attribute_id']:'';
        $where = [];
        $where['store_status'] = '1';
        $where['goods_code'] = $goods_code;
        $where['stores_id'] = $stores_id;
        if($attribute_id){
            $where['attribute_id'] = $attribute_id;
        }
        $serial = Db::name('kuerp_goods_serial')->where($where)->field('serial,remark,status')->select();
        return json_encode(['list'=>$serial]);
    }
    public function getMenu($url){
        $auth = Db::name('auth_rule')->where(['name'=>$url])->find();
        if($auth){
            $this->success($auth);
        }else{
            $this->error('查询失败');
        }
    }

    //收款单选择源单
    public function chosesale($customers_id='',$supplyer_id=''){
        $this->assignconfig('customers_id',$customers_id);
        $this->assignconfig('supplyer_id',$supplyer_id);
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $page = isset($data['page'])?$data['page']:1;
            $limit = isset($data['limit'])?$data['limit']:10;
            if(!empty($customers_id)){
                $where = [];
                $where['customers_id'] = $customers_id;
                if(isset($data['filter'])){
                    $filter = (array)json_decode($data['filter'], true);
                    $filter = $filter ? $filter : [];
                    if(!empty($filter['purchase_time'])){
                        $str = explode(' - ',$filter['purchase_time']);
                        $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                    }
                }
                $list = Db::name('kuerp_goods_sales')
                    ->where($where)
                    ->where(['pay_status'=>['in',[0,2]]])
                    ->order('id', 'DESC')
                    ->page($page,$limit)
                    ->select();
                foreach ($list as $list_k=>$list_v){
                    $list[$list_k]['purchase_type'] = 1;
                }
                $count = Db::name('kuerp_goods_sales')
                    ->where($where)
                    ->where(['pay_status'=>['in',[0,2]]])
                    ->count();
                $lists = Db::name('kuerp_goods_sales_exchange')
                    ->where($where)
                    ->where(['retreat_status'=>['in',[0,2]]])
                    ->order('id', 'DESC')
                    ->page($page,$limit)
                    ->select();
                $count += Db::name('kuerp_goods_sales_exchange')
                    ->where($where)
                    ->where(['retreat_status'=>['in',[0,2]]])
                    ->count();
                foreach ($lists as $lists_k=>$lists_v){
                    $lists_v['purchase_type'] = 2;
                    $list[] = $lists_v;
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
                $price_decimal  = $setList['price_decimal']['value'];
                foreach ($list as $list_k=>$list_v){
                    $list[$list_k]['money'] = round(($list_v['money_tax_price']-$list_v['pay_money']),$price_decimal);
                    $list[$list_k]['purchase_type_id'] = $list_v['purchase_type'];
                    if($list_v['purchase_type']==1){
                        $list[$list_k]['money'] = round((($list_v['money_tax_price'])-$list_v['pay_money']),$price_decimal);
                    }else{
                        $list[$list_k]['money'] = -round((($list_v['money_tax_price'])-$list_v['pay_money']),$price_decimal);
                        $list[$list_k]['money_tax_price'] = -$list_v['money_tax_price'];
                        $list[$list_k]['pay_money'] = -$list_v['pay_money'];
                    }
                    $list[$list_k]['purchase_type'] = $list_v['purchase_type']==1?'普通销售':'销售退货';
                    $list[$list_k]['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                }
            }elseif (!empty($supplyer_id)){
                $suppliers = Db::name('kuerp_suppliers')->where(['id'=>$supplyer_id])->find();
                $where = [];
                $where['supplyer_code'] = $suppliers['supplyer_code'];
                if(isset($data['filter'])){
                    $filter = (array)json_decode($data['filter'], true);
                    $filter = $filter ? $filter : [];
                    if(!empty($filter['purchase_time'])){
                        $str = explode(' - ',$filter['purchase_time']);
                        $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                    }
                }
                $list = Db::name('kuerp_goods_purchase')
                    ->where($where)
                    ->where(['pay_status'=>['in',[0,2]]])
                    ->order('id', 'DESC')
                    ->page($page,$limit)
                    ->select();
                foreach ($list as $list_k=>$list_v){
                    $list[$list_k]['purchase_type'] = 1;
                }
                $count = Db::name('kuerp_goods_purchase')
                    ->where($where)
                    ->where(['pay_status'=>['in',[0,2]]])
                    ->count();
                $lists = Db::name('kuerp_goods_exchange')
                    ->where($where)
                    ->where(['retreat_status'=>['in',[0,2]]])
                    ->order('id', 'DESC')
                    ->page($page,$limit)
                    ->select();
                $count += Db::name('kuerp_goods_exchange')
                    ->where($where)
                    ->where(['retreat_status'=>['in',[0,2]]])
                    ->count();
                foreach ($lists as $lists_k=>$lists_v){
                    $lists_v['purchase_type'] = 2;
                    $list[] = $lists_v;
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
                $price_decimal  = $setList['price_decimal']['value'];
                foreach ($list as $list_k=>$list_v){
                    if($list_v['purchase_type']==1){
                        $list[$list_k]['money_tax_price'] = round(($list_v['all_money']+$list_v['tax_money']),$price_decimal);
                        $list[$list_k]['money'] = round((($list_v['all_money']+$list_v['tax_money'])-$list_v['pay_money']),$price_decimal);
                    }else{
                        $list[$list_k]['money_tax_price'] = -round(($list_v['all_money']+$list_v['tax_money']),$price_decimal);
                        $list[$list_k]['money'] = -round((($list_v['all_money']+$list_v['tax_money'])-$list_v['pay_money']),$price_decimal);
                        $list[$list_k]['pay_money'] = -$list_v['pay_money'];
                    }
                    $list[$list_k]['purchase_type_id'] = $list_v['purchase_type'];
                    $list[$list_k]['purchase_type'] = $list_v['purchase_type']==1?'普通购货':'购货退货';
                    $list[$list_k]['purchase_time'] = date('Y-m-d',$list_v['purchase_time']);
                }
            }
            $result = array("total" => $count, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
    public function getSuppliers($supplyer_id){
        if(empty($supplyer_id)){
            return 0;
        }else{
            $give_account = Db::name('kuerp_suppliers')->where(['id'=>$supplyer_id])->value('need_give_account');
            return (float)$give_account;
        }
    }

    public function getGoods($code){
        if(empty($code)){
            $this->error('商品查询失败');
        }else{
            $goods_attr = Db::name('kuerp_goods_attr')->where(['barcode'=>$code])->find();
            if($goods_attr){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$goods_attr['goods_code']])->find();
            }else{
                $goods = Db::name('kuerp_goods')->where(['goods_barcode'=>$code])->find();
            }
            if(empty($goods)){
                $this->error('商品查询失败');
            }else{
                $attr = isset($goods_attr)?$goods_attr['id']:'';
                $this->success(['goods_code'=>$goods['goods_code'],'goods_name'=>$goods['goods_name'],'stores_id'=>$goods['manager_warehouse_id'],'goods_attr'=>$attr,'goods_unit'=>$goods['unit_id']]);
            }
        }
    }

}
