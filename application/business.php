<?php

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\admin\model\User;
use app\common\exception\ApiException;
use app\common\library\rabbitmq\BaseHandler;
use app\common\library\rabbitmq\BoardNoticeMQ;
use app\common\model\MoneyLog;
use app\common\model\UserBusiness;
use app\common\service\ImService;
use fast\Http;
use GatewayClient\Gateway;
use think\Db;
use think\Env;
use think\Log;

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
 * @param int    $from   分类:0=其它,1=商城兑换,2=活动奖励,3=充值钻石,4=打赏礼物,6=IM红包,7=红包雨,8=兑换钻石,9=兑换游戏券,10=守护分成,11=家族流水奖励兑换,12=家族周流水扶持,13=收益提现
 * @throws
 */
function user_business_change($user_id, $type, $amount, $flow = 'increase', $note = '', $from = 0, $room_id = 0): float
{
    if ($amount < 0) throw new ApiException(__('Invalid operation'));
    $business = db('user_business')->where('id', $user_id)->lock(true)->find();
    if (!$business) throw new ApiException(__('User does not exist'));
    $origin_amount = $business[$type];
    $diff_amount = $flow == 'decrease' ? -$amount : $amount;
    $later_amount = $origin_amount + $diff_amount;
    if ($later_amount < 0) throw new ApiException(__('Insufficient balance'));
    $row = db('user_business')->where(['id' => $user_id, 'version' => $business['version']])
        ->inc('version')->inc($type, $diff_amount)->update();
    if ($row > 0) business_log_add($user_id, $type, $flow, $origin_amount, $later_amount, $amount, $note, $from);

    return $later_amount;
}

/**
 * @param int    $user_id       用户名
 * @param int    $type          类型:1=积分,2=钻石,3=钻石,4=可提现收益(币),5=锁定金额,6=魅力值,7=能量
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
    ) return;
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
        //$LogData['error_trace'] = json_encode($LogData['error_trace']);
        //$LogData['param'] = json_encode($LogData['param']);
        //Db::name('error_log')->insert($LogData);
    } catch (Throwable $e) {
        Log::error('错误日志写入报错: ' . $e->getMessage());
    }
}

function update_seat_gift_val($room_id, $seat, $user_id, $gift_val)
{
    $sql = db('room_seat_gift_info')->fetchSql()->insert([
        'room_id'  => $room_id,
        'seat'     => $seat,
        'user_id'  => $user_id,
        'gift_val' => $gift_val,
    ]);
    return db::execute($sql . "ON DUPLICATE KEY UPDATE gift_val=gift_val+$gift_val");
}


function room_profit_statistics($room_id, $gift_val, $room_reward_val, $receiver)
{
    if ($room_id == 0) return;
    // 用户在自己所在房间收礼, 自己家族族长分15%收益
    $room_admin = db('room_admin')->where('user_id', $receiver)->where('room_id', $room_id)->where('status', 'in', [1, 2])->find();
    if ($room_admin) {
        $room = db('room')->where(['id' => $room_id])->find();
        $owner_rate = get_site_config('gift_room_owner');
        user_business_change($room['owner_id'], 'reward_amount', $gift_val * $owner_rate, 'increase', '厅成员收获礼物', 4);
    }

    // 用户在家族房间收礼, 流水5%进家族收益
    $exist = db('room_profit')->where(['room_id' => $room_id])->find();
    if (!$exist) {
        db('room_profit')->insert(['room_id' => $room_id, 'val' => $gift_val, 'reward_val' => $room_reward_val]);
    } else {
        db('room_profit')->where(['room_id' => $room_id])->inc('val', $gift_val)->inc('reward_val', $room_reward_val)->update();
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


function send_im_msg_by_system($user_id, $text)
{
    if (Env::get('app.server') != 'dev') {
        $imService = new ImService();
        $result = $imService->sendChatMessageByUser($imService::SYS_ID, $user_id, $imService::CHAT_MESSAGE_TEXT, $text);
        return $result;
    }
}

// todo inline
function send_im_msg_by_system_with_lang($user_id, $text, ...$var)
{
    send_im_msg_by_system($user_id, $text);
}


function user_noble_switch($user_id, $switch_type)
{
    $rs = db('user_noble')->where('id', $user_id)->where('end_time', '>', datetime())->find();
    if (!$rs) return 0;
    return 0;
}


function get_users_info($user_ids)
{
    $time = datetime(time() - config('app.noble_protection_time'));
    return db('user u')
        ->join('user_business s', 'u.id = s.id')
        ->join('level i', 's.level = i.grade', 'left')
        ->join('user_noble e', "u.id = e.user_id and e.end_time >'{$time}'", 'left')
        ->join('noble l', 'e.noble_id = l.id', 'left')
        ->where('u.id', 'in', $user_ids)
        ->column(
            'u.id as user_id,u.is_online,u.nickname,u.bio,u.avatar,u.gender,s.level,i.icon as level_icon,e.noble_id,l.name as noble_name,l.badge as noble_badge',
            'u.id'
        );
}


/**
 * 获取商品截至时间距现在多少天和多少小时
 * @param     $lockName
 * @param int $expire
 * @return object $obj->days $obj->hours
 */
