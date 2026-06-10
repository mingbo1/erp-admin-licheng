<?php
/**
 * User: 开发者
 * QQ: 1123416584
 * web: blog.hnh117.com
 */

namespace app\admin\controller;


use addons\adminlogin\library\Service;
use app\admin\model\AdminLog;
use app\common\controller\Backend;
use think\Cache;
use think\Config;
use think\Hook;
use think\Lang;
use think\Request;
use think\Session;
use think\Validate;

class Adminlogin extends Backend
{
    protected $noNeedLogin = ['index', 'login'];

    public function __construct(Request $request = null)
    {
        $addonConfig = get_addon_config('adminlogin');
        if ($addonConfig['is_origin']) {
            $this->redirect('index/login');
        }

        parent::__construct($request);
    }


    public function login()
    {
        if (!$this->request->isPost()) {
            $this->error(404);
        }

        AdminLog::setTitle(__('Login'));

        $url = Service::getUrl();

        $addonConfig = get_addon_config('adminlogin');

        if (false == $addonConfig['dev']) {
            if ($this->auth->isLogin()) {
                $this->success(__("You've logged in, do not login again"), $url);
            }
        }

        // 必须带上i用cache来缓存信息，不能用session
        $cacheKey = 'addon_adminlogin_error_'.request()->ip();
        // 错误多少次需要输入验证码
        $maxError = $addonConfig['num'];
        $errorNum = Cache::tag('adminlogin')->get($cacheKey) ?: 0;
        $hasCaptcha = $errorNum >= $maxError;
        $nextHasCaptcha = $errorNum >= $maxError-1;

        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $keeplogin = $this->request->post('keeplogin');
            $token = $this->request->post('__token__');
            $rule = [
                'username'  => 'require|length:3,30',
                'password'  => 'require|length:3,30',
                '__token__' => 'require|token',
            ];
            $data = [
                'username'  => $username,
                'password'  => $password,
                '__token__' => $token,
            ];
            if (Config::get('fastadmin.login_captcha') && $hasCaptcha) {
                $rule['captcha'] = 'require|captcha';
                $data['captcha'] = $this->request->post('captcha');
            }
            $validate = new Validate($rule, [], ['username' => __('Username'), 'password' => __('Password'), 'captcha' => __('Captcha')]);
            $result = $validate->check($data);
            if (!$result) {
                $this->error($validate->getError(), $url, ['token' => $this->request->token()]);
            }
            AdminLog::setTitle(__('Login'));
            $result = $this->auth->login($username, $password, $keeplogin ? 86400 : 0);
            if ($result === true) {
                Cache::tag('adminlogin')->set($cacheKey, 0);
                Hook::listen("admin_login_after", $this->request);
                $this->success(__('Login successful'), $url, ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]);
            } else {
                // 记录密码错误次数
                Cache::tag('adminlogin')->set($cacheKey, $errorNum+1);

                $msg = $this->auth->getError();
                $msg = $msg ? $msg : __('Username or password is incorrect');
                $this->error($msg, $url, ['token' => $this->request->token(), 'has_captcha' => $nextHasCaptcha]);
            }
        }
    }

    /**
     * 管理员登录
     */
    public function index()
    {
        $url = Service::getUrl();

        $addonConfig = get_addon_config('adminlogin');

        if (false == $addonConfig['dev']) {
            if ($this->auth->isLogin()) {
                $this->success(__("You've logged in, do not login again"), $url);
            }
        }

        $templateType = $addonConfig['type'];
        if (true == $addonConfig['dev']) {
            $templateType = input('type', $templateType);
        }

        // 必须带上i用cache来缓存信息，不能用session
        $cacheKey = 'addon_adminlogin_error_'.request()->ip();
        // 错误多少次需要输入验证码
        $maxError = $addonConfig['num'];
        $errorNum = Cache::tag('adminlogin')->get($cacheKey) ?: 0;
        $hasCaptcha = $errorNum >= $maxError;

        if ($this->request->isPost()) {
            $this->error(404);
        }

        // 根据客户端的cookie,判断是否可以自动登录
        if ($this->auth->autologin() && false == $addonConfig['dev']) {
            Session::delete("referer");
            $this->redirect($url);
        }
        $background = Config::get('fastadmin.login_background');
        $background = $background ? (stripos($background, 'http') === 0 ? $background : config('site.cdnurl') . $background) : '';
        $this->view->assign('background', $background);
        $this->view->assign('title', __('Login'));
        Hook::listen("admin_login_init", $this->request);

        $this->view->assign('hasCaptcha', $hasCaptcha);


        $template = 'login';
        if ($templateType > 1) {
            $template = $template.$templateType;
        }

        $templateTypeList = [];
        if (true == $addonConfig['dev']) {
            $templateTypeList = get_addon_fullconfig('adminlogin')[0]['content'];
            $i = 1;
            foreach ($templateTypeList as &$item) {
                $item = "{$i}、{$item}";
                $i++;
            }
        }
        $this->assign('templateTypeList', $templateTypeList);
        $this->assignconfig('hasCaptcha', $hasCaptcha);
        return $this->view->fetch($template);
    }
}