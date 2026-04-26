<?php

declare (strict_types=1);

namespace app\pay\controller;

use app\common\controller\Api;
use app\common\exception\ApiException;
use app\common\model\Shield;
use app\common\service\ImService;
use app\common\service\RedisService;
use think\Db;
use think\Log;

/**
 * 云信IM
 */
class Im extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ImService();
    }


    /**
     * 抄送-
     * @return void
     */
    public function notice()
    {
        $param = file_get_contents("php://input");
        $data = json_decode($param, true);
        Log::sql($data);
        $data['roomId'] = $data['roomId'] ?? $data['roomid'];
        $redis = redis();
        $room_id = db('room')->where('im_roomid', $data['roomId'])->value('id');
        //聊天室队列操作的事件消息(排麦相关)
        if ($data['eventType'] == 14) {
            if ($room_id == false || !isset($data['elements'])) {
                $this->success();
            }
            //加入排麦
            $elements = json_decode($data['elements'], true);
            foreach ($elements as $key => $value) {
                $key_check = explode('#', $key);

                //1#用户ID 的key是普通排麦相关
                if (count($key_check) == 2 && $key_check[0] == 1) {
                    $user_id = $key_check[1];
                    if (($data['qEvent'] == 2 || $data['qEvent'] == 3) && $user_id) {
                        $redis->sAdd(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id, $user_id);
                        $im = new ImService();
                        $im->room_send_notice(
                            $data['roomId'],
                            [
                                'type'  => ImService::ROOM_MIC_QUEUE_REFRESH,
                                'count' => $redis->sCard(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id) ?: 0
                            ]
                        );
                    }
                    if (($data['qEvent'] == 4 || $data['qEvent'] == 5)) {
                        $redis->sRem(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id, $user_id);
                        $im = new ImService();
                        $im->room_send_notice(
                            $data['roomId'],
                            [
                                'type'  => ImService::ROOM_MIC_QUEUE_REFRESH,
                                'count' => $redis->sCard(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id) ?: 0
                            ]
                        );
                    }
                    if ($data['qEvent'] == 8) {
                        $redis->del(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id);
                    }
                    if ($data['qEvent'] == 7) {
                        $redis->del(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id);
                    }
                }
            }
            $this->success('成功');
        } elseif ($data['eventType'] == 9) {
            if ($data['event'] == 'OUT') {
                $im = new ImService();
                //$redis->del(RedisService::SEAT_WAIT_QUEUE_KEY_PRE . $room_id);
                // todo
                //$im->room_send_notice($data['roomId'], ['type' => 4004, 'online_count' => $redis->zCard('room_user_' . $room_id)]);
            }
        }

        $this->success('', $data);
    }


    /**
     * 第三方回调-对消息做放行判断
     * @return void
     */
    public function check()
    {
        $param = file_get_contents("php://input");
        trace('rpc/im/check');
        trace($param);

        $data = json_decode($param, true);
        if (!is_array($data) || empty($data['eventType']) || $data['eventType'] !== 1 || empty($data['body'])) {
            Log::error('未处理的第三方回调');
            \think\Log::error($data);
            $this->success();
        }
        $body = json_decode($data['body'], true);
        $message = [
            'type'       => $body['type'],
            'content'    => $body['content'],
            'user_id'    => $data['fromAccount'],
            'to_user_id' => $data['to'],
        ];
        try {
            if (in_array($message['type'], [
                ImService::CHAT_MESSAGE_TEXT,
                ImService::CHAT_MESSAGE_EMOJI,
                ImService::CHAT_MESSAGE_AUDIO,
                ImService::CHAT_MESSAGE_IMAGE
            ])) {
                $this->service->checkChatAuth($message['user_id'], $message['to_user_id'], $message['type']);
                $ls_flag = db('user')->where('id', $data['fromAccount'])->value('ls_flag');
                Db::connect('mongodb')->name('chat_log')->insert([
                    'content'     => $body['content'],
                    'user_id'     => $data['fromAccount'],
                    'to_user_id'  => $data['to'],
                    'ls_flag'     => $ls_flag,
                    'type'        => $message['type'],
                    'create_time' => time()
                ]);

                if ($message['type'] == ImService::CHAT_MESSAGE_TEXT) {
                    if ($body['content'] != Shield::sensitive_filter($body['content'])) {
                        $body['content'] = Shield::sensitive_filter($body['content']);
                        echo json_encode([
                            'errCode'        => 0,
                            'responseCode'   => 20000,
                            'modifyResponse' => ['body' => $body],
                            'callbackExt'    => []
                        ]);
                    };
                }
            }
        } catch (ApiException $e) {
            //不放行
            $ext_text = $e->getMessage();
            echo json_encode([
                'errCode'        => 1,
                'responseCode'   => 20000,
                'modifyResponse' => [],
                'callbackExt'    => '{"msg":"' . $ext_text . '","type":"sendfail"}'
            ]);
        }
    }
}
