<?php

namespace app\api\controller;

use app\api\library\ImService;
use app\api\library\RedisService;

/**
 * IM聊天
 */
class Im extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    public function __construct()
    {
        parent::__construct();
        $this->service = new ImService();
    }

    /**
     * @ApiTitle    (发送私聊消息)
     * @ApiMethod   (post)
     * @ApiParams   (name="to_user_id", type="string", required=true, rule="", description="接收用户id")
     * @ApiParams   (name="content", type="string", required=true, rule="", description="文本或文件地址")
     * @ApiParams   (name="type", type="string", required=true, rule="", description="消息类型:text=文本,img=图片,audio=录音")
     **/
    public function send_message()
    {
        $to_user_id = input('to_user_id');
        $type = input('type');
        $content = input('content');
        $result = $this->service->sendChatMessageByUser($this->auth->id, $to_user_id, $type, $content);
        if (!$result) {
            $this->error(__('Operation failed'));
        }
        $this->error(__('Operation completed'));
    }

    /**
     * @ApiTitle    (客服自动消息,在打开客服聊天界面时调用)
     * @ApiMethod   (get)
     **/
    public function customer_service_notice()
    {
        $get_site_config = RedisService::loadLang(get_site_config('automatic_response'));
        $this->success('', ['text' => $get_site_config]);
    }

    /**
     * @ApiTitle    (充值客服自动消息)
     * @ApiMethod   (get)
     **/
    public function recharge_service_notice()
    {
        $redis = redis();
        $user_id = $this->auth->id;
        $fk_id = config('app.recharge_kf_id');
        $key = 'recharge.notice:';
        //已通知
        if ($redis->get($key . $user_id)) {
            $this->success();
        }
        //发送充值消息到用户
        $content = __('您好，请问您在充值过程中遇到过什么问题吗，有什么可以为您服务的吗？');
        $result = $this->service->sendChatMessageByUser($fk_id, $user_id, ImService::CHAT_MESSAGE_TEXT, $content);
        if ($result) {
            $redis->set($key . $user_id, 1, strtotime('tomorrow') - time());
        }
        $this->success();
    }

    /**
     * @ApiTitle    (获取充值客服云信Id)
     * @ApiMethod   (get)
     **/
    public function get_recharge_service()
    {
        $this->success('', [
            'id'     => config('app.recharge_kf_id'),
            'name'   => '充值客服',
            'avatar' => 'assets/icon/kf.png'
        ]);
    }
}