function get_expiry_days($expiry_date)
{
    $time = strtotime($expiry_date);
    //计算两个日期之间的时间差
    $diff = $time - time();
    $obj = new stdClass();
    if ($diff < 0) {
        $obj->days = 0;
        $obj->hours = 0;
        return $obj;
    }
    //转换时间差的格式
    $obj->days = floor($diff / (60 * 60 * 24));
    $obj->hours = ceil(($diff % (60 * 60 * 24)) / 3600);
    return $obj;
}

function board_notice($cmd, $data, $msg = '')
{
    try {
        if (in_array($cmd, [Message::CMD_REFRESH_USER, Message::CMD_KICK_USER])) {
            Gateway::sendToUid($data['user_id'], Message::json($cmd, $data, $msg));
        } else {
            Gateway::sendToAll(Message::json($cmd, $data, $msg));
        }
    } catch (Exception $e) {
        if (Env::get('app.debug')) return;
        Log::error($e->getMessage());
    }
}

function board_notice_delay($cmd, $data, $msg = '', $delay = 2)
{
    mq_publish(BoardNoticeMQ::instance(), ['cmd' => $cmd, 'data' => $data, 'msg' => $msg,], $delay * 1000);
}


/**
 * @param int   $user_id 用户名
 * @param int   $type    类型:1=新人礼包,2=首充礼包,3=签到奖励,4=任务奖励,5=活动奖励
 * @param array $data    内容
 */
function other_log_add(int $user_id, int $type, array $data)
{
    if (empty($data)) {
        return false;
    }
    $content = '';
    switch ($type) {
        case '1'://新人礼包
            $content = '新人礼包 获得: ';
            break;
        case '2': //首充礼包
            $content = '首充礼包 获得: ';
            break;
        case '3':  //签到奖励
            $content = '签到 获得: ';
            break;
        case '4': //任务奖励
            $content = '完成任务 获得: ';
            break;
        case '5': //活动奖励
            $content = '活动 获得: ';
            break;
        case '6': //升级奖励
            $content = '升级 获得: ';
            break;
    }
    foreach ($data as &$v) {
        $content .= $v['name'] . '×' . $v['count'] . '; ';
    }
    db('user_other_log')->insert([
        'user_id' => $user_id,
        'type'    => $type,
        'content' => $content,
    ]);
}


function send_check_message($message)
{
    return;
    if (Env::get('app.server') == 'test') return;
    $dialog_id = 'G-1077597528';
    $message = urlencode($message);
    $api_url = "http://47.242.104.63/sendTextMessage?content={$message}&dialogId={$dialog_id}&replyTo=0";
    Http::post($api_url);
}


//添加礼物到用户背包
function user_gift_add($user_id, $gift_id, $count)
{
    $sql = db('user_bag')->fetchSql(true)->insert(['user_id' => $user_id, 'gift_id' => $gift_id, 'count' => $count,]);
    return Db::execute($sql . " on duplicate key update count=count+{$count};");
}


