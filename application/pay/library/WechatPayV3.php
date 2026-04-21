<?php

namespace app\pay\library;

use app\common\library\exception\BusinessException;
use app\pay\library\wechatpay_sdk\WechatAppPay;
use app\pay\library\wechatpay_sdk\WechatAppPayV3;
use fast\Random;
use think\Exception;

/**
 * 微信支付 v3
 * 商户证书由商户/服务商提供
 * 平台证书使用wechatpay-php-main中的CertificateDownloader.php来下载,使用说明见readme.md
 */
class WechatPayV3 extends BasePay
{

    protected $config;

    /** 微信只能同时存在一个, 所以暂时没有做配置 */
    public function __construct()
    {
        parent::__construct();
        $this->config = [
            'appid'          => 'wx1f0d805dcdc95ceb',
            'mch_id'         => '1615165904',
            'key'            => 'm7Zf79El8XOMPVAj8vLD41DA3bBabf62',  // v3密钥
            'sub_appid'      => 'wx798e2a36b7dc2412',
            'sub_mch_id'     => '1653804091',
            'serial_no'      => '7FAF6F4E90060F9D9263328316E2035218274AAF',
            'sslcert_path'   => __DIR__ . '/cert/wx_shangxue/apiclient_cert.pem',
            'sslkey_path'    => __DIR__ . '/cert/wx_shangxue/apiclient_key.pem',
            'p_sslcert_path' => __DIR__ . '/cert/wx_shangxue/wechat_cert.pem',
            'p_serial_no'    => '3FD60318B8C65FB514B3E460960932BC7DFF201E',
            // todo  下次接的时候使用配置的回调地址
            'notify_url'     => url('/pay/callback/wechatv3_payment', '', '', true)
        ];
        trace($this->config, 'm');
    }

    public function payment_sdk($order_no)
    {
        $config = $this->config;
        trace($config, 'm');
        $order = db('user_recharge')->where(['order_no' => $order_no])->find();
        //初始化配置
        $wechatAppPay = new WechatAppPayV3($config);
        //下单必要的参数
        $params['description'] = $this->description;    //商品描述
        $params['total_fee'] = (int)($order['pay_amount'] * 100); //订单金额 只能为整数 单位为分
        $params['out_trade_no'] = $order['order_no'];          // 订单号

        //统一下单
        $result = $wechatAppPay->unifiedOrder($params, true);
        return $result;
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
        return new WechatAppPayV3($this->config);
    }


    // ==============================解密前===================================
    // $callData = array(
    //     'id'            => 'c5ca54d1-b6b1-5c9d-891b-5a243f1bae72',
    //     'create_time'   => '2023-10-20T18:01:10+08:00',
    //     'resource_type' => 'encrypt-resource',
    //     'event_type'    => 'TRANSACTION.SUCCESS',
    //     'summary'       => '支付成功',
    //     'resource'      =>
    //         array(
    //             'original_type'   => 'transaction',
    //             'algorithm'       => 'AEAD_AES_256_GCM',
    //             'ciphertext'      => '5p+7ozI2S/dZnG5rJ5MEgjT3/jBot9AXZvxHcgkyOGW7ouwYRfzyNrc1izPnbX75tutiqNjz+efEQF/tDAYDhfrcmoZ1sKYZQOy7tsjZMJXjetwsEmIIJMWr7la67irVgjABjEwk5lhhzvAou38eeNczzn6swi0mho+vlOyiUoMdftyYS+czyScErEap2mELUXgvN0/ysDIzHFMOAOxT5x8ASdXpvXZ9TT0mmWV7QWx57xGXAmpjXe/gf8EyGCKn8G8Z1AOpq4Nt71S49fABzBJQtAzJcF3w8EjgMZJZoUlV5HfaFSjWi9usKqlgK1LuDTzvbXietQH9V2GAef8hvRSBNFTFCgFDVF7z0wcw7wDhR3ICB0HSdSxiIRyvraAkPKsTUh16ujvv039nGzOGHw1E1FQ3Arkc2Fz5buLPxVXYLVD0E1wf+Xe2+7CkoYjoU/TPpFA+h0MmVoJpPLH6RwNJLqpetFDVSNXtdzRLe+4K8wHhhMqNtJz7rCwO+dDjWywRXJKyWSMjeg1khUs5oiBwVjLgbPXukKAV6ZPLY5Ikg+o/szp7W/PSf4q7VyZcF7mDKXpKH8Z31ZAamfs1QJeQlbgAiW96q6kApWuqW9XiD7pn/b92BqqY+BIt7Bg6OXl0S3xf+xlnyHGVQUkIPdnKrpYG0xUU7Kuh2zjvSPLJuX4P52HQaiP5nVkyQJs3PZXHw7v8K2E2j/0PNWA=',
    //             'associated_data' => 'transaction',
    //             'nonce'           => 'TCESFEnIaEIW',
    //         ),
    // );
    // ==============================解密后===================================
    // array(14) {
// ["sp_mchid"] => string(10) "1615165904"
// ["sub_mchid"] => string(10) "1653804091"
// ["sp_appid"] => string(18) "wx1f0d805dcdc95ceb"
// ["sub_appid"] => string(18) "wx798e2a36b7dc2412"
// ["out_trade_no"] => string(18) "202310201801041275"
// ["transaction_id"] => string(28) "4200002047202310208007022446"
// ["trade_type"] => string(3) "APP"
// ["trade_state"] => string(7) "SUCCESS"
// ["trade_state_desc"] => string(12) "支付成功"
// ["bank_type"] => string(6) "OTHERS"
// ["attach"] => string(0) ""
// ["success_time"] => string(25) "2023-10-20T18:01:10+08:00"
// ["payer"] => array(2) {
// ["sp_openid"] => string(28) "oiN4J60qJ9EfpPFS-k7rz1kArSVw"
// ["sub_openid"] => string(28) "oti2F6j6luTCu41ZPW0sWyuPGvEs"
// }
// ["amount"] => array(4) {
//     ["total"] => int(1)
//     ["payer_total"] => int(1)
//     ["currency"] => string(3) "CNY"
//     ["payer_currency"] => string(3) "CNY"
//   }
// }
    /**
     * 回调解密
     * @param $resource
     * @return mixed
     */
    public function decrypted($resource)
    {
        $nonceStr = $resource['nonce'];
        $associatedData = $resource['associated_data'];
        $ciphertext = base64_decode($resource['ciphertext']);
        $key = $this->config['key'];
        // $str= sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $key);
        $ctext = substr($ciphertext, 0, -16);
        $authTag = substr($ciphertext, -16);
        $str = openssl_decrypt($ctext, 'aes-256-gcm', $key, \OPENSSL_RAW_DATA, $nonceStr, $authTag, $associatedData);
        return json_decode($str, true);
    }
}
