<?php

namespace app\common\model;

use think\Model;

class UserBusinessLog extends Model
{
    // 表名
    protected $name = 'user_business_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [];

    //数值类型:1=积分,2=钻石,3=红豆,4=收益
    const TYPE_INTEGRAL = 1; //积分
    const TYPE_DIAMOND = 2; //钻石
    const TYPE_RED_BEANS = 3; //红豆
    const TYPE_INCOME = 4; //收益

    //分类:0=其它,1=商城兑换,2=活动奖励,3=充值钻石,4=打赏礼物,6=IM红包,7=房间红包,8=兑换钻石,9=兑换游戏券,10=守护分成,11=家族收益提领,12=流水奖励,13=收益提现
    const FROM_MALL_EXCHANGE = 1; //商城兑换
    const FROM_TASK = 2; //活动奖励
    const FROM_RECHARGE = 3; //充值钻石
    const FROM_REWARD = 4; //打赏礼物
    const FROM_IM_LUCKY_MONEY = 6; //IM红包
    const FROM_ROOM_LUCKY_MONEY = 7; //房间红包
    const FROM_CHANGE_DIAMOND = 8; //兑换钻石
    const FROM_GAME_ARCH = 9; //兑换游戏券
    const FROM_GUARD_REWARD = 10; //守护分成
    const FROM_UNION_REWARD = 11; //家族收益提领
    const FROM_TURNOVER_REWARD = 12; //家族周流水扶持
    const FROM_INCOME_WITHDRAWAL = 13; //收益提现

    //变化类型:1=增加,0=减少
    const CATE_ADD = 1; //增加
    const CATE_SUB = 0; //减少
}
