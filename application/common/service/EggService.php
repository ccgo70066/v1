<?php

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\library\Auth;
use app\common\library\ChinaName;
use app\common\model\AnchorRecommend as AnchorRecommendModel;
use app\common\model\Gift as GiftModel;
use app\common\model\User;
use app\common\model\UserBlacklist;
use app\common\model\UserBusiness;
use fast\Http;
use fast\Random;
use think\Cache;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Env;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Model;
use util\Util;

/**
 * 游戏一
 */
class EggService extends BaseService
{

    /**
     * @param $user_id
     * @param $box_type
     * @param $count
     * @param $room_id
     * @return array|mixed
     * @throws
     */
    public static function open($user_id, $box_type, $count, $room_id)
    {
        $price = get_site_config("egg_box{$box_type}_price");
        $limit_price = get_site_config('egg_log_limit');

        try {
            $later = user_business_change($user_id, 'amount', $count * $price, 'decrease', '兑换游戏券', 9);
        } catch (\Exception $e) {
            throw new ApiException(__('Your game coupons are insufficient, please exchange'), 405);
        }
        $room_id && redis()->hIncrBy('room_egg', $room_id, $count * $price); // 房间统计

        $gift = self::get_gift($box_type);
        if (!$gift) throw new ApiException(__('Network request exception, please retry'));

        $index = self::get_user_index($user_id, $box_type);
        $index['current_room_id'] = $room_id;
        $user_info = self::get_user_info($user_id);
        $sys_config = self::get_sys_config($index, $count);

        $limit_log = [];
        $time = time();
        $log_data = [];

        for ($i = 0; $i < $count; $i++) {
            $weigh = (1 == $sys_config['status'] && $i == $count - 1) ? $sys_config : self::get_weigh($index, $count);
            $lottery_gift_id = self::lottery($weigh, $index);
            $pool_info = $count == 1 ? [] : self::process_pool($weigh, $index, $gift[$lottery_gift_id]['price'], $count);
            $data = [
                'user_id'     => $user_id,
                'box_type'    => $box_type,
                'count_type'  => $count,
                'gift_id'     => (int)$lottery_gift_id,
                'count'       => 1,
                'room_id'     => $room_id,
                'create_time' => $time,
                'update_time' => $time
            ];
            if ($gift[$lottery_gift_id]['price'] > $limit_price) {
                $limit_log[] = $data;
            }
            $log_data[] = array_merge($data, [
                'used_amount' => (int)$price,
                'weigh_name'  => $weigh['title'],
                'jump_status' => $weigh['jump_status'],
                'level_id'    => $index['level_id'],
                'box_index'   => $count == 1 ? 0 : ($index["sys_{$count}_count"] + 1),
                'ip'          => $user_info['loginip'],
                'ip_count'    => $user_info['ip_count'],
                'imei'        => $user_info['imei'],
                'imei_count'  => $user_info['imei_count'],
                'gift_value'  => (int)($gift[$lottery_gift_id]['price'])
            ], $pool_info);
            $gift[$lottery_gift_id]['count']++;
            if ($count != 1) {
                $index['pool'] = bcadd($index['pool'], $pool_info['pool_per_diff'] ?? 0);
                $index['count']++;
                $index["sys_{$count}_count"]++;
            }
            $index['total_used'] += $price;
            $index['today_used'] += $price;
            $index['total_lucre'] += $gift[$lottery_gift_id]['price'];
            $index['today_lucre'] += $gift[$lottery_gift_id]['price'];
        }
        $count == 100 && $index['level_info']['upgrade_gift_ids'] != '' && $index['sys_100_hammer']++;
        db('egg_user_index')->update(Util::array_index_filter($index, 'level_info', true));
        [$gift, $intact_log_id] = self::intact_log($gift, $index, $count, $user_info);
        [$gift, $limit_log_v2, $group_flag] = self::process_gift($gift, $index, $count, $room_id, $intact_log_id);
        // db('egg_log')->insertAll($log_data);//删除
        $total_value = 0;
        foreach ($gift as $item) {
            $total_value += $item['price'] * $item['count'];
        }
        MongoService::dataStore([
            'user_id'     => (int)$user_id,
            'box_type'    => (int)$box_type,
            'count_type'  => (int)$count,
            'used_amount' => (int)$count * (int)$price,
            'total_value' => (int)$total_value,
            'room_id'     => (int)$room_id,
            'level_id'    => (int)$index['level_id'],
            'ip'          => $user_info['loginip'],
            'ip_count'    => (int)($user_info['ip_count']),
            'imei'        => $user_info['imei'],
            'imei_count'  => (int)($user_info['imei_count']),
            'create_time' => $time,
            'log'         => array_reverse(array_columns($log_data, ['weigh_name', 'jump_status', 'pool_sys_before', 'pool_sys_after', 'pool_sys_diff', 'pool_pub_after', 'pool_pub_before', 'pool_pub_diff', 'pool_pubn_after', 'pool_pubn_before', 'pool_pubn_diff', 'pool_per_after', 'pool_per_before', 'pool_per_diff', 'box_index', 'level_id', 'gift_id', 'gift_value', 'used_amount']))
        ]);
        $limit_log && db('egg_limit_log')->insertAll($limit_log); //todo 2023-09-23 后删除
        $limit_log_v2 && db('egg_limit_log_v2')->insertAll($limit_log_v2);
        $result = ['amount' => $later, 'id' => $intact_log_id, 'group_flag' => $group_flag];
        $result['result'] = array_map(static function ($item) {
            unset($item['broadcast'], $item['room_notice'], $item['last_status'], $item['last_time']);
            return $item;
        }, array_values($gift));
        return $result;
    }

