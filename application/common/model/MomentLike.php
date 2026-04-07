<?php


namespace app\common\model;


use think\Db;
use think\Model;

class MomentLike extends Model
{
    // 表名
    protected $name = 'moment_like';
    // 追加属性
    protected $append = [];

    /**
     * 点赞数统计
     */
    public function countMomentLike()
    {
        $data = [];
        // $list = collection($this->field('moment_id, count(*) as count')->group('moment_id')->select())->toArray();
        $list = db('moment_like l')
            ->join('blacklist b', 'b.type=1 and b.number=l.user_id and b.end_time > ' . time(), 'left')
            ->field('l.moment_id,count(*) as count')
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->group('l.moment_id')
            ->select();
        if ($list) {
            foreach ($list as $v) {
                $data[$v['moment_id']] = $v['count'];
            }
        }
        return $data;
    }

    /**
     * 指定动态点赞数统计
     */
    public function countMomentLikeByMomentId($momentIds)
    {
        $data = [];
        $list = db('moment_like l')
            ->join('blacklist b', 'b.type=1 and b.number=l.user_id and b.end_time > ' . time(), 'left')
            ->field('l.moment_id,count(*) as count')
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->where('l.moment_id','in',$momentIds)
            ->group('l.moment_id')
            ->select();
        if ($list) {
            foreach ($list as $v) {
                $data[$v['moment_id']] = $v['count'];
            }
        }
        return $data;
    }
    /**
     * 获取动态点赞用户结果集
     */
    public function getUserLike(array $momentIdsAr)
    {
        $data = [];
        $list = db('moment_like')->alias('ml')
            ->join('user u', 'u.id=ml.user_id')
            ->field('u.avatar, ml.moment_id,ml.user_id, u.nickname')
            ->whereIn('ml.moment_id', $momentIdsAr)
            ->order('ml.create_time', 'desc')
            ->page(1, 500)
            ->select();
        if (!empty($list)) {
            foreach ($list as $v) {
                $data[$v['moment_id']][] = $v;
            }
        }
        return $data;
    }

    /**
     * 获取某用户点赞的动态id结果集
     * @param $userId
     */
    public function getUserLikeIds($userId)
    {
        $list = $this->where('user_id', $userId)->column('moment_id');
        return $list;
    }

}
