<?php

namespace app\pay\library;

use AlipayConfig;
use AlipayFundTransUniTransferRequest;
use AlipayTradeAppPayRequest;
use AopCertClient;
use think\Env;

class BasePay
{
    /** @var bool|mixed|string|null  支付相关域名 */
    protected $pay_domain;
    /** @var string  商品描述 */
    protected $description;

    public function __construct()
    {
        $this->pay_domain = Env::get('app.pay_domain');
        $this->description = get_site_config('pay_body_description')??'goods';
    }
}