    /**
     * 雷霆一击
     * @param $user_id
     * @param $box_type
     * @param $count
     * @param $room_id
     * @return array|mixed
     * @throws
     */
    public static function open_free($intact_log_id, $user_id, $box_type, $count, $room_id)
    {
        $group = db('egg_group')->cache('egg:group:' . $box_type, 0, 'small_data_egg')
            ->where(['box_type' => $box_type, 'status' => 1])->find();
        $group = explode(',', $group['gift_ids']);

        $price = 0;
        $limit_price = get_site_config('egg_log_limit');
        $room_id && redis()->hIncrBy('room_egg', $room_id, $count * $price); // 房间统计

        $gift = self::get_gift($box_type);
        if (!$gift) {
            throw new ApiException(__('Network request exception, please retry'));
        }
        $index = self::get_user_index($user_id, $box_type);
        $index['current_room_id'] = $room_id;
        $user_info = self::get_user_info($user_id);
        $sys_config = self::get_sys_config($index, $count);

        $limit_log = [];
        $time = time();
        $log_data = [];

        $rs = [];

        for ($i = 0; $i < $count; $i++) {
            $weigh = (1 == $sys_config['status'] && $i == $count - 1) ? $sys_config : self::get_weigh($index, $count);
            $lottery_gift_id = self::lottery_without_group($weigh, $index, $group);
            $rs[$lottery_gift_id] = ($rs[$lottery_gift_id] ?? 0) + 1;
            $pool_info = $count == 1 ? [] : self::process_pool($weigh, $index, $gift[$lottery_gift_id]['price'], $count, 1);
            $data = [
                'user_id'     => $user_id,
                'box_type'    => $box_type,
                'count_type'  => $count,
                'gift_id'     => (int)$lottery_gift_id,
                'count'       => 1,
                'room_id'     => $room_id,
                'create_time' => $time,
                'update_time' => $time
            ];
            if ($gift[$lottery_gift_id]['price'] > $limit_price) {
                $limit_log[] = $data;
            }
            $log_data[] = array_merge($data, [
                'used_amount' => (int)$price,
                'weigh_name'  => $weigh['title'],
                'jump_status' => $weigh['jump_status'],
                'level_id'    => $index['level_id'],
                'box_index'   => $count == 1 ? 0 : ($index["sys_{$count}_count"] + 1),
                'ip'          => $user_info['loginip'],
                'ip_count'    => $user_info['ip_count'],
                'imei'        => $user_info['imei'],
                'imei_count'  => $user_info['imei_count'],
                'gift_value'  => (int)($gift[$lottery_gift_id]['price'])
            ], $pool_info);
            $gift[$lottery_gift_id]['count']++;
            if ($count != 1) {
                $index['pool'] = bcadd($index['pool'], $pool_info['pool_per_diff'] ?? 0);
                $index['count']++;
                $index["sys_{$count}_count"]++;
            }
            $index['total_used'] += $price;
            $index['today_used'] += $price;
            $index['total_lucre'] += $gift[$lottery_gift_id]['price'];
            $index['today_lucre'] += $gift[$lottery_gift_id]['price'];
        }
        $count == 100 && $index['level_info']['upgrade_gift_ids'] != '' && $index['sys_100_hammer']++;
        db('egg_user_index')->update(Util::array_index_filter($index, 'level_info', true));
        $gift = self::intact_log_append($intact_log_id, $gift, $index, $count, $user_info, 1);
        $total_value = 0;
        foreach ($gift as $item) {
            $total_value += $item['price'] * $item['count'];
        }
        MongoService::dataStore([
            'user_id'     => (int)$user_id,
            'box_type'    => (int)$box_type,
            'count_type'  => (int)$count,
            'used_amount' => (int)$count * (int)$price,
            'total_value' => (int)$total_value,
            'room_id'     => (int)$room_id,
            'level_id'    => (int)$index['level_id'],
            'ip'          => $user_info['loginip'],
            'ip_count'    => (int)($user_info['ip_count']),
            'imei'        => $user_info['imei'],
            'imei_count'  => (int)($user_info['imei_count']),
            'create_time' => $time,
            'log'         => array_reverse(
                array_columns(
                    $log_data,
                    [
                        'weigh_name',
                        'jump_status',
                        'pool_sys_before',
                        'pool_sys_after',
                        'pool_sys_diff',
                        'pool_pub_after',
                        'pool_pub_before',
                        'pool_pub_diff',
                        'pool_pubn_after',
                        'pool_pubn_before',
                        'pool_pubn_diff',
                        'pool_per_after',
                        'pool_per_before',
                        'pool_per_diff',
                        'box_index',
                        'level_id',
                        'gift_id',
                        'gift_value',
                        'used_amount'
                    ]
                )
            )
        ]);
        MongoService::dataInsert('aa_egg_log');
    }

