<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品序列号状态
 *
 * @icon fa fa-circle-o
 */
class GoodsSerialStatus extends Backend
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
                    ->where($where)
                    ->field('max(id) as id')
                    ->group('serial')
                    ->paginate($limit);

            foreach ($list as $list_k=>$list_v){
                $info = Db::name('kuerp_goods_serial_log')->where(['id'=>$list_v['id']])->find();
                $list[$list_k]['serial']        = $info['serial'];
                $list[$list_k]['business_type'] = $info['business_type'];
                $list[$list_k]['store_status']  = $info['store_status'];
                $list[$list_k]['purchase_time'] = $info['purchase_time'];
                $list[$list_k]['createtime']    = $info['createtime'];
                $list[$list_k]['purchase_code'] = $info['purchase_code'];
                $list[$list_k]['remarks']       = $info['remarks'];

                $goods = Db::name('kuerp_goods')->where(['id'=>$info['goods_id']])->field('goods_code,goods_name,goods_barcode,goods_specification_id')->find();
                $list[$list_k]['goods_code'] = $goods['goods_code'];
                $list[$list_k]['goods_name'] = $goods['goods_name'];
                $list[$list_k]['goods_spec'] = $goods['goods_specification_id'];

                $stores = Db::name('kuerp_stores')->where(['id'=>$info['stores_id']])->field('store_code,name')->find();
                $list[$list_k]['store_name'] = $stores['name'];
                //供应商/客户
                if($info['business_type']==2 || $info['business_type']==3 || $info['business_type']==7 || $info['business_type']==8){
                    $list[$list_k]['customer_id'] = \think\Db::name('kuerp_suppliers')->where(['id'=>$info['customer_id']])->value('supplyer_name');
                }else if($info['business_type']==4 || $info['business_type']==5 || $info['business_type']==9 || $info['business_type']==10){
                    $list[$list_k]['customer_id'] = \think\Db::name('kuerp_customers')->where(['id'=>$info['customer_id']])->value('customer_name');
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
