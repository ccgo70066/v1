<?php

namespace app\api\controller;


use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\service\MongoService;
use app\common\service\RedisService;
use app\common\service\WheelService;
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
class Game2 extends Base
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

        $user_id = $this->auth->id;
        $box_type = 1;
        $count = abs((int)input('count'));
        $room_id = input('room_id') ?: '0';

        $result = WheelService::instance()->open_wheel($user_id, $box_type, $count, $room_id);
        if (!$result) {
            $this->error(__('Network busy'));
        }
        $data['result'] = $result;
        $data['user_info'] = WheelService::instance()->get_info($user_id);
        $data['total_amount'] = 0;
        foreach ($data['result'] as $item) {
            $data['total_amount'] += $item['price'] * $item['count'];
        }
        $this->success('', $data);
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


}
