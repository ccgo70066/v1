<?php


namespace app\common\model;


use fast\Date;
use think\Db;
use think\Model;
use util\Util;

class MomentComment extends Model
{
    // 表名
    protected $name = 'moment_comment';
    // 追加属性
    protected $append = [];

    /**
     * 评论数统计
     */
    public function countMomentComment()
    {
        $data = [];
        // $list = collection($this->field('moment_id, count(*) as count')->group('moment_id')->select())->toArray();
        $list=db('moment_comment c')
            ->join('blacklist b', 'b.type=1 and b.number=c.user_id and b.end_time > ' . time(), 'left')
            ->field('c.moment_id, count(*) as count')
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->group('c.moment_id')
            ->order('c.create_time', 'desc')
            ->select();
        if ($list) {
            foreach ($list as $v) {
                $data[$v['moment_id']] = $v['count'];
            }
        }
        return $data;
    }

    /**
     * 评论数统计
     */
    public function countMomentCommentByMomentId($momentIdsAr)
    {
        $data = [];
        // $list = collection($this->field('moment_id, count(*) as count')->group('moment_id')->select())->toArray();
        $list=db('moment_comment c')
            ->join('blacklist b', 'b.type=1 and b.number=c.user_id and b.end_time > ' . time(), 'left')
            ->field('c.moment_id, count(*) as count')
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->where('c.moment_id','in',$momentIdsAr)
            ->group('c.moment_id')
            ->select();
        if ($list) {
            foreach ($list as $v) {
                $data[$v['moment_id']] = $v['count'];
            }
        }
        return $data;
    }

    /*
     * 获取动态评论内容
     * @param $userId
     * @param int $limit
     * @return array
     */
    public function getUserComment(array $momentIdsAr, $commentLimit,$user_id =0)
    {
        $data = [];

        $momentUserIdsAr = $this->whereIn('moment_id', $momentIdsAr)->column('user_id');
        if (!$momentUserIdsAr) return[];
        $userInfo = get_users_info($momentUserIdsAr);
        $list = db('moment_comment c')
            ->join('blacklist b', 'b.type=1 and b.number=c.user_id and b.end_time > ' . time(), 'left')
            ->field('c.id,c.moment_id,c.type,c.user_id,c.content,c.to_user_id,c.create_time')
            ->whereIn('c.moment_id', $momentIdsAr)
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->order('c.create_time', 'desc')
            ->page(1, 100)
            ->select();

        $date = new \util\Date();
        foreach ($list as &$v) {
            $v['create_time_text'] = $date->human_time(strtotime($v['create_time'])) ?? '';
            if (isset($data[$v['moment_id']]) && isset($data[$v['moment_id']]) &&
                is_array($data[$v['moment_id']]) && count($data[$v['moment_id']]) == $commentLimit) {
                continue;
            }
            $v['nickname'] = $userInfo[$v['user_id']]['nickname'] ?? '';
            $v['avatar'] = $userInfo[$v['user_id']]['avatar'] ?? '';
            $v['gender'] = $userInfo[$v['user_id']]['gender'] ?? 0;
            $v['to_nickname'] = $userInfo[$v['to_user_id']]['nickname'] ?? '';
            $v['to_avatar'] = $userInfo[$v['to_user_id']]['avatar'] ?? '';
            $v['to_gender'] = $userInfo[$v['to_user_id']]['gender'] ?? 0;
            $v['is_comment_like'] = db('moment_comment_like')->where(['comment_id'=>$v['id'],'user_id'=>$user_id])->count() ? 1 : 0;
            $v['count'] = db('moment_comment_like')->where(['comment_id'=>$v['id']])->count();
            $data[$v['moment_id']][] = $v;
        }

        return $data;
    }

}
