<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Session;
/**
 * 结账/反结账
 *
 * @icon fa fa-circle-o
 */
class Checkout extends Backend
{

    /**
     * ExpendType模型对象
     * @var \app\admin\model\kuerp\ExpendType
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);
        $this->model = new \app\admin\model\kuerp\Checkout();

        $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
        $checkdate = Db::name('kuerp_checkout')->order(['checkdate'=>'DESC'])->value('checkdate');
        $this->assign('enable_time', $enable_time);
        $this->assign('checkdate', $checkdate);
    }

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
                ->with(['preparation'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('preparation')->visible(['username']);
            }

            foreach ($list as $list_k=>$list_v){
                $list[$list_k]['createtime'] = date('Y-m-d H:i:s',$list_v['createtime']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 结账
     */
    public function checkout()
    {
        if ($this->request->isPost()) {
            $checkdate = $this->request->post("checkdate");
            if(empty($checkdate)){
                $this->error('请选择结账日期');
            }
            $checkdate = date('Y-m-d',strtotime($checkdate));
            $checktime = strtotime($checkdate);

            $enable_time = Db::name('kuerp_sets')->where(['name'=>'enable_time'])->value('value');
            $enable_time = strtotime($enable_time);
            if($enable_time>$checktime){
                $this->error('结账日期不能小于系统启用日期：'.date('Y-m-d',$enable_time).'！');
            }
            $lastdate = Db::name('kuerp_checkout')->order(['checktime'=>'DESC'])->value('checkdate');
            $log = Db::name('kuerp_checkout')->where(['checktime'=>['>=',$checktime]])->find();
            if($log){
                $this->error('结账日期不能小于等于上次结账日期：'.$lastdate.'！');
            }
            if(empty($lastdate)){
                $lastdate = $enable_time;
            }else{
                $lastdate = strtotime($lastdate);
            }

            $admin = Session::get('admin');
            $data = [
                'checkdate'         => $checkdate,
                'checktime'         => $checktime,
                'preparation_id'    => $admin['id'],
                'preparation_time'  => time(),
                'createtime'        => time(),
                'updatetime'        => time()
            ];

            $goodsInfo = \addons\kuerp\library\Checkout::checkOutGoods($lastdate,$checktime);
            $saleData = [];
            foreach ($goodsInfo as $goodsInfo_k=>$goodsInfo_v){
                $saleData[] = [
                    'id'            => $goodsInfo_v['id'],
                    'sales_cost'    => $goodsInfo_v['sales_cost']
                ];
            }

            $info = [];
            $result= \addons\kuerp\library\Checkout::checkOutGoodss($checktime);
            foreach ($result as $result_k=>$result_v){
                $info[] = [
                    'goods_id'      => $result_v['id'],
                    'goods_code'    => $result_v['goods_code'],
                    'basic_nmber'   => isset($result_v['purchase_info']['basic_nmber'])?$result_v['purchase_info']['basic_nmber']:0,
                    'all_money'     => isset($result_v['purchase_info']['all_money'])?$result_v['purchase_info']['all_money']:0,
                    'purchase_id'   => isset($result_v['purchase_info']['id'])?$result_v['purchase_info']['id']:0,
                    'purchase_time' => isset($result_v['purchase_info']['purchase_time'])?$result_v['purchase_info']['purchase_time']:null,
                ];
            }
            //移动平均
            $result= \addons\kuerp\library\Checkout::checkOutGoodsss($checktime);
            foreach ($result as $result_k=>$result_v){
                foreach ($info as $info_k=>$info_v){
                    if(isset($result_v['purchase_info']['purchase_time'])){
                        if($info_v['purchase_time']==$result_v['purchase_info']['purchase_time']){
                            $info[$info_k]['av_basic_nmber'] = isset($result_v['purchase_info']['basic_nmber'])?$result_v['purchase_info']['basic_nmber']:0;
                            $info[$info_k]['av_all_money'] = isset($result_v['purchase_info']['all_money'])?$result_v['purchase_info']['all_money']:0;
                        }
                    }else{
                        $info[$info_k]['av_basic_nmber'] = 0;
                        $info[$info_k]['av_all_money'] = 0;
                    }
                }
            }
            $result = false;
            Db::startTrans();
            try {
                $result = Db::name('kuerp_checkout')->insertGetId($data);
                if(!empty($saleData)){
                    (new \app\admin\model\kuerp\GoodsSalesProfit())->allowField(true)->saveAll($saleData);
                }
                if(!empty($info)){
                    foreach($info as $info_k=>$info_v){
                        $info[$info_k]['checkout_id'] = $result;
                    }
                    Db::name('kuerp_checkout_info')->insertAll($info);
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if($result){
                $this->success('结账成功');
            }else{
                $this->error('结账失败');
            }
        }
    }

    /**
     * 反结账
     */
    public function checkoutf()
    {
        if ($this->request->isPost()) {
            $lastdate = Db::name('kuerp_checkout')->order(['checktime'=>'DESC'])->value('id');
            if(empty($lastdate)){
                $this->error('请先结账');
            }
            $result = false;
            Db::startTrans();
            try {
                $result = $this->model->where(['id'=>$lastdate])->delete();
                Db::name('kuerp_checkout_info')->where(['checkout_id'=>$lastdate])->delete();
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if($result){
                $this->success('反结账成功');
            }else{
                $this->error('反结账失败');
            }
        }
    }

}
