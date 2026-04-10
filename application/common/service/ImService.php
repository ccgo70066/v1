<?php

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\library\Yunxin;
use app\common\model\ChannelBlacklist;
use app\common\model\Room;
use app\common\model\Union;


/**
 * Im服务类
 */
class ImService
{
    public Yunxin $im;
    //系统消息ID
    const SYS_ID = 'sys';
    //客服ID
    public static $KF_IDS = ['kf_001'];
    //排麦列表人数刷新
    const ROOM_MIC_QUEUE_REFRESH = 4001;
    //聊天室送礼公屏消息
    const ROOM_GIVE_GIFT_MESSAGE = 4003;
    //聊天室在线人数刷新
    const ROOM_ONLINE_USER_REFRESH = 4004;
    //聊天室热力值刷新
    const ROOM_HOT_REFRESH = 4005;
    //聊天室送礼通知
    const ROOM_GIVE_GIFT_NOTICE = 4006;
    //聊天室管理角色信息变更
    const ROOM_USER_ROLE_REFRESH = 4007;
    //聊天室一键打赏消息
    const ROOM_GIVE_BAG_GIFT_ALL_MESSAGE = 4008;
    //私聊消息体消息类型
    const CHAT_MESSAGE_TEXT = 'text';   //文本
    const CHAT_MESSAGE_IMAGE = 'image'; //图片
    const CHAT_MESSAGE_AUDIO = 'audio'; //音频
    const CHAT_MESSAGE_GIFT = 'gift';   //送礼
    const CHAT_MESSAGE_RED_PACKET = 'red_packet';   //红包
    const CHAT_MESSAGE_ROOM_SHARE = 'room_share';  //邀请房间
    const CHAT_MESSAGE_TYPE_RANGE = [
        self::CHAT_MESSAGE_TEXT,
        self::CHAT_MESSAGE_IMAGE,
        self::CHAT_MESSAGE_AUDIO,
        self::CHAT_MESSAGE_GIFT,
        self::CHAT_MESSAGE_RED_PACKET,

    ];

    public function __construct()
    {
        $this->im = new Yunxin();
    }


    /**
     * 获取IM黑名单
     * @param $user_id
     * @return array
     */
    public function blacklist($user_id)
    {
        return $this->im->listBlackAndMuteList($user_id);
    }

    public function setBlacklist($user_id, $to_user_id, $type)
    {
        return $this->im->set_blacklist($user_id, $to_user_id, $type);
    }

    /**
     * 验证聊天权限
     * @return array ['发送人昵称','发送人头像','接收人昵称','接收人头像']
     */
    public function checkChatAuth($user_id, $to_user_id, $type)
    {
        //发送人为系统账号
        if (in_array($user_id, self::$KF_IDS) || $user_id === self::SYS_ID) {
            $to_user = db('user')->where('id', $to_user_id)->field('avatar,nickname')->find();
            return ['', '', $to_user['nickname'], $to_user['avatar']];
        }
        //接收人为系统账号
        if (in_array($to_user_id, self::$KF_IDS, true) || $to_user_id === self::SYS_ID) {
            $user = db('user')->where('id', $user_id)->field('avatar,nickname')->find();
            return [$user['nickname'], $user['avatar'], '', ''];
        }
        if ($user_id == $to_user_id) {
            throw new ApiException(__('Cannot send to yourself'));
        }
        if (in_array($to_user_id, $this->blacklist($user_id))) {
            throw new ApiException(__('You have blocked this user, cannot send message!'));
        }
        if (in_array($user_id, $this->blacklist($to_user_id))) {
            throw new ApiException(__('You have been blocked by this user, cannot send message!'));
        }
        $user = db('user')->where('id', $user_id)->where('status', 'normal')->find();
        $to_user = db('user')->where('id', $to_user_id)->where('status', 'normal')->find();
        if (!$user) {
            throw new ApiException(__('Account abnormality!'));
        }
        if (!$to_user) {
            throw new ApiException(__('Opposite party account abnormality!'));
        }

        $list = ChannelBlacklist::get_blacklist($to_user['package_appid'], $to_user['system'], $to_user['version']);
        if (in_array(ChannelBlacklist::ITEM_CHAT, $list)) {
            //throw new ApiException('对方尚未实名认证');
        }

        if (in_array($type, [self::CHAT_MESSAGE_GIFT, self::CHAT_MESSAGE_RED_PACKET])) {
            return [$user['nickname'], $user['avatar'], $to_user['nickname'], $to_user['avatar']];
        }
        $is_union_user = db('union_user')
            ->where('user_id = ' . $user_id . ' or user_id = ' . $to_user_id)
            ->where('status', 'in', Union::STATUS_JOINED_RANGE)
            ->find();
        if ($is_union_user) {
            return [$user['nickname'], $user['avatar'], $to_user['nickname'], $to_user['avatar']];
        }
        throw new ApiException(__('This feature is not unlocked'));
    }


