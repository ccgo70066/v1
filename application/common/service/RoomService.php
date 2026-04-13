<?php

namespace app\common\service;

use app\admin\model\UserBusiness;
use app\common\exception\ApiException;
use app\common\model\Room;
use app\common\model\Room as RoomModel;
use app\common\model\Shield;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;

/**
 * 房间服务类
 */
class RoomService
{

    private RoomModel $model;

    public function __construct()
    {
        $this->model = new RoomModel();
    }

    /**
     * @param $user_id
     * @param $info
     * @return void
     * @throws
     */
    public function create($user_id, $info)
    {
        $imService = new ImService();
        $resultIm = $imService->createRoom($user_id, $info['name'], $info['intro']);
        if (!$resultIm) throw new ApiException('创建房间失败');
        $room_id = $resultIm['chatroom']['roomid'];
        $info['id'] = $room_id;
        $info['owner_id'] = $user_id;
        $info['status'] = 1;
        db('room')->strict(false)->insert($info);
        db('room_admin')->insert(['room_id' => $info['id'], 'user_id' => $user_id, 'role' => 1, 'status' => 1]);
        $this->add_room_log($room_id, $user_id, '申请创建房间');
    }

    /**
     * @param int $room_id
     * @param int $result 审核结果:1=通过,0=拒绝
     * @param int $operator
     * @return void
     * @throws
     */
    public function check(int $room_id, int $result, int $operator = 0): void
    {
        db('room')->where('id', $room_id)->update([
            'status'      => $result == 1 ? 2 : -3,
            'audit_admin' => $operator,
            'audit_time'  => datetime()
        ]);
        $this->add_room_log($room_id, $operator, $result == 1 ? '房间审核通过' : '房间审核被拒绝');

        $row = db('room')->find($room_id);
        send_im_msg_by_system($row['owner_id'], sprintf($result == 1 ? '您的派对%s已审核通过!' : '您的派对%s审核拒绝!', $row['name']));
        if ($result == 0) {
            $this->closeRoom($room_id);
        } else {
            UserBusinessService::set_user_role($row['owner_id'], 2);
        }
    }


    //获取房间列表
    public function roomList($where = [], $limit = 30)
    {
        $result = RoomModel::getRoomList($where, $limit);
        $redis = redis();

        $condition = array_column((array)$result, 'owner_id');
        $owner = db('user')->where('id', 'in', $condition)->column('avatar', 'id');
        //获取房间类似数组
        $roomCateArr = (new RoomService())->get_room_cate();
        foreach ($result as $k => &$v) {
            $room_user = $redis->zRevRange(RedisService::ROOM_USER_KEY_PRE . $v['id'], 0, 5);
            $result[$k]['room_user'] = db('user')->where('id', 'in', $room_user)->limit(5)->column('avatar');
            $cateData = $roomCateArr[$v['theme_id']];
            $result[$k]['theme_name'] = $cateData['name'];
            $result[$k]['theme_color'] = $cateData['color'];
            $v['owner_avatar'] = $owner[$v['owner_id']] ?? '';
        }
        return $result;
    }


    /**
     * 添加房间日志
     * @param $room
     * @param $update
     * @param $operator_id
     */
    public static function addRoomLog($room, $update, $operator_id)
    {
        $messages = [
            'name'           => '变更房间名称',
            'notice'         => '变更房间公告',
            'bg_img'         => '更换了房间背景',
            'welcome_switch' => [0 => '关闭欢迎语', 1 => '开启欢迎语'],
            'welcome_msg'    => '更换了房间欢迎语',
            'way'            => [1 => '更改为自由上麦', 2 => '更改为排麦模式'],
            'is_show'        => [1 => '更改房间状态为开业', 2 => '更改房间状态为歇业'],
            'is_lock'        => [0 => '取消房间密码', 1 => '设置房间密码'],
        ];
        $log = [];
        foreach ($update as $key => $value) {
            if ($room[$key] <> $value) {
                if (isset($messages[$key])) {
                    $temp = is_array($messages[$key]) ? ($messages[$key][$value] ?? '') : $messages[$key];
                    if ($temp) $log[] = ['user_id' => $operator_id, 'room_id' => $room['id'], 'action' => $temp,];
                }
            }
        }
        $log && db('room_log')->insertAll($log);
    }

    /**
     * 添加房间日志
     */
    public function add_room_log($room_id, $user_id, $text = '', $text_en = '', $to_user_id = 0)
    {
        db('room_log')->insert([
            'user_id'    => $user_id,
            'room_id'    => $room_id,
            'action'     => $text,
            'action_en'  => $text_en,
            'to_user_id' => $to_user_id,
        ]);
    }

