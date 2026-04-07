<?php
namespace app\common\library\rabbitmq;

class EventHandler extends BaseHandler
{
    //特殊的一个交换机，采用广播模式
    // 消费回调
    public function handler(array $message): bool
    {
        return true;
    }


    public function exitAllConsume()
    {
        $this->publish(['quit' => time() - 5]);

    }
}
