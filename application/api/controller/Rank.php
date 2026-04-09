<?php

namespace app\api\controller;

use app\api\library\RankService;
use app\api\library\RedisService;
use app\api\library\UserBusinessService;
use app\common\library\token\driver\Redis;
use app\common\model\GiftSendStatistic;

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
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=周榜,2=日榜")
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
        $cache = GiftSendStatistic::get_rank_data($range, 1, $room_id);
        !$cache && $this->success('', []);
        $result = array_slice($cache, ($page - 1) * $size, $size);
        $this->data_format($result, $room_id);
        //foreach ($result as &$value) {
        //    $value['nickname'] = '用户' . substr($value['user_id'], 0, 2) . '****' . substr($value['user_id'], -1, 1);
        //}
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (房间魅力榜)
     * @ApiSummary  (返回值中value为0，榜单值不显示)
     *
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=周榜,2=日榜")
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

        $cache = GiftSendStatistic::get_rank_data($range, 2, $room_id);
        !$cache && $this->success('', []);
        $result = array_slice($cache, ($page - 1) * $size, $size);
        $this->data_format($result, $room_id);

        $this->success('', $result);
    }

    /**
     * 房间的贵族榜
     * @ApiSummary  (返回值中value为0，榜单值不显示)
     *
     * @ApiParams   (name="page",   type="int", required=false, rule="", description="頁碼,默認1")
     * @ApiParams   (name="size",   type="int", required=false, rule="", description="分頁大小,默認20")
     * @ApiParams   (name="room_id",   type="int", required=true,  rule="require|min:0", description="房间ID")
     */
    public function room_noble()
    {
        $page = input('page') ?: 1;
        $size = 20;
        $page > 5 && $this->success();

        $result = db('room_enter_log r')
            ->join('user u', 'u.id=r.user_id and u.hidden_noble=0', 'left')
            ->join('user_business ub', 'ub.id=r.user_id', 'left')
            ->join('user_noble un', 'r.user_id = un.user_id')
            ->join('noble n', 'un.noble_id = n.id')
            ->where('r.room_id', input('room_id'))
            ->where(['un.end_time' => ['gt', datetime()]])
            ->field('r.user_id,n.badge,u.hidden_level,u.nickname,u.avatar,ub.level')
            ->group('r.user_id')
            ->order('n.weigh desc,un.end_time desc')
            ->page($page, $size)
            ->select();
        $this->data_format($result, input('room_id'));
        //foreach ($result as &$value) {
        //    $value['nickname'] = '用户' . substr($value['user_id'], 0, 2) . '****' . substr($value['user_id'], -1, 1);
        //}
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (财富榜)
     * @ApiSummary  (返回值中value为0，榜单值不显示)
     *
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=周榜,2=日榜")
     * @ApiParams   (name="page",   type="int", required=false, rule="", description="頁碼,默認1")
     */
    public function contribution()
    {
        $range = input('type');
        $page = input('page') ?: 1;
        $size = 20;
        $page > 5 && $this->success('', []);

        $cache = GiftSendStatistic::get_rank_data($range, 1);
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
     * @ApiParams   (name="type",   type="int", required=true,  rule="require|min:0", description="類別:1=周榜,2=日榜")
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

        $cache = GiftSendStatistic::get_rank_data($range, 2);
        !$cache && $this->success('', []);
        $result = array_slice($cache, ($page - 1) * $size, $size);
        $this->data_format($result);
        $this->success('', $result);
    }


    /**
     * 贵族榜
     * @ApiParams   (name="page",   type="int", required=false, rule="", description="頁碼,默認1")
     *
     * @ApiReturnParams   (name="room_id", type="string",  description="派对id：>0在派对中")
     */
    public function noble()
    {
        $page = input('page') ?: 1;
        $size = 20;
        $page > 5 && $this->success();

        $result = db('user_noble l')
            ->join('user u', 'u.id=l.user_id and u.hidden_noble=0', 'left')
            ->join('user_business ub', 'ub.id=l.user_id', 'left')
            ->join('noble n', 'l.noble_id=n.id', 'left')
            ->where(['l.end_time' => ['gt', datetime()]])
            ->field('l.user_id,n.badge,u.hidden_level,u.nickname,u.avatar,ub.level')
            ->order('n.weigh desc,l.end_time desc')
            ->page($page, $size)
            ->select();
        $this->data_format($result);
        //foreach ($result as &$value) {
        //    $value['nickname'] = '用户' . substr($value['user_id'], 0, 2) . '****' . substr($value['user_id'], -1, 1);
        //}
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
        //return;
        if (count($result)) {
            //非家族成员-不可见榜单值
            $userId = $this->auth->id;
            //$valueShow = (RankService::checkIsUnionUser($room_id, $userId));

            $levelIconMap = RedisService::getLevelCache('all');
            $wearAdornmentImages = UserBusinessService::getWearAdornmentImages(array_column($result, 'user_id'));
            foreach ($result as &$item) {
                //if (!$valueShow) {
                //    $item['value'] = 0;
                //}
                //if ($item['hidden_level'] == 1) {
                //    $item['level'] = 0;
                //}
                $item['adornment'] = $wearAdornmentImages[$item['user_id']] ?? '';
                $item['level'] = $levelIconMap[$item['level']] ?? '';
                unset($item['hidden_level']);
            }
        }
    }


}
