<?php

namespace app\common\library\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\Env;
use think\Log;

abstract class BaseHandler
{
    private $connection;    //连接
    public static $consumes_count = 1;
    private string $queue_name;    //队列名
    private $channel;
    private string $exchange_name; //交换机名
    private bool $confirm_select = false;    //生产确认模式
    public static $Log = '../runtime/log/mq.log';    //日志文件,cli和命令行路径表示不同

    /**
     * @var BaseHandler[]
     */
    protected static array $_builders = []; //示例化类

    public static function get_consumes_count()
    {
        return self::$consumes_count;
    }

    /**
     * @return BaseHandler|static
     */
    public static function instance(): BaseHandler
    {
        if (!isset(self::$_builders[$class = get_called_class()])) {
            self::$_builders[$class] = new $class();
        }
        return self::$_builders[$class];
    }


    //$queue_name命名与业务挂钩.
    public function __construct()
    {
        set_time_limit(0);
        $host = Env::get('rabbitmq.host', 'localhost');
        $port = '5672';
        $username = Env::get('rabbitmq.user', 'guest');
        $password = Env::get('rabbitmq.pass', 'guest');
        //$host, $port, $user, $password, $vhost = '/', $insist = false, $login_method = 'AMQPLAIN', $login_response = null,
        //$locale = 'en_US', $connection_timeout = 3.0, $read_write_timeout = 3.0, $context = null,
        // $keepalive = false, $heartbeat = 0, $channel_rpc_timeout = 0.0, $ssl_protocol = null, ?AMQPConnectionConfig $config = null
        //     ) AMQPStreamConnection
        // Overrides:
        $this->connection = new AMQPStreamConnection($host, $port, $username, $password, '/', false, 'AMQPLAIN', null, 'en_US', 3.0, 3.0, null, false, 30);
        $this->connection->set_close_on_destruct(false);
        $this->queue_name = $this->exchange_name = get_called_class();
        $this->channel = $this->connection->channel();
        if (get_called_class() !== EventHandler::class) {
            $this->channel->exchange_declare($this->queue_name, 'x-delayed-message', false, true, false, false, false, new AMQPTable(['x-delayed-type' => 'direct']));
            $this->channel->queue_declare($this->queue_name, false, true, false, false);
            $this->channel->basic_qos(null, 10, null);   // 这个10 就是Unacked 里面的值，表示预先取出多少值来消费
            $this->channel->queue_bind($this->queue_name, $this->exchange_name);
        }
    }


    public function __destruct()
    {
        try {
            Log::error('溪沟了');
            $this->connection->close();
        } catch (\throwable|\Exception $e) {
            self::InsertMqLog('errorMessage:' . $e->getMessage() . ' Line:' . $e->getLine() . ' File:' . $e->getFile());
        }
    }

    //生产
    public function publish(array $message, $delay = 0)
    {
        try {
            //Log::error('生产开始' . datetime());
            if (request()->isCli() && $this->connection->isConnected()) {
                Log::error($this->queue_name . '重连开始' . datetime());
                $this->connection->reconnect();
                $this->channel = $this->connection->channel();
            };
        } catch (\Exception $e) {
        }
        //将投递信息入库,做健全
        $message = serialize($this->publishCreateMsgIdAndLog($message));

        if (!$this->confirm_select) {
            $this->confirm_select = true;
            $this->channel->confirm_select();
        }
        // 投递消息成功处理
        $this->channel->set_ack_handler(function ($msg) {
            //Log::error('成功处理' . datetime());
            $this->publish_success(unserialize($msg->body));
        });
        //投递消息失败处理
        $this->channel->set_nack_handler(function ($msg) {
            $this->publish_fail(unserialize($msg->body));
        });
        $msg = new AMQPMessage($message, [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            //此处是重点，设置延时时间，单位是毫秒 1s=1000ms
            'application_headers' => new AMQPTable(['x-delay' => $delay,])
        ]);
        //投递消息
        $this->channel->basic_publish($msg, $this->queue_name);
        //等待投递消息结果
        $this->channel->wait_for_pending_acks();
        //Log::error('生产完成' . datetime());
        return true;
    }

