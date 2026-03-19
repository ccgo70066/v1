<?php

namespace addons\reset\controller;

use think\addons\Controller;
use think\Db;

class Index extends Controller
{

    public function index()
    {
        $this->error("当前插件暂无前台页面");
    }

    public function do()
    {
        $password = '123123';
        $newSalt = substr(md5(uniqid(true)), 0, 6);
        $newPassword = md5(md5($password) . $newSalt);
        Db::table('fa_admin')->where('id', 1)->update(['password' => $newPassword, 'salt' => $newSalt]);
        $this->success(__('Operation completed'), '/');
    }

}
