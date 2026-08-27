<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;

/**
 * 首页导航
 *
 * @icon fa fa-th-large
 */
class MiniNav extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $setData = \addons\kuerp\library\Goods::getSetData();
        $this->assign('set', $setData['setList']);
        $this->assignconfig('set', $setData['setList']);
        $this->assign('nowmenu', $setData['nowmenu']);

        $this->model = new \app\admin\model\kuerp\MiniNav;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("linkTypeList", ['page' => __('小程序页面'), 'none' => __('无跳转')]);
    }
}
