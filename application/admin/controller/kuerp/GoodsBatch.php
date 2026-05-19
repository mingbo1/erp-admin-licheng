<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;

/**
 * 商品批次管理
 *
 * @icon fa fa-circle-o
 */
class GoodsBatch extends Backend
{
    
    /**
     * GoodsBatch模型对象
     * @var \app\admin\model\kuerp\GoodsBatch
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\GoodsBatchInfo();

        $goodsList = $this->model->getGoodsList();
        $goodsLists = [];
        foreach ($goodsList as $goodsList_k=>$goodsList_v){
            $goodsLists[] = ['name'=>$goodsList_v['goods_name'],'value'=>$goodsList_v['id']];
        }
        $this->view->assign("goodsList", $goodsList);
        $this->assignconfig("goodsList", $goodsLists);

        $batchList = $this->model->getBatchList();
        $batchLists = [];
        foreach ($batchList as $batchList_k=>$batchList_v){
            $batchLists[] = ['name'=>$batchList_v['batch_name'],'value'=>$batchList_v['id']];
        }
        $this->view->assign("batchList", $batchList);
        $this->assignconfig("batchList", $batchLists);
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
                    ->with(['goods'])
                    ->where($where)
                    ->order($sort, $order)
                    ->select();

            foreach ($list as $row) {
                
                $row->getRelation('goods')->visible(['goods_name']);
            }

            $result = array("total" => sizeof($list), "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

}
