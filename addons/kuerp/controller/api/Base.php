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
        $customer_id = $miniUser ? $miniUser->customer_id : 0;
        //检查客户是否存在
        if ($customer_id) {
            $customer = Db::name('kuerp_customers')->where('id', $customer_id)->find();
            if (!$customer) {
                $customer_id = 0;
            }
        }
        return $customer_id;
    }
    public function createCustomer($nickname = '',$mobile = '')
    {
         // 在 fa_kuerp_customers 创建客户
         $customerCode = \addons\kuerp\library\Goods::getCodes('', 1);
       $customerData = [
                    'customer_name'     => $nickname ?: '微信用户',
                    'customer_code'     => $customerCode,
                    'customertype_id' =>1,
                    'customerlevel_id'  => 1,
                    'customer_mobile'   => $mobile,
                    'status'            => 1,
                    'createtime'        => time(),
                    'updatetime'        => time(),
                ];
                $customerId = Db::name('kuerp_customers')->insertGetId($customerData);
        if (!$customerId) {
            // 创建客户
            $this->createCustomer();
        }
        return $customerId;
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
