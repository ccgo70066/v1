<?php

//namespace App\Http\Controllers\Api\V1;
namespace app\pay\library\wechatpay_sdk;

//use Illuminate\Http\Request;
//use App\Http\Controllers\Controller;
use Psr\Log\Test\DummyTest;

/**
 * v2 版本
 * @link https://developers.weixin.qq.com/community/pay/doc/00008274290738d3d9ec1442c56c00?jumpto=comment&commentid=000626277988f854d3eca2333560
 */
class WechatAppPay
{
    //接口API URL前缀
    const API_URL_PREFIX = 'https://api.mch.weixin.qq.com';
    //下单地址URL
    const UNIFIEDORDER_URL = "/pay/unifiedorder";
    //查询订单URL
    const ORDERQUERY_URL = "/pay/orderquery";
    //关闭订单URL
    const CLOSEORDER_URL = "/pay/closeorder";
    // 单次分帐
    const PROFITSHARING_URL = "/secapi/pay/profitsharing";
    /** @var string 添加分帐接收方 */
    const PROFIT_ADD_RECEIVER_URL = '/pay/profitsharingaddreceiver';
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
    private $body;
    //商户订单号
    private $out_trade_no;
    //支付总金额
    private $total_fee;
    //终端IP
    private $spbill_create_ip;
    //支付结果回调通知地址
    private $notify_url;
    //交易类型
    private $trade_type;
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


    public function __construct($options)
    {
        $this->appid = isset($options['appid']) ? $options['appid'] : '';
        $this->mch_id = isset($options['mch_id']) ? $options['mch_id'] : '';
        $this->notify_url = isset($options['notify_url']) ? $options['notify_url'] : '';
        $this->key = isset($options['key']) ? $options['key'] : '';

        $this->sub_appid = isset($options['sub_appid']) ? $options['sub_appid'] : '';
        $this->sub_mch_id = isset($options['sub_mch_id']) ? $options['sub_mch_id'] : '';

        $this->sslcert_path = isset($options['sslcert_path']) ? $options['sslcert_path'] : '';
        $this->sslkey_path = isset($options['sslkey_path']) ? $options['sslkey_path'] : '';
    }

