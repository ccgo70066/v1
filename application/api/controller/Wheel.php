<?php

namespace app\api\controller;


use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\service\MongoService;
use app\common\service\RedisService;
use fast\Http;
use think\Cache;
use think\Db;
use think\Env;
use think\Exception;
use think\Log;
use util\Util;

/**
 * 游戏二
 * @package app\api\controller
 */
class Wheel extends Base
{
    protected $noNeedLogin = ['notice', 'notice_debug'];
    protected $noNeedRight = ['*'];

    /**
     * 獲取獎池禮物
     */
    public function gift()
    {
        $box_type = 1;
        $user_id = $this->auth->id;
        // $result = Cache::remember('wheel:gift:box_' . $box_type, function () use ($box_type) {
        //     Cache::tag('small_data_wheel', 'wheel:gift:box_' . $box_type);
        // $orderBy = redis()->get('wheel_gift_sort');
        //     if(!$orderBy) {
        //         //todo 固定位置,升级包后去掉
        //         $sort = Db::name('wheel_gift')->where('box_type',1)->orderRaw('rand()')->column('id');
        //         $orderBy = redis()->set('wheel_gift_sort',implode(',',$sort));
        //     }
        $list1 = db('wheel_gift e')
            ->join('gift g', 'e.gift_id=g.id', 'left')
            ->field('e.gift_id,g.name,g.image,g.price')
            ->where(['box_type' => 1])
            // ->orderRaw('field(e.id,'.$orderBy.')')
            ->orderRaw('rand()')
            ->select();

        $adornment = db('adornment')->where('id', get_site_config('egg_gift_adornment'))
            ->field('id,name,cover as image')->find();
        $result = [
            'config'    => [
                'price'                => get_site_config('wheel_price1'),
                'option'               => get_site_config('wheel_counts'),
                'wheel_gift_adornment' => $adornment['image'],
                'gift'                 => $list1,
            ],
            'user_info' => $this->get_info($user_id),
        ];
        // return $result;
        // }, 30);

        $this->success('', $result);
    }

    /**
     * 全服记录
     */
    public function log()
    {
        $box_type = 1;
        $log = Db::name('wheel_limit_log l')
            ->join('user u', 'l.user_id=u.id', 'left')
            ->join('gift g', 'l.gift_id=g.id', 'left')
            ->field("l.id,user_id,box_type,count_type,l.count,gift_id,g.name,g.image,g.price,u.nickname,u.avatar,l.create_time")
            ->where('l.box_type', $box_type)
            ->order('l.id desc')->page(1, 100)->select() ?: [];
        $date = new \util\Date();
        foreach ($log as &$item) {
            $item['create_time_text'] = $date->human_time($item['create_time']) ?? '';
        }
        $this->success('', $log);
    }

    /**
     * 我的记录
     * @ApiParams   (name="box_type", type="int", required=true, rule="between:1,2", description="类型:1=初级,2=高级")
     * @ApiParams   (name="start_id", type="int", required=false, rule="", description="起始id")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每页数量")
     *
     * @ApiReturnParams   (name="box_type", type="int", description="类型:1=初级,2=高级")
     * @ApiReturnParams   (name="count_type", type="int", description="连抽次数")
     * @ApiReturnParams   (name="create_time", type="string", description="时间")
     * @ApiReturnParams   (name="gifts", type="array", description="中奖礼物列表")
     * @ApiReturnParams   (name="gifts.name", type="string", description="礼物名称")
     * @ApiReturnParams   (name="gifts.image", type="string", description="礼物图标")
     * @ApiReturnParams   (name="gifts.count", type="string", description="礼物数量")
     */
    public function my_log()
    {
        $size = input('size') ?: 10;
        $user_id = (string)$this->auth->id;
        $start_id = input('start_id') ?: 0;

        $where['user_id'] = $user_id;
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }
        $log = Db::name('wheel_intact_log')
            ->field("id,box_type,count_type,create_time,gift_json")
            ->where('box_type', input('box_type'))
            ->where($where)
            ->order('create_time desc,id desc')
            ->page(1, $size)
            ->select();

