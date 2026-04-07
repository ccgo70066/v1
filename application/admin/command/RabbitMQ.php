<?php

declare(ticks=1);

namespace app\admin\command;

use app\common\library\rabbitmq\EventHandler;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Request;

class RabbitMQ extends Command
{
    protected $processPath = 'application/common/library/rabbitmq/';    //文件目录
    protected $processNamespace = '\app\common\library\rabbitmq';     //命名空间

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 初始化方法,最前且始终执行
     */
    public function _initialize(Request $request)
    {
        // 只可以以cli方式执行
        if (!$request->isCli()) {
            throw new \Exception('MQ script only work at client!');
        }
    }

    protected function configure()
    {
        $this->setName('rabbitmq')
            ->addOption('method', 'm', Option::VALUE_REQUIRED, '', null)
            ->addOption('args', 'a', Option::VALUE_OPTIONAL, '', null)
            ->setDescription('rabbit message queue');
    }

    protected function execute(Input $input, Output $output)
    {
        $queue_name = trim($input->getOption('method'));
        if (method_exists($this, $queue_name)) {
            call_user_func([$this, $queue_name], $input, $output);
        } else {
            $output->writeln('method not found');
        }
    }


    /**
     * @throws \ReflectionException
     */
    public function restart(Input $input, Output $output)
    {
        if (!$input->getOption('args')) {
            $output->writeln('参数args必传,如果开启或重启全部队列消费者请输入命令 php think rabbitmq -m restart -a all');
            $output->writeln('如果开启或重启某个队列消费者请输入命令php think rabbitmq -m restart -a 类名');
            $output->writeln('例如: php think rabbitmq -m restart -a giftboxMQ');
            return;
        }
        $datetime = datetime();
        \think\Log::sql($datetime . 'rabbitmq-cron开始:' . datetime());
        $classNameList = [];

        //根据参数获取需要启动的消费者,使用类名调用
        if ($input->getOption('args') === 'all') {
            $filesObj = dir($this->processPath);
            while (false !== ($fileName = $filesObj->read())) {
                if (!str_ends_with($fileName, 'MQ.php')) {
                    continue;
                }
                $classNameList[] = substr($fileName, 0, -4);
            }
            //var_dump($classNameList);
            exec("cd " . ROOT_PATH . '&&' . 'php think rabbitmq -m stop -a ' . 'all' . " >/dev/null 2>&1 &");
        } else {
            $argsClassName = ucfirst($input->getOption('args'));
            $exist = class_exists($this->processNamespace . '\\' . $argsClassName);
            if (!$exist) {
                $output->writeln('参数 -a 错误,找不到类:' . $this->processNamespace . '\\' . $argsClassName);
                return false;
            }
            $classNameList[] = $argsClassName;
            exec("cd " . ROOT_PATH . '&&' . 'php think rabbitmq -m stop -a ' . $argsClassName . " >/dev/null");
        }
        \think\Log::sql($datetime . 'rabbitmq-cron关闭消费完成:' . datetime());

        foreach ($classNameList as $className) {
            //$consumes_count = call_user_func($this->processNamespace . '\\' . $className . '::get_consumes_count');
            $consumes_count = (new \ReflectionClass($this->processNamespace . '\\' . $className))->getStaticPropertyValue('consumes_count');
            //dump($consumes_count);
            $consumes_count = (int)$consumes_count > 5 ? 3 : (int)$consumes_count;    //一个队列最多5个消费者
            while ($consumes_count > 0) {
                $consumes_count--;
                $output->writeln('开启消费进程:' . $className);
                // exec("cd " . ROOT_PATH . '&&' . 'php think rabbitmq -m consumesProcess -a ' . $className . " >/dev/null 2>&1 &");
                exec("cd " . ROOT_PATH . '&&' . 'php think rabbitmq -m consumesProcess -a ' . $className . " >/dev/null");
            }
        }
        \think\Log::sql($datetime . 'rabbitmq-cron重启消费完成:' . datetime());
        $output->writeln('restartSuccess');
        //exit('结束'.__LINE__);
    }


    public function stop(Input $input, Output $output)
    {
        if (!$input->getOption('args')) {
            $output->writeln('参数args必传,如果关闭全部连接请输入命令 php think rabbitmq -m stop -a all');
            $output->writeln('如果开启某个队列消费者请输入命令php think rabbitmq -m stop -a 类名');
            return false;
        }
        umask(0);
        if ($input->getOption('args') == 'all') {
            $event = EventHandler::instance();
            $event->exitAllConsume();
        } else {
            $argsClassName = ucfirst($input->getOption('args'));
            $exist = class_exists($this->processNamespace . '\\' . $argsClassName);
            if (!$exist) {
                $output->writeln('参数 -a 错误,找不到类:' . $this->processNamespace . '\\' . $argsClassName);
                return false;
            }
            mq_stop_consume(call_user_func($this->processNamespace . '\\' . $argsClassName . '::instance'));
        }
        $redis = redis();
        $precess = [];
        if ($input->getOption('args') == 'all') {
            $keys = $redis->keys('mq_process_id:*');
            foreach ($keys as $key) {
                $precess = array_merge($precess, $redis->sMembers($key));
                $redis->del($key);
            }
        } else {
            $argsClassName = ucfirst($input->getOption('args'));
            $precess = $redis->sMembers('mq_process_id:' . $argsClassName);
            $redis->del('mq_process_id:' . $argsClassName);
        }
        foreach ($precess as $v) {
            shell_exec('kill -9 ' . $v);
        }
        $redis->close();
        exit('结束' . __LINE__);
    }

    //以独立进程的形式启动消费者
    public function consumesProcess(Input $input, Output $output)
    {
        $className = $input->getOption('args');
        if (!$className) {
            $output->writeln('ConsumesDaemonizeProcess由内部exec调用,');
            return;
        }
        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            exit("process fork fail\n");
        } elseif ($pid > 0) {
            exit(0);
        }
        $pid = posix_getpid();
        $redis = redis();
        $redis->sAdd('mq_process_id:' . $className, $pid);
        $redis->close();
        if (-1 === posix_setsid()) {
            exit("process setsid fail\n");
        }
        mq_consume(call_user_func($this->processNamespace . '\\' . $className . '::instance'));
        exit('结束' . __LINE__);
    }

}
