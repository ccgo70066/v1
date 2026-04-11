<?php

namespace app\common\service;

use app\common\model\Union as UnionModel;

/**
 * 排行榜类
 */
class RankService
{
    /**
     * 判断用户是否是家族成员
     * @param int $room_id 房间ID
     * @param int $user_id 会员ID
     * @return bool
     */
    public static function checkIsUnionUser($room_id, $user_id)
    {
        if (!$room_id) {
            return true;
        }
        $isExist = db('room_admin')
            ->where('room_id', $room_id)
            ->where('user_id', $user_id)
            ->whereIn('status', [1, 2])
            ->count();
        if ($isExist) {
            return true;
        }
        return false;
    }

}
