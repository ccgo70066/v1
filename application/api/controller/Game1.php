<?php

namespace app\api\controller;


use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\service\EggService;
use app\common\service\MongoService;
use app\common\service\RedisService;
use think\Cache;
use think\Db;
use think\Log;
use think\Request;

/**
 * 游戏一
 * @package app\api\controller
 */
class Game1 extends Base
{
    protected $noNeedLogin = ['notice', 'explain',];
    protected $noNeedRight = ['*'];

    /**
     * 配置
     * @ApiReturnParams  (name="egg_switch", type="int", description="游戏开关")
     * @ApiReturnParams  (name="egg_box1_switch", type="int", description="游戏初级场次开关")
     * @ApiReturnParams  (name="egg_box2_switch", type="int", description="游戏高级场次开关")
     * @ApiReturnParams  (name="egg_box1_price", type="int", description="游戏初级场次价格")
     * @ApiReturnParams  (name="egg_box2_price", type="int", description="游戏初级场次价格")
     * @ApiReturnParams  (name="egg_box1_count1_switch", type="int", description="游戏初级场次单抽开关")
     * @ApiReturnParams  (name="egg_box1_count10_switch", type="int", description="游戏初级场次十连开关")
     * @ApiReturnParams  (name="egg_box1_count100_switch", type="int", description="游戏初级场次百连开关")
     * @ApiReturnParams  (name="egg_box2_count100_switch", type="int", description="游戏高级场次百连开关")
     * @ApiReturnParams  (name="egg_gift_adornment", type="string", description="游戏赠送头像框-图片")
     */
    public function config()
    {
        Cache::clear('egg:config');
        $config = Cache::remember('egg:config', function () {
            Cache::tag('small_data_egg', 'egg:config');
            $arr = [
                'egg_switch',
                'egg_box1_switch',
                'egg_box1_price',
                'egg_box1_count1_switch',
                'egg_box1_count10_switch',
                'egg_box1_count100_switch',
                'egg_gift_adornment',
            ];
            $config = [];
            foreach ($arr as $item) {
                if (strpos($item, 'box2') !== false) {
                    unset($item);
                    continue;
                }
                if ($item == 'egg_gift_adornment') {
                    $adornment = db('adornment')->where('id', get_site_config($item))
                        ->field('id,name,cover as image')->find();
                    $config[$item] = $adornment['image'];
                } else {
                    $config[$item] = get_site_config($item);
                }
            }
            return $config;
        }, 5);

        // fix 避免新用户第一次百抽时出雷霆一击的报错
        EggService::instance()->get_user_index($this->auth->id, 1);

        $this->success('', $config);
    }

    /**
     * 获取奖池礼物
     * @ApiParams   (name="box_type", type="int", required=true, rule="between:1,2", description="类型:1=初级,2=高级")
     */
    public function gift()
    {
        $box_type = input('box_type');
        $list = Cache::remember('egg:gift:box_' . $box_type, function () use ($box_type) {
            Cache::tag('small_data_egg', 'egg:gift:box_' . $box_type);
            return db('egg_gift e')
                ->join('gift g', 'e.gift_id=g.id', 'left')
                ->field('g.name,g.image,g.price')
                ->where(['box_type' => $box_type])
                ->order('g.price desc')
                ->select();
        }, 300);
        $secret = [
            'name'   => '神秘月光',
            'image'  => '',  // 前端打包
            'price'  => '8888+',
            'x_gift' => 1,
        ];
        array_unshift($list, $secret);
        $this->success('', $list);
    }

