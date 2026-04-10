<?php

namespace app\common\model;
use think\Db;
use think\Model;

class Gift extends Model
{

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [];

    //状态:1=启用,0=禁用
    const STATUS_OFF = 0;
    const STATUS_ON = 1;

    //送礼飘屏设置:0=不飘屏,1=飘屏,2=价值飘屏
    const ScreenShowOff = 0;
    const ScreenShowOn = 1;
    const ScreenShowPrice = 2;

    //送礼方式：-1=背包单个礼物全送,1=背包选择数量送,2=面板送礼,3=盲盒送4=一键清包,5=私聊送礼
    const GIVE_TYPE_BAG_ONE_ALL = -1;
    const GIVE_TYPE_BAG = 1;
    const GIVE_TYPE_PANEL = 2;
    const GIVE_TYPE_BOX = 3;
    const GIVE_TYPE_BAG_ALL = 4;
    const GIVE_TYPE_IM = 5;


    //礼物类别:1=面板礼物,2=幸运礼物(盲盒中奖礼物),3=捞月湖(游戏),4=海贼王(游戏),5=大冒险(游戏),6=其它礼物
    const GIFT_TYPE_BOARD = 1;
    const GIFT_TYPE_BOX = 2;
    const GIFT_TYPE_EGG = 3;
    const GIFT_TYPE_WHEEL = 4;
    const GIFT_TYPE_GAME = 5;
    const GIFT_TYPE_OTHER = 6;



    //礼物-二级类别:10=热门,11=专场,12=特权,13=土豪(启用),14=幸运盲盒,20=玫瑰之恋,21=星语星愿,22=锦鲤祈愿
    const GIFT_CATE_HOT = 10;
    const GIFT_CATE_SPECIAL = 11;
    const GIFT_CATE_PRIVILEGE = 12;
    const GIFT_CATE_JET = 13;
    const GIFT_CATE_BOX = 14;
    const GIFT_CATE_WISH_BOTTLE = 20;
    const GIFT_CATE_SPRING = 21;
    const GIFT_CATE_KOI_BOTTLE = 22;
    const GIFT_CATE_BAG = 100;  //背包礼物

    //礼物类别(面板礼物)-数组
    const GIFT_TYPE_BOARD_CATES = [
        self::GIFT_CATE_HOT => '热门',
        self::GIFT_CATE_SPECIAL => '专场',
        self::GIFT_CATE_PRIVILEGE => '特权',
        //self::GIFT_CATE_BOX => '盲盒',
    ];
    //礼物类别(幸运礼物)-数组
    const GIFT_CATE_SPECIAL_CATES = [
        self::GIFT_CATE_WISH_BOTTLE => '穹翼银龛',
        self::GIFT_CATE_SPRING => '炎凰金匣',
        self::GIFT_CATE_KOI_BOTTLE => '紫霄龍龕',
    ];

    public static function getGiftById($gift_id, $filed = '*', $where = [])
    {
        $result = db('gift')->where($where)->where('id', $gift_id)->field($filed)->find();
        return $result;
    }

    public function getGiftList($filed = '*', $where = ['status' => 1, 'type' => 1], $order = 'weigh asc')
    {
        $result = db('gift')->where($where)->field($filed)->order($order)->select();
        return $result;
    }


}
