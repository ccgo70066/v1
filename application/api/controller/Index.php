<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功', 'index');
    }

    public function index1()
    {
        $this->success('请求成功', 'index1');
    }

    public function demo()
    {
        $this->success('', [
            'username'  => 'demo',
            'test' => 'demo',
            'list'  => [
                ['id' => 1, 'name' => 'demo1'],
                ['id' => 2, 'name' => 'demo2'],
                ['id' => 3, 'name' => 'demo3'],
                ['id' => 4, 'name' => 'demo4'],
                ['id' => 5, 'name' => 'demo5'],
                ['id' => 6, 'name' => 'demo6'],
            ]
        ]);
    }

}
