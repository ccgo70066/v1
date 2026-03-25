<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */

namespace addons\socket\library\GatewayWorker\Applications\App;

//declare(ticks=1);

use fast\Http;
use GatewayWorker\Lib\Gateway;
use think\Cache;
use think\Db;
use think\Env;
use think\Exception;
use think\Log;
use Tool;
use util\Huobi;
use Workerman\Lib\Timer;
use app\admin\model\Shield;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{

    public static function onWorkerStart(\GatewayWorker\BusinessWorker $businessWorker)
    {
        echo "WorkerStart:$businessWorker->id\n";
    }


    /**
     * WebSocket 链接成功
     *
     * @param int $client_id data
     * @param $[data] [websocket握手时的http头数据，包含get、server等变量]
     */
    public static function onWebSocketConnect($client_id, $data)
    {
        echo "WebSocketConnect:$client_id\n";
    }

    /**
     * 当客户端发来消息时触发
     * @param int   $client_id 连接id
     * @param mixed $message   具体消息
     */
    public static function onMessage($client_id, $message)
    {
        echo "Message:$client_id, $message\n";
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        echo "Close:$client_id\n";
    }

}