    /**
     * 下单方法
     *
     * @param mixed $params              下单参数
     * @param bool  $profit_sharing_flag 是否分帐
     * @return array|false|mixed
     */
    public function unifiedOrder($params, $profit_sharing_flag = false)
    {
        $this->body = $params['body'];
        $this->out_trade_no = $params['out_trade_no'];
        $this->total_fee = $params['total_fee'];
        $this->trade_type = $params['trade_type'];
        $this->nonce_str = $this->genRandomString();
        $this->spbill_create_ip = $_SERVER['REMOTE_ADDR'];

        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        if ($this->sub_appid) {
            $this->params['sub_appid'] = $this->sub_appid;
            $this->params['sub_mch_id'] = $this->sub_mch_id;
        }
        $this->params['nonce_str'] = $this->nonce_str;
        $this->params['body'] = $this->body;
        $this->params['out_trade_no'] = $this->out_trade_no;
        $this->params['total_fee'] = $this->total_fee;
        $this->params['spbill_create_ip'] = $this->spbill_create_ip;
        $this->params['notify_url'] = $this->notify_url;
        $this->params['trade_type'] = $this->trade_type;
        $profit_sharing_flag && $this->params['profit_sharing'] = 'Y';  // 分帐标识

        //获取签名数据
        $this->sign = $this->MakeSign($this->params);
        $this->params['sign'] = $this->sign;
        $xml = $this->data_to_xml($this->params);
        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::UNIFIEDORDER_URL);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);
        if (!empty($result['result_code']) && !empty($result['err_code'])) {
            $result['err_msg'] = $this->error_code($result['err_code']);
        }

        return $result;
    }

    /**
     * 查询订单信息
     *
     * @param $out_trade_no       订单号
     *
     * @return array
     */
    public function orderQuery($out_trade_no)
    {
        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        $this->params['nonce_str'] = $this->genRandomString();
        $this->params['out_trade_no'] = $out_trade_no;

        //获取签名数据
        $this->sign = $this->MakeSign($this->params);
        $this->params['sign'] = $this->sign;
        $xml = $this->data_to_xml($this->params);
        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::ORDERQUERY_URL);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);
        if (!empty($result['result_code']) && !empty($result['err_code'])) {
            $result['err_msg'] = $this->error_code($result['err_code']);
        }

        return $result;
    }

    /**
     * 关闭订单
     *
     * @param $out_trade_no       订单号
     *
     * @return array
     */
    public function closeOrder($out_trade_no)
    {
        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        $this->params['nonce_str'] = $this->genRandomString();
        $this->params['out_trade_no'] = $out_trade_no;

        //获取签名数据
        $this->sign = $this->MakeSign($this->params);
        $this->params['sign'] = $this->sign;
        $xml = $this->data_to_xml($this->params);
        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::CLOSEORDER_URL);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);

        return $result;
    }

    /** 添加分帐接收方 */
    public function profit_add_receiver($params)
    {
        $this->nonce_str = $this->genRandomString();
        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        if ($this->sub_appid) {
            $this->params['sub_appid'] = $this->sub_appid;
            $this->params['sub_mch_id'] = $this->sub_mch_id;
        }
        $this->params['nonce_str'] = $this->nonce_str;
        $this->params['sign_type'] = 'HMAC-SHA256';
        $this->params['receiver'] = $params['receiver'];

        $this->sign = $this->MakeSign($this->params, 'HMAC-SHA256');
        $this->params['sign'] = $this->sign;
        $xml = $this->data_to_xml($this->params);

        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::PROFIT_ADD_RECEIVER_URL);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);
        if ('SUCCESS' == $result['return_code'] && 'SUCCESS' == $result['result_code']) {
            return [1, ''];
        }else {
            return [0, $result['err_code_des']];
        }
    }

    /** 完结分帐 */
    public function profit_finish($params)
    {
        $this->nonce_str = $this->genRandomString();
        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        if ($this->sub_appid) {
            // $this->params['sub_appid'] = $this->sub_appid;
            $this->params['sub_mch_id'] = $this->sub_mch_id;
        }
        $this->params['nonce_str'] = $this->nonce_str;
        $this->params['sign_type'] = 'HMAC-SHA256';
        $this->params['out_order_no'] = $params['out_order_no'];
        $this->params['transaction_id'] = $params['transaction_id'];
        $this->params['description'] = $params['description'];

        $this->sign = $this->MakeSign($this->params, 'HMAC-SHA256');
        $this->params['sign'] = $this->sign;
        $xml = $this->data_to_xml($this->params);

        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::PROFIT_FINISH_URL, true);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);
        if ('SUCCESS' == $result['return_code'] && 'SUCCESS' == $result['result_code']) {
            return [1, $result['order_id']];
        }else {
            return [0, $result['err_code_des']];
        }
    }

    /**
     * 单次分帐
     *
     * @param array $params 参数
     */
    public function profit_sharing($params)
    {
        $this->nonce_str = $this->genRandomString();
        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        if ($this->sub_appid) {
            $this->params['sub_appid'] = $this->sub_appid;
            $this->params['sub_mch_id'] = $this->sub_mch_id;
        }
        $this->params['nonce_str'] = $this->nonce_str;

        $this->params['sign_type'] = 'HMAC-SHA256';
        $this->params['out_order_no'] = $params['out_order_no'];
        $this->params['transaction_id'] = $params['transaction_id'];
        $this->params['receivers'] = json_encode($params['receivers']);

        //获取签名数据
        $this->sign = $this->MakeSign($this->params, 'HMAC-SHA256');
        $this->params['sign'] = $this->sign;
        $xml = $this->data_to_xml($this->params);

        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::PROFITSHARING_URL, true);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);
        if ('SUCCESS' == $result['return_code'] && 'SUCCESS' == $result['result_code']) {
            trace('分帐成功日志:', 'sql');
            trace($result, 'sql');
            return [1, ''];
        }else {
            trace('分帐日志:', 'sql');
            trace($result, 'sql');
            return [0, $result['err_code_des']];
        }
    }


    /**
     * 获取支付结果通知数据
     * return array
     */
    public function getNotifyData()
    {
        //获取通知的数据
        //		$xml  = $GLOBALS['HTTP_RAW_POST_DATA'];
        //		if(!$xml){
        $xml = file_get_contents('php://input');
        //		}
        $data = array();
        if (empty($xml)) {
            return false;
        }
        $data = $this->xml_to_data($xml);
        if (!empty($data['return_code'])) {
            if ($data['return_code'] == 'FAIL') {
                return false;
            }
        }

        return $data;
    }

    /**
     * 接收通知成功后应答输出XML数据
     *
     * @param string $xml
     */
    public function replyNotify()
    {
        $data['return_code'] = 'SUCCESS';
        $data['return_msg'] = 'OK';
        $xml = $this->data_to_xml($data);
        echo $xml;
        die();
    }

    /**
     * 生成APP端支付参数
     *
     * @param  $prepayid  预支付id
     */
    public function getAppPayParams($prepayid)
    {
        $data['appid'] = $this->appid;
        $this->sub_appid && $data['appid'] = $this->sub_appid;
        $data['partnerid'] = $this->mch_id;
        $data['prepayid'] = $prepayid['prepay_id'];
        $data['package'] = 'Sign=WXPay';
        $data['noncestr'] = $prepayid['nonce_str'];
        $data['timestamp'] = time();
        $data['sign'] = $this->MakeSign($data);

        return $data;
    }

    /**
     * 生成签名
     * @return 签名
     */
    public function MakeSign($params, $signType = 'MD5')
    {
        //签名步骤一：按字典序排序数组参数
        ksort($params);
        $string = $this->ToUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = trim($string . "&key=" . $this->key);
        //var_dump($string);die;
        //签名步骤三：MD5加密
        if ($signType == 'MD5') {
            $string = md5($string);
        }else {
            $string = hash_hmac('sha256', $string, $this->key);
        }
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }

    /**
     * 将参数拼接为url: key=value&key=value
     *
     * @param  $params
     *
     * @return string
     */
    public function ToUrlParams($params)
    {
        $string = '';
        if (!empty($params)) {
            $array = array();
            foreach ($params as $key => $value) {
                $array[] = $key . '=' . $value;
            }
            $string = implode("&", $array);
        }

        return $string;
    }

    /**
     * 输出xml字符
     *
     * @param  $params       参数名称
     *                       return  string    返回组装的xml
     **/
    public function data_to_xml($params)
    {
        if (!is_array($params) || count($params) <= 0) {
            return false;
        }
        $xml = "<xml>";
        foreach ($params as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";

        return $xml;
    }


    /**
     * 将xml转为array
     *
     * @param string $xml
     * return array
     */
    public function xml_to_data($xml)
    {
        if (!$xml) {
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        return $data;
    }


    /**
     * 获取毫秒级别的时间戳
     */
    private static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode(" ", microtime());
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode(".", $time);
        $time = $time2[0];

        return $time;
    }

    /**
     * 产生一个指定长度的随机字符串,并返回给用户
     *
     * @param type $len 产生字符串的长度
     *
     * @return string 随机字符串
     */
    private function genRandomString($len = 16)
    {
        $chars = array(
            "a",
            "b",
            "c",
            "d",
            "e",
            "f",
            "g",
            "h",
            "i",
            "j",
            "k",
            "l",
            "m",
            "n",
            "o",
            "p",
            "q",
            "r",
            "s",
            "t",
            "u",
            "v",
            "w",
            "x",
            "y",
            "z",
            "A",
            "B",
            "C",
            "D",
            "E",
            "F",
            "G",
            "H",
            "I",
            "J",
            "K",
            "L",
            "M",
            "N",
            "O",
            "P",
            "Q",
            "R",
            "S",
            "T",
            "U",
            "V",
            "W",
            "X",
            "Y",
            "Z",
            "0",
            "1",
            "2",
            "3",
            "4",
            "5",
            "6",
            "7",
            "8",
            "9",
        );
        $charsLen = count($chars) - 1;
        // 将数组打乱
        shuffle($chars);
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }

        return $output;
    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml     需要post的xml数据
     * @param string $url     url
     * @param bool   $useCert 是否需要证书，默认不需要
     * @param int    $second  url执行超时时间，默认30s
     *
     * @throws WxPayException
     */
    private function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, false);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $this->sslcert_path);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $this->sslkey_path);
        }
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

            return false;
        }
    }

    /**
     * 错误代码
     *
     * @param  $code     服务器输出的错误代码
     *                   return string
     */
    public function error_code($code)
    {
        $errList = array(
            'NOAUTH'                => '商户未开通此接口权限',
            'NOTENOUGH'             => '用户帐号余额不足',
            'ORDERNOTEXIST'         => '订单号不存在',
            'ORDERPAID'             => '商户订单已支付，无需重复操作',
            'ORDERCLOSED'           => '当前订单已关闭，无法支付',
            'SYSTEMERROR'           => '系统错误!系统超时',
            'APPID_NOT_EXIST'       => '参数中缺少APPID',
            'MCHID_NOT_EXIST'       => '参数中缺少MCHID',
            'APPID_MCHID_NOT_MATCH' => 'appid和mch_id不匹配',
            'LACK_PARAMS'           => '缺少必要的请求参数',
            'OUT_TRADE_NO_USED'     => '同一笔交易不能多次提交',
            'SIGNERROR'             => '参数签名结果不正确',
            'XML_FORMAT_ERROR'      => 'XML格式错误',
            'REQUIRE_POST_METHOD'   => '未使用post传递参数 ',
            'POST_DATA_EMPTY'       => 'post数据不能为空',
            'NOT_UTF8'              => '未使用指定编码格式',
        );
        if (array_key_exists($code, $errList)) {
            return $errList[$code];
        }
    }
}

?>
