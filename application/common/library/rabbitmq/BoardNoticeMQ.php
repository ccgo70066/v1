<?php

namespace app\common\library\rabbitmq;

use think\Log;

class BoardNoticeMQ extends BaseHandler
{
    // 消费回调
    public function handler(array $message): bool
    {
        try{
            board_notice($message['cmd'], $message['data'], $message['msg']);
            return true;
        }catch (\Throwable|\Exception $e){
            error_log_out($e);
            self::InsertMqLog($e->getMessage());
            return false;
        }
    }
}
