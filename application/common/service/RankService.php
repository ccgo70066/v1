<?php

namespace app\common\service;

/**
 * 排行榜类
 */
class RankService extends BaseService
{
    protected static self $instance;

    public static function instance(): static
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

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

    /**
     * @param int $range   "類別:1=今日,2=昨日,3=本周,4=上周"
     * @param int $type    类型:1=贡献,2=魅力
     * @param int $room_id 房间ID
     * @return array
     * @throws
     */
    public function get_rank_data($range, $type, $room_id = 0): array
    {
        $name = $type == 1 ? 'user_id' : 'to_user_id';
        [$start_time, $end_time] = $this->range_to_time($range);

        $list = db('gift_send_statistic')->alias('l')
            ->join('user u', 'u.id=l.' . $name, 'left')
            ->join('user_business ub', 'ub.id=l.' . $name, 'left')
            ->whereBetween('l.date', [$start_time, $end_time])
            ->where($room_id ? ['l.room_id' => $room_id] : [])
            ->group("l.{$name}")
            ->field("l.{$name} as user_id,sum(value) as value,u.nickname,u.avatar,ub.level")
            ->order('value desc')
            ->limit(100)
            ->select();
        return collection($list)->toArray();
    }

    /**
     * @param int $range "類別:1=今日,2=昨日,3=本周,4=上周"
     */
    public function range_to_time(int $range): array
    {
        switch ($range) {
            case 2:
                $start_time = date("Y-m-d", strtotime("-1 day"));
                $end_time = date("Y-m-d");
                break;
            case 3:
                $start_time = date("Y-m-d", strtotime("this week"));
                $end_time = date("Y-m-d", strtotime("this week + 7 day"));
                break;
            case 4:
                $start_time = date("Y-m-d", strtotime("last week"));
                $end_time = date("Y-m-d", strtotime("last week + 7 day"));
                break;
            case 1:
            default:
                $start_time = date('Y-m-d');
                $end_time = date('Y-m-d', strtotime('+1 day'));
                break;
        }

        return [$start_time, $end_time];
    }

    /**
     * 送礼日统计
     * @param $user_id
     * @param $to_user_id
     * @param $value
     * @return void
     * @throws
     */
    public function count_up($user_id, $to_user_id, $room_id, $value)
    {
        $sql = db('gift_send_statistic')->fetchSql()->insert([
            'user_id'    => $user_id,
            'to_user_id' => $to_user_id,
            'value'      => $value,
            'room_id'    => $room_id,
            'date'       => date('Y-m-d', time()),
        ]);
        db()->execute($sql . " on duplicate key update value=value+{$value}");
    }


}
