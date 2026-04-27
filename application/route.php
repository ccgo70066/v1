<?php

use think\Route;

// 从数据库读取API路由配置
$rs = db('api_route_confuse')->cache(true, 3600, 'api_route_confuse')->column('concat("api", name)', 'encrypt');

// 手动添加允许访问的路由（不依赖数据库）
$manualRoutes = [
    'api/test' => 'api/test/index',
];

// 合并数据库路由和手动配置的路由
$rs = array_merge($rs ?: [], $manualRoutes);

// 添加兜底路由，禁止直接访问未配置的API控制器
Route::rule('api/:_miss_', function () {
    return response('', 404);
}, '*', [], ['_miss_' => '[\\s\\S]+']);

return $rs;
