<?php

use think\Route;

$rs = db('api_route_confuse')->cache(true, 3600, 'api_route_confuse')->column('concat("/api", name)', 'encrypt');

Route::any('/api/:path*', function () {
    return '禁止访问API模块';
})->method('*')->completeMatch(false);

return $rs;
