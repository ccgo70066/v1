<?php

use think\Route;

$rs = db('api_route_confuse')->cache(true, 3600, 'api_route_confuse')->column('concat("api", name)', 'encrypt');

return $rs;
