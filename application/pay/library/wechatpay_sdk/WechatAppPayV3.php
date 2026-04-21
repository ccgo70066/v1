<?php

//namespace App\Http\Controllers\Api\V1;
namespace app\pay\library\wechatpay_sdk;

//use Illuminate\Http\Request;
//use App\Http\Controllers\Controller;
use app\common\library\exception\BusinessException;
use Exception;

/**
 * v3 版本 服务商
 */
class WechatAppPayV3
{
    //接口API URL前缀
    const API_URL_PREFIX = 'https://api.mch.weixin.qq.com';
    //下单地址URL
    const UNIFIEDORDER_URL = "/v3/pay/partner/transactions/app";

    //查询订单URL
    const ORDERQUERY_URL = "/pay/orderquery";
    //关闭订单URL
    const CLOSEORDER_URL = "/pay/closeorder";
    const PROFITSHARING_URL = "/v3/profitsharing/orders";
    const PROFIT_ADD_RECEIVER_URL = '/v3/profitsharing/receivers/add';
    /** @var string 完结分账 */
    const PROFIT_FINISH_URL = '/secapi/pay/profitsharingfinish';

    //公众账号ID
    private $appid;
    //商户号
    private $mch_id;
    //随机字符串
    private $nonce_str;
    //签名
    private $sign;
    //商品描述
    private $description;
    //商户订单号
    private $out_trade_no;
    //支付总金额
    private $total_fee;
    //支付结果回调通知地址
    private $notify_url;
    //支付密钥
    private $key;
    //证书路径
    private $sslcert_path;
    private $sslkey_path;
    //所有参数
    private $params = array();

    //子商户应用ID
    private $sub_appid;
    //子商户号
    private $sub_mch_id;
    private $serial_no;
    private $p_sslcert_path;
    private $p_serial_no;


    public function __construct($options)
    {
        $this->notify_url = $options['notify_url'] ?? '';

        $this->appid = $options['appid'] ?? '';
        $this->mch_id = $options['mch_id'] ?? '';
        $this->key = $options['key'] ?? '';

        $this->sub_appid = $options['sub_appid'] ?? '';
        $this->sub_mch_id = $options['sub_mch_id'] ?? '';

        $this->sslcert_path = $options['sslcert_path'] ?? '';
        $this->sslkey_path = $options['sslkey_path'] ?? '';
        $this->serial_no = $options['serial_no'] ?? '';

        $this->p_sslcert_path = $options['p_sslcert_path'] ?? '';
        $this->p_serial_no = $options['p_serial_no'] ?? '';
    }

    /**
     * 下单方法
     * @param mixed $params              下单参数
     * @param bool  $profit_sharing_flag 是否分帐
     * @return array|false|mixed
     */
    public function unifiedOrder($params, bool $profit_sharing_flag = false)
    {
        $this->description = $params['description'];
        $this->out_trade_no = $params['out_trade_no'];
        $this->total_fee = $params['total_fee'];
        $this->nonce_str = $this->genRandomString();

        $this->params[ 'sp_appid' ] = $this->appid;
        $this->params[ 'sp_mchid' ] = $this->mch_id;
        $this->params[ 'sub_appid' ] = $this->sub_appid;
        $this->params[ 'sub_mchid' ] = $this->sub_mch_id;
        $this->params[ 'description' ] = $this->description;
        $this->params[ 'out_trade_no' ] = $this->out_trade_no;
        $this->params[ 'notify_url' ] = $this->notify_url;
        $this->params[ 'amount' ][ 'total' ] = $this->total_fee;
        $profit_sharing_flag && $this->params['settle_info']['profit_sharing'] = true;  // 分帐标识

        trace($this->params, 'm');
        $body = json_encode($this->params);
        $timestamp = time();
        $result = \fast\Http::sendRequest(self::API_URL_PREFIX . self::UNIFIEDORDER_URL, $body, 'post', [
            CURLOPT_HTTPHEADER => [
                $this->getAuthorization('POST', self::UNIFIEDORDER_URL, $timestamp, $this->nonce_str, $body),
                'Accept: application/json',
                'Content-Type: application/json',
            ]
        ]);
        $data = json_decode($result['msg'], true);
        if (!$result['ret'] || !$data || !isset($data['prepay_id'])) {
            throw new BusinessException($result['msg']);
        }
        return [
            'appid'     => $this->sub_appid,
            'partnerid' => $this->mch_id,
            'prepayid'  => $data['prepay_id'],
            'package'   => 'Sign=WXPay',
            'noncestr'  => $this->nonce_str,
            'timestamp' => $timestamp,
            'sign'      => $this->getPrepaySign($data['prepay_id'], $timestamp, $this->nonce_str)
        ];
    }