    public static function lottery_without_group($weigh, $index, &$group)
    {
        $lottery_gift_id = self::lottery($weigh, $index);
        if ($group) {
            if (count($group) == 1) {
                while (array_search($lottery_gift_id, $group)) {
                    $lottery_gift_id = self::lottery($weigh, $index);
                }
            } else {
                unset($group[array_search($lottery_gift_id, $group)]);
            }
        }

        return $lottery_gift_id;
    }

    /**
     * 获取用户信息[余额,背包金额]
     * @param $user_id
     * @return array
     * @throws
     */
    public static function get_user_bag_info($user_id): array
    {
        $amount = db('user_business')->where('id', $user_id)->field('amount')->find();
        $bag_amount = db('user_bag b')
            ->join('gift g', 'b.gift_id=g.id', 'left')
            ->where(['user_id' => $user_id, 'b.count' => ['gt', 0]])
            ->field('ifnull(sum(g.price * b.count), 0) as amount')
            ->find();

        return ['amount' => $amount['amount'], 'bag_amount' => $bag_amount['amount']];
    }


    /**
     * 获取奖池礼物
     * @param $box_type
     * @return mixed
     */
    public static function get_gift($box_type)
    {
        return Cache::remember("egg:gift:list_{$box_type}", function () use ($box_type) {
            Cache::tag('small_data_egg', "egg:gift:list_{$box_type}");
            $gift = db('egg_gift e')
                ->join('gift g', 'e.gift_id=g.id', 'left')
                ->where(['e.box_type' => $box_type, 'e.status' => 1])
                ->order('g.price asc')
                ->column('e.*,g.name,g.image,g.price,"0" as count', 'gift_id');
            foreach ($gift as &$item) {
                unset($item['id'], $item['box_type'], $item['weigh'], $item['status']);
            }
            return $gift;
        }, 15);
    }

    /**
     * 获取用户个人游戏记数记录
     * @param $user_id
     * @param $box_type
     * @return array|bool|\PDOStatement|string|Model
     * @throws
     */
    public static function get_user_index($user_id, $box_type)
    {
        $where_data = ['user_id' => $user_id, 'box_type' => $box_type];
        $index = db('egg_user_index')->where($where_data)->find();
        if (!$index) {
            $pool_default = 100000; // 默认
            $default_level = db('egg_level')->where('box_type', $box_type)->order('weigh asc')->find();
            if (!$default_level) {
                throw new Exception('配置出错');
            }
            $index = array_merge($where_data, [
                'pool'           => $pool_default,
                'level_id'       => $default_level['id'],
                'count'          => 0,
                'sys_10_count'   => 0,
                'sys_100_count'  => 0,
                'sys_100_hammer' => 1,
                'total_used'     => 0,
                'total_lucre'    => 0,
                'today_used'     => 0,
                'today_lucre'    => 0,
            ]);
            $index['id'] = db('egg_user_index')->insertGetId($index);
        }
        $index['level_info'] = db('egg_level')->cache('egg:level_data:' . $index['level_id'], 0, 'small_data_egg')
            ->find($index['level_id']);
        return $index;
    }

    /**
     * 获取用户ip,imei信息
     * @param $user_id
     * @return mixed
     */
    private static function get_user_info($user_id)
    {
        return Cache::remember('egg:user_index1:' . $user_id, function () use ($user_id) {
            Cache::tag('small_data_egg', 'egg:user_index1:' . $user_id);
            $user_sub = db('user')->where('id', $user_id)->field('joinip,loginip,imei')->find();
            $user_sub['ip_count'] = db('user')->where('loginip', $user_sub['loginip'])->count();
            $user_sub['imei_count'] = db('user')->where('imei', $user_sub['imei'])->count();
            return $user_sub;
        }, 60 * 60);
    }

