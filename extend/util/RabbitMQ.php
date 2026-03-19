<?php

namespace util;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\Env;
use think\Exception;


/**
 * RabbitMQ 工具类
 */
class RabbitMQ
{
    protected $host;
    protected $port;
    protected $username;
    protected $password;

    protected $connection;
    /**
     * @var AMQPChannel
     */
    protected $channel;
    protected $queue_name;

    public function __construct($queue_name)
    {
        $this->host = Env::get('rabbitmq.host', 'localhost');
        $this->port = '5672';
        $this->username = Env::get('rabbitmq.user', 'guest');
        $this->password = Env::get('rabbitmq.pass', 'guest');
        $this->queue_name = $queue_name;

        $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->username, $this->password);
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->queue_name, 'x-delayed-message',
            false, true, false, false, false, new AMQPTable(['x-delayed-type' => 'direct']));
        // queue 队列名
        // passive 检测队列是否存在  true 只检测不创建 false 创建
        // durable 是否持久化队列 true 为持久化
        // exclusive 私有队列 不允许其它用户访问  设置true 将会变成私有
        // auto_delete  当所有消费客户端连接断开后，是否自动删除队列
        $this->channel->queue_declare($queue_name, false, false, false, false);
        //队列与exchange绑定
        $this->channel->queue_bind($queue_name, $queue_name, $queue_name);
    }

    public function get_channel()
    {
        return $this->channel;
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * 生产
     * @param string|array $data
     * @param int          $delay millisecond
     * @return void
     */
    public function publish($data, $delay = 0)
    {
        is_array($data) && $data = json_encode($data);
        $msg = new AMQPMessage($data, [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            //此处是重点，设置延时时间，单位是毫秒 1s=1000ms
            'application_headers' => new AMQPTable(['x-delay' => $delay,])
        ]);
        $this->channel->basic_publish($msg, $this->queue_name, $this->queue_name);
        $this->close();
    }

    /**
     * 消费
     * @return void
     */
    public function work($callback = null)
    {
        // $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($this->queue_name, '', false, false, false, false, $callback);

        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
    }
}
