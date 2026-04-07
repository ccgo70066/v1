<?php

namespace app\common\model;

use think\Model;


class ShopItem extends Model
{

    // 表名
    protected $name = 'shop_item';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    //类型:1=商城礼物,2=头像框,3=坐骑,4=贵族,5=个人守护,6=聊天气泡,8=铭牌
    const TYPE_GIFT = 1;
    const TYPE_ADORNMENT= 2;
    const TYPE_CAR = 3;
    const TYPE_NOBLE= 4;
    const TYPE_GUARD= 5;
    const TYPE_BUBBLE= 6;
    const TYPE_NAMEPLATE= 7;
    const TYPE_TAIL= 8;

    //分类:1=钻,2=红豆,3=能量
    const CATE_RED_DIAMOND = 1;
    const CATE_BLUE_DIAMOND= 2;
    const CATE_FRAGMENT = 3;

    //状态:1=上架中,0=已下架
    const STATUS_ON = 1;
    const STATUS_OFF= 2;
}
