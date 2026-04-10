<?php

namespace app\common\library;

use think\Env;

/**
 * 网易云信接口
 */
class Yunxin
{
    private $app_key;
    private $app_secret;
    private $server;//国内应用

    //系统Im账号
    public $account = [
        'recharge' => 'recharge', //充值消息
        'sys'      => 'sys',      //系统消息号
        'kf_id'    => ''

    ];

    public function __construct()
    {
        if (Env::get('app.server') == 'prod') {
            $this->app_key = Env::get('prod.yunxinAppKey');
            $this->app_secret = Env::get('prod.yunxinAppSecret');
            $this->server = Env::get('prod.yunxinServer');
        } else {
            $this->app_key = Env::get('test.yunxinAppKey');
            $this->app_secret = Env::get('test.yunxinAppSecret');
            $this->server = Env::get('test.yunxinServer');
        }
        $this->account['kf_id'] = config('app.kf_id');
    }

    /*---------------------消息-------------------------------*/

    /**
     * 用户之间私聊消息
     * @param $from
     * @param $to
     * @param $data
     * @param $type //消息体
     * @return mixed
     */
    public function send_msg($from, $to, $data)
    {
        $url = "/msg/sendMsg.action";
        $imData = [
            "from" => $from,
            "ope"  => "0",
            "to"   => $to,
            "type" => '100',
            "body" => json_encode($data)
        ];
        return $this->im($url, $imData);
    }


    /**
     * 聊天室消息(再次进入聊天室的时候会拉取到历史消息的消息)
     * @param $room_id string IM房间号
     * @param $data      array
     * @return mixed
     */
    public function room_send_message($room_id, $data = [])
    {
        $body = $data;
        $body['type_room_msg'] = $data['type'];

        $url = "/chatroom/sendMsg.action";
        $imData = [
            'roomid'    => $room_id,
            'msgId'     => \fast\Random::alnum(10),
            'fromAccid' => $this->account['sys'],//随便哪个号码都行,只要注册过云信
            'msgType'   => '100',//这种消息能拉取到历史消息记录
            'attach'    => json_encode(['msg' => $body])
        ];
        return $this->im($url, $imData);
    }

    /**
     * 聊天室通知
     * @param $room_id string IM房间号
     * @param $data      array 数据,'type'=>400X房间信息更新相关,500X=>PK相关
     * @return mixed
     */
    public function room_send_notice($room_id, $data = [])
    {
        $url = "/chatroom/sendMsg.action";
        $imData = [
            'roomid'    => $room_id,
            'msgId'     => \fast\Random::alnum(10),
            'fromAccid' => $this->account['sys'], //随便哪个号码都行,只要注册过云信
            'msgType'   => '10',//这种消息不需要拉取到历史消息记录
            'attach'    => json_encode($data)
        ];

        return $this->im($url, $imData);
    }

    /*---------------------用户---------------------------*/

    /**注册IM用户
     * @param $id   string user_id
     * @param $name string  昵称
     * @param $icon string  头像
     */
    public function create_user($id, $nickname, $avatar)
    {
        $url = "/user/create.action";
        $data = ['accid' => $id, 'name' => $nickname, 'icon' => $avatar];

        return $this->im($url, $data);
    }


    /**
     * 更新IM用户资料
     * @param $id   string  user_id
     * @param $data array  更新的数据
     */
    public function update_user($id, $data)
    {
        $url = "/user/updateUinfo.action";

        $update = [];
        $update['accid'] = $id;
        if (isset($data['nickname']) && $data['nickname'] != '') {
            $update['name'] = $data['nickname'];
        }
        if (isset($data['avatar']) && $data['avatar'] != '') {
            $update['icon'] = $data['avatar'];
        }
        if (isset($data['gender']) && $data['gender'] != '') {
            if ($data['gender'] == 1) {
                $update['gender'] = 1;  //男
            } else {
                $update['gender'] = 2;  //女
            }
        }
        if (isset($data['extend']) && $data['extend'] != '') {
            $update['ex'] = $data['extend'];
        }
//        print_r($update);die;
        return $this->im($url, $update);
    }

    /**
     * 获取用户信息（可批量获取）
     * @param array $ids 用户id  [1,2,3]
     * @return mixed
     */
    public function get_user(array $ids)
    {
        $url = "/user/getUinfos.action";
        $data = ['accids' => json_encode($ids)];
        return $this->im($url, $data);
    }

    /**
     * 重置im_token
     * @param $user_id
     * @return mixed
     */
    public function refresh_token($user_id)
    {
        $url = "/user/refreshToken.action";
        $data = ['accid' => $user_id];
        return $this->im($url, $data);
    }

    /**
     * 更新用户imtoken为指定值(用于客服)
     * @param $user_id
     * @return mixed
     */
    public function update_token($user_id, $token)
    {
        $url = "/user/update.action";
        $data = ['accid' => $user_id, 'token' => $token];
        return $this->im($url, $data);
    }