    //消费
    public function consume()
    {
        $opening_time = time(); //开启时间,用于控制连接关闭

        //绑定广播交换机，用于控制连接断开
        $this->channel->exchange_declare(EventHandler::class, 'fanout', false, true, false);
        $this->channel->queue_bind($this->queue_name, EventHandler::class);
        $this->channel->basic_consume($this->queue_name, '', false, false, false, false, function ($msg) use ($opening_time) {
            try {
                $body = unserialize($msg->body);
                Log::error('开始消费');
                Log::error($body);
                $this->checkAndReconnectDb();
                //重复消费判断
                if (!$this->not_consume_repeat($body)) {
                    //非报错的重复消费则禁止重复消费
                    if (!redis()->get('retry' . $msg->delivery_info['delivery_tag'])) {
                        // // 发送确认消息
                        $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
                        return;
                    }
                }
                if ($this->consume_business($body)) {
                    $this->consume_success($body);
                }
                //停止消费指令
                if ($this->isStopMsg($body, $opening_time)) {
                    try {
                        Log::error('停止消费命令');
                        $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
                        $this->channel->close();
                        $this->connection->close();
                    } catch (\Exception $e) {
                    } finally {
                        exit(11);
                    }
                }
                // // 发送确认消息
                $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
            } catch (\Exception $e) {
                $this->consume_fail($e->getMessage());
                //重试消费
                if (strstr($e->getMessage(), 'Packets out of order. Expected 1 received 0')
                    && redis()->get('retry' . $msg->delivery_info['delivery_tag']) < 3) {
                    redis()->incr('retry' . $msg->delivery_info['delivery_tag']);
                    redis()->expire('retry' . $msg->delivery_info['delivery_tag'], 10);
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, true);
                } else {
                    // 发送拒绝消息,不重试消费
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
                }
            }
        });
        while ($this->channel->is_open()) {
            $this->checkAndReconnectDb();
            try {
                $this->channel->wait(null, false, 30); // 设置30秒超时
            } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                continue; // 超时继续检查
            }
        }
    }

    public function stopCurrentConsume($count = 0)
    {
        $count = $count ?: self::$consumes_count;
        for ($i = $count; $i > 0; $i--) {
            $this->publish(['quit' => time() - 3]);
        }
    }


    public function isStopMsg($body, $current_time)
    {
        if (isset($body['quit']) && $current_time < $body['quit']) {
            return true;
        }
        return false;
    }

    //成功生产
    private function publish_success($message)
    {
        if (!db('mq_log')->where(['mq_id' => $message['mq_id']])
            ->where('status', 0)->setField('status', 1)) {
            self::InsertMqLog('MQ投递成功,更新mq_log失败' . $message['mq_id']);
        } else {
            self::InsertMqLog('MQ投递成功,更新mq_log成功' . $message['mq_id']);
        }
    }

    private function publish_fail($message)
    {
        self::InsertMqLog('MQ生产失败');
        self::InsertMqLog($message);
    }

    public function getOriginData($body)
    {
        if (!empty($body['mq_id'])) {
            unset($body['mq_id']);
        }
        return $body;
    }

    private function consume_success($message)
    {
        if (!db('mq_log')->where(['mq_id' => $message['mq_id']])->where('status', 'in', [1, 2])->setField('status', 3)) {
            self::InsertMqLog('MQ消费成功,更新mq_log失败' . $message['mq_id']);
        } else {
            self::InsertMqLog('MQ消费成功,更新mq_log成功' . $message['mq_id']);
        }
    }

    private function not_consume_repeat($body)
    {
        if (isset($body['quit'])) {
            return true;
        }
        self::InsertMqLog($body);
        try {
            $result = db('mq_log')->where(['mq_id' => $body['mq_id']])->where('status', 'in', [0, 1])->setField('status', 2);
        } catch (\Exception $e) {
            return false;
        }
        if (!$result) {
            self::InsertMqLog('重复消费' . $body['mq_id']);
            //有记录并且已经被消费过的才算重复消费
            return !db('mq_log')->where(['mq_id' => $body['mq_id']])->find();
        }
        return $result;
    }

    private function consume_fail($message)
    {
        self::InsertMqLog('MQ消费失败' . $message);
    }

    private function publishCreateMsgIdAndLog($message)
    {
        //生成唯一的mq_id来记录本次投递
        $micro = microtime();
        list($usec, $sec) = explode(' ', $micro);
        $unique_id = $sec . substr($usec, 2, 6) . mt_rand(100000, 999999);
        if (!is_array($message)) {
            $message = json_decode($message, true);
        }
        //属于重新投递的消息
        if (!empty($message['mq_id'])) {
            return $message;
        }

        $message['mq_id'] = $unique_id;
        db('mq_log')->insert([
            'mq_id'        => $unique_id,
            'queue'        => $this->queue_name,
            'data_json'    => json_encode($message, JSON_UNESCAPED_UNICODE),
            'status'       => 0,
            'request_json' => json_encode(array_merge(['url' => request()->url()], request()->param()), JSON_UNESCAPED_UNICODE)
        ]);

        return $message;
    }


    //消费
    private function consume_business(array $body)
    {
        try {
            $body = $this->getOriginData($body);
            if (isset($body['quit'])) {
                return true;
            }
            $this->checkAndReconnectDb();
            $result = $this->handler($body);
            if (!$result) {
                throw new \Exception($this->queue_name . '消费返回false');
            }
        } catch (\Throwable|\Exception $e) {
            self::InsertMqLog('MQ消费业务逻辑不成功' . $e->getMessage() . $e->getLine() . $e->getFile());
            self::InsertMqLog(json_encode($body));
            return false;
        }
        return true;
    }

    public static function InsertMqLog($text)
    {
        if (is_array($text)) {
            $text = json_encode($text);
        }
        try {
            $reques = request();
            if ($reques->isCli()) {
                self::$Log = './runtime/log/mq.log';
            }
            file_put_contents(self::$Log, datetime() . $text . "\r\n", FILE_APPEND);
        } catch (\Throwable|\Exception $e) {
            Log::error('日志报错' . $e->getMessage());
        }
    }

    private function checkAndReconnectDb()
    {
        try {
            // 执行一个简单的查询来检查连接是否有效
            db()->query('SELECT 1');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            trace('checkAndReconnectDb: ' . $errorMsg);

            // 检查是否为连接断开相关错误
            $connectionErrors = [
                'Broken pipe',
                'server has gone away',
                'Lost connection',
                'is dead or not enabled',
                'no connection to the server',
                'Connection refused',
                'Packets out of order'
            ];

            $shouldReconnect = false;
            foreach ($connectionErrors as $error) {
                if (strpos($errorMsg, $error) !== false) {
                    $shouldReconnect = true;
                    break;
                }
            }
            if ($shouldReconnect) {
                try {
                    \think\Db::connect(); // 重新初始化数据库连接
                    // 测试新连接是否正常
                    db()->query('SELECT 1');
                } catch (\Exception $reconnectException) {
                    // 重连失败，记录日志并抛出异常
                    self::InsertMqLog('数据库重连失败: ' . $reconnectException->getMessage());
                    throw $reconnectException;
                }
            } else {
                error_log_out($e);
                throw $e; // 其他异常继续抛出
            }
        }
    }

    /**
     * 消费响应处理器
     * @return string
     */
    abstract public function handler(array $message);

}






