<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 销货订单详情
 *
 * @icon fa fa-circle-o
 */
class GoodsSalesOrderInfo extends Backend
{
    
    /**
     * GoodsSalesOrderInfo模型对象
     * @var \app\admin\model\kuerp\GoodsSalesOrderInfo
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesOrderInfo;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("checkStatusList", $this->model->getCheckStatusList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());

        $this->view->assign("customers", $this->model->getCustomersList());
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
            $where = [];
            $searchdata = json_decode(input('filter'),true);
            if($searchdata){
                foreach ($searchdata as $k => &$v) {
                    if($v!==''){
                        if($k=='id'){
                            $where['i.'.$k] = ['<',$v];
                        }else if($k=='examine_status'){
                            $where['po.'.$k] = $v;
                        }else if(strpos($v,' - ') !== false && strpos($k,'time') !== false){
                            $str = explode(' - ',$v);
                            $where['i.'.$k] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                        }else{
                            $where['i.'.$k] = ["LIKE", "%{$v}%"];
                        }
                    }
                }
            }
            $field = empty(input('field'))?'goods_code':trim(input('field'));
            $order = empty(input('sort'))?'DESC':trim(input('sort'));

            $list = Db::name('kuerp_goods_sales_order_info')->alias('i')
                ->join('kuerp_goods_sales_order po','po.purchase_code=i.purchase_code')
                ->join('kuerp_goods g','g.goods_code=i.goods_code')
                ->join('kuerp_unit u','u.id=i.default_unit')
                ->join('kuerp_customers s','s.id=i.customers_id')
                ->join('kuerp_stores r','r.id=i.stores_id')
                ->where($where)
                ->order(['i.'.$field=>$order,'i.purchase_time'=>'DESC'])
                ->field('i.id,i.goods_code,i.purchase_code,i.purchase_type,i.purchase_time,i.delivery_time
                ,i.all_money,po.status,i.number,i.tax_included,i.money_tax_price,i.unit_price,i.number as library_number,i.money_tax_price as library_money,i.library_time,
                g.goods_name,g.goods_specification_id,
                s.customer_name as s_name,
                r.name as r_name,
                u.name as unitname')
                ->select();
            foreach ($list as $list_k=>$list_v){
                $purord_info = Db::name('kuerp_goods_saleord_info')->where(['order_id'=>$list_v['id'],'status'=>'1'])->field('sum(number) as number,sum(money_tax_price) as all_money')->find();
                $p_id = Db::name('kuerp_goods_saleord_info')->where(['order_id'=>$list_v['id'],'status'=>'1'])->value('purchase_id');
                $goods_excpur_info = Db::name('kuerp_goods_saleexc_info')->where(['purchase_id'=>$p_id,'status'=>'1'])->find();
                if($goods_excpur_info){
                    $purord_info['number'] = round( ($purord_info['number']-$goods_excpur_info['number']),2 );
                    $purord_info['all_money'] = round( ($purord_info['all_money']-$goods_excpur_info['money_tax_price']),2 );
                }
                if($purord_info){
                    $list[$list_k]['library_number'] = round(($list[$list_k]['library_number']-$purord_info['number']),2);
                    $list[$list_k]['library_money'] = round(($list[$list_k]['library_money']-$purord_info['all_money']),4);
                }
                if($list[$list_k]['library_number']>0 && $list[$list_k]['library_number']<$list[$list_k]['number']){
                    $list[$list_k]['status'] = 2;
                }else if($list[$list_k]['library_number']>0 && $list[$list_k]['library_number']=$list[$list_k]['number']){
                    $list[$list_k]['status'] = 0;
                }
                $purord_time = Db::name('kuerp_goods_saleord_info')->where(['order_id'=>$list_v['id'],'status'=>1])->order('times','DESC')->value('times');
                if($purord_time){
                    $list[$list_k]['library_time'] = $purord_time;
                }
            }

            foreach ($list as $list_k=>$list_v){
                if($list_v['purchase_type']==2){
                    $list[$list_k]['number'] = -$list_v['number'];
                    $list[$list_k]['money_tax_price'] = -$list_v['money_tax_price'];
                    $list[$list_k]['library_number'] = -$list_v['library_number'];
                    $list[$list_k]['library_money'] = -$list_v['library_money'];
                }
            }

            $count = [
                'number'=>0,
                'money_tax_price'=>0,
                'library_number'=>0,
                'library_money'=>0,
            ];
            $counts = $count;
            $counts['purchase_type'] = '合计';
            $data = [];
            $now = isset($list[0][$field])?$list[0][$field]:'';
            foreach ($list as $list_k=>$list_v){
                if($list_v[$field]!=$now){
                    $now = $list_v[$field];
                    $row = $count;$row['purchase_type'] = '小计';
                    $data[] = $row;
                    foreach ($count as $count_k=>$count_v){
                        $count[$count_k] = $list_v[$count_k];
                        $counts[$count_k] += $list_v[$count_k];
                    }
                }else{
                    foreach ($count as $count_k=>$count_v){
                        $count[$count_k] += $list_v[$count_k];
                        $counts[$count_k] += $list_v[$count_k];
                    }
                }
                $data[] = $list_v;
                if($list_k==(sizeof($list)-1) || sizeof($list)==1){
                    $row = $count;$row['purchase_type'] = '小计';
                    $data[] = $row;
                }
            }
            $data[] = $counts;
            $result = array("total" => sizeof($data), "rows" => $data, "field" => $field, "order" => $order);
            return json($result);
        }
        return $this->view->fetch();
    }

}
