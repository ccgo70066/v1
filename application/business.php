<?php

use app\admin\model\User;
use app\common\exception\ApiException;
use app\common\library\rabbitmq\BaseHandler;
use app\common\model\MoneyLog;
use think\Db;
use think\Env;
use think\Log;

function getLastSql()
{
    return \think\Db::getLastSql();
}

function traceInDB($content)
{
    db('test')->insert(['content' => json_encode($content)]);
}

if (!function_exists('trace')) {
    function trace($log = '[think]', $level = 'log')
    {
        if ('[think]' === $log) {
            return Log::getLog();
        }
        $back = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        Log::record($back[0]['file'] . ':' . $back[0]['line'], $level);
        Log::record($log, $level);
    }
}

function redis()
{
    static $redis;
    if (!$redis) {
        $config = config('cache.redis');
        $redis = new Redis();
        $redis->connect($config['host'], $config['port']);
        !empty($config['password']) && $redis->auth($config['password']);
        $redis->select($config['select'] ?? 1);
    }
    return $redis;
}

/**
 * 获取缓存开关
 * @return bool
 */
function cacheFlag(): bool
{
    if (Env::get('app.debug')) return false;
    return Env::get('app.cache', 1) == 1;
}


/**
 * 获取配置
 */
function get_site_config($name)
{
    $value = db('config')->cache(cacheFlag(), 86400)->where('name', $name)->value('value');
    if (!$value && $value != 0) {
        trace('找不到配置值:' . $name, 'error');
    }
    return $value;
}

/**
 * 用户余额变更
 * @param int    $user_id 用户
 * @param float  $money   金额
 * @param string $memo    备注
 * @return array [$before, $after]
 * @throws
 */
function user_money_change($user_id, $money, string $memo = '')
{
    $user = User::where('id', $user_id)->field('id,money')->lock(true)->find();
    if (!$user) throw new ApiException(__('User does not exist'));
    $before = $user['money'];
    $after = $before + $money;
    if ($after < 0) throw new ApiException(__('Insufficient balance'));
    $row = User::where(['id' => $user_id])->inc('money', $money)->update();
    $row > 0 && MoneyLog::create(compact('user_id', 'money', 'before', 'after', 'memo'));

    return [$before, $after];
}


/**
 * 用户数值变更
 * @param int    $user_id
 * @param string $type   数值类型:integral,amount,reward_amount,lock_amount,charm,shred
 * @param double $amount 数值大小
 * @param string $flow   流水:increase=增长,decrease=减少
 * @param null   $note   备注
 * @param int    $from   分类:0=其它,1=商城兑换,2=活动奖励,3=充值金幣,4=打赏礼物,5=赠送盲盒,6=IM红包,7=红包雨,8=兑换金幣,9=兑换游戏券,10=守护分成,11=家族流水奖励兑换,12=家族周流水扶持,13=收益提现
 * @throws
 */
function user_business_change($user_id, $type, $amount, $flow = 'increase', $note = '', $from = 0, $room_id = 0)
{
    if ($amount < 0) throw new ApiException(__('Invalid operation'));
    $business = db('user_business')->where('id', $user_id)->lock(true)->find();
    if (!$business) throw new ApiException(__('User does not exist'));
    $origin_amount = $business[$type];
    $diff_amount = $flow == 'decrease' ? -$amount : $amount;
    $later_amount = $origin_amount + $diff_amount;
    if ($later_amount < 0) throw new ApiException(__('Insufficient balance'));
    $row = db('user_business')->where(['id' => $user_id, 'version' => $business['version']])->inc('version')->inc($type, $diff_amount)->update();
    if ($row > 0) {
        business_log_add($user_id, $type, $flow, $origin_amount, $later_amount, $amount, $note, $from);
    }
}

/**
 * @param int    $user_id       用户名
 * @param int    $type          类型:1=积分,2=金幣,3=金幣,4=可提现收益(币),5=锁定金额,6=魅力值，7=能量, 8=VIP成长值,9=VIP积分
 * @param string $cate          变化类型:increase=增加,decrease=减少
 * @param double $origin_amount 原始数值
 * @param double $later_amount  结果数值
 * @param string $comment       备注
 * @param int    $from          来源:1=面板礼物,2=背包礼物,3=房间流水分成,5=红包,6=提現,0=其他
 */
function business_log_add(
    $user_id,
    $type,
    $cate,
    $origin_amount,
    $later_amount,
    $amount,
    $comment,
    $from = 0,
    $room_id = 0
) {
    $typeArr = [
        'integral'      => 1,
        'amount'        => 2,
        'reward_amount' => 4,
    ];
    if (!is_numeric($type) && isset($typeArr[$type])) {
        $type = $typeArr[$type];
    }

    db($type == 1 ? 'user_integral_log' : 'user_business_log')->insert([
        'user_id'       => $user_id,
        'type'          => $type,
        'cate'          => $cate == 'increase' ? 1 : 0,
        'origin_amount' => $origin_amount,
        'amount'        => $later_amount,
        'change_amount' => $amount,
        'comment'       => $comment,
        'from'          => $from,
        'room_id'       => $room_id
    ]);
}

