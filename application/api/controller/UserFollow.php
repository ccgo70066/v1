<?php


namespace app\api\controller;

use app\common\exception\ApiException;
use app\common\model\UserBusiness;
use app\common\model\UserFollow as UserFollowModel;
use app\common\service\RedisService;
use think\Cache;
use think\Db;
use think\Log;
use util\Date;

/**
 * 会员关注
 */
class UserFollow extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * @ApiTitle    (获取关注列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="每页数量")
     */
    public function get_follows()
    {
        $page = input('page') ?: 1;
        $size = input('size') ?: 10;
        $user_id = $this->auth->id;
        $where = ['f.user_id' => $user_id];
        $list = db('user_follow f')
            ->join('user u', 'f.to_user_id = u.id', 'left')
            ->field('f.to_user_id as user_id,u.bio,f.create_time')
            ->where($where)
            ->where(['u.status' => 'normal'])
            ->page($page, $size)
            ->order('u.is_online desc,f.update_time desc')
            ->select();

        UserBusiness::bind_userinfo($list);
        $redis = redis();

        //获取所有用户穿戴的头像框
        $wearAdornmentImages = UserBusinessService::getWearAdornmentImages(array_column($list, 'user_id'));
        $date = new Date();
        foreach ($list as &$v) {
            $v['create_time'] = $date->human_time(strtotime($v['create_time'])) ?? '';
            $v['adornment'] = $wearAdornmentImages[$v['user_id']] ?? '';
            $v['room_id'] = $redis->hGet(RedisService::USER_NOW_ROOM_KEY, $v['user_id']) ?: 0;
        }
        $this->success('', $list);
    }


    /**
     * @ApiTitle    (获取粉丝列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="每页数量")
     */
    public function get_fans()
    {
        $page = input('page') ?: 1;
        $size = input('size') ?: 10;
        $user_id = $this->auth->id;
        $where = ['f.to_user_id' => $user_id];
        $list = db('user_follow f')
            ->join('user u', 'f.user_id = u.id', 'left')
            ->field('f.user_id as user_id,f.to_user_id,u.bio,f.create_time')
            ->where($where)
            ->where(['u.status' => 'normal'])
            ->page($page, $size)
            ->order('u.is_online desc,f.update_time desc')
            ->select();

        UserBusiness::bind_userinfo($list);
        $redis = redis();

        //获取所有用户穿戴的头像框
        $wearAdornmentImages = UserBusinessService::getWearAdornmentImages(array_column($list, 'user_id'));

        $followArr = db('user_follow')->where(['user_id' => $user_id])->column('to_user_id');
        $date = new Date();
        foreach ($list as &$v) {
            $v['create_time'] = $date->human_time(strtotime($v['create_time'])) ?? '';
            $v['room_id'] = $redis->hGet(RedisService::USER_NOW_ROOM_KEY, $v['user_id']) ?: 0;
            $v['adornment'] = $wearAdornmentImages[$v['user_id']] ?? '';
            $v['is_follow'] = in_array($v['user_id'], $followArr) ? 1 : 0;
        }
        $this->success('', $list);
    }

    /**
     * @ApiTitle    (获取访客列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="每页数量")
     */
    public function get_visitors()
    {
        $user_id = $this->auth->id;
        $page = input('page') ?: 1;
        $size = input('size') ?: 10;

        $list = db('user_guest g')
            ->join('user u', 'g.user_id = u.id')
            ->field('u.id as user_id,g.to_user_id,u.nickname,u.avatar,u.gender,u.bio,u.is_online,g.count,g.create_time')
            ->where([
                'u.status'     => 'normal',
                'g.to_user_id' => $user_id,
            ])->page($page, $size)
            ->order('u.is_online desc,g.update_time desc')->select();

        $redis = redis();
        $wearAdornmentImages = UserBusinessService::getWearAdornmentImages(array_column($list, 'user_id'));
        $followArr = db('user_follow')->where(['user_id' => $user_id])->column('to_user_id');
        $date = new Date();
        foreach ($list as &$v) {
            $v['create_time'] = $date->human_time(strtotime($v['create_time'])) ?? '';
            $v['room_id'] = $redis->hGet(RedisService::USER_NOW_ROOM_KEY, $v['user_id']) ?: 0;
            $v['adornment'] = $wearAdornmentImages[$v['user_id']] ?? '';
            $v['is_follow'] = in_array($v['user_id'], $followArr) ? 1 : 0;
        }
        $this->success('', $list);
    }

    /**
     * @ApiTitle    (关注/取消关注)
     * @ApiMethod   (post)
     * @ApiParams   (name="type", type="int",  required=true, rule="in:1,2", description="1=关注,2=取消关注")
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="", description="关注/取关的用戶對象id")
     */
    public function follow()
    {
        $userId = $this->auth->id;
        $type = input('type');
        $to_user_id = input('to_user_id');
        $this->operate_check('follow_lock:' . $this->auth->id . '_' . $type . '_' . $to_user_id, 2);
        $to_user_id == $userId && $this->error(__('Cannot operate on yourself'));
        $exist = db('user')->where('id', $to_user_id)->count();
        !$exist && $this->error(__('User does not exist'));

        try{
            $type == 1 && UserFollowModel::follow($userId, $to_user_id);
            $type == 2 && UserFollowModel::unfollow($userId, $to_user_id);

            Cache::rm('moment:follow_ids:' . $userId);
            Cache::rm('moment:follow_ids:' . $to_user_id);
        }catch (\Exception $e){
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }

        $this->success(__('Operation completed'));
    }


    /**
     * @ApiTitle    (判斷是否關注用戶)
     * @ApiMethod   (get)
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="", description="用戶id")
     */
    public function check()
    {
        $userId = $this->auth->id;
        $to_user_id = input('to_user_id');

        $count = (bool)db('user_follow')->where(['user_id' => $userId, 'to_user_id' => $to_user_id])->find();
        $this->success('', $count);
    }


    /**
     * 添加用户到黑名单
     * @ApiMethod   (post)
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="", description="屏蔽對象")
     */
    public function add_blacklist()
    {
        $to_user_id = input('to_user_id');
        $userId = $this->auth->id;
        Db::startTrans();
        try{
            $exist = db('user_blacklist')->where([
                'user_id'    => $userId,
                'to_user_id' => $to_user_id
            ])->find();
            if ($exist) {
                throw new ApiException(__('This user is already in blacklist'));
            }

            $result = db('user_blacklist')->insert(['user_id' => $userId, 'to_user_id' => $to_user_id]);
            if ($result) {
                UserBaseStatisticsService::setUserStatistics($userId, 'blacklist_num', 'increase');
            }
            Db::commit();
            $imService = new ImService();
            $imService->setBlacklist($userId, $to_user_id, true);

            Cache::rm('moment:follow_ids:' . $userId);
            Cache::rm('moment:follow_ids:' . $to_user_id);
        }catch (\Exception $e){
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('Operation completed'));
    }

    /**
     * 获取我的用户黑名单列表
     * @ApiMethod   (get)
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="每页数量")
     */
    public function get_blacklist()
    {
        $page = input('page') ?: 1;
        $size = input('size') ?: 10;
        $list = db('user_blacklist b')
            ->join('user u', 'b.to_user_id = u.id')
            ->field('u.id as user_id,u.nickname,u.beautiful_id,u.avatar,u.gender,u.bio,u.is_online,b.create_time')
            ->where('b.user_id', $this->auth->id)
            ->where('u.status', 'normal')
            ->page($page, $size)
            ->order('u.is_online desc,b.update_time desc')
            ->select();
        $redis = redis();
        $date = new Date();
        foreach ($list as &$v) {
            $v['create_time'] = $date->human_time(strtotime($v['create_time'])) ?? '';
            $v['room_id'] = $redis->hGet(RedisService::USER_NOW_ROOM_KEY, $v['user_id']) ?: 0;
            $v['adornment'] = '';
        }
        $this->success('', $list);
    }

    /**
     * 移除用户黑名单
     * @ApiMethod   (post)
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="", description="移除對象")
     */
    public function remove_blacklist()
    {
        try{
            Db::startTrans();
            $result = db('user_blacklist')
                ->where('user_id', $this->auth->id)
                ->where('to_user_id', input('to_user_id'))
                ->delete();
            if ($result) {
                UserBaseStatisticsService::setUserStatistics($this->auth->id, 'blacklist_num', 'decrease');
            }
            Db::commit();
            $imService = new ImService();
            $imService->setBlacklist($this->auth->id, input('to_user_id'), false);
        }catch (\Exception $e){
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('Operation completed'));
    }


}
