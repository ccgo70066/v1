<?php


namespace app\common\service;
use app\common\library\ApiException;

/**
 * 异步请求类
 */
class AsyncRequestService
{
    /**
     * @param $url string 请求地址
     * @param $params array 请求参数,可为一维或多维数组或json字符串,json字符串会被转化为数组
     * @param $method string 请求方法
     * @return void
     */
    public static function send($url, $params = [], $method = 'POST')
    {
        try {
            $micro = microtime();
            list($usec, $sec) = explode(' ', $micro);
            $unique_id = $sec . substr($usec, 2, 6) . mt_rand(100000, 999999);

            db('async_request_log')->insert([
                'request_id'     => $unique_id,
                'url'            => $url,
                'params_json'    => json_encode($params),
                'origin_request' => json_encode(array_merge([request()->url()], request()->param()))
            ]);

            if (!is_array($params)) {
                $params = json_decode($params, true);
                $params['request_id'] = $unique_id;
            } else {
                $params['request_id'] = $unique_id;
            }
            self::asyncHttpRequest($url, $params, $method);
        } catch (\Exception $e) {
            \think\Log::error('异步报错');
            \think\Log::error($e->getMessage());
        }
    }

    //确认异步请求收到
    public static function ask()
    {
        if (!db('async_request_log')->where(['request_id' => \request()->param('request_id')])->delete()){
            throw new ApiException(__('Duplicate request, not executed'));
        }
    }


    //发送一个异步请求
    public static function asyncHttpRequest($url, $params = [], $method = 'GET', $headers = [], $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if (is_array($params)) {
            $params = http_build_query($params);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 100);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 100);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, fopen('php://stdout', 'w'));
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) == -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
        $response = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);
        curl_close($ch);
        return $response;
    }


}
