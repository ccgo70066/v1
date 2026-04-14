<?php

namespace app\admin\model;

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
    public static function count_up($user_id, $to_user_id,$room_id, $value)
    {
        $sql = self::fetchSql(true)->insert([
            'user_id'    => $user_id,
            'to_user_id' => $to_user_id,
            'value'      => $value,
            'room_id'    => $room_id,
            'date'       => date('Y-m-d',time()),
        ]);
        self::execute($sql . " on duplicate key update value=value+{$value}");
    }


    /**
     * @param int $range   区间:1=周榜,2=日榜,3=月榜
     * @param int $type    类型:1=贡献,2=魅力
     * @param int $room_id 房间ID
     * @return array
     * @throws
     */
    public static function get_rank_data($range, $type, $room_id = 0)
    {
        $name = $type == 1 ? 'user_id' : 'to_user_id';
        $room_where = $room_id == 0 ? [] : ['l.room_id' => $room_id];
        $list = self::get_custom_model($range, $name, $room_id)
            ->where($room_where)
            ->group("l.{$name}")
            ->field("l.{$name} as user_id,sum(value) as value")
            ->order('value desc')
            ->limit(110)
            ->select();
        $list =  collection($list)->toArray();
        return self::filter_noble_hide($list);

    }

    public static function get_custom_model($range, $name = 'user_id')
    {
        list($start_time, $end_time) = self::get_rank_range($range);
        return self::alias('l')
            ->join('user u', "l.{$name}=u.id", 'left')
            ->whereBetween('l.date', [$start_time, $end_time]);
    }

    public static function filter_noble_hide($list)
    {
        $hide_user = db('user_noble')->where('rank_hide',1)->column('user_id');
        foreach ($list as $key=>$value) {
            if (in_array($value['user_id'],$hide_user)) {
                unset($list[$key]);
            }
        }
        return array_slice($list, 0, 99);
    }


    /**
     * 获取榜单时间区间
     * @param int $type 类别:1=周榜,2=日榜,3=月榜
     * @return array
     */
    public static function get_rank_range($type = 2)
    {

        switch ($type) {
            case 1:
                $time = time() - ((date('w', time()) == 0 ? 7 : date('w', time())) - 1) * 24 * 3600;    //本周一时间
                $start_time = date('Y-m-d', $time);
                $refresh_time = strtotime(date('Y-m-d 1:0:0', $time)); //刷新时间
                $end_time = date('Y-m-d');
                if ($refresh_time > time()) {
                    //上周榜单
                    $start_time = date('Y-m-d', strtotime('-1 monday', time()));//周一
                    $end_time = date('Y-m-d', strtotime('-1 sunday', time()));//周末
                }
                return array($start_time, $end_time);
            case 2:
                $start_time = date('Y-m-d');
                $end_time = date('Y-m-d');
                return array($start_time, $end_time);
            case 3:
                $refresh_time = strtotime(date('Y-m-1 1:0:0'));    //榜单刷新时间
                if ($refresh_time < time()) {
                    $start_time = date('Y-m-1');
                    $end_time = date('Y-m-d');
                }else {
                    //当前时间位于早上1点前,防止榜单数据量少,1点前显示昨日的排行
                    $start_time = date('Y-m-1', strtotime('-1 month'));
                    $end_time = date('Y-m-t', strtotime('-1 month'));
                }
                return array($start_time, $end_time);
        }
    }

    public static function get_rank_user($range, $type, $user_id, $room_id = 0)
    {
        $name = $type == 1 ? 'user_id' : 'to_user_id';
        $room_where = $room_id == 0 ? [] : ['l.room_id' => $room_id];
        $result = self::get_custom_model($range, $name)
            // ->cache($key, Date::get_next(), 'small_data_rank')
            ->where("l.{$name}", $user_id)
            ->where($room_where)
            ->field('ifnull(sum(value),0) as value,"99+" as `index`')
            ->find()->toArray();
        $result['user_id'] = $user_id;
        // 统计值为0, 前端处理index显示为'--'
        if ($result['value'] > 0) {
            $rank = self::get_rank_data($range, $type, $room_id);
            foreach ($rank as $k => $v) {
                if ($v['user_id'] == $user_id) {
                    $result['index'] = $k + 1;
                    break;
                }
            }
        }
        return $result;
    }

}
