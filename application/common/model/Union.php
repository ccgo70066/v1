<?php

namespace app\common\model;

use app\common\model\Shield;
use app\common\exception\ApiException;
use think\Model;

class Union extends Model
{

    // 表名
    protected $name = 'union';
    //union_user家族成员
    const STATUS_APPLYING = 1;//申请中
    const STATUS_JOINED = 2;//已通过
    const STATUS_EXITING = 3;//退出家族中
    const STATUS_APPLY_REJECT = 4;//加入家族申请驳回
    const STATUS_EXIT_REJECT = 6;//退出家族申请驳回
    const STATUS_ADMIN_AUDIT = 7;//运营审核中  //废弃
    //属于加入家族中的状态集合
    const STATUS_JOINED_RANGE = [
        self::STATUS_JOINED,
        self::STATUS_EXITING,
        self::STATUS_EXIT_REJECT,
        //self::STATUS_ADMIN_AUDIT
    ];
    //union 家族
    const UNION_STATUS_AUDIT = 0;   //申请中
    const UNION_STATUS_NORMAL = 1;  //正常
    const UNION_STATUS_BAN = 2;  //封禁


    /**
     * 新增家族操作日志
     * @param        $union_id     int 家族号
     * @param        $user_id      int 操作人
     * @param        $text         string 内容
     * @param        $to_user_id   int 被操作人
     */
    public function add_union_log($union_id, $user_id, $text = '', $to_user_id = 0)
    {
        db('union_log')->insert([
            'user_id'    => $user_id,
            'union_id'   => $union_id,
            'action'     => $text,
            'to_user_id' => $to_user_id,
        ]);
    }

    /**
     * 创建家族
     * @param $user_id
     * @return bool
     */
    public function createUnion($user_id, $data)
    {
        try {
            $sel = db('union_user')->where(['user_id' => $user_id, 'status' => ['in', Union::STATUS_JOINED_RANGE]])->find();
            if ($sel) {
                throw new ApiException(__('You have applied to join another clan'));
            }

            $sel = db('union')->where(['owner_id' => $user_id, 'status' => ['neq', 3]])->find();
            if ($sel) {
                throw new ApiException(__('You already have a clan'));
            }
            if (!isset($data['logo']) || !isset($data['name']) || !isset($data['content'])) {
                throw new ApiException(__('Application information incomplete'));
            }
            db('union_user')->where('user_id', $user_id)->delete();
            $union_data = [
                'logo'     => $data['logo'],
                'name'     => Shield::sensitive_filter($data['name']),
                'content'  => Shield::sensitive_filter($data['content']),
                'status'   => 0,
                'owner_id' => $user_id,
            ];
            db('union')->max('id') < 100000 && $union_data['id'] = 100001;
            $id = db('union')->insertGetId($union_data);
            db('user_business')->where('id', $user_id)->setField(['role' => 4]);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
        return (bool)$id;
    }

}
