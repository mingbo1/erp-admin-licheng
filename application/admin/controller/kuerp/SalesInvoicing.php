<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\exception\PDOException;
/**
 * 销货开票管理
 *
 * @icon fa fa-circle-o
 */
class SalesInvoicing extends Backend
{
    protected $noNeedRight = ['list'];
    /**
     * SalesInvoicing模型对象
     * @var \app\admin\model\kuerp\SalesInvoicing
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\SalesInvoicingLog;
        $this->view->assign("statusList", $this->model->getStatusList());

        $this->view->assign("customers", $this->model->getCustomersList());
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
            $map = [];
            if(!empty($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['bill_time'])){
                    $str = explode(' - ',$filter['bill_time']);
                    $map['bill_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['customers_id'])){
                    $map['customers_id'] = $filter['customers_id'];
                }
                if(isset($filter['bill_codes'])){
                    if(strpos($filter['bill_codes'],',') !== false){
                        $str = explode(',',$filter['bill_codes']);
                        $map['bill_codes'] = ["in", $str];
                    }else{
                        $map['bill_codes'] = ["like", '%'.$filter['bill_codes'].'%'];
                    }
                }
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->with(['customers'])
                ->where($map)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('customers')->visible(['customer_name']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function list()
    {
        $model = new \app\admin\model\kuerp\SalesInvoicing;
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $data = input();
            $map = [];
            $map['sales_invoicing.give_money'] = ['<>',0];
            if(!empty($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['bill_time'])){
                    $str = explode(' - ',$filter['bill_time']);
                    $map['sales_invoicing.bill_time'] = ["BETWEEN", [strtotime($str[0]),strtotime($str[1])]];
                }
                if(!empty($filter['customers_id'])){
                    $map['sales_invoicing.customers_id'] = $filter['customers_id'];
                }
                if(isset($filter['bill_codes'])){
                    if(strpos($filter['bill_codes'],',') !== false){
                        $str = explode(',',$filter['bill_codes']);
                        $map['sales_invoicing.bill_codes'] = ["in", $str];
                    }else{
                        $map['sales_invoicing.bill_codes'] = ["like", '%'.$filter['bill_codes'].'%'];
                    }
                }
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $model
                ->with(['customers'])
                ->where($map)
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('customers')->visible(['customer_name']);
            }
            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['invoicing'] = '';
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $invoice = $this->request->post("invoice/a");
            $invoice_header = $this->request->post("invoice_header/a");
            if ($params) {
                if($params[0]){
                    $params = json_decode($params[0],true);
                }else{
                    $params = [];
                }
                if(empty($params)){
                    $this->error('请选择需要开票的数据');
                }
                $invoice = isset($invoice[0])?$invoice[0]:'';
                if(empty($invoice)){
                    $this->error('请输入发票号');
                }
                $invoice_header = isset($invoice_header[0])?$invoice_header[0]:'';
                if(empty($invoice_header)){
                    $this->error('请输入发票抬头');
                }
                $log = [];
                foreach ($params as $params_k=>$params_v){
                    $row = Db::name('kuerp_sales_invoicing')->where(['id'=>$params_v['id']])->find();
                    if(empty($row)){
                        $this->error('开票数据查询错误');
                    }else if($row['invoice_status']==1){
                        $this->error($row['bill_codes'].'已开票，不可重复开票');
                    }else if(empty($params_v['invoicing'])){
                        $this->error('请输入开票金额');
                    }else if(!is_numeric($params_v['invoicing'])){
                        $this->error('开票金额格式不正确');
                    }else{
                        $row['createtime'] = time();
                        $row['invoicing'] = $row['pay_money']+$params_v['invoicing'];
                        $row['give_money'] = $row['give_money']-$params_v['invoicing'];
                        $row['pay_money'] = $params_v['invoicing'];
                        $row['times'] = time();
                        $row['invoice'] = $invoice;
                        $row['invoice_header'] = $invoice_header;
                        $log[] = $row;
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    foreach ($log as $log_k=>$log_v){
                        $id = $log_v['id'];
                        Db::name('kuerp_sales_invoicing')->where(['id'=>$id])->update([
                            'invoice_status'=>1,
                            'pay_money'=>$log_v['invoicing'],
                            'give_money'=>$log_v['give_money']
                        ]);
                        unset($log_v['id']);unset($log_v['invoice_status']);unset($log_v['invoicing']);
                        $log_v['invoicing_id'] = $id;
                        $result = Db::name('kuerp_sales_invoicing_log')->insert($log_v);
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

        $list = Db::name('kuerp_sales_invoicing')->where(['invoice_status'=>'0'])->select();
        $this->view->assign("list", $list);

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
                    $row = Db::name('kuerp_sales_invoicing')->where(['id'=>$v['invoicing_id']])->find();
                    if($row){
                        Db::name('kuerp_sales_invoicing')->where(['id'=>$v['invoicing_id']])->update([
                            'invoice_status'=>'0',
                            'pay_money'=>($row['pay_money']-$v['pay_money']),
                            'give_money'=>($row['give_money']+$v['pay_money'])
                        ]);
                    }
                    $count += Db::name('kuerp_sales_invoicing_log')->where(['id'=>$v['id']])->delete();
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
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

}
