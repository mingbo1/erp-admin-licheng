<?php

namespace addons\kuerp\controller;

use think\addons\Controller;

class Base extends Controller
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        check_cors_request();
    }
}
