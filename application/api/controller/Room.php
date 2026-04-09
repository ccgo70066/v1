<?php

namespace app\api\controller;

use app\common\exception\ApiException;
use app\common\model\ChannelBlacklist;
use app\common\model\NoblePrivilege;
use app\common\model\Room as RoomModel;
use app\common\model\Shield;
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
     * @ApiTitle    (创建房间申请)
     * @ApiParams   (name="theme_id",    type="int",  required=true, rule="", description="主题类型")
     * @ApiParams   (name="name",    type="string",  required=true, rule="", description="名称")
     * @ApiParams   (name="cover",  type="string",  required=true, rule="require", description="封面")
     * @ApiParams   (name="intro",  type="string",  required=true, rule="require", description="简介")
     * @ApiParams   (name="bg_img",   type="string",  required=false, rule="", description="背景图")
     * @ApiParams   (name="welcome_switch",   type="int",  required=false, rule="", description="歡迎語開關:1=開,0=關")
     * @ApiParams   (name="welcome_msg",   type="string",  required=false, rule="", description="欢迎语")
     */
    public function create_room(RoomModel $roomModel)
    {
        if (input('welcome_switch', 0) == 1 && input('welcome_msg') == '') $this->error(__('Please enter welcome message'));
        $user_id = $this->auth->id;
        $this->operate_check('create_room_' . $user_id, 5);
        $info = input();
        $exist = db('room')->where('owner_id', $user_id)->where('status', '>', 0)->find();
        if ($exist) $this->error(__('You already have a room'));
        $info['owner_id'] = $user_id;
        $info['status'] = 1;
        db('room')->strict(false)->insert($info);
        $this->success();
    }

    /**
     * @ApiTitle    (编辑)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="require", description="房間ID")
     * @ApiParams   (name="name",    type="int",  required=false, rule="", description="房間名稱")
     * @ApiParams   (name="theme_id",    type="int",  required=false,rule="min:1", description="房間主題類型")
     * @ApiParams   (name="notice",  type="str",  required=false, rule="", description="公告")
     * @ApiParams   (name="cover",   type="int",  required=false, rule="min:0", description="封面")
     * @ApiParams   (name="is_lock", type="int",  required=false, rule="", description="是否要密碼:1=要,0=不要")
     * @ApiParams   (name="password",type="int",  required=false, rule="", description="明文密碼")
     * @ApiParams   (name="bg_img",  type="str",  required=false, rule="", description="聊天背景圖片")
     * @ApiParams   (name="label",   type="str",  required=false, rule="max:4", description="自定義標簽")
     * @ApiParams   (name="way",   type="int",  required=false, rule="between:0,5", description="排麥方式:1=自由麥,2=排麥")
     * @ApiParams   (name="is_show",   type="int",  required=false, rule="between:0,5", description="是否營業:1=營業,0=不營業")
     * @ApiParams   (name="welcome_msg",   type="str",  required=false, rule="", description="房間歡迎語")
     * @ApiParams   (name="welcome_switch",   type="int",  required=false, rule="", description="歡迎語開關:1=開,0=關")
     **/
    public function edit(RoomModel $roomModel)
    {
        $arr = [];
        $room_id = input('room_id');
        $this->operate_check('edit_room:' . $this->auth->id, 2);
        $check_role = $this->service->checkRoomRole($room_id, $this->auth->id, [1, 2]);
        if (!$check_role) $this->error(__('No permissions'));
        $is_blacklist = $this->service->isBlacklist($room_id, $this->auth->id);
        if ($is_blacklist) $this->error(__('You\'re on the party blacklist'));

        $room = db('room')->where('id', $room_id)->find();
        try {
            Db::startTrans();
            if (input('name')) {
                $arr['name'] = Shield::sensitive_filter(input('name'));
                if ($arr['name'] <> $room['name']) {
                    $roomModel->add_room_log($room_id, $this->auth->id, "變更房間名稱", ' change room name');
                }
            }
            if (input('theme_id')) $arr['theme_id'] = input('theme_id');
            if (input('notice')) {
                $arr['notice'] = Shield::sensitive_filter(input('notice'));
                if ($arr['notice'] <> $room['notice']) {
                    $roomModel->add_room_log($room_id, $this->auth->id, "變更房間公告", ' change room notice');
                }
            }
            if (input('cover')) $arr['cover'] = input('cover');
            if (input('password')) $arr['password'] = input('password');
            if (input('bg_img')) {
                $arr['bg_img'] = input('bg_img');
                if ($arr['bg_img'] <> $room['bg_img']) {
                    $roomModel->add_room_log($room_id, $this->auth->id, "更換了派對背景", ' change room background');
                }
            }
            if (input('label')) $arr['label'] = input('label');
            if (input('way')) {
                $arr['way'] = input('way');
                if ($arr['way'] == 1) $this->service->clearMicQueue($room_id); //清空排麦
                if ($arr['way'] <> $room['way']) {
                    $way = [1 => '更改為自由上麥', 2 => '更改為排麥模式'];
                    $way_en = [1 => ' changed to free Mic', 2 => ' changed to queue Mic mode'];
                    $roomModel->add_room_log($room_id, $this->auth->id, $way[$arr['way']], $way_en[$arr['way']]);
                }
            }
            if (input('welcome_switch') || input('welcome_switch') === 0 || input('welcome_switch') === '0') {
                $arr['welcome_switch'] = input('welcome_switch');
                if ($arr['welcome_switch'] <> $room['welcome_switch']) {
                    $t = [0 => '關閉歡迎語', 1 => '開啟歡迎語'];
                    $t_en = [0 => ' welcome-message off', 1 => ' welcome-message on'];
                    $roomModel->add_room_log($room_id, $this->auth->id, $t[$arr['welcome_switch']], $t_en[$arr['welcome_switch']]);
                }
            }
            if (input('status')) $arr['status'] = input('status');
            if (input('welcome_msg')) {
                $arr['welcome_msg'] = Shield::sensitive_filter(input('welcome_msg'));
                if (strlen($arr['welcome_msg']) > 500) {
                    throw new ApiException(__('Welcome message character length exceeds limit'));
                }
            }

            if (input('is_show') || input('is_show') === 0 || input('is_show') === '0') {
                if (input('is_show') == 1) {
                    $union = db('union')->where('id', $room['union_id'])->find();
                    if ($union['status'] == 2) {
                        throw new ApiException(__('Clan status is disabled'));
                    }
                }
                $redis = redis();
                if (input('is_show') == 1 && $redis->hGet(RedisService::ADMIN_SET_ROOM_NOT_SHOW, $room_id)) {
                    $this->error(__('Unable to update to business, please contact customer service'));
                }

                $arr['is_show'] = input('is_show');
                if ($arr['is_show'] == 0) $this->service->clearMicQueue($room_id); //清空排麦
                if ($arr['is_show'] <> $room['is_show']) {
                    $is_show = [1 => '更改房間狀態為開業', 0 => '更改房間狀態為歇業'];
                    $is_show_en = [1 => ' changed to opening', 0 => ' changed to closed'];
                    $roomModel->add_room_log($room_id, $this->auth->id, $is_show[$arr['is_show']], $is_show_en[$arr['is_show']]);
                }
            }
            if (input('is_lock') || input('is_lock') === 0 || input('is_lock') === '0') {
                $arr['is_lock'] = input('is_lock');
                if ($arr['is_lock'] <> $room['is_lock']) {
                    $lock = [0 => '取消房間密碼', 1 => '設置房間密碼'];
                    $lock_en = [0 => ' cancelled room password', 1 => ' set room password'];
                    $roomModel->add_room_log($room_id, $this->auth->id, $lock[$arr['is_lock']], $lock_en[$arr['is_lock']]);
                }
            }

            if (input('name')) {
                $res = db('room')->where('name', $arr['name'])->where('status', '<>', RoomModel::ROOM_STATUS_FORBIDDEN)->where('id', '<>', $room_id)->count();
                if ($res) throw new ApiException(__('Room name already exists'));
            }

            if (input('is_lock')) {
                if (!input('password')) {
                    throw new ApiException(__('Password cannot be empty'));
                }
                if (mb_strlen((input('password'))) > 6 || mb_strlen((input('password'))) < 4) {
                    throw new ApiException(__('Password must be 4-6 characters'));
                }
            }
            if (isset($arr['status']) && $arr['status']) {
                $status = db('room')->where('id', $room_id)->value('status');
                if ($status == 1) {
                    throw new ApiException(__('Room under review, status cannot be modified'));
                }
                if ($status == 0) {
                    throw new ApiException(__('Room is being banned, cannot modify'));
                }
            }

            db('room')->where('id', $room_id)->update($arr);
            Db::commit();
            $data = db('room')->where('id', $room_id)->find();
            $this->success('', $data);
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error(show_error_notify($e));
        }
    }

    /**
     * @ApiTitle    (開啓/關閉個人房間)
     * @ApiSummary  (開啓/關閉個人房間,關閉房間前端刪除第三方im平台成員數據)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房間號")
     * @ApiParams   (name="is_close", type="int",  required=true, rule="", description="關閉房間:1=關閉,0=開啓")
     * @ApiParams   (name="user_id",  type="int",  required=false, rule="", description="房主id,默認當前用戶")
     **/
    public function close()
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $room_id = input('room_id');
        $is_close = input('is_close');
        $room = db('room')->where('id', $room_id)->find();
        if (!$room) {
            $this->error(__('No results were found'), 406);
        }

        if ($room['owner_id'] <> $user_id) {
            $this->error(__('No homeowner permission'));
        }
        try {
            Db::startTrans();
            switch ($is_close) {
                case 1:
                    if ($room['is_close'] === 1) {
                        throw new ApiException(__('Room closed'), 406);
                    }
                    $this->service->closeRoom($room_id);
                    break;
                case 0:
                    if ($room['is_close'] === 0) {
                        throw new ApiException(__('Room opened'), 406);
                    }
                    $imService = new ImService();
                    $imService->roomSetSwitch($room_id, true);
                    db('room')->where('id', $room_id)->setField('is_close', $is_close);
                    break;
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error(show_error_notify($e));
        }
        $this->success();
    }


    /**
     * @ApiTitle    (更多房间)
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
            ->where('type', RoomModel::ROOM_TYPE_NUION)
            ->where('r.status', 'in', [RoomModel::ROOM_STATUS_PLAYING, RoomModel::ROOM_STATUS_IDLE])
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
     * @ApiTitle    (訪問記錄)
     * @ApiSummary
     * @ApiParams   (name="size",       type="int", required=true,  rule="", description="分頁大小,默認20")
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
        $roomCateArr = (new HomeService())->getRoomCate();
        foreach ($result as $k => &$v) {
            $room_user = $redis->zRevRange(RedisService::ROOM_USER_KEY_PRE . $v['id'], 0, 5);
            $result[$k]['room_user'] = db('user')->where('id', 'in', $room_user)->limit(5)->column('avatar');
            $cateData = $roomCateArr[$v['theme_id']];
            $result[$k]['theme_name'] = $cateData['name'];
            $result[$k]['theme_color'] = $cateData['color'];
        }
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (上座[公,個])
     * @ApiSummary  (上座)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="require", description="ID房間")
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用戶ID")
     * @ApiParams   (name="op_user_id", type="int",  required=false, rule="", description="操作者")
     * @ApiParams   (name="seat", type="str",  required=true, rule="", description="座位號:1到9")
     **/
    public function sit_seat(RoomModel $roomModel)
    {
        $room_id = input('room_id');
        $user_id = input('user_id');
        $op_user_id = input('op_user_id') ?: $this->auth->id;
        $seat = input('seat');
        $room = db('room')->where('id', $room_id)->find();

        $isset = $this->service->isInRoomByImApi($user_id, $room_id);
        if (!$isset) {
            $this->error(__('The user is no longer in the room'));
        }
        if ($user_id == $op_user_id) {
            //为了健全,更新用户在哪房间
            $this->service->saveUserWhichRoom($user_id, $room_id);
        }
        $seatField = "no{$seat}_user_id";
        if ($room[$seatField]) {
            if ($room[$seatField] == $user_id) {
                $this->success();
            }
        }
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
        //上座了将其退出排麥隊列
        if ($room['way'] == 2) {
            $imService = new ImService();
            $imService->room_wait_mic_delete($room_id, $user_id);
        }
        if ($user_id == $op_user_id) {
            $msg = "坐上了" . ($seat - 1) . "號麥";
            $msg_en = " on the Mic " . ($seat - 1);
        } else {
            $nickname = RedisService::getUserCache($user_id, 'nickname');
            $msg = "將*" . $nickname . "*抱上" . ($seat - 1) . "號麥";
            $msg_en = " hold *{$nickname}* on the Mic " . ($seat - 1);
        }
        $roomModel->add_room_log($room_id, $op_user_id, $msg, $msg_en, $user_id);
        $this->success();
    }

    /**
     * @ApiTitle    (下座[公,個])
     * @ApiParams   (name="room_id", type="int",  required=false, rule="", description="房間ID")
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用戶ID,不傳則爲操作者")
     * @ApiParams   (name="seat", type="str",  required=true, rule="", description="座位號:1到9")
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
            $msg = "離開了" . ($seat - 1) . "號麥";
            $msg_en = " take off the Mic " . ($seat - 1);
        } else {
            $nickname = RedisService::getUserCache($user_id, 'nickname');
            $msg = "將*" . $nickname . "*抱下" . ($seat - 1) . "號麥";
            $msg_en = " take *{$nickname}* off the Mic " . ($seat - 1);
        }
        $roomModel->add_room_log($room_id, $this->auth->id, $msg, $msg_en, $user_id);

        $this->success();
    }

    /**
     * 獲取房間是否有密碼鎖
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
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
     * @ApiTitle    (進入房間[公,個])
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
     * @ApiParams   (name="password",type="int",    required=false,rule="", description="密碼")
     * @ApiParams   (name="last_room_id", type="int",    required=false, rule="", description="用戶當前所在房間")
     **/
    public function enter(RoomModel $roomModel)
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $room_id = input('room_id');
        $password = input('password');
        if ($user_id == 0) {
            throw new ApiException(__('Please log in to the app again'));
        }

        $room = db('room')->where(['id' => $room_id])->where(
            'status',
            'in',
            [RoomModel::ROOM_STATUS_IDLE, RoomModel::ROOM_STATUS_PLAYING, RoomModel::ROOM_STATUS_AUDIT, RoomModel::ROOM_STATUS_CANCEL]
        )->find();

        if ($room && !in_array($room['status'], [RoomModel::ROOM_STATUS_IDLE, RoomModel::ROOM_STATUS_PLAYING, RoomModel::ROOM_STATUS_CANCEL])) {
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

        $hiding = $room['type'] == 1 && user_vip_switch($user_id, 9);

        $data = [];
        $data['hiding'] = $hiding;
        $data['is_collect'] = db('room_collect')->where(['room_id' => $room_id, 'user_id' => $this->auth->id])->count();
        $data['blacklist'] = ChannelBlacklist::get_blacklist($this->appid, $this->system, $this->version);
        //声网token
        $agora = new Agora();
        $data['agora_token'] = $agora->get_token($room['im_roomid'], $user_id);

        $data['room'] = db('room')->where('id', $room_id)
            ->field(
                'welcome_msg,welcome_switch,way,id,beautiful_id,name,type,owner_id,screen_clear_time,
            im_roomid,union_id,theme_id,cover,notice,hot,bg_img,is_lock,is_show'
            )
            ->find();
        $theme = db('room_theme_cate')->where('id', $data['room']['theme_id'])->field('name,image')->find();;
        $data['room']['theme_cate_name'] = $theme['name'] ?: '';
        $data['room']['theme_cate_image'] = $theme['image'] ?: '';
        $data['room']['hot'] = $redis->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;
        $data['owner'] = db('user')->where('id', $data['room']['owner_id'])->field('id,avatar,nickname')->find();
        $data['union_master_id'] = db('union')->where('id', $data['room']['union_id'])->value('owner_id');
        $data['role'] = db('room_admin')->where(['room_id' => $room_id, 'user_id' => $user_id])->value('role') ?: 0;
        $data['vip_info'] = db('user_vip')->alias('uv')->cache(cacheFlag(), 3600, 'user_vip')
            ->join('vip v', 'uv.grade=v.grade', 'left')->join('car c', 'v.car=c.id', 'left')
            ->where('uv.id', $user_id)->field('uv.grade,v.icon,c.face_image as car')->find();
        $imService = new ImService();
        $countdown = $redis->keys(RedisService::ROOM_COUNTDOWN_KEY_PRE . $room_id . ':*');
        $data['countdown'] = [];
        foreach ($countdown as $v) {
            $data['countdown'][] = [
                'seat_no' => explode(':', $v)[3],
                'time'    => $redis->get($v) - time()
            ];
        }

        $data['game_flag'] = false; // 参与游戏vip等级限制、参与游戏收到IM红包限制
        $green_pact = [
            'en' => 'Safety reminder: 24-hour online inspection. Any dissemination of illegal, irregular, vulgar, violent or other harmful information will result in account suspension; Do not trust investments and financial management lightly; Do not believe in unofficial stored value advertisements in private chats, as they are all fraudulent activities! If you have any questions, please communicate with the platform customer service to confirm!',
            'zh' => '安全提示：24小時線上巡查，任何傳播違法、違規、低俗、暴力等不良資訊的行為將會導致帳號被封停； 切勿輕信投資、理財； 切勿相信私聊的非官方儲值廣告，均屬於詐騙行為！ 如有疑問請通過平臺客服溝通確認！',
        ];
        $data['green_pact'] = $green_pact[request()->langset()];
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
     * @ApiTitle    (退出房間)
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用戶id")
     * @ApiParams   (name="room_id", type="int",  required=true, rule="require", description="房間ID")
     **/
    public function quit(RoomModel $roomModel)
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $room_id = input('room_id');
        if ($user_id == 0) {
            throw new ApiException(__('Please log in to the app again'));
        }
        $roomModel->quit_room($user_id, $room_id, $user_id == $this->auth->id);
        $this->success();
    }


    /**
     * 重连进入房间
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用戶id")
     * @ApiParams   (name="room_id", type="int",  required=true, rule="require", description="房間ID")
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
     * @ApiTitle    (舉報房間)
     * @ApiSummary  (舉報房間)
     * @ApiParams   (name="room_id",    type="int",  required=false, rule="require", description="房間ID")
     * @ApiParams   (name="comment",    type="str",  required=true,  rule="require|min:0", description="反饋內容")
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
     * @ApiTitle    (開始/暫停座位打賞金額[公,個])
     * @ApiSummary  (開始/暫停座位打賞金額)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房間ID")
     * @ApiParams   (name="pause",    type="int", required=true,  rule="require|min:0", description="設置統計狀態:0:關閉,1=開啓,2=暫停,3=清空")
     */
    public function sit_sum(roomModel $roomModel)
    {
        $room_id = input('room_id');
        $pause = input('pause');
        $explain = [
            0 => '關閉了麥上打賞統計',
            1 => '開啟了麥上打賞統計',
            2 => '暫停了麥上打賞統計',
            3 => '清空了麥上打賞統計'
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

        $roomModel->add_room_log($room_id, $this->auth->id, $explain[$pause], $explain_en[$pause]);

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
     * @ApiTitle    (重置某壹個座位打賞金額)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房間ID")
     * @ApiParams   (name="seat_no",  type="int", required=false,  rule="", description="指定座位號:1-9,不填默認全麥")
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
        $roomModel->add_room_log($room_id, $this->auth->id, "清空了{$seat}號麥的麥上打賞統計", " cleared the gift statistics for Mic {$seat_en}");

        $imService = new ImService();
        $hot = redis()->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;  //热力值
        //刷新界面信息
        $imService->roomGiveGiftNotice($room_id, $hot);
        $this->success();
    }


    /**
     * @ApiTitle    (獲取房間分類欄-創建時使用[公,個])
     * @ApiParams   (name="room_type",  type="int", required=true,  rule="", description="房間類型:1=聯盟房 2=個人房")
     * @ApiSummary  (房間類別,1=標准房間,2=個人房)
     */
    public function get_cate_column()
    {
        $where = [
            //'room_type' => input('room_type'),
            'status' => 1
        ];
        $res = db('room_theme_cate')->where($where)->field('create_time,status,weigh', true)->order('weigh', 'desc')->select();
        foreach ($res as &$re) {
            $re['name'] = RedisService::loadLang($re['name']);
        }
        $this->success('', $res);
    }

    /**
     * @ApiTitle    (收藏聊天室[公,個])
     * @ApiSummary  (收藏聊天室)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房間ID")
     */
    public function collect()
    {
        $room_id = input('room_id');
        $user_id = $this->auth->id;

        $res = db('room_collect')->where([
            'room_id' => $room_id,
            'user_id' => $user_id
        ])
            ->find();
        if ($res) {
            throw new ApiException(__('Already collected'));
        }
        $arr = [
            'user_id' => $user_id,
            'room_id' => $room_id,
        ];
        db('room_collect')->insert($arr);

        $this->success(__('Operation completed'));
    }

    /**
     * @ApiTitle    (取消收藏[公,個])
     * @ApiSummary  (用戶取消收藏聊天室)
     * @ApiParams   (name="room_id",  type="int", required=true,  rule="require|min:0", description="房間ID")
     */
    public function uncollect()
    {
        $room_id = input('room_id');
        $user_id = $this->auth->id;

        db('room_collect')->where([
            'user_id' => $user_id,
            'room_id' => $room_id,
        ])
            ->delete();

        $this->success(__('Operation completed'));
    }

    /**
     * @ApiTitle    (礼物统计[公,個])
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id",    type="int", required=true,  rule="require|min:0", description="房間ID")
     * @ApiParams   (name="start_id", type="int", required=true,  rule="", description="查询起始ID")
     * @ApiParams   (name="size",       type="int", required=true,  rule="", description="分頁大小,默認20")
     */
    public function gift_log()
    {
        $room_id = input('room_id');
        $roomService = new RoomService();
        $check = $roomService->checkRoomRole($room_id, $this->auth->id, [1, 2, 3]);
        if (!$check) {
            $this->error(__('No permissions'));
        }
        $size = input('size') ?: 20;
        $start_id = input('start_id');

        $where = [];
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }
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
        $val_total = db('gift_log')->where('room_id', $room_id)
            ->where($where)
            ->sum('gift_val');

        $data = [
            'list'      => $data,
            'val_total' => $val_total
        ];
        $this->success('', $data);
    }

    /**
     * @ApiTitle    (踢出用户)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="require", description="被禁用戶id")
     **/
    public function kick_room(RoomModel $roomModel)
    {
        $room_id = input('room_id');
        $to_user_id = input('to_user_id');
        if ($to_user_id == $this->auth->id) {
            throw new ApiException(__('Unable to kick self from party'));
        }
        $check_auth = $this->service->checkRoomRole($room_id, $this->auth->id, [1, 2]);
        if (!$check_auth) {
            throw new ApiException(__('No permissions'));
        }
        $nickname = db('user')->where('id', $to_user_id)->value('nickname');
        $room = db('room')->where('id', $room_id)->field('im_roomid,im_operator,owner_id,beautiful_id')->find();
        if ($room['owner_id'] == $to_user_id) {
            throw new ApiException(__('No permissions'));
        }
        if (user_vip_switch($to_user_id, 5)) {
            throw new ApiException(__('Operation failed, unable to kick the premium member out of the room'));
        }
        //發送訊息給房主
        send_im_msg_by_system_with_lang($room['owner_id'], '%s將*%s*踢出派對%s', $this->auth->nickname, $nickname, $room['beautiful_id']);
        $roomModel->add_room_log($room_id, $this->auth->id, "將*{$nickname}*踢出了派對", " kicked *{$nickname}* out of the room", $to_user_id);
        $roomModel->quit_room($to_user_id, $room_id, 0, 1);
        $this->success();
    }

    /**
     * @ApiTitle    (將用戶拉入/移出黑名單)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="require", description="被禁用戶id")
     * @ApiParams   (name="type",       type="int",  required=true, rule="require", description="操作類型:1=加入黑名單,2=移出黑名單")
     **/
    public function user_blacklist(RoomModel $roomModel)
    {
        $room_id = input('room_id');
        $type = input('type');
        $to_user_id = input('to_user_id');
        if ($to_user_id == $this->auth->id) {
            throw new ApiException(__('Unable to kick self from party'));
        }
        $check_auth = $this->service->checkRoomRole($room_id, $this->auth->id, [1, 2]);
        if (!$check_auth) {
            throw new ApiException(__('No permissions'));
        }
        $nickname = RedisService::getUserCache($to_user_id, 'nickname');
        $room = db('room')->where('id', $room_id)->field('im_roomid,im_operator,owner_id,beautiful_id')->find();
        if ($room['owner_id'] == $to_user_id) {
            throw new ApiException(__('No permissions'));
        }
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
            send_im_msg_by_system_with_lang($room['owner_id'], '%s將*%s*踢出派對%s,並加入派對黑名單', $this->auth->nickname, $nickname, $room['beautiful_id']);
            $roomModel->add_room_log($room_id, $this->auth->id, "將*{$nickname}*踢出了派對", " kicked *{$nickname}* out of the room", $to_user_id);
            //移除房間角色
            $this->service->roomRoleRemove($room_id, $to_user_id);
        }

        $roomModel->quit_room($to_user_id, $room_id);

        if ($type == 2) {
            db('room_blacklist')->where(['room_id' => $room_id, 'user_id' => $to_user_id])->delete();
            $roomModel->add_room_log($room_id, $this->auth->id, "將*{$nickname}*解除了派對黑名單", " unblocked *{$nickname}* from the room blacklist", $to_user_id);
        }

        $this->success();
    }


    /**
     * @ApiTitle    (獲取黑名單列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
     * @ApiParams   (name="size", type="int",  required=true, rule="", description="每頁數量")
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
     * @ApiTitle    (設置用戶角色身份[公])
     * @ApiParams   (name="user_id",    type="int",  required=true, rule="require", description="目標用戶id")
     * @ApiParams   (name="role",       type="int",  required=true, rule="require", description="角色:0=取消角色,2=设为房管,3=设为陪陪")
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間id")
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

        $room = db('room')->where('id', $room_id)->field('im_operator,im_roomid,union_id')->find();

        //房主或者族长才能设置身份
        $check_auth = $this->service->checkRoomRole($room_id, $user_id, [1]);
        if (!$check_auth && !$roomModel->is_union_user($room['union_id'], $user_id, [1])) {
            throw new ApiException(__('No permissions'));
        }
        $check_union_role = $roomModel->is_union_user($room['union_id'], $to_user_id, [1, 2]);
        if (!$check_union_role) {
            throw new ApiException(__("This user is not a clan member"));
        }

        if ($role) {
            $this->service->roomRoleSet($room_id, $to_user_id, $role);
        } else {
            $this->service->roomRoleRemove($room_id, $to_user_id, 0);
        }
        $to_nickname = db('user')->where('id', $to_user_id)->value('nickname');

        $arr = [0 => "取消了{$to_nickname}角色身份", 2 => "將*{$to_nickname}*設為主持", 3 => "將*{$to_nickname}*設為主播"];
        $arr_en = [0 => " canceled {$to_nickname}'s role", 2 => " set *{$to_nickname}* as host", 3 => " set *{$to_nickname}* as anchor"];
        $roomModel->add_room_log($room_id, $this->auth->id, $arr[$role], $arr_en[$role], $to_user_id);
        $this->success();
    }

    /**
     * @ApiTitle    (獲取房間操作日志[公,個])
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間id")
     * @ApiParams   (name="size",       type="int",  required=true, rule="", description="分頁大小")
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
     * @ApiTitle    (寫入房間操作日志[公,個])
     * @ApiSummary  ("action:1=封座,2=解封座,3=封人麦,4=解封人麦,5=禁言,6=取消禁言")
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間id")
     * @ApiParams   (name="user_id",    type="int",  required=true, rule="require", description="操作者user_id")
     * @ApiParams   (name="to_user_id", type="int",  required=false,rule="",        description="被操作者user_id,如果目标是user_id")
     * @ApiParams   (name="action",     type="str",  required=true, rule="require", description="操作行爲,见注释")
     * @ApiParams   (name="action_val", type="str",  required=false, rule="",       description="操作行爲参数:如座位号")
     **/
    public function add_log(RoomModel $room)
    {
        $action_val = input('action_val') ? input('action_val') - 1 : '';
        $to_nickname = db('user')->where('id', input('to_user_id'))->value('nickname');
        switch (input('action')) {
            case 1:
                $action = "封禁了{$action_val}號麥";
                $action_en = " banned Mic {$action_val}";
                break;
            case 2:
                $action = "解封了{$action_val}號麥";
                $action_en = " unbanned Mic {$action_val}";
                break;
            case 3:
                $action = "將*{$to_nickname}*禁麥";
                $action_en = " muted *{$to_nickname}*";
                break;
            case 4:
                $action = "取消了{$to_nickname}的禁麥";
                $action_en = " unmute *{$to_nickname}*";
                break;
            case 5:
                $action = "關閉了公屏";
                $action_en = " close the public screen";
                break;
            case 6:
                $action = "打開了公屏";
                $action_en = " turned on the public screen";
                break;
            default:
                throw new ApiException(__('Invalid operation'));
        };
        $room->add_room_log(input('room_id'), input('user_id'), $action, $action_en, input('to_user_id') ?: 0);
        $this->success();
    }


    /**
     * @ApiTitle    (上傳房間背景圖)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
     * @ApiParams  (name="img_url",    type="string",  required=true, rule="require", description="圖片地址")
     * @ApiParams  (name="id",    type="string",  required=false, rule="", description="id,新增圖片不傳")
     **/
    public function upload_bg_img(RoomModel $roomModel)
    {
        $room_id = input('room_id');
        $img_url = input('img_url');
        $id = input('id');

        //房间管理员才能更改房间背景图
        $check_auth = $this->service->checkRoomRole($room_id, $this->auth->id, [1, 2]);
        if (!$check_auth) {
            throw new ApiException(__('No permissions'));
        }

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
     * @ApiTitle    (獲取房間背景圖[公,個])
     * @ApiParams   (name="room_id",    type="int",  required=false, rule="", description="房間號")
     * @ApiMethod   (get)
     **/
    public function get_bg_img()
    {
        $data['upload'] = db('room_img')->where(
            'room_id',
            input('room_id')
        )->field('id,image as url')->order('id')->select();
        $data['sys'] = db('room_img')->where('type', 1)->order('weigh asc')->field('image as url')->select();
        $this->success('', $data);
    }

    /**
     * @ApiTitle    (刪除上傳的房間背景圖)
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
     * @ApiParams   (name="id",    type="int",  required=true, rule="require", description="圖片ID")
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
     * @ApiTitle    (獲取排麥隊列用戶(普通排麥)[公,個])
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
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
     * @ApiTitle    (獲取成員列表[公,個])
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
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
     * @ApiTitle    (獲取房間綜合通知信息[公,個])
     * @ApiParams   (name="room_id",    type="int",  required=true, rule="require", description="房間號")
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
     * @ApiTitle    (獲取房間詳情[公,個])
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
     **/
    public function get_room_info()
    {
        $user_id = $this->auth->id;
        $room_id = input('room_id');
        $redis = redis();

        $room = db('room r')
            ->where('r.id', $room_id)
            ->join('room_theme_cate cate', 'r.theme_id = cate.id')
            ->field('r.welcome_switch,r.password,r.welcome_msg,r.way,r.id,r.beautiful_id')
            ->field('r.name,r.type,r.owner_id,r.im_roomid,r.union_id,r.theme_id')
            ->field("r . cover,notice,r . hot,r . bg_img,r . is_lock,r . is_show,cate . name as theme_cate_name")
            ->find();
        $room['theme_cate_name'] = RedisService::loadLang($room['theme_cate_name']);

        $data = [];

        $data['hiding'] = $room['type'] == RoomModel::ROOM_TYPE_NUION ? user_vip_switch($user_id, 9) : 0;
        $data['is_collect'] = db('room_collect')->where(['room_id' => $room_id, 'user_id' => $this->auth->id])->count();
        $blacklist = ChannelBlacklist::get_blacklist($this->appid, $this->system, $this->version);
        foreach ($blacklist as $item) {
            $data['blacklist'][] = ['item_code' => $item];
        }

        //声网token
        $agora = new Agora();
        $data['agora_token'] = $agora->get_token($room['im_roomid'], $user_id);
        $data['room'] = $room;
        $data['room']['hot'] = $redis->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;
        $this->success('', $data);
    }


    /**
     * @ApiTitle    (获取声网token)
     * @ApiParams   (name="im_roomid",    type="int",  required=true, rule="require", description="云信房间号")
     * @ApiParams   (name="user_id",    type="int",  required=true, rule="require", description="用戶ID")
     **/
    public function get_agora_token()
    {
        $im_room_id = input('im_roomid');
        $user_id = input('user_id');
        $agora = new Agora();
        $token = $agora->get_token($im_room_id, $user_id);
        $this->success('', $token);
    }

    /**
     * @ApiTitle    (麥上打賞統計明細[公,個])
     * @ApiMethod   (get)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
     * @ApiParams   (name="seat", type="str",  required=true, rule="require", description="座位號:1到9")
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
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
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
     * @ApiTitle    (添加倒計時)
     * @ApiMethod   (get)
     * @ApiParams   (name="seat_no", type="int",    required=false, rule="", description="座位號:1 - 9")
     * @ApiParams   (name="second", type="int",    required=true, rule="require", description="秒")
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
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
     * 獲取共享音樂
     * @ApiMethod   (get)
     */
    public function get_share_song()
    {
        $data = db('share_song')->order('weigh asc')->where('status', 1)->select();
        foreach ($data as $k => &$v) {
            $v['file'] = cdnurl($v['file']);
            $v['title'] = RedisService::loadLang($v['title']);
            $v['author'] = RedisService::loadLang($v['author']);
        }

        $this->success('', $data);
    }


    /**
     * @ApiTitle    (随机进入房间)
     * @ApiMethod   (get)
     * @ApiParams   (name="theme_id",    type="int",  required=false,rule="min:1", description="房間主題類型")
     **/
    public function rand_room()
    {
        $theme_id = (int)input('theme_id');
        $res = db('room')->where([
            'status'  => RoomModel::ROOM_STATUS_PLAYING,
            'is_lock' => 0,
            'type'    => 1
        ])->where($theme_id ? 'theme_id = ' . $theme_id : [])->column('id');
        if (count($res) > 0) {
            $key = array_rand($res, 1);
            $this->success('', $res[$key]);
        } else {
            $this->error(__('No recommended rooms at the moment'));
        }
    }

    /**
     * 清理公屏
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
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
        foreach ($data as &$item) {
            $item = RedisService::loadLang($item);
        }
        $this->success('', $data);
    }

    /**
     * 申请注销房间
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房間id")
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
        db('room')->where('id', $room_id)->setField('status', RoomModel::ROOM_STATUS_CANCEL);
        $this->success(__('Submitted successfully, please wait for review'));
    }
}