    /**
     * 获取系统调控
     * @param $index
     * @param $count
     * @return int[]
     * @throws
     */
    private static function get_sys_config($index, $count)
    {
        $level = $index['level_info'];
        $jump_data = explode(',', $level['jump_data']);
        $box_type = $index['box_type'];
        $config_where = ['box_type' => $box_type, 'level_id' => $level['id'], 'status' => 1,];
        $weigh = ['status' => 0];

        if ($count == 10) {
            return $weigh;
        }

        // 系统调控
        if (!in_array(1, $jump_data) && $index['sys_100_hammer'] > 0) {
            $pool = cache('pool:egg:sys_' . $box_type) / 100;
            $config = db('egg_config_sys')->where($config_where)->where([
                'count_type'  => $count,
                'range_start' => [['elt', $pool], ['exp', Db::raw('is null')], 'or'],
                'range_end'   => [['gt', $pool], ['exp', Db::raw('is null')], 'or'],
            ])->find();
            if ($config) {
                $enter_json = json_decode($config['enter_json'], true);
                !isset($enter_json[$index['sys_100_hammer']]) && $index['sys_100_hammer'] = 1;
                $percent = $enter_json[$index['sys_100_hammer']];
                $weigh['status'] = self::roll($percent) ? 1 : 0;
                if ($weigh['status']) {
                    $weigh['jump_status'] = 3;
                    $weigh['weigh_type'] = 1;
                    $weigh['title'] = $config['title'];
                    $weigh['config'] = $config['config'];
                }
            }
        }

        return $weigh;
    }

    /**
     * 根据比率计算是否进入,   例如: $percent=90.5,  去掉小数点905 则最大的随机数为 100*905/90.5=1000,
     * 获得1~1000的随机数, 小于$percent则返回true, 大于$percent则返回false
     * true 进入, false 跳过进入
     * @param float $percent 百分比  0~100
     * @return bool|mixed
     */
    public static function roll($percent)
    {
        if ($percent == 0 || $percent == null) {
            return false;
        }
        $int_percent = (int)str_replace('.', '', (float)$percent);
        $divisor = 100 * $int_percent / $percent;
        return random_int(1, $divisor) <= $int_percent;
    }


