<?php

namespace app\common\model;

use app\common\exception\ApiException;
use app\common\model\Union as UnionModel;
use app\common\service\ImService;
use app\common\service\RedisService;
use app\common\service\RoomService;
use think\Model;

class Room extends Model
{
    // 表名
    protected $name = 'room';
    //麦上打赏统计:1=开启,2=暂停,3=停止
    const RoomPauseOn = 1;
    const RoomPauseSuspend = 2;
    const RoomPauseOff = 3;

    //PK功能:1=开启,0=关闭
    const RoomPkStatusOn = 1;
    const RoomPkStatusOff = 0;
    //房间状态:0=封禁 1=审核中2=休息中(无人在房)3=开播中(有人在房)
    const ROOM_STATUS_CANCEL = -1;  // 申请注销中
    const ROOM_STATUS_FORBIDDEN = 0;
    const ROOM_STATUS_AUDIT = 1;
    const ROOM_STATUS_IDLE = 2;
    const ROOM_STATUS_PLAYING = 3;
    //room_admin 房间角色
    const ROOM_ROLE_MASTER = 1;  //房主
    const ROOM_ROLE_MANAGE = 2;  //房管
    const ROOM_ROLE_ANCHOR = 3;  //陪陪


    public function getRoomById($room_id, $field = '*')
    {
        return db('room')->where('id', $room_id)->field($field)->find();
    }

    public static function getRoomByImRid($im_room_id, $field = '*')
    {
        return $im_room_id;
    }


    /**
     * 获取可展示的房间列表
     */
    public static function getRoomList($where = [], $limit = 0)
    {
        $query = db('room');
        if ($where) {
            $query->where($where);
        }
        if ($limit) {
            $query->limit($limit);
        }
        // 个人房列表中，无人的过滤掉不在列表中展示
        $map = ['status' => ['in', [self::ROOM_STATUS_IDLE, self::ROOM_STATUS_PLAYING]]];
        return $query->where($map)->where(['is_close' => 0,])
            ->order('show_sort asc,hot desc,create_time')
            ->field('id,beautiful_id,name,is_lock,hot,cover,owner_id')
            ->select();
    }

    /**
     * 清空个人房间所有用户
     * @param int $room_id
     * @return int|string
     */
    public function clearPersonRoomUser(int $room_id)
    {
        return $this->where('id', $room_id)->setField([
            'no1_user_id' => null,
            'no2_user_id' => null,
            'no3_user_id' => null,
            'no4_user_id' => null,
            'no5_user_id' => null,
            'no6_user_id' => null,
            'no7_user_id' => null,
            'no8_user_id' => null,
            'no9_user_id' => null,
        ]);
    }


    /**
     * 添加访问记录
     * @param $room_id
     * @return void
     */
    public function add_enter_log($user_id, $room_id)
    {
        $log = db('room_enter_log')->where(['room_id' => $room_id, 'user_id' => $user_id])->find();
        if ($log) {
            db('room_enter_log')->where(['room_id' => $room_id, 'user_id' => $user_id])->setField(
                'create_time',
                datetime()
            );
        } else {
            db('room_enter_log')->insert(['room_id' => $room_id, 'user_id' => $user_id]);
        }
    }

    /**下座
     * @param $room_id
     * @param $user_id
     */
    public function sit_leave($room_id, $user_id)
    {
        $res = db('room')->where('id', $room_id)
            ->field('no1_user_id,no2_user_id,no3_user_id,no4_user_id, no5_user_id,no6_user_id,no7_user_id,no8_user_id,no9_user_id')->find();
        foreach ($res as $k => $v) {
            if ($res[$k] == $user_id) {
                db('room')->where('id', $room_id)->setField($k, 0);
            }
        }
    }

    /**重置座位打赏金额
     * @param $room_id int 房间号
     * @param $seat_no int 指定座位号1-9,默认全部
     */
    public function sit_reset($room_id, $seat_no = null)
    {
        $roomService = new RoomService();
        if ($seat_no) {
            db('room_seat_gift_info')->where(['room_id' => $room_id, 'seat' => $seat_no])->delete();
            $roomService->clearSeatGiftValue($room_id, $seat_no);
        } else {
            db('room_seat_gift_info')->where(['room_id' => $room_id])->delete();
            $roomService->clearSeatGiftValue($room_id);
        }
    }