    /**
     * 红包消息
     * @param $user_id
     * @param $to_user_id
     * @param $amount
     * @param $remark
     * @return array|bool
     * @throws ApiException
     */
    public function sendRedPacketMessage($user_id, $to_user_id, $amount, $remarks)
    {
        $content = [
            'amount'  => $amount,
            'remarks' => $remarks,
        ];
        return $this->sendChatMessageByUser($user_id, $to_user_id, self::CHAT_MESSAGE_RED_PACKET, $content);
    }


    /**
     * 发送私聊消息
     * @param $user_id
     * @param $to_user_id
     * @param $type
     * @param $content
     * @return bool
     * @throws
     */
    public function sendChatMessageByUser($user_id, $to_user_id, $type, $content)
    {
        if (!in_array($type, self::CHAT_MESSAGE_TYPE_RANGE)) {
            throw new ApiException(__('Message type not recognized'));
        }

        [$nickname, $avatar, $to_nickname, $to_avatar] = $this->checkChatAuth($user_id, $to_user_id, $type);
        $body = [
            'type'     => $type,
            'content'  => $content,
            'sender'   => ['nickname' => $nickname, 'avatar' => $avatar],
            'receiver' => ['nickname' => $to_nickname, 'avatar' => $to_avatar],
        ];
        return $this->im->send_msg($user_id, $to_user_id, $body);
    }

    //房间送礼通知(前端刷新热力值和麦上统计)
    public function roomGiveGiftNotice($room_id, $hot)
    {
        $roomModel = new Room();
        $roomService = new RoomService();
        $im_room_id = $roomModel->getImRoomId($room_id);
        return $this->im->room_send_notice($im_room_id, [
            'type'  => self::ROOM_GIVE_GIFT_NOTICE,
            'hot'   => $hot,
            'pause' => $roomService->getSeatGiftValue($room_id)
        ]);
    }

