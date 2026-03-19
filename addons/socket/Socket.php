<?php

namespace addons\socket;

use app\common\library\Menu;
use think\Addons;

/**
 * 插件
 */
class Socket extends Addons
{
    public function appInit()
    {
        if (request()->isCli()) {
            \think\Console::addDefaultCommands([
                //'addons\socket\command\Socket',
                'addons\socket\library\GatewayWorker\start'
            ]);
        }
    }

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {
        return true;
    }

}
