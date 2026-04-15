<?php

namespace app\common\model;

use think\Model;


class GiftSendStatistic extends Model
{
    // 表名
    protected $name = 'gift_send_statistic';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    /**
     * 送礼日统计
     * @param $user_id
     * @param $to_user_id
     * @param $value
     * @return void
     * @throws
     */
    public static function count_up($user_id, $to_user_id, $room_id, $value)
    {
        $sql = self::fetchSql(true)->insert([
            'user_id'    => $user_id,
            'to_user_id' => $to_user_id,
            'value'      => $value,
            'room_id'    => $room_id,
            'date'       => date('Y-m-d', time()),
        ]);
        self::execute($sql . " on duplicate key update value=value+{$value}");
    }


    /**
     * @param int $range   区间:1=周榜,2=日榜
     * @param int $type    类型:1=贡献,2=魅力
     * @param int $room_id 房间ID
     * @return array
     * @throws
     */
    public static function get_rank_data($range, $type, $room_id = 0)
    {
        $name = $type == 1 ? 'user_id' : 'to_user_id';

        list($start_time, $end_time) = self::get_rank_range($range, $room_id);

        $list = self::alias('l')
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
     * 获取榜单时间区间
     * @param int $type 类别:1=周榜,2=日榜
     * @return array
     */
    public static function get_rank_range($type, $room_id)
    {
        //1、房间日榜每天00:30更新，
        // 2、房间周榜每周一01:00更新
        // 00:00-00:30期间刷的魅力不会让榜单发生变化，00:30后才会统计上去
        // 3、总榜早上9点更新
        if ($room_id) {
            switch ($type) {
                case 1:
                    $time = time() - ((date('w', time()) == 0 ? 7 : date('w', time())) - 1) * 24 * 3600;    //本周一时间
                    $start_time = date('Y-m-d', $time);
                    $refresh_time = strtotime(date('Y-m-d 01:0:0', $time)); //刷新时间
                    $end_time = date('Y-m-d');
                    if ($refresh_time > time()) {
                        //上周榜单
                        $start_time = date('Y-m-d', strtotime('-1 monday', time()));//周一
                        $end_time = date('Y-m-d', strtotime('-1 sunday', time()));//周末
                    }
                    return array($start_time, $end_time);
                case 2:
                    $refresh_time = strtotime(date('Y-m-d 00:30:00'));    //榜单刷新时间
                    if ($refresh_time < time()) {
                        $start_time = date('Y-m-d');
                        $end_time = date('Y-m-d');
                    } else {
                        //当前时间位于早上0点30分前,显示昨日的排行
                        $start_time = date("Y-m-d", strtotime("-1 day"));//昨天
                        $end_time = date("Y-m-d", strtotime("-1 day"));//昨天
                    }
                    return array($start_time, $end_time);
            }
        } else {
            switch ($type) {
                case 1:
                    $time = time() - ((date('w', time()) == 0 ? 7 : date('w', time())) - 1) * 24 * 3600;    //本周一时间
                    $start_time = date('Y-m-d', $time);
                    $refresh_time = strtotime(date('Y-m-d 09:00:00', $time)); //刷新时间
                    $end_time = date('Y-m-d');
                    if ($refresh_time > time()) {
                        //上周榜单
                        $start_time = date('Y-m-d', strtotime('-1 monday', time()));//周一
                        $end_time = date('Y-m-d', strtotime('-1 sunday', time()));//周末
                    }
                    return array($start_time, $end_time);
                case 2:
                    $refresh_time = strtotime(date('Y-m-d 09:00:00'));    //榜单刷新时间
                    if ($refresh_time < time()) {
                        $start_time = date('Y-m-d');
                        $end_time = date('Y-m-d');
                    } else {
                        //当前时间位于早上9点前,显示昨日的排行
                        $start_time = date("Y-m-d", strtotime("-1 day"));//昨天
                        $end_time = date("Y-m-d", strtotime("-1 day"));//昨天
                    }
                    return array($start_time, $end_time);
            }
        }
    }


}
