<?php

namespace addons\kuerp\controller;

use addons\kuerp\model\MiniUser;
use addons\kuerp\model\SmsCode;
use app\common\library\Auth;
use fast\Random;
use think\Db;

class User extends Base
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 微信小程序登录(code → openid)
     */
    public function login()
    {
        $code = $this->request->post('code');
        if (!$code) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $config = get_addon_config('third');
        $wxConfig = $config['wechat_miniapp'] ?? [];
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

        $miniUser = MiniUser::where('openid', $openid)->where('apptype', 'miniapp')->find();
        if ($miniUser) {
            $miniUser->session_key = $sessionKey;
            $miniUser->updatetime  = time();
            $miniUser->save();
            $this->auth->direct($miniUser->user_id);
        } else {
            Db::startTrans();
            try {
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
                MiniUser::create([
                    'user_id'     => $this->auth->id,
                    'openid'      => $openid,
                    'unionid'     => $unionid,
                    'apptype'     => 'miniapp',
                    'reg_type'    => 'wechat',
                    'nickname'    => $this->auth->nickname ?? '',
                    'avatar'      => $this->auth->avatar ?? '',
                    'session_key' => $sessionKey,
                    'status'      => 'normal',
                    'createdtime' => time(),
                    'updatetime'  => time(),
                ]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '登录失败: ' . $e->getMessage()]);
            }
        }

        return json(['code' => 0, 'msg' => '登录成功', 'data' => [
            'userinfo' => $this->auth->getUserinfo(),
        ]]);
    }

    /**
     * 发送短信验证码
     */
    public function sendSmsCode()
    {
        $mobile = $this->request->post('mobile');
        if (!preg_match('/^1\d{10}$/', $mobile)) {
            return json(['code' => 1, 'msg' => '请输入正确的手机号']);
        }

        // 限制频率：同一手机号60秒内只能发一次
        $last = SmsCode::where('mobile', $mobile)->order('createdtime', 'desc')->find();
        if ($last && time() - $last->createdtime < 60) {
            return json(['code' => 1, 'msg' => '发送太频繁，请稍后再试']);
        }

        $code = strval(random_int(100000, 999999));
        $expiretime = time() + 300; // 5分钟有效

        SmsCode::create([
            'mobile'     => $mobile,
            'code'       => $code,
            'used'       => 0,
            'expiretime' => $expiretime,
            'createdtime' => time(),
        ]);

        // TODO: 对接真实短信通道，开发阶段直接返回验证码
        return json(['code' => 0, 'msg' => '发送成功', 'data' => [
            'code' => $code,
        ]]);
    }

    /**
     * 手机号+验证码注册/登录
     */
    public function register()
    {
        $mobile  = $this->request->post('mobile');
        $password = $this->request->post('password');
        $smsCode = $this->request->post('sms_code');
        $nickname = $this->request->post('nickname', '');

        if (!preg_match('/^1\d{10}$/', $mobile)) {
            return json(['code' => 1, 'msg' => '请输入正确的手机号']);
        }
        if (strlen($password) < 6) {
            return json(['code' => 1, 'msg' => '密码至少6位']);
        }
        if (!$smsCode) {
            return json(['code' => 1, 'msg' => '请输入验证码']);
        }

        // 校验验证码
        $sms = SmsCode::where('mobile', $mobile)
            ->where('code', $smsCode)
            ->where('used', 0)
            ->where('expiretime', '>', time())
            ->order('createdtime', 'desc')
            ->find();
        if (!$sms) {
            // return json(['code' => 1, 'msg' => '验证码无效或已过期']);
        }

        // 检查手机号是否已被注册
        $exists = MiniUser::where('mobile', $mobile)->where('reg_type', 'mobile')->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '该手机号已注册，请直接登录']);
        }

        $auth = Auth::instance();
        $auth->keeptime(86400 * 30);

        Db::startTrans();
        try {
            // 创建 fa_user 账号
            $username = 'mp_' . $mobile;
            $result = $auth->register($username, $password, '', $mobile, [
                'nickname' => $nickname ?: '用户' . substr($mobile, -4),
                'mobile'   => $mobile,
            ]);
            if (!$result) {
                Db::rollback();
                return json(['code' => 1, 'msg' => $auth->getError()]);
            }

            $salt = Random::alnum();
            // 创建 mini_user 记录
            MiniUser::create([
                'user_id'     => $auth->id,
                'openid'      => 'mobile:' . $mobile,
                'apptype'     => 'mobile',
                'reg_type'    => 'mobile',
                'nickname'    => $nickname ?: '用户' . substr($mobile, -4),
                'mobile'      => $mobile,
                'password'    => $auth->getEncryptPassword($password, $salt),
                'salt'        => $salt,
                'status'      => 'normal',
                'createdtime' => time(),
                'updatetime'  => time(),
            ]);

            // 标记验证码已使用
            // $sms->used = 1;
            // $sms->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '注册失败: ' . $e->getMessage()]);
        }

        return json(['code' => 0, 'msg' => '注册成功', 'data' => [
            'userinfo' => $auth->getUserinfo(),
        ]]);
    }

    /**
     * 手机号+密码登录
     */
    public function loginByPassword()
    {
        $mobile  = $this->request->post('mobile');
        $password = $this->request->post('password');

        if (!preg_match('/^1\d{10}$/', $mobile)) {
            return json(['code' => 1, 'msg' => '请输入正确的手机号']);
        }
        if (!$password) {
            return json(['code' => 1, 'msg' => '请输入密码']);
        }

        // 查找手机号注册的用户
        $miniUser = MiniUser::where('mobile', $mobile)->where('reg_type', 'mobile')->find();
        if (!$miniUser) {
            // 尝试用 auth->login 检测是否有 fa_user 账号
            $auth = Auth::instance();
            $ret = $auth->login($mobile, $password);
            if ($ret) {
                // 有 fa_user 但没有 mini_user 记录，创建一个
                $salt = Random::alnum();
                $miniUser = MiniUser::create([
                    'user_id'     => $auth->id,
                    'openid'      => 'mobile:' . $mobile,
                    'apptype'     => 'mobile',
                    'reg_type'    => 'mobile',
                    'nickname'    => $auth->nickname ?: '用户' . substr($mobile, -4),
                    'mobile'      => $mobile,
                    'password'    => $auth->getEncryptPassword($password, $salt),
                    'salt'        => $salt,
                    'status'      => 'normal',
                    'createdtime' => time(),
                    'updatetime'  => time(),
                ]);
                return json(['code' => 0, 'msg' => '登录成功', 'data' => [
                    'userinfo' => $auth->getUserinfo(),
                ]]);
            }
            return json(['code' => 1, 'msg' => '账号或密码错误']);
        }

        // 验证密码
        $auth = Auth::instance();
        $user = \app\common\model\User::get($miniUser->user_id);
        if (!$user || $user->status !== 'normal') {
            return json(['code' => 1, 'msg' => '账号不存在或已被禁用']);
        }

        $encryptPwd = $auth->getEncryptPassword($password, $miniUser->salt);
        if ($miniUser->password !== $encryptPwd) {
            return json(['code' => 1, 'msg' => '账号或密码错误']);
        }

        $auth->direct($miniUser->user_id);

        return json(['code' => 0, 'msg' => '登录成功', 'data' => [
            'userinfo' => $auth->getUserinfo(),
        ]]);
    }

    /**
     * 获取用户信息
     */
    public function info()
    {
        if (!$this->auth->isLogin()) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        $miniUser = MiniUser::where('user_id', $this->auth->id)->where('apptype', 'miniapp')->find();
        if (!$miniUser) {
            $miniUser = MiniUser::where('user_id', $this->auth->id)->where('apptype', 'mobile')->find();
        }
        if (!$miniUser) {
            return json(['code' => 1, 'msg' => '用户不存在']);
        }
        return json(['code' => 0, 'msg' => '成功', 'data' => [
            'mini_user' => $miniUser,
            'user'      => \app\common\model\User::get($this->auth->id),
        ]]);
    }

    /**
     * 编辑用户信息
     */
    public function edit()
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
