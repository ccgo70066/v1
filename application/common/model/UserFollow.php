<?php

namespace app\common\model;

use app\common\service\UserBaseStatisticsService;
use think\Model;


class UserFollow extends Model
{

    // 表名
    protected $name = 'user_follow';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_mutual_text'
    ];


    public function getIsMutualList()
    {
        return ['0' => __('Is_mutual 0'), '1' => __('Is_mutual 1')];
    }

    public function getIsMutualTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_mutual']) ? $data['is_mutual'] : '');
        $list = $this->getIsMutualList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    /**
     * 获取在范围内某用户关注的用户id结果集
     * @param $userId
     */
    public function getUserFollowIdsByIds($userId, $targetIds)
    {
        return $this->where('user_id', $userId)->where('to_user_id', 'in', $targetIds)->column('to_user_id');
    }


    /**
     * 关注
     * @param $user_id
     * @param $to_user_id
     * @return void
     */
    public static function follow($user_id, $to_user_id)
    {
        $data = ['user_id' => $user_id, 'to_user_id' => $to_user_id];
        $exist = self::where($data)->count();
        if (!$exist) {
            $follower_where = ['user_id' => $to_user_id, 'to_user_id' => $user_id];
            $follower = self::where($follower_where)->find();
            if ($follower) {
                $data['is_mutual'] = 1;
                self::where($follower_where)->setField(['is_mutual' => 1]);
            }
            self::insert($data);
            UserBaseStatisticsService::setUserStatistics($user_id, 'follow_num', 'increase');
            UserBaseStatisticsService::setUserStatistics($to_user_id, 'fan_num', 'increase');
        }
    }

    /**
     * 取消关注
     * @param $user_id
     * @param $to_user_id
     * @return void
     */
    public static function unfollow($user_id, $to_user_id)
    {
        $result = self::where(['user_id' => $user_id, 'to_user_id' => $to_user_id])->delete();
        self::where(['user_id' => $to_user_id, 'to_user_id' => $user_id])->setField(['is_mutual' => 0]);
        if ($result) {
            UserBaseStatisticsService::setUserStatistics($user_id, 'follow_num', 'decrease');
            UserBaseStatisticsService::setUserStatistics($to_user_id, 'fan_num', 'decrease');
        }
    }



    /**
     * 是否是好友关系
     * @param $userId
     * @param $firsendUid
     * @return int|string
     * @throws \think\Exception
     */
    public static function isFriend($userId, $firsendUid)
    {
        return self::where([
            'user_id' => $userId,
            'to_user_id' => $firsendUid,
            'is_mutual' => 1,
        ])->find();
    }


    /**
     * 是否是粉丝关系
     * @param $userId
     * @param $firsendUid
     * @return int|string
     * @throws \think\Exception
     */
    public static function isFans($userId, $firsendUid)
    {
        return self::where([
            'user_id' => $userId,
            'to_user_id' => $firsendUid,
        ])->find();
    }


}
