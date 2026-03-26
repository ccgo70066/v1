<?php

use think\Env;

return [
    'type'            => '\think\mongo\Connection',
    'query'           => '\think\mongo\Query',
    'hostname'        => Env::get('mongodb.hostname', '127.0.0.1'),
    'database'        => Env::get('mongodb.database', 'database'),
    'username'        => Env::get('mongodb.username', 'admin'),
    'password'        => Env::get('mongodb.password', '123456'),
    'port'            => Env::get('mongodb.port', '27017'),
    'prefix'          => Env::get('mongodb.prefix', 'fa_'),
    'charset'         => 'utf8',
    'resultset_type'  => 'array',
    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',
    'pk_convert_id'   => true,
];