    /**
     * 拉入或取消黑名单
     * @param $user_id
     * @param $to_user_id
     * @param $type 1=拉黑，0=取消拉黑
     */
    public function set_blacklist($user_id, $to_user_id, $type)
    {
        $url = "/user/setSpecialRelation.action";
        $data = [
            'accid'        => $user_id,
            'targetAcc'    => $to_user_id,
            'relationType' => 1,
            'value'        => (string)$type
        ];
        return $this->im($url, $data);
    }


    /*---------------------房间---------------------------*/


    /** 创建IM房间
     * @param $id           int 创建者user_id
     * @param $name         string 房间名称
     * @param $announcement string 房间公告
     * @return mixed
     */
    public function room_create($id, $name, $announcement = '')
    {
        $url = '/chatroom/create.action';
        $data = ['creator' => $id, 'name' => $name, 'announcement' => $announcement];

        return $this->im($url, $data);
    }

    /** 批量查询聊天室信息
     * @param $id int 创建者user_id
     * @return mixed
     */
    public function room_list(array $roomids)
    {
        $url = '/chatroom/getBatch.action';
        $data = ['roomids' => json_encode($roomids), 'needOnlineUserCount' => 'true'];

        return $this->im($url, $data);
    }

    /**修改聊天室开/关闭状态
     * @param $room_id string IM房间号
     * @param $creator   int 房间创建者user_id，必须是创建者才可以操作
     * @param $valid     boolean true或false，false:关闭聊天室；true:打开聊天室
     * @return mixed
     */
    public function room_valid($room_id, $creator, $valid)
    {
        $valid = $valid ? 'true' : 'false';

        $url = '/chatroom/toggleCloseStat.action';
        $data = ['roomid' => $room_id, 'operator' => $creator, 'valid' => $valid];
        return $this->im($url, $data);
    }


    /**
     * 云信消息记录
     * @param string $user_id    用户
     * @param int    $to_user_id 用户
     * @param int    $start_time 记录区间开始时间戳
     * @param int    $start_time 记录区间结束时间戳
     * @param int    $limit      条数
     * @return mixed
     */
    public function msg_log($user_id, $to_user_id, $start_time, $end_time, $limit = 10)
    {
        $url = "/history/querySessionMsg.action";
        $imData = [
            "from"      => $user_id,
            'to'        => $to_user_id,
            'begintime' => $start_time * 1000,    //微妙
            'endtime'   => $end_time * 1000,
            'limit'     => $limit
        ];

        return $this->im($url, $imData);
    }

    /**
     * 聊天室云端历史消息查询
     * @param int    $room_id 云信聊天室ID
     * @param int    $time      查询的时间戳锚点。reverse=1时timetag为起始时间戳，reverse=2时timetag为终止时间戳
     * @param string $user_id   发送人
     * @param int    $limit     条数
     * @return mixed
     */
    public function queryChatroomMsg($room_id, $time, $user_id)
    {
        $url = "/history/queryChatroomMsg.action";
        $imData = [
            "roomid"  => $room_id,
            'accid'   => 20,
            'timetag' => $time * 1000,    //微妙
            'limit'   => 100,
            'reverse' => 1,    //1按时间正序排列，2按时间降序排列
            'type'    => 0
        ];

        return $this->im($url, $imData);
    }

    /**
     * 设置聊天室内用户角色
     * @param int     $operator   云信房间 创建者,创建者不一定等于业务上的房主
     * @param int     $to_user_id 目标对象
     * @param int     $room_id    房间ID
     * @param string  $type       true或false，true:设置；false:取消设置；必须是字符串 true或者false
     * @param boolean $opt        1: 设置为管理员，operator必须是创建者
     *                            2:设置普通等级用户，operator必须是创建者或管理员
     *                            -1:设为黑名单用户，operator必须是创建者或管理员
     *                            -2:设为禁言用户，operator必须是创建者或管理员
     *
     */
    public function set_room_role($operator, $to_user_id, $room_id, $type = 'true', $opt = 1)
    {
        $url = "/chatroom/setMemberRole.action";
        $imData = [
            "roomid"   => $room_id,
            'operator' => $operator,
            'target'   => $to_user_id,
            'opt'      => $opt,
            'optvalue' => $type
        ];


        return $this->im($url, $imData);
    }

    /**
     * 批量获取在线成员信息
     * @param string $room_id 房间号
     * @param array  $user_ids  用户数组
     * @return mixed
     */
    public function queryMembers($room_id, $user_ids)
    {
        $url = "/chatroom/queryMembers.action";
        $imData = [
            "roomid" => $room_id,
            'accids' => json_encode($user_ids),
        ];

        return $this->im($url, $imData);
    }

    /**
     * 排序列出队列中所有元素
     * @param string $room_id 房间号
     * @return mixed
     */
    public function queueList($room_id)
    {
        $url = "/chatroom/queueList.action";
        $imData = [
            "roomid" => $room_id,
        ];
        return $this->im($url, $imData);
    }


