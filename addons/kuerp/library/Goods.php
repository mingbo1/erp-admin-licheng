<?php

namespace addons\kuerp\library;

use app\admin\model\Admin;
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
use think\Session;

class Goods
{
    /**
     * 获取设置
     */
    public static function getSetData(){
        $set= Db::name('kuerp_sets')->field('type,name,value,is_checked,set')->select();
        $setList = [];
        foreach ($set as $set_k=>$set_v){
            if(!empty($set_v['set'])){
                $set_v['set'] = unserialize($set_v['set']);
            }
            $setList[$set_v['name']] = $set_v;
        }
        $controllername = Loader::parseName(request()->controller());
        $controllername = str_replace(".","/",$controllername);
        return ['setList'=>$setList,'nowmenu'=>$controllername];
    }

    /**
     * 获取编号
     * @param int $type   编号类型
     * @return mixed
     */
    public static function getCodes($code,$type,$new=true)
    {
        $set = Db::name('kuerp_prefix')->where(['type'=>$type])->find();
        if(empty($set)){
            return '';
        }else{
            if(!empty($set['dbname']) && !empty($set['field']) && empty($code) && $new && $type<7){
                $code = Db::name($set['dbname'])->order([$set['field']=>'DESC'])->value($set['field']);
            }
            preg_match_all("/\d+/",$code,$num);
            $order_num = implode('',$num[0]);
            if($code && $new){
                try{
                    if($set['year']==1){
                        $order_num = substr($order_num, 4);
                    }
                    if($set['month']==1){
                        $order_num = substr($order_num, 2);
                    }
                    if($set['day']==1){
                        $order_num = substr($order_num, 2);
                    }
                    if($set['hour']==1){
                        $order_num = substr($order_num, 2);
                    }
                    if($set['minute']==1){
                        $order_num = substr($order_num, 2);
                    }
                    if($set['second']==1){
                        $order_num = substr($order_num, 2);
                    }
                }catch (\Exception $e){}
            }
            if($order_num<$set['start']){
                $order_num = $set['start'];
            }else{
                $order_num = (float)$order_num+1;
            }
            $prefix = $set['prefix'];
            if($set['year']==1){
                $prefix = $prefix.date('Y');
            }
            if($set['month']==1){
                $prefix = $prefix.date('m');
            }
            if($set['day']==1){
                $prefix = $prefix.date('d');
            }
            if($set['hour']==1){
                $prefix = $prefix.date('H');
            }
            if($set['minute']==1){
                $prefix = $prefix.date('i');
            }
            if($set['second']==1){
                $prefix = $prefix.date('s');
            }
            $s = '';
            if(strlen($order_num)<$set['strlen']){
                for ($a=1;$a<=$set['strlen']-(strlen($order_num));$a++){
                    $s .= '0';
                }
            }
            $code = $order_num;
            $order_num = $prefix.$s.$order_num;
        }
        if(!empty($set['dbname']) && !empty($set['field'])){
            $res = Db::name($set['dbname'])->where([$set['field']=>$order_num])->value('id');
            if($res){
                return self::getCodes($code,$type,false);
            }
        }
        return $order_num;
    }

    /**
     * 获取编号
     * @param string $code   最大编号，没有时传1
     * @param string $db     数据库表名
     * @param string $field  数据库表中编号字段名
     * @param string $ext    编号前缀
     * @param string $type   编号类型（单据编号为order，其他编号为code）
     * @param int    $strlen 其他编号数字长度
     * @return mixed
     */
    public static function getCode($code,$db,$field,$ext,$type='order',$strlen=3)
    {
        preg_match_all("/\d+/",$code,$num);
        $order_num = implode('',$num[0]);
        if($type=='order'){
            if(strpos($order_num,date('Ymd')) === false  || empty($order_num)){
                $order_num = date('Ymd').'000';
            }
            $order_num = $ext.((float)$order_num+1);
        }else{
            if(empty($order_num)){
                $order_num = '1';
            }else{
                $order_num += 1;
            }
            if(strlen($order_num)<$strlen){
                $s = '';
                for ($a=1;$a<=$strlen-(strlen($order_num));$a++){
                    $s .= '0';
                }
                $order_num = $s.$order_num;
            }
            $order_num = $ext.$order_num;
        }
        $res = Db::name($db)->where([$field=>$order_num])->value('id');
        if($res){
            return self::getCode($order_num,$db,$field,$ext,$type,$strlen);
        }
        return $order_num;
    }

    /**
     * 二维数组根据字段进行排序
     * @params array $array 需要排序的数组
     * @params string $field 排序的字段
     * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
     */
    public static function arraySequence($array, $field, $sort)
    {
        $arrSort = array();
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
        return $array;
    }

    public static function setDataInfo($data=[])
    {
        $admin = Session::get('admin');
        $data['admin_id'] = $admin['id'];
        return $data;
    }

    public static function getLimitField()
    {
        return 'admin_id';
    }

    public static function getAdminId()
    {
        $admin_id = '';
        $admin = Session::get('admin');
        if(isset($admin['admin_id']) && !empty($admin['admin_id'])){
            $admin_id = $admin['admin_id'];
        }elseif (isset($admin['admin_id']) && empty($admin['admin_id'])){
            $admin_id= (new Admin())->where(['id'=>$admin['id']])->value('admin_id');
        }elseif (!isset($admin['admin_id']) && !empty($admin['id'])){
            $admin_id= (new Admin())->where(['id'=>$admin['id']])->value('admin_id');
        }
        return $admin_id;
    }

    /**
     * 获取设置
     * @param string $name   设置名称
     * @return mixed
     */
    public static function getSets($name)
    {
        $set= (new \addons\kuerp\model\Sets())->where(['name'=>$name])->find();
        return $set;
    }

    /**
     * 所有数组的笛卡尔积
     * @param $data
     */
    public static function combineDika(array $data) {
        $cnt = count($data);
        $result = $data[0];
        if($cnt==1){
            $list = [];
            foreach ($result['spec'] as $k=>$item) {
                $temp = [];
                $key = $k;
                $temp[] = $item;
                $list[] = ['id'=>$key,'spec'=>$temp];
            }
            return $list;
        }
        for($i = 1; $i < $cnt; $i++) {
            $result = self::combineArray($result,$data[$i]);
        }
        return $result;
    }

    /**
     * 两个数组的笛卡尔积
     * @param $arr1
     * @param $arr2
     */
    public static function combineArray($arr1,$arr2) {
        $result = array();
        if(!isset($arr1['spec'])){
            $arr1['spec'] = $arr1;
        }
        foreach ($arr1['spec'] as $k1=>$item1) {
            foreach ($arr2['spec'] as $k2=>$item2) {
                $temp = [];
                if(!isset($arr1['id'])){
                    $key = $item1['id'].'&'.$k2;
                }else{
                    $key = $k1.'&'.$k2;
                }
                if(is_array($item1)){
                    foreach ($item1['spec'] as $item1_k=>$item1_v){
                        $temp[] = $item1_v;
                    }
                }else{
                    $temp[] = $item1;
                }
                $temp[] = $item2;
                $result[] = ['id'=>$key,'spec'=>$temp];
            }
        }
        return $result;
    }

    /**
     * 构建层级（树状）数组
     * @param array $array 要进行处理的一维数组，经过该函数处理后，该数组自动转为树状数组
     * @param string $pid_name 父级ID的字段名
     * @param string $child_key_name 子元素键名
     * @return array|bool
     */
    public static function array2tree(&$array, $pid_name = 'pid', $child_key_name = 'children')
    {
        $counter = self::array_children_count($array, $pid_name);
        if (!isset($counter[0]) || $counter[0] == 0) {
            return $array;
        }
        $tree = [];
        while (isset($counter[0]) && $counter[0] > 0) {
            $temp = array_shift($array);
            if (isset($counter[$temp['id']]) && $counter[$temp['id']] > 0) {
                array_push($array, $temp);
            } else {
                if ($temp[$pid_name] == 0) {
                    $tree[] = $temp;
                } else {
                    $array = self::array_child_append($array, $temp[$pid_name], $temp, $child_key_name);
                }
            }
            $counter = self::array_children_count($array, $pid_name);
        }
        return $tree;
    }

    /**
     * 子元素计数器
     * @param array $array
     * @param int $pid
     * @return array
     */
    public static function array_children_count($array, $pid)
    {
        $counter = [];
        foreach ($array as $item) {
            $count = isset($counter[$item[$pid]]) ? $counter[$item[$pid]] : 0;
            $count++;
            $counter[$item[$pid]] = $count;
        }
        return $counter;
    }

