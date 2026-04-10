<?php

namespace app\api\controller;

/**
 * 厅
 * @ApiWeigh    (901)
 */
class Member extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

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
        $room_id = input('room_id', 0);
        $extend = input('status') == 2 ? ',a.reason' : '';
        $list = db('room_admin')->alias('a')->join('user u', 'a.user_id = u.id', 'left')
            ->field('a.user_id,a.status,u.id,u.nickname,u.avatar' . $extend)
            ->where('a.room_id', $room_id)
            ->where('a.status', input('status', 1))
            ->page(input('page', 1), input('size', 10))->select();

        $this->success('', $list);
    }

    /**
     * 申请加入/退出
     * @ApiMethod   (post)
     * @ApiParams   (name="room_id", type="int",  required=true, rule="", description="房间ID")
     * @ApiParams   (name="type", type="int",  required=true, rule="", description="类型:1=加入,2=退出")
     * @ApiParams   (name="reason", type="string",  required=true, rule="", description="原因")
     */
    public function join()
    {
        $type = input('type', 1);
        $room_id = input('room_id', 0);
        $user_id = $this->auth->id;
        $exist = db('room_admin')->where(['user_id' => $user_id, 'status' => 1])->find();
        if ($type == 1) {
            if ($exist) $this->error(__('You are already in the room'));
            db('room_admin')->insert([
                'room_id' => $room_id,
                'user_id' => $user_id,
                'status'  => 0,
                'reason'  => input('reason', ''),
            ]);
        } else {
            if (!$exist) $this->error(__('You are not in the room'));
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
        db('room_admin')->where([
            'room_id' => input('room_id'),
            'user_id' => input('user_id'),
        ])->setField('status', $status);

        $this->success();
    }

    public function statistic()
    {

    }
}
