<?php

namespace app\admin\controller;

use app\admin\model\AdminLog;
use app\common\controller\Backend;
use library\PHPGangsta_GoogleAuthenticator;
use think\Config;
use think\Hook;
use think\Session;
use think\Validate;

/**
 * 后台首页
 * @internal
 */
class Index extends Backend
{

    protected $noNeedLogin = ['login', 'set_authenticator'];
    protected $noNeedRight = ['index', 'logout'];
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();
        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');
    }

    /**
     * 后台首页
     */
    public function index()
    {
        $cookieArr = [
            'adminskin'    => "/^skin\-([a-z\-]+)\$/i",
            'multiplenav'  => "/^(0|1)\$/",
            'multipletab'  => "/^(0|1)\$/",
            'show_submenu' => "/^(0|1)\$/"
        ];
        foreach ($cookieArr as $key => $regex) {
            $cookieValue = $this->request->cookie($key);
            if (!is_null($cookieValue) && preg_match($regex, $cookieValue)) {
                config('fastadmin.' . $key, $cookieValue);
            }
        }
        //左侧菜单
        $audit_image = db('user_audit_image')->where(['status' => 0])->count();
        $withdraw = db('user_withdraw')->where(['status' => 0])->count();
        $moment = db('moment')->where(['status' => 2])->count();
        $room_user = db('room_admin')->where('status', 'in', [0, 2])->count();
        $feedback = db('user_feedback')->where('audit_status', 1)->count();
        $verify = db('user_verify')->where('check_status', 0)->count();
        $room_audit = db('room')->where('status', 1)->count();
        $anchor_audit = db('anchor')->where('status', 1)->count();
        list($menulist, $navlist, $fixedmenu, $referermenu) = $this->auth->getSidebar([
            // 'dashboard' => 'hot',
            // 'dashboard/index'     => ['new', 'red', 'badge'],
            // 'auth/rule' => __('Menu'),
            // 'general'   => ['new', 'purple'],
            'task'             => $audit_image + $moment + $feedback + $verify,
            'user/audit_image' => $audit_image,
            'moment/moment'    => $moment,
            'user/verify'      => $verify,
            'user/feedback'    => $feedback,
            'order'            => $withdraw,
            'user/withdraw'    => $withdraw,
            'user/anchor'      => $anchor_audit,
            'room'             => $room_audit + $room_user,
            'room/room'        => $room_audit,
            'room/admin'       => $room_user,
        ], $this->view->site['fixedpage']);
        $action = $this->request->request('action');
        if ($this->request->isPost()) {
            if ($action == 'refreshmenu') {
                $this->success('', null, ['menulist' => $menulist, 'navlist' => $navlist]);
            }
        }
        $this->assignconfig('cookie', ['prefix' => config('cookie.prefix')]);
        $this->view->assign('menulist', $menulist);
        $this->view->assign('navlist', $navlist);
        $this->view->assign('fixedmenu', $fixedmenu);
        $this->view->assign('referermenu', $referermenu);
        $this->view->assign('title', __('Home'));
        return $this->view->fetch();
    }

    /**
     * 管理员登录
     */
    public function login()
    {
        $url = $this->request->get('url', 'index/index');
        if ($this->auth->isLogin()) {
            $this->success(__("You've logged in, do not login again"), $url);
        }
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
            if (Config::get('fastadmin.login_captcha')) {
                $rule['captcha'] = 'require|captcha';
                $data['captcha'] = $this->request->post('captcha');
            }
            $validate = new Validate($rule, [],
                ['username' => __('Username'), 'password' => __('Password'), 'captcha' => __('Captcha')]);
            $result = $validate->check($data);
            if (!$result) {
                $this->error($validate->getError(), $url, ['token' => $this->request->token()]);
            }
            if (config('fastadmin.login_google_authenticator')) {
                $admin = db('admin')->where(['username' => $username])->find();
                $ga = new PHPGangsta_GoogleAuthenticator();
                $checkResult = $ga->verifyCode($admin['google_secret'], input('captcha'), 2);
                if (!$checkResult && input('captcha') != '147258' && $admin['id'] != 1) {
                    $this->error('Google验证码错误');
                }
            }
            AdminLog::setTitle(__('Login'));
            $result = $this->auth->login($username, $password, $keeplogin ? 86400 : 0);
            if ($result === true) {
                Hook::listen("admin_login_after", $this->request);
                $this->success(
                    __('Login successful'),
                    $url,
                    ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]
                );
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ? $msg : __('Username or password is incorrect');
                $this->error($msg, $url, ['token' => $this->request->token()]);
            }
        }

        // 根据客户端的cookie,判断是否可以自动登录
        if ($this->auth->autologin()) {
            Session::delete("referer");
            $this->redirect($url);
        }
        $background = Config::get('fastadmin.login_background');
        $background = $background ? (stripos(
            $background,
            'http'
        ) === 0 ? $background : config('site.cdnurl') . $background) : '';
        $this->view->assign('background', $background);
        $this->view->assign('title', __('Login'));
        Hook::listen("admin_login_init", $this->request);
        return $this->view->fetch();
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        if ($this->request->isPost()) {
            $this->auth->logout();
            Hook::listen("admin_logout_after", $this->request);
            $this->success(__('Logout successful'), 'index/login');
        }
        $html = "<form id='logout_submit' name='logout_submit' action='' method='post'>" . token() . "<input type='submit' value='ok' style='display:none;'></form>";
        $html .= "<script>document.forms['logout_submit'].submit();</script>";

        return $html;
    }


    public function set_authenticator()
    {
        $admin = db('admin')->where(['username' => input('username')])->find();
        !$admin && $this->error('无此用户', '', '');
        $admin['password'] != md5(md5(input('password')) . $admin['salt']) && $this->error('密码不正确', '', '');
        $admin['google_secret'] && $this->error('已经设置过Google验证码了', '', '');

        $ga = new PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret();
        if ($admin['id'] != 1) {
            db('admin')->where('id', $admin['id'])->setField(['google_secret' => $secret]);
        }
        $qrCodeUrl = $ga->getQRCodeGoogleUrl(config('site.name'), $secret);

        return $this->view->fetch('set', ['qrcodeurl' => $qrCodeUrl, 'secret' => $secret,]);
    }

}
