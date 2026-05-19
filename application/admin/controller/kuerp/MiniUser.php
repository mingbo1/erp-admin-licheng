<?php

namespace app\admin\controller\kuerp;

use app\common\controller\Backend;

/**
 * 用户管理
 *
 * @icon fa fa-users
 */
class MiniUser extends Backend
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

        $this->model = new \app\admin\model\kuerp\MiniUser;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("apptypeList", $this->model->getApptypeList());
    }
}