    /**
     * 获取权重
     * 顺序: 系统调控 > 基础调控 > [初始/个人]调控 > 容灾
     * @param $index
     * @param $count
     * @return array
     * @throws
     */
    private static function get_weigh($index, $count)
    {
        // weigh_type:1=系统调控,2=基础调控,3=初始调控,4=个人调控,5=容灾调控,6=新手初始调控,10=回血调控,11=单抽
        $box_type = $index['box_type'];
        $level = $index['level_info'];
        $weigh['jump_status'] = 0;
        $config_where = ['box_type' => $box_type, 'level_id' => $level['id'], 'status' => 1,];
        // 单抽
        if ($count == 1) {
            $config = db('egg_config_single')->cache("egg:config:single_$box_type", 0, 'small_data_egg')->where(['box_type' => $box_type])->find();
            if ($config) {
                $weigh['weigh_type'] = 11;
                $weigh['title'] = $config['title'];
                $weigh['config'] = $config['config'];
                return $weigh;
            }
            throw new Exception(__('Operation failed') . '2');
        }
        // 回血调控
        if ($box_type == 1 && in_array($index['count'] + 1, explode(',', get_site_config('egg_back_threshold')))) {
            $pool = $index['pool'];
            $config = db('egg_config_back')->where([
                'box_type'    => $box_type,
                'status'      => 1,
                'range_start' => [['elt', $pool], ['exp', Db::raw('is null')], 'or'],
                'range_end'   => [['gt', $pool], ['exp', Db::raw('is null')], 'or'],
            ])->find();
            if ($config) {
                $weigh['weigh_type'] = 10;
                $weigh['title'] = $config['title'];
                $weigh['config'] = $config['config'];
                return $weigh;
            }
        }

        $jump_data = explode(',', $level['jump_data']);
        $threshold_count = $index["sys_{$count}_count"];
        $threshold_count++;
        // 基础调控
        if (!in_array(2, $jump_data)) {
            // 单人调控10次就是在第10次处理不是符合10次再处理. @done (21-08-07 22:41)
            // $index['count']++;
            $configs = db('egg_config_base')
                ->cache('egg:config:base_' . implode('_', [$box_type, $level['id'], $count]), 0, 'small_data_egg')
                ->where($config_where)->where(['count_type' => $count,])->order('count desc')->select();
            foreach ($configs as $config) {
                if ($threshold_count % $config['count'] == 0) {
                    if (!self::roll($config['ignore_1th'])) {
                        // if (!self::roll($config['ignore_2th'])) {
                        $weigh['jump_status'] = 6;
                        $weigh['weigh_type'] = 2;
                        $weigh['title'] = $config['title'];
                        $weigh['config'] = $config['config'];
                        return $weigh;
                        // }
                        // $weigh['jump_status'] = 5;
                    } else {
                        $weigh['jump_status'] = 4;
                    }
                    break;
                }
            }
        }
        // 初始调控
        if ($level['or_data'] == 1 && !in_array(3, $jump_data)) {
            $pool = cache('pool:egg:pub_' . $box_type) / 100;
            $config = db('egg_config_pub')->where($config_where)->where([
                'count_type'  => $count,
                'range_start' => [['elt', $pool], ['exp', Db::raw('is null')], 'or'],
                'range_end'   => [['gt', $pool], ['exp', Db::raw('is null')], 'or'],
            ])->field('title,config')->find();
            if ($config) {
                $weigh['weigh_type'] = 3;
                return array_merge($config, $weigh);
            }
        }
        // 新手初始调控
        if ($level['or_data'] == 3) {
            $pool = cache('pool:egg:pubn_' . $box_type) / 100;
            $config = db('egg_config_pubn')->where($config_where)->where([
                'count_type'  => $count,
                'range_start' => [['elt', $pool], ['exp', Db::raw('is null')], 'or'],
                'range_end'   => [['gt', $pool], ['exp', Db::raw('is null')], 'or'],
            ])->field('title,config')->find();
            if ($config) {
                $weigh['weigh_type'] = 6;
                return array_merge($config, $weigh);
            }
        }
        // 个人调控
        if (!in_array(4, $jump_data)) {
            $pool = $index['pool'];
            $config = db('egg_config_per')->where($config_where)->where([
                'count_type'  => $count,
                'range_start' => [['elt', $pool], ['exp', Db::raw('is null')], 'or'],
                'range_end'   => [['gt', $pool], ['exp', Db::raw('is null')], 'or'],
            ])->field('title,config')->find();
            if ($config) {
                $weigh['weigh_type'] = 4;
                return array_merge($config, $weigh);
            }
        }
        // 容灾
        $last_config = db('egg_config_def')->field('title,config,"5" as weigh_type')
            ->where(['box_type' => $box_type, 'status' => 1])->find();
        if (!$last_config) {
            Log::error('火之预言:无容灾配置');
            throw new Exception(__('Operation failed') . '9');
        }
        return array_merge($last_config, $weigh);
    }


    /**
     * 获取抽中礼物的id
     * @param $weigh
     * @return int|string
     */
    public static function lottery($weigh, $index)
    {
        $key = 'egg:lottery:' . implode('_', [$weigh['weigh_type'], $index['level_info']['id'], $weigh['title']]);
        $arr = Cache::remember($key, function () use ($weigh, $key) {
            Cache::tag('small_data_egg', $key);
            $gift_weigh = json_decode($weigh['config'], true);
            $diff_multiple = 1;
            foreach ($gift_weigh as $item) {
                if (strpos($item['weight'], '.') !== false) {
                    $min = $item['weight'];
                    $min_int = (int)str_replace('.', '', $min);
                    $diff_temp = $min_int / $min;
                    $diff_multiple = $diff_temp > $diff_multiple ? $diff_temp : $diff_multiple;
                }
            }
            $arr = [];
            foreach ($gift_weigh as $item) {
                $item['weight'] > 0 && $arr[$item['id']] = (int)($item['weight'] * $diff_multiple);
            }
            return $arr;
        });

        return Util::get_rand($arr);
    }

