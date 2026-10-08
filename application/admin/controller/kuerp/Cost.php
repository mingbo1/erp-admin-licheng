<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 采购销售费用清单
 *
 * @icon fa fa-circle-o
 */
class Cost extends Backend
{
    protected $noNeedRight = ['checkdata'];
    /**
     * Cost模型对象
     * @var \app\admin\model\kuerp\Cost
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Cost;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());

        $this->view->assign("incomeType", $this->model->getIncomeTypeList());
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
                ->with(['admin'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('admin')->visible(['nickname']);
            }
            $suppliers = Db::name('kuerp_suppliers')->column('supplyer_name','id');
            $customer = Db::name('kuerp_customers')->column('customer_name','id');
            $lists = [];
            foreach ($list as $list_k=>$list_v){
                $expenditure = Db::name('kuerp_expenditure_info')->where(['cost_id'=>$list_v['id'],'business_type'=>'1'])->group('purchase_code')->column('purchase_code');
                $expenditure = implode('<br/>',$expenditure);
                if($list_v['business_type']==1 || $list_v['business_type']==3){
                    $suppliers_name = isset($suppliers[$list_v['suppliers_ids']])?$suppliers[$list_v['suppliers_ids']]:'';
                }else{
                    $suppliers_name = isset($customer[$list_v['suppliers_ids']])?$customer[$list_v['suppliers_ids']]:'';
                }
                $lists[] = [
                    'id'=>$list_v['id'],
                    'purchase_code' => $list_v['purchase_code'],
                    'suppliers_id'     => $list_v['suppliers_id'],
                    'suppliers'     => isset($suppliers[$list_v['suppliers_id']])?$suppliers[$list_v['suppliers_id']]:'',
                    'business_type' => $list_v['business_type'],
                    'money' => $list_v['money'],
                    'pay_money' => $list_v['pay_money'],
                    'purchase_number' => $list_v['purchase_number'],
                    'suppliers_name'     => $suppliers_name,
                    'expenditure' => $expenditure,
                    'purchase_times' => !empty($list_v['purchase_times'])?date('Y-m-d',$list_v['purchase_times']):'',
                    'remark' => $list_v['remark'],
                    'pay_status' => $list_v['pay_status'],
                ];
            }

            $result = array("total" => $list->total(), "rows" => $lists);

            return json($result);
        }
        return $this->view->fetch();
    }

    //组合采购销售费用
    public function checkdata(){
        if(isset($_POST['cost_data']) && !empty($_POST['cost_data'])){
            $this->success(['ser'=>unserialize($_POST['cost_data'])]);
        }
        $cost = isset($_POST['costs'])?$_POST['costs']:[];
        if(empty($cost)){
            $this->error('请输入金额');
        }else{
            $data = [];
            $money = 0;
            foreach ($cost['money'] as $k=>$v){
                if(!empty($v)){
                    if(empty($cost['expend_type_id'][$k])){
                        $this->error('请选择支出类别');
                    }else{
                        $money += $cost['money'][$k];
                        $data[] = [
                            'suppliers_id' => empty($cost['suppliers_id'][$k])?null:$cost['suppliers_id'][$k],
                            'expend_type_id' => $cost['expend_type_id'][$k],
                            'money' => $cost['money'][$k],
                            'remark' => $cost['remark'][$k],
                        ];
                    }
                }
            }
            $data = serialize($data);
            $this->success(['data'=>$data,'count'=>$money]);
        }
    }
}
