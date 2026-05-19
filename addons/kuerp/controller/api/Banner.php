<?php

namespace addons\kuerp\controller\api;

use addons\kuerp\model\MiniBanner;

class Banner extends Base
{
    /**
     * 首页轮播图列表
     * GET /api/kuerp/banner/index
     */
    public function index()
    {
        $list = MiniBanner::where('status', 'normal')
            ->order('sort', 'asc')
            ->order('id', 'desc')
            ->field('id, title, image, link_type, link_url, sort')
            ->select();

        return json(['code' => 0, 'msg' => '成功', 'data' => ['list' => $list]]);
    }
}
