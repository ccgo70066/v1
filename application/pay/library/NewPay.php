<?php

namespace app\pay\library;

use app\common\library\ApiException;
use fast\Http;
use think\Cache;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;


/**
 * NewPay
 * @link  https://auth.hnapay.com/login
 * 登录名: 19526223348@163.com
 * 操作员id: admin
 * 电话: 15801133620
 * 登录密码: 123123aA？
 */
class NewPay extends BasePay
{
    private $mch_id;
    private $private_key_path;
    private $public_key_path;
    private $platform_public_key_path;    //新生公钥
    protected $app_id;
    private $notifyUrl;
    private $returnUrl;
    private $extend1;
    private $extend2;


    public function __construct($config)
    {
        parent::__construct();
        $cert_file_dir = $config['cert_dirname'];
        $this->notifyUrl = $config['notify_url'];
        $this->returnUrl = $config['return_url'];
        $this->mch_id = $config['mch_id'];
        $appIds = array_map(static function ($value) {
            return trim($value);
        }, explode(';', $config['app_id']));
        $this->app_id = $appIds[array_rand($appIds)];
        $this->private_key_path = __DIR__ . "/cert/$cert_file_dir/PrivateKey_10.pem";
        $this->public_key_path = __DIR__ . "/cert/$cert_file_dir/PublicKey_10.pem";
        $this->platform_public_key_path = __DIR__ . "/cert/$cert_file_dir/HnapayExpPublicKey.pem";    //新生公钥
        $this->extend1 = $config['extend1'];
        $this->extend2 = $config['extend2'];
    }