    /**
     * 奖池变更
     * @param array $weigh
     * @param array $index
     * @param       $gift_price
     * @param       $count
     * @return array
     */
    private static function process_pool(array $weigh, array $index, $gift_price, $count, $buffer = 1)
    {
        // weigh_type:1=系统调控,2=基础调控,3=初始调控,4=个人调控,5=容灾调控,6=新手初始调控,7=手动补件,10=回血调控,11=单抽
        // 奖池变更增加:  跟据当前分级的的奖池增长比率进行加分
        // 奖池变更减少:
        //     系统调控: 系统盘、个人盘减分
        //     手动补件: 系统盘、个人盘减分
        //     基础调控: 对应小公池盘（新手/老手）、个人盘减分
        //     初始调控: 老人公池盘、个人盘减分
        //     新手初始调控: 新手公池盘、个人盘减分
        //     个人调控: 个人盘减分
        //     容灾调控: 个人盘减分
        //     回血调控: 按用户当前分级比率进行系统盘、老手公池盘、个人盘减分
        // 演员用户 不对新手公池和老手公池加减分. 只针对娱乐厅生效
        // 10连不进系统调控,系统盘不加减分
        // 10连老手公池增长比率需要加上系统大盘增长比率
        // 单抽和十连的次数不加入公层次数，只使用100连次数做分层，默认用户是一层

        bcscale(2);
        $gift_price *= $buffer;
        $box_type = $index['box_type'];
        $price = get_site_config("egg_box{$box_type}_price");
        $level = $index['level_info'];
        $pool_sys_percent = $level['pool_sys_percent'];
        $pool_pub_percent = $level['pool_pub_percent'];
        $count == 10 && $pool_pub_percent += $pool_sys_percent;
        $pool_pubn_percent = $level['pool_pubn_percent'];
        $pool_per_percent = $level['pool_per_percent'];
        $sys_gift_percent = 1;
        $pub_gift_percent = 1;
        $per_gift_percent = 1;
        if ($weigh['weigh_type'] == 10) {
            $sys_gift_percent = get_site_config('egg_back_sys_percent');
            $pub_gift_percent = get_site_config('egg_back_pub_percent');
            $per_gift_percent = get_site_config('egg_back_per_percent');
        }
        $pool_sys_diff = $count == 10 ? 0 : bcdiv(bcmul($price, $pool_sys_percent), 100);
        $pool_pub_diff = bcdiv(bcmul($price, $pool_pub_percent), 100);
        $pool_pubn_diff = bcdiv(bcmul($price, $pool_pubn_percent), 100);
        $pool_per_diff = bcdiv(bcmul($price, $pool_per_percent), 100);

        in_array($weigh['weigh_type'], [1, 7, 10]) && $pool_sys_diff = bcsub(
            $pool_sys_diff,
            bcmul($gift_price, $sys_gift_percent)
        );


        if (in_array($level['or_data'], [1, 3])) {
            if ($weigh['weigh_type'] == 2 && $level['or_data'] == 1) {
                $pool_pub_diff = bcsub($pool_pub_diff, bcmul($gift_price, $pub_gift_percent));
            }
            if ($weigh['weigh_type'] == 2 && $level['or_data'] == 3) {
                $pool_pubn_diff = bcsub($pool_pubn_diff, bcmul($gift_price, $pub_gift_percent));
            }
            if (in_array($weigh['weigh_type'], [3, 10])) {
                $pool_pub_diff = bcsub($pool_pub_diff, bcmul($gift_price, $pub_gift_percent));
            }
            if ($weigh['weigh_type'] == 6) {
                $pool_pubn_diff = bcsub($pool_pubn_diff, bcmul($gift_price, $pub_gift_percent));
            }
        }
        $pool_per_diff = bcsub($pool_per_diff, bcmul($gift_price, $per_gift_percent));

        $pool_sys_key = 'pool:egg:sys_' . $box_type;
        $pool_pub_key = 'pool:egg:pub_' . $box_type;
        $pool_pubn_key = 'pool:egg:pubn_' . $box_type;
        $pool_sys_before = bcdiv(cache($pool_sys_key), 100);
        Cache::inc($pool_sys_key, bcmul($pool_sys_diff, 100));
        $pool_pub_before = bcdiv(cache($pool_pub_key), 100);
        Cache::inc($pool_pub_key, bcmul($pool_pub_diff, 100));
        $pool_pubn_before = bcdiv(cache($pool_pubn_key), 100);
        Cache::inc($pool_pubn_key, bcmul($pool_pubn_diff, 100));
        $pool_per_before = $index['pool'];

        return [
            'pool_sys_before'  => $pool_sys_before,
            'pool_sys_after'   => bcadd($pool_sys_before, $pool_sys_diff),
            'pool_sys_diff'    => $pool_sys_diff,
            'pool_pub_before'  => $pool_pub_before,
            'pool_pub_after'   => bcadd($pool_pub_before, $pool_pub_diff),
            'pool_pub_diff'    => $pool_pub_diff,
            'pool_pubn_before' => $pool_pubn_before,
            'pool_pubn_after'  => bcadd($pool_pubn_before, $pool_pubn_diff),
            'pool_pubn_diff'   => $pool_pubn_diff,
            'pool_per_before'  => $pool_per_before,
            'pool_per_after'   => bcadd($pool_per_before, $pool_per_diff),
            'pool_per_diff'    => $pool_per_diff,
        ];
    }


