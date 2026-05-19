<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
/**
 * 商品销售明细
 *
 * @icon fa fa-circle-o
 */
class GoodsSalesInfo extends Backend
{
    
    /**
     * GoodsSalesInfo模型对象
     * @var \app\admin\model\kuerp\GoodsSalesInfo
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSalesInfo;
        $this->view->assign("purchaseTypeList", $this->model->getPurchaseTypeList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['customers','staff_member','goods','unit','stores'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('staff_member')->visible(['name']);
                $row->getRelation('customers')->visible(['customer_name']);

                $row->getRelation('goods')->visible(['goods_name','goods_specification_id','goods_code','goods_barcode']);
                $row->getRelation('unit')->visible(['name']);
                $row->getRelation('stores')->visible(['name']);
            }

            foreach ($list as $list_k=>$list_v){
                if($list_v['purchase_type']==1){
                    $note = Db::name('kuerp_goods_sales')->where(['purchase_code'=>$list_v['purchase_code']])->value('note');
                }else{
                    $note = Db::name('kuerp_goods_sales_exchange')->where(['purchase_code'=>$list_v['purchase_code']])->value('note');
                }
                $list[$list_k]['note'] = $note;
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
