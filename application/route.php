<?php

use think\Route;

$dbRoutes = db('api_route_confuse')
    ->cache(true, 3600, 'api_route_confuse')
    ->column('concat("api", name)', 'encrypt');

if (!empty($dbRoutes)) {
    foreach ($dbRoutes as $route => $target) {
        Route::rule($route, $target);
    }
}

Route::any('api/test$', 'api/test/index');
Route::any('api/test/:action', 'api/test/:action');
Route::rule('api/:_miss_', function () {
    return response('', 404);
}, '*', [], ['_miss_' => '[\\s\\S]+']);

