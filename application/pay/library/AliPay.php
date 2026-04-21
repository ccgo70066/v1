<?php

namespace app\pay\library;

use AlipayConfig;
use AlipayFundTransUniTransferRequest;
use AlipayTradeAppPayRequest;
use AopCertClient;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Env;
use think\exception\DbException;
use think\Log;

class AliPay extends BasePay
{
    protected $app_id;
    private $app_private_key;
    private $app_public_key;
    private $alipay_public_key;
    private $alipay_root_key;
    private $notifyUrl;
    private $returnUrl;

    public function __construct($config)
    {
        parent::__construct();
        $app_id = $config['app_id'];
        $cert_file_dir = $config['cert_dirname'];
        $this->returnUrl = $config['return_url'];
        $this->app_id = $app_id;
        $this->app_private_key = __DIR__ . "/cert/$cert_file_dir/app_private.txt";
        $this->app_public_key = __DIR__ . "/cert/$cert_file_dir/appCertPublicKey.crt";
        $this->alipay_public_key = __DIR__ . "/cert/$cert_file_dir/alipayCertPublicKey_RSA2.crt";
        $this->alipay_root_key = __DIR__ . "/cert/$cert_file_dir/alipayRootCert.crt";
        $this->notifyUrl = $config['notify_url'];
    }

    /**
     * 支付宝单笔转账
     * @param string $order_no  订单号
     * @param string $login_id  收款人支付宝登录账号
     * @param string $real_name 收款人姓名
     * @param string $amount    金额
     * @param string $title     转帐业务标题
     * @return array
     * @throws \Exception
     */
    public function payout($order_no, $login_id, $real_name, $amount, $title)
    {
        require_once 'alipay_sdk/aop/AlipayConfig.php';
        require_once 'alipay_sdk/aop/AopCertClient.php';
        require_once 'alipay_sdk/aop/request/AlipayFundTransUniTransferRequest.php';
        $alipayConfig = new AlipayConfig();
        Log::error(__LINE__);
        Log::error($this->alipay_public_key);
        $alipayConfig->setPrivateKey(file_get_contents($this->app_private_key));
        $alipayConfig->setServerUrl("https://openapi.alipay.com/gateway.do");
        $alipayConfig->setAppId($this->app_id);
        $alipayConfig->setCharset("utf-8");
        $alipayConfig->setSignType("RSA2");
        $alipayConfig->setEncryptKey("");
        $alipayConfig->setFormat("json");
        $alipayConfig->setAppCertPath($this->app_public_key);
        $alipayConfig->setAlipayPublicCertPath($this->alipay_public_key);
        $alipayConfig->setRootCertPath($this->alipay_root_key);
        $alipayClient = new AopCertClient($alipayConfig);
        $alipayClient->isCheckAlipayPublicCert = true;
        $request = new AlipayFundTransUniTransferRequest();
        $request->setBizContent(json_encode([
            'out_biz_no'   => $order_no,
            'trans_amount' => $amount,
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene'    => 'DIRECT_TRANSFER',
            'order_title'  => $title,
            'payee_info'   => [
                'identity'      => $login_id,  // 支付宝登录号
                'identity_type' => 'ALIPAY_LOGON_ID',
                'name'          => $real_name,
            ]
        ]));
        $responseResult = $alipayClient->execute($request);
        $responseApiName = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $response = $responseResult->$responseApiName;
        $response = (array)$response;
        if (!empty($response['code']) && $response['code'] == 10000) {
            return [1, $response['order_id']];
            // ["code"] => string(5) "10000"
            // ["msg"] => string(7) "Success"
            // ["order_id"] => string(32) "20220728020070011500690023805134"
            // ["out_biz_no"] => string(14) "20220728150929"
            // ["pay_fund_order_id"] => string(32) "20220728020070011500690023805134"
            // ["status"] => string(7) "SUCCESS"
            // ["trans_date"] => string(19) "2022-07-28 15:09:32"
        }else {
            return [0, $response['sub_msg']];
            // ["code"] => string(5) "40004"
            // ["msg"] => string(15) "Business Failed"
            // ["sub_code"] => string(15) "PAYEE_NOT_EXIST"
            // ["sub_msg"] => string(78) "收款账号不存在或户名有误，建议核实账号和户名是否准确"
        }
    }


    /**
     * 支付宝sdk原生支付 证书方式
     * @param $order_no
     * @return string
     * @throws
     */
    public function sdk_payment($order_no)
    {
        require_once 'alipay_sdk/aop/AopCertClient.php';
        require_once 'alipay_sdk/aop/request/AlipayTradeAppPayRequest.php';
        $aop = new AopCertClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $this->app_id;
        $aop->rsaPrivateKey = file_get_contents($this->app_private_key);
        $aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipay_public_key);
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        $aop->appCertSN = $aop->getCertSN($this->app_public_key);
        // $aop->alipayRootCertSN = $aop->getRootCertSN($this->alipay_root_key);
        $aop->alipayRootCertSN = "687b59193f3f462dd5336e5abf83c5d8_02941eef3187dddf3d3b83462e1dfcf6";

        $order = db('user_recharge')->where(['order_no' => $order_no])->find();

        $request = new AlipayTradeAppPayRequest();
        $request->setReturnUrl($this->returnUrl);
        $request->setNotifyUrl($this->notifyUrl);
        $request->setBizContent(json_encode([
            'timeout_express'      => '30m',
            'product_code'         => 'QUICK_MSECURITY_PAY',
            'total_amount'         => sprintf('%.2f', $order['pay_amount']), //保留两位小数
            'subject'              => get_site_config('pay_body_description'),
            'body'                 => get_site_config('pay_body_description'),
            'out_trade_no'         => $order['order_no'], //此订单号为商户唯一订单号
            'disable_pay_channels' => 'creditCard,pcredit',  // https://opendocs.alipay.com/support/01rfz3
        ]));
        return $aop->sdkExecute($request);
    }


    /**
     * 支付宝h5原生支付 证书方式
     * @param $order
     * @return string
     */
    public function h5_payment($order_no)
    {
        $order = db('user_recharge')->where(['order_no' => $order_no])->find();
        if (!$order) {
            return;
        }

        require_once 'alipay_sdk/aop/AopCertClient.php';
        require_once 'alipay_sdk/aop/request/AlipayTradeWapPayRequest.php';
        $aop = new AopCertClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $this->app_id;
        $aop->rsaPrivateKey = file_get_contents($this->app_private_key);

        $aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipay_public_key);

        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        $aop->appCertSN = $aop->getCertSN($this->app_public_key);
        Log::error($this->alipay_root_key);
        // $aop->alipayRootCertSN = $aop->getRootCertSN($this->alipay_root_key);
        $aop->alipayRootCertSN = "687b59193f3f462dd5336e5abf83c5d8_02941eef3187dddf3d3b83462e1dfcf6";

        Log::error($aop->alipayRootCertSN);
        $request = new \AlipayTradeWapPayRequest();
        $request->setReturnUrl($this->returnUrl);
        $request->setNotifyUrl($this->notifyUrl);
        $request->setBizContent(json_encode([
            'timeout_express'      => '30m',
            'out_trade_no'         => $order['order_no'], //此订单号为商户唯一订单号
            'total_amount'         => sprintf('%.2f', $order['pay_amount']), //保留两位小数
            'subject'              => get_site_config('pay_body_description'),
            'disable_pay_channels' => 'creditCard,pcredit',  // https://opendocs.alipay.com/support/01rfz3
        ]));
        return $aop->pageExecute($request, 'get');
    }
}
