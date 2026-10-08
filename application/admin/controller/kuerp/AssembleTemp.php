<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 组装单模板
 *
 * @icon fa fa-circle-o
 */
class AssembleTemp extends Backend
{
    protected $noNeedRight = ['gettemp'];
    /**
     * AssembleTemp模型对象
     * @var \app\admin\model\kuerp\AssembleTemp
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set',$setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\AssembleTemp;
        $this->view->assign("businessTypeList", $this->model->getBusinessTypeList());
        $this->view->assign("examineStatusList", $this->model->getExamineStatusList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index($type='')
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
            $type = empty($type)?1:$type;
            $list = $this->model
                    ->with(['admin'])
                    ->where($where)
                    ->where(['business_type'=>$type])
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('admin')->visible(['nickname']);
            }
            $lists = [];
            foreach ($list as $list_k=>$list_v){
                $p_info = Db::name('kuerp_assemble_info_temp')->alias('a')
                    ->join('kuerp_goods g','g.id=a.goods_id')
                    ->where(['a.purchase_id'=>$list_v['id'],'a.type'=>'1'])
                    ->field('a.number,g.goods_name,g.goods_specification_id as spec')->find();
                $info = Db::name('kuerp_assemble_info_temp')->alias('a')
                    ->join('kuerp_goods g','g.id=a.goods_id')
                    ->where(['a.purchase_id'=>$list_v['id'],'a.type'=>'2'])
                    ->field('a.number,g.goods_name,g.goods_specification_id as spec')
                    ->select();
                foreach ($info as $info_k=>$info_v){
                    $row = [];
                    $row['id'] = $list_v['id'];
                    $row['temp_name'] = $list_v['temp_name'];
                    $row['pnumber'] = $p_info['number'];
                    $row['pname'] = $p_info['goods_name'].'_'.$p_info['spec'];
                    $row['number'] = $info_v['number'];
                    $row['name'] = $info_v['goods_name'].'_'.$info_v['spec'];
                    $lists[] = $row;
                }
            }
            $result = array("total" => $list->total(), "rows" => $lists);
            return json($result);
        }
        return $this->view->fetch();
    }

    public function gettemp($id){
        $temp = Db::name('kuerp_assemble_temp')->where(['id'=>$id])->find();
        if(empty($temp)){
            $this->error('未查询到模板信息');
        }
        $info = Db::name('kuerp_assemble_info_temp')->alias('a')
            ->join('kuerp_goods g','g.id=a.goods_id')
            ->where(['a.purchase_id'=>$temp['id']])
            ->field('a.*,g.goods_name')
            ->select();
        if(empty($info)){
            $this->error('未查询到模板信息');
        }else{
            $this->success(['info'=>$info,'temp'=>$temp]);
        }
    }
}