    /**
     * 分账
     * @link https://pay.weixin.qq.com/wiki/doc/apiv3_partner/apis/chapter8_1_1.shtml
     * @param $params
     * @return array
     * @throws BusinessException
     */
    public function profit_sharing($params)
    {
        $this->sslcert_path = $this->p_sslcert_path;
        $this->nonce_str = $this->genRandomString();
        $this->params = [
            'sub_mchid'        => $this->sub_mch_id,
            'appid'            => $this->appid,
            'sub_appid'        => $this->sub_appid,
            'transaction_id'   => $params['transaction_id'],
            'out_order_no'     => $params['out_order_no'],
            'receivers'        => $params['receivers'],
            'unfreeze_unsplit' => $params['unfreeze_unsplit'],
        ];
        trace($this->params, 'm');
        $body = json_encode($this->params);
        $timestamp = time();
        $authorization = $this->getAuthorization('POST', self::PROFITSHARING_URL, $timestamp, $this->nonce_str, $body);
        $result = \fast\Http::sendRequest(self::API_URL_PREFIX . self::PROFITSHARING_URL, $body, 'post', [
            CURLOPT_HTTPHEADER => [
                $authorization,
                'Accept: application/json',
                'Content-Type: application/json',
                'Wechatpay-Serial: ' . $this->p_serial_no,
            ]
        ]);
        trace($result, 'm');
        $data = json_decode($result['msg'], true);
        trace($data, 'm');
        return [1, ''];
    }

    /**
     * 添加分账接收方关系
     * @return void
     */
    public function receiversAdd()
    {
        $this->nonce_str = $this->genRandomString();
        $this->params = [
            'sub_mchid'     => $this->sub_mch_id,
            'appid'         => $this->appid,
            'sub_appid'     => $this->sub_appid,
            'type'          => 'MERCHANT_ID',
            'account'       => '1615165904',
            'name'          => $this->getEncrypt('深圳中星创展资产管理有限公司'),
            'description'   => 'sharing',
            'relation_type' => 'SERVICE_PROVIDER',

        ];
        $body = json_encode($this->params);
        $timestamp = time();
        $authorization = $this->getAuthorization('POST', self::PROFIT_ADD_RECEIVER_URL, $timestamp, $this->nonce_str, $body);
        $result = \fast\Http::sendRequest(self::API_URL_PREFIX . self::PROFIT_ADD_RECEIVER_URL, $body, 'post', [
            CURLOPT_HTTPHEADER => [
                $authorization,
                'Accept: application/json',
                'Content-Type: application/json',
                'Wechatpay-Serial: ' . $this->p_serial_no,
            ]
        ]);
        $data = json_decode($result['msg'], true);
        // dump($data);
    }

    /**
     * 加解密敏感信息
     * @param $str
     * @return string
     */
    public function getEncrypt($str)
    {
        $public_key = file_get_contents($this->p_sslcert_path);
        $encrypted = '';
        if (openssl_public_encrypt($str, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            //base64编码
            $sign = base64_encode($encrypted);
        }else {
            throw new Exception('encrypt failed');
        }
        return $sign;
    }

    /**
     * app预支付信息签名
     * @param $prepay_id
     * @param $timestamp
     * @param $nonce
     * @return string
     */
    private function getPrepaySign($prepay_id, $timestamp, $nonce)
    {
        $message = $this->sub_appid . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $prepay_id . "\n";
        $mch_private_key = file_get_contents($this->sslkey_path);
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        return base64_encode($raw_sign);
    }


    /**
     * 产生一个指定长度的随机字符串,并返回给用户
     * @param int $len 产生字符串的长度
     * @return string 随机字符串
     */
    private function genRandomString($len = 16)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
    }

    /**
     * 接口请求签名
     * @param $http_method
     * @param $url
     * @param $timestamp
     * @param $nonce
     * @param $body
     * @return string
     */
    private function getAuthorization($http_method, $url, $timestamp, $nonce, $body)
    {
        $url_parts = parse_url($url);
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
        $message = strtoupper($http_method) . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";

        $mch_private_key = file_get_contents($this->sslkey_path);
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);

        $schema = 'WECHATPAY2-SHA256-RSA2048';
        $merchant_id = $this->mch_id;
        $serial_no = $this->serial_no;
        $token = sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $merchant_id, $nonce, $timestamp, $serial_no, $sign);

        return "Authorization: $schema $token";
    }

}
