<?php

namespace addons\smsbao\library;

class NXclould
{
    private $_params = [];
    protected $error = '';
    protected $config = [
        'AccessKey'    => '4mL9qNF2',
        'AccessSecret' => '7A0wdb2u',
        'appKey'       => 'Ymbg5qSR',
        'secretKey'    => 'fpW9ehPB',
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
        $url = 'https://api.nxcloud.com/v1/sms/mt';
        $headers = array(
            "accessKey" => $this->config['AccessKey'],
            "bizType"   => "3",
            "action"    => "mtsend",
            "ts"        => time() * 1000,
            //"algorithm" => 'md5'
        );
        $postData = [
            'appKey'  => $this->config['appKey'],
            'phone'   => $params['mobile'],
            'content' => $params['msg'],
        ];
        $body = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $headers['sign'] = $this->calcSign($headers, $body, $this->config['AccessSecret']);
        //trace($headers);
        //trace($postData);
        //trace($body);

        $headerAll = array_merge(['Content-Type' => 'application/json'], $headers);
        $options = [];
        foreach ($headerAll as $k => $v) {
            $options[] = $k . ': ' . $v;
        }
        $result = \fast\Http::sendRequest($url, $body, 'POST', [CURLOPT_HTTPHEADER => $options]);

        if ($result['ret']) {
            $response = json_decode($result['msg'], true);
            if ($response && isset($response['code']) && $response['code'] === 0) {
                return true;
            }
            trace('Nxcloud Error: ');
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
        $this->_params['mobile'] = $mobile;
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
     *
     * @param headers      请求头中的公共参数
     * @param body         body中json字符串
     * @param accessSecret 秘钥
     * @return
     */
    function calcSign($headers, $body, $accessSecret)
    {
        // step1: 拼接header参数
        $str = "accessKey=" . $headers['accessKey'] . "&action=" . $headers['action']
            //. '&algorithm=' . $headers['algorithm']
            . "&bizType=" . $headers['bizType'] . "&ts=" . $headers['ts'];
        //echo "step1: " . $str . "\n"; // step1: accessKey=fme2na3kdi3ki&action=send&bizType=1&ts=1655710885431

        // step2: 拼接body参数
        if (!empty($body)) {
            $str = $str . "&body=" . $body;
        }
        //echo "step2: " . $str . "\n"; // step2: accessKey=fme2na3kdi3ki&action=send&bizType=1&ts=1655710885431&body={"id":10001,"name":"牛小信"}

        // step3: 拼接accessSecret
        $str = $str . "&accessSecret=" . $accessSecret;
        //echo "step3: " . $str . "\n"; // step3: accessKey=fme2na3kdi3ki&action=send&bizType=1&ts=1655710885431&body={"id":10001,"name":"牛小信"}&accessSecret=abciiiko2k3

        // step4: MD5算法加密,结果转换成十六进制小写
        $ret = md5($str);
        //echo "step4: sign=" . $ret . "\n"; // step4: sign=7750759da06333f20d0640be09355e34

        return $ret;
    }
}
