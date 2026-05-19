<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 采购付款一览管理
 *
 * @icon fa fa-circle-o
 */
class PaymentLog extends Backend
{

    /**
     * PaymentLog模型对象
     * @var \app\admin\model\kuerp\PaymentLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\PaymentLog;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
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
                ->with(['suppliers'])
                ->where($where)
                ->order(['bill_code'=>'ASC','id'=>'ASC'])
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('suppliers')->visible(['supplyer_name']);
            }

            $price_decimal = Db::name('kuerp_sets')->where(['name'=>'price_decimal'])->value('value');

            $oldcount = $count = [
                'money_tax_price'=>0,
                'purchase_money'=>0,
                'discount_money'=>0,
                'real_money'=>0,
                'pay_money'=>0,
                'give_money'=>0,
            ];
            $counts = $count;
            $counts['s_name'] = '合计';
            $data = [];
            $field = 'bill_code';
            $now = isset($list[0][$field])?$list[0][$field]:'';
            $give_money = 0;
            $pay_money = 0;
            $status = true;
            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['supplyer_name'] = $list_v['suppliers']['supplyer_name'];
                $list[$list_k]['payment_rate'] = '';
                $list[$list_k]['payment_rate'] = '';

                if($list_v[$field]!=$now){
                    $now = $list_v[$field];
                    $row = $count;
                    $row['s_name'] = '小计';$row['supplyer_name'] = '小计';$row['bill_time'] = '';
                    if(($count['real_money'])>0){
                        $row['payment_rate'] = round($count['pay_money']/($count['real_money']),4)*100 .'%';
                    }else{
                        $row['payment_rate'] = '';
                    }
                    $data[] = $row;
                    foreach ($count as $count_k=>$count_v){
                        $counts[$count_k] += $row[$count_k];
                    }
                    $status = true;
                }

                if($list_v[$field]==$now){
                    if($status){
                        $give_money = $list_v['give_money'];$pay_money = $list_v['pay_money'];
                        $status = false;
                        $count = $oldcount;
                    }else{
                        $pay_money += $list_v['pay_money'];
                        $give_money += $list_v['give_money'];
                        $give_money -= $list_v['pay_money'];
                    }
                    $list_v['give_money'] = $give_money;
                    if(!$status){
                        foreach ($count as $count_k=>$count_v){
                            $count[$count_k] += $list_v[$count_k];
                        }
                        $count['give_money'] = $list_v['give_money'];
                    }
                }
                $data[] = $list_v;
                if($list_k==(sizeof($list)-1) || sizeof($list)==1){
                    $row = $count;$row['s_name'] = '小计';$row['supplyer_name'] = '小计';$row['bill_time'] = '';
                    if($row['real_money']>0){
                        $row['payment_rate'] = round($count['pay_money']/($count['real_money']),4)*100 .'%';
                    }else{
                        $row['payment_rate'] = '';
                    }
                    $data[] = $row;
                    foreach ($count as $count_k=>$count_v){
                        $counts[$count_k] += $row[$count_k];
                    }
                }
            }

            if ($counts['real_money']>0){
                $counts['payment_rate'] = round($counts['pay_money']/($counts['real_money']),4)*100 .'%';
            }else{
                $counts['payment_rate'] = '';
            }
            $counts['supplyer_name'] = '合计';$counts['bill_time'] = '';
            $counts['purchase_money'] = round($counts['purchase_money'],$price_decimal);
            $counts['discount_money'] = round($counts['discount_money'],$price_decimal);
            $counts['real_money'] = round($counts['real_money'],$price_decimal);
            $counts['pay_money'] = round($counts['pay_money'],$price_decimal);
            $counts['give_money'] = round($counts['give_money'],$price_decimal);

            $data[] = $counts;
            $result = array("total" => $list->total(), "rows" => $data);
            return json($result);
        }
        return $this->view->fetch();
    }

}
