<?php

namespace app\common\model;

use think\Model;

class ChannelBlacklist extends Model
{
    // 表名
    protected $name = 'channel_blacklist';
    const ITEM_CHAT = 'ITEM_005';

    public static function get_blacklist($appid, $system, $version)
    {
        $blacklist = self::where(['appid' => $appid, 'system' => $system, 'version' => $version, 'status' => 1])->find();
        $list = $blacklist ? explode(',', $blacklist['item_code']) : [];
        return $list;
    }

}