    /**
     * 获取IM聊天室ID
     * @ApiInternal
     * @param $room_id
     * @return int
     */
    public function getImRoomId($room_id, $throw = 1)
    {
        return $room_id;
    }

    /**
     * 是否是家族成员
     * @ApiInternal
     * @param $room_id int 房间ID
     * @param $user_id int 用户ID
     * @param $role    array|string 角色要求(多选) 如 [1,2] 1=族长,2=普通成员
     * @return int|string 1|0
     */
    public function is_union_user($union_id, $user_id, $role)
    {
        return db('union_user')->where([
            'union_id' => $union_id,
            'user_id'  => $user_id,
            'status'   => ['in', UnionModel::STATUS_JOINED_RANGE],
            'role'     => ['in', $role]
        ])->count(1);
    }

    /** 是否是房间黑名单
     * @param $room_id
     * @param $user_id
     */
    public function is_blacklist($room_id, $user_id)
    {
        $sel = db('room_blacklist')
            ->where([
                'room_id' => $room_id,
                'user_id' => $user_id
            ])
            ->count();
        return $sel;
    }

    /**
     * todo 待删除
     * 新增房间操作日志
     * @param        $room_id      int 房间号
     * @param        $user_id      int 操作人
     * @param        $text         string 内容
     * @param        $to_user_id   int 被操作人
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

    /** 添加贵族记录
     * // todo 可删
     * @ApiInternal
     * @param $room_id
     * @param $user_id
     */
    public function room_noble_log($room_id, $user_id)
    {
        $redis = redis();
        $user_noble_id = db('user_noble')->where([
            'user_id'    => $user_id,
            'start_time' => ['<', date('Y-m-d H:i:s', time())],
            'end_time'   => ['>', date('Y-m-d H:i:s', time() - config('app.noble_protection_time'))]
        ])->value('noble_id');

        if (!$user_noble_id) {
            return;
        }

        $log = db('room_noble_log')->where(['user_id' => $user_id, 'room_id' => $room_id])->order('id desc')->find();
        if ($log) {
            if ($log['noble_id'] != $user_noble_id) {
                db('room_noble_log')->where(['user_id' => $user_id, 'room_id' => $room_id])->setField(
                    'noble_id',
                    $user_noble_id
                );
            }
        } else {
            db('room_noble_log')->insert([
                'user_id'  => $user_id,
                'room_id'  => $room_id,
                'noble_id' => $user_noble_id
            ]);
        }

        if (db('noble')->where('id', $user_noble_id)->where("find_in_set(" . NoblePrivilege::ROOM_HOT_ADD . ",`privilege_ids`)")->find()) {
            //热力进场
            if (!$log || $log['create_time'] < date('Y-m-d 0:0:0')) {
                db('room_noble_log')->where(['user_id' => $user_id, 'room_id' => $room_id])->setField('create_time', datetime());
                $hot = $redis->hIncrBy(RedisService::ROOM_HOT_KEY, $room_id, 1000);
                $hot = $hot > 0 ? $hot : 0;
                $im = new ImService();
                $im->roomSendNotice($room_id, ['type' => ImService::ROOM_HOT_REFRESH, 'hot' => $hot]);
            }
        }
    }

    /**
     * 热力进场, 1天1次
     * @param $room_id
     * @param $user_id
     * @return void
     */
    public function hot_in($room_id, $user_id)
    {
        $key = 'hot_in:' . $room_id . ':' . $user_id;
        if (cache($key) != 1) {
            $redis = redis();
            $hot = $redis->hIncrBy(RedisService::ROOM_HOT_KEY, $room_id, 1000);
            $hot = $hot > 0 ? $hot : 0;
            $im = new ImService();
            $im->roomSendNotice($room_id, ['type' => ImService::ROOM_HOT_REFRESH, 'hot' => $hot]);
            cache($key, 1, strtotime('tomorrow') - time(), 'hot_in');
        }
    }

    /*
     * todo 待删除
     * 退出房间
     */
    public function quit_room($user_id, $room_id, $autonomic = 0, $is_kick = 0)
    {
        trace(['quit_room', $user_id, $room_id]);
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

}