/**
 * 返回数组中指定多列
 *
 * @param Array        $input       需要取出数组列的多维数组
 * @param String|array $column_keys 要取出的列名，['aa','bb']或者'aa,bb'
 * @param String       $index_key   作为返回数组的索引的列
 * @param bool         $check_key   检查数组中是否缺少要取出的字段，true=缺少字段抛出异常,false=缺少字段忽略
 * @return Array
 */
function array_columns($input, $column_keys = null, $index_key = null, $check_key = false)
{
    $result = array();
    $keys = isset($column_keys) ? (!is_array($column_keys) ? explode(',', $column_keys) : $column_keys) : array();

    if ($input) {
        foreach ($input as $k => $v) {
            // 指定返回列
            if ($keys) {
                $tmp = array();
                foreach ($keys as $key) {
                    if (isset($v[$key])) {
                        $tmp[$key] = $v[$key];
                    } elseif ($check_key) {
                        throw new Exception('Undefined index: ' . $key);
                    }
                }
            } else {
                $tmp = $v;
            }
            // 指定索引列
            if (isset($index_key)) {
                $result[$v[$index_key]] = $tmp;
            } else {
                $result[] = $tmp;
            }
        }
    }

    return $result;
}


/**
 * 商城订单成功
 * @param $order_id
 * @param $pay_way
 */
function shop_order_success($order_id, $pay_way = 2)
{
    $order = db('shop_order')->find($order_id);
    $order['status'] = 1;
    $order['pay_way'] = $pay_way;
    $item = db('shop_item')->find($order['item_id']);

    //类型:1=礼物,2=头像框,3=坐骑,4=贵族,6=气泡
    if ($item['type'] == 1) {
        user_gift_add($order['user_id'], $item['item_id'], 1);
    } elseif ($item['type'] == 2) {
        user_adornment_add($order['user_id'], $item['item_id'], $item['days'] * $order['count']);
    } elseif ($item['type'] == 3) {
        user_car_add($order['user_id'], $item['item_id'], $item['days'] * $order['count']);
    } elseif ($item['type'] == 4) {
        user_noble_add($order['user_id'], $item['item_id'], $item['days'] * $order['count']);

        $data = get_user_info($order['user_id'], ['level']);
        $data['goods_name'] = $item['name'];
        $data['goods_image'] = db('noble')->where('id', $item['item_id'])->value('badge');
        board_notice(Message::CMD_SHOW_BUY_NOBLE, $data);

        //更新云信用户等级贵族装扮相关信息
        send_im_msg_by_system_with_lang($order['user_id'], sprintf('您已成功购买%s', $item['name']));
    } elseif ($item['type'] == 6) {
        user_bubble_add($order['user_id'], $item['item_id'], $item['days'] * $order['count']);
    } elseif ($item['type'] == 8) {
        user_tail_add($order['user_id'], $item['item_id'], $item['days'] * $order['count']);
    }
    db('shop_order')->update($order);
    UserBusiness::clear_cache($order['user_id']);
}


/**
 * 新增或累加装扮给用户
 * @param $user_id
 * @param $adornment_id
 * @param $days
 * @param $from_by 1=购买,2=系统赠送
 */