    public function get_room_cate()
    {
        $data = db('room_theme_cate')
            ->where('status', 1)
            ->order('weigh desc')
            ->column('name,color,image', 'id');
        return $data;
    }


    /**
     * 获取房间在线用户
     * @param $room_id
     * @return array [全部用户ID数组,隐身用户ID数组]
     */
    public function getOnlineUser($room_id)
    {
        $redis = redis();
        $user = $redis->zRevRange(RedisService::ROOM_USER_KEY_PRE . $room_id, 0, -1);
        $hider_user = [];
        $user_vip = db('user_vip')->where('id', 'in', $user)->field('id,switch')->select();
        foreach ($user_vip as $item) {
            $json = json_decode($item['switch'], true);
            ($json['9'] ?? 0) == 1 && $hider_user[] = $item['id'];
        }
        return [$user, $hider_user];
    }

    /**
     * 增加麦上打赏统计值
     * @param $room_id
     * @param $key_value_update array ['座位号x'=>'增加值','座位号x'=>'增加值']
     * @return bool
     * @throws ApiException
     */
    public function incrSeatGiftValue($room_id, array $key_value_update): bool
    {
        if (!$key_value_update) {
            return true;
        }
        try {
            $redis = redis();
            //锁
            while (locked('saveSeatGiftValue:' . $room_id, 1)) {
                usleep(1000);
            }
            $before_data = $redis->hGetAll(RedisService::SEAT_GIFT_KEY_PRE . $room_id);
            Log::error($before_data);

            $later_data = [];

            foreach ($key_value_update as $seat_no => $value) {
                $later_data[$seat_no] = ($before_data[$seat_no] ?? 0) + $value;
            }
            Log::error(__LINE__);
            Log::error($later_data);
            $redis->hMSet(RedisService::SEAT_GIFT_KEY_PRE . $room_id, $later_data);
            //锁
            lock_remove('saveSeatGiftValue:' . $room_id);
        } catch (\Exception $exception) {
            error_log_out($exception);
            lock_remove('saveSeatGiftValue:' . $room_id);
            return false;
        }
        return true;
    }

    /**
     * 清空麦上打赏统计值
     * @param $room_id
     * @param $seat_no 0=全部麦,1-9 九个麦序
     * @return bool
     * @throws ApiException
     */
    public function clearSeatGiftValue($room_id, $seat_no = 0): bool
    {
        try {
            if ($seat_no < 0 || $seat_no > 9) {
                throw new ApiException();
            }
            $redis = redis();
            //锁
            while (locked('saveSeatGiftValue:' . $room_id, 1)) {
                usleep(10000);
            }
            if ($seat_no == 0) {
                $redis->del(RedisService::SEAT_GIFT_KEY_PRE . $room_id);
            } else {
                $redis->hSet(RedisService::SEAT_GIFT_KEY_PRE . $room_id, $seat_no, 0);
            }
            //释放锁
            lock_remove('saveSeatGiftValue:' . $room_id);
        } catch (\Exception $exception) {
            error_log_out($exception);
            return false;
        }
        return true;
    }

    /**
     * 获取麦上打赏统计值
     * @param $room_id int 房间号
     * @param $seat_no 0=全部,1-9 九个麦序
     * @return array|int
     */
    public function getSeatGiftValue($room_id, $seat_no = 0)
    {
        $redis = redis();
        if ($seat_no) {
            $result = $redis->hGet(RedisService::SEAT_GIFT_KEY_PRE . $room_id, $seat_no);
            return (int)$result;
        } else {
            $result = $redis->hGetAll(RedisService::SEAT_GIFT_KEY_PRE . $room_id);
            return [
                '1' => (int)($result['1'] ?? 0),
                '2' => (int)($result['2'] ?? 0),
                '3' => (int)($result['3'] ?? 0),
                '4' => (int)($result['4'] ?? 0),
                '5' => (int)($result['5'] ?? 0),
                '6' => (int)($result['6'] ?? 0),
                '7' => (int)($result['7'] ?? 0),
                '8' => (int)($result['8'] ?? 0),
                '9' => (int)($result['9'] ?? 0),
            ];
        }
    }