        $date = new \util\Date();
        if ($log) {
            //从缓存获取礼物、用户信息
            foreach ($log as &$item) {
                $item['create_time_text'] = $date->human_time(strtotime($item['create_time'])) ?? '';
                $data = json_decode($item['gift_json'], true);
                $gifts = [];
                foreach ($data as $key => $value) {
                    $gifts[$key]['name'] = RedisService::getGiftCache($value['gift_id'], 'name');
                    $gifts[$key]['image'] = RedisService::getGiftCache($value['gift_id'], 'image');
                    $gifts[$key]['price'] = RedisService::getGiftCache($value['gift_id'], 'price');
                    $gifts[$key]['count'] = $value['count'];
                }
                $item['gifts'] = $gifts;
                unset($item['gift_json']);
            }
        }
        $this->success('', $log);
    }

    /**
     * 玩游戏
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int", required=false, rule="", description="房間號")
     * @ApiParams   (name="count", type="int", required=true, rule="in:1,10,100", description="次數:1,10,100")
     * @throws \Exception
     */
    public function play()
    {
        // dump($this->auth->id);
        $this->operate_check('wheel_pay:' . $this->auth->id, 3);
        get_site_config('wheel_switch') != 1 && $this->error(__('The opening time is not yet, please wait!'));
        // $lang = $this->request->langset() == 'en' ? 'en' : 'zh';
        // in_array(5, explode(',', get_site_config('business_limit'))) &&
        // $this->error(Translate::trans(get_site_config('business_limit_msg'), $lang));

        $user_id = $this->auth->id;
        $box_type = 1;
        $count = abs((int)input('count'));
        if (!in_array($count, [1, 10, 100])) {
            $this->error(__('Parameter %s can not be empty', 'count'));
        }
        $room_id = input('room_id') ?: '0';


        $range = get_site_config("wheel_{$box_type}_range");
        if ($range != '') {
            $current_date = date('H:i');
            $open_flag = false;
            foreach (explode(',', $range) as $item) {
                list($start, $end) = explode('-', $item);
                $start = str_pad($start, 5, '0', STR_PAD_LEFT);
                $end = str_pad($end, 5, '0', STR_PAD_LEFT);
                if ($start <= $current_date && $current_date <= $end) {
                    $open_flag = true;
                }
            }
            !$open_flag && $this->error(__('The opening time is not yet, please wait!'));
        }
        if ($box_type == 2) {
            $this->error(__('The opening time is not yet, please wait!'));
        }

        $price = get_site_config('wheel_price' . $box_type);
        !check_user_amount($user_id, $count * $price) &&
        $this->error(__('Your game coupons are insufficient, please redeem them'), null, 405);

        $result = $this->open_wheel($user_id, $box_type, $count, $room_id);
        if (!$result) {
            $this->error(__('Network busy'));
        }
        $data['result'] = $result;
        $data['user_info'] = $this->get_info($user_id);
        $data['total_amount'] = 0;
        foreach ($data['result'] as $item) {
            $data['total_amount'] += $item['price'] * $item['count'];
        }
        $this->success('', $data);
    }


    protected function get_info($user_id): array
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
     * @param     $user_id
     * @param     $box_type
     * @param     $count
     * @param     $room_id
     * @return array|mixed|void
     * @throws ApiException
     */
    protected function open_wheel($user_id, $box_type, $count, $room_id)
    {
        try {
            Db::startTrans();
            $price = get_site_config('wheel_price' . $box_type);
            user_business_change($user_id, 'amount', $count * $price, 'decrease', '兑换游戏券', 9);
            if ($room_id) {
                $redis = redis();
                $redis->hIncrBy('room_egg', $room_id, $count * $price);
            }
            $gift = $this->get_gift($box_type);
            $index = $this->get_user_index($user_id, $box_type, $room_id);
            $user_level = $this->get_user_level($index);
            $sys_config = $this->get_sys_config($index, $user_level, $count);

            $time = time();
            $log_data = [];
            $total_value = 0;
            $log_gift_ids = [];
            for ($i = 0; $i < $count; $i++) {
                $weigh = $this->get_weigh($index, $user_level, $count);
                if ($sys_config['status'] == 1 && $i == $count - 1) {
                    $weigh = $sys_config;
                }
                $weigh['level'] = $user_level;
                $lottery_gift_id = $this->lottery($weigh);

                $pool_info = $this->process_pool($weigh, $index, $gift[$lottery_gift_id]['price']);
                $log_data[] = array_merge([
                    'gift_value'  => $gift[$lottery_gift_id]['price'],
                    'user_id'     => $user_id,
                    'box_type'    => $box_type,
                    'count_type'  => $count,
                    'used_amount' => $price,
                    'gift_id'     => $lottery_gift_id,
                    'count'       => 1,
                    'room_id'     => $room_id,
                    'weigh_name'  => $weigh['title'],
                    'jump_status' => $weigh['jump_status'],
                    'level_id'    => $user_level['id'],
                    'box_index'   => $index["count"] + 1,
                    'create_time' => $time,
                    'update_time' => $time,
                ], $pool_info ?? []);

                $index['pool'] = bcadd($index['pool'], $pool_info['pool_per_diff'] ?? 0);
                $index['count']++;
                $index['count_' . $count] = ($index['count_' . $count]) + 1;
                $index['total_used'] += $price;
                $index['total_lucre'] += $gift[$lottery_gift_id]['price'];
                $index['today_used'] += $price;
                $index['today_lucre'] += $gift[$lottery_gift_id]['price'];
                $total_value += $gift[$lottery_gift_id]['price'];
                $gift[$lottery_gift_id]['count']++;
                $log_gift_ids[] = $lottery_gift_id;
            }
            $index['update_time'] = datetime(time());
            $first_level = db('wheel_level')->where('box_type', $box_type)->order('weigh asc')->find();
            $first_level['weigh'] != $user_level['weigh'] && $index['hammer_' . $count]++;
            db('wheel_user_index')->update($index);
            $gift = $this->intact_log($gift, $index, $count, $weigh['level']['name'] ?? '', $time);
            $this->process_gift($gift, $index, $count, $room_id);
            $this->process_limit_log($gift, $user_id, $box_type, $count, $room_id, $time);

            foreach ($gift as $item) {
                if (!in_array($item['gift_id'], $log_gift_ids)) {
                    Log::error('游戏数据不一致');
                    Log::error($gift);
                    Log::error($log_gift_ids);
                    throw new \Exception('网络请求异常,请重试');
                }
            }
            $intact_gift_ids = array_column($gift, 'gift_id');
            foreach ($log_gift_ids as $log_gift_id) {
                if (!in_array($log_gift_id, $intact_gift_ids)) {
                    Log::error('游戏数据不一致');
                    Log::error($gift);
                    Log::error($log_gift_ids);
                    throw new \Exception('网络请求异常,请重试');
                }
            }

            //数据存储
            MongoService::dataStore([
                'user_id'     => (int)$user_id,
                'box_type'    => (int)$box_type,
                'count_type'  => (int)$count,
                'used_amount' => (int)$count * (int)$price,
                'total_value' => (int)$total_value,
                'room_id'     => (int)$room_id,
                'level_id'    => (int)$index['level_id'],
                'create_time' => $time,
                'log'         => array_reverse(array_columns($log_data, [
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
                ]))
            ]);
            MongoService::dataInsert('aa_wheel_log');
            Db::commit();
            return array_values($gift);
        } catch (\Throwable $e) {
            Db::rollback();
            error_log_out($e);
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return false;
        }
    }


    /**
     * 完整记录
     * @param $gift
     * @param $index
     * @param $count
     * @param $weigh_name
     * @return array
     * @throws
     */
    protected function intact_log($gift, $index, $count, $weigh_name, $time): array
    {
        $gift = array_filter($gift, static function ($value) {
            return $value['count'] > 0;
        });

        $price = get_site_config('wheel_price' . $index['box_type']);
        $content = '';
        $amount = 0;
        $gift_list = [];
        $key = 0;
        foreach ($gift as $item) {
            $amount += $item['price'] * $item['count'];
            $content .= $item['name'] . '(' . (float)$item['price'] . ')x' . $item['count'] . ',';
            $gift_list[$key]['gift_id'] = $item['gift_id'];
            $gift_list[$key]['count'] = $item['count'];
            $key++;
        }
        $content = substr($content, 0, -1);
        $user = db('user')->where('id', $index['user_id'])->field('imei,loginip')->find();
        $res = Db::name('wheel_intact_log')->insert([
            'user_id'     => $index['user_id'],
            'box_type'    => $index['box_type'],
            'count_type'  => $count,
            'content'     => $content,
            'amount'      => $amount,
            'used_amount' => $price * $count,
            'level_name'  => $weigh_name,
            'buffer'      => 1,
            'gift_json'   => json_encode($gift_list),
            'room_id'     => $index['current_room_id'],
            'imei'        => $user['imei'],
            'ip'          => $user['loginip'],
            'create_time' => datetime($time),
            'update_time' => datetime($time),
        ]);
        if (!$res) {
            throw new \Exception('网络请求异常,请重试');
        }
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
    protected function process_gift($gift, $index, $count, $room_id)
    {
        foreach ($gift as $item) {
            user_gift_add($index['user_id'], $item['gift_id'], $item['count']);
        }
        $this->clear_gift_process($index, array_column($gift, 'gift_id'), $count);
        $this->upgrade_level($index, array_column($gift, 'gift_id'));
        !Env::get('app.dev_mode') &&
        Http::sendAsyncRequest(Env::get('app.lan_api_url') . '/wheel/notice', [
            'info' => json_encode([
                'sleep'   => 5,
                'gift'    => $gift,
                'count'   => $count,
                'index'   => $index,
                'room_id' => $room_id,
            ])
        ], 'post');
    }

    /**
     * 异步发送通知
     * @ApiInternal
     */
    public function notice()
    {
        $info = json_decode(htmlspecialchars_decode(input('info')), true);
        if (!$info) {
            Log::error('异步解析出错');
            Log::error(input('info'));
            return;
        }

        $gift = $info['gift'];
        $count = $info['count'];
        $index = $info['index'];
        $room_id = $info['room_id'];
        $info['sleep']++;  // 增加一秒延时
        $sleep = 7;

        // todo
        $screen_notice_switch = 1;
        $room_notice_switch = 1;

        $all_room_notice_gift = [];
        $current_room_notice_gift = [];
        $screen_notice_gift = [];
        $count = 0;
        foreach ($gift as $item) {
            $count += $item['count'];
            $info = [
                'gift_id' => $item['gift_id'],
                'name'    => $item['name'],
                'image'   => $item['image'],
                'price'   => (float)$item['price'],
                'count'   => $item['count'],
            ];
            isset($item['is_max_gift']) && $info['is_max_gift'] = $item['is_max_gift'];
            $gift_info = $info;

            $current_room_notice_gift[] = $gift_info;
            if ($item['room_notice'] == 1) {
                $all_room_notice_gift[] = $gift_info;
            }
            if ($item['broadcast']) {
                $screen_notice_gift[] = $gift_info;
            }
        }
        $user_info = get_user_info($index['user_id'], ['level']);

        if ($room_notice_switch && !empty($all_room_notice_gift)) {
            $msg_data = array_merge([
                'ignore_room_id' => $room_id,
                'game_id'        => '2',
                'box_type'       => $index['box_type'],
                'gift'           => $all_room_notice_gift,
            ], get_user_info($index['user_id'], ['level', 'noble']) ?? []);
            board_notice_delay(Message::CMD_SCREEN_ALL_ROOM, $msg_data, '', $sleep);
        }
        if ($screen_notice_switch) {
            //  需要飘屏的礼物
            foreach ($screen_notice_gift as $item) {
                $data = array_merge($user_info, [
                    'box_type'   => (int)$index['box_type'],
                    'gift_name'  => $item['name'],
                    'gift_image' => $item['image'],
                    'gift_price' => (string)($item['price'] + 0),
                    'count'      => $item['count'],
                ], isset($item['is_max_gift']) ? ['is_max_gift' => $item['is_max_gift']] : []);
                board_notice_delay(Message::CMD_BOARD_WHEEL, $data, '', $sleep);
            }
        }
        user_adornment_add($index['user_id'], get_site_config('wheel_gift_adornment'), -1);
    }

    /**
     * 异步发送通知
     * @ApiInternal
     */
    public function notice_debug()
    {
        sleep(10);
        $this->success('1');
    }

    protected function get_bubble($gift_list)
    {
        return $gift_list[0]['gift_id'];
    }

    /**
     * 变更奖池
     * @param $weigh
     * @param $index
     * @param $gift_price
     * @return array|mixed
     */
    protected function process_pool($weigh, $index, $gift_price)
    {
        // weigh_type:1=系统调控,2=基础调控,3=初始调控,4=个人调控,5=容灾调控,10=回血调控,11=单抽
        // 奖池变更增加:  跟据当前分级的的奖池增长比率进行加分
        // 奖池变更减少:
        //     系统调控: 系统盘、个人盘减分
        //     基础调控: 小公池、个人盘减分
        //     初始调控: 小公池、个人盘减分
        //     个人调控: 个人盘减分
        //     容灾调控: 小公池, 个人盘减分
        //     回血调控: 按用户当前分级比率进行系统盘、小公池、个人盘减分
        bcscale(2);
        $box_type = $index['box_type'];
        $price = get_site_config('wheel_price' . $box_type);
        $pool_sys_percent = $weigh['level']['pool_sys_percent'];
        $pool_pub_percent = $weigh['level']['pool_pub_percent'];
        $pool_per_percent = $weigh['level']['pool_per_percent'];
        $sys_gift_percent = 1;
        $pub_gift_percent = 1;
        $per_gift_percent = 1;

        $pool_sys_diff = bcdiv(bcmul($price, $pool_sys_percent), 100);
        $pool_pub_diff = bcdiv(bcmul($price, $pool_pub_percent), 100);
        $pool_per_diff = bcdiv(bcmul($price, $pool_per_percent), 100);

        in_array($weigh['weigh_type'], [1, 10]) && $pool_sys_diff = bcsub(
            $pool_sys_diff,
            bcmul($gift_price, $sys_gift_percent)
        );
        in_array($weigh['weigh_type'], [2, 3, 5, 10]) && $pool_pub_diff = bcsub(
            $pool_pub_diff,
            bcmul($gift_price, $pub_gift_percent)
        );
        $pool_per_diff = bcsub($pool_per_diff, bcmul($gift_price, $per_gift_percent));

        $pool_sys_key = 'pool:wheel:sys_' . $box_type;
        $pool_pub_key = 'pool:wheel:pub_' . $box_type;
        $pool_sys_before = bcdiv(cache($pool_sys_key), 100);
        Cache::inc($pool_sys_key, bcmul($pool_sys_diff, 100));
        $pool_pub_before = bcdiv(cache($pool_pub_key), 100);
        Cache::inc($pool_pub_key, bcmul($pool_pub_diff, 100));
        $pool_per_before = $index['pool'];

        return [
            'pool_sys_before' => $pool_sys_before,
            'pool_sys_after'  => bcadd($pool_sys_before, $pool_sys_diff),
            'pool_sys_diff'   => $pool_sys_diff,
            'pool_pub_before' => $pool_pub_before,
            'pool_pub_after'  => bcadd($pool_pub_before, $pool_pub_diff),
            'pool_pub_diff'   => $pool_pub_diff,
            'pool_per_before' => $pool_per_before,
            'pool_per_after'  => bcadd($pool_per_before, $pool_per_diff),
            'pool_per_diff'   => $pool_per_diff,
        ];
    }

    /**
     * 获取抽中礼物的id
     * @param $weigh
     * @return int|string
     */
    protected function lottery($weigh)
    {
        $gift_weigh = json_decode($weigh['config'], true);
        $diff_multiple = 1;
        foreach ($gift_weigh as $item) {
            if (strpos($item['weight'], '.') !== false) {
                $min = $item['weight'];
                $min_int = (int)str_replace('.', '', $min);
                $diff_temp = $min_int / $min;
                $diff_multiple = max($diff_temp, $diff_multiple);
            }
        }
        $arr = [];
        foreach ($gift_weigh as $item) {
            $item['weight'] > 0 && $arr[$item['id']] = (int)($item['weight'] * $diff_multiple);
        }
        return Util::get_rand($arr);
        return $arr;


        $key = 'wheel:lottery:' . implode('_', [$weigh['weigh_type'], $weigh['level_id'], $weigh['title']]);
        $arr = Cache::remember($key, function () use ($weigh, $key) {
            Cache::tag('small_data_wheel', $key);
            $gift_weigh = json_decode($weigh['config'], true);
            $diff_multiple = 1;
            foreach ($gift_weigh as $item) {
                if (strpos($item['weight'], '.') !== false) {
                    $min = $item['weight'];
                    $min_int = (int)str_replace('.', '', $min);
                    $diff_temp = $min_int / $min;
                    $diff_multiple = max($diff_temp, $diff_multiple);
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
     * 获取权重
     * 顺序: 系统调控 > 基础调控 > [初始/个人]调控 > 容灾
     * @param $index
     * @param $count
     * @param $demo
     * @return array|mixed
     * @throws Exception
     * @throws \Exception
     */
    protected function get_weigh($index, $level, $count)
    {
        // weigh_type:1=系统调控,2=基础调控,3=初始调控,4=个人调控,5=容灾调控,10=回血调控,11=单抽
        $box_type = $index['box_type'];
        $weigh['level_id'] = $level['id'];
        $weigh['level'] = $level;
        $weigh['jump_status'] = 0;
        $config_where = ['box_type' => $box_type, 'level_id' => $level['id'], 'status' => 1,];

        $jump_data = explode(',', $level['jump_data']);
        $threshold_count = $index["count_" . $count] ?? 0;
        $threshold_count++;
        // 基础调控
        if (!in_array(2, $jump_data)) {
            // 单人调控10次就是在第10次处理不是符合10次再处理. @done (21-08-07 22:41)
            // $index['count']++;
            // $threshold_count = $demo == 0 ? $index['count'] : $threshold_count;
            $configs = db('wheel_config_base')
                // ->cache('wheel:config:base_' . implode('_', [$box_type, $level['id'], $count]), 0, 'small_data_wheel')
                ->where($config_where)->where(['count_type' => $count,])->order('count desc')->select();
            foreach ($configs as $config) {
                if ($threshold_count % $config['count'] == 0) {
                    if (!$this->jump($config['ignore_1th'])) {
                        if (!$this->jump($config['ignore_2th'])) {
                            $weigh['jump_status'] = 6;
                            $weigh['weigh_type'] = 2;
                            $weigh['title'] = $config['title'];
                            $weigh['config'] = $config['config'];
                            return $weigh;
                        }
                        $weigh['jump_status'] = 5;
                    } else {
                        $weigh['jump_status'] = 4;
                    }
                    break;
                }
            }
        }
        // 初始调控
        if ($level['or_data'] == 1 && !in_array(3, $jump_data)) {
            $pool = cache('pool:wheel:pub_' . $box_type) / 100;
            $config = db('wheel_config_pub')->where($config_where)->where([
                'count_type'  => $count,
                'range_start' => [['elt', $pool], ['exp', Db::raw('is null')], 'or'],
                'range_end'   => [['gt', $pool], ['exp', Db::raw('is null')], 'or'],
            ])->field('title,config')->find();
            if ($config) {
                $weigh['weigh_type'] = 3;
                return array_merge($config, $weigh);
            }
        }
        // 个人调控
        if (($level['or_data'] == 2 && !in_array(4, $jump_data))) {
            $pool = $index['pool'];
            $config = db('wheel_config_per')->where($config_where)->where([
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
        $last_config = db('wheel_config_def')->field('title,config,"5" as weigh_type')
            ->where(['box_type' => $box_type, 'status' => 1])->find();
        if (!$last_config) {
            Log::error('海贼王:无容灾配置');
            throw new Exception('海贼王投掷失败:9');
        }
        return array_merge($last_config, $weigh);
    }

    /**
     * 根据比率计算是否跳过,   例如: $percent=90.5,  去掉小数点905 则最大的随机数为 100*905/90.5=1000,
     * 获得1~1000的随机数, 小于$percent则返回true, 大于$percent则返回false
     * true 跳过, false 进入
     * @param $percent
     * @return bool|mixed
     * @throws \Exception
     */
    protected function jump($percent)
    {
        if ($percent == 0) {
            return false;
        }
        $int_percent = (int)str_replace('.', '', $percent);
        $divisor = 100 * $int_percent / $percent;
        return random_int(1, $divisor) <= $int_percent;
    }

    /**
     * @param $user_id
     * @param $box_type
     * @return array|mixed
     * @throws Exception
     */
    protected function get_user_index($user_id, $box_type, $room_id)
    {
        $where_data = ['user_id' => $user_id, 'box_type' => $box_type];
        $index = db('wheel_user_index')->where($where_data)->find();
        if (!$index) {
            $pool_default = 100000; // 默认
            $default_level = db('wheel_level')->where('box_type', $box_type)->order('weigh asc')->find();
            $index = array_merge($where_data, [
                'pool'        => $pool_default,
                'count'       => 0,
                'count_1'     => 0,
                'count_10'    => 0,
                'count_100'   => 0,
                'total_used'  => 0,
                'total_lucre' => 0,
                'today_used'  => 0,
                'today_lucre' => 0,
                'hammer_1'    => 1,
                'hammer_10'   => 1,
                'hammer_100'  => 1,
                'level_id'    => $default_level['id'],
            ]);
            if (get_site_config('behaviour_switch') == 1) {
                //todo 没有此表
                $behaviour = db('user_behaviour')->cache('behaviour:' . $user_id)
                    ->where('id', $user_id)->value('b_status');
                $behaviour == 0 && $behaviour = TagBehaviour::tag_init($user_id);
                $behaviour > 1 && $index['pool'] *= 0.5;
            }
            $index['id'] = db('wheel_user_index')->insertGetId($index);
        }
        $index['current_room_id'] = $room_id;
        return $index;
    }

    /**
     * @param $box_type
     * @return mixed
     */
    protected function get_gift($box_type)
    {
        $gift = db('wheel_gift e')
            ->join('gift g', 'e.gift_id=g.id', 'left')
            ->where(['e.box_type' => $box_type, 'e.status' => 1])
            // ->cache('wheel:gift:list_' . $box_type, 0, 'small_data_wheel')
            ->order('g.price desc')
            ->column('e.*,g.name,g.image,g.price,"0" as count', 'gift_id');
        $max = 0;
        $max_id = 0;
        foreach ($gift as &$item) {
            unset($item['id'], $item['box_type'], $item['weigh'], $item['status']);
            // , $item['last_status'], $item['last_time']);
            if ($item['price'] >= $max) {
                $max = $item['price'];
                $max_id = $item['gift_id'];
            }
        }
        $gift[$max_id]['is_max_gift'] = 1;
        return $gift;
    }


    protected function get_level_image($level)
    {
        return db('user_level')->cache('level_image:' . $level, 0, 'small_data_level')
            ->where('grade', $level)->value('icon');
    }

    /**
     * 规则说明
     */
    public function explain()
    {
        $info = '活動期間，玩家可扮演海賊王，通過購買裝扮獲取附贈的藏寶圖進行尋寶，每次尋寶都會獲得隨機獎勵。';
        $rule = [
            '1.獲得的道具在禮物介面背包中查看；',
            '2.您知悉“海賊王”是旨在提升平臺娛樂性和用戶活躍度的互動功能，該活動僅供互動娛樂，用戶通過活動獲取獎勵僅限在平臺內消耗使用；',
            '3.活動期間，用戶應當遵守法律法規及平臺規則，如發現用戶違規（包括但不限於使用外掛作弊、惡意套現、反向兌換現金、倒買倒賣、洗錢等違背誠實信用或損害其他用戶、平臺及任何協力廠商合法權益的行為），平臺有權撤銷用戶活動參與資格，收回全部權益，必要時追究法律責任，並有權視情况對用戶採取限制、封禁帳號、凍結資金以做進一步查證處理；',
            '4.任何人不得利用本次活動進行博彩或其他違法行為，對於本平臺用戶，一經發現，本平臺將立即取消用戶參與資格並收回所得獎勵。 如行為構成犯罪，將移交司法機關處理。 如給平臺造成其他損失，同時應當承擔賠償責任；',
            '5平臺嚴禁並嚴厲打擊任何以盈利為目的的線下非法交易行為，任何人以本平臺名義宣稱或從事類似活動或惡意發放、轉讓、回收、獎勵、欺詐等造成用戶損失的，本平臺無需為此承擔任何法律責任；',
            '6.平臺有權根據活動實際舉辦情况隨時對活動規則進行變動或調整，並將及時公佈在活動頁面上，公佈後依法生效；',
            '7.本活動僅限年齡18歲及以上用戶參加，請參與互動玩法的用戶務必注意健康娛樂，適度消費；',
            '8.本活動與Apple In.無關；',
            '9.如對活動規則有任何疑問，請聯系線上客服諮詢。',
        ];
        $percent = Db::name('wheel_gift')->alias('w')->join('gift g', 'w.gift_id =g.id')
            ->where('w.status', 1)->order('g.price', 'desc')->order('w.weigh', 'asc')
            ->field('g.name,w.show_rate as percent,g.image,g.price')
            ->select();
        $this->success('', compact('info', 'rule', 'percent'));
    }


    protected function get_user_level($index)
    {
        $level_id = $index['level_id'];
        return db('wheel_level')
            // ->cache('wheel:level_data:' . $level_id, 0, 'small_data_wheel')
            ->find($level_id);
    }

    protected function get_sys_config(&$index, $level, $count)
    {
        $jump_data = explode(',', $level['jump_data']);
        $box_type = $index['box_type'];
        $config_where = ['box_type' => $box_type, 'level_id' => $index['level_id'], 'status' => 1,];
        $weigh = ['status' => 0];

        // 系统调控
        if (!in_array(1, $jump_data) && $index['hammer_10'] > 0) {
            $pool = cache('pool:wheel:sys_' . $box_type) / 100;
            $config = db('wheel_config_sys')->where($config_where)->where([
                'count_type'  => $count,
                'range_start' => [['elt', $pool], ['exp', Db::raw('is null')], 'or'],
                'range_end'   => [['gt', $pool], ['exp', Db::raw('is null')], 'or'],
            ])->find();
            if ($config) {
                $enter_json = json_decode($config['enter_json'], true);
                !isset($enter_json[$index['hammer_' . $count]]) && $index['hammer_' . $count] = 1;
                $percent = $enter_json[$index['hammer_' . $count] ?? 1] ?? '';
                $weigh['status'] = $this->enter_roll($percent) ? 1 : 0;
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
     * @param $percent
     * @return bool|mixed
     * @throws \Exception
     */
    protected function enter_roll($percent)
    {
        if ($percent == 0 || $percent == null) {
            return false;
        }
        $int_percent = (int)str_replace('.', '', (float)$percent);
        $divisor = 100 * $int_percent / $percent;
        return random_int(1, $divisor) <= $int_percent;
    }

    /**
     * 处理系统调控,基础调控 清零
     * @param $index
     * @param $gift_ids
     * @param $count
     * @return void
     */
    protected function clear_gift_process($index, $gift_ids, $count)
    {
        !is_array($gift_ids) && $gift_ids = explode(',', $gift_ids);
        $clear_gift = db('wheel_clear_gift')->where(['box_type' => $index['box_type'], 'count_type' => $count,])
            // ->cache('wheel:clear_gift:gift_ids_' . $index['box_type'], 0, 'small_data_wheel')
            ->column('gift_ids', 'clear_type');
        if (isset($clear_gift[1]) && array_intersect($gift_ids, explode(',', $clear_gift[1]))) {
            $update_index['hammer_' . $count] = 1;
        }
        isset($update_index) && db('wheel_user_index')->where('id', $index['id'])->setField($update_index);
    }

    /**
     * 用户等级检测
     * @param $index
     * @param $gift_ids  string|array  获取礼物id组合
     * @return void
     */
    protected function upgrade_level($index, $gift_ids)
    {
        !is_array($gift_ids) && $gift_ids = explode(',', $gift_ids);
        $level = $this->get_user_level($index);
        $all_level = db('wheel_level')->where('box_type', $index['box_type'])
            ->cache('wheel:level_upgrade_' . $index['box_type'], null, 'small_data_wheel')
            ->order('weigh desc')->column('upgrade_gift_ids,id', 'weigh');

        foreach ($all_level as $weigh => $item) {
            if ($weigh == $level['weigh']) {
                break;
            }
            if ($weigh > $level['weigh'] && array_intersect(explode(',', $item['upgrade_gift_ids']), $gift_ids)) {
                db('wheel_user_index')->where('id', $index['id'])->setField('level_id', $item['id']);
                break;
            }
        }
    }

    /**
     * @param     $gift
     * @param     $user_id
     * @param     $box_type
     * @param     $count
     * @param     $room_id
     * @param int $time
     */
    private function process_limit_log($gift, $user_id, $box_type, $count, $room_id, int $time): void
    {
        $limit_log = [];
        $limit_price = get_site_config('wheel_log_limit');
        foreach ($gift as $item) {
            if ($item['price'] > $limit_price) {
                $limit_log[] = [
                    'user_id'     => $user_id,
                    'box_type'    => $box_type,
                    'count_type'  => $count,
                    'gift_id'     => $item['gift_id'],
                    'count'       => $item['count'],
                    'room_id'     => $room_id,
                    'create_time' => $time,
                    'update_time' => $time,
                ];
            }
        }
        $limit_log && db('wheel_limit_log')->insertAll($limit_log);
    }


}
