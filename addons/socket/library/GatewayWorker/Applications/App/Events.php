<?php

namespace addons\socket\library\GatewayWorker\Applications\App;


use app\common\service\RedisService;
use fast\Http;
use GatewayWorker\BusinessWorker;
use GatewayWorker\Lib\Gateway;
use think\Cache;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Env;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;
use Throwable;

class Events
{

    public static function onWorkerStart(BusinessWorker $businessWorker)
    {
        date_default_timezone_set(Env::get('app.timezone'));
    }

    /**
     * @throws
     */
    public static function onWebSocketConnect($client_id, $data)
    {
        $user_id = trim($data['get']['user_id'] ?? 0);
        if (!isset($data['get']['user_id'])) {
            return Gateway::closeClient($client_id, Message::json(Message::CMD_LOGIN, '', 'login please', Message::CODE_BAD));
        }
        $user = db('user')->field('id,status')->where('id', $user_id)->find();
        if (!$user) return Gateway::closeClient($client_id, Message::json(Message::CMD_LOGIN, '', 'user error', Message::CODE_BAD));
        if ($user['status'] != 'normal') return Gateway::closeClient($client_id, Message::json(2000, 405, 'user disabled', 200));
        if (isset($data['get']['token'])) {
            $token = $data['get']['token'];
            $token = hash_hmac(config('token.hashalgo'), $token, config('token.key'));
            $exist_token = db('user_token')->where(['token' => $token])->find();
            if (!$exist_token) {
                return Gateway::closeClient($client_id, Message::json(2000, 'token error', 200, 405));
            }
        }
        $exist_client_ids = Gateway::getClientIdByUid($user_id);
        foreach ($exist_client_ids as $exist_client_id) {
            if ($exist_client_id != $client_id) {
                Gateway::closeClient($exist_client_id, Message::json(4001, ['user_id' => $user_id], 'login in on another device'));
            }
        }
        Gateway::bindUid($client_id, $user_id);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_status'] = 'connected';
        $_SESSION['user_login_time'] = time();
        $_SESSION['current_date'] = date('Y-m-d');
        $_SESSION['user_last_operate_time'] = time();
        redis()->hSet('onlineUser', $user_id, $client_id);
        db('user')->update(['id' => $user_id, 'is_online' => 1, 'logintime' => time(),]);

        Gateway::sendToCurrentClient(Message::json(Message::CMD_APP_LOGIN, '', 'login success'));
    }

    /**
     * 当客户端发来消息时触发
     * @param int   $client_id 连接id
     * @param mixed $message   具体消息
     */
    public static function onMessage($client_id, $message): void
    {
        $user_id = Gateway::getUidByClientId($client_id);
        $data = json_decode($message, true);
        if (!isset($data['cmd'])) return;
        try {
            if ($data['cmd'] == Message::CMD_HEART) { // 心跳
            }
            if ($data['cmd'] == Message::CMD_CONFIG_UPDATE) {
                Gateway::sendToCurrentClient(Message::json(Message::CMD_CONFIG_UPDATE, ['version' => get_site_config('base_version')]));
            }
            if ($data['cmd'] == Message::CMD_ENTER_ROOM) {
                $room_id = $data['data']['room_id'];
                $_SESSION['user_status'] = 'in_room';
                $_SESSION['user_room'] = $data['data']['room_id'];
                Log::error(($user_id ?: $data['data']['user_id']) . '断线重连进入房间' . $room_id);

                Gateway::joinGroup($client_id, $data['data']['room_id']);

                $url = Env::get('app.lan_api_url') . '/api/room/reconnect';
                Http::post($url, ['user_id' => $user_id ?: $data['data']['user_id'], 'room_id' => $room_id]);
            }
            if ($data['cmd'] == Message::CMD_EXIT_ROOM) {
                $_SESSION['user_status'] = 'connected';
                if (isset($data['data']['room_id']) && $data['data']['room_id']) {
                    Gateway::leaveGroup($client_id, $data['data']['room_id'] ?? '');
                    unset($_SESSION['user_room']);
                }
            }
        } catch (Throwable $e) {
            error_log_out($e);
        }
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        $user_id = $_SESSION['user_id'] ?? 0;
        if ($user_id == 0) {
            $all = redis()->hGetAll('onlineUser');
            $user_id = array_search($client_id, $all);
        }

        $exist_client_ids = Gateway::getClientIdByUid($user_id);
        try {
            if (empty($exist_client_ids)) {
                db('user')->where('id', $user_id)->setField(['is_online' => 0]);
                $redis = redis();
                $redis->hDel('onlineUser', $user_id);
                $room_id = $redis->hGet(RedisService::USER_NOW_ROOM_KEY, $user_id);
                if ($room_id) {
                    Gateway::sendToGroup($room_id, Message::json(Message::CMD_LOSS_NOTICE, ['user_id' => $user_id]));
                    $url = Env::get('app.lan_api_url') . '/room/quit';
                    Http::post($url, ['user_id' => $user_id, 'room_id' => $room_id]);
                }
            }
        } catch (Throwable $e) {
            error_log_out($e);
        }
    }

}