    /**
     * 生成下单参数后返回给h5, h5使用加签参数提交到新生支付调起支付宝支付.
     * @param $order_no
     * @return string|void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function h5_payment($order_no)
    {
        $order = db('user_recharge')->where(['order_no' => $order_no])->find();
        if (!$order) {
            return;
        }
        db('user_recharge')->where(['id' => $order['id']])->update(['extend' => $this->app_id]);
        $url = 'https://gateway.hnapay.com/multipay/h5.do';

        $order_info = [
            'tranAmt'              => $order['pay_amount'],
            'payType'              => 'HnaZFB',
            'exPayMode'            => '',
            'cardNo'               => '',
            'holderName'           => '',
            'identityCode'         => '',
            'merUserId'            => '',
            'orderExpireTime'      => '',
            'frontUrl'             => $this->returnUrl,
            'notifyUrl'            => $this->notifyUrl,
            'riskExpand'           => '',
            'goodsInfo'            => '',
            'orderSubject'         => 'goods',
            'orderDesc'            => '',
            'merchantId'           => '{"02":"' . $this->app_id . '"}',   //报备编号
            'bizProtocolNo'        => '',
            'payProtocolNo'        => '',
            'merUserIp'            => $order['ip'],
            'payLimit'             => '',
            'paymentTerminalInfo'  => '01|10001',
            'receiverTerminalInfo' => '01|00001|CN|110000',
            'deviceInfo'           => "192.168.0.1||||||",
        ];
        trace('订单');
        trace($order_info);
        $params = [
            'version'       => '2.0',
            'tranCode'      => 'MUP11',
            'merId'         => $this->mch_id,
            'merOrderId'    => $order['order_no'],
            'submitTime'    => date('YmdHis', strtotime($order['create_time'])),
            'signType'      => '1',
            'charset'       => '1',
            'msgCiphertext' => $this->openssl_public_encrypt(json_encode($order_info)),
        ];
        $signString = '';
        foreach ($params as $k => $v) {
            $signString .= "{$k}=[$v]";
        }
        $params['signValue'] = $this->rsa_sign($signString);
        return $url . '?' . http_build_query($params);
    }

    /**
     * @param string $data 签名串
     * @return string|void
     */
    public function rsa_sign(string $data)
    {
        $key = $this->private_key_path;
        if (file_exists($key)) {
            $private_key = openssl_pkey_get_private(file_get_contents($key));
            $free_flag = true;
        }else {
            $private_key = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($key, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        }
        if (!$private_key) {
            return;
        }
        openssl_sign($data, $signature, $private_key);
        isset($free_flag) && openssl_free_key($private_key);
        return base64_encode($signature);
    }

    public function openssl_public_encrypt(string $data)
    {
        $key = $this->platform_public_key_path;
        if (file_exists($key)) {
            $publicKey = openssl_pkey_get_public(file_get_contents($key));
            $free_flag = true;
        }else {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($key, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        }
        if (!$publicKey) {
            return;
        }
        $blockSize = openssl_pkey_get_details($publicKey)['bits'] / 8 - 11;
        $encryptedData = '';
        while ($data) {
            $chunk = substr($data, 0, $blockSize);
            $data = substr($data, $blockSize);
            openssl_public_encrypt($chunk, $encryptedChunk, $publicKey);
            $encryptedData .= $encryptedChunk;
        }
        isset($free_flag) && openssl_free_key($publicKey);
        return base64_encode($encryptedData);
    }

    public function check_sign(string $data, string $signature)
    {
        $data = md5($data);
        $signature = base64_decode($signature);
        $key = $this->platform_public_key_path;
        if (file_exists($key)) {
            $public_key = openssl_get_publickey(file_get_contents($key));
            $free_flag = true;
        }else {
            $public_key = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($key, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        }
        if (!$public_key) {
            return;
        }
        $result = openssl_verify($data, $signature, $public_key);
        isset($free_flag) && openssl_free_key($public_key);
        return $result;
    }


    /** 微信支付 */
    public function payment_wechatService($order_no, $openid)
    {
        $order = db('user_recharge')->where(['order_no' => $order_no])->find();
        if (!$order) {
            return;
        }
        db('user_recharge')->where(['id' => $order['id']])->update(['extend' => $this->app_id]);
        $url = 'https://gateway.hnapay.com/ita/inCharge.do';
        $params = [
            'version'       => '2.0',
            'tranCode'      => 'ITA10',
            'merId'         => $this->mch_id,
            'merOrderId'    => $order['order_no'],
            'submitTime'    => date('YmdHis', strtotime($order['create_time'])),
            'signType'      => '1',
            'charset'       => '1',
            'msgCiphertext' => $this->openssl_public_encrypt(json_encode([
                'tranAmt'         => $order['pay_amount'],
                'orgCode'         => 'WECHATPAY',
                'notifyServerUrl' => $this->notifyUrl,
                'merUserIp'       => $order['ip'],
                'expirTime'       => '',
                'riskExpand'      => '',
                'goodsInfo'       => 'goods',
                'orderSubject'    => 'goods',
                'orderDesc'       => '',
                'payLimit'        => '',
                'appId'           => 'wx4cd4b137ebd9a1bb',  // 商家微信 公众号 ID
                'openId'          => $openid,  // 微信用户 关注商家 公众号的 openid
                'aliAppId'        => '',
                'buyerLogonId'    => '',
                'buyerId'         => '',
                // 'merchantId'      => '2310252020243414958',
                // 'merchantId'      => '2310262021463415201',   // chris 2023-10-27 10:01:06
                // 'merchantId'      => '2310252019463414957',   // chris 2023-10-27 15:27:30
                // 'merchantId'      => '2310262022043415295',   // emma 2023-10-27 23:05:40
                // 'merchantId'      => '2310262022153415296',   // emma 2023-10-27 23:05:40
                'merchantId'      => $this->app_id,   // emma 2023-10-27 23:05:40
                'holderName'      => '',
                'identityType'    => '',
                'identityCode'    => '',
                'minAge'          => '',
            ])),
        ];
        $signString = '';
        foreach ($params as $k => $v) {
            !in_array($k, ['signType', 'charset']) && $signString .= "{$k}=[$v]";
        }
        $params['signValue'] = $this->rsa_sign($signString);
        $result = Http::post($url, $params);
        $result = json_decode($result, true);
        /*
array(13) {
["charset"] => string(1) "1"
["hnapayOrderId"] => string(16) "2023102649902326"
["resultCode"] => string(4) "0000"
["errorCode"] => string(0) ""
["version"] => string(3) "2.0"
["signValue"] => string(172) "phJSLvGigWS4iN82wSFrF0FqWM2/2yc1MJzVtTwGM8Tmz/uknyPZmsS1RyAEYYvFbmigEc85pBkDOm9YjdwHKORMsbsk5dGaoFjPG4UwrUegpYxx0zLsBDiwJjbUiLRs48UvxPqHo7cFYOVgnal/AHnjXtACoReMiYOK0PRkbqM="
["errorMsg"] => string(0) ""
["signType"] => string(1) "1"
["merId"] => string(11) "11000008058"
["tranCode"] => string(5) "ITA10"
["merAttach"] => string(0) ""
["payInfo"] => array(7) {
    ["timeStamp"] => string(10) "1698310090"
    ["package"] => string(46) "prepay_id=wx2616481074740392df4246b65024780000"
    ["paySign"] => string(344) "QYCgGPVEkUVRb9s5C3ivVb6UclwfjgB7+uZY1Z2K6UsL4Rk8o9GCPAMrPu4ZehU7gxg7noT7LH89u0FzWo6fYcqDAq3yQPUYpV7BKrF1imWSrtzYBFfHWOQGcMMjeDj0NFcLlkJn0px/3nQHhwLvLbREgxzk/3fVY3l5DZHTfHlPfmfaJMDQrsT+L0uCA1uytcAMY/MyJ56ctXJYOmRCurAMef7lPCcE1eHxosePVDqx1xuk8tTM2IcXm3l13TpgsjQMr8B+S77g1Qn7tso70qWLJRs/CrTp9QAb7oMfqxhM8cBziNWQpov3SLlphrkKjI8KTlSWZ8tJvfQxgu2U6A=="
    ["orderId"] => string(19) "2310261648105087189"
    ["appId"] => string(18) "wx4cd4b137ebd9a1bb"
    ["signType"] => string(3) "RSA"
    ["nonceStr"] => string(32) "36404f0807384a80b26668164d51acb2"
}
["merOrderId"] => string(18) "202310261648059753"
}
        */
        trace($result, 'm');
        if (!isset($result['payInfo'])) {
            throw new ApiException(__('Payment failure'));
        }
        return $result['payInfo'];
    }

    public function getScheme($order_no)
    {
        $token_key = 'newpay_wemini_access_token';
        $access_token = cache($token_key);
        if (!$access_token) {
            $url = 'https://api.weixin.qq.com/cgi-bin/token';
            $params = [
                'grant_type' => 'client_credential',
                'appid'      => 'wx4cd4b137ebd9a1bb',
                // 'secret'     => 'b475397a8a82aa7e1677546f4e5cfe1e',
                'secret'     => '794fd4debeabb2363ee47af1fccbab33',
            ];
            $rs = \fast\Http::get($url, $params);
            $rs = json_decode($rs, true);
            cache($token_key, $rs['access_token'], $rs['expires_in']);
            $access_token = $rs['access_token'];
        }
        $url1 = "https://api.weixin.qq.com/wxa/generatescheme?access_token={$access_token}";
        $order = db('user_recharge')->where('order_no', $order_no)->find();
        $params = [
            'jump_wxa' => [
                'path'  => '/pages/pay/login',
                'query' => http_build_query([
                    'order_no' => $order_no,
                    'login'    => Cache::has("newpay_wemini_openid:{$order['user_id']}") ? 0 : 1
                ]),
            ],
        ];
        $rs = \fast\Http::post($url1, json_encode($params), [CURLOPT_HTTPHEADER => ['Content-Type' => 'application/json']]);
        trace($rs, 'm');
        $rs = json_decode($rs, true);
        if ($rs['errcode'] != 0) {
            throw new ApiException($rs['errmsg'], $rs['errcode']);
        }
        return $rs['openlink'];
    }

}
