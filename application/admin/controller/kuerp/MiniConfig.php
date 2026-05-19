<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;
use think\Db;

/**
 * 系统配置
 *
 * @icon fa fa-cog
 */
class MiniConfig extends Backend
{
    protected $model = null;

    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\MiniConfig;
    }

    /**
     * 查看配置列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = Db::name('kuerp_mini_config')
                ->field('id,name,value,description')
                ->order('id', 'asc')
                ->select();
            $total = Db::name('kuerp_mini_config')->count();
            return json(['code' => 0, 'msg' => '', 'total' => $total, 'rows' => $list]);
        }
        return $this->view->fetch();
    }

    /**
     * 编辑配置
     */
    public function edit($ids = '')
    {
        $row = Db::name('kuerp_mini_config')->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('配置不存在'));
        }

        if ($this->request->isPost()) {
            $value = $this->request->post('value');
            Db::name('kuerp_mini_config')->where('id', $ids)->update([
                'value'      => $value,
                'updatetime' => time(),
            ]);
            $this->success(__('保存成功'));
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }
}