function user_adornment_add($user_id, $adornment_id, $days, $from_by = 1)
{
    if (cache('adornment:' . implode(',', [$user_id, $adornment_id])) == 1) {
        return;
    }
    $adornment = db('user_adornment')->where(['user_id' => $user_id, 'adornment_id' => $adornment_id,])->find();
    if ($adornment && $adornment['expired_days'] == -1) {
        cache('adornment:' . implode(',', [$user_id, $adornment_id]), 1, strtotime('tomorrow') - time() + random_int(30, 9999));
        return;
    }
    if ($adornment) {
        if ($days < 0) {
            if ($adornment['use_status'] == 0) {
                $adornment['expired_days'] = -1;
            } elseif ($adornment['use_status'] == 1) {
                $adornment['expired_days'] = -1;
                $adornment['expired_time'] = null;
            } elseif ($adornment['use_status'] == 2) {
                $adornment['expired_days'] = -1;
                $adornment['expired_time'] = null;
                $adornment['use_status'] = 0;
            }
        } else {
            if ($adornment['use_status'] == 0) {
                $adornment['expired_days'] += $days;
            } elseif ($adornment['use_status'] == 1) {
                $adornment['expired_days'] += $days;
                $adornment['expired_time'] = date(
                    'Y-m-d H:i:s',
                    strtotime("+{$days}day", strtotime($adornment['expired_time']))
                );
            } elseif ($adornment['use_status'] == 2) {
                $adornment['expired_days'] = $days;
                $adornment['expired_time'] = null;
                $adornment['use_status'] = 0;
            }
        }
        db('user_adornment')->update($adornment);
    } else {
        if ($days < 0) {
            $days = -1;
        }
        db('user_adornment')->insert([
            'user_id'      => $user_id,
            'adornment_id' => $adornment_id,
            'from_by'      => $from_by,
            'expired_days' => $days,
            'use_status'   => 0,
            'is_wear'      => 0,
        ]);
    }
}


/**
 * 新增或累加坐骑给用户
 * @param $user_id
 * @param $car_id
 * @param $days
 * @param $from_by 1=购买,2=系统赠送
 */
function user_car_add($user_id, $car_id, $days, $from_by = 1)
{
    $car = db('user_car')->where(['user_id' => $user_id, 'car_id' => $car_id,])->find();
    if ($car && $car['expired_days'] == -1) {
        return;
    }
    if ($car) {
        if ($days < 0) {
            if ($car['use_status'] == 0) {
                $car['expired_days'] = -1;
            } elseif ($car['use_status'] == 1) {
                $car['expired_days'] = -1;
                $car['expired_time'] = null;
            } elseif ($car['use_status'] == 2) {
                $car['expired_days'] = $days;
                $car['expired_time'] = null;
                $car['use_status'] = 0;
            }
        } else {
            if ($car['use_status'] == 0) {
                $car['expired_days'] += $days;
            } elseif ($car['use_status'] == 1) {
                $car['expired_days'] += $days;
                $car['expired_time'] = date(
                    'Y-m-d H:i:s',
                    strtotime("+{$days}day", strtotime($car['expired_time']))
                );
            } elseif ($car['use_status'] == 2) {
                $car['expired_days'] = $days;
                $car['expired_time'] = null;
                $car['use_status'] = 0;
            }
        }

        db('user_car')->update($car);
    } else {
        if ($days < 0) {
            $days = -1;
        }
        db('user_car')->insert([
            'user_id'      => $user_id,
            'car_id'       => $car_id,
            'from_by'      => $from_by,
            'expired_days' => $days,
            'use_status'   => 0,
            'is_wear'      => 0,
        ]);
    }
}


/**
 * 新增或累加聊天气泡给用户
 * @param $user_id
 * @param $bubble_id
 * @param $days
 * @param $from_by 1=购买,2=系统赠送
 */
function user_bubble_add($user_id, $bubble_id, $days, $from_by = 1)
{
    $bubble = db('user_bubble')->where(['user_id' => $user_id, 'bubble_id' => $bubble_id,])->find();
    if ($bubble && $bubble['expired_days'] == -1) {
        return;
    }
    if ($bubble) {
        if ($days < 0) {
            if ($bubble['use_status'] == 0) {
                $bubble['expired_days'] = -1;
            } elseif ($bubble['use_status'] == 1) {
                $bubble['expired_days'] = -1;
                $bubble['expired_time'] = null;
            } elseif ($bubble['use_status'] == 2) {
                $bubble['expired_days'] = $days;
                $bubble['expired_time'] = null;
                $bubble['use_status'] = 0;
            }
        } else {
            if ($bubble['use_status'] == 0) {
                $bubble['expired_days'] += $days;
            } elseif ($bubble['use_status'] == 1) {
                $bubble['expired_days'] += $days;
                $bubble['expired_time'] = date(
                    'Y-m-d H:i:s',
                    strtotime("+{$days}day", strtotime($bubble['expired_time']))
                );
            } elseif ($bubble['use_status'] == 2) {
                $bubble['expired_days'] = $days;
                $bubble['expired_time'] = null;
                $bubble['use_status'] = 0;
            }
        }

        db('user_bubble')->update($bubble);
    } else {
        if ($days < 0) {
            $days = -1;
        }
        db('user_bubble')->insert([
            'user_id'      => $user_id,
            'bubble_id'    => $bubble_id,
            'from_by'      => $from_by,
            'expired_days' => $days,
            'use_status'   => 0,
            'is_wear'      => 0,
        ]);
    }
}


