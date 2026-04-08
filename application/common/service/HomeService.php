<?php

namespace app\common\service;

use app\common\model\Room as RoomModel;

/**
 * 主页服务类
 */
class HomeService
{
    public function roomThemeList($type = 0)
    {
        return RoomModel::getThemeList($type);
    }


    //获取房间列表
    public function roomList($where = [], $limit = 30)
    {
        $result = RoomModel::getRoomList($where, $limit);
        $redis = redis();

        $condition = array_column((array)$result, 'owner_id');
        $owner = db('user')->where('id', 'in', $condition)->column('avatar', 'id');
        //获取房间类似数组
        //$roomCateArr = $this->getRoomCate();
        foreach ($result as $k => &$v) {
            $room_user = $redis->zRevRange(RedisService::ROOM_USER_KEY_PRE . $v['id'], 0, 5);
            $result[$k]['room_user'] = db('user')->where('id', 'in', $room_user)->limit(5)->column('avatar');
            $v['owner_avatar'] = $owner[$v['owner_id']] ?? '';
        }
        return $result;
    }


    /**
     * 获取房间类似数组
     * @return array|false|string
     */
    public function getRoomCate()
    {
        $data = db('room_theme_cate')
            ->where('status', 1)
            ->order('weigh desc')
            ->column('name,color,image', 'id');
        return $data;
    }

}