    /**
     * 全服记录
     * @ApiParams   (name="box_type", type="int", required=true, rule="between:1,2", description="类型:1=初级,2=高级")
     * @ApiParams   (name="start_id", type="int", required=false, rule="", description="起始id")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每页数量")
     */
    public function log()
    {
        $box_type = input('box_type');
        $log = Db::name('egg_limit_log')->alias('l')
            ->join('user u', 'l.user_id = u.id', 'left')
            ->join('gift g', 'l.gift_id = g.id', 'left')
            ->field("l.id,l.user_id,l.box_type,l.count_type,l.gift_id,l.secret,l.create_time")
            ->field('u.nickname,u.avatar,g.name,g.image,g.price')
            ->where('box_type', $box_type)
            ->order('l.id desc')
            ->page(1, 100)
            ->select();
        $date = new \util\Date();
        foreach ($log as &$item) {
            $item['create_time_text'] = $date->human_time($item['create_time']) ?? '';
        }
        $this->success('', $log);
    }

    /**
     * 全服记录
     * @ApiReturnParams   (name="type", type="int", description="类型:1=普通,2=神秘,3=五连")
     */
    public function log_v2()
    {
        $box_type = input('box_type') ?: 1;
        $log = Db::name('egg_limit_log_v2')->alias('l')
            ->join('user u', 'l.user_id = u.id', 'left')
            ->join('gift g', 'l.gift_id = g.id', 'left')
            ->field('l.type,l.count_type,l.count,l.create_time')
            ->field('u.nickname,u.avatar,g.name,g.image,g.price')
            ->where('box_type', $box_type)
            ->order('l.id desc')
            ->page(1, 100)
            ->select();
        $date = new \util\Date();
        foreach ($log as &$item) {
            $item['create_time_text'] = $date->human_time($item['create_time']) ?? '';
        }
        $this->success('', ['time' => time(), 'list' => $log]);
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
        $log = Db::name('egg_intact_log')
            ->field("id,box_type,count_type,create_time,gift_json,gift_other")
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
                    $temp = RedisService::getGiftCache($value['gift_id'], ['name', 'image', 'price'], request()->langset());
                    $gifts[$key] = $temp + ['count' => $value['count']];
                }
                $item['gifts'] = $gifts;
                unset($item['gift_json']);

