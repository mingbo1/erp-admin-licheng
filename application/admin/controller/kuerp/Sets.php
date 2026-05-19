<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;
use Exception;
/**
 * 设置
 *
 * @icon fa fa-circle-o
 */
class Sets extends Backend
{

    /**
     * Sets模型对象
     * @var \app\admin\model\kuerp\Sets
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\Sets;
        $this->view->assign("typeList", $this->model->getTypeList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function index(){
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                if(isset($params['type']) && $params['type']==3){
                    //单号设置
                    $data = [];
                    $prefix = [];
                    foreach ($params['id'] as $k=>$v){
                        if(in_array($params['prefix'.$v],$prefix)){
                            $this->error('单号前缀'.$params['prefix'.$v].'重复');
                        }
                        $data[] = [
                            'id' => $v,
                            'prefix' => $params['prefix'.$v],
                            'year' => isset($params['year'.$v])?$params['year'.$v]:2,
                            'month' => isset($params['month'.$v])?$params['month'.$v]:2,
                            'day' => isset($params['day'.$v])?$params['day'.$v]:2,
                            'hour' => isset($params['hour'.$v])?$params['hour'.$v]:2,
                            'minute' => isset($params['minute'.$v])?$params['minute'.$v]:2,
                            'second' => isset($params['second'.$v])?$params['second'.$v]:2,
                            'start' => isset($params['start'.$v])?$params['start'.$v]:1,
                            'strlen' => isset($params['strlen'.$v])?$params['strlen'.$v]:1,
                            'example' => isset($params['example'.$v])?$params['example'.$v]:'',
                        ];
                        $datas = [
                            'id' => $v,
                            'prefix' => $params['prefix'.$v],
                            'year' => isset($params['year'.$v])?$params['year'.$v]:2,
                            'month' => isset($params['month'.$v])?$params['month'.$v]:2,
                            'day' => isset($params['day'.$v])?$params['day'.$v]:2,
                            'hour' => isset($params['hour'.$v])?$params['hour'.$v]:2,
                            'minute' => isset($params['minute'.$v])?$params['minute'.$v]:2,
                            'second' => isset($params['second'.$v])?$params['second'.$v]:2,
                            'start' => isset($params['start'.$v])?$params['start'.$v]:1,
                            'strlen' => isset($params['strlen'.$v])?$params['strlen'.$v]:1,
                            'example' => isset($params['example'.$v])?$params['example'.$v]:'',
                        ];
                        $row = Db::name('kuerp_prefix')->where(['id'=>$v])->find();
                        foreach ($row as $row_k=>$row_v){
                            if(isset($datas[$row_k]) && $datas[$row_k]!=$row_v){
                                $order = Db::name($row['dbname'])->find();
                                if($order){
                                    $this->error('已产生'.$row['name'].'数据，不可再修改'.$row['name'].'单号组合规则');
                                }
                            }
                        }
                    }
                    if(!empty($data)){
                        $res = (new \app\admin\model\kuerp\Prefix())->saveAll($data);
                        if ($res) {
                            $this->success();
                        } else {
                            $this->error(__('No rows were inserted'));
                        }
                    }
                }
                if(isset($params['type']) && $params['type']==4){
                    $price_sale_last = $params['price_sale_last'];
                    $price_buy_last = $params['price_buy_last'];
                    $res = $this->model->where(['name'=>'price_sale_last'])->update(['is_checked'=>$price_sale_last]);
                    $res1 = $this->model->where(['name'=>'price_buy_last'])->update(['is_checked'=>$price_buy_last]);
                    if ($res || $res1) {
                        $this->success();
                    } else {
                        $this->error(__('没有更改设置项'));
                    }
                }
                foreach ($params as $params_k=>$params_v){
                    if($params_v['id']==8 || $params_v['id']==9){
                        $sets = Db::name('kuerp_sets')->where(['id'=>$params_v['id']])->value('value');
                        if($sets>$params_v['value']){
                            if($params_v['id']==8){
                                $this->error('价格小数位数不可小于当前设置位数');
                            }
                            if($params_v['id']==9){
                                $this->error('数量小数位数不可小于当前设置位数');
                            }
                        }
                        if($params_v['value']>4 && ($params_v['id']==8 || $params_v['id']==9)){
                            $this->error('小数位数最高不可超过4位');
                        }
                    }
                    if($params_v['id']==11 && (!isset($params_v['is_checked']) || (isset($params_v['is_checked']) && $params_v['is_checked']==0))){
                        $sets = Db::name('kuerp_sets')->where(['id'=>$params_v['id']])->value('is_checked');
                        if($sets==1){
                            $arr = [
                                'kuerp_goods_purchase_order' => '购货订单',
                                'kuerp_goods_purchase' => '购货单',
                                'kuerp_goods_exchange' => '购货退货单',
                                'kuerp_goods_sales_order' => '销货订单',
                                'kuerp_goods_sales' => '销货单',
                                'kuerp_goods_sales_exchange' => '销货退货单',
                                'kuerp_allocation' => '调拨单',
                                'kuerp_warehousing' => '其他入库单',
                                'kuerp_warehouse' => '其他出库单',
                                'kuerp_assemble' => '组装单',
                                'kuerp_split' => '拆卸单',
                                'kuerp_payment' => '付款单',
                                'kuerp_collection' => '收款单',
                                'kuerp_writeoff' => '核销单',
                                'kuerp_income' => '其他收入单',
                                'kuerp_expenditure' => '其他支出单',
                                'kuerp_accounts_transfer' => '资金转账单',
                            ];
                            foreach ($arr as $arr_k=>$arr_v){
                                $order =Db::name($arr_k)->where(['examine_status'=>'0'])->find();
                                if($order){
                                    $this->error('你还有未审核的'.$arr_v.'，不可取消审核功能');
                                }
                            }
                        }
                    }
                    if($params_v['id']=='12'){
                        $check = self::checkOrder();
                        if(!$check['status']){
                            $price_taxes = Db::name('kuerp_sets')->where(['id'=>$params_v['id']])->find();
                            $params_v['is_checked'] = isset($params_v['is_checked'])?$params_v['is_checked']:0;
                            $params_v['value'] = isset($params_v['value'])?$params_v['value']:0;
                            if($price_taxes['is_checked']!=$params_v['is_checked']){
                                return ['status'=>400,'msg'=>'你已产生'.$check['msg'].'，不可编辑启用税金'];
                            }
                            if($price_taxes['value']!=$params_v['value']){
                                return ['status'=>400,'msg'=>'你已产生'.$check['msg'].'，不可编辑税金'];
                            }
                        }
                    }
                    if($params_v['id']=='13'){
                        $taxes = 0;
                        foreach ($params as $k=>$v){
                            $v['is_checked'] = isset($v['is_checked'])?$v['is_checked']:0;
                            if($v['id']=='12'){
                                $taxes = $v['is_checked'];
                            }
                        }
                        if($taxes==0){
                            $taxes = Db::name('kuerp_sets')->where(['id'=>'12'])->value('is_checked');
                        }
                        $params_v['is_checked'] = isset($params_v['is_checked'])?$params_v['is_checked']:0;
                        if(empty($taxes) && $params_v['is_checked']==1){
                            return ['status'=>400,'msg'=>'必须启用税金才能设置商品价格是否含税'];
                        }
                        $check = self::checkOrder();
                        if(!$check['status']){
                            $price_taxes = Db::name('kuerp_sets')->where(['id'=>$params_v['id']])->value('is_checked');
                            if($price_taxes!=$params_v['is_checked']){
                                return ['status'=>400,'msg'=>'你已产生'.$check['msg'].'，不可编辑商品价格是否含税'];
                            }
                        }
                    }
                    if(($params_v['id']=='14' || $params_v['id']=='15' || $params_v['id']=='16') && (!isset($params_v['is_checked']) || (isset($params_v['is_checked']) && $params_v['is_checked']==0))){
                        $check = self::checkOrder();
                        $data_name = [
                            '14' => '辅助属性',
                            '15'    => '序列号',
                            '16'     => '批次保质期管理',
                        ];
                        if(!$check['status']){
                            $price_taxes = Db::name('kuerp_sets')->where(['id'=>$params_v['id']])->find();
                            $params_v['is_checked'] = isset($params_v['is_checked'])?$params_v['is_checked']:0;
                            if($price_taxes['is_checked']!=$params_v['is_checked']){
                                return ['status'=>400,'msg'=>'你已产生'.$check['msg'].'，不可关闭'.$data_name[$params_v['id']]];
                            }
                        }
                    }

                    if(isset($params_v['value'])){
                        if(is_array($params_v['value'])){
                            $params[$params_k]['value'] = implode(',',$params_v['value']);
                        }
                        if(!isset($params_v['is_checked'])){
                            $params[$params_k]['is_checked'] = 0;
                        }
                    }else{
                        $params[$params_k]['value'] = '';
                        $params[$params_k]['is_checked'] = 0;
                    }
                }
                foreach ($params as $params_k=>$params_v){
                    $params[$params_k] = $params_v;
                }

                $data = input();
                if(isset($data['agreement']) && isset($data['privacy'])){
                    $agreement = $this->request->post("agreement",'');
                    $privacy = $this->request->post("privacy",'');
                    Db::name('kuerp_config')->where(['name'=>'agreement'])->update(['value'=>$agreement]);
                    Db::name('kuerp_config')->where(['name'=>'privacy'])->update(['value'=>$privacy]);
                }

                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->saveAll($params);
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
        $sets = Db::name('kuerp_sets')->select();
        $this->view->assign("sets", $sets);
        $prefix = Db::name('kuerp_prefix')->select();
        $this->view->assign("prefix", $prefix);
        return $this->view->fetch();
    }
    private static function checkOrder(){
        $arr = [
            'kuerp_goods_purchase_order' => '购货订单',
            'kuerp_goods_purchase' => '购货单',
            'kuerp_goods_exchange' => '购货退货单',
            'kuerp_goods_sales_order' => '销货订单',
            'kuerp_goods_sales' => '销货单',
            'kuerp_goods_sales_exchange' => '销货退货单',
            'kuerp_allocation' => '调拨单',
            'kuerp_warehousing' => '其他入库单',
            'kuerp_warehouse' => '其他出库单',
            'kuerp_assemble' => '组装单',
            'kuerp_split' => '拆卸单',
            'kuerp_payment' => '付款单',
            'kuerp_collection' => '收款单',
            'kuerp_writeoff' => '核销单',
            'kuerp_income' => '其他收入单',
            'kuerp_expenditure' => '其他支出单',
            'kuerp_accounts_transfer' => '资金转账单',
        ];
        foreach ($arr as $arr_k=>$arr_v){
            $order = \think\Db::name($arr_k)->find();
            if($order){
                return ['status'=>false,'msg'=>$arr_v];
            }
        }
        return ['status'=>true,'msg'=>''];
    }

}
