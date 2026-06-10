<?php

namespace addons\kuerp\controller\api;

use addons\kuerp\model\MiniUser;
use app\common\library\Auth;
use think\Db;

class User extends Base
{
    protected $noNeedLogin = ['login','loginByPassword','register','sendSmsCode'];
    protected $noNeedRight = ['*'];
    /**
     * 微信小程序登录
     * POST /api/kuerp/user/login
     */
    public function login()
    {
        $code     = $this->request->post('code');
        $nickname = $this->request->post('nickname', '');
        $avatar   = $this->request->post('avatar', '');
        $mobile   = $this->request->post('mobile', '');

        if (!$code) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }
        $config = Db::name('kuerp_mini_config')
            ->field('id,name,value')
            ->where('name', 'in', ['app_id','app_secret'])
            ->select();
        $wxConfig = [
            'app_id' => $config[0]['value'] ?? '',
            'app_secret' => $config[1]['value'] ?? '',
        ];
        if (empty($wxConfig['app_id']) || empty($wxConfig['app_secret'])) {
            return json(['code' => 1, 'msg' => '请先配置微信小程序参数']);
        }

        $ret = \fast\Http::get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid'      => $wxConfig['app_id'],
            'secret'     => $wxConfig['app_secret'],
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ]);
        $session = json_decode($ret, true);
        if (!$session || !empty($session['errcode'])) {
            return json(['code' => 1, 'msg' => '微信登录失败']);
        }

        $openid     = $session['openid'];
        $unionid    = $session['unionid'] ?? '';
        $sessionKey = $session['session_key'] ?? '';

        $miniUser = MiniUser::where('openid', $openid)->find();

        Db::startTrans();
        try {
            if ($miniUser) {
                // 已有记录，更新 session_key
                $miniUser->session_key = $sessionKey;
                $miniUser->updatetime   = time();
                if ($nickname) {
                    $miniUser->nickname = $nickname;
                }
                if ($avatar) {
                    $miniUser->avatar = $avatar;
                }
                if ($mobile) {
                    $miniUser->mobile = $mobile;
                }
                $miniUser->save();
                $this->auth->direct($miniUser->user_id);
            } else {
                // 首次登录：创建 fa_user + fa_kuerp_customers + fa_kuerp_mini_user
                $auth = Auth::instance();
                $auth->keeptime(86400 * 30);

                $result = \addons\third\library\Service::connect('wechat', [
                    'openid'        => $openid,
                    'unionid'       => $unionid,
                    'access_token'  => $sessionKey,
                    'refresh_token' => '',
                    'apptype'       => 'miniapp',
                ]);
                if (!$result) {
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '登录失败']);
                }

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
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '创建客户失败']);
                }

                MiniUser::create([
                    'user_id'      => $auth->id,
                    'customer_id'  => $customerId,
                    'openid'       => $openid,
                    'unionid'      => $unionid,
                    'apptype'      => 'miniapp',
                    'nickname'     => $nickname,
                    'avatar'       => $avatar,
                    'mobile'       => $mobile,
                    'session_key'  => $sessionKey,
                    'status'       => 'normal',
                    'createdtime'  => time(),
                    'updatetime'   => time(),
                ]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '登录失败: ' . $e->getMessage()]);
        }

        return json(['code' => 0, 'msg' => '登录成功', 'data' => [
            'userinfo'   => $this->auth->getUserinfo(),
            'mini_user'  => $miniUser ?: MiniUser::where('user_id', $this->auth->id)->order('id', 'desc')->find(),
        ]]);
    }

    /**
     * 手机号密码登录
     * POST /api/kuerp/user/login-by-password
     */
    public function loginByPassword()
    {
        $mobile   = $this->request->post('mobile', '');
        $password = $this->request->post('password', '');

        if (!$mobile || !$password) {
            return json(['code' => 1, 'msg' => '请输入手机号和密码']);
        }

        // 通过手机号查找用户
        $user = \app\common\model\User::where('mobile', $mobile)->find();
        if (!$user) {
            return json(['code' => 1, 'msg' => '用户不存在']);
        }

        $ret = $this->auth->login($user->mobile, $password);
        if (!$ret) {
            return json(['code' => 1, 'msg' => $this->auth->getError() ?: '登录失败']);
        }

        $miniUser = MiniUser::where('user_id', $this->auth->id)->find();
        if (!$miniUser) {
            // 创建 mini_user
            $customerId = Db::name('kuerp_customers')->where('customer_mobile', $mobile)->value('id');
            if (!$customerId) {
                $customerCode = \addons\kuerp\library\Goods::getCodes('', 1);
                $customerId = Db::name('kuerp_customers')->insertGetId([
                    'customer_name'    => $user->nickname ?: $mobile,
                    'customer_code'    => $customerCode,
                    'customer_mobile'  => $mobile,
                    'customerlevel_id' => 1,
                    'status'           => 1,
                    'createtime'       => time(),
                    'updatetime'       => time(),
                ]);
            }
            $miniUser = MiniUser::create([
                'user_id'     => $this->auth->id,
                'customer_id' => $customerId,
                'mobile'      => $mobile,
                'apptype'     => 'h5',
                'openid'      => 'h5_' . $this->auth->id,
                'nickname'    => $user->nickname,
                'status'      => 'normal',
                'createdtime' => time(),
                'updatetime'  => time(),
            ]);
        }

        $userinfo = $this->auth->getUserinfo();

        return json(['code' => 0, 'msg' => '登录成功', 'data' => [
            'userinfo'  => $userinfo,
            'mini_user' => $miniUser,
        ]]);
    }

    /**
     * 注册
     * POST /api/kuerp/user/register
     */
    public function register()
    {
        $mobile   = $this->request->post('mobile', '');
        $password = $this->request->post('password', '');
        $nickname = $this->request->post('nickname', '');
        $smsCode  = $this->request->post('sms_code', '');

        if (!$mobile || !$password) {
            return json(['code' => 1, 'msg' => '请填写完整信息']);
        }
        if (strlen($password) < 6) {
            return json(['code' => 1, 'msg' => '密码至少6位']);
        }

        // 检查手机号是否已注册
        $exists = \app\common\model\User::where('mobile', $mobile)->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '该手机号已注册']);
        }

        // SMS 验证码校验（可选）
        if ($smsCode) {
            // TODO: 对接短信服务验证码
        }

        $auth = Auth::instance();
        $auth->keeptime(86400 * 30);

        $result = $auth->register($mobile, $password, '', $mobile, []);
        if (!$result) {
            return json(['code' => 1, 'msg' => $auth->getError() ?: '注册失败']);
        }

        // 创建客户
        $customerCode = \addons\kuerp\library\Goods::getCodes('', 1);
        $customerId = Db::name('kuerp_customers')->insertGetId([
            'customer_name'    => $nickname ?: '用户' . substr($mobile, -4),
            'customer_code'    => $customerCode,
            'customer_mobile'  => $mobile,
            'customerlevel_id' => 1,
            'status'           => 1,
            'createtime'       => time(),
            'updatetime'       => time(),
        ]);

        // 创建 mini_user
        MiniUser::create([
            'user_id'     => $auth->id,
            'customer_id' => $customerId,
            'mobile'      => $mobile,
            'apptype'     => 'h5',
            'openid'      => 'h5_' . $auth->id,
            'nickname'    => $nickname,
            'status'      => 'normal',
            'createdtime' => time(),
            'updatetime'  => time(),
        ]);

        $userinfo = $auth->getUserinfo();
        // $token = Db::name('user_token')->where('user_id', $auth->id)->where('expiretime', '>', time())->order('createtime', 'desc')->value('token');
        // $userinfo['token'] = $token ?: '';

        return json(['code' => 0, 'msg' => '注册成功', 'data' => [
            'userinfo'  => $userinfo,
            'mini_user' => MiniUser::where('user_id', $auth->id)->find(),
        ]]);
    }

    /**
     * 发送短信验证码
     * POST /api/kuerp/user/send-sms-code
     */
    public function sendSmsCode()
    {
        $mobile = $this->request->post('mobile', '');
        if (!$mobile || !preg_match('/^1\d{10}$/', $mobile)) {
            return json(['code' => 1, 'msg' => '请输入正确手机号']);
        }

        // TODO: 对接短信服务商发送验证码
        // 开发环境直接返回 123456
        $code = '123456';

        return json(['code' => 0, 'msg' => '发送成功', 'data' => ['code' => $code]]);
    }

    /**
     * 获取用户信息
     * GET /api/kuerp/user/info
     */
    public function info()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        $miniUser = MiniUser::where('user_id', $this->auth->id)->find();
        if (!$miniUser) {
            return json(['code' => 1, 'msg' => '用户不存在']);
        }

        // 获取客户信息
        $customer = [];
        if ($miniUser->customer_id) {
            $customer = Db::name('kuerp_customers')->find($miniUser->customer_id);
        }

        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'mini_user' => $miniUser,
            'customer'  => $customer,
            'user'      => \app\common\model\User::get($this->auth->id),
        ]]);
    }

    /**
     * 更新用户资料
     * POST /api/kuerp/user/update
     */
    public function update()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        $miniUser = MiniUser::where('user_id', $this->auth->id)->find();
        if (!$miniUser) {
            return json(['code' => 1, 'msg' => '用户不存在']);
        }

        $data = [];
        $nickname = $this->request->post('nickname');
        $avatar   = $this->request->post('avatar');
        $mobile   = $this->request->post('mobile');
        if ($nickname) {
            $data['nickname'] = $nickname;
        }
        if ($avatar) {
            $data['avatar'] = $avatar;
        }
        if ($mobile) {
            $data['mobile'] = $mobile;
        }
        if (!empty($data)) {
            $data['updatetime'] = time();
            $miniUser->save($data);
        }
        return json(['code' => 0, 'msg' => '保存成功', 'data' => ['mini_user' => $miniUser]]);
    }
}
