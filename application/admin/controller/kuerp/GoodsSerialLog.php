<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;

/**
 * 商品序列跟踪号
 *
 * @icon fa fa-circle-o
 */
class GoodsSerialLog extends Backend
{
    
    /**
     * GoodsSerialLog模型对象
     * @var \app\admin\model\kuerp\GoodsSerialLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsSerialLog;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("storeStatusList", $this->model->getStoreStatusList());

        $serialList = $this->model->getSerialList();
        $serialLists = [];
        foreach ($serialList as $serialList_k=>$serialList_v){
            $serialLists[] = ['name'=>$serialList_v['serial'],'value'=>$serialList_v['serial']];
        }
        $this->view->assign("serialList", $serialList);
        $this->assignconfig("serialList", $serialLists);

        $goodsList = $this->model->getGoodsList();
        $goodsLists = [];
        foreach ($goodsList as $goodsList_k=>$goodsList_v){
            $goodsLists[] = ['name'=>$goodsList_v['goods_name'],'value'=>$goodsList_v['id']];
        }
        $this->view->assign("goodsList", $goodsList);
        $this->assignconfig("goodsList", $goodsLists);
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
                    ->with(['goods','stores'])
                    ->where($where)
                    ->order('serial asc,createtime asc')
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('goods')->visible(['goods_code','goods_name','goods_barcode','goods_specification_id']);
				$row->getRelation('stores')->visible(['store_code','name']);
            }
            $listData = $list->items();
            $lists = array_reverse($list->items());
            $serial_id = '';
            foreach ($lists as $lists_k=>$lists_v){
                if($serial_id==$lists_v['serial']){
                    unset($lists[$lists_k]);
                }else{
                    $serial_id=$lists_v['serial'];
                }
            }
            foreach ($listData as $listData_k=>$listData_v){
                $listData[$listData_k]['goods_code'] = $listData_v['goods']['goods_code'];
                $listData[$listData_k]['goods_name'] = $listData_v['goods']['goods_name'];
                $listData[$listData_k]['goods_spec'] = $listData_v['goods']['goods_specification_id'];
                $listData[$listData_k]['store_name'] = $listData_v['stores']['name'];
                //供应商/客户
                if($listData_v['business_type']==2 || $listData_v['business_type']==3 || $listData_v['business_type']==8 || $listData_v['business_type']==9){
                    $listData[$listData_k]['customer_id'] = \think\Db::name('kuerp_suppliers')->where(['id'=>$listData_v['customer_id']])->value('supplyer_name');
                }else if($listData_v['business_type']==4 || $listData_v['business_type']==5 || $listData_v['business_type']==10 || $listData_v['business_type']==11){
                    $listData[$listData_k]['customer_id'] = \think\Db::name('kuerp_customers')->where(['id'=>$listData_v['customer_id']])->value('customer_name');
                }
                foreach ($lists as $lists_k=>$lists_v){
                    if($listData_v['id']!=$lists_v['id'] && $listData_v['serial']==$lists_v['serial']){
                        $listData[$listData_k]['store_status'] = '';
                    }
                }
            }
            $result = array("total" => $list->total(), "rows" => $listData);

            return json($result);
        }
        return $this->view->fetch();
    }

}
