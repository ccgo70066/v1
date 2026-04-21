<?php

namespace app\pay\library;

use fast\Http;
use think\Cache;

/**
 * google pay
 */
class GooglePay extends BasePay
{
    public const CLIENT_ID = 'xxxx';
    public const CLIENT_SECRET = 'xxxx';
    public const API_URL = 'https://accounts.google.com/o/oauth2/token';
    public const REDIRECT_URL = 'https://vo.vogofruit.com/api/gpay';
    /**
     * 关于RefreshToken过期问题
     * api项目-同意屏幕，发布状态为测试（有效期7天）
     * RefreshToken 6个月都未使用，这个要维护accessToken的有效性，应该可以不必考虑
     * 授权账号改密码了（笔者未测试，修改开发者账号密码是否会导致过期）
     * 授权超过50个刷新令牌，最先的刷新令牌就会失效（这里50个应该够用了，除了测试时，可能会授权多个）
     * 取消了授权
     * 属于具有有效会话控制策略的 Google Cloud Platform 组织
     */
    public const REFRESH_TOKEN = "xxx";

    /**
     * 获取access_token
     * @return mixed
     */
    public function get_access_token()
    {
        $key = 'google:pay:access_token';
        if (cache($key)) {
            return cache($key);
        }
        $params = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => self::REFRESH_TOKEN,
            'client_id'     => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ];
        $result = json_decode(Http::post(self::API_URL, $params), true);
        // {"access_token":"xxx","expires_in":3599,"scope":"androidpublisher","token_type":"Bearer"}
        // {"error":"invalid_grant","error_description":"Bad Request"}
        if (isset($result['error'])) {
            trace('获取accesstoken异常', 'error');
            trace($result, 'error');
            return null;
        }
        cache($key, $result['access_token'], $result['expires_in']);
        return $result['access_token'];
    }


    /**
     * 订单验证
     * @param $params
     * @return mixed
     */
    public function query($params)
    {
        $packageName = $params['packagename'];//app包名，必须是创建登录api项目时，创建android客户端Id使用包名
        $productId = $params['productid'];//对应购买商品的商品ID
        $token = $params['token']; //购买成功后Purchase对象的getPurchaseToken()
        $access_token = $this->get_access_token();
        $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$packageName}/purchases/products/{$productId}/tokens/{$token}?access_token={$access_token}";

        $result = Http::get($url);
        return json_decode($result, true);
    }
}

