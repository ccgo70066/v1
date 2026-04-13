<?php

namespace app\common\service;


/**
 * 用户商品相关
 */
class MallService extends BaseService
{
    /**
     * 获取所有礼物数组
     */
    public static function getGiftArr()
    {
        $data = db('gift')
            ->where('status', 1)
            ->column('id,name,image', 'id');
        return $data;
    }

    /**
     * 获取所有头像框数组
     */
    public static function getAdornmentArr()
    {
        $data = db('adornment')
            ->where('status', 1)
            ->where('is_sell', 1)
            ->column('id,name,cover as image', 'id');
        return $data;
    }

    /**
     * 获取所有聊天气泡数组
     */
    public static function getBubbleArr()
    {
        $data = db('bubble')
            ->where('status', 1)
            ->where('is_sell', 1)
            ->column('id,name,cover as image', 'id');
        return $data;
    }

    /**
     * 获取所有坐骑数组
     */
    public static function getCarArr()
    {
        $data = db('car')
            ->where('status', 1)
            ->where('is_sell', 1)
            ->column('id,name,cover as image', 'id');
        return $data;
    }

    /**
     * 获取所有贵族数组
     */
    public static function getNobleArr()
    {
        $data = db('noble')
            ->column('id,name,badge as image', 'id');
        return $data;
    }

    /**
     * 获取所有铭牌数组
     */
    public static function getTailArr()
    {
        $data = db('tail')
            ->where('status', 1)
            ->column('id,name,face_image as image', 'id');
        return $data;
    }
}