    /**
     * 完整记录入库
     * @param $gift
     * @param $index
     * @param $count
     * @param $user_info
     * @param $buffer
     * @return array
     */
    public static function intact_log($gift, $index, $count, $user_info, $buffer = 1)
    {
        $gift = array_filter($gift, function ($value) {
            return $value['count'] > 0;
        });
        $weigh_name = $index['level_info']['name'];
        $price = get_site_config("egg_box{$index['box_type']}_price");
        $content = '';
        $amount = 0;
        foreach ($gift as $item) {
            $item_count = $item['count'] * $buffer;
            $amount += $item['price'] * $item_count;
            $content .= $item['name'] . '(' . (float)$item['price'] . ')x' . $item_count . ',';
        }
        $content = substr($content, 0, -1);
        $gift_list = [];
        $key = 0;
        foreach ($gift as $value) {
            $item_count = $value['count'] * $buffer;
            $gift_list[$key]['gift_id'] = $value['gift_id'];
            $gift_list[$key]['count'] = $item_count;
            $key++;
        }
        $intact_log_id = Db::name('egg_intact_log')->insertGetId([
            'user_id'     => $index['user_id'],
            'box_type'    => $index['box_type'],
            'count_type'  => $count,
            'content'     => $content,
            'amount'      => $amount,
            'use_amount'  => $price * $count,
            'level_name'  => $weigh_name,
            'buffer'      => $buffer,
            'room_id'     => $index['current_room_id'],
            'ip'          => $user_info['loginip'],
            'ip_count'    => $user_info['ip_count'],
            'imei'        => $user_info['imei'],
            'imei_count'  => $user_info['imei_count'],
            'gift_json'   => json_encode($gift_list),
            'create_time' => datetime(time()),
            'update_time' => datetime(time()),
        ]);
        return [$gift, $intact_log_id];
    }

    /**
     * 雷霆一击追加入库
     * @param $gift
     * @param $index
     * @param $count
     * @param $user_info
     * @param $buffer
     * @return array
     */
    public static function intact_log_append($intact_log_id, $gift, $index, $count, $user_info, $buffer = 1)
    {
        $gift = array_filter($gift, function ($value) {
            return $value['count'] > 0;
        });
        $weigh_name = $index['level_info']['name'];
        $price = get_site_config("egg_box{$index['box_type']}_price");
        $content = '';
        $amount = 0;
        foreach ($gift as $item) {
            $item_count = $item['count'] * $buffer;
            $amount += $item['price'] * $item_count;
            $content .= $item['name'] . '(' . (float)$item['price'] . ')x' . $item_count . ',';
        }
        $content = substr($content, 0, -1);
        $gift_list = [];
        $key = 0;
        $value = 0;
        foreach ($gift as $item) {
            user_gift_add($index['user_id'], $item['gift_id'], $item['count']);
            $item_count = $item['count'] * $buffer;
            $gift_list[$key]['gift_id'] = $item['gift_id'];
            $gift_list[$key]['count'] = $item_count;
            $key++;
            $value += $item['price'] * $item_count;
        }
        Db::name('egg_intact_log')->where('id', $intact_log_id)->setField(['gift_other' => json_encode($gift_list),]);
        Db::name('egg_intact_log')->where('id', $intact_log_id)->setInc('amount', $value);
        return $gift;
    }


    /**
     * 处理礼物后续
     * @param $gift
     * @param $index
     * @param $count
     * @param $room_id
     * @return mixed
     * @throws
     */
    public static function process_gift($gift, $index, $count, $room_id, $intact_log_id, $reward = 1)
    {
        [$gift, $limit_log, $group_flag] = self::groupGift($gift, $index, $count, $room_id, $reward);
        self::clear_gift_process($index, array_column($gift, 'gift_id'), $count);
        $url = Env::get('app.lan_api_url', url('/', '', '', true)) . '/egg/notice';
        Http::sendAsyncRequest($url, ['info' => json_encode(compact('gift', 'count', 'index', 'room_id', 'reward', 'intact_log_id', 'group_flag'))]);
        return [$gift, $limit_log, $group_flag];
    }

    /**
     * 清零礼物处理, 处理系统调控,基础调控 清零
     * @param $index
     * @param $gift_ids
     * @param $count
     * @return void
     */
    public static function clear_gift_process($index, $gift_ids, $count)
    {
        !is_array($gift_ids) && $gift_ids = explode(',', $gift_ids);
        $clear_gift = db('egg_clear_gift')->where(['box_type' => $index['box_type'], 'count_type' => $count,])
            // ->cache('egg:clear_gift:gift_ids_' . $index['box_type'], 0, 'small_data_egg')
            ->column('gift_ids', 'clear_type');
        if (isset($clear_gift[1]) && array_intersect($gift_ids, explode(',', $clear_gift[1]))) {
            $update_index['sys_100_hammer'] = 1;
        }
        if (isset($clear_gift[2]) && array_intersect($gift_ids, explode(',', $clear_gift[2]))) {
            $update_index['base_100_threshold'] = 1;
        }
        isset($update_index) && db('egg_user_index')->where('id', $index['id'])->setField($update_index);
    }

