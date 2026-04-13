<?php

namespace app\common\service;

class RedisService extends BaseService
{
    //常量命名规范：描述key的含义
    //key命名规范：业务模块：key描述.value描述.[key分割:]
    //哈希:用户在哪个房间
    const USER_NOW_ROOM_KEY = 'room:uid.rid';
    //哈希:房间热力值
    const ROOM_HOT_KEY = 'room:rid.hot';
    //有序集合:房间在线用户集合,排序值为用户进入房间的时间戳
    const ROOM_USER_KEY_PRE = 'room:rid.onlineUid:';
    //集合:排麦用户列表
    const SEAT_WAIT_QUEUE_KEY_PRE = 'room:rid.seatWaitUid:';
    //哈希:麦上统计
    const SEAT_GIFT_KEY_PRE = 'room:seat.gift:';
    //String：房间倒计时
    const ROOM_COUNTDOWN_KEY_PRE = 'room:countdown:';
    //哈希:运营设置房间为休息
    const ADMIN_SET_ROOM_NOT_SHOW = 'room:notShowRid';
    //哈希:探险投注列表
    const FLY_GAME_USER_LIST = 'fly:userList:';
    //哈希:用户基础数据列表(id、昵称、头像、appid)
    const USER_BASE_LISTS_KEY = 'user:base.lists:';
    //哈希:等级基础数据列表
    const LEVEL_BASE_LIST_KEY = 'level:base.list';
    //哈希:礼物基础数据列表(id、名称、图片、价格)
    const GIFT_BASE_LISTS_KEY = 'gift:base.lists:';
    //哈希:管理员基础数据列表(id、昵称)
    const ADMIN_BASE_LISTS_KEY = 'admin:base.lists:';
    //哈希:打款方式基础数据列表(id、名称)
    const PAYMENT_WAY_BASE_LISTS_KEY = 'payment_way:base.lists:';

    public static function getCache($table, $id, $field = '')
    {
        $data = db($table)->cache(cacheFlag(), 3600, $table)->find($id);
        return self::returnDataField($data, $field);
    }

    public static function getGiftCache($id, $field = '', $lang = 'zh')
    {
        $table = 'gift';
        $data = db($table)->cache(cacheFlag(), 3600, $table)->find($id);
        return self::returnDataField($data, $field);
    }


    /*
     * 取用户缓存信息
     */
    public static function getUserCache($id, $field)
    {
        $table = 'user';
        $data = db($table)->cache(cacheFlag(), 3600, $table)->field('id,nickname,avatar')->find($id);
        return self::returnDataField($data, $field);
    }

    /*
     * 获取用户缓存信息
     */
    public static function getLevelCache($key = '')
    {
        if ($key == 'all' && !is_numeric($key)) {
            return db('level')->column('icon', 'grade');
        } else {
            return db('level')->where('grade', $key)->value('icon');
        }
    }

    /*
     * 获取管理员缓存信息
     */
    public static function getAdminCache($id, $field)
    {
        $table = 'admin';
        $data = db($table)->cache(cacheFlag(), 3600, $table)->field('id,nickname')->find($id);
        return self::returnDataField($data, $field);
    }

    /*
     * 获取打款方式缓存信息
     */
    public static function getPaymentWayCache($id, $field)
    {
        $table = 'payment_way';
        $data = db($table)->cache(cacheFlag(), 3600, $table)->field('id,name,fee,company_id,pay_way_id')->find($id);
        return self::returnDataField($data, $field);
    }

    /**
     * @param $data
     * @param $field
     * @return array|mixed
     */
    public static function returnDataField($data, $field)
    {
        if (!$data) return null;
        if ($field == '' || $field == 'all') return $data;
        if (is_array($field)) return array_intersect_key($data, array_flip($field));
        if (strpos($field, ',')) return array_intersect_key($data, array_flip(explode(',', $field)));
        return $data[$field];
    }

}
