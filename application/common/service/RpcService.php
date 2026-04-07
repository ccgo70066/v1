<?php

namespace app\common\service;

use think\Env;

/**
 * Rpc服务类
 */
class RpcService
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    public function __call($name, $args)
    {
        trace('rpc/' . $name);
        trace($args);
        return $this->request(Env::get('app.rpc_url') . '/' . $this->controller . '/' . $name, $args[0]);
    }

    public function request($url, $params)
    {
        // 对参数进行 URL 编码
        $query_string = http_build_query($params, '', '&');
        // 发送 HTTP POST 请求
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $query_string,
            ),
        );
        $context = stream_context_create($options);
        trace('rpc request');
        trace($url);
        trace($options);
        return file_get_contents($url, false, $context);
    }
}
