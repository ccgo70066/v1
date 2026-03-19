<?php

namespace addons\smsbao\library;

use fast\Random;

/**
 * @link  http://183.178.45.166/login
 * Account:  wang xiao
 * Password: 0124
 * Afyr1NUKRoL4phIvGCSvlCCc7wA5BbBS
 * rCp7ir
 */
class Sms001
{
    private $_params = [];
    protected $error = '';
    protected $config = [
        'apiKey'   => 'Afyr1NUKRoL4phIvGCSvlCCc7wA5BbBS',
        'username' => 'rCp7ir',
    ];
    protected static $instance = null;

    public function __construct($options = [])
    {
        //if ($config = get_addon_config('smsbao')) {
        //    $this->config = array_merge($this->config, $config);
        //}
        $this->config = array_merge($this->config, is_array($options) ? $options : []);
    }

    /**
     * 单例
     * @param array $options 参数
     * @return Smsbao
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }

    /**
     * 立即发送短信
     *
     * @return boolean
     */
    public function send()
    {
        $this->error = '';
        $params = $this->_params();
        $url = 'http://183.178.45.166';
        $path = '/ta-sms/openapi/submittal';
        $body = [
            'username'  => $this->config['username'],
            'nonceStr'  => Random::alnum(16),
            'timestamp' => number_format(microtime(true) * 1000, 0, '.', ''),
            'signType'  => 'MD5',
            'content'   => $params['msg'],
            'phones'    => [
                ['phone' => $params['mobile'],]
            ]
        ];
        $body['sign'] = $this->sign($body, $this->config['apiKey']);
        $header = ['Content-Type: application/json', 'ta-version: v2'];
        $result = \fast\Http::sendRequest($url . $path, json_encode($body), 'POST', [CURLOPT_HTTPHEADER => $header]);
        if ($result['ret']) {
            $response = json_decode($result['msg'], true);
            if ($response && isset($response['code']) && $response['code'] == 200 && $response['message'] == 'SUCCESS') {
                return true;
            }
            trace('Sms001 error');
            trace($response);
            $this->error = isset($response['message']) ? $response['message'] : 'Invalid response format';
        } else {
            $this->error = $result['msg'];
        }
        return false;
    }

    private function _params()
    {
        return array_merge([], $this->_params);
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 接收手机
     * @param string $mobile 手机号码
     * @return  $this
     */
    public function mobile($mobile = '')
    {
        $this->_params['mobile'] = str_replace(['+820', '+'], ['82', ''], $mobile);
        return $this;
    }

    /**
     * 短信内容
     * @param string $msg 短信内容
     * @return  $this
     */
    public function msg($msg = '')
    {
        $this->_params['msg'] = $msg;
        return $this;
    }


    /**
     * 计算sign签名
     * @param $body
     * @param $apiKey
     * @return string
     */
    function sign($body, $apiKey)
    {
        ksort($body);
        $body['phones'] = str_replace(['"', ':'], ['', '='], json_encode($body['phones']));
        $body['key'] = $apiKey;
        $rs = urldecode(http_build_query($body));
        trace($rs);
        return md5($rs);
    }
}
