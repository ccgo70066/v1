<?php

namespace app\common\model;

use app\common\service\RankService;
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


}
