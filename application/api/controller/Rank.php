<?php

namespace app\api\controller;

use app\common\model\GiftSendStatistic;
use app\common\service\RankService;
use app\common\service\RedisService;
use app\common\service\UserBusinessService;

/**
 * 榜单
 * @ApiWeigh    (2002)
 */
class Rank extends Base
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * @ApiTitle    (房间财富榜)
     * @ApiSummary  (返回值中value为0，榜单值不显示)
     *
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=今日,2=昨日,3=本周,4=上周")
     * @ApiParams   (name="room_id",   type="int", required=true,  rule="require|min:0", description="房间ID")
     * @ApiParams   (name="page",   type="int", required=false, rule="", description="頁碼,默認1")
     */
    public function room_contribution()
    {
        $range = input('type');
        $room_id = input('room_id');
        $page = input('page') ?: 1;
        $size = 20;
        $page > 5 && $this->success('', []);
        //查询榜单数据
        $cache = RankService::instance()->get_rank_data($range, 1, $room_id);
        !$cache && $this->success('', []);
        $result = array_slice($cache, ($page - 1) * $size, $size);
        $this->data_format($result, $room_id);
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (房间魅力榜)
     * @ApiSummary  (返回值中value为0，榜单值不显示)
     *
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=今日,2=昨日,3=本周,4=上周")
     * @ApiParams   (name="room_id",   type="int", required=true,  rule="require|min:0", description="房间ID")
     * @ApiParams   (name="page",   type="int", required=false, rule="", description="頁碼,默認1")
     */
    public function room_charm()
    {
        $range = input('type');
        $room_id = input('room_id');
        $page = input('page') ?: 1;
        $size = 20;
        $page > 5 && $this->success('', []);

        $cache = RankService::instance()->get_rank_data($range, 2, $room_id);
        !$cache && $this->success('', []);
        $result = array_slice($cache, ($page - 1) * $size, $size);
        $this->data_format($result, $room_id);

        $this->success('', $result);
    }


    /**
     * @ApiTitle    (财富榜)
     * @ApiSummary  (返回值中value为0，榜单值不显示)
     *
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=今日,2=昨日,3=本周,4=上周")
     * @ApiParams   (name="page",   type="int", required=false, rule="", description="頁碼,默認1")
     */
    public function contribution()
    {
        $range = input('type');
        $page = input('page') ?: 1;
        $size = 20;
        $page > 5 && $this->success('', []);

        $cache = RankService::instance()->get_rank_data($range, 1, 0);
        !$cache && $this->success('', []);
        $result = array_slice($cache, ($page - 1) * $size, $size);
        $this->data_format($result);
        //foreach ($result as &$value) {
        //    $value['nickname'] = '用户' . substr($value['user_id'], 0, 2) . '****' . substr($value['user_id'], -1, 1);
        //}
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (魅力榜)
     * @ApiSummary  (返回值中value为0，榜单值不显示)
     *
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=今日,2=昨日,3=本周,4=上周")
     * @ApiParams   (name="page",   type="int", required=false, rule="", description="頁碼,默認1")
     * @ApiParams   (name="size",   type="int", required=false, rule="", description="分頁大小,默認20")
     *
     * @ApiReturnParams   (name="room_id", type="string",  description="派对id：>0在派对中")
     * @ApiReturnParams   (name="value", type="string",  description="榜单值：=0榜单值不显示")
     */
    public function charm()
    {
        $range = input('type');
        $page = input('page') ?: 1;
        $size = 20;
        $page > 5 && $this->success('', []);

        $cache = RankService::instance()->get_rank_data($range, 2, 0);
        !$cache && $this->success('', []);
        $result = array_slice($cache, ($page - 1) * $size, $size);
        $this->data_format($result);
        $this->success('', $result);
    }


    /**
     * 榜单数据格式
     * @param $result
     * @param $room_id string 房间内榜单房间号
     * @return void
     */
    protected function data_format(&$result, $room_id = 0)
    {
        if (count($result)) {
            $levelIconMap = RedisService::getLevelCache('all');
            $wearAdornmentImages = UserBusinessService::getWearAdornmentImages(array_column($result, 'user_id'));
            foreach ($result as &$item) {
                $item['adornment'] = $wearAdornmentImages[$item['user_id']] ?? '';
                $item['level'] = $levelIconMap[$item['level']] ?? '';
                unset($item['hidden_level']);
            }
        }
    }


}
