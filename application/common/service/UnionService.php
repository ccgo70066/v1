<?php

namespace app\common\service;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\library\ApiException;
use app\common\model\Room;
use app\common\model\Shield;
use app\common\model\Union;

use function app\api\library\board_notice;
use function app\api\library\send_im_msg_by_system_with_lang;

/**
 * 家族服务类
 */
class UnionService
{

    /**
     * 是否已签约为主播
     * @return bool
     */
    public function isAnchor($user_id)
    {
        $role = db('user_business')->where('id', $user_id)->value('role');
        return $role == 2;
    }

    /**
     * 创建家族
     * @param $user_id
     * @return true
     */
    public function createUnion($user_id, $logo, $name, $content, $sign_img = '')
    {
        $sel = db('union_user')->where(['user_id' => $user_id, 'status' => ['in', Union::STATUS_JOINED_RANGE]])->find();
        if ($sel) {
            throw new ApiException(__('You have joined another clan'));
        }

        $sel = db('union')->where(['owner_id' => $user_id, 'status' => ['neq', 3]])->find();
        if ($sel) {
            throw new ApiException(__('You already have a clan'));
        }
        if (!$logo || !$name || !$content) {
            throw new ApiException(__('Application information incomplete'));
        }
        db('union_user')->where('user_id', $user_id)->delete();
        $result = db('union')->insert([
            'logo'     => $logo,
            'name'     => Shield::sensitive_filter($name),
            'content'  => Shield::sensitive_filter($content),
            'status'   => Union::UNION_STATUS_AUDIT,
            'sign_img' => $sign_img,
            'owner_id' => $user_id,
        ]);
        return (bool)$result;
    }


    public function expelUnion($union_id, $to_user_id)
    {
        $is_room_master = db('room_admin ra')
            ->join('room r', 'ra.room_id = r.id')
            ->where(['ra.role' => 1, 'r.union_id' => $union_id, 'ra.user_id' => $to_user_id])
            ->count();

        if ($is_room_master) {
            throw new ApiException(__('Need to transfer room ownership before leaving clan as room owner'));
        }

        $union = db('union')->where('id', $union_id)->find();
        if (!$union) {
            throw new ApiException(__('Clan ID error'));
        }

        $nickname = db('user')->where('id', $to_user_id)->value('nickname');
        $target_user = db('union_user')->where([
            'union_id' => $union_id,
            'user_id'  => $to_user_id,
            'status'   => ['in', Union::STATUS_JOINED_RANGE]
        ])->count();
        if (!$target_user) {
            throw new ApiException(__('This user is not a clan member'));
        }

        db('union_user')->where(['user_id' => $to_user_id, 'union_id' => $union_id])->delete();
        //解除用户与家族关联关系
        db('user_business')->where('id', $to_user_id)->setField(['union_id' => 0, 'role' => 1]);

        (new Union())->add_union_log($union_id, $union['owner_id'], "将{$nickname}移出家族", $to_user_id);

        $room_ids = db('room')->where('status', 'in', [Room::ROOM_STATUS_IDLE, Room::ROOM_STATUS_PLAYING])
            ->where('union_id', $union_id)->column('id');

        $roomService = new RoomService();
        foreach ($room_ids as $room_id) {
            $roomService->roomRoleRemove($room_id, $to_user_id);
        }
        board_notice(Message::CMD_REFRESH_USER, ['user_id' => $to_user_id]);
        //系统消息
        send_im_msg_by_system_with_lang($to_user_id, '您已离开%s家族，去其他家族看看吧', $union['name']);
    }

}