    /**
     * 用户等级检测
     * @param $index
     * @param $gift_ids  string|array  获取礼物id组合
     * @return void
     */
    public static function upgrade_level($index, $gift_ids)
    {
        !is_array($gift_ids) && $gift_ids = explode(',', $gift_ids);
        $level = self::get_user_level($index);
        $all_level = db('egg_level')->where('box_type', $index['box_type'])
            ->cache('egg:level_upgrade_' . $index['box_type'], null, 'small_data_egg')
            ->order('weigh desc')->column('upgrade_gift_ids,id', 'weigh');

        // 第一级升第二级只需要 百连3次   第一级不判定获取的礼物
        $all_level1 = $all_level;
        array_multisort(array_column($all_level1, 'weigh'), SORT_ASC, $all_level1);
        trace(json_encode($all_level1));
        if ($level['weigh'] == $all_level1[0]['weigh']) {
            if ($index['sys_100_count'] >= 300) {
                db('egg_user_index')->where('id', $index['id'])->setField('level_id', $all_level1[1]['id']);
            }
            return;
        }

        foreach ($all_level as $weigh => $item) {
            if ($weigh == $level['weigh']) {
                break;
            }
            if ($weigh > $level['weigh'] && array_intersect(explode(',', $item['upgrade_gift_ids']), $gift_ids)) {
                db('egg_user_index')->where('id', $index['id'])->setField('level_id', $item['id']);
                break;
            }
        }
    }

    public static function get_user_level($index)
    {
        $level_id = $index['level_id'];
        $level = db('egg_level')->cache('egg:level_data:' . $level_id, 0, 'small_data_egg')->find($level_id);
        return $level;
    }

    /**
     * 权重名称唯一性检测
     * @param $title
     * @param $table
     * @return void
     * @throws Exception
     */
    public static function weigh_title_unique($title, $table, $id)
    {
        $arr = [
            'back'   => '回血调控',
            'base'   => '基础调控',
            'def'    => '默认调控',
            'per'    => '个人调控',
            'pubn'   => '新手初始调控',
            'pub'    => '初始调控',
            'single' => '单抽调控',
            'sys'    => '系统调控',
        ];
        $exist = db('egg_config_' . $table)->where(['title' => $title, 'id' => ['neq', $id]])->count();
        if ($exist) {
            throw new Exception($arr[$table] . 'code重复, 请更换新的code再试');
        }
        unset($arr[$table]);
        foreach ($arr as $key => $val) {
            $exist = db('egg_config_' . $key)->where(['title' => $title])->count();
            if ($exist) {
                throw new Exception($val . 'code重复, 请更换新的code再试');
            }
        }
    }

    /**
     * @param $gift
     * @param $index
     * @param $count
     * @param $room_id
     * @param $reward
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    private static function groupGift($gift, $index, $count, $room_id, $reward): array
    {
        $limit_price = get_site_config('egg_log_limit');
        $base_limit_log = [
            'type'        => 3,
            'user_id'     => $index['user_id'],
            'box_type'    => $index['box_type'],
            'count_type'  => $count,
            'gift_id'     => 0,
            'count'       => 1,
            'room_id'     => $room_id,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $limit_log = [];
        foreach ($gift as $k => $item) {
            if ($reward > 1) {
                $gift[$k]['count'] *= $reward;
                $gift[$k]['buffer'] = 1;
            }
        }
        // 百连 检测雷霆一击
        $group_flag = false;
        if ($count == 100) {
            $group = db('egg_group')->cache('egg:group:' . $index['box_type'], 0, 'small_data_egg')
                ->where(['box_type' => $index['box_type'], 'status' => 1])->find();
            $group_gift_ids = explode(',', $group['gift_ids']);
            $group_flag = true;
            foreach ($group_gift_ids as $item) {
                if (!isset($gift[$item])) {
                    $group_flag = false;
                    break;
                }
            }
        }
        foreach ($gift as &$item) {
            user_gift_add($index['user_id'], $item['gift_id'], $item['count']);
            unset($item['x_percent']);
            $group_flag && $item['voice'] = 0;
            if ($item['price'] > $limit_price) {
                $limit_log[] = array_merge($base_limit_log, [
                    'type'    => isset($item['x_gift']) ? 2 : 1,
                    'gift_id' => $item['gift_id'],
                    'count'   => $item['count'],
                ]);
            }
        }
        return [$gift, $limit_log, $group_flag ? 1 : 0];
    }

}
