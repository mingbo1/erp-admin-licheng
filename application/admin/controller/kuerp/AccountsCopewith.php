<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 应付账款明细管理
 *
 * @icon fa fa-circle-o
 */
class AccountsCopewith extends Backend
{

    /**
     * AccountsCopewith模型对象
     * @var \app\admin\model\kuerp\AccountsCopewith
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\AccountsCopewith;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());

        $this->view->assign("customers", $this->model->getCustomersList());
        $this->view->assign("suppliers", $this->model->getSuppliersList());
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
            $data = input();
            $where = [];
            $map = [];
            if(isset($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['purchase_time'])){
                    $str = explode(' - ',$filter['purchase_time']);
                    $where['purchase_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['suppliers_id'])){
                    $map['sp.id'] = trim($filter['suppliers_id']);
                }
            }

            $sup = Db::name('kuerp_suppliers')->alias('sp')
                ->join('kuerp_supplyertype gt','sp.supplyertype_id=gt.id')
                ->where($map)
                ->order('sp.supplyer_code asc')
                ->field('sp.id,sp.receive_account,sp.supplyer_code,sp.supplyer_name,sp.opening_payable,sp.opening_prepayment,gt.supplier_type_name')
                ->select();

            $field = empty(input('field'))?'createtime':trim(input('field'));
            $order = empty(input('sort'))?'ASC':trim(input('sort'));

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

            $listarr = [];
            $all_balance = 0;
            $all_receivable = 0;
            $all_advance = 0;
            foreach ($sup as $sup_k=>$sup_v){
                $balance = round($sup_v['opening_payable']-$sup_v['opening_prepayment'],$price_decimal);
                $receivable = 0;
                $advance = 0;
                $listarr[] = [
                    'customer_name' => $sup_v['supplyer_name'],
                    'purchase_time' => '',
                    'purchase_code' => '期初余额',
                    'business_type' => '',
                    'receivable'    => '',
                    'advance'       => '',
                    'balance'       => $balance,
                    'remark'        => ''
                ];
                $list = Db::name('kuerp_accounts_copewith')
                    ->where($where)
                    ->where(['suppliers_id'=>$sup_v['id']])
                    ->order([$field=>$order])
                    ->select();
                foreach ($list as $list_k=>$list_v){
                    $balance    += $list_v['receivable'];
                    $balance    -= $list_v['advance'];
                    $receivable += $list_v['receivable'];
                    $advance    += $list_v['advance'];
                    $listarr[] = [
                        'customer_name' => $sup_v['supplyer_name'],
                        'purchase_time' => !empty($list_v['purchase_time'])?date('Y-m-d',$list_v['purchase_time']):'',
                        'purchase_code' => $list_v['purchase_code'],
                        'business_type' => $list_v['business_type'],
                        'receivable'    => $list_v['receivable'],
                        'advance'       => $list_v['advance'],
                        'balance'       => round($balance,$price_decimal),
                        'remark'        => $list_v['remark'],
                    ];
                }
                $listarr[] = [
                    'customer_name' => $sup_v['supplyer_name'],
                    'purchase_time' => '',
                    'purchase_code' => '小计',
                    'business_type' => '',
                    'receivable'    => round($receivable,$price_decimal),
                    'advance'       => round($advance,$price_decimal),
                    'balance'       => round($balance,$price_decimal),
                    'remark'        => ''
                ];
                $all_balance    += $balance;
                $all_receivable += $receivable;
                $all_advance    += $advance;
            }

            $result = array("total" => sizeof($listarr), "rows" => $listarr, "all_balance" => $all_balance, "all_receivable" => $all_receivable, "all_advance" => $all_advance);

            return json($result);
        }
        return $this->view->fetch();
    }

}
