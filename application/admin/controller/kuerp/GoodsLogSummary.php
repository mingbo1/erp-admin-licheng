<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品收发明细汇总
 *
 * @icon fa fa-circle-o
 */
class GoodsLogSummary extends Backend
{

    /**
     * GoodsLog模型对象
     * @var \app\admin\model\kuerp\GoodsLog
     */
    protected $model = null;

    protected $noNeedRight = ['getgoods'];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsLog;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());

        $storesList = $this->model->getStoresList();
        $storesLists = [];
        foreach ($storesList as $storesList_k=>$storesList_v){
            $storesLists[] = ['name'=>$storesList_v['name'],'value'=>$storesList_v['id']];
        }
        $this->view->assign("storesList", $storesList);
        $this->assignconfig("storesList", $storesLists);

        $goodsList = $this->model->getGoodsList();
        $this->view->assign("goodsList", $goodsList);
        //商品下拉只给有收发记录的商品，与「仓库/商品联动」的 getgoods 口径保持一致
        $this->assignconfig("goodsList", $this->getGoodsByStores());
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

            $wheres = [];
            $searchdata = json_decode(input('filter'),true);
            if($searchdata){
                foreach ($searchdata as $k => &$v) {
                    if(!empty($v)){
                        if(strpos($v,' - ') !== false && strpos($k,'time') !== false){
                            $str = explode(' - ',$v);
                            $wheres['goods_log.'.$k] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                        }else{
                            $wheres['goods_log.'.$k] = ["LIKE", "%{$v}%"];
                        }
                    }
                }
            }

            //按 商品+仓库 分组：先取全部分组键算总数，再按当前页切片，
            //不能整表查出来后既当 rows 又当 total（会导致每页都返回全部数据、分页对不上）
            $groupKeyList = Db::name('kuerp_goods_log')->alias('goods_log')
                ->join('kuerp_goods goods','goods.id=goods_log.goods_id')
                ->join('kuerp_stores stores','stores.id=goods_log.stores_id')
                ->where($where)
                ->group('goods_log.goods_id,goods_log.stores_id')
                ->field('goods_log.goods_id,goods_log.stores_id')
                ->order('goods_log.goods_id','asc')
                ->order('goods_log.stores_id','asc')
                ->select();
            //summary 页是汇总报表，合计行要按全部数据算，不能只合计当前页
            $totalRow = $this->getTotalRow($groupKeyList);
            $total = count($groupKeyList);
            $page  = max(1, $this->request->get('page/d', 1));
            $limit = $this->request->get('limit/d', 0);
            $list  = $limit > 0 ? array_slice($groupKeyList, ($page - 1) * $limit, $limit) : $groupKeyList;
            foreach ($list as $list_k=>$list_v) {
                $list[$list_k]['goods'] = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])
                    ->field('goods_code,goods_name,goods_type_id,goods_barcode,goods_specification_id,note,unit_id')
                    ->find();
                $list[$list_k]['stores'] = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('name')->find();
            }

            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['goods_code'] = $list_v['goods']['goods_code'];
                $list[$list_k]['goods_name'] = $list_v['goods']['goods_name'];
                $list[$list_k]['goods_spec'] = $list_v['goods']['goods_specification_id'];
                $list[$list_k]['goods_barcode'] = $list_v['goods']['goods_barcode'];
                $list[$list_k]['type_name'] = Db::name('kuerp_goods_type')->where(['id'=>$list_v['goods']['goods_type_id']])->value('supplier_type_name');
                //期初
                $enter_basics = 0;$enter_cost = 0;
                $period = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'1'])
                    ->field('sum(balance_basics) as basics,sum(balance_cost) as cost')
                    ->find();
                $list[$list_k]['period_basics'] = $period['basics'];
                $list[$list_k]['period_cost'] = $period['cost'];
                //调拨入库
                $allocation_e = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'6'])
                    ->field('sum(enter_basics) as basics,sum(enter_cost) as cost')
                    ->find();
                $enter_basics+=(float)$allocation_e['basics'];
                $enter_cost+=(float)$allocation_e['cost'];
                $list[$list_k]['allocation_e_basics'] = $allocation_e['basics'];
                $list[$list_k]['allocation_e_cost'] = $allocation_e['cost'];
                //普通采购
                $buy_o = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'2'])
                    ->field('sum(enter_basics) as basics,sum(enter_cost) as cost')
                    ->find();
                $enter_basics+=(float)$buy_o['basics'];
                $enter_cost+=(float)$buy_o['cost'];
                $list[$list_k]['buy_o_basics'] = $buy_o['basics'];
                $list[$list_k]['buy_o_cost'] = $buy_o['cost'];
                //销售退回
                $sale_e = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'5'])
                    ->field('sum(enter_basics) as basics,sum(enter_cost) as cost')
                    ->find();
                $enter_basics+=(float)$sale_e['basics'];
                $enter_cost+=(float)$sale_e['cost'];
                $list[$list_k]['sale_e_basics'] = $sale_e['basics'];
                $list[$list_k]['sale_e_cost'] = $sale_e['cost'];

                //盘盈
                $surplus = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'8'])
                    ->field('sum(enter_basics) as basics,sum(enter_cost) as cost')
                    ->find();
                $enter_basics+=(float)$surplus['basics'];
                $enter_cost+=(float)$surplus['cost'];
                $list[$list_k]['surplus_basics'] = $surplus['basics'];
                $list[$list_k]['surplus_cost'] = $surplus['cost'];

                //其他入库
                $other_e = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'9'])
                    ->field('sum(enter_basics) as basics,sum(enter_cost) as cost')
                    ->find();
                $enter_basics+=(float)$other_e['basics'];
                $enter_cost+=(float)$other_e['cost'];
                $list[$list_k]['other_e_basics'] = $other_e['basics'];
                $list[$list_k]['other_e_cost'] = $other_e['cost'];

                $list[$list_k]['enter_basics'] = $enter_basics;
                $list[$list_k]['enter_cost'] = $enter_cost;

                $out_basics = 0;$out_cost = 0;
                //调拨出库
                $allocation_o = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'6'])
                    ->field('sum(out_basics) as basics,sum(out_cost) as cost')
                    ->find();
                $out_basics+=(float)$allocation_o['basics'];
                $out_cost+=(float)$allocation_o['cost'];
                $list[$list_k]['allocation_o_basics'] = $allocation_o['basics'];
                $list[$list_k]['allocation_o_cost'] = $allocation_o['cost'];
                //普通销售
                $sale_o = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'4'])
                    ->field('sum(out_basics) as basics,sum(out_cost) as cost')
                    ->find();
                $out_basics+=(float)$sale_o['basics'];
                $out_cost+=(float)$sale_o['cost'];
                $list[$list_k]['sale_o_basics'] = $sale_o['basics'];
                $list[$list_k]['sale_o_cost'] = $sale_o['cost'];
                //采购退回
                $buy_e = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'3'])
                    ->field('sum(out_basics) as basics,sum(out_cost) as cost')
                    ->find();
                $out_basics+=(float)$buy_e['basics'];
                $out_cost+=(float)$buy_e['cost'];
                $list[$list_k]['buy_e_basics'] = $buy_e['basics'];
                $list[$list_k]['buy_e_cost'] = $buy_e['cost'];

                //盘亏
                $loss = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'10'])
                    ->field('sum(out_basics) as basics,sum(out_cost) as cost')
                    ->find();
                $out_basics+=(float)$loss['basics'];
                $out_cost+=(float)$loss['cost'];
                $list[$list_k]['loss_basics'] = $loss['basics'];
                $list[$list_k]['loss_cost'] = $loss['cost'];

                //其他出库
                $other_o = Db::name('kuerp_goods_log')
                    ->where(['goods_id'=>$list_v['goods_id'],'stores_id'=>$list_v['stores_id'],'business_type'=>'11'])
                    ->field('sum(out_basics) as basics,sum(out_cost) as cost')
                    ->find();
                $out_basics+=(float)$other_o['basics'];
                $out_cost+=(float)$other_o['cost'];
                $list[$list_k]['other_o_basics'] = $other_o['basics'];
                $list[$list_k]['other_o_cost'] = $other_o['cost'];

                $list[$list_k]['out_basics'] = $out_basics;
                $list[$list_k]['out_cost'] = $out_cost;

                $list[$list_k]['balance_basics'] = (float)$period['basics']+$enter_basics-$out_basics;
                $list[$list_k]['balance_cost'] = (float)$period['cost']+$enter_cost-$out_cost;

                $list[$list_k]['unit_name'] = Db::name('kuerp_unit')->where(['id'=>$list_v['goods']['unit_id']])->value('name');
                $store = Db::name('kuerp_stores')->where(['id'=>$list_v['stores_id']])->field('store_code,name')->find();
                $list[$list_k]['store_code'] = $store['store_code'];
                $list[$list_k]['store_name'] = $store['name'];
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

            $format = [
                'period_basics'  =>$number_decimal,
                'period_cost'  =>$price_decimal,
                'allocation_e_basics'    =>$number_decimal,
                'allocation_e_cost'    =>$price_decimal,
                'buy_o_basics'    =>$number_decimal,
                'buy_o_cost'    =>$price_decimal,
                'sale_e_basics'      =>$number_decimal,
                'sale_e_cost'      =>$price_decimal,
                'enter_basics'      =>$number_decimal,
                'enter_cost'      =>$price_decimal,

                'allocation_o_basics'      =>$number_decimal,
                'allocation_o_cost'      =>$price_decimal,
                'sale_o_basics'      =>$number_decimal,
                'sale_o_cost'      =>$price_decimal,
                'buy_e_basics'      =>$number_decimal,
                'buy_e_cost'      =>$price_decimal,
                'out_basics'      =>$number_decimal,
                'out_cost'      =>$price_decimal,
                'balance_basics'      =>$number_decimal,
                'balance_cost'      =>$price_decimal,
            ];
            foreach ($list as $list_k=>$list_v){
                //格式化数字输出
                foreach ($format as $k=>$v){
                    if(isset($list_v[$k]) && $list_v[$k]!==null){
                        $list[$list_k][$k]  = round($list_v[$k],$v);
                    }
                }
            }
            foreach ($format as $k=>$v){
                if(isset($totalRow[$k])){
                    $totalRow[$k] = round($totalRow[$k],$v);
                }
            }
            $result = array("total" => $total, "rows" => $list, "total_row" => $totalRow);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 汇总页合计行：对「全部分组结果之和」求合计（不受分页影响）
     *
     * 必须与 index() 逐行统计同源：逐行数量是按 商品+仓库 汇总该行**全部**收发记录
     * （不叠加页面上的日期/商品等筛选，筛选只决定哪些 商品+仓库 出现在列表里），
     * 所以合计行也按同一批 商品+仓库 汇总，才能保证「合计 = 当前筛选下所有行相加」。
     *
     * @param array $groupKeyList 分组键列表（goods_id/stores_id），不受分页影响
     * @return array
     */
    protected function getTotalRow($groupKeyList)
    {
        $sum = function ($field, $types) {
            $when = [];
            foreach ((array)$types as $type) {
                $when[] = "goods_log.business_type='{$type}'";
            }
            return "SUM(CASE WHEN " . implode(' OR ', $when) . " THEN goods_log.{$field} ELSE 0 END)";
        };
        $fields = [
            'period_basics'       => $sum('balance_basics', [1]),
            'period_cost'         => $sum('balance_cost', [1]),
            'allocation_e_basics' => $sum('enter_basics', [6]),
            'allocation_e_cost'   => $sum('enter_cost', [6]),
            'buy_o_basics'        => $sum('enter_basics', [2]),
            'buy_o_cost'          => $sum('enter_cost', [2]),
            'sale_e_basics'       => $sum('enter_basics', [5]),
            'sale_e_cost'         => $sum('enter_cost', [5]),
            'enter_basics'        => $sum('enter_basics', [6, 2, 5, 8, 9]),
            'enter_cost'          => $sum('enter_cost', [6, 2, 5, 8, 9]),
            'allocation_o_basics' => $sum('out_basics', [6]),
            'allocation_o_cost'   => $sum('out_cost', [6]),
            'sale_o_basics'       => $sum('out_basics', [4]),
            'sale_o_cost'         => $sum('out_cost', [4]),
            'buy_e_basics'        => $sum('out_basics', [3]),
            'buy_e_cost'          => $sum('out_cost', [3]),
            'out_basics'          => $sum('out_basics', [6, 4, 3, 10, 11]),
            'out_cost'            => $sum('out_cost', [6, 4, 3, 10, 11]),
        ];
        $totalRow = [];
        foreach ($fields as $field_k => $field_v) {
            $totalRow[$field_k] = 0;
        }
        if (!empty($groupKeyList)) {
            $pairs = [];
            foreach ($groupKeyList as $groupKey) {
                $pairs[] = '(' . (int)$groupKey['goods_id'] . ',' . (int)$groupKey['stores_id'] . ')';
            }
            $fieldStr = [];
            foreach ($fields as $field_k => $field_v) {
                $fieldStr[] = $field_v . ' AS ' . $field_k;
            }
            $row = Db::name('kuerp_goods_log')->alias('goods_log')
                ->where('(goods_log.goods_id,goods_log.stores_id) in (' . implode(',', $pairs) . ')')
                ->field(implode(',', $fieldStr))
                ->find();
            foreach ($fields as $field_k => $field_v) {
                $totalRow[$field_k] = (float)$row[$field_k];
            }
        }
        $totalRow['balance_basics'] = $totalRow['period_basics'] + $totalRow['enter_basics'] - $totalRow['out_basics'];
        $totalRow['balance_cost']   = $totalRow['period_cost'] + $totalRow['enter_cost'] - $totalRow['out_cost'];
        return $totalRow;
    }

    /**
     * 按仓库取商品下拉数据（仓库/商品联动）
     */
    public function getgoods()
    {
        return json(['code' => 1, 'msg' => '', 'data' => $this->getGoodsByStores(input('stores_id', ''))]);
    }

    /**
     * 取指定仓库下有收发记录的商品（返回 xmSelect 的 data 结构）
     * @param string $stores_id 仓库ID，多个用逗号分隔；留空=不限仓库
     * @return array
     */
    protected function getGoodsByStores($stores_id = '')
    {
        $stores_ids = [];
        foreach (explode(',', (string)$stores_id) as $stores_id_v) {
            $stores_id_v = (int)$stores_id_v;
            if ($stores_id_v > 0 && !in_array($stores_id_v, $stores_ids)) {
                $stores_ids[] = $stores_id_v;
            }
        }
        $query = Db::name('kuerp_goods_log')->alias('goods_log')
            ->join('kuerp_goods goods', 'goods.id=goods_log.goods_id')
            ->where('goods.status', '1');
        if (!empty($stores_ids)) {
            $query->where('goods_log.stores_id', 'in', $stores_ids);
        }
        $rows = $query->group('goods.id')
            ->field('goods.id,goods.goods_name')
            ->order('goods.id', 'asc')
            ->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['name' => $row['goods_name'], 'value' => $row['id']];
        }
        return $list;
    }

}
