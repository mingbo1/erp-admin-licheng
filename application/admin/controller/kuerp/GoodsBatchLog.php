<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 批次跟踪管理
 *
 * @icon fa fa-circle-o
 */
class GoodsBatchLog extends Backend
{
    
    /**
     * GoodsBatchLog模型对象
     * @var \app\admin\model\kuerp\GoodsBatchLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsBatchLog;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("saleList", $this->model->getSaleList());
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
                    ->with(['goods','unit','batch','stores'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('goods')->visible(['goods_code','goods_name']);
				$row->getRelation('unit')->visible(['name']);
                $row->getRelation('stores')->visible(['name']);
                $row->getRelation('batch')->visible(['batch_name','manufacture','guarantee','validity','address','registration','production']);
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

            foreach ($list as $list_k=>$list_v){
                //供应商/客户
                if($list_v['business_type']==2 || $list_v['business_type']==3 || $list_v['business_type']==7 || $list_v['business_type']==8){
                    $list[$list_k]['customer_id'] = Db::name('kuerp_suppliers')->where(['id'=>$list_v['customer_id']])->value('supplyer_name');
                }else if($list_v['business_type']==4 || $list_v['business_type']==5 || $list_v['business_type']==9 || $list_v['business_type']==10){
                    $list[$list_k]['customer_id'] = Db::name('kuerp_customers')->where(['id'=>$list_v['customer_id']])->value('customer_name');
                }
                //单位信息
                $goods = Db::name('kuerp_goods')->where(['id'=>$list_v['goods_id']])->field('more_unit,unit_id')->find();
                $unit = Db::name('kuerp_unit')->where(['id'=>$goods['unit_id']])->field('id,name')->find();
                $unit_name = Db::name('kuerp_unit')->where(['id'=>$list_v['goods_unit']])->value('name');
                $list[$list_k]['unit_name'] = $unit_name;
                $max_unit = 1;
                $unit_arr = [];
                $more_unit_num = $list_v['number'];
                $sub_unit = '';
                if($unit){
                    $unit_arr[] = ['name'=>$unit['name'],'advance_rate'=>1];
                    if($goods['more_unit']==1){
                        $more_unit = Db::name('kuerp_unit_conversion')->alias('uc')
                            ->join('kuerp_unit u','u.id=uc.vice_unit')
                            ->where(['uc.main_unit'=>$unit['id']])
                            ->order('uc.advance_rate','ASC')
                            ->field('uc.vice_unit,u.name,u.id,uc.advance_rate')
                            ->select();
                        foreach ($more_unit as $unit_k=>$unit_v){
                            $unit_arr[] = ['name'=>$unit_v['name'],'advance_rate'=>$unit_v['advance_rate']];
                            if($unit_v['advance_rate']>$max_unit){
                                $max_unit = $unit_v['advance_rate'];
                            }
                            if($unit_v['id']==$list_v['goods_unit']){
                                $more_unit_num = round(($list_v['number']*$unit_v['advance_rate']),$number_decimal);
                            }
                            $sub_unit = round(($more_unit_num/$unit_v['advance_rate']),$number_decimal).$unit_v['name'];
                        }
                    }
                    $unitconversion = '';
                    $unit_arr = \addons\kuerp\library\Goods::arraySequence($unit_arr,'advance_rate','SORT_DESC');
                    foreach ($unit_arr as $unit_arr_k=>$unit_arr_v){
                        if(!empty($unitconversion)){
                            $unitconversion .= '=';
                        }
                        $unitconversion .= round(($max_unit/$unit_arr_v['advance_rate']),$number_decimal).$unit_arr_v['name'];
                    }
                    $list[$list_k]['unitconversion'] = $unitconversion;
                    $list[$list_k]['more_unit_num']  = $more_unit_num;
                    $list[$list_k]['sub_unit']  = $sub_unit;
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
