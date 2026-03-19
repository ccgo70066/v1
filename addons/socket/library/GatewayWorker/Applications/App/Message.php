<?php

namespace addons\socket\library\GatewayWorker\Applications\App;

/**
 * 消息号
 * @desc  备注后方的json为消息中data的数据
 */
class Message
{

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
