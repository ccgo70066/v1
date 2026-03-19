<?php


namespace util;


class Sign
{
    /**
     * 签名生成算法
     * @param $params
     * @param $key
     * @return string|null
     */
    public static function generate($params, $key)
    {
        if ($params && $key) {
            unset($params['sign']);
            ksort($params);
            $params['key'] = $key;
            return md5(urldecode(http_build_query($params)));
        } else {
            return null;
        }

    }

    public static function generateWithoutKey($params)
    {
        if ($params) {
            ksort($params);
//            $params['key'] = $key;
            return md5(http_build_query($params));
        } else {
            return null;
        }

    }

    public static function sortParam(array $arrayData)
    {
        $str = '';
        if (empty($arrayData)) return $str;

        ksort($arrayData);
        foreach ($arrayData as $k => $v) {
            if (!empty($v)) {
                $str .= $k . $v;
            }
        }
        return $str;
    }

    public static function generateOtc($data, $key)
    {
        ksort($data);
        $data['key'] = $key;
        $encrypt = urldecode(http_build_query($data));
        return strtoupper(md5($encrypt));
    }
}