    /**
     * 把元素插入到对应的父元素$child_key_name字段
     * @param        $parent
     * @param        $pid
     * @param        $child
     * @param string $child_key_name 子元素键名
     * @return mixed
     */
    public static function array_child_append($parent, $pid, $child, $child_key_name)
    {
        foreach ($parent as &$item) {
            if ($item['id'] == $pid) {
                if (!isset($item[$child_key_name]))
                    $item[$child_key_name] = [];
                $item[$child_key_name][] = $child;
            }
        }
        return $parent;
    }

    //获取单位成本
    public static function getCost_Price($goods_code,$supplyer_code='',$now_nmber='',$purchase_info=[]){
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

        $taxe = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$supplyer_code])->value('vat_rate');
        if($taxe!==0){
            if(empty($taxe)){
                $taxe = Db::name('kuerp_sets')->where(['name'=>'taxes'])->value('value');
            }
        }
        $set= Db::name('kuerp_sets')->where(['name'=>'taxes'])->field('value,is_checked')->find();
        $price_taxes= Db::name('kuerp_sets')->where(['name'=>'price_taxes'])->value('is_checked');

        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->find();
        //期初
        $now_nmbers = (float)$now_nmber;$now_all_money = 0;
        $number = 1;

        $valuation_set= Db::name('kuerp_sets')->where(['name'=>'valuation'])->value('value');//计算方法
        if($valuation_set!=2){
            $valuation_set = 1;
        }
        $now_nmbers2 = 0;

        while ($now_nmber>0){//循环计算并可预先扣除后一天的成本
            $number++;
            foreach ($purchase_info as $k=>$v){
                if($valuation_set==1){
                    if($now_nmber>0){
                        $now_nmbers2 += $v['basic_nmber'];
                        $now_nmber -= $v['basic_nmber'];
                        $now_all_money += $v['all_money'];
                        $purchase_info[$k]['basic_nmber'] = 0;
                        $purchase_info[$k]['all_money'] = 0;
                        if($now_nmber>=$v['basic_nmber']){
                            $purchase_info[$k]['basic_nmber'] = 0;
                            $purchase_info[$k]['all_money'] = 0;
                        }else{
                            $all_unit = round(($now_all_money/$now_nmbers2)*$now_nmber,4);
                            $purchase_info[$k]['basic_nmber'] -= $now_nmber;
                            $purchase_info[$k]['all_money'] -= $all_unit;
                        }
                    }
                }else{
                    if($now_nmber>=$v['basic_nmber']){
                        $now_nmber -= $v['basic_nmber'];
                        $now_all_money += $v['all_money'];
                        $purchase_info[$k]['basic_nmber'] = 0;
                        $purchase_info[$k]['all_money'] = 0;
                    }else{
                        $unit = round($v['all_money']/$v['basic_nmber'],4);
                        $all_unit = round($unit*$now_nmber,4);
                        $now_all_money += $all_unit;
                        $purchase_info[$k]['basic_nmber'] -= $now_nmber;
                        $purchase_info[$k]['all_money'] -= $all_unit;
                        $now_nmber = 0;
                    }
                }
            }
            if($number>50){
                break;
            }
        }
        if($valuation_set==1){
            $now_nmbers = $now_nmbers2;
        }
        if($now_nmbers!=0){
            $cost = ($now_all_money)/($now_nmbers);//成本
        }else{
            $cost = $goods['purchase'];
            if($set['is_checked']==1){
                if($price_taxes==1){
                    $cost = $cost*100/(100+$taxe);
                }
            }
        }
        return ['cost'=>$cost,'purchase_info'=>$purchase_info];
    }

    public static function getGoodsCostPrice($goods_code,$number_decimal,$price_decimal,$checkdate,$enable_time,$valuation_set,$stores_set,$price_taxes,$taxes_set,$stores_ids){
        $sort = 'purchase_time';$order='ASC';
        $where = [];
        $where['pi.goods_code'] = $goods_code;
        $list = Db::name('kuerp_goods_sales_info')->alias('pi')
            ->join('kuerp_goods_sales p','p.purchase_code=pi.purchase_code','left')
            ->join('kuerp_goods_sales_exchange e','e.purchase_code=pi.purchase_code','left')
            ->where($where)
            ->where(['p.examine_status|e.examine_status'=>'1'])
            ->order('pi.'.$sort, $order)
            ->field('pi.*')
            ->select();
        if(!empty($list)){
            $map = [];
            $map['pi.goods_code'] = $goods_code;
            if(!empty($checkdate)){
                $map['pi.purchase_time'] = ['>',$checkdate];
            }
            $lists = Db::name('kuerp_goods_sales_info')->alias('pi')
                ->join('kuerp_goods_sales p','p.purchase_code=pi.purchase_code','left')
                ->join('kuerp_goods_sales_exchange e','e.purchase_code=pi.purchase_code','left')
                ->where($map)->where(['p.examine_status|e.examine_status'=>'1'])
                ->order('pi.'.$sort, $order)
                ->field('pi.*')
                ->select();
            $all_money_all = 0;$profit_all = 0;$net_profit_add = 0;
            $map = [];
            if(!empty($checkdate)){
                $map['purchase_time'] = ['>',$checkdate];
            }
            $goods_info = [];
            $lists = \addons\kuerp\library\Goods::getPrList($lists,$stores_set,$stores_ids,$enable_time,$valuation_set,$goods_info,$price_taxes,$taxes_set,$price_decimal,false);

            foreach ($list as $list_k=>$list_v){
                $status = true;
                foreach ($lists as $lists_k=>$lists_v){
                    if($list_v['id']==$lists_v['id']){
                        $status = false;
                        $unit_price = isset($lists_v['info_goods'][$lists_v['goods_code']])?$lists_v['info_goods'][$lists_v['goods_code']]:0;
                        if(empty($unit_price)){
                            $unit_price = Db::name('kuerp_goods')->where(['goods_code'=>$lists_v['goods_code']])->value('purchase');
                        }
                        $list[$list_k]['pay_money'] = $lists[$lists_k]['pay_money'];
                        $list[$list_k]['sales_cost'] = $lists_v['basic_nmber']*$unit_price;

                        $list[$list_k]['profit'] = $lists[$lists_k]['profit'];
                        $list[$list_k]['profit_rate'] = $lists[$lists_k]['profit_rate'];
                        $list[$list_k]['net_profit'] = $lists[$lists_k]['net_profit'];
                        $list[$list_k]['net_profit_rate'] = $lists[$lists_k]['net_profit_rate'];
                        $list[$list_k]['info_goods'] = $lists_v['info_goods'];
                        $all_money_all += $lists[$lists_k]['all_money'];
                        $profit_all += $lists[$lists_k]['profit'];
                        $net_profit_add += $lists[$lists_k]['net_profit'];
                    }
                }
                if($status){
                    $cost_all = (float)(Db::name('kuerp_goods_sales_profit')
                        ->where(['purchase_code'=>$list_v['purchase_code']])->value('sales_cost'));
                    $list[$list_k]['sales_cost'] = round($cost_all,$price_decimal);
                    $list[$list_k]['profit'] = round($list_v['all_money']-$cost_all,$price_decimal);
                    if($list_v['all_money']>0){
                        $list[$list_k]['profit_rate'] = round($list[$list_k]['profit']/$list_v['all_money']*100,2);
                    }else{
                        $list[$list_k]['profit_rate'] = 0;
                    }
                    $list[$list_k]['net_profit'] = round($list[$list_k]['profit']-$list_v['preferential_money']-$list_v['purchase_money'],$price_decimal);
                    if($list_v['all_money']>0){
                        $list[$list_k]['net_profit_rate'] = round($list[$list_k]['net_profit']/$list_v['all_money']*100,2);
                    }else{
                        $list[$list_k]['net_profit_rate'] = 0;
                    }
                    $list[$list_k]['info_goods'] = [];
                }
            }
            $profit_list = [];$cost_price_list = [];
            foreach ($list as $list_k=>$list_v){
                $profit_list[$list_v['purchase_code']] = $list_v['sales_cost'];
                $cost_price_list[$list_v['purchase_code']] = $list_v['info_goods'];
            }
            unset($list);


            $where = [];
            $where['goods_code'] = $goods_code;
            $lists=[];
            $list = Db::name('kuerp_goods_sales_info')->where($where)->group('goods_code')->field('goods_code')->select();
            foreach ($list as $list_k=>$list_v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$list_v['goods_code']])->field('goods_code,goods_type_id,goods_name')->find();
                if($goods){
                    $c_type = Db::name('kuerp_goods_type')->where(['id'=>$goods['goods_type_id']])->value('supplier_type_name');
                }

                $info = Db::name('kuerp_goods_sales_info')->alias('pi')
                    ->join('kuerp_goods_sales p','p.purchase_code=pi.purchase_code','left')
                    ->join('kuerp_goods_sales_exchange e','e.purchase_code=pi.purchase_code','left')
                    ->where(['pi.goods_code'=>$list_v['goods_code'],'p.examine_status|e.examine_status'=>'1'])
                    ->field('pi.*')
                    ->select();

                $count = 0;
                $basic_nmber = 0;
                $all_money = 0;
                $tax_money = 0;
                $money_tax_price = 0;
                $cost_all = 0;
                foreach ($info as $info_k=>$info_v){
                    if($info_v['purchase_type']==1){
                        $count += 1;
                        $basic_nmber += $info_v['basic_nmber'];
                        $all_money += $info_v['all_money'];
                        $tax_money += $info_v['tax_money'];
                        $money_tax_price += $info_v['money_tax_price'];
                        $cost_all += isset($profit_list[$info_v['purchase_code']])?$profit_list[$info_v['purchase_code']]:0;
                    }else{
                        $basic_nmber -= $info_v['basic_nmber'];
                        $all_money -= $info_v['all_money'];
                        $tax_money -= $info_v['tax_money'];
                        $money_tax_price -= $info_v['money_tax_price'];
                        $cost_price = isset($cost_price_list[$info_v['purchase_code']])?$cost_price_list[$info_v['purchase_code']]:[];
                        $cost_price = isset($cost_price[$info_v['goods_code']])?$cost_price[$info_v['goods_code']]:'';
                        if($cost_price===''){
                            $cost_price = Db::name('kuerp_goods')->where(['goods_code'=>$info_v['goods_code']])->value('purchase');
                        }
                        $cost_all -= $cost_price*$info_v['basic_nmber'];
                    }
                }
                if($basic_nmber>0){
                    $unit_cost = round($cost_all/$basic_nmber,$price_decimal);
                }else{
                    $unit_cost = 0;
                }
                $profit = $all_money-$cost_all;
                if($all_money>0){
                    $profit_rate = round($profit/$all_money*100,2);
                }else{
                    $profit_rate = 0;
                }
                $lists[] = [
                    'c_type_name' => isset($c_type)?$c_type:'',
                    'customer_code' => isset($goods['goods_code'])?$goods['goods_code']:'',
                    'customer_name' => isset($goods['goods_name'])?$goods['goods_name']:'',
                    'count' => $count,
                    'basic_nmber' => round($basic_nmber,$number_decimal),
                    'all_money' => round($all_money,$price_decimal),
                    'tax_money' => round($tax_money,$price_decimal),
                    'money_tax_price' => round($money_tax_price,$price_decimal),
                    'unit_cost' => round($unit_cost,$price_decimal),
                    'cost_all' => round($cost_all,$price_decimal),
                    'profit' => round($profit,$price_decimal),
                    'profit_rate' => round($profit_rate,$price_decimal),
                ];
            }
        }else{
            $lists = [];
        }
        return $lists;
    }

    //获取单位成本 优化
    public static function getCost_Prices($goods,$supplyer_code='',$now_nmber='',$purchase_info=[],$price_taxes='',$valuation_set=1,$set=[]){
        $taxe = Db::name('kuerp_suppliers')->where(['supplyer_code'=>$supplyer_code])->value('vat_rate');
        if($taxe!==0){
            if(empty($taxe)){
                $taxe = Db::name('kuerp_sets')->where(['name'=>'taxes'])->value('value');
            }
        }
        //期初
        $now_nmbers = (float)$now_nmber;$now_all_money = 0;
        $number = 1;
        $now_nmbers2 = 0;

        while ($now_nmber>0){//循环计算并可预先扣除后一天的成本
            $number++;
            foreach ($purchase_info as $k=>$v){
                if($valuation_set==1){
                    if($now_nmber>0){
                        $now_nmbers2 += $v['basic_nmber'];
                        $now_nmber -= $v['basic_nmber'];
                        $now_all_money += $v['all_money'];
                        $purchase_info[$k]['basic_nmber'] = 0;
                        $purchase_info[$k]['all_money'] = 0;
                        if($now_nmber>=$v['basic_nmber']){
                            $purchase_info[$k]['basic_nmber'] = 0;
                            $purchase_info[$k]['all_money'] = 0;
                        }else{
                            $all_unit = round(($now_all_money/$now_nmbers2)*$now_nmber,4);
                            $purchase_info[$k]['basic_nmber'] -= $now_nmber;
                            $purchase_info[$k]['all_money'] -= $all_unit;
                        }
                    }
                }else{
                    if($now_nmber>=$v['basic_nmber']){
                        $now_nmber -= $v['basic_nmber'];
                        $now_all_money += $v['all_money'];
                        $purchase_info[$k]['basic_nmber'] = 0;
                        $purchase_info[$k]['all_money'] = 0;
                    }else{
                        $unit = round($v['all_money']/$v['basic_nmber'],4);
                        $all_unit = round($unit*$now_nmber,4);
                        $now_all_money += $all_unit;
                        $purchase_info[$k]['basic_nmber'] -= $now_nmber;
                        $purchase_info[$k]['all_money'] -= $all_unit;
                        $now_nmber = 0;
                    }
                }
            }
            if($number>50){
                break;
            }
        }
        if($valuation_set==1){
            $now_nmbers = $now_nmbers2;
        }
        if($now_nmbers!=0){
            $cost = ($now_all_money)/($now_nmbers);//成本
        }else{
            $cost = $goods['purchase'];
            if($set['is_checked']==1){
                if($price_taxes==1){
                    $cost = $cost*100/(100+$taxe);
                }
            }
        }
        return ['cost'=>$cost,'purchase_info'=>$purchase_info];
    }

    //获取成本
    public static function getProfit($goods_code,$start_time='',$end_time=''){
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

        $valuation_set= Db::name('kuerp_sets')->where(['name'=>'valuation'])->value('value');//计算方法
        $stores_set= Db::name('kuerp_sets')->where(['name'=>'stores'])->value('is_checked');//是否分仓
        if($valuation_set!=2){
            $valuation_set = 1;
        }
        if($stores_set!=1){
            $stores_set = 0;
        }
        $goods = Db::name('kuerp_goods')->where(['goods_code'=>$goods_code])->find();
        //期初
        switch ($valuation_set){
            case 1:
                //移动平均法
                if($stores_set==1){
                    $sale_money = 0;//销售收入
                    $sales_discount = 0;//销售优惠金额
                    $collection_all = 0;//收款折扣

                    $discount_all = 0;//购货优惠金额
                    $payment_all = 0;//付款折扣

                    $surplus_loss = 0;//盘盈盘亏
                    $stores = Db::name('kuerp_stores')->where(['status'=>'1'])->select();
                    foreach ($stores as $stores_k=>$stores_v){

                        //成本调整
                        $adjustment_money = 0;
                        $adjustment = Db::name('kuerp_adjustment_info')->where(['goods_id'=>$goods['id'],'stores_id'=>$stores_v['id']])->select();
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            $advance_rate = 1;//单位换算进率
                            if($adjustment_v['goods_unit']!=$goods['unit_id']){
                                $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
                                if(!empty($unit) && $unit>0){
                                    $advance_rate = $unit;
                                }
                            }
                            $money = $adjustment_v['money']/$advance_rate;//换算后的调整成本
                            $adjustment_money += $money;
                        }
                        //成本调整结束
                        //采购
                        $where = [];
                        $where['goods_code'] = $goods['goods_code'];
                        $where['stores_id'] = $stores_v['id'];
                        if(!empty($start_time) && !empty($end_time)){
                            $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                        }
                        $basic_nmber = Db::name('kuerp_goods_purchase_info')->where(['purchase_type'=>1])->where($where)->sum('basic_nmber');
                        $discount = Db::name('kuerp_goods_purchase_info')->where(['purchase_type'=>1])->where($where)->sum('preferential_money');
                        $basic_nmber2 = Db::name('kuerp_goods_purchase_info')->where(['purchase_type'=>2])->where($where)->sum('basic_nmber');
                        $discount2 = Db::name('kuerp_goods_purchase_info')->where(['purchase_type'=>2])->where($where)->sum('preferential_money');
                        $basic_nmber -= $basic_nmber2;
                        $discount -= $discount2;

                        //付款折扣
                        $code = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_code')->column('purchase_code');
                        $payment_sale_id = Db::name('kuerp_payment_sale')->where(['purchase_number'=>['in',$code]])->group('collection_id')->column('collection_id');
                        $payment_sale = Db::name('kuerp_payment')->where(['id'=>['in',$payment_sale_id]])->sum('discount');

                        $all_money_s = Db::name('kuerp_goods_sales_info')->where(['purchase_type'=>1])->where($where)->sum('all_money');
                        $discount_s = Db::name('kuerp_goods_sales_info')->group('purchase_code')->where(['purchase_type'=>1])->where($where)->sum('preferential_money');
                        $all_money_s2 = Db::name('kuerp_goods_sales_info')->where(['purchase_type'=>2])->where($where)->sum('all_money');
                        $discount_s2 = Db::name('kuerp_goods_sales_info')->where(['purchase_type'=>2])->where($where)->sum('preferential_money');
                        $all_money_s -= $all_money_s2;
                        $discount_s -= $discount_s2;

                        //收款折扣
                        $code_s = Db::name('kuerp_goods_sales_info')->where($where)->group('purchase_code')->column('purchase_code');
                        $payment_sale_s_id = Db::name('kuerp_collection_sale')->where(['purchase_number'=>['in',$code_s]])->group('collection_id')->column('collection_id');
                        $payment_sale_s = Db::name('kuerp_collection')->where(['id'=>['in',$payment_sale_s_id]])->sum('discount');

                        //盘盈
                        $where = [];
                        $where['goods_id'] = $goods['id'];
                        $where['stores_id'] = $stores_v['id'];
                        if(!empty($start_time) && !empty($end_time)){
                            $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                        }
                        $surplus = Db::name('kuerp_warehousing_info')->where($where)->sum('price');//盘盈
                        $loss = Db::name('kuerp_warehouse_info')->where($where)->sum('price');//盘亏
                        $surplus_loss = $surplus_loss+$surplus-$loss;

                        if($basic_nmber>0){
                            $sale_money += $all_money_s;//销售收入
                            $sales_discount += $discount_s;//销售优惠金额
                            $collection_all += $payment_sale_s;//收款折扣

                            $discount_all += $discount;//购货优惠金额
                            $payment_all += $payment_sale;//付款折扣
                        }
                    }
                    //其他收入
                    $where = [];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $where['examine_status'] = 1;
                    $income = Db::name('kuerp_income')->where($where)->sum('money');
                    $expenditure = Db::name('kuerp_expenditure')->where($where)->sum('money');
                    $other_income = $income-$expenditure;

                    $main_business = $sale_money-$collection_all+$discount_all+$payment_all;
                    $profit = $other_income+$main_business;
                }else{
                    $sale_money = 0;//销售收入
                    $sales_discount = 0;//销售优惠金额
                    $collection_all = 0;//收款折扣

                    $discount_all = 0;//购货优惠金额
                    $payment_all = 0;//付款折扣

                    $surplus_loss = 0;//盘盈盘亏
                    $stores_ids = Db::name('kuerp_stores')->where(['status'=>'1'])->column('id');

                    //成本调整
                    $adjustment_money = 0;
                    $adjustment = Db::name('kuerp_adjustment_info')->where(['goods_id'=>$goods['id'],'stores_id'=>['in',$stores_ids]])->select();
                    foreach ($adjustment as $adjustment_k=>$adjustment_v){
                        $advance_rate = 1;//单位换算进率
                        if($adjustment_v['goods_unit']!=$goods['unit_id']){
                            $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
                            if(!empty($unit) && $unit>0){
                                $advance_rate = $unit;
                            }
                        }
                        $money = $adjustment_v['money']/$advance_rate;//换算后的调整成本
                        $adjustment_money += $money;
                    }
                    //成本调整结束
                    //采购
                    $where = [];
                    $where['goods_code'] = $goods['goods_code'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $basic_nmber = Db::name('kuerp_goods_purchase_info')->where($where)->sum('basic_nmber');
                    $discount = Db::name('kuerp_goods_purchase_info')->where($where)->sum('preferential_money');

                    //付款折扣
                    $code = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_code')->column('purchase_code');
                    $payment_sale_id = Db::name('kuerp_payment_sale')->where(['purchase_number'=>['in',$code]])->group('collection_id')->column('collection_id');
                    $payment_sale = Db::name('kuerp_payment')->where(['id'=>['in',$payment_sale_id]])->sum('discount');

                    $all_money_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('all_money');
                    $discount_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('preferential_money');

                    //收款折扣
                    $code_s = Db::name('kuerp_goods_sales_info')->where($where)->group('purchase_code')->column('purchase_code');
                    $payment_sale_s_id = Db::name('kuerp_collection_sale')->where(['purchase_number'=>['in',$code_s]])->group('collection_id')->column('collection_id');
                    $payment_sale_s = Db::name('kuerp_collection')->where(['id'=>['in',$payment_sale_s_id]])->sum('discount');

                    //盘盈
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $surplus = Db::name('kuerp_warehousing_info')->where($where)->sum('price');//盘盈
                    $loss = Db::name('kuerp_warehouse_info')->where($where)->sum('price');//盘亏
                    $surplus_loss = $surplus_loss+$surplus-$loss;

                    if($basic_nmber>0){
                        $sale_money += $all_money_s;//销售收入
                        $sales_discount += $discount_s;//销售优惠金额
                        $collection_all += $payment_sale_s;//收款折扣

                        $discount_all += $discount;//购货优惠金额
                        $payment_all += $payment_sale;//付款折扣
                    }
                    //其他收入
                    $where = [];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $where['examine_status'] = 1;
                    $income = Db::name('kuerp_income')->where($where)->sum('money');
                    $expenditure = Db::name('kuerp_expenditure')->where($where)->sum('money');
                    $other_income = $income-$expenditure;

                    $main_business = $sale_money-$collection_all+$discount_all+$payment_all;
                    $profit = $other_income+$main_business;
                }
                return ['sale_money'=>$sale_money,'sales_discount'=>$sales_discount,'collection_all'=>$collection_all,
                    'cost_all'=>0,'discount_all'=>$discount_all,'payment_all'=>$payment_all,
                    'surplus_loss'=>$surplus_loss,'main_business'=>$main_business,'other_income'=>$other_income,'profit'=>$profit];
                break;
            case 2:
                //先进先出法
                if($stores_set==1){
                    $sale_money = 0;//销售收入
                    $sales_discount = 0;//销售优惠金额
                    $collection_all = 0;//收款折扣

                    $discount_all = 0;//购货优惠金额
                    $payment_all = 0;//付款折扣

                    $surplus_loss = 0;//盘盈盘亏
                    $stores = Db::name('kuerp_stores')->where(['status'=>'1'])->select();
                    foreach ($stores as $stores_k=>$stores_v){
                        //期初
                        $goods_period = Db::name('kuerp_goods_period')->where(['goods_code'=>$goods['goods_code'],'stores_id'=>$stores_v['id']])->sum('period');
                        $goods_period_money = Db::name('kuerp_goods_period')->where(['goods_code'=>$goods['goods_code'],'stores_id'=>$stores_v['id']])->sum('price');

                        //成本调整
                        $adjustment_money = 0;
                        $adjustment = Db::name('kuerp_adjustment_info')->where(['goods_id'=>$goods['id'],'stores_id'=>$stores_v['id']])->select();
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            $advance_rate = 1;//单位换算进率
                            if($adjustment_v['goods_unit']!=$goods['unit_id']){
                                $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
                                if(!empty($unit) && $unit>0){
                                    $advance_rate = $unit;
                                }
                            }
                            $money = $adjustment_v['money']/$advance_rate;//换算后的调整成本
                            $adjustment_money += $money;
                        }
                        //成本调整结束

                        $where = [];
                        $where['goods_code'] = $goods['goods_code'];
                        $where['stores_id'] = $stores_v['id'];
                        if(!empty($start_time) && !empty($end_time)){
                            $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                        }
                        $basic_nmber_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('basic_nmber');
                        $all_money_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('all_money');
                        $discount_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('preferential_money');

                        //收款折扣
                        $code_s = Db::name('kuerp_goods_sales_info')->where($where)->group('purchase_code')->column('purchase_code');
                        $payment_sale_s_id = Db::name('kuerp_collection_sale')->where(['purchase_number'=>['in',$code_s]])->group('collection_id')->column('collection_id');
                        $payment_sale_s = Db::name('kuerp_collection')->where(['id'=>['in',$payment_sale_s_id]])->sum('discount');

                        $cost = 0;
                        if($basic_nmber_s>$goods_period){
                            $basic_nmber_s -= $goods_period;
                            $cost += $goods_period_money;
                        }else{
                            $basic_nmber_s = 0;
                            if(($goods_period*$basic_nmber_s)!=0){
                                $cost += $goods_period_money/$goods_period*$basic_nmber_s;
                            }else{
                                $cost += 0;
                            }
                        }
                        //采购
                        $basic_nmber_all = $basic_nmber_s;
                        Db::name('kuerp_goods_purchase_info')
                            ->where($where)
                            ->chunk(100, function($orderList) use ($basic_nmber_all,$cost){
                                foreach ($orderList as $order) {
                                    if($basic_nmber_all>0){
                                        if($order['basic_nmber']>$basic_nmber_all){
                                            $now_nmber = $order['basic_nmber']-$basic_nmber_all;
                                            $now_money = $now_nmber*$order['unit_price'];
                                            $basic_nmber_all -= $now_nmber;
                                            $cost += $now_money;
                                        }else{
                                            $cost += $order['all_money'];
                                            $basic_nmber_all -= $order['basic_nmber'];
                                        }
                                    }
                                }
                            });

                        $discount = Db::name('kuerp_goods_purchase_info')->where($where)->sum('preferential_money');

                        //付款折扣
                        $code = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_code')->column('purchase_code');
                        $payment_sale_id = Db::name('kuerp_payment_sale')->where(['purchase_number'=>['in',$code]])->group('collection_id')->column('collection_id');
                        $payment_sale = Db::name('kuerp_payment')->where(['id'=>['in',$payment_sale_id]])->sum('discount');


                        //盘盈
                        $where = [];
                        $where['goods_id'] = $goods['id'];
                        $where['stores_id'] = $stores_v['id'];
                        if(!empty($start_time) && !empty($end_time)){
                            $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                        }
                        $surplus = Db::name('kuerp_warehousing_info')->where($where)->sum('price');//盘盈
                        $loss = Db::name('kuerp_warehouse_info')->where($where)->sum('price');//盘亏
                        $surplus_loss = $surplus_loss+$surplus-$loss;

                        $sale_money += $all_money_s;//销售收入
                        $sales_discount += $discount_s;//销售优惠金额
                        $collection_all += $payment_sale_s;//收款折扣

                        $discount_all += $discount;//购货优惠金额
                        $payment_all += $payment_sale;//付款折扣
                    }
                    //其他收入
                    $where = [];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $where['examine_status'] = 1;
                    $income = Db::name('kuerp_income')->where($where)->sum('money');
                    $expenditure = Db::name('kuerp_expenditure')->where($where)->sum('money');
                    $other_income = $income-$expenditure;

                    $main_business = $sale_money-$collection_all+$discount_all+$payment_all;
                    $profit = $other_income+$main_business;
                }else{
                    $sale_money = 0;//销售收入
                    $sales_discount = 0;//销售优惠金额
                    $collection_all = 0;//收款折扣

                    $cost_all = 0;//销售成本
                    $discount_all = 0;//购货优惠金额
                    $payment_all = 0;//付款折扣

                    $surplus_loss = 0;//盘盈盘亏
                    $stores_ids = Db::name('kuerp_stores')->where(['status'=>'1'])->column('id');
                    //期初
                    $goods_period = Db::name('kuerp_goods_period')->where(['goods_code'=>$goods['goods_code'],'stores_id'=>['in',$stores_ids]])->sum('period');
                    $goods_period_money = Db::name('kuerp_goods_period')->where(['goods_code'=>$goods['goods_code'],'stores_id'=>['in',$stores_ids]])->sum('price');

                    //成本调整
                    $adjustment_money = 0;
                    $adjustment = Db::name('kuerp_adjustment_info')->where(['goods_id'=>$goods['id'],'stores_id'=>['in',$stores_ids]])->select();
                    foreach ($adjustment as $adjustment_k=>$adjustment_v){
                        $advance_rate = 1;//单位换算进率
                        if($adjustment_v['goods_unit']!=$goods['unit_id']){
                            $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
                            if(!empty($unit) && $unit>0){
                                $advance_rate = $unit;
                            }
                        }
                        $money = $adjustment_v['money']/$advance_rate;//换算后的调整成本
                        $adjustment_money += $money;
                    }
                    //成本调整结束

                    $where = [];
                    $where['goods_code'] = $goods['goods_code'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $basic_nmber_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('basic_nmber');
                    $all_money_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('all_money');
                    $discount_s = Db::name('kuerp_goods_sales_info')->where($where)->sum('preferential_money');

                    //收款折扣
                    $code_s = Db::name('kuerp_goods_sales_info')->where($where)->group('purchase_code')->column('purchase_code');
                    $payment_sale_s_id = Db::name('kuerp_collection_sale')->where(['purchase_number'=>['in',$code_s]])->group('collection_id')->column('collection_id');
                    $payment_sale_s = Db::name('kuerp_collection')->where(['id'=>['in',$payment_sale_s_id]])->sum('discount');

                    $cost = 0;
                    if($basic_nmber_s>$goods_period){
                        $basic_nmber_s -= $goods_period;
                        $cost += $goods_period_money;
                    }else{
                        $basic_nmber_s = 0;
                        if($goods_period>0){
                            $cost += $goods_period_money/$goods_period*$basic_nmber_s;
                        }else{
                            $cost += 0;
                        }
                    }
                    //采购
                    $basic_nmber_all = $basic_nmber_s;
                    Db::name('kuerp_goods_purchase_info')
                        ->where($where)
                        ->chunk(1, function($orderList) use (&$basic_nmber_all,&$cost){
                            foreach ($orderList as $order) {
                                if($basic_nmber_all>0){
                                    if($order['basic_nmber']>$basic_nmber_all){
                                        $now_nmber = $order['basic_nmber']-$basic_nmber_all;
                                        $now_money = $now_nmber*$order['unit_price'];
                                        $basic_nmber_all -= $now_nmber;
                                        $cost += $now_money;
                                    }else{
                                        $cost += $order['all_money'];
                                        $basic_nmber_all -= $order['basic_nmber'];
                                    }
                                }
                            }
                        });
                    $discount = Db::name('kuerp_goods_purchase_info')->where($where)->sum('preferential_money');

                    //付款折扣
                    $code = Db::name('kuerp_goods_purchase_info')->where($where)->group('purchase_code')->column('purchase_code');
                    $payment_sale_id = Db::name('kuerp_payment_sale')->where(['purchase_number'=>['in',$code]])->group('collection_id')->column('collection_id');
                    $payment_sale = Db::name('kuerp_payment')->where(['id'=>['in',$payment_sale_id]])->sum('discount');


                    //盘盈
                    $where = [];
                    $where['goods_id'] = $goods['id'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $surplus = Db::name('kuerp_warehousing_info')->where($where)->sum('price');//盘盈
                    $loss = Db::name('kuerp_warehouse_info')->where($where)->sum('price');//盘亏
                    $surplus_loss = $surplus_loss+$surplus-$loss;

                    $sale_money += $all_money_s;//销售收入
                    $sales_discount += $discount_s;//销售优惠金额
                    $collection_all += $payment_sale_s;//收款折扣

                    $discount_all += $discount;//购货优惠金额
                    $payment_all += $payment_sale;//付款折扣
                    //其他收入
                    $where = [];
                    if(!empty($start_time) && !empty($end_time)){
                        $where['purchase_time'] = ["BETWEEN", [$start_time,$end_time]];
                    }
                    $where['examine_status'] = 1;
                    $income = Db::name('kuerp_income')->where($where)->sum('money');
                    $expenditure = Db::name('kuerp_expenditure')->where($where)->sum('money');
                    $other_income = $income-$expenditure;

                    $main_business = $sale_money-$collection_all+$discount_all+$payment_all;
                    $profit = $other_income+$main_business;
                }
                return ['sale_money'=>$sale_money,'sales_discount'=>$sales_discount,'collection_all'=>$collection_all,
                    'cost_all'=>0,'discount_all'=>$discount_all,'payment_all'=>$payment_all,
                    'surplus_loss'=>$surplus_loss,'main_business'=>$main_business,'other_income'=>$other_income,'profit'=>$profit];
                break;
            default:
                return ['sale_money'=>'','sales_discount'=>'','collection_all'=>'',
                    'cost_all'=>'','discount_all'=>'','payment_all'=>'',
                    'surplus_loss'=>'','main_business'=>'','other_income'=>'','profit'=>''];
                break;
        }
    }

    public static function getLimitTable()
    {
        $limitTable   = [
            'erp_accounts','erp_accounts_log','erp_supplyertype','erp_suppliers','erp_suppliers_user',
            'erp_stores','erp_staff_member','erp_address','erp_settlementtype',
            'erp_unit','erp_unit_conversion','erp_expend_type','erp_income_type',
            'erp_customers','erp_customertype','erp_customer_goodscoding','erp_customerlevel',
            'erp_auxiliary','erp_goods_stock','erp_aux_combination','erp_goods','erp_goods_type','erp_goods_price','erp_goods_price','erp_goods_attr','erp_goods_serial','erp_goods_stock_warning',
            'erp_goods_purchase_order','erp_goods_purchase_order_info','erp_goods_purchase','erp_goods_purchase_info','erp_goods_purchase_summary',
            'erp_goods_exchange','erp_goods_exchange_info','erp_goods_batch','erp_goods_batch_info','erp_goods_batch_exchange_info',
            'erp_goods_sales_order','erp_goods_sales_order_info','erp_goods_sales','erp_goods_sales_info','erp_goods_sales_summary',
            'erp_goods_sales_exchange','erp_goods_sales_exchange_info','erp_goods_batch_sale_info','erp_goods_batch_salee_info',
            'erp_invoicing','erp_payment_log',
            'erp_sales_invoicing','erp_sales_payment_log',
            'erp_payment_account','erp_sets'
        ];
        return $limitTable;
    }

    public static function addGoodsCate($data,$pid=0)
    {
        foreach ($data as $data_k=>$data_v){
            unset($data[$data_k]);
            if(!empty($data_v)){
                if($pid>0){
                    $path = Db::name('kuerp_goods_type')->where(['id'=>$pid])->value('path');
                }else{
                    $path = ',0,';
                }
                $id = Db::name('kuerp_goods_type')->where(['supplier_type_name'=>$data_v])->value('id');
                if(empty($id)){
                    $pid = Db::name('kuerp_goods_type')->insertGetId([
                        'supplier_type_name'=>$data_v,
                        'pid'=>$pid,
                        'path'=>$path,
                        'createtime'=>time(),
                        'updatetime'=>time(),
                    ]);
                    \addons\kuerp\library\Goods::addGoodsCate($data,$pid);
                }else{
                    \addons\kuerp\library\Goods::addGoodsCate($data,$id);
                }
            }
        }
    }

    /**
     * @params array $lists 销售数据
     * @params int  $stores_set 是否分仓
     * @params string $stores_ids 仓库id数组
     * @params string $enable_time 启用时间
     * @params int $valuation_set 成本计算方法
     * @params array $goods_info 商品数组，默认空
     * @params int $price_taxes 是否含税
     * @params string $taxes_set 税率数组
     * @params string $price_decimal 价格小数位数
     */
    public static function getPrList($lists,$stores_set,$stores_ids,$enable_time,$valuation_set,$goods_info,$price_taxes,$taxes_set,$price_decimal,$ad_status=true){
        foreach ($lists as $list_k=>$list_v){
            $cost_all = 0;$pay_money = 0;
            $where = [];
            $where['p.purchase_code|e.purchase_code'] = $list_v['purchase_code'];
            if(isset($list_v['goods_code'])){
                $where['pi.goods_code'] = $list_v['goods_code'];
            }
            $info = Db::name('kuerp_goods_sales_info')->alias('pi')
                ->join('kuerp_goods_sales p','p.purchase_code=pi.purchase_code','left')
                ->join('kuerp_goods_sales_exchange e','e.purchase_code=pi.purchase_code','left')
                ->where($where)
                ->where(['p.examine_status|e.examine_status'=>'1'])
                ->field('pi.pay_money,pi.basic_nmber,pi.goods_code,pi.stores_id,pi.purchase_time')->select();
            $info_goods = [];
            foreach ($info as $info_k=>$info_v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$info_v['goods_code']])->find();
                if(!isset($goods_info[$info_v['goods_code']])){
                    $purchase_info = [];
                    $where = [];
                    if($stores_set==0){
                        $where['stores_id'] = ['in',$stores_ids];
                    }else{
                        $where['stores_id'] = $info_v['stores_id'];
                    }
                    $where['goods_code'] = $info_v['goods_code'];
                    if(empty($checkdate)){
                        $start_time = strtotime($enable_time);
                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $thiswhere = [];
                            foreach ($where as $where_k=>$where_v){
                                $thiswhere['pi.'.$where_k] = $where_v;
                            }
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($thiswhere)
                                ->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.all_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();


                            $maps = $where;
                            if(isset($where['purchase_time'])){
                                $maps['createtime'] = $where['purchase_time'];
                                unset($maps['purchase_time']);
                            }
                            $goods_period = Db::name('kuerp_goods_period')->where($maps)
                                ->group('createtime')
                                ->order(['createtime'=>'ASC'])
                                ->field('createtime as purchase_time,sum(price) as all_money,sum(stock) as basic_nmber')
                                ->select();
                            if(!empty($goods_period)){
                                foreach ($goods_period as $goods_period_k=>$goods_period_v){
                                    $goods_period_v['purchase_time'] = strtotime(date('Y-m-d',$goods_period_v['purchase_time']));
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($goods_period_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$goods_period_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$goods_period_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $goods_period_v;
                                    }
                                }
                            }


                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }else{
                            $thiswhere = [];
                            foreach ($where as $where_k=>$where_v){
                                $thiswhere['pi.'.$where_k] = $where_v;
                            }
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($thiswhere)
                                ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();


                            $maps = $where;
                            if(isset($where['purchase_time'])){
                                $maps['createtime'] = $where['purchase_time'];
                                unset($maps['purchase_time']);
                            }
                            $goods_period = Db::name('kuerp_goods_period')->where($maps)
                                ->group('createtime')
                                ->order(['createtime'=>'ASC'])
                                ->field('createtime as purchase_time,sum(price) as all_money,sum(stock) as basic_nmber')
                                ->select();
                            if(!empty($goods_period)){
                                foreach ($goods_period as $goods_period_k=>$goods_period_v){
                                    $goods_period_v['purchase_time'] = strtotime(date('Y-m-d',$goods_period_v['purchase_time']));
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($goods_period_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$goods_period_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$goods_period_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $goods_period_v;
                                    }
                                }
                            }


                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }
                    }else{
                        $checkout_id = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('id');
                        $purchase_more = Db::name('kuerp_checkout_info')->where([
                            'checkout_id'=>$checkout_id,
                            'goods_code'=>$info_v['goods_code'],
                        ])->find();
                        $purchase_infos = Db::name('kuerp_goods_purchase')->where(['id'=>$purchase_more['purchase_id'],'examine_status'=>'1'])->find();

                        $start_time = $checkdate;
                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $thiswhere = [];
                            foreach ($where as $where_k=>$where_v){
                                $thiswhere['pi.'.$where_k] = $where_v;
                            }
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($thiswhere)
                                ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();
                            if(!empty($purchase_more)){
                                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                    if($purchase_info_v['purchase_time']==$purchase_more['purchase_time']){
                                        $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$purchase_more['av_basic_nmber'];
                                        $purchase_info[$purchase_info_k]['all_money'] = (float)$purchase_more['av_all_money'];
                                    }
                                }
                            }

                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }else{
                            if(empty($purchase_more)){
                                $thiswhere = [];
                                foreach ($where as $where_k=>$where_v){
                                    $thiswhere['pi.'.$where_k] = $where_v;
                                }
                                $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                    ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                    ->where(['p.examine_status'=>'1'])
                                    ->where($thiswhere)
                                    ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                    ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();
                            }else{
                                $where['purchase_code'] = ['>=',$purchase_infos['purchase_code']];
                                $thiswhere = [];
                                foreach ($where as $where_k=>$where_v){
                                    $thiswhere['pi.'.$where_k] = $where_v;
                                }
                                $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                    ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                    ->where(['p.examine_status'=>'1'])
                                    ->where($thiswhere)
                                    ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                    ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();
                                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                    if($purchase_info_v['id']==$purchase_more['purchase_id']){
                                        $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$purchase_more['basic_nmber'];
                                        $purchase_info[$purchase_info_k]['all_money'] = (float)$purchase_more['all_money'];
                                    }
                                }
                            }

                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }
                    }

                    $where = [];
                    $where['goods_code'] = $info_v['goods_code'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($wheretime)){
                        $where['createtime'] = ['between',[$wheretime['start_time'],time()]];
                    }
                    $period = Db::name('kuerp_goods_period')->where($where)->field('period as basic_nmber,price as all_money,createtime as purchase_time')->find();
                    if(!empty($period)){
                        $period['purchase_time'] = strtotime(date('Y-m-d',$period['purchase_time']));
                        if($valuation_set==1){
                            $status = true;
                            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                if($purchase_info_v['purchase_time']==$period['purchase_time']){
                                    $status = false;
                                    $purchase_info[$purchase_info_k]['basic_nmber'] += $period['basic_nmber'];
                                    $purchase_info[$purchase_info_k]['all_money'] += $period['all_money'];
                                }
                            }
                            if($status){
                                $purchase_info[] = $period;
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }else{
                            $purchase_infos = [];
                            $purchase_infos[] = $period;
                            foreach ($purchase_info as $k=>$v){
                                $purchase_infos[] = $v;
                            }
                            $purchase_info = $purchase_infos;
                        }
                    }

                    if($goods){
                        $where = [];
                        $where['goods_id'] = $goods['id'];
                        $where['stores_id'] = ['in',$stores_ids];
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        $adjustment_money = 0;
                        $adjustment = Db::name('kuerp_adjustment_info')->where($where)->select();
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            $advance_rate = 1;
                            if($adjustment_v['goods_unit']!=$goods['unit_id']){
                                $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
                                if(!empty($unit) && $unit>0){
                                    $advance_rate = $unit;
                                }
                            }
                            $money = $adjustment_v['money']/$advance_rate;
                            $adjustment[$adjustment_k]['purchase_time'] = strtotime(date('Y-m-d',$adjustment_v['purchase_time']));
                            $adjustment[$adjustment_k]['all_money'] = $adjustment_v['money'];
                        }
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                if(time()>$adjustment_v['purchase_time']){
                                    $purchase_info[$purchase_info_k]['all_money'] += $adjustment_v['all_money'];
                                }
                            }
                        }
                    }
                }else{
                    $purchase_info = $goods_info[$info_v['goods_code']];
                }

                $cost = \addons\kuerp\library\Goods::getCost_Prices($goods,'',$info_v['basic_nmber'],$purchase_info,$price_taxes,$valuation_set,$taxes_set);
                if(isset($cost['purchase_info'])){
                    $goods_info[$info_v['goods_code']] = $cost['purchase_info'];
                    $purchase_info = $goods_info[$info_v['goods_code']];
                    $cost = $cost['cost'];
                }
                if($ad_status){
                    $cost_all += $cost*$info_v['basic_nmber'];
                    $pay_money += $info_v['pay_money'];
                }else{
                    $cost_all = $cost*$info_v['basic_nmber'];
                    $pay_money = $info_v['pay_money'];
                }
                $info_goods[$info_v['goods_code']] = $cost;
            }

            $lists[$list_k]['info_goods'] = $info_goods;
            $lists[$list_k]['pay_money'] = $pay_money;
            if($list_v['purchase_type']==2){
                $cost_all = -$cost_all;
            }
            $lists[$list_k]['sales_cost'] = round($cost_all,$price_decimal);
            $lists[$list_k]['profit'] = round($list_v['all_money']-$cost_all,$price_decimal);
            if($list_v['all_money']>0){
                $lists[$list_k]['profit_rate'] = round($lists[$list_k]['profit']/$list_v['all_money']*100,2);
            }else{
                $lists[$list_k]['profit_rate'] = 0;
            }
            $lists[$list_k]['net_profit'] = round($lists[$list_k]['profit']-$list_v['preferential_money']-$list_v['purchase_money'],$price_decimal);
            if($list_v['all_money']>0){
                $lists[$list_k]['net_profit_rate'] = round($lists[$list_k]['net_profit']/$list_v['all_money']*100,2);
            }else{
                $lists[$list_k]['net_profit_rate'] = 0;
            }
        }
        foreach ($lists as $lists_k=>$lists_v){
            if(empty($lists_v['info_goods']) && $lists_v['purchase_type']==2){
                $sale_code = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$lists_v['purchase_code']])->value('purchase_id');
                if($sale_code){
                    foreach ($lists as $lists_k2=>$lists_v2){
                        if($sale_code==$lists_v2['purchase_code']){
                            $lists[$lists_k]['info_goods'] = $lists_v2['info_goods'];
                        }
                    }
                }
                $info = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$lists_v['purchase_code']])->select();
                $cost_all = 0;
                foreach ($info as $info_k=>$info_v){
                    $unit_price = isset($lists[$lists_k]['info_goods'][$info_v['goods_code']])?$lists[$lists_k]['info_goods'][$info_v['goods_code']]:$info_v['unit_price'];
                    $cost_all += ($info_v['retreat_number']*$unit_price);
                }
                $cost_all = -$cost_all;
                $lists[$lists_k]['sales_cost'] = round($cost_all,$price_decimal);

                $lists[$lists_k]['profit'] = round($lists_v['all_money']-$cost_all,$price_decimal);
                if($lists_v['all_money']!=0){
                    $lists[$lists_k]['profit_rate'] = round($lists[$lists_k]['profit']/$lists_v['all_money']*100,2);
                }else{
                    $lists[$lists_k]['profit_rate'] = 0;
                }
                $lists[$lists_k]['net_profit'] = round($lists[$lists_k]['profit']-$lists_v['preferential_money']-$lists_v['purchase_money'],$price_decimal);
                if($lists_v['all_money']!=0){
                    $lists[$lists_k]['net_profit_rate'] = round($lists[$lists_k]['net_profit']/$lists_v['all_money']*100,2);
                }else{
                    $lists[$lists_k]['net_profit_rate'] = 0;
                }
            }
        }
        return $lists;
    }

    public static function getPrListOne($lists,$stores_set,$stores_ids,$enable_time,$valuation_set,$goods_info,$price_taxes,$taxes_set,$price_decimal,$ad_status=true){
        foreach ($lists as $list_k=>$list_v){
            $cost_all = 0;$pay_money = 0;
            $info = Db::name('kuerp_goods_sales_info')->alias('pi')
                ->join('kuerp_goods_sales p','p.purchase_code=pi.purchase_code','left')
                ->join('kuerp_goods_sales_exchange e','e.purchase_code=pi.purchase_code','left')
                ->where(['p.examine_status|e.examine_status'=>'1'])
                ->where(['p.purchase_code|e.purchase_code'=>$list_v['purchase_code'],'pi.id'=>$list_v['id']])
                ->field('pi.pay_money,pi.basic_nmber,pi.goods_code,pi.stores_id,pi.purchase_time')->select();
            $info_goods = [];
            foreach ($info as $info_k=>$info_v){
                $goods = Db::name('kuerp_goods')->where(['goods_code'=>$info_v['goods_code']])->find();
                if(!isset($goods_info[$info_v['goods_code']])){
                    $purchase_info = [];
                    $where = [];
                    if($stores_set==0){
                        $where['stores_id'] = ['in',$stores_ids];
                    }else{
                        $where['stores_id'] = $info_v['stores_id'];
                    }
                    $where['goods_code'] = $info_v['goods_code'];
                    if(empty($checkdate)){
                        $start_time = strtotime($enable_time);
                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $thiswhere = [];
                            foreach ($where as $where_k=>$where_v){
                                $thiswhere['pi.'.$where_k] = $where_v;
                            }
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($thiswhere)
                                ->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.all_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();

                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }else{
                            $thiswhere = [];
                            foreach ($where as $where_k=>$where_v){
                                $thiswhere['pi.'.$where_k] = $where_v;
                            }
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($thiswhere)
                                ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();

                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }
                    }else{
                        $checkout_id = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('id');
                        $purchase_more = Db::name('kuerp_checkout_info')->where([
                            'checkout_id'=>$checkout_id,
                            'goods_code'=>$info_v['goods_code'],
                        ])->find();
                        $purchase_infos = Db::name('kuerp_goods_purchase')->where(['id'=>$purchase_more['purchase_id'],'examine_status'=>'1'])->find();

                        $start_time = $checkdate;
                        $end_time = strtotime(date('Y-m-d',$info_v['purchase_time']).' 23:59:59');
                        $wheretime['start_time'] = $start_time;
                        $wheretime['end_time'] = $end_time;
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        if($valuation_set==1){
                            $thiswhere = [];
                            foreach ($where as $where_k=>$where_v){
                                $thiswhere['pi.'.$where_k] = $where_v;
                            }
                            $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($thiswhere)
                                ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();
                            if(!empty($purchase_more)){
                                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                    if($purchase_info_v['purchase_time']==$purchase_more['purchase_time']){
                                        $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$purchase_more['av_basic_nmber'];
                                        $purchase_info[$purchase_info_k]['all_money'] = (float)$purchase_more['av_all_money'];
                                    }
                                }
                            }

                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }else{
                            if(empty($purchase_more)){
                                $thiswhere = [];
                                foreach ($where as $where_k=>$where_v){
                                    $thiswhere['pi.'.$where_k] = $where_v;
                                }
                                $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                    ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                    ->where(['p.examine_status'=>'1'])
                                    ->where($thiswhere)
                                    ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                    ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();
                            }else{
                                $where['purchase_code'] = ['>=',$purchase_infos['purchase_code']];
                                $thiswhere = [];
                                foreach ($where as $where_k=>$where_v){
                                    $thiswhere['pi.'.$where_k] = $where_v;
                                }
                                $purchase_info = Db::name('kuerp_goods_purchase_info')->alias('pi')
                                    ->join('kuerp_goods_purchase p','p.purchase_code=pi.purchase_code')
                                    ->where(['p.examine_status'=>'1'])
                                    ->where($thiswhere)
                                    ->field('pi.purchase_time,pi.basic_nmber,pi.all_money')
                                    ->order(['pi.purchase_time'=>'ASC','pi.purchase_code'=>'ASC'])->select();
                                foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                    if($purchase_info_v['id']==$purchase_more['purchase_id']){
                                        $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$purchase_more['basic_nmber'];
                                        $purchase_info[$purchase_info_k]['all_money'] = (float)$purchase_more['all_money'];
                                    }
                                }
                            }

                            $map = $thiswhere;unset($map['pi.goods_code']);$map['pi.goods_id'] = $goods['id'];
                            $order_info = Db::name('kuerp_warehousing_info')->alias('pi')
                                ->join('kuerp_warehousing p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_nmber) as basic_nmber,sum(pi.price) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            $order_info = Db::name('kuerp_assemble_info')->alias('pi')
                                ->join('kuerp_assemble p','p.purchase_code=pi.purchase_code')
                                ->where(['p.examine_status'=>'1'])
                                ->where($map)->group('pi.purchase_time')
                                ->field('pi.purchase_time,sum(pi.basic_number) as basic_nmber,sum(pi.cost_money) as all_money')
                                ->order(['pi.purchase_time'=>'ASC'])->select();
                            if(!empty($order_info)){
                                foreach ($order_info as $order_info_k=>$order_info_v){
                                    $add_status = true;
                                    foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                        if($order_info_v['purchase_time']==$purchase_info_v['purchase_time']){
                                            $add_status = false;
                                            $purchase_info[$purchase_info_k]['basic_nmber'] = (float)$order_info_v['basic_nmber'];
                                            $purchase_info[$purchase_info_k]['all_money'] = (float)$order_info_v['all_money'];
                                        }
                                    }
                                    if($add_status){
                                        $purchase_info[] = $order_info_v;
                                    }
                                }
                            }

                            if(!empty($purchase_info)){
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }
                    }

                    $where = [];
                    $where['goods_code'] = $info_v['goods_code'];
                    $where['stores_id'] = ['in',$stores_ids];
                    if(!empty($wheretime)){
                        $where['createtime'] = ['between',[$wheretime['start_time'],time()]];
                    }
                    $period = Db::name('kuerp_goods_period')->where($where)->field('period as basic_nmber,price as all_money,createtime as purchase_time')->find();
                    if(!empty($period)){
                        $period['purchase_time'] = strtotime(date('Y-m-d',$period['purchase_time']));
                        if($valuation_set==1){
                            $status = true;
                            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                if($purchase_info_v['purchase_time']==$period['purchase_time']){
                                    $status = false;
                                    $purchase_info[$purchase_info_k]['basic_nmber'] += $period['basic_nmber'];
                                    $purchase_info[$purchase_info_k]['all_money'] += $period['all_money'];
                                }
                            }
                            if($status){
                                $purchase_info[] = $period;
                                $purchase_info = \addons\kuerp\library\Goods::arraySequence($purchase_info,'purchase_time','SORT_ASC');
                            }
                        }else{
                            $purchase_infos = [];
                            $purchase_infos[] = $period;
                            foreach ($purchase_info as $k=>$v){
                                $purchase_infos[] = $v;
                            }
                            $purchase_info = $purchase_infos;
                        }
                    }

                    if($goods){
                        $where = [];
                        $where['goods_id'] = $goods['id'];
                        $where['stores_id'] = ['in',$stores_ids];
                        if(!empty($wheretime)){
                            $where['purchase_time'] = ['between',[$wheretime['start_time'],time()]];
                        }
                        $adjustment_money = 0;
                        $adjustment = Db::name('kuerp_adjustment_info')->where($where)->select();
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            $advance_rate = 1;
                            if($adjustment_v['goods_unit']!=$goods['unit_id']){
                                $unit = Db::name('kuerp_unit_conversion')->where(['main_unit'=>$goods['unit_id'],'vice_unit'=>$adjustment_v['goods_unit']])->value('advance_rate');
                                if(!empty($unit) && $unit>0){
                                    $advance_rate = $unit;
                                }
                            }
                            $money = $adjustment_v['money']/$advance_rate;
                            $adjustment[$adjustment_k]['purchase_time'] = strtotime(date('Y-m-d',$adjustment_v['purchase_time']));
                            $adjustment[$adjustment_k]['all_money'] = $adjustment_v['money'];
                        }
                        foreach ($adjustment as $adjustment_k=>$adjustment_v){
                            foreach ($purchase_info as $purchase_info_k=>$purchase_info_v){
                                if($purchase_info_v['purchase_time']==$adjustment_v['purchase_time']){
                                    $purchase_info[$purchase_info_k]['all_money'] += $adjustment_v['all_money'];
                                }
                            }
                        }
                    }
                }else{
                    $purchase_info = $goods_info[$info_v['goods_code']];
                }

                $cost = \addons\kuerp\library\Goods::getCost_Prices($goods,'',$info_v['basic_nmber'],$purchase_info,$price_taxes,$valuation_set,$taxes_set);
                if(isset($cost['purchase_info'])){
                    $goods_info[$info_v['goods_code']] = $cost['purchase_info'];
                    $purchase_info = $goods_info[$info_v['goods_code']];
                    $cost = $cost['cost'];
                }
                if($ad_status){
                    $cost_all += $cost*$info_v['basic_nmber'];
                    $pay_money += $info_v['pay_money'];
                }else{
                    $cost_all = $cost*$info_v['basic_nmber'];
                    $pay_money = $info_v['pay_money'];
                }
                $info_goods[$info_v['goods_code']] = $cost;
            }
            $lists[$list_k]['info_goods'] = $info_goods;
            $lists[$list_k]['pay_money'] = $pay_money;
            if($list_v['purchase_type']==2){
                $cost_all = -$cost_all;
            }
            $lists[$list_k]['sales_cost'] = round($cost_all,$price_decimal);
            $lists[$list_k]['profit'] = round($list_v['all_money']-$cost_all,$price_decimal);
            if($list_v['all_money']>0){
                $lists[$list_k]['profit_rate'] = round($lists[$list_k]['profit']/$list_v['all_money']*100,2);
            }else{
                $lists[$list_k]['profit_rate'] = 0;
            }
            $lists[$list_k]['net_profit'] = round($lists[$list_k]['profit']-$list_v['preferential_money']-$list_v['purchase_money'],$price_decimal);
            if($list_v['all_money']>0){
                $lists[$list_k]['net_profit_rate'] = round($lists[$list_k]['net_profit']/$list_v['all_money']*100,2);
            }else{
                $lists[$list_k]['net_profit_rate'] = 0;
            }
        }
        foreach ($lists as $lists_k=>$lists_v){
            if(empty($lists_v['info_goods']) && $lists_v['purchase_type']==2){
                $sale_code = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$lists_v['purchase_code']])->value('purchase_id');
                if($sale_code){
                    foreach ($lists as $lists_k2=>$lists_v2){
                        if($sale_code==$lists_v2['purchase_code']){
                            $lists[$lists_k]['info_goods'] = $lists_v2['info_goods'];
                        }
                    }
                }
                $info = Db::name('kuerp_goods_sales_exchange_info')->where(['purchase_code'=>$lists_v['purchase_code']])->select();
                $cost_all = 0;
                foreach ($info as $info_k=>$info_v){
                    $unit_price = isset($lists[$lists_k]['info_goods'][$info_v['goods_code']])?$lists[$lists_k]['info_goods'][$info_v['goods_code']]:$info_v['unit_price'];
                    $cost_all += ($info_v['retreat_number']*$unit_price);
                }
                $lists[$lists_k]['sales_cost'] = -round($cost_all,$price_decimal);

                $lists[$lists_k]['profit'] = round($lists_v['all_money']-$cost_all,$price_decimal);
                if($lists_v['all_money']!=0){
                    $lists[$lists_k]['profit_rate'] = round($lists[$lists_k]['profit']/$lists_v['all_money']*100,2);
                }else{
                    $lists[$lists_k]['profit_rate'] = 0;
                }
                $lists[$lists_k]['net_profit'] = round($lists[$lists_k]['profit']-$lists_v['preferential_money']-$lists_v['purchase_money'],$price_decimal);
                if($lists_v['all_money']!=0){
                    $lists[$lists_k]['net_profit_rate'] = round($lists[$lists_k]['net_profit']/$lists_v['all_money']*100,2);
                }else{
                    $lists[$lists_k]['net_profit_rate'] = 0;
                }
            }
        }
        return $lists;
    }

}
