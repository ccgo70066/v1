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
     * @param int $roomId 房间ID
     * @param int $userId 会员ID
     * @return bool
     */
    public static function checkIsUnionUser($roomId, $userId)
    {
        if (!$roomId) {
            return true;
        }
        $unionId = db('room')->where('id', $roomId)->value('union_id');
        $isExist = db('union_user')
            ->whereIn('status', UnionModel::STATUS_JOINED_RANGE)
            ->where('union_id', $unionId)
            ->where('user_id', $userId)
            ->count();
        if ($isExist){
            return true;
        }
        return false;
    }

}