/**
 * 数组下标过滤
 * @param array        $array
 * @param array|string $filter  ['id','name'] |'id,name'
 * @param bool         $exclude true=排除,false=保留
 * @return array
 */
function array_index_filter($array, $filter, $exclude = false)
{
    if (!is_array($filter) && is_string($filter)) {
        $filter = explode(',', $filter);
    }
    if ($exclude) {
        return array_diff_key($array, array_flip($filter));
    } else {
        return array_intersect_key($array, array_flip($filter));
    }
}

/**
 * 报错信息输出到mongodb
 * @param Throwable $e
 * @param array     $data
 * @return void
 */
function error_log_out(Throwable $e, $data = [])
{
    if (
        $e instanceof ApiException ||
        $e instanceof \think\exception\HttpResponseException ||
        $e instanceof \think\exception\HttpException
    ) {
        return;
    }
    try {
        $ext = [];
        if (!request()->isCli()) {
            $ext['param'] = request()->param();
            $ext['module'] = request()->module();
            $ext['controller'] = request()->controller();
            $ext['action'] = request()->action();
        }
    } catch (Throwable $exception) {
        $ext = [];
    }
    try {
        $errorData['dir'] = basename(dirname(__DIR__));
        $errorData['error_message'] = $e->getMessage();
        $errorData['error_trace'] = explode("\n", $e->getTraceAsString());
        $errorData['file'] = str_replace(dirname(__DIR__), '', $e->getFile());
        $errorData['line'] = $e->getLine();
        $errorData['create_time'] = datetime();
        $LogData = array_merge($errorData, $data, $ext);
        Db::connect('mongodb')->table('fa_error_log')->insert($LogData);
    } catch (Throwable $e) {
        Log::error('mongodb写错误日志报错' . $e->getMessage());
    }
}

function show_error_notify($e)
{
    return $e->getMessage();
}


function union_profit_statistics($union_id, $gift_val, $union_reward_val, $receiver)
{
    if (!$union_id) {
        return;
    }
    // 用户在自己家族房间收礼, 自己家族族长分15%收益
    $union_user = db('union_user')->where('user_id', $receiver)->where('status', 'in', [2, 3, 6])->find();
    if ($union_user && $union_user['union_id'] == $union_id) {
        $union = db('union')->where(['id' => $union_id])->find();
        $owner_rate = config('app.gift_union_owner');
        user_business_change($union['owner_id'], 'reward_amount', $gift_val * $owner_rate, 'increase', '联盟派对收获礼物', 4);
    }

    // 用户在家族房间收礼, 流水5%进家族收益
    $exist = db('union_profit')->where(['union_id' => $union_id])->find();
    if (!$exist) {
        db('union_profit')->insert(['union_id' => $union_id, 'val' => $gift_val, 'reward_val' => $union_reward_val]);
    } else {
        db('union_profit')->where(['union_id' => $union_id])->setInc('val', $gift_val);
        db('union_profit')->where(['union_id' => $union_id])->setInc('reward_val', $union_reward_val);
    }
}


function mq_publish(BaseHandler $baseHandler, array $message, $delay = 0)
{
    try {
        return $baseHandler->publish($message, $delay);
    } catch (throwable|Exception $e) {
        error_log_out($e);
        Log::error($e->getMessage());
        //$baseHandler::InsertMqLog($e->getMessage());
        return false;
    }
}

function mq_stop_consume(BaseHandler $baseHandler)
{
    try {
        $baseHandler->stopCurrentConsume();
    } catch (throwable|Exception $e) {
        Log::error($e->getMessage());
        error_log_out($e);
        return false;
    }
}

function mq_consume(BaseHandler $baseHandler)
{
    try {
        // $baseHandler->stopCurrentConsume();
        $baseHandler->consume();
    } catch (throwable|Exception $e) {
        error_log_out($e);

        Log::error($e->getMessage());
        return false;
    }
}


function get_user_info($userIds, array $extend = []): array
{
    $data = [];
    if (empty($userIds)) {
        return $data;
    }

    $query = db('user r')->where('r.id', 'in', $userIds)
        ->field('r.id as user_id,r.nickname,r.avatar,r.age,r.gender');

    if (in_array('level', $extend)) {
        $query->join('user_business s', 'r.id = s.id');
        $query->join('level i', 's.level = i.grade', 'left');
        $query->field('s.level,i.icon as level_icon');
    }

    if (in_array('noble', $extend)) {
        $time = datetime(time() - config('app.noble_protection_time'));
        $query->join('user_noble un', "r.id = un.user_id and un.end_time >'{$time}'", 'left');
        $query->join('noble l', 'un.noble_id = l.id', 'left');
        $query->field('l.name as noble_name,l.badge as noble_badge');
    }
    if (in_array('adornment', $extend)) {
        $time = datetime();
        $query->join('user_adornment ua', "r.id = ua.user_id and ua.expired_time >'{$time}' and ua.is_wear = 1", 'left');
        $query->join('adornment a', 'ua.adornment_id = a.id', 'left');
        $query->field('a.face_image as adornment');
    }
    if (!is_array($userIds)) {
        $data = $query->find();
    } else {
        $list = $query->select();
        foreach ($list as $v) {
            $data[$v['user_id']] = $v;
        }
    }

    return $data;
}
