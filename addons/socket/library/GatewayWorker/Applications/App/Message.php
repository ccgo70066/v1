<?php

namespace addons\socket\library\GatewayWorker\Applications\App;

/**
 * 消息号
 * @desc  备注后方的json为消息中data的数据
 */
class Message
{

    const CMD_APP_LOGIN = 2002;  // 用户登录
    const CMD_APP_OTHER_LOGIN = 2000; // 用户多地登录
    const CDM_ROOM_COMMEND = 1001; // 推荐陪陪及所在房间
    const CDM_LOG_REPORT = 6001; // 通知前端上传日志

    // 消息编号 cmd
    const CMD_HEART = 1000; // @var int 心跳
    const CMD_CONFIG_UPDATE = 1002; // 配置更新推送  {"version":1}
    const CMD_MESSAGE = 2000; // @var int 普通消息
    const CMD_LOGIN = 2001; // @var int 登录
    const CMD_OTHER_LOGIN = 2002;
    const CMD_ENTER_ROOM = 2003; // @var int 进入房间
    const CMD_EXIT_ROOM = 2004; // @var int 退出房间
    const CMD_LOSS_NOTICE = 2006; // @var int 用户掉线推送给房间
    /**
     * 头像,形像照,vip升级,
     */
    const CMD_REFRESH_USER = 2010; // 用户信息更新推送
    const CMD_GIFT_PUSH = 2015; // 资源推送

    const CMD_NOTICE = 3998; // 全服通知(可关闭)   {"cmd":3999, "msg":"", "data":{"title":"test", "content":"cccccc"}}
    const CMD_SYSTEM = 3999; // 系统通知(不可关闭)    接口返回code=999则前端开启全服维护状态
    const CMD_KICK_USER = 4000; // 踢用户掉线推送

    // 飄屏
    const CMD_SHOW_GIFT_BOX = 2102; // 幸运瓶开出大礼物飘屏
    const CMD_SHOW_GIFT_GLOBAL = 2014;  //全局广播礼物
    const CMD_SHOW_BUY_NOBLE = 2103; // 商城购买贵族飘屏
    const CMD_SHOW_BUY_GUARD = 2021; // 购买守护飘屏
    const CMD_SHOW_LEVEL_UP = 2222; // 等级升级飘屏

    const CMD_LUCKY_MONEY = 2301;  // 厅红包推送


    // 游戏推送
    const CMD_EGG_NOTICE = 8101; // 扭蛋飘屏
    const CMD_EGG_REWARD_NOTICE = 8102;  // 扭蛋达成组合奖励获得下一局双倍奖励
    const CMD_EGG_SECRET = 8106; // 神秘彩蛋
    const CMD_EGG_SECRET_OPEN = 8107; // 神秘彩蛋開出xx禮物

    const CMD_SCREEN_ALL_ROOM = 8201;  // 公屏推送 所有房间 ignore_room_id为排除的房间号由前端处理排除逻辑
    const CMD_SCREEN_ROOM = 8202;  // 公屏推送 指定房间
    const CMD_EGG_CHAT_NOTICE = 8105;  // 遊戲公屏追加 扭蛋
    const CMD_WHEEL_CHAT_NOTICE = 8003;  // 遊戲公屏追加  轉盤

    const CMD_BOARD_WHEEL = 2027;  // 桃花签游戏飘屏


    // 信息码 code
    // @var int 请求成功
    const CODE_SUCCESS = 200;
    // @var int 请求报文语法错误
    const CODE_BAD = 400;
    // @var int 请求没有认证
    const CODE_UNAUTHORIZED = 401;
    // @var int 请求被禁止
    const CODE_FORBIDDEN = 403;
    // @var int 请求资源没有找到
    const CODE_NOT_FOUND = 404;
    // @var int 其它地方登录
    const CODE_OTHER_LOGIN = 405;
    // @var int 失败
    const CODE_FAIL = 500;

    public static function json($cmd, $data = null, $msg = '', $code = Message::CODE_SUCCESS)
    {
        return json_encode([
            'cmd'  => $cmd,
            'msg'  => $msg,
            'code' => $code,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }
}