    //房间发送消息
    public function roomSendMessage($room_id, $message)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);

        return $this->im->room_send_message($im_room_id, $message);
    }

    //房间发送通知(通知或不会保留在公屏历史中的消息)
    public function roomSendNotice($room_id, $message)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        return $this->im->room_send_notice($im_room_id, $message);
    }

    //房间普通送礼消息(前端展示于公屏)
    public function roomGiveGiftMessage($room_id, $user_id, $to_user_ids, $gift_id, $count)
    {
        $roomModel = new Room();
        $gift_info = db('gift')->where('id', $gift_id)->field("name,price,animate,image,{$count} as count")->find();
        $gift_info['price'] = (string)($gift_info['price'] + 0);
        $im_room_id = $roomModel->getImRoomId($room_id);
        $user = db('user')->where('id', $user_id)->field('id,nickname,avatar')->find();
        $to_users = db('user')->where('id', 'in', $to_user_ids)->field('id,nickname,avatar')->select();
        $amount_sum = $gift_info['price'] * $count * count($to_user_ids);

        $body = [
            'type'       => self::ROOM_GIVE_GIFT_MESSAGE,
            'user'       => $user,
            'to_user'    => $to_users,
            'amount_sum' => $amount_sum,
            'gift'       => $gift_info,

        ];
        return $this->im->room_send_message($im_room_id, $body);
    }


    //房间一键清包送礼消息(前端展示于公屏)
    public function roomGiveGiftAllMessage($room_id, $user_id, $to_user_id, $gift_info)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        $user = db('user')->where('id', $user_id)->field('id,nickname,avatar')->find();
        $to_users = db('user')->where('id', $to_user_id)->field('id,nickname,avatar')->find();
        $amount_sum = array_sum(array_column($gift_info, 'gift_val'));

        $body = [
            'type'       => self::ROOM_GIVE_BAG_GIFT_ALL_MESSAGE,
            'user'       => $user,
            'to_user'    => $to_users,
            'amount_sum' => $amount_sum,
            'gift'       => $gift_info,

        ];
        \think\Log::error($body);
        return $this->im->room_send_message($im_room_id, $body);
    }


    //创建IM用户
    public function createUser($user_id, $nickname, $avatar)
    {
        return $this->im->create_user($user_id, $nickname, $avatar);
    }

    //更新资料
    public function updateUser($user_id, $nickname, $avatar)
    {
        return $this->im->update_user($user_id, ['nickname' => $nickname, 'avatar' => $avatar]);
    }

    /**
     * 设置IM聊天室权限
     * @param $room_id int 房间ID
     * @param $user_id int 被操作者
     * @param $type    bool true=赋予权限(房主、房管、陪陪),false=取消权限
     */
    public function roomSetAuth($room_id, $user_id, $type)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        $operator = db('room')->where('id', $room_id)->value('owner_id');
        return $this->im->set_room_role($operator, $user_id, $im_room_id, $type);
    }

    //创建房间
    public function createRoom($master, $room_name, $announcement = '')
    {
        return $this->im->room_create($master, $room_name, $announcement);
    }

    /**
     * 关闭或开启聊天室
     * @param $room_id  int 房间ID
     * @param $valid    bool true=开启,false=关闭
     */
    public function roomSetSwitch($room_id, $valid)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        $operator = db('room')->where('id', $room_id)->value('owner_id');
        return $this->im->room_valid($im_room_id, $operator, $valid);
    }

    /**
     * 删除排麦人员
     * @ApiInternal
     * @param $room_id
     * @param $user_id
     */
    public function room_wait_mic_delete($room_id, $user_id)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        $key = '1#' . $user_id;
        return $this->im->queuePoll($im_room_id, $key);
    }

    /**
     * 删除云信队列中所有的排麦元素
     * @ApiInternal
     * @param $room_id
     * @param $user_id
     */
    public function room_wait_mic_delete_all($room_id)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        $result = $this->get_room_wait_mic($room_id);
        if ($result) {
            foreach ($result['desc']['list'] as $k => $v) {
                foreach ($v as $kk => $vv) {
                    $k_arr = explode('#', $kk);
                    if (count($k_arr) == 2 && $k_arr[0] == 1) {
                        $key = '1#' . $k_arr[1];
                        $this->im->queuePoll($im_room_id, $key);
                    }
                }
            }
        }
    }

    //查询用户在房间的状况，可看是否在房间
    public function room_query_user($room_id, $user_ids_arr)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        return $this->im->queryMembers($im_room_id, $user_ids_arr);
    }

    //踢用户出房间
    public function room_kick_user($room_id, $to_user_id, $extend = '')
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        $operator = db('room')->where('id', $room_id)->value('owner_id');
        return $this->im->kickMember($operator, $im_room_id, $to_user_id, $extend);
    }

    public function get_room_wait_mic($room_id)
    {
        $roomModel = new Room();
        $im_room_id = $roomModel->getImRoomId($room_id);
        return $this->im->queueList($im_room_id);
    }

    //发送短信验证码
    public function send_sms($phone, $code)
    {
        return $this->im->sendSMS($phone, $code);
    }
}
