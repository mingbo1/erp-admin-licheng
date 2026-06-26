<?php

namespace app\admin\controller\kuerp;

use think\Db;

/**
 * 采购单入库
 *
 * 基于购货单(GoodsPurchase)，复用同一数据库表 fa_kuerp_goods_purchase。
 * 主要供仓库员使用，用于入库审核和对单操作，前端页面不显示金额相关字段。
 *
 * @icon fa fa-industry
 */
class GoodsPurchaseStorage extends GoodsPurchase
{
    protected $noNeedRight = ['checkpurchase','getsuppliers','getunit','formatinfo','formatinfo2','getbranch','gettime','printpage'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 列表页 - 隐藏金额相关字段
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
            $sort = 'purchase_time';$order='DESC';
            $list = $this->model
                ->with(['suppliers','preparation','examine','secondExamine'])
                ->where($where)
                ->order($sort, $order)
                ->order('purchase_code', 'DESC')
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('suppliers')->visible(['supplyer_code','supplyer_name']);
                $row->getRelation('preparation')->visible(['username']);
                $row->getRelation('examine')->visible(['username']);
                if ($row->getRelation('secondExamine')) {
                    $row->getRelation('secondExamine')->visible(['username']);
                }
            }
            foreach ($list as $list_k=>$list_v){
                if(isset($list_v['print_num']) && empty($list_v['print_num'])){
                    $list[$list_k]['print_num'] = 0;
                }
                $purchase_id = Db::name('kuerp_goods_excpur')->where(['purchase_id'=>$list_v['id']])->column('exchange_id');
                $purchase = Db::name('kuerp_goods_exchange')->where(['id'=>['in',$purchase_id]])->column('purchase_code');
                $list[$list_k]['returns_order_id'] = implode(',',$purchase);
                // 隐藏金额相关字段
                $list[$list_k]->hidden([
                    'all_money','real_money','tax_money','preferential_rate',
                    'preferential_money','pay_money','arrears','all_arrears',
                    'purchase_money','account','cost_data','delivery_time'
                ]);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 审核/反审核 - 复用父类的内部逻辑
     */
    public function examine($ids = null, $examine = 1)
    {
        return $this->_examine($ids, $examine);
    }

    /**
     * 对单/反对单 - 复用父类的内部逻辑
     */
    public function counter($ids = null, $counter = 1)
    {
        return $this->_counter($ids, $counter);
    }

}
