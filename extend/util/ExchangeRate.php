<?php

namespace util;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use fast\Http;
use GatewayClient\Gateway;
use think\Cache;
use think\Log;

/**
 * Exchange Rate API
 */
class ExchangeRate
{

    /**
     * 获取以USD为基准的货币汇率
     * @return mixed
     * @link https://www.exchangerate-api.com/
     */
    public static function getCurrencyRates()
    {
        $rates = Cache::remember('exchange_rates', function () {
            $api_url = 'https://open.er-api.com/v6/latest/USD';
            $response = Http::get($api_url);
            $data = json_decode($response, true);
            if ($data) {
                return $data['rates'];
            }
            Log::error('Exchange Rate API Error: ' . $response);
            return null;
        }, 86400);
        if (!$rates) cache('rates', null);
        return $rates;
    }

    /**
     * 获取金价
     * @param string $metal Metal Symbol:Gold=XAU,Silver=XAG,Platinum=XPT,Palladium=XPD;
     * @param string $currency
     * @return mixed|null
     * @link https://www.goldapi.io/dashboard
     */
    public static function getMetalPrice(string $metal = 'XAU', string $currency = 'USD')
    {
        $price = Cache::remember('GoldApi:' . $metal . '-' . $currency, function () use ($metal, $currency) {
            $api_url = 'https://www.goldapi.io/api/';
            $api_key = 'goldapi-3pz9dsmk3gaqey-io';
            $response = Http::get($api_url . $metal . '/' . $currency, [], [CURLOPT_HTTPHEADER => ['x-access-token: ' . $api_key]]);
            $data = json_decode($response, true);
            if ($data && isset($data['price'])) {
                return $data['price'];
            }
            Log::error('GoldApi getMetalPrice error:' . $response);
            return null;
        }, 86400);
        !$price && cache('GoldApi:' . $metal . '-' . $currency, null);
        return $price;
    }

}
