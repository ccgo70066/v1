<?php

namespace util;

use think\Cache;

/**
 * @link  http://api.fanyi.baidu.com/product/113
 */
class Translate
{

    const CURL_TIMEOUT = 10;
    const URL = 'http://api.fanyi.baidu.com/api/trans/vip/translate';
    const APP_ID = '20220607001240632';
    const SEC_KEY = 'owRWNOQmVBnghvd5eLkO';

    /**
     * @param string $query 需要翻譯內容
     * @param string $to    翻译目标语言:auto=自動檢測,zh=中文,cht=繁體中文,en=英語
     * @return string
     */
    public static function trans($query, $to)
    {
        $key = 'translate:to_' . $to . ':' . $query;
        $result = Cache::remember($key, function () use ($query, $to, $key) {
            Cache::tag('small_data_translate', $key);
            return self::trans_do($query, $to);
        });
        $result == null && cache($key, null);
        return $result;
    }

    /**
     * @param string $query 需要翻譯內容
     * @param string $to    翻译目标语言
     * @param string $form  源语言
     * @return string
     */
    public static function trans_do($query, $to, $form = 'auto')
    {
        $args = array(
            'q'     => $query,
            'appid' => self::APP_ID,
            'salt'  => random_int(10000, 99999),
            'from'  => $form,
            'to'    => $to,
        );
        $args['sign'] = self::buildSign($query, self::APP_ID, $args['salt'], self::SEC_KEY);
        $ret = self::call(self::URL, $args);
        $ret = json_decode($ret, true);
        return $ret['trans_result'][0]['dst'];
    }


    //加密
    private static function buildSign($query, $appID, $salt, $secKey)
    {
        $str = $appID . $query . $salt . $secKey;
        $ret = md5($str);
        return $ret;
    }

    //发起网络请求
    private static function call(
        $url,
        $args = null,
        $method = "post",
        $testflag = 0,
        $timeout = self::CURL_TIMEOUT,
        $headers = array()
    ) {
        $ret = false;
        $i = 0;
        while ($ret === false) {
            if ($i > 1) {
                break;
            }
            if ($i > 0) {
                sleep(1);
            }
            $ret = self::callOnce($url, $args, $method, false, $timeout, $headers);
            $i++;
        }
        return $ret;
    }

    private static function callOnce(
        $url,
        $args = null,
        $method = "post",
        $withCookie = false,
        $timeout = self::CURL_TIMEOUT,
        $headers = array()
    ) {
        $ch = curl_init();
        if ($method == "post") {
            $data = self::convert($args);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_POST, 1);
        }else {
            $data = self::convert($args);
            if ($data) {
                if (stripos($url, "?") > 0) {
                    $url .= "&$data";
                }else {
                    $url .= "?$data";
                }
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($withCookie) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $_COOKIE);
        }
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }

    private static function convert(&$args)
    {
        $data = '';
        if (is_array($args)) {
            foreach ($args as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $data .= $key . '[' . $k . ']=' . rawurlencode($v) . '&';
                    }
                }else {
                    $data .= "$key=" . rawurlencode($val) . "&";
                }
            }
            return trim($data, "&");
        }
        return $args;
    }

}
