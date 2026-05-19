<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
/**
 * 往来单位欠款表
 *
 * @icon fa fa-circle-o
 */
class Intercourse extends Backend
{

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $types[] = ['name'=>'客户','value'=>1];
        $types[] = ['name'=>'供应商','value'=>2];
        $this->assignconfig("types", $types);
    }

    public function index(){
        if ($this->request->isAjax()) {
            $data = input();
            $types = [];$keyword = '';
            if(!empty($data['filter'])){
                $filter = (array)json_decode($data['filter'], true);
                $filter = $filter ? $filter : [];
                if(!empty($filter['types_id'])){
                    $types = explode(',',$filter['types_id']);
                }
                if(!empty($filter['keyword'])){
                    $keyword = trim($filter['keyword']);
                }
            }
            $customers = [];
            if(empty($types) || in_array(1,$types)){
                $where = [];
                if(!empty($keyword)){
                    $where['customer_code|customer_name'] = ['like','%'.$keyword.'%'];
                }
                $customers = Db::name('kuerp_customers')->where($where)->select();
            }
            $suppliers = [];
            if(empty($types) || in_array(2,$types)){
                $where = [];
                if(!empty($keyword)){
                    $where['supplyer_code|supplyer_name'] = ['like','%'.$keyword.'%'];
                }
                $suppliers = Db::name('kuerp_suppliers')->where($where)->select();
            }

            $list = [];
            foreach ($customers as $customers_k=>$customers_v){
                $list[] = [
                    'code'      => $customers_v['customer_code'],
                    'name'      => $customers_v['customer_name'],
                    'type'      => 1,
                    'receive'   => $customers_v['need_receive_account'],
                ];
            }
            foreach ($suppliers as $suppliers_k=>$suppliers_v){
                $list[] = [
                    'code'      => $suppliers_v['supplyer_code'],
                    'name'      => $suppliers_v['supplyer_name'],
                    'type'      => 2,
                    'give'      => $suppliers_v['need_give_account'],
                ];
            }
            $result = array("total" => sizeof($list), "rows" => $list);
            return json($result);
        }
        return $this->view->fetch();
    }

}
