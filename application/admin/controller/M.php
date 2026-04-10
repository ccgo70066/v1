<?php

namespace app\admin\controller;

use think\Cache;
use think\Controller;

/**
 * M
 */
class M extends Controller
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    public function index()
    {
        $user_id = '1232221101';
        Cache::tag('tag', $user_id);
        var_dump(md5('tag'));;
    }
}
