<?php

namespace app\pay\library;

use app\common\library\exception\BusinessException;
use app\pay\library\wechatpay_sdk\WechatAppPay;
use fast\Random;
use think\Exception;

class WechatPay extends BasePay
{

    protected $config;

    /** 微信只能同时存在一个, 所以暂时没有做配置 */
    public function __construct()
    {
        parent::__construct();
        $this->config = [
            'appid'        => 'wx1f0d805dcdc95ceb',
            'mch_id'       => '1615165904',
            'key'          => 'TtRs7JW84eqDU6azcN2Eb70dfPDKpjzv',
            'sub_appid'    => 'wx798e2a36b7dc2412',
            'sub_mch_id'   => '1653804091',
            'sslcert_path' => __DIR__ . '/cert/wx_shangxue/apiclient_cert.pem',
            'sslkey_path'  => __DIR__ . '/cert/wx_shangxue/apiclient_key.pem',
            // todo  下次接的时候使用配置的回调地址
            'notify_url'   => url('/pay/callback/wechat_payment', '', '', true)
        ];
        trace($this->config, 'm');
    }

    public function payment_sdk($order_no)
    {
        $config = $this->config;
        trace($config, 'm');
        $order = db('user_recharge')->where(['order_no' => $order_no])->find();
        //初始化配置
        $wechatAppPay = new WechatAppPay($config);
        //下单必要的参数
        $params['body'] = $this->description;    //商品描述
        $params['total_fee'] = (int)($order['pay_amount'] * 100); //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';             //交易类型 JSAPI | NATIVE | APP | WAP
        $params['out_trade_no'] = $order['order_no'];          // 订单号

        //统一下单
        $result = $wechatAppPay->unifiedOrder($params, true);
        if ($result['result_code'] == 'SUCCESS' && $result['return_msg'] == 'OK') {
            //创建APP端预支付参数
            return $wechatAppPay->getAppPayParams($result);
        }else {
            trace($result, 'm');
            throw new BusinessException($result['return_msg']);
        }
    }


    public function payment_h5($order_no)
    {
        $order = db('user_recharge')->where(['order_no' => $order_no])->find();

        if ($order) {
            $config = $this->config;
            $appid = $config['app_id'];                  //应用APPID
            $mch_id = $config['mch_id'];                  //微信支付商户号
            $key = $config['api_secret'];                 //微信商户API密钥
            $out_trade_no = $order['order_no'];//平台内部订单号
            $nonce_str = Random::alnum(32);//随机字符串
            $body = $this->description;  //内容
            $total_fee = $order['pay_amount'] * 100; //金额 分
            $spbill_create_ip = $this->request->ip(); //IP
            $notify_url = url('/index/site/notify_wn', '', '', true); //回调地址
            $trade_type = 'MWEB';//交易类型 具体看API 里面有详细介绍
            $scene_info = '{"h5_info":{"type":"Wap","wap_url":"http://wx.whtaole.com","wap_name":"支付"}}';//场景信息 必要参数
            $signA = "appid=$appid&attach=$out_trade_no&body=$body&mch_id=$mch_id&nonce_str=$nonce_str&notify_url=$notify_url&out_trade_no=$out_trade_no&scene_info=$scene_info&spbill_create_ip=$spbill_create_ip&total_fee=$total_fee&trade_type=$trade_type";
            //拼接字符串  注意顺序微信有个测试网址 顺序按照他的来 直接点下面的校正测试 包括下面XML  是否正确
            $strSignTmp = $signA . "&key=$key";
            $sign = strtoupper(MD5($strSignTmp));
            $post_data = "<xml>
                    <appid>$appid</appid>
                    <mch_id>$mch_id</mch_id>
                    <body>$body</body>
                    <out_trade_no>$out_trade_no</out_trade_no>
                    <total_fee>$total_fee</total_fee>
                    <spbill_create_ip>$spbill_create_ip</spbill_create_ip>
                    <notify_url>$notify_url</notify_url>
                    <trade_type>$trade_type</trade_type>
                    <scene_info>$scene_info</scene_info>
                    <attach>$out_trade_no</attach>
                    <nonce_str>$nonce_str</nonce_str>
                    <sign>$sign</sign>
            </xml>";//拼接成XML 格式
            $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";//微信传参地址
            $dataxml = $this->postXmlCurl($post_data, $url); //后台POST微信传参地址  同时取得微信返回的参数
            $objectxml = (array)simplexml_load_string($dataxml, 'SimpleXMLElement', LIBXML_NOCDATA); //将微信返回的XML 转换成数组

            return $objectxml['mweb_url'];
//            var_dump($objectxml); die;
            $this->assign('url', $objectxml['mweb_url'] ?? '');
            return $this->view->fetch('wn');
//            header("HTTP_REFERER: " . url('/', '', '', true), true);
//            header('Location: ' . $objectxml['mweb_url']);
        }
    }


    protected function postXmlCurl($xml, $url, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, false);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);

            return $data;
        }else {
            $error = curl_errno($ch);
            curl_close($ch);
            echo "curl出错，错误码:$error" . "<br>";
        }
    }


    public function get_handler()
    {
        return new WechatAppPay($this->config);
    }
}
