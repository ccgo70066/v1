<?php

namespace app\api\controller;

use app\common\exception\ApiException;
use app\common\library\agora\Agora;
use app\common\model\ChannelBlacklist;
use app\common\model\NoblePrivilege;
use app\common\model\Room as RoomModel;
use app\common\model\Shield;
use app\common\service\BaseService;
use app\common\service\ImService;
use app\common\service\LuckyMoneyService;
use app\common\service\RedisService;
use app\common\service\RoomService;
use think\Db;
use think\Exception;
use think\Log;

/**
 * 房间
 * @ApiWeigh    (901)
 */
class Room extends Base
{
    protected $noNeedLogin = ['quit', 'reconnect', 'get_log'];
    protected $noNeedRight = '*';
    protected $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new RoomService;
    }

    /**
     * 获取房间分类
     */
    public function get_cate_column()
    {
        $res = db('room_theme_cate')->where(['status' => 1])->field('create_time,status,weigh', true)->order('weigh', 'desc')->select();
        $this->success('', $res);
    }


    /**
     * 创建房间申请
     * @ApiMethod   (post)
     * @ApiParams   (name="name",    type="string",  required=true, rule="", description="名称")
     * @ApiParams   (name="cover",  type="string",  required=true, rule="require", description="封面")
     * @ApiParams   (name="intro",  type="string",  required=true, rule="require", description="简介")
     * @ApiParams   (name="bg_img",   type="string",  required=false, rule="", description="背景图")
     * @ApiParams   (name="welcome_switch",   type="int",  required=false, rule="", description="欢迎语开关:1=开,0=关")
     * @ApiParams   (name="welcome_msg",   type="string",  required=false, rule="", description="欢迎语")
     */
    public function create_room()
    {
        if (input('welcome_switch', 0) == 1 && input('welcome_msg') == '') $this->error(__('Please enter welcome message'));
        $user_id = $this->auth->id;
        $this->operate_check('create_room_' . $user_id, 5);
        $info = input();
        if (db('user_business')->where('id', $user_id)->value('role') == 4) $this->error(__('You have no permission'));
        $exist = db('room')->where('owner_id', $user_id)->where('status', '>', 0)->find();
        if ($exist) $this->error(__('You already have a room'));
        $this->service->create($user_id, $info);
        $this->success();
    }

    /**
     * 修改房间
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="int",  required=true, rule="require", description="房间ID")
     * @ApiParams   (name="name",    type="int",  required=false, rule="", description="房间名称")
     * @ApiParams   (name="theme_id",    type="int",  required=false,rule="min:1", description="房间主题类型")
     * @ApiParams   (name="notice",  type="str",  required=false, rule="", description="公告")
     * @ApiParams   (name="cover",   type="int",  required=false, rule="min:0", description="封面")
     * @ApiParams   (name="is_lock", type="int",  required=false, rule="", description="是否要密码:1=要,0=不要")
     * @ApiParams   (name="password",type="int",  required=false, rule="", description="明文密码")
     * @ApiParams   (name="bg_img",  type="str",  required=false, rule="", description="聊天背景图片")
     * @ApiParams   (name="label",   type="str",  required=false, rule="max:4", description="自定义标签")
     * @ApiParams   (name="way",   type="int",  required=false, rule="between:0,5", description="排麦方式:1=自由麦,2=排麦")
     * @ApiParams   (name="is_show",   type="int",  required=false, rule="between:0,5", description="是否营业:1=营业,0=不营业")
     * @ApiParams   (name="welcome_msg",   type="str",  required=false, rule="", description="房间欢迎语")
     * @ApiParams   (name="welcome_switch",   type="int",  required=false, rule="", description="欢迎语开关:1=开,0=关")
     **/
    public function edit()
    {
        $update = array_filter(input(), function ($value) {
            return $value !== '' && $value !== null;
        });
        unset($update['raw']);
        $room_id = $update['id'];
        $this->operate_check('edit_room:' . $this->auth->id, 2);
        if (!($this->service->checkRoomRole($room_id, $this->auth->id, [1, 2])))
            $this->error(__('No permissions'));
        $is_blacklist = $this->service->isBlacklist($room_id, $this->auth->id);
        if ($is_blacklist) $this->error(__('You\'re on the party blacklist'));

        $room = db('room')->where('id', $room_id)->find();
        if ($room['status'] == 1) $this->error(__('Room under review, status cannot be modified'));
        if ($room['status'] == 0) $this->error(__('Room is being banned, cannot modify'));

        if (isset($update['name'])) {
            $update['name'] = Shield::sensitive_filter($update['name']);
            $res = db('room')->where('name', $update['name'])->whereIn('status', [2, 3])->where('id', '<>', $room_id)->count();
            if ($res) $this->error(__('Room name already exists'));
        }
        if (isset($update['notice'])) $update['notice'] = Shield::sensitive_filter(input('notice'));
        if (input('is_lock')) {
            if (!input('password')) $this->error(__('Password cannot be empty'));
            if (mb_strlen((input('password'))) > 6 || mb_strlen((input('password'))) < 4) $this->error(__('Password must be 4-6 characters'));
        }
        if (input('is_show') || input('is_show') === 0 || input('is_show') === '0') {
            $redis = redis();
            if (input('is_show') == 1 && $redis->hGet(RedisService::ADMIN_SET_ROOM_NOT_SHOW, $room_id)) {
                $this->error(__('Unable to update to business, please contact customer service'));
            }
        }
        if (input('is_show') == 0 || input('way') == 1) $this->service->clearMicQueue($room_id); //清空排麦
        Db::startTrans();
        try {
            db('room')->strict(false)->where('id', $room_id)->update($update);
            RoomService::addRoomLog($room, $update, $this->auth->id);
            Db::commit();
            $data = db('room')->where('id', $room_id)->find();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success('', $data ?? '');
    }

    /**
     * 开启/关闭个人房间
     * @ApiSummary  (开启/关闭个人房间,关闭房间前端删除第三方im平台成员数据)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房间号")
     * @ApiParams   (name="is_close", type="int",  required=true, rule="", description="关闭房间:1=关闭,0=开启")
     * @ApiParams   (name="user_id",  type="int",  required=false, rule="", description="房主id,默认当前用户")
     **/
    public function close()
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $room_id = input('room_id');
        $is_close = input('is_close');
        $room = db('room')->where('id', $room_id)->find();
        if (!$room) $this->error(__('No results were found'), 406);
        if ($room['owner_id'] <> $user_id) $this->error(__('No homeowner permission'));
        Db::startTrans();
        try {
            switch ($is_close) {
                case 1:
                    if ($room['is_close'] === 1) throw new ApiException(__('Room closed'), 406);
                    $this->service->closeRoom($room_id);
                    break;
                case 0:
                    if ($room['is_close'] === 0) throw new ApiException(__('Room opened'), 406);
                    $imService = new ImService();
                    $imService->roomSetSwitch($room_id, true);
                    db('room')->where('id', $room_id)->setField('is_close', $is_close);
                    break;
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success();
    }


    /**
     * 更多房间
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="当前房间号")
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="页码大小")
     **/
    public function more_room_list(RoomModel $roomModel)
    {
        $room_hot = redis()->hGetAll(RedisService::ROOM_HOT_KEY);

        $page = input('page') ?: 1;
        $where = [];
        if ($page == 1) {
            redis()->del('more_room_list:' . $this->auth->id);
        } else {
            $already_show_ids = redis()->sMembers('more_room_list:' . $this->auth->id) ?: [];
            $where['r.id'] = ['not in', $already_show_ids];
        }
        $room_id = input('room_id') ?: 0;
        $result = db('room r')
            ->join('room_theme_cate t', 't.id=r.theme_id')
            ->where('r.status', 'in', [3, 2])
            ->where('r.is_show', 1)
            ->where($where)
            ->where('r.id', '<>', $room_id)
            ->where('is_close', 0)
            ->order('r.show_sort asc, r.hot desc,r.create_time')
            ->field('r.id,r.beautiful_id,r.name,r.is_lock,hot,r.cover,r.owner_id,r.theme_id,t.name as theme_name,t.color as theme_color')
            ->select();
        foreach ($result as $key => $value) {
            $result[$key]['hot'] = $room_hot[$value['id']] ?? 0;
        }
        if ($result) {
            array_multisort(array_column($result, 'hot'), SORT_DESC, $result);
            $result = array_slice($result, 0, 20);
            redis()->sAddArray('more_room_list:' . $this->auth->id, array_column($result, 'id'));
            redis()->expire('more_room_list:' . $this->auth->id, 60 * 30);
        }
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (访问记录)
     * @ApiSummary
     * @ApiParams   (name="size",       type="int", required=true,  rule="", description="分页大小,默认20")
     * @ApiParams   (name="start_id",   type="int",  required=true,  rule="", description="分页id")
     */
    public function enter_log()
    {
        $size = input('size') ?: 20;
        $start_id = input('start_id');
        $where = [];
        if ($start_id) {
            $where['l.id'] = ['<', $start_id];
        }

        $result = db('room_enter_log l')
            ->join('room r', 'l.room_id = r.id')
            ->where('l.user_id', $this->auth->id)
            ->where($where)
            ->where('r.status', 'in', '2,3')
            ->field('r.id,r.beautiful_id,r.name,r.is_lock,r.hot,r.cover,r.theme_id,r.owner_id')
            ->order('l.id desc')
            ->page(1, $size)
            ->select();
        $redis = redis();

        //获取房间类似数组
        foreach ($result as $k => &$v) {
            $room_user = $redis->zRevRange(RedisService::ROOM_USER_KEY_PRE . $v['id'], 0, 5);
            $result[$k]['room_user'] = db('user')->where('id', 'in', $room_user)->limit(5)->column('avatar');
        }
        $this->success('', $result);
    }

    /**
     * 上座
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="require", description="ID房间")
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用户ID")
     * @ApiParams   (name="op_user_id", type="int",  required=false, rule="", description="操作者")
     * @ApiParams   (name="seat", type="str",  required=true, rule="", description="座位号:1到9")
     **/
    public function sit_seat()
    {
        $room_id = input('room_id');
        $user_id = input('user_id');
        $op_user_id = input('op_user_id') ?: $this->auth->id;
        $seat = input('seat');
        $room = db('room')->where('id', $room_id)->find();

        $isset = $this->service->isInRoomByImApi($user_id, $room_id);
        if (!$isset) $this->error(__('The user is no longer in the room'));
        if ($user_id == $op_user_id) $this->service->saveUserWhichRoom($user_id, $room_id);
        $seatField = "no{$seat}_user_id";
        if (isset($room[$seatField]) && $room[$seatField] == $user_id) $this->success();

        db('room')->where('id', $room_id)->setField($seatField, $user_id);
        //位置上换座
        $seat_info = db('room')->where('id', $room_id)
            ->field('no1_user_id,no2_user_id,no3_user_id,no4_user_id,no5_user_id,no6_user_id,no7_user_id,no8_user_id,no9_user_id')
            ->find();
        foreach ($seat_info as $key => $value) {
            if ($key != $seatField && $value == $user_id) {
                db('room')->where('id', $room_id)->setField($key, 0);
            }
        }
        //上座了将其退出排麦队列
        if ($room['way'] == 2) {
            $imService = new ImService();
            $imService->room_wait_mic_delete($room_id, $user_id);
        }
        if ($user_id == $op_user_id) {
            $msg = "坐上了" . ($seat - 1) . "号麦";
            $msg_en = " on the Mic " . ($seat - 1);
        } else {
            $nickname = RedisService::getUserCache($user_id, 'nickname');
            $msg = "将*" . $nickname . "*抱上" . ($seat - 1) . "号麦";
            $msg_en = " hold *{$nickname}* on the Mic " . ($seat - 1);
        }
        $this->service->add_room_log($room_id, $op_user_id, $msg, $msg_en, $user_id);
        $this->success();
    }

    /**
     * @ApiTitle    (下座)
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=false, rule="", description="房间ID")
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用户ID,不传则为操作者")
     * @ApiParams   (name="seat", type="str",  required=true, rule="", description="座位号:1到9")
     **/
    public function leave_seat(RoomModel $roomModel)
    {
        $room_id = input('room_id');
        $user_id = input('user_id') ?: $this->auth->id;
        $seat = input('seat');
        if (!$room_id) {
            $this->error(__('操作失敗'));
        }
        $roomModel->sit_leave($room_id, $user_id);
        if ($user_id == $this->auth->id) {
            $msg = "离开了" . ($seat - 1) . "号麦";
            $msg_en = " take off the Mic " . ($seat - 1);
        } else {
            $nickname = RedisService::getUserCache($user_id, 'nickname');
            $msg = "将*" . $nickname . "*抱下" . ($seat - 1) . "号麦";
            $msg_en = " take *{$nickname}* off the Mic " . ($seat - 1);
        }
        $this->service->add_room_log($room_id, $this->auth->id, $msg, $msg_en, $user_id);

        $this->success();
    }

    /**
     * 获取房间是否有密码锁
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     **/
    public function has_lock()
    {
        $room_id = input('room_id');
        $user_id = $this->auth->id;
        $roomModel = new RoomModel();
        $is_blacklist = $roomModel->is_blacklist($room_id, $user_id);
        if ($is_blacklist) {
            $this->error(__('You\'re on the party blacklist'));
        }

        if ($this->service->checkRoomRole($room_id, $user_id, [1])) {
            $this->success('', "0");
        }
        $has_lock = db('room')->where('id', $room_id)->value('is_lock');
        $this->success('', $has_lock);
    }

    /**
     * @ApiTitle    (进入房间)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     * @ApiParams   (name="password",type="int",    required=false,rule="", description="密码")
     * @ApiParams   (name="last_room_id", type="int",    required=false, rule="", description="用户当前所在房间")
     **/
    public function enter(RoomModel $roomModel)
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $room_id = input('room_id');
        $password = input('password');

        $room = db('room')->where(['id' => $room_id])->where('status', 'in', [2, 3, 1, -1])->find();

        if ($room && !in_array($room['status'], [2, 3, -1])) {
            throw new ApiException(__('Party under review, please wait patiently'));
        }
        if (!$room || ($room['is_close'] == 1 && $this->auth->id <> $room['owner_id'])) {
            throw new ApiException(__('Room closed'));
        }

        $is_blacklist = $roomModel->is_blacklist($room_id, $user_id);
        if ($is_blacklist) {
            throw new ApiException(__('You have been added to the room blacklist'));
        }
        $redis = redis();
        $last_room_id = $redis->hGet(RedisService::USER_NOW_ROOM_KEY, $user_id);

        //网络不好，重连不需要密码
        if (!input('reconnect') && input('last_room_id') != $room_id) {
            if ($room['is_lock'] && $room['password'] != $password && $room['owner_id'] <> $user_id && $last_room_id != $room_id) {
                throw new ApiException('Room password incorrect');
            }
        }
        $this->service->enterRoom($user_id, $room_id, $last_room_id);


        $data = [];
        $data['hiding'] = false;
        $data['is_collect'] = db('room_collect')->where(['room_id' => $room_id, 'user_id' => $this->auth->id])->count();
        $data['blacklist'] = ChannelBlacklist::get_blacklist($this->appid, $this->system, $this->version);
        //声网token
        $agora = new Agora();
        $data['agora_token'] = $agora->get_token($room_id, $user_id);

        $data['room'] = db('room')->where('id', $room_id)
            ->field('welcome_msg,welcome_switch,way,id,beautiful_id,name,owner_id,screen_clear_time,theme_id,cover,notice,hot,bg_img,is_lock,is_show')
            ->find();
        $theme = db('room_theme_cate')->where('id', $data['room']['theme_id'])->field('name,image')->find();;
        $data['room']['theme_cate_name'] = $theme['name'] ?: '';
        $data['room']['theme_cate_image'] = $theme['image'] ?: '';
        $data['room']['hot'] = $redis->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;
        $data['owner'] = db('user')->where('id', $data['room']['owner_id'])->field('id,avatar,nickname')->find();
        $data['role'] = db('room_admin')->where(['room_id' => $room_id, 'user_id' => $user_id])->value('role') ?: 0;
        $imService = new ImService();
        $countdown = $redis->keys(RedisService::ROOM_COUNTDOWN_KEY_PRE . $room_id . ':*');
        $data['countdown'] = [];
        foreach ($countdown as $v) {
            $data['countdown'][] = [
                'seat_no' => explode(':', $v)[3],
                'time'    => $redis->get($v) - time()
            ];
        }

        $data['game_flag'] = false; // 参与游戏等级限制、参与游戏收到IM红包限制
        $data['green_pact'] = '安全提示：24小时线上巡查，任何传播违法、违规、低俗、暴力等不良资讯的行为将会导致账号被封停； 切勿轻信投资、理财； 切勿相信私聊的非官方储值广告，均属于诈骗行为！ 如有疑问请通过平台客服沟通确认！';
        $level = db('user_business')->where('id', $user_id)->value('level');
        if ($level >= get_site_config('game_limit_level')) {
            $data['game_flag'] = true;
        } elseif (get_site_config('game_limit_im_redpackage') == 1) {
            $find = db('red_packet_log')->where('to_user_id', $user_id)->find();
            $find && $data['game_flag'] = true;
        }
        $message = [
            'type'         => ImService::ROOM_ONLINE_USER_REFRESH,
            'online_count' => $redis->zCard(RedisService::ROOM_USER_KEY_PRE . $room_id)
        ];
        trace($message);
        $imService->roomSendNotice($room_id, $message);
        $this->success('', $data);
    }

    /**
     * @ApiTitle    (退出房间)
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用户id")
     * @ApiParams   (name="room_id", type="int",  required=true, rule="require", description="房间ID")
     **/
    public function quit(RoomModel $roomModel)
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $room_id = input('room_id');
        if ($user_id == 0) {
            throw new ApiException(__('Please log in to the app again'));
        }
        $this->service->quit_room($user_id, $room_id, $user_id == $this->auth->id);
        $this->success();
    }


    /**
     * 重连进入房间
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用户id")
     * @ApiParams   (name="room_id", type="int",  required=true, rule="require", description="房间ID")
     **/
    public function reconnect(RoomModel $roomModel)
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $room_id = input('room_id');
        if ($user_id == 0) {
            throw new ApiException(__('Please log in to the app again'));
        }
        $redis = redis();
        $redis->hSet(RedisService::USER_NOW_ROOM_KEY, $user_id, $room_id);
        if ($redis->zRank(RedisService::ROOM_USER_KEY_PRE . $room_id, $user_id) === false) {
            //有序集合记录房间有哪些用户
            $redis->zAdd(RedisService::ROOM_USER_KEY_PRE . $room_id, time(), $user_id);
        }
        $imService = new ImService();
        $imService->roomSendNotice($room_id, [
            'type'         => ImService::ROOM_ONLINE_USER_REFRESH,
            'online_count' => $redis->zCard(RedisService::ROOM_USER_KEY_PRE . $room_id)
        ]);
        $this->success();
    }


    /**
     * @ApiTitle    (举报房间)
     * @ApiSummary  (举报房间)
     * @ApiParams   (name="room_id",    type="int",  required=false, rule="require", description="房间ID")
     * @ApiParams   (name="comment",    type="str",  required=true,  rule="require|min:0", description="反馈内容")
     **/
    public function accusation()
    {
        $user_id = $this->auth->id;
        $room_id = input('room_id');
        $comment = input('comment');
        BaseService::operateCheck(__CLASS__ . __METHOD__ . $user_id, 2);

        db('feedback')->insert([
            'user_id'      => $user_id,
            'type'         => 2,
            'target_id'    => $room_id,
            'comment'      => $comment,
            'audit_status' => 1
        ]);

        $this->success();
    }

    /**
     * @ApiTitle    (开始/暂停座位打赏金额)
     * @ApiSummary  (开始/暂停座位打赏金额)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房间ID")
     * @ApiParams   (name="pause",    type="int", required=true,  rule="require|min:0", description="设置统计状态:0:关闭,1=开启,2=暂停,3=清空")
     */
    public function sit_sum(roomModel $roomModel)
    {
        $room_id = input('room_id');
        $pause = input('pause');
        $explain = [
            0 => '关闭了麦上打赏统计',
            1 => '开启了麦上打赏统计',
            2 => '暂停了麦上打赏统计',
            3 => '清空了麦上打赏统计'
        ];
        $explain_en = [
            0 => ' disabled Mic gift statistics',
            1 => ' dnabled Mic gift statistics',
            2 => ' daused Mic gift statistics',
            3 => ' dleared Mic gift statistics'
        ];
        $check_auth = $this->service->checkRoomRole($room_id, $this->auth->id, [1, 2, 3]);
        if (!$check_auth) {
            throw new ApiException(__('No permissions'));
        }
        if ($pause <> 3) {
            db('room')->where('id', $room_id)->setField('pause', $pause);
        }

        $this->service->add_room_log($room_id, $this->auth->id, $explain[$pause], $explain_en[$pause]);

        if ($pause == 3 || $pause == 0) {
            $roomModel->sit_reset($room_id);
            $imService = new ImService();
            $hot = redis()->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;  //热力值
            //刷新界面信息
            $imService->roomGiveGiftNotice($room_id, $hot);
        }

        $this->success();
    }

    /**
     * @ApiTitle    (重置某一个座位打赏金额)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房间ID")
     * @ApiParams   (name="seat_no",  type="int", required=false,  rule="", description="指定座位号:1-9,不填默认全麦")
     */
    public function sit_reset(roomModel $roomModel)
    {
        $room_id = input('room_id');
        $seat_no = input('seat_no');

        $roomModel->sit_reset($room_id, $seat_no);

        if (!$seat_no) {
            $seat = '全部';
            $seat_en = 'all';
        } else {
            $seat = $seat_en = $seat_no - 1;
        }
        $this->service->add_room_log($room_id, $this->auth->id, "清空了{$seat}号麦的麦上打赏统计", " cleared the gift statistics for Mic {$seat_en}");

        $imService = new ImService();
        $hot = redis()->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;  //热力值
        //刷新界面信息
        $imService->roomGiveGiftNotice($room_id, $hot);
        $this->success();
    }


    /**
     * 收藏房间
     * @ApiSummary  (收藏聊天室)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房间ID")
     */
    public function collect()
    {
        $room_id = input('room_id');
        $user_id = $this->auth->id;
        if (db('room_collect')->where(['room_id' => $room_id, 'user_id' => $user_id])->find()) $this->error(__('Already collected'));
        db('room_collect')->insert(['user_id' => $user_id, 'room_id' => $room_id,]);

        $this->success(__('Operation completed'));
    }

    /**
     * 取消收藏
     * @ApiSummary  (用户取消收藏聊天室)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房间ID")
     */
    public function uncollect()
    {
        $room_id = input('room_id');
        $user_id = $this->auth->id;
        db('room_collect')->where(['user_id' => $user_id, 'room_id' => $room_id,])->delete();

        $this->success(__('Operation completed'));
    }

    /**
     * 礼物统计
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id",    type="int", required=true,  rule="require|min:0", description="房间ID")
     * @ApiParams   (name="start_id", type="int", required=true,  rule="", description="查询起始ID")
     * @ApiParams   (name="size",       type="int", required=true,  rule="", description="分页大小,默认20")
     */
    public function gift_log()
    {
        $room_id = input('room_id');
        $size = input('size') ?: 20;
        $start_id = input('start_id');
        $roomService = new RoomService();
        if (!($roomService->checkRoomRole($room_id, $this->auth->id, [1, 2, 3]))) $this->error(__('No permissions'));

        $where = [];
        if ($start_id) $where['id'] = ['<', $start_id];
        $data = db('gift_log')
            ->where(['room_id' => $room_id])
            ->where($where)
            ->field('id,user_id,to_user_id,gift_id,gift_val,count as gift_count,create_time')
            ->page(1, $size)
            ->order('create_time', 'desc')
            ->select();

        foreach ($data as &$item) {
            $item['create_time'] = date('Y.m.d H:i:s', strtotime($item['create_time']));
            $item['gift_image'] = RedisService::getGiftCache($item['gift_id'], 'image');

            $item['nickname'] = RedisService::getUserCache($item['user_id'], 'nickname');
            $item['avatar'] = RedisService::getUserCache($item['user_id'], 'avatar');
            $item['to_nickname'] = RedisService::getUserCache($item['to_user_id'], 'nickname');
            $item['to_avatar'] = RedisService::getUserCache($item['to_user_id'], 'avatar');
        }
        $val_total = db('gift_log')->where('room_id', $room_id)->where($where)->sum('gift_val');

        $this->success('', [
            'list'      => $data,
            'val_total' => $val_total
        ]);
    }

    /**
     * @ApiTitle    (踢出用户)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="require", description="被禁用户id")
     **/
    public function kick_room(RoomModel $roomModel)
    {
        $room_id = input('room_id');
        $to_user_id = input('to_user_id');
        if ($to_user_id == $this->auth->id) $this->error(__('Unable to kick self from party'));
        if (!($this->service->checkRoomRole($room_id, $this->auth->id, [1, 2]))) $this->error(__('No permissions'));
        $nickname = db('user')->where('id', $to_user_id)->value('nickname');
        $room = db('room')->where('id', $room_id)->field('im_operator,owner_id,beautiful_id')->find();
        if ($room['owner_id'] == $to_user_id) $this->error(__('No permissions'));
        if (user_noble_switch($to_user_id, 5)) $this->error(__('Operation failed, unable to kick the premium member out of the room'));
        send_im_msg_by_system($room['owner_id'], '%s将*%s*踢出厅%s');
        $this->service->add_room_log($room_id, $this->auth->id, "将*{$nickname}*踢出了厅", '', $to_user_id);
        $this->service->quit_room($to_user_id, $room_id, 0, 1);
        $this->success();
    }

    /**
     * @ApiTitle    (将用户拉入/移出黑名单)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="require", description="被禁用户id")
     * @ApiParams   (name="type",       type="int",  required=true, rule="require", description="操作类型:1=加入黑名单,2=移出黑名单")
     **/
    public function user_blacklist(RoomModel $roomModel)
    {
        $room_id = input('room_id');
        $type = input('type');
        $to_user_id = input('to_user_id');
        if ($to_user_id == $this->auth->id) $this->error(__('Unable to kick self from party'));
        if (!($this->service->checkRoomRole($room_id, $this->auth->id, [1, 2]))) $this->error(__('No permissions'));
        $nickname = RedisService::getUserCache($to_user_id, 'nickname');
        $room = db('room')->where('id', $room_id)->field('im_operator,owner_id,beautiful_id')->find();
        if ($room['owner_id'] == $to_user_id) $this->error(__('No permissions'));
        if ($type == 1) {
            $noble = db('user_noble u')
                ->join('noble n', 'u.noble_id = n.id')
                ->where("find_in_set(" . NoblePrivilege::PERMISSION_ID_ROOM_BAN_TICK . ",`privilege_ids`)")
                ->where([
                    'u.user_id'  => $to_user_id,
                    'u.end_time' => ['>', date('Y-m-d H:i:s', time() - config('app.noble_protection_time'))],
                ])
                ->count(1);
            if ($noble) {
                throw new ApiException(__("Cannot kick out high nobility"));
            }
            if (!db('room_blacklist')->where(['room_id' => $room_id, 'user_id' => $to_user_id])->find()) {
                db('room_blacklist')->insert(['room_id' => $room_id, 'user_id' => $to_user_id,]);
            }
            //给房主发消息
            send_im_msg_by_system($room['owner_id'], '%s将*%s*踢出厅%s,并加入厅黑名单');
            $this->service->add_room_log($room_id, $this->auth->id, "将*{$nickname}*踢出了厅", " kicked *{$nickname}* out of the room", $to_user_id);
            //移除房间角色
            $this->service->roomRoleRemove($room_id, $to_user_id);
        }

        $this->service->quit_room($to_user_id, $room_id);

        if ($type == 2) {
            db('room_blacklist')->where(['room_id' => $room_id, 'user_id' => $to_user_id])->delete();
            $this->service->add_room_log($room_id, $this->auth->id, "将*{$nickname}*解除了厅黑名单", " unblocked *{$nickname}* from the room blacklist", $to_user_id);
        }

        $this->success();
    }


    /**
     * @ApiTitle    (获取黑名单列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     * @ApiParams   (name="size", type="int",  required=true, rule="", description="每页数量")
     * @ApiParams   (name="start_id", type="int",  required=true, rule="", description="起始id")
     **/
    public function get_blacklist()
    {
        $room_id = input('room_id');
        $size = input('size') ?: 500;
        $start_id = input('start_id');

        $where = [];
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }

        $data = db('room_blacklist')
            ->where(['room_id' => $room_id])
            ->where($where)
            ->field('id,room_id,user_id,create_time')
            ->page(1, $size)
            ->order('id desc')
            ->select();
        $beautiful_ids = Db::name('user')->where('id', 'in', array_column($data, 'user_id'))->column('beautiful_id', 'id');

        foreach ($data as &$item) {
            $item['nickname'] = RedisService::getUserCache($item['user_id'], 'nickname');
            $item['avatar'] = RedisService::getUserCache($item['user_id'], 'avatar');
            $item['beautiful_id'] = $beautiful_ids[$item['user_id']];
        }

        $this->success('', $data);
    }

    /**
     * @ApiTitle    (设置用户角色身份[公])
     * @ApiParams   (name="user_id",    type="int",  required=true, rule="require", description="目标用户id")
     * @ApiParams   (name="role",       type="int",  required=true, rule="require", description="角色:0=取消角色,2=设为房管,3=设为陪陪")
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间id")
     **/
    public function set_role(RoomModel $roomModel)
    {
        $user_id = $this->auth->id;
        $to_user_id = input('user_id');
        $room_id = input('room_id');
        $role = input('role');

        if (!in_array($role, [0, RoomModel::ROOM_ROLE_MANAGE, RoomModel::ROOM_ROLE_ANCHOR])) {
            throw new ApiException(__('Operation failed'));
        }

        //房主或者族长才能设置身份
        $check_auth = $this->service->checkRoomRole($room_id, $user_id, [1]);
        if ($role) {
            $this->service->roomRoleSet($room_id, $to_user_id, $role);
        } else {
            $this->service->roomRoleRemove($room_id, $to_user_id, 0);
        }
        $to_nickname = db('user')->where('id', $to_user_id)->value('nickname');

        $arr = [0 => "取消了{$to_nickname}角色身份", 2 => "将*{$to_nickname}*设为主持", 3 => "将*{$to_nickname}*设为主播"];
        $arr_en = [0 => " canceled {$to_nickname}'s role", 2 => " set *{$to_nickname}* as host", 3 => " set *{$to_nickname}* as anchor"];
        $this->service->add_room_log($room_id, $this->auth->id, $arr[$role], $arr_en[$role], $to_user_id);
        $this->success();
    }

    /**
     * @ApiTitle    (获取房间操作日志)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间id")
     * @ApiParams   (name="size",       type="int",  required=true, rule="", description="分页大小")
     * @ApiParams   (name="start_id",   type="int",  required=false, rule="", description="查询起始id")
     **/
    public function get_log()
    {
        $room_id = input('room_id');
        $size = input('size') ?: 20;
        $start_id = input('start_id');

        $where = [];
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }
        $sel = db('room_log')
            ->where(['room_id' => $room_id])
            ->where($where)
            ->field('id,user_id,create_time,action,action_en')
            ->page(1, $size)
            ->order('id', 'desc')
            ->select();
        foreach ($sel as &$v) {
            $v['create_time'] = datetime(strtotime($v['create_time']), 'Y.m.d H:i:s');
            $v['nickname'] = RedisService::getUserCache($v['user_id'], 'nickname');
            $v['avatar'] = RedisService::getUserCache($v['user_id'], 'avatar');
            request()->langset() == 'en' && $v['action'] = $v['action_en'];
            $v['action'] = preg_replace("/\*/", "<span style='color:#00ff00'>", $v['action'], 1);
            $v['action'] = preg_replace("/\*/", '</span>', $v['action'], 1);
            $v['action'] = $v['nickname'] . $v['action'];
        }
        $this->success('', $sel);
    }

    /**
     * @ApiTitle    (写入房间操作日志)
     * @ApiSummary  ("action:1=封座,2=解封座,3=封人麦,4=解封人麦,5=禁言,6=取消禁言")
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间id")
     * @ApiParams   (name="user_id",    type="int",  required=true, rule="require", description="操作者user_id")
     * @ApiParams   (name="to_user_id", type="int",  required=false,rule="",        description="被操作者user_id,如果目标是user_id")
     * @ApiParams   (name="action",     type="str",  required=true, rule="require", description="操作行为,见注释")
     * @ApiParams   (name="action_val", type="str",  required=false, rule="",       description="操作行为参数:如座位号")
     **/
    public function add_log(RoomModel $room)
    {
        $action_val = input('action_val') ? input('action_val') - 1 : '';
        $to_nickname = db('user')->where('id', input('to_user_id'))->value('nickname');
        switch (input('action')) {
            case 1:
                $action = "封禁了{$action_val}号麦";
                $action_en = " banned Mic {$action_val}";
                break;
            case 2:
                $action = "解封了{$action_val}号麦";
                $action_en = " unbanned Mic {$action_val}";
                break;
            case 3:
                $action = "将*{$to_nickname}*禁麦";
                $action_en = " muted *{$to_nickname}*";
                break;
            case 4:
                $action = "取消了{$to_nickname}的禁麦";
                $action_en = " unmute *{$to_nickname}*";
                break;
            case 5:
                $action = "关闭了公屏";
                $action_en = " close the public screen";
                break;
            case 6:
                $action = "打开了公屏";
                $action_en = " turned on the public screen";
                break;
            default:
                throw new ApiException(__('Invalid operation'));
        };
        $this->service->add_room_log(input('room_id'), input('user_id'), $action, $action_en, input('to_user_id') ?: 0);
        $this->success();
    }


    /**
     * @ApiTitle    (上传房间背景图)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     * @ApiParams  (name="img_url",    type="string",  required=true, rule="require", description="图片地址")
     * @ApiParams  (name="id",    type="string",  required=false, rule="", description="id,新增图片不传")
     **/
    public function upload_bg_img()
    {
        $room_id = input('room_id');
        $img_url = input('img_url');
        $id = input('id');
        if (!($this->service->checkRoomRole($room_id, $this->auth->id, [1, 2]))) $this->error(__('No permissions'));

        if ($id) {
            db('room_img')->where('room_id', $room_id)->where(['id' => $id, 'room_id' => $room_id])->setField([
                'image'          => $img_url,
                'upload_user_id' => $this->auth->id
            ]);
        } else {
            $id = db('room_img')->insertGetId([
                'room_id'        => $room_id,
                'image'          => $img_url,
                'upload_user_id' => $this->auth->id
            ]);
        }

        $this->success('', $id);
    }

    /**
     * @ApiTitle    (获取房间背景图)
     * @ApiParams   (name="room_id",    type="int",  required=false, rule="", description="房间号")
     * @ApiMethod   (get)
     **/
    public function get_bg_img()
    {
        $data['upload'] = db('room_img')->where('room_id', input('room_id'))->field('id,image as url')->order('id')->select();
        $data['sys'] = db('room_img')->where('type', 1)->order('weigh asc')->field('image as url')->select();
        $this->success('', $data);
    }

    /**
     * @ApiTitle    (删除上传的房间背景图)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     * @ApiParams   (name="id",    type="int",  required=true, rule="require", description="图片ID")
     **/
    public function del_bg_img(RoomModel $model)
    {
        $auth_check = $this->service->checkRoomRole(input('room_id'), $this->auth->id, [1, 2, 3]);
        if (!$auth_check) {
            throw new ApiException(__('No permissions'));
        }
        db('room_img')->where('room_id', input('room_id'))->where('id', input('id'))->delete();
        $this->success();
    }

    /**
     * @ApiTitle    (获取排麦队列用户(普通排麦))
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     **/
    public function queue_list()
    {
        $redis = redis();
        $room_id = input('room_id');
        $user_ids = $redis->sMembers(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id);
        if (!$user_ids) {
            $this->success('', []);
        }
        $list = array_values(get_user_info($user_ids, ['adornment', 'level']));

        $this->success('', $list);
    }

    /**
     * 获取在线用户
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     * @ApiMethod   (get)
     **/
    public function online_user()
    {
        $room_id = input('room_id');
        [$user, $hider_user] = $this->service->getOnlineUser($room_id);

        $users = array_diff($user, $hider_user);
        $users_info = array_values(get_user_info($users, ['adornment', 'level']));

        $this->success('', ['data' => $users_info, 'hider' => count($hider_user)]);
    }

    /**
     * @ApiTitle    (获取房间综合通知信息)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房间号")
     **/
    public function get_notice()
    {
        $room_id = input('room_id');
        $redis = redis();
        $data['online_count'] = ($redis->zCard(RedisService::ROOM_USER_KEY_PRE . $room_id) ?: 0); //在线人数
        $data['seat_wait_count'] = ($redis->sCard(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id) ?: 0);   //排麦人数
        $data['hot'] = ($redis->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0);  //热力值
        $data['pause'] = $this->service->getSeatGiftValue($room_id);//麦上打赏统计
        $this->success('', $data);
    }

    /**
     * @ApiTitle    (获取房间详情)
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     **/
    public function get_room_info()
    {
        $user_id = $this->auth->id;
        $room_id = input('room_id');
        $redis = redis();

        $room = db('room r')
            ->where('r.id', $room_id)
            ->join('room_theme_cate cate', 'r.theme_id = cate.id')
            ->field('welcome_switch,password,welcome_msg,way,id,beautiful_id,name,owner_id,theme_id,cover,notice,hot,bg_img,is_lock,is_show', false, 'r')
            ->field("cate.name as theme_cate_name")->find();

        $data = [];

        $data['is_collect'] = db('room_collect')->where(['room_id' => $room_id, 'user_id' => $this->auth->id])->count();
        $blacklist = ChannelBlacklist::get_blacklist($this->appid, $this->system, $this->version);
        foreach ($blacklist as $item) {
            $data['blacklist'][] = ['item_code' => $item];
        }

        //声网token
        $agora = new Agora();
        $data['agora_token'] = $agora->get_token($room_id, $user_id);
        $data['room'] = $room;
        $data['room']['hot'] = $redis->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;
        $this->success('', $data);
    }


    /**
     * @ApiTitle    (获取声网token)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="云信房间号")
     * @ApiParams   (name="user_id",    type="int",  required=true, rule="require", description="用户ID")
     **/
    public function get_agora_token()
    {
        $room_id = input('room_id');
        $user_id = input('user_id');
        $agora = new Agora();
        $token = $agora->get_token($room_id, $user_id);
        $this->success('', $token);
    }

    /**
     * @ApiTitle    (麦上打赏统计明细)
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     * @ApiParams   (name="seat", type="str",  required=true, rule="require", description="座位号:1到9")
     **/
    public function seat_gift_detail()
    {
        $room_id = input('room_id');
        $seat = input('seat');

        $res = db('room_seat_gift_info r')
            ->where(['r.room_id' => $room_id, 'r.seat' => $seat])
            ->field('r.user_id,r.gift_val')
            ->order('gift_val desc')
            ->select();
        $users = array_column($res, 'user_id');
        $userinfo = get_user_info($users, ['adornment', 'level']);
        foreach ($res as &$v) {
            $v = array_merge($v, $userinfo[$v['user_id']]);
        }
        $this->success('', $res);
    }

    /**
     * @ApiTitle    (获取房管和陪陪)
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     **/
    public function get_admin()
    {
        $reception = db('room_admin')->where('room_id', input('room_id'))
            ->where('role', RoomModel::ROOM_ROLE_MANAGE)->column('user_id');
        $manager = db('room_admin')->where('room_id', input('room_id'))
            ->where('role', RoomModel::ROOM_ROLE_ANCHOR)->column('user_id');
        $this->success('', ['reception' => $reception, 'manager' => $manager]);
    }

    /**
     * @ApiTitle    (添加倒计时)
     * @ApiMethod   (get)
     * @ApiParams   (name="seat_no", type="int",    required=false, rule="", description="座位号:1 - 9")
     * @ApiParams   (name="second", type="int",    required=true, rule="require", description="秒")
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     **/
    public function add_countdown()
    {
        $redis = redis();
        $room_id = input('room_id');
        $seat_no = input('seat_no') ?: 0;
        $second = input('second');
        $deadline = time() + $second;
        $redis->set(RedisService::ROOM_COUNTDOWN_KEY_PRE . "$room_id:$seat_no", $deadline, $second);
        $this->success();
    }

    /**
     * @ApiTitle    (随机进入房间)
     * @ApiMethod   (get)
     **/
    public function rand_room()
    {
        $res = db('room')->where(['status' => 3, 'is_lock' => 0,])->column('id');
        if (count($res) > 0) {
            $key = array_rand($res, 1);
            $this->success('', $res[$key]);
        } else {
            $this->error(__('No recommended rooms at the moment'));
        }
    }

    /**
     * 清理公屏
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     */
    public function clear_screen()
    {
        $room_id = input('room_id');
        [$usec, $sec] = explode(" ", microtime());
        $millisecond = round(($usec + $sec) * 1000);
        $result = db('room')->where('id', $room_id)->setField('screen_clear_time', $millisecond);
        if (!$result) {
            $this->error(__('Operation failed'));
        }
        $this->success('', ['time' => $millisecond]);
    }

    /**
     * 聊天快捷语
     * @ApiMethod   (get)
     */
    public function chat_word()
    {
        $config = get_site_config('room_chat_word');
        $data = $config ? explode('#', $config) : [];
        $this->success('', $data);
    }

    /**
     * 申请注销房间
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     * @return void
     * @throws
     */
    public function cancel()
    {
        $room_id = input('room_id');
        $user_id = $this->auth->id;
        $room = db('room')->where('id', $room_id)->find();
        if (!$room) {
            $this->error(__('No results were found'));
        }
        if ($room['owner_id'] != $user_id) {
            $this->error(__('You are not the owner of the room'));
        }
        db('room')->where('id', $room_id)->setField('status', -1);
        $this->success(__('Submitted successfully, please wait for review'));
    }


    /**
     * 获取房间列表及自己的当前房间信息
     * @ApiMethod   (post)
     * @ApiParams   (name="keyword", type="string",  required=false, rule="", description="搜索关键字")
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="页码大小")
     * @ApiReturnParams   (name="my.status", type="int", description="房间状态:1=审核中,2=休息中,3=开播中,0=禁封,-1=申请注销中,-2=已注销,-3=审核驳回")
     * @ApiReturnParams   (name="my.member_status", type="int", description="成员状态:0=申请加入,1=已通过,-1=驳回,2=申请退出,-2=已退出")
     * @throws
     */
    public function get_list(): void
    {
        $user_id = $this->auth->id;
        $list = db('room')->field('id,beautiful_id,name,is_lock,hot,cover,member_count')
            ->where(['name|id|beautiful_id' => ['like', '%' . input('keyword') . '%'], 'status' => ['in', [2, 3]]])
            ->page(input('page', 1), input('size', 10))->select();

        $my = db('room r')->join('room_admin a', 'r.id = a.room_id', 'left')
            ->field('id,beautiful_id,name,is_lock,hot,cover,member_count,status', false, 'r')
            ->field('a.status as member_status,a.role')
            ->where('a.user_id', $user_id)->where('r.status', '>=', 0)->find();
        $this->success('', [
            'my'   => $my,
            'list' => $list,
        ]);
    }

    /**
     * 开厅红包
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="int",  required=true, rule="", description="红包id")
     * @throws
     */
    public function open_lucky_money()
    {
        $user_id = $this->auth->id;
        $id = input('id');
        $money = LuckyMoneyService::instance()->open($id, $user_id);
        $this->success('', $money);
    }
}
