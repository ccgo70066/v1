<?php

namespace app\api\controller;

use app\common\exception\ApiException;
use app\common\library\agora\Agora;
use app\common\model\ChannelBlacklist;
use app\common\service\RedisService;
use app\common\service\RoomService;
use app\common\service\UserBusinessService;
use think\Db;

/**
 * 厅
 * @ApiWeigh    (901)
 */
class Member extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 获取厅信息
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",    required=true, rule="require", description="房间id")
     * @ApiReturnParams    (name="status", type="int", description="房间状态:1=审核中,2=休息中,3=开播中,0=禁封,-1=申请注销中,-2=已注销,-3=审核驳回")
     * @ApiReturnParams    (name="role", type="int", description="角色:1=房主,2=管理,3=主播")
     * @ApiReturnParams    (name="role_status", type="int", description="状态:0=申请加入,1=已通过,-1=驳回,2=申请退出,-2=已退出")
     *
     */
    public function get_room_info()
    {
        $user_id = $this->auth->id;
        $room_id = input('room_id');
        $redis = redis();
        $room = db('room r')->where('r.id', $room_id)
            ->field('id,beautiful_id,name,owner_id,intro,cover,status', false, 'r')->find();
        $role = db('room_admin')->where('user_id', $user_id)->where('room_id', $room_id)->where('status', '>=', 0)->find();
        $room['role'] = $role['role'] ?? null;
        $room['role_status'] = $role['status'] ?? null;
        $room['hot'] = $redis->hGet(RedisService::ROOM_HOT_KEY, $room_id) ?: 0;
        $profit = db('room_profit')->where('room_id', $room_id)->find();
        $room['gift_value'] = $profit['gift_val'] ?? 0;
        $room['reward_value'] = $profit['reward_val'] ?? 0;
        $room['member_count'] = db('room_admin')->where('room_id', $room_id)->where('status', 'in', [1, 2])->count();

        $this->success('', $room);
    }

    /**
     * 获取房间成员
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房间ID")
     * @ApiParams   (name="status", type="int",  required=true, rule="", description="类型:1=正常,0=申请加入,2=申请退出")
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="页码大小")
     * @throws
     */
    public function get_list()
    {
        $user_id = $this->auth->id;
        $room_id = input('room_id', 0);
        $extend = input('status') == 2 ? ',a.reason' : '';
        $list = db('room_admin')->alias('a')->join('user u', 'a.user_id = u.id', 'left')
            ->field('a.user_id,a.status,u.id,u.nickname,u.avatar,u.birthday,gender,level' . $extend)
            ->where('a.room_id', $room_id)
            ->where('a.status', input('status', 1))
            ->page(input('page', 1), input('size', 10))->select();
        $user_flow = db('user_follow')->where('user_id', $user_id)->whereIn('to_user_id', array_column((array)$list, 'id'))->column('id', 'to_user_id');
        foreach ($list as &$item) {
            $item['is_follow'] = isset($user_flow[$item['id']]) ? 1 : 0;
            $item['age'] = date('Y') - substr($item['birthday'], 0, 4);
            $item['vip_icon'] = RedisService::getLevelCache($item['level']);
        }

        $this->success('', $list);
    }

    /**
     * 踢出成员
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房间ID")
     * @ApiParams   (name="user_id", type="int",  required=true, rule="", description="用户ID")
     * @return void
     */
    public function kick()
    {
        $room_id = input('room_id', 0);
        $user_id = input('user_id', 0);
        $role = db('room_admin')->where(['room_id' => $room_id, 'user_id' => $this->auth->id, 'role' => ['in', '1,2'], 'status' => 1])->count();
        if (!$role) $this->error(__('You have no permission'));
        (new RoomService())->kick($room_id, $user_id);
        $this->success();
    }

    /**
     * 申请解散
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房间ID")
     * @return void
     */
    public function dismiss()
    {
        $room_id = input('room_id', 0);
        $role = db('room_admin')->where(['room_id' => $room_id, 'user_id' => $this->auth->id, 'status' => 1, 'role' => 1])->count();
        if (!$role) $this->error(__('You have no permission'));
        db('room')->where(['room_id' => $room_id])->setField(['status' => -1]);

        $this->success();
    }


    /**
     * 申请加入/退出
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房间ID")
     * @ApiParams   (name="type", type="int",  required=true, rule="", description="类型:1=加入,2=退出")
     * @ApiParams   (name="reason", type="string",  required=false, rule="", description="原因")
     */
    public function join()
    {
        $type = input('type', 1);
        $room_id = input('room_id', 0);
        $user_id = $this->auth->id;
        if (db('user_business')->where('id', $user_id)->value('role') == 4) $this->error(__('You have no permission'));
        $exist = db('room_admin')->where(['user_id' => $user_id, 'status' => 1])->find();
        if ($type == 1) {
            if ($exist) $this->error(__('You are already in the room'));
            db('room_admin')->insert(['room_id' => $room_id, 'user_id' => $user_id, 'role' => 2,]);
        } else {
            if (!$exist) $this->error(__('You are not in the room'));
            if (!input('reason')) $this->error(__('Please enter the reason'));
            db('room_admin')->where(['room_id' => $room_id, 'user_id' => $user_id,])->update(['status' => 2, 'reason' => input('reason', ''),]);
        }

        $this->success();
    }

    /**
     * 审核
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房间ID")
     * @ApiParams   (name="user_id", type="int",  required=true, rule="", description="用户ID")
     * @ApiParams   (name="status", type="int",  required=true, rule="", description="审核结果:1=同意加入,-1=拒绝加入,-2=同意退出,-3=拒绝退出")
     * @throws
     */
    public function audit()
    {
        $status = input('status', 0);
        $status == -3 && $status = 1;
        $roomServer = new RoomService();
        $roomServer->check(input('room_id'), input('user_id'), $status);
        if (in_array($status, [1, -2])) {
            UserBusinessService::set_user_role(input('user_id'), $status == 1 ? 3 : 1);
        }

        $this->success();
    }

    /**
     * @ApiTitle    (流水奖励兑换)
     * @ApiParams   (name="reward_val",   type="int",     required=true,rule="between:1,9999999", description="申请兑换的流水奖励值")
     * @ApiParams   (name="type",         type="int",     required=true,rule="in:1,2", description="兑换类型:1=转为收益,2=转为金幣")
     **/
    public function withdraw()
    {
        $user_id = $this->auth->id;
        $room = db('room')->where('owner_id', $user_id)->find();
        if ($room['status'] == 1) {
            throw new ApiException(__('Please be patient while the room is being reviewed'));
        } elseif ($room['status'] == 0) {
            throw new ApiException(__('Room banned, operation unavailable'));
        }
        $this->operate_check('union_apply_withdraw' . $room['id'], 2);
        $reward_val = (int)input('reward_val');
        $type = input('type');
        //现有未兑换奖励
        $profit = db('room_profit')->where('room_id', $room['id'])->find();
        $sum_reward_val = $profit['reward_val'];
        $used_reward_val = $profit['used_reward_val'];
        $sur_reward_val = bcsub($sum_reward_val, $used_reward_val, 2);
        if ($sum_reward_val < 1 || $reward_val > $sur_reward_val) {
            $this->error(__('Insufficient balance of flow reward'));
        }
        try {
            Db::startTrans();
            $exec1 = db('room_profit')->where('room_id', $room['id'])
                ->whereRaw("reward_val-used_reward_val >= $reward_val")
                ->setInc('used_reward_val', $reward_val);
            $exec2 = db('room_withdraw')->insert([
                'room_id'    => $room['id'],
                'amount'     => $reward_val,
                'type'       => $type,
                'profit_val' => $reward_val,
                'user_id'    => $user_id,
                'status'     => 2
            ]);
            if (!$exec1 || !$exec2) {
                throw new ApiException(__('Operation failed'));
            }
            if ($type == 1) {
                user_business_change($user_id, 'reward_amount', $reward_val, 'increase', '家族流水奖励兑换收益', 11);
            } else {
                user_business_change($user_id, 'amount', $reward_val, 'increase', '家族收益提领', 11);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success();
    }

}
