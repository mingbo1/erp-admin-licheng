<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\Session;
/**
 * 其他收支明细
 *
 * @icon fa fa-circle-o
 */
class Revenue extends Backend
{
    
    /**
     * Income模型对象
     * @var \app\admin\model\kuerp\Income
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Income;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("suppliers", $this->model->getSuppliersList());
        $this->view->assign("incomeType", $this->model->getIncomeTypeList());
        $this->view->assign("accountsList", $this->model->getAccountsList());
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
            $data = input();
            $where = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['i.purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
            }

            $field = empty(input('field'))?'purchase_time':trim(input('field'));
            $order = empty(input('sort'))?'ASC':trim(input('sort'));

            $lists = [];
            if(!isset($filter['business_type']) || (empty($filter['business_type']) || (isset($filter['business_type']) && $filter['business_type']==1))){
                $list = Db::name('kuerp_income_info')->alias('i')
                    ->join('kuerp_customers c','c.id=i.customers_id','left')
                    ->join('kuerp_income_type t','t.id=i.income_type_id')
                    ->join('kuerp_income p','p.id=i.purchase_id')
                    ->where(['p.examine_status'=>'1'])
                    ->where($where)
                    ->order([$field=>$order])
                    ->field('i.purchase_code,i.purchase_time,i.money,i.remark,c.customer_name,t.name')
                    ->select();
                foreach ($list as $list_k=>$list_v){
                    $lists[] = [
                        'purchase_time' => !empty($list_v['purchase_time'])?date('Y-m-d',$list_v['purchase_time']):'',
                        'purchase_code' => $list_v['purchase_code'],
                        'business_type' => '其他收入',
                        'name'          => $list_v['name'],
                        'receivable'    => $list_v['money'],
                        'amount'        => '',
                        'intercourse'   => $list_v['customer_name'],
                        'remark'        => $list_v['remark'],
                    ];
                }
            }

            if(!isset($filter['business_type']) || (empty($filter['business_type']) || (isset($filter['business_type']) && $filter['business_type']==2))){
                $list = Db::name('kuerp_expenditure_info')->alias('i')
                    ->join('kuerp_suppliers c','c.id=i.suppliers_id','left')
                    ->join('kuerp_expend_type t','t.id=i.expend_type_id')
                    ->join('kuerp_expenditure p','p.id=i.purchase_id')
                    ->where(['p.examine_status'=>'1'])
                    ->where($where)
                    ->order([$field=>$order])
                    ->field('i.purchase_code,i.purchase_time,i.money,i.remark,c.supplyer_name,t.name')
                    ->select();
                foreach ($list as $list_k=>$list_v){
                    $lists[] = [
                        'purchase_time' => !empty($list_v['purchase_time'])?date('Y-m-d',$list_v['purchase_time']):'',
                        'purchase_code' => $list_v['purchase_code'],
                        'business_type' => '其他支出',
                        'name'          => $list_v['name'],
                        'receivable'    => '',
                        'amount'        => $list_v['money'],
                        'intercourse'   => $list_v['supplyer_name'],
                        'remark'        => $list_v['remark'],
                    ];
                }
            }
            $result = array("total" => sizeof($lists), "rows" => $lists);
            return json($result);
        }
        return $this->view->fetch();
    }
}
