<?php

use think\Route;

$rs = db('api_route_confuse')
    ->cache(true, 3600, 'api_route_confuse')
    ->column('concat("api", name)', 'encrypt');

$manualRoutes = [
    'api/test' => 'api/test/index',
];

$rs = array_merge($rs ?: [], $manualRoutes);

Route::rule('api/:_miss_', function () {
    return response('', 404);
}, '*', [], ['_miss_' => '[\\s\\S]+']);

return $rs;
