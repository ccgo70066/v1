<?php

// app 相关配置
use think\Env;

return [
    'cdn_url'               => Env::get('app.cdn_url'),
    'icon'                  => [
        'diamond' => 'assets/icon/diamond.png',   // 金幣
    ],
    'default_avatar_gender' => [
        ['gender' => 0, 'avatar' => '/assets/avatar/f_1.jpg',],
        ['gender' => 0, 'avatar' => '/assets/avatar/f_2.jpg',],
        ['gender' => 0, 'avatar' => '/assets/avatar/f_3.jpg',],
        ['gender' => 1, 'avatar' => '/assets/avatar/m_1.jpg',],
        ['gender' => 1, 'avatar' => '/assets/avatar/m_2.jpg',],
        ['gender' => 1, 'avatar' => '/assets/avatar/m_3.jpg',],
    ],
    'kf_id'                 => 'kf_001',    //运营客服
    'recharge_kf_id'        => 'kf_recharge',   //充值客服
    'noble_protection_time' => 604800,  //贵族保护期,7天
];

