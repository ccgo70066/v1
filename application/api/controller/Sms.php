<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\Hook;

/**
 * 手机短信接口
 */
class Sms extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     * 发送验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     */
    public function send()
    {
        $mobile = $this->request->post("mobile");
        $event = $this->request->post("event");
        $event = $event ? $event : 'register';

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            //$this->error(__('Incorrect mobile phone number'));
        }
        $last = Smslib::get($mobile, $event);
        if ($last && time() - $last['createtime'] < 60) {
            $this->error(__('Send frequently'));
        }
        $ipSendTotal = \app\common\model\Sms::where(['ip' => $this->request->ip()])->whereTime('createtime', '-1 hours')->count();
        if ($ipSendTotal >= 5) {
            $this->error(__('Send frequently'));
        }
        if ($event) {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('has been registered'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('Occupied'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('Not registered'));
            }
        }
        if (!Hook::get('sms_send')) {
            //$this->error(__('No sms hook registered'));
        }
        $ret = Smslib::send($mobile, null, $event);
        if ($ret) {
            $this->success($ret);
        } else {
            $this->error();
        }
    }

    /**
     * 检测验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function check()
    {
        $mobile = $this->request->post("mobile");
        $event = $this->request->post("event");
        $event = $event ? $event : 'register';
        $captcha = $this->request->post("captcha");

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Incorrect mobile phone number'));
        }
        if ($event) {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('has been registered'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('Occupied'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('Not registered'));
            }
        }
        $ret = Smslib::check($mobile, $captcha, $event);
        if ($ret) {
            $this->success();
        } else {
            $this->error();
        }
    }
}