    /**
     * 获取麦上用户
     * @param $room_id int 房间号
     * @param $seat_no 0=全部,1-9 九个麦序
     * @return array
     * @re
     */
    public function getSeatUserId($room_id)
    {
        $imService = new ImService();
        $queuelist = $imService->get_room_wait_mic($room_id);
        if ($queuelist) {
            $seat = [];
            foreach ($queuelist['desc']['list'] as $list) {
                foreach ($list as $kk => $vv) {
                    $k_arr = explode('#', $kk);
                    if (count($k_arr) == 3 && $k_arr[0] == 10 && $vv) {
                        $seat[($k_arr[2])] = (int)$vv;
                    }
                }
            }
            return $seat;
        } else {
            Log::error('云信获取麦上信息队列数据：' . json_encode($queuelist));
            //云信获取座位信息失败的话，就用数据库信息
            $room = $this->model->getRoomById($room_id);
            return [
                '1' => (int)$room['no1_user_id'],
                '2' => (int)$room['no2_user_id'],
                '3' => (int)$room['no3_user_id'],
                '4' => (int)$room['no4_user_id'],
                '5' => (int)$room['no5_user_id'],
                '6' => (int)$room['no6_user_id'],
                '7' => (int)$room['no7_user_id'],
                '8' => (int)$room['no8_user_id'],
                '9' => (int)$room['no9_user_id'],
            ];
        }
    }


    /**
     *
     * @ApiInternal
     * @param $room_id int 房间ID
     * @param $user_id int 用户ID
     * @param $role    array|string 角色要求(多选) 如 [2,3] 角色:1=房主,2=管理,3=主播
     * @return boolean
     */
    public function checkRoomRole($room_id, $user_id, $role): bool
    {
        $result = db('room_admin')->where([
            'room_id' => $room_id,
            'user_id' => $user_id,
            'role'    => ['in', $role]
        ])->find();
        return (bool)$result;
    }

    /** 是否是房间黑名单
     * @param $room_id
     * @param $user_id
     */
    public function isBlacklist($room_id, $user_id)
    {
        $result = db('room_blacklist')->where(['room_id' => $room_id, 'user_id' => $user_id])->find();
        return (bool)$result;
    }


    /** 清空排麦人数
     * @param $room_id
     * @param $user_id
     */
    public function clearMicQueue($room_id)
    {
        $redis = redis();
        $redis->del(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id);
        $imService = new ImService();
        $imService->room_wait_mic_delete_all($room_id);
        $imService->roomSendNotice($room_id, ['type' => $imService::ROOM_MIC_QUEUE_REFRESH, 'count' => 0]);
    }

    /**
     * 生成随机房间号
     */
    public function createRoomNumber()
    {
        $lockName = date('dh');
        $key = incrLock($lockName, 3600);
        $room_id = mt_rand(10, 99) . $lockName . str_pad($key, 2, '0', STR_PAD_LEFT);
        return $room_id;
    }

    /**
     * 更新用户所在房间redis信息
     * @param $user_id
     * @param $room_id
     * @return
     */
    public function saveUserWhichRoom($user_id, $room_id)
    {
        $redis = redis();
        $current_room_id = $redis->hGet(RedisService::USER_NOW_ROOM_KEY, $user_id);
        if ($current_room_id <> $room_id) {
            //记录用户在哪个房间
            $redis->hSet(RedisService::USER_NOW_ROOM_KEY, $user_id, $room_id);
            //记录房间用户集合
            $redis->zAdd(RedisService::ROOM_USER_KEY_PRE . $room_id, time(), $user_id);
        }
    }

    /**
     * 根据云信APi来判断是否在房间.比较准
     * @param $user_id
     * @param $room_id
     * @return
     */
    public function isInRoomByImApi($user_id, $room_id)
    {
        $imService = new ImService();
        $result = $imService->room_query_user($room_id, [$user_id]);
        if (!empty($result['desc']['data'][0]['onlineStat'])) {
            return true;
        }
        return false;
    }


    //进入房间
    public function enterRoom($user_id, $room_id, $last_room_id)
    {
        $room = db('room')->where(['id' => $room_id])->find();
        $roomModel = new RoomModel();
        if ($room['status'] == 2 || $room['is_close'] == 1) {
            db('room')->where('id', $room_id)->setField(['status' => 3, 'is_close' => 0]);
        }
        $imService = new ImService();
        $hiding = $room['type'] == 1 && user_vip_switch($user_id, 9);
        if ($room['type'] == 2 && $room['is_close'] == 1) {
            $imService->roomSetSwitch($room_id, true);
        }

        //筛选重复进入房间
        if ($last_room_id != $room_id) {
            //如果还没有从上个房间退出,则强性退出房间
            if ($last_room_id) {
                $this->quit_room($user_id, $last_room_id);
            }
            //如果没有开启房间隐身
            if ($hiding == 0 && user_vip_switch($user_id, 7)) {
                $roomModel->vip_hot($room_id, $user_id);
            }
            if (!($room['type'] == 2 && $room['owner_id'] == $user_id)) {
                $roomModel->add_enter_log($user_id, $room_id);
            }
        }
        $this->saveUserWhichRoom($user_id, $room_id);
    }

