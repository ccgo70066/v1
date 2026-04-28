<?php

namespace app\admin\command;

use addons\apilog\model\Apilog;
use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\library\Yunxin;
use app\common\model\UserBusiness;
use app\common\service\ImService;
use app\common\service\RedisService;
use app\common\service\RoomService;
use fast\Http;
use Redis;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Env;
use think\Exception;
use think\Log;

use function cache;

class Cron extends Command
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('cron')
            ->addOption('type', 't', Option::VALUE_REQUIRED, '', null)
            ->addOption('args', 'a', Option::VALUE_REQUIRED, '', null)
            ->setDescription('cron tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        $option = $input->getOptions();
        if (method_exists($this, $option['type'])) {
            call_user_func([$this, $option['type']], $input, $output);
        } else {
            $output->writeln('method not found');
        }
    }

    /**
     * 热力值小时刷新,同步到MYSql
     * (0 * * * *)
     */
    public function hot()
    {
        traceInDB('热力值小时刷新,同步到MYSql');
        traceInDB(datetime());
        RoomService::instance()->hot_in_mysql();
        echo 'success';
    }

    /**
     * mysql数据清理
     * @return void
     */
    public function mysql_clear()
    {
        db('egg_limit_log')->where('id', 'not in', db('egg_limit_log')->order('id desc')->limit(200)->column('id'))->delete();
        db('wheel_limit_log')->where('id', 'not in', db('wheel_limit_log')->order('id desc')->limit(200)->column('id'))->delete();
        db('gift_box_limit_log')->where('id', 'not in', db('gift_box_limit_log')->order('id desc')->limit(200)->column('id'))->delete();
    }

    /**
     * 道具到期(0 * * * *)
     */
    public function item_expire()
    {
        $adornment = db('user_adornment ua')
            ->join('adornment a', 'ua.adornment_id = a.id')
            ->where('ua.expired_time', '<', date('Y-m-d H:i:s'))
            ->where('ua.use_status', '<>', 2)
            ->fieldRaw('ua.user_id,ua.id,ua.expired_time,ua.expired_days as days,a.name,"adornment" as item')
            ->select();

        $car = db('user_car ua')
            ->join('car a', 'ua.car_id = a.id')
            ->where('ua.expired_time', '<', date('Y-m-d H:i:s'))
            ->where('ua.use_status', '<>', 2)
            ->fieldRaw('ua.user_id,ua.id,ua.expired_time,ua.expired_days as days,a.name,"car" as item')
            ->select();

        $bubble = db('user_bubble ua')
            ->join('bubble a', 'ua.bubble_id = a.id')
            ->where('ua.expired_time', '<', date('Y-m-d H:i:s'))
            ->where('ua.use_status', '<>', 2)
            ->fieldRaw('ua.user_id,ua.id,ua.expired_time,ua.expired_days as days,a.name,"bubble" as item')
            ->select();

        $arr = array_merge($adornment, $bubble, $car);

        if (!$arr) {
            echo 'success,data is null';
            return;
        }
        foreach ($arr as $k => $v) {
            db('user_' . $v['item'])->where('id', $v['id'])->setField(['use_status' => 2, 'is_wear' => 0]);
            UserBusiness::clear_cache($v['user_id']);
            board_notice(Message::CMD_REFRESH_USER, ['user_id' => $v['user_id']]);
            send_im_msg_by_system($v['user_id'], "您的{$v['name']}已过期");
        }
        echo 'success';
    }

    //封禁用户到时解封
    public function remove_blacklist()
    {
        $sel = db('blacklist')->where('end_time', '<', datetime())->select();
        if (!$sel) {
            echo 'success,data is null';
            return;
        }
        foreach ($sel as $k => $v) {
            db('blacklist')->where('id', $v['id'])->delete();
            if ($v['type'] == 1) {
                user_unblacklist_after($v['number']);
            }
            if ($v['type'] == 2) {
                // 解封此设备注册的其它用户
                $other_user = db('user')->where(['imei' => $v['number']])->column('id');
                foreach ($other_user as $other) {
                    user_unblacklist_after($other);
                }
            }
        }
        echo 'success';
    }


    //同步云信在线用户
    public function update_room_user()
    {
        $sel = db('room')->where('status', 'in', '2,3')->where('type', 1)->field('id,im_roomid')->select();
        $im = new ImService();
        $redis = redis();
        try {
            foreach ($sel as $k => $v) {
                $res_1 = $im->membersByPage($v['im_roomid'], 1, '0');
                Log::error('在线人数同步返回值111');
                Log::error($res_1);
                if ($res_1['code'] == 200) {
                    $id_arr_1 = $res_1['desc']['data'];
                    $id_arr_1 = array_column($id_arr_1, 'accid');
                } else {
                    Log::error('在线人数同步返回值');
                    Log::error($res_1);
                }

                $res_2 = $im->membersByPage($v['im_roomid'], 2, '0');
                if ($res_2['code'] == 200) {
                    $id_arr_2 = $res_2['desc']['data'];
                    $id_arr_2 = array_column($id_arr_2, 'accid');
                }
                if ($res_1['code'] != 200 || $res_2['code'] != 200) {
                    Log::error(json_encode($res_1) . '和' . json_encode($res_2));
                    return;
                }
                //云信查询出的用户数据
                $id_arr = array_merge($id_arr_1, $id_arr_2); //云信数据
                $redis_z_id = $redis->zRevRange(RedisService::ROOM_USER_KEY_PRE . $v['id'], 0, -1); //redis有序集合
                foreach ($id_arr as $val) {
                    if (!in_array($val, $redis_z_id)) {
                        $redis->zAdd(RedisService::ROOM_USER_KEY_PRE . $v['id'], time(), $val);
                        $redis->hSet(RedisService::USER_NOW_ROOM_KEY, $val, $v['id']);
                    }
                }
                foreach ($redis_z_id as $z) {
                    if (!in_array($z, $id_arr)) {
                        $redis->zRem(RedisService::ROOM_USER_KEY_PRE . $v['id'], $z);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log_out($e);
            Log::error($e->getMessage());
        }
        echo 200;
    }

    //游戏的个人盘管理统计
    public function update_user_index()
    {
        db('wheel_user_index')->where(['user_id' => ['<>', 0]])->setField(['today_used' => 0, 'today_lucre' => 0]);
        db('egg_user_index')->where(['user_id' => ['<>', 0]])->setField(['today_used' => 0, 'today_lucre' => 0]);
        $wheel_data = db('wheel_intact_log')->whereTime('create_time', 'd')->group("user_id,box_type")
            ->field('user_id,sum(used_amount) as today_used,sum(amount) as today_lucre')->select();
        foreach ($wheel_data as $wheel_datum) {
            db('wheel_user_index')->where([
                'user_id'  => $wheel_datum['user_id'],
                'box_type' => $wheel_datum['box_type']
            ])->setField($wheel_datum);
        }
        $egg_data = db('egg_intact_log')->whereTime('create_time', 'd')->group("user_id,box_type")
            ->field('user_id,sum(use_amount) as today_used,sum(amount) as today_lucre')->select();
        foreach ($egg_data as $egg_datum) {
            db('egg_user_index')->where(['user_id' => $egg_datum['user_id'], 'box_type' => $egg_datum['box_type']])->setField($egg_datum);
        }

        print_r('success');
    }


    /**
     * 定时刷新奖池过期时间
     * @return void
     */
    public function refresh_pool_expire()
    {
        $redis = redis();
        $arr = [
            'pool:egg:pub_1',
            'pool:egg:pub_2',
            'pool:egg:pubn_1',
            'pool:egg:pubn_2',
            'pool:egg:sys_1',
            'pool:egg:sys_2',
            'pool:wheel:sys_1',
            'pool:wheel:sys_2',
            'pool:wheel:pub_1',
            'pool:wheel:pub_2',
        ];

        foreach ($arr as $item) {
            $redis->persist($item);
        }

        $apilogM = new Apilog();
        $apilogM->where('createtime', '<', strtotime('-3 day'))->delete();
    }

    /**
     * 数据清理
     * @return void
     */
    public function del_mysql_data()
    {
        $result = 0;

        $date_2week = datetime(strtotime('-14 day'), 'Y-m-d 00:00:00');
        $date_1month = datetime(strtotime('-1 month'), 'Y-m-d 00:00:00');
        $date_2month = datetime(strtotime('-2 month'), 'Y-m-d 00:00:00');
        $limit = 8000000;
        $last_id = db('gift_log')->where('create_time', '<', $date_2week)->order('id desc')->value('id');
        $result += db('gift_log')->where('id', '<', $last_id)->limit($limit)->delete();
        $result += db('gift_box_log')->where('create_time', '<=', $date_2week)->limit($limit)->delete();
        $result += db('gift_box_intact_log')->where('create_time', '<=', $date_2week)->limit($limit)->delete();
        $result += db('wheel_intact_log')->where('create_time', '<=', $date_2week)->limit($limit)->delete();
        $result += db('egg_limit_log')->where('create_time', '<=', strtotime($date_2week))->limit($limit)->delete();
        $result += db('egg_limit_log_v2')->where('create_time', '<=', strtotime($date_2week))->limit($limit)->delete();
        $result += db('egg_intact_log')->where('create_time', '<=', $date_2week)->limit($limit)->delete();
        $result += db('user_business_log')->where('create_time', '<=', $date_2month)->limit($limit)->delete();
        $result += db('user_recharge')->where('create_time', '<=', $date_2month)->limit($limit)->delete();
        $result += db('user_withdraw')->where('create_time', '<=', $date_1month)->where('status', 'in', [2, -1])->limit($limit)->delete();
        $result += db('red_packet_log')->where('create_time', '<=', $date_2week)->limit($limit)->delete();
        $result += Db::connect('mongodb')->table('aa_chat_log')->where('create_time', '<', strtotime($date_1month))->limit($limit)->delete();
        $result += Db::connect('mongodb')->table('aa_apilog')->where('createtime', '<', strtotime($date_2week))->limit($limit)->delete();
        $result += Db::connect('mongodb')->table('aa_egg_log')->where('create_time', '<', strtotime($date_2week))->limit($limit)->delete();
        $result += Db::connect('mongodb')->table('aa_wheel_log')->where('create_time', '<', strtotime($date_2week))->limit($limit)->delete();
        $result += db('union_reward')->where('update_time', '<', $date_1month)->where('status', 'in', [3, 4, 5])->delete();
        $result += db('union_withdraw')->where('create_time', '<', $date_1month)->where('status', 'in', [2, 3])->delete();
        echo $result;
    }

    public function del_mysql_data_fix()
    {
        $date_2week = datetime(strtotime('-14 day'), 'Y-m-d 00:00:00');
        $result = 0;
        $result += Db::connect('mongodb')->table('aa_wheel_log')->where(
            'create_time',
            '<',
            strtotime($date_2week)
        )->limit(5000000)->delete();
        echo $result;
    }

    /** 产值统计 12点/24点执行 */
    public function statistic_value()
    {
        $hour = date('G');
        if (!in_array($hour, [0, 12])) {
            //return;
        }
        $date = $hour == 0 ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
        $hour == 0 && $hour = 24;
        db()->execute("call statistic_value('$date', '$hour')");

        echo 'success';
    }

}

