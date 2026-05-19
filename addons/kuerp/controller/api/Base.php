<?php

namespace addons\kuerp\controller\api;

use think\Db;
use think\addons\Controller;

class Base extends Controller
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        check_cors_request();
        $token = $this->request->header('token');
        if (!$token) {
            $token = $this->request->request('token');
        }
        if ($token) {
            $this->auth->init($token);
        }
    }

    /**
     * 获取当前 mini_user 记录
     */
    protected function getMiniUser()
    {
        if (!$this->auth->isLogin()) {
            return null;
        }
        return \addons\kuerp\model\MiniUser::where('user_id', $this->auth->id)
            ->order('id', 'desc')
            ->find();
    }

    /**
     * 获取当前客户ID
     */
    protected function getCustomerId()
    {
        $miniUser = $this->getMiniUser();
        return $miniUser ? $miniUser->customer_id : 0;
    }

    /**
     * 根据客户等级获取对应价格字段
     */
    protected function getPriceField($customerId)
    {
        if (!$customerId) {
            return 'market_sell_price';
        }
        $levelId = Db::name('kuerp_customers')->where('id', $customerId)->value('customerlevel_id');
        switch ((int)$levelId) {
            case 2:
                return 'wholesale_price';
            case 3:
                return 'vip_price';
            default:
                return 'market_sell_price';
        }
    }
}