    public function closeRoom($room_id)
    {
        $redis = redis();
        $room = $this->model->where('id', $room_id)->find();
        $im_operator = $room['im_operator'];
        $rUserAll = $redis->hGetAll(RedisService::USER_NOW_ROOM_KEY);
        if ($rUserAll && is_array($rUserAll)) {
            foreach ($rUserAll as $key => $value) {
                if ($value == $room_id) {
                    $redis->hDel(RedisService::USER_NOW_ROOM_KEY, $key);
                }
            }
        }
        $this->model->clearPersonRoomUser($room_id);//清空用户记录
        $this->clearSeatGiftValue($room_id);//清空打赏记录
        $redis->hDel(RedisService::ROOM_HOT_KEY, $room_id);
        $redis->del(RedisService::ROOM_USER_KEY_PRE . $room_id);

        $this->model->where('id', $room_id)->setField(['is_close' => 1]);
        $this->model->where('id', $room_id)->where(
            'status',
            3
        )->setField(['status' => 2]);

        $imService = new ImService();
        $imService->room_wait_mic_delete_all($room_id);
        $imService->roomSetSwitch($room_id, false);
        return true;
    }

    /**
     * 创建
     * @param int $union_id
     * @param     $room_name
     * @param     $cover
     * @param int $beautiful_id
     * @return ApiException|bool
     */
    public function createUnionRoom(int $union_id, $room_name, $cover, int $beautiful_id = 0)
    {
        if ($room_name != Shield::sensitive_filter($room_name)) {
            throw new ApiException(__('Room name violates regulations'));
        }
        if (!$room_name || !$cover) {
            throw new ApiException(__('Room name or cover is empty'));
        }
        $union = db('union')->where('id', $union_id)->find();
        $union_master = db('union_user')->where(['union_id' => $union_id, 'role' => 1])->value('user_id');
        if (!$union_master) {
            throw new ApiException(__('Clan leader does not exist'));
        }
        if (!$union) {
            throw new ApiException(__('Clan does not exist'));
        };
        $count = db('room')->where(['union_id' => $union_id, 'status' => ['in', '1,2,3']])->count();
        if ($count >= $union['room_max_num']) {
            throw new ApiException(__('Room quantity limit reached'));
        }
        $exist = db('room')->where('name', $room_name)->find();
        if ($exist) {
            throw new ApiException(__('Room name already exists'));
        }
        if ($beautiful_id) {
            if (!preg_match('/^[0-9]{4,5}$/i', $beautiful_id)) {
                throw new ApiException(__('Room premium number should be 4~5 digits'));
            }
        } else {
            $beautiful_id = $this->createRoomNumber();
        }
        $sel = db('room')->where('beautiful_id', $beautiful_id)->find();
        if ($sel) {
            throw new ApiException(__('This room premium number already exists'));
        }
        $imService = new ImService();
        $resultIm = $imService->createRoom($union_master, $room_name);
        $room_data = [
            'beautiful_id'   => $beautiful_id,
            'union_id'       => $union_id,
            'name'           => $room_name,
            'im_operator'    => $union_master,
            'owner_id'       => $union_master,
            'cover'          => $cover,
            'im_roomid'      => $resultIm['chatroom']['roomid'],
            'status'         => 1,
            'is_show'        => 0,
            'theme_id'       => input('theme_id') ?: db('room_theme_cate')->value('id'),
            'way'            => input('way') ?: 1,
            'bg_img'         => input('bg_img') ?: db('room_img')->where('type', 1)->order('weigh')->value('image'),
            'welcome_msg'    => Shield::sensitive_filter(input('welcome_msg')),
            'welcome_switch' => input('welcome_switch') ?: 0
        ];
        db('room')->max('id') < 100000 && $room_data['id'] = 100001;
        $save = db('room')->insert($room_data);
        if ($save) {
            $room_id = db('room')->where('beautiful_id', $beautiful_id)->where('owner_id', $union_master)->value('id');
            $result = db('room_admin')->insert(['room_id' => $room_id, 'user_id' => $union_master, 'role' => 1]);
        }
        return (bool)($result ?? false);
    }

