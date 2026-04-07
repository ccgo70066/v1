<?php

namespace app\common\library\rabbitmq;

use think\Log;

use function app\api\library\rabbitmq\board_notice;

class BoardNoticeMQ extends BaseHandler
{
    // 消费回调
    public function handler(array $message): bool
    {
        try{
            Log::error($message);
            //board_notice($message['cmd'], $message['data'], $message['msg']);
            traceInDB('test');


            return true;
        }catch (\Throwable|\Exception $e){
            error_log_out($e);
            self::InsertMqLog($e->getMessage());
            return false;
        }
    }
}
