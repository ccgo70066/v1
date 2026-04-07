<?php

namespace app\common\model;

use think\Model;


class NoblePrivilege extends Model
{
    // 表名
    protected $name = 'noble_privilege';
    //炫彩昵称
    const PERMISSION_ID_NAME_COLOR = 6;
    //房间热力提升
    const ROOM_HOT_ADD = 7;
    //房间防被踢
    const PERMISSION_ID_ROOM_BAN_TICK = 8;
    //房间隐身入场
    const PERMISSION_ID_ROOM_HIDE = 9;


}