    /**
     * 移除用户房间角色
     * @param $room_id    int 房间号
     * @param $to_user_id int 被操作人
     * @return void
     */
    public function roomRoleRemove($room_id, $to_user_id)
    {
        //取消角色
        $update = db('room_admin')->where(['room_id' => $room_id, 'user_id' => $to_user_id])->delete();
        if ($update) {
            $imService = new ImService();
            $imService->roomSetAuth($room_id, $to_user_id, false);
            $imService->roomSendNotice(
                $room_id,
                ['type' => ImService::ROOM_USER_ROLE_REFRESH, 'user_id' => $to_user_id, 'role' => 0]
            );
        }
    }

    /**
     * 设置用户房间角色(房管或者陪陪)
     * @param $room_id    int 房间号
     * @param $to_user_id int 被操作人
     * @param $role       2=房管,3=陪陪
     * @return void
     */
    public function roomRoleSet($room_id, $to_user_id, $role)
    {
        $roomModel = new RoomModel();
        if (!in_array($role, [RoomModel::ROOM_ROLE_MANAGE, RoomModel::ROOM_ROLE_ANCHOR])) {
            throw new ApiException(__('Operation failed'));
        }
        $sel = db('room_admin')->where(['room_id' => $room_id, 'user_id' => $to_user_id])->value('role');
        if ($sel) {
            if ($sel == 1) {
                throw new ApiException(__('Operation forbidden'));
            }
            db('room_admin')->where(['room_id' => $room_id, 'user_id' => $to_user_id])->setField('role', $role);
        } else {
            db('room_admin')->insert(['room_id' => $room_id, 'user_id' => $to_user_id, 'role' => $role,]);
        }
        $imService = new ImService();
        $imService->roomSetAuth($room_id, $to_user_id, true);
        $imService->roomSendNotice(
            $room_id,
            ['type' => ImService::ROOM_USER_ROLE_REFRESH, 'user_id' => $to_user_id, 'role' => $role]
        );
    }


    public function quit_room($user_id, $room_id, $autonomic = 0, $is_kick = 0)
    {
        $redis = redis();
        if ($redis->hget(RedisService::USER_NOW_ROOM_KEY, $user_id) == $room_id) {
            $redis->hDel(RedisService::USER_NOW_ROOM_KEY, $user_id);
        }
        $redis->zRem(RedisService::ROOM_USER_KEY_PRE . $room_id, $user_id);

        $redis_del = $redis->sRem(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id, $user_id);//退出房间从排麦中删除
        $imService = new ImService();
        if ($redis_del) {
            $imService->roomSendNotice(
                $room_id,
                [
                    'type'  => ImService::ROOM_MIC_QUEUE_REFRESH,
                    'count' => $redis->sCard(RedisService::SEAT_GIFT_KEY_PRE . $room_id)
                ]
            );
            //非用户主动退出房间
            if ($autonomic) {
                $imService->room_wait_mic_delete($room_id, $user_id);
            }
        }
        //下座
        $this->sit_leave($room_id, $user_id);
        //房间全部人退出来后修改状态为休息中
        if (!$redis->zCard(RedisService::ROOM_USER_KEY_PRE . $room_id)) {
            db('room')->where('id', $room_id)->where('status', Room::ROOM_STATUS_PLAYING)
                ->setField('status', Room::ROOM_STATUS_IDLE);
        }
        $imService->roomSendNotice(
            $room_id,
            [
                'type'         => ImService::ROOM_ONLINE_USER_REFRESH,
                'online_count' => $redis->zCard(RedisService::ROOM_USER_KEY_PRE . $room_id)
            ]
        );
        if ($is_kick) {
            $imService->room_kick_user($room_id, $user_id);
        }
    }

    /**
     * 成员审核
     * @param $room_id
     * @param $user_id
     * @param $status
     * @return void
     * @throws
     */
    public function member_check($room_id, $user_id, $status): void
    {
        $role = db('room_admin')->where(['room_id' => $room_id, 'user_id' => $user_id])->find();
        if (!$role) throw new ApiException(__('Row does not exist'));
        db('room_admin')->where(['room_id' => $room_id, 'user_id' => $user_id])->update(['status' => $status]);
        // todo notice user

    }

    /**
     * 踢出成员
     * @param $room_id
     * @param $user_id
     * @return void
     * @throws
     */
    public function kick($room_id, $user_id)
    {
        $where = ['room_id' => $room_id, 'user_id' => $user_id, 'status' => ['in', '1,2']];
        $role = db('room_admin')->where($where)->count();
        if (!$role) throw new ApiException(__('User does not exist'));
        db('room_admin')->where($where)->setField(['status' => -2]);
        UserBusinessService::set_user_role($user_id, 1);
    }


}