                $data = json_decode($item['gift_other'], true) ?? [];
                $gifts = [];
                foreach ($data as $key => $value) {
                    $temp = RedisService::getGiftCache($value['gift_id'], ['name', 'image', 'price'], request()->langset());
                    $gifts[$key] = $temp + ['count' => $value['count']];
                }
                unset($item['gift_other']);
                $item['gift_other'] = $gifts;
            }
        }
        $this->success('', $log);
    }

    /**
     * 获取雷霆一击礼物信息
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="int", required=true, rule="", description="id")
     */
    public function open_excitedly()
    {
        $log = Db::name('egg_intact_log')
            ->field("id,box_type,count_type,create_time,gift_json,gift_other")
            ->where(['user_id' => $this->auth->id, 'id' => input('id', 0)])
            ->find();
        $gifts = [];
        if ($log) {
            $data = json_decode($log['gift_other'], true) ?? [];
            $gifts = [];
            foreach ($data as $key => $value) {
                $temp = RedisService::getGiftCache($value['gift_id'], ['name', 'image', 'price'], request()->langset());
                $gifts[$key] = $temp + ['count' => $value['count']];
            }
        }

        $this->success('', $gifts);
    }

    /**
     * 开扭
     * @ApiMethod   (get)
     * @ApiParams   (name="box_type", type="int", required=true, rule="between:1,2", description="類型:1=初级,2=高级")
     * @ApiParams   (name="count", type="int", required=true, rule="in:1,10,100", description="次数:1,10,100")
     * @ApiParams   (name="room_id", type="int", required=false, rule="", description="房間號")
     */
    public function open()
    {
        $start = microtime(true);
        $box_type = input('box_type');
        (!get_site_config('egg_switch') || !get_site_config("egg_box{$box_type}_switch")) && $this->error(__('Not yet enabled'));
        $user_id = $this->auth->id;
        $count = abs((int)input('count'));
        if (!in_array($count, [1, 10, 100])) {
            $this->error(__('Parameter %s can not be empty', 'count'));
        }
        $room_id = (int)input('room_id') ?: 0;

        Db::startTrans();
        try {
            $result = EggService::open($user_id, $box_type, $count, $room_id);
            MongoService::dataInsert('fa_egg_log');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $str = $e->getCode() == 405 ? $e->getMessage() : '网络请求异常,请重试';
            $this->error($str, null, $e->getCode());
        }
        $this->success(microtime(true) - $start, $result ?? null);
    }


    /**
     * 规则说明
     */
    public function explain()
    {
        $info = <<<EOF
活動期間，玩家可扮演大力水手波派（Popeye），通過購買裝扮獲取附贈的菠菜罐頭，使用鐵鎬、銅鎬、金鎬在礦坑中挖掘寶藏。 每次挖掘都會獲得隨機獎勵，而金鎬有機會觸發「雷霆一擊」特效，獲得一次免費金鎬挖掘機會並立即開啟！
EOF;
        $desc = [
            ["a" => "鐵鎬1", "b" => "菠菜罐頭挖掘1次", "c" => "獲得1個隨機獎勵",],
            ["a" => "銅鎬10", "b" => "菠菜罐頭連續挖掘10次", "c" => "獲得10個隨機獎勵",],
            ["a" => "金鎬100", "b" => "菠菜罐頭連續挖掘100次", "c" => "獲得100個隨機獎勵，有幾率觸發「雷霆一擊」",],
        ];;
        $gift = '高级礼物';
        $rule = [
            "1.雷霆一擊：一次挖掘獲得特定寶藏可獲得一次免費金鎬挖掘機會，特定寶藏為：{$gift}；",
            '2.獲得的禮物獎勵在背包中查看；',
            '3.您知悉“波派的礦井”是旨在提升平臺娛樂性和用戶活躍度的互動功能，該活動僅供互動娛樂，用戶通過活動獲取獎勵僅限在平臺內消耗使用；',
            '4.活動期間，用戶應當遵守法律法規及平臺規則，如發現用戶違規（包括但不限於使用外掛作弊、惡意套現、反向兌換現金、倒買倒賣、洗錢等違背誠實信用或損害其他用戶、平臺及任何協力廠商合法權益的行為），平臺有權撤銷用戶活動參與資格，收回全部權益，必要時追究法律責任，並有權視情况對用戶採取限制、封禁帳號、凍結資金以做進一步查證處理；',
            '5.任何人不得利用本次活動進行博彩或其他違法行為，對於本平臺用戶，一經發現，本平臺將立即取消用戶參與資格並收回所得獎勵。 如行為構成犯罪，將移交司法機關處理。 如給平臺造成其他損失，同時應當承擔賠償責任；',
            '6.平臺嚴禁並嚴厲打擊任何以盈利為目的的線下非法交易行為，任何人以本平臺名義宣稱或從事類似活動或惡意發放、轉讓、回收、獎勵、欺詐等造成用戶損失的，本平臺無需為此承擔任何法律責任；',
            '7.平臺有權根據活動實際舉辦情况隨時對活動規則進行變動或調整，並將及時公佈在活動頁面上，公佈後依法生效；',
            '8.本活動僅限年齡18歲及以上用戶參加，請參與互動玩法的用戶務必注意健康娛樂，適度消費；',
            '9.本活動與Apple In.無關；',
            '10如對活動規則有任何疑問，請聯系線上客服諮詢。',
        ];
        $percent = Db::name('egg_gift')->alias('w')->join('gift g', 'w.gift_id =g.id')
            ->where('w.status', 1)->order('g.price desc')->order('w.weigh', 'asc')
            ->field('g.name,w.show_rate as percent,g.image,g.price')
            ->select();
        $this->success('', compact('info', 'desc', 'rule', 'percent'));
    }

    /**
     * @ApiInternal
     * @return void
     */
    public function test()
    {
        $index['user_id'] = 101101;
        $index['box_type'] = 1;
        $reward = 2;
        board_notice();
        board_notice_delay(
            Message::CMD_EGG_REWARD_NOTICE,
            array_merge(get_user_info($index['user_id'], ['level', 'noble']),
                ['reward' => $reward, 'box_type' => $index['box_type']])
        );

        $this->success('test success');
    }

}
