<?php

namespace addons\reset\command;

use fast\Random;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Reset extends Command
{
    protected function configure()
    {
        $this->setName('reset')
            ->setDescription('reset admin password');
    }

    protected function execute(Input $input, Output $output)
    {
        $password = Random::alnum(8);
        $password = '123123';
        $newSalt = substr(md5(uniqid(true)), 0, 6);
        $newPassword = md5(md5($password) . $newSalt);
        Db::table('fa_admin')->where('id', 1)->update(['password' => $newPassword, 'salt' => $newSalt]);
        $output->highlight("Admin newPassword: $password");
    }
}