/**
 * 新增或累加聊天气泡给用户
 * @param $user_id
 * @param $noble
 * @param $days
 * @param $from_by 1=购买,2=系统赠送
 */
function user_noble_add($user_id, $noble_id, $days, $from_by = 1)
{
    db('user_noble')->where(['user_id' => $user_id, 'noble_id' => ['<', $noble_id],])->delete();
    $noble = db('user_noble')->where(['user_id' => $user_id, 'noble_id' => $noble_id,])->find();
    if ($noble) {
        $noble['end_time'] = date('Y-m-d H:i:s', strtotime("+{$days}day", strtotime($noble['end_time'])));
        db('user_noble')->update($noble);
    } else {
        db('user_noble')->insert([
            'user_id'    => $user_id,
            'noble_id'   => $noble_id,
            'start_time' => datetime(),
            'end_time'   => datetime(strtotime("+$days day"))
        ]);
    }
}


/**
 * 新增或累加铭牌给用户
 * @param $user_id
 * @param $tail_id
 * @param $days
 * @param $from_by 1=购买,2=系统赠送
 */
function user_tail_add($user_id, $tail_id, $days, $from_by = 1)
{
    $tail = db('user_tail')->where(['user_id' => $user_id, 'tail_id' => $tail_id,])->find();
    if ($tail && $tail['expired_days'] == -1) {
        return;
    }
    if ($tail) {
        if ($days < 0) {
            if ($tail['use_status'] == 0) {
                $tail['expired_days'] = -1;
            } elseif ($tail['use_status'] == 1) {
                $tail['expired_days'] = -1;
                $tail['expired_time'] = null;
            } elseif ($tail['use_status'] == 2) {
                $tail['expired_days'] = $days;
                $tail['expired_time'] = null;
                $tail['use_status'] = 0;
            }
        } else {
            if ($tail['use_status'] == 0) {
                $tail['expired_days'] += $days;
            } elseif ($tail['use_status'] == 1) {
                $tail['expired_days'] += $days;
                $tail['expired_time'] = date(
                    'Y-m-d H:i:s',
                    strtotime("+{$days}day", strtotime($tail['expired_time']))
                );
            } elseif ($tail['use_status'] == 2) {
                $tail['expired_days'] = $days;
                $tail['expired_time'] = null;
                $tail['use_status'] = 0;
            }
        }

        db('user_tail')->update($tail);
    } else {
        if ($days < 0) {
            $days = -1;
        }
        db('user_tail')->insert([
            'user_id'      => $user_id,
            'tail_id'      => $tail_id,
            'from_by'      => $from_by,
            'expired_days' => $days,
            'use_status'   => 0,
            'is_wear'      => 0,
        ]);
    }
}


function get_new_expiry_days($expiry_date)
{
    $time = strtotime($expiry_date);
    //计算两个日期之间的时间差
    $diff = $time - time();
    $obj = new stdClass();
    if ($diff < 0) {
        $obj->days = 0;
        $obj->hours = 0;
        return $obj;
    }
    //转换时间差的格式
    if ($diff / (60 * 60 * 24) < 1) {
        $obj->days = floor($diff / (60 * 60 * 24));
    } else {
        $obj->days = ceil($diff / (60 * 60 * 24));
    }
    $obj->hours = ceil(($diff % (60 * 60 * 24)) / 3600);
    return $obj;
}
