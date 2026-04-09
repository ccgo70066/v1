<?php

// app 相关配置
use think\Env;

return [
    'power_code'    => Env::get('app.power_code', '1472'),
    'sign_key'      => 'kyC87Bg9gwAmteRu',
    'logo'          => 'resource/image/index/logo.png',
    'default_appid' => 'DD100',
    'cdn_url'       => Env::get('app.cdn_url'),
    // 'pay_domain'            => 'pay.vogofruit.com',  // 支付回调地址
    // 'recharge_domain'       => 'https://recharge.vogofruit.com',  // 支付主頁

    'icon'                  => [
        'diamond' => 'assets/icon/diamond.png',   // 金幣
    ],
    // 后台也需要更新
    'default_avatar_gender' => [
        ['gender' => 0, 'avatar' => '/assets/avatar/f_1.jpg',],
        ['gender' => 0, 'avatar' => '/assets/avatar/f_2.jpg',],
        ['gender' => 0, 'avatar' => '/assets/avatar/f_3.jpg',],
        //['gender' => 0, 'avatar' => '/assets/avatar/f_4.png',],
        // ['gender' => 0, 'avatar' => '/assets/avatar/f_5.png',],
        ['gender' => 1, 'avatar' => '/assets/avatar/m_1.jpg',],
        ['gender' => 1, 'avatar' => '/assets/avatar/m_2.jpg',],
        ['gender' => 1, 'avatar' => '/assets/avatar/m_3.jpg',],
        // ['gender' => 1, 'avatar' => '/assets/avatar/m_4.png',],
        // ['gender' => 1, 'avatar' => '/assets/avatar/m_5.png',],
    ],
    'kf_id'                 => 'kf_001',    //运营客服
    'recharge_kf_id'        => 'kf_recharge',   //充值客服
    'noble_protection_time' => 604800,  //贵族保护期,7天
    // +----------------------------------------------------------------------
    // | websocket
    // +----------------------------------------------------------------------
    // 'ws_url'                => 'websocket://127.0.0.1:8282',
    // 'ws_admin_url'          => Env::get('app.ws_admin_url', 'tcp://192.168.0.229:7273'),
    'receive_gifts'         => 0.70, //  未加入家族的用户或不在本家族派对的陪陪收到礼物，自己获得50%收益
    'union_receive_gifts'   => 0.70, //  加入家族的陪陪并且在本家族下的派对收到礼物，自己获得70%收益
    'gift_union_owner'      => 0.15, // 送礼物所在派对的家族族长获得15%收益
    'gift_union_profit'     => 0.03, // 送礼物所在派对的家族族长获得5%可提领收益
];