    /**
     * 查询聊天室信息
     * @param int $operator 云信房间 创建者,创建者不一定等于业务上的房主
     */
    public function room_info($room_id, $onlineUser = false)
    {
        $url = "/chatroom/get.action";
        $imData = [
            "roomid"              => $room_id,
            'needOnlineUserCount' => "$onlineUser",
        ];

        return $this->im($url, $imData);
    }


    /** 请求云信服务器API
     * @param $url
     * @param $data
     * @return mixed
     */
    public function im($url, $data)
    {
        $randStr = 'Nonce' . mt_rand(10000, 99999);
        $time = time();
        $header = array();
        array_push($header, "AppKey: " . $this->app_key);
        array_push($header, "Nonce: " . $randStr);
        array_push($header, "CurTime: " . $time);
        array_push($header, "CheckSum: " . sha1($this->app_secret . $randStr . $time));
        array_push($header, "Content-Type: application/x-www-form-urlencoded;charset=utf-8");


        $retStr = $this->post($this->server . $url, $this->toUrlParams($data, [], true), $header);
        $res = json_decode($retStr, true);
        if (!isset($res['code']) || $res['code'] <> 200) {
            \think\Log::error('云信响应打印--url:' . $url);
            \think\Log::error($res);
            return false;
        }
        return $res;
    }

    private function post($url, $data, $headers = [])
    {
        //简单的curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);//SSL证书认证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);//严格认证
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        if (isset($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    private function toUrlParams($values, $exclude = [], $urlEnc = false/*url encode*/, $ridEpt = true/*去除val为空的*/)
    {
        ksort($values);

        $buff = "";
        foreach ($values as $k => $v) {
            if (!in_array($k, $exclude) && (!$ridEpt || $v != "") && !is_array($v)) {
                $v = $urlEnc ? urlencode($v) : $v;
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    //删除清理整个队列
    public function queueDrop($im_room_id)
    {
        $url = "/chatroom/queueDrop.action";
        $imData = [
            "roomid" => $im_room_id,
        ];
        return $this->im($url, $imData);
    }

    /**
     * 查询聊天室在线成员ID
     * @param int $im_room_id 云信房间号
     * @param int $type       需要查询的成员类型,0:固定成员;1:非固定成员;2:仅返回在线的固定成员
     */
    public function membersByPage($im_room_id, $type, $endtime)
    {
        $url = "/chatroom/membersByPage.action";
        $imData = [
            "roomid"  => $im_room_id,
            'type'    => $type,
            'endtime' => $endtime,
            'limit'   => 100
        ];
        return $this->im($url, $imData);
    }

    /**
     * 查询聊天室在线成员ID
     */
    public function queueOffer($im_room_id, $key, $value)
    {
        $url = "/chatroom/queueOffer.action";
        $imData = [
            "roomid"    => $im_room_id,
            'key'       => $key,
            'value'     => $value,
            'transient' => 'true',
            'operator'  => '100002271'
        ];
        return $this->im($url, $imData);
    }


    /**
     * 从队列中取出元素
     */
    public function queuePoll($im_room_id, $key)
    {
        $url = "/chatroom/queuePoll.action";
        $imData = [
            "roomid" => $im_room_id,
            'key'    => $key,
        ];
        return $this->im($url, $imData);
    }


    public function set_room_role_ext($user_id, $room_id, $ext)
    {
        $url = "/chatroom/updateMyRoomRole.action";
        $imData = [
            "roomid" => $room_id,
            'accid'  => $user_id,
            'ext'    => $ext,
        ];
        return $this->im($url, $imData);
    }

    public function kickMember($operator, $to_user_id, $room_id, $ext = '')
    {
        $url = "/chatroom/kickMember.action";
        $imData = [
            "roomid"      => $room_id,
            'accid'       => $operator,
            'targetAccid' => $to_user_id,
            'notifyExt'   => $ext,
        ];
        return $this->im($url, $imData);
    }


    /**
     * 获取用户查看用户的黑名单列表
     * @param $user_id
     * @return mixed
     */
    public function listBlackAndMuteList($user_id)
    {
        $url = "/user/listBlackAndMuteList.action";
        $imData = [
            'accid' => $user_id,
        ];
        return $this->im($url, $imData);
    }

    /**
     * 发送短信验证码
     * @param $mobile      int //手机号
     * @param $code        int  验证码
     * @param $template_id int 短信模版ID
     * @return mixed
     */
    public function sendSMS($mobile, $code)
    {
        $this->app_key = 'c8733a06f1c38025827b0339660709cf';
        $this->app_secret = 'dbf64b3e348e';
        $url = '/sms/sendcode.action';
        $imData = [
            'templateid' => '19518052',    //短信模版ID
            'mobile'     => $mobile,        //接收手机号
            'authCode'   => $code           //验证码
        ];
        $this->server = 'https://api.netease.im'; //短信与语音不同
        return $this->im($url, $imData);
    }

}
