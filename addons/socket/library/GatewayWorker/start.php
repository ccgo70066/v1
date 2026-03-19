<?php
/**
 * run with command
 * php start.php start
 */

namespace addons\socket\library\GatewayWorker;

ini_set('display_errors', 'on');

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Workerman\Worker;

/**
 *
 */
class start extends Command
{
    protected function configure()
    {
        $this->setName('socket')
            ->addArgument('action', Argument::OPTIONAL, "action  start [d]|stop|restart|status")
            ->addArgument('type', Argument::OPTIONAL, "d -d")
            ->setDescription('socket service');
    }

    protected function execute(Input $input, Output $output)
    {
        global $argv;
        $action = trim($input->getArgument('action') ?: 'status');
        $type = trim($input->getArgument('type') ?: '') ? '-d' : '';

        $argv[0] = 'chat';
        $argv[1] = $action;
        $argv[2] = $type ? '-d' : '';
        $this->start();
    }

    private function start()
    {
        if (strpos(strtolower(PHP_OS), 'win') === 0) {
            exit("Windows下不支持窗口启动，请手动运行(not support windows, please use)：public/kefu_start_for_win.bat\n");
        }

        // 检查扩展
        if (!extension_loaded('pcntl')) {
            exit("Please install pcntl extension. See http://doc.workerman.net/appendices/install-extension.html\n");
        }

        if (!extension_loaded('posix')) {
            exit("Please install posix extension. See http://doc.workerman.net/appendices/install-extension.html\n");
        }

        // 标记是全局启动
        define('GLOBAL_START', 1);

        require_once __DIR__ . '/vendor/autoload.php';

        // 加载所有Applications/*/start.php，以便启动所有服务
        foreach (glob(__DIR__ . '/Applications/*/start*.php') as $start_file) {
            require_once $start_file;
        }
        !file_exists('runtime/workerman') && mkdir('runtime/workerman');
        // 所有的打印输出全部保存在/tmp/stdout.log文件中
        Worker::$stdoutFile = 'runtime/workerman/stdout.log';
        Worker::$logFile = 'runtime/workerman/workerman.log';
        Worker::$pidFile = 'runtime/workerman/workerman.pid';

        // 运行所有服务
        Worker::runAll();
    }
}
