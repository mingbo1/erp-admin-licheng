<?php

namespace addons\kuerp\controller\api;

use addons\kuerp\model\MiniNav;

class Nav extends Base
{
    /**
     * 首页中部导航列表
     * GET /api/kuerp/nav/index
     */
    public function index()
    {
        $list = MiniNav::where('status', 'normal')
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->field('id, title, image, link_type, link_url, sort')
            ->select();

        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }
}
