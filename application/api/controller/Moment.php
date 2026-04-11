<?php


namespace app\api\controller;

use app\common\model\Moment as MomentModel;
use app\common\model\MomentComment;
use app\common\model\MomentLike;
use app\common\model\Shield;
use app\common\model\UserFollow as UserFollowModel;
use app\common\model\UserGuest as UserGuestModel;
use think\Cache;
use think\Db;
use think\Exception;
use think\Log;
use util\Date;

/**
 * 动态
 */
class Moment extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';
    protected $momentFieldData = 'm.id,m.user_id,m.content,m.images,m.video,m.audio,m.audio_size,m.publish,m.create_time,m.status';

    /**
     * 获取推荐动态列表
     * @ApiSummary  ("stutas=2:审核中的,status=1审核通过的")
     * @ApiMethod   (get)
     * @ApiParams   (name="size", type="int",  required=true, rule="", description="每页数量")
     * @ApiParams   (name="start_id", type="int",  required=true, rule="", description="起始id")
     */
    public function get_commend()
    {
        $userId = $this->auth->id;
        $size = input('size') ?: 10;
        $start_id = input('start_id') ?: 0;
        $where = ['u.status' => 'normal'];
        $filterUserIds = $this->getFilterUserIds($userId);
        $blackUserIds = db('user_blacklist')->where('user_id', $userId)->column('to_user_id');
        $momentBlackUserIds = db('moment_blacklist')->where('user_id', $userId)->column('to_user_id');

        if ($start_id) {
            $where['m.id'] = ['<', $start_id];
        }
        $list = db('moment m')
            ->join('user u', 'm.user_id=u.id', 'left')
            ->field($this->momentFieldData)
            ->where('m.status', MomentModel::StatusOn)
            ->whereNotIn('m.id', $filterUserIds)
            ->where('m.create_time', '>', '2023-10-20 00:00:00')
            ->whereNotIn('m.user_id', $blackUserIds)
            ->whereNotIn('m.user_id', $momentBlackUserIds)
            ->where($where)
            ->order('m.id', 'desc')
            ->page(1, $size)
            ->select();

        if ($list) {
            $list = $this->formatResult($list, $userId, 2);
        }

        $this->success('', $list);
    }


    /**
     * @ApiTitle    (动态列表带搜索)
     * @ApiMethod   (get)
     * @ApiParams   (name="size", type="int",  required=true, rule="", description="每页数量")
     * @ApiParams   (name="start_id", type="int",  required=true, rule="", description="起始id")
     * @ApiParams   (name="title", type="string",  required=false, rule="", description="搜索内容或者话题名称")
     */
    public function get_moment_list()
    {
        $userId = $this->auth->id;
        $size = input('size') ?: 10;
        $start_id = input('start_id') ?: 0;
        $title = input('title') ?: '';
        if ($title) $map = ['m.content' => ['like', "%{$title}%"]];
        $where = ['u.status' => 'normal'];
        $filterUserIds = $this->getFilterUserIds($userId);
        $blackUserIds = db('user_blacklist')->where('user_id', $userId)->column('to_user_id');
        $momentBlackUserIds = db('moment_blacklist')->where('user_id', $userId)->column('to_user_id');

        if ($start_id) {
            $where['m.id'] = ['<', $start_id];
        }
        $list = db('moment m')
            ->join('user u', 'm.user_id=u.id', 'left')
            ->field($this->momentFieldData)
            ->where('m.status', MomentModel::StatusOn)
            ->whereNotIn('m.id', $filterUserIds)
            ->where('m.create_time', '>', '2023-10-20 00:00:00')
            ->whereNotIn('m.user_id', $blackUserIds)
            ->whereNotIn('m.user_id', $momentBlackUserIds)
            ->where($where)
            ->where($map)
            ->whereOr($map1)
            ->order('m.id', 'desc')
            ->page(1, $size)
            ->select();

        if ($list) {
            $list = $this->formatResult($list, $userId, 2);
        }

        $this->success('', $list);
    }

    /**
     * @ApiTitle    (获取关注动态列表)
     * @ApiSummary  (自已发的全部可见, 我关注的屏蔽仅自己可见)
     * @ApiMethod   (get)
     * @ApiParams   (name="size", type="int",  required=true, rule="", description="每页数量")
     * @ApiParams   (name="start_id", type="int",  required=true, rule="", description="起始id")
     */
    public function get_follow()
    {
        $userId = $this->auth->id;
        $size = input('size') ?: 10;
        $start_id = input('start_id') ?: 0;

        [$followUserIds, $followByUserIds] = Cache::remember('moment:follow_ids:' . $userId, function () use ($userId) {
            Cache::tag('moment', 'moment:follow_ids:' . $userId);
            $followUserIds = db('user_follow')->where('user_id', $userId)->column('to_user_id') ?? [];
            $blackUserIds = db('user_blacklist')->where('user_id', $userId)->column('to_user_id');
            $momentBlackUserIds = db('moment_blacklist')->where('user_id', $userId)->column('to_user_id');
            $followUserIds = array_diff($followUserIds, array_merge($blackUserIds, $momentBlackUserIds));
            $followByUserIds = db('user_follow')->where('to_user_id', $userId)->column('user_id') ?? [];
            return [$followUserIds, $followByUserIds];
        }, 2 * 60);

        $list = db('moment')->alias('m')
            ->field($this->momentFieldData)
            ->where('m.create_time', '>', '2023-10-20 00:00:00')
            ->where(function ($query) use ($userId, $followUserIds, $followByUserIds) {
                $query->whereOr(function ($query) use ($userId) {
                    $query->where('user_id', $userId)->where("status", 1);
                })->whereOr(function ($query) use ($followUserIds) {
                    $query->whereIn('user_id', $followUserIds)->where("status", 1)->where('publish', 'not in', [2, 4]);
                })->whereOr(function ($query) use ($followByUserIds) {
                    $query->whereIn('user_id', $followByUserIds)->where('status', 1)->where('publish', 2);
                });
            })
            ->where($start_id ? ['m.id' => ['<', $start_id]] : [])
            ->order('m.id', 'desc')
            ->page(1, $size)
            ->select();
        $this->success('', $this->formatResult($list, $userId, 2));
    }


    /**
     * @ApiTitle    (获取用户/个人主页动态列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int",  required=false, rule="", description="用户id-当前登录用户不用传")
     *
     * @ApiParams   (name="system", type="int",  required=false, rule="in:1,2", description="系统:1=ios,2=android")
     * @ApiParams   (name="version", type="string",  required=false, rule="", description="版本号")
     * @ApiParams   (name="appid", type="string",  required=false, rule="", description="渠道号")
     *
     * @ApiParams   (name="size", type="int",  required=true, rule="", description="每页数量")
     * @ApiParams   (name="start_id", type="int",  required=true, rule="", description="起始id")
     */
    public function get_user_moment()
    {
        try {
            $userId = input('user_id') ?: $this->auth->id;
            $size = input('size') ?: 10;
            $start_id = input('start_id') ?: 0;
            if (!db('user')->where('id', $userId)->count(1)) {
                $this->error(__('User does not exist'));
            }
            // 有屏蔽时 个人主页动态全部返回空.
            if ($userId == $this->auth->id) {
                $where = [
                    'user_id' => $userId,
                    'status'  => ['in', [MomentModel::StatusOn, MomentModel::StatusAudit]],
                ];
            } else {
                $where = [
                    'user_id' => $userId,
                    'status'  => MomentModel::StatusOn,
                ];
            }

            $filterUserIds = [];
            $is_blacklist = 0;
            if ($userId != $this->auth->id) {
                $filterUserIds = $this->getFilterUserIds($this->auth->id);
                $map = [
                    'user_id'    => $this->auth->id,
                    'to_user_id' => $userId,
                ];
                $is_blacklist = db('moment_blacklist')->where($map)->limit(1)->count(1);
            }

            if ($start_id) {
                $where['m.id'] = ['<', $start_id];
            }
            $list = db('moment m')
                ->field($this->momentFieldData)
                ->where('m.create_time', '>', '2023-10-20 00:00:00')
                ->where($where)
                ->whereNotIn('id', $filterUserIds)
                ->order('m.id', 'desc')
                ->page(1, $size)
                ->select();
            if ($list) {
                $list = $this->formatResult($list, $this->auth->id, 2);
                foreach ($list as &$v) {
                    $v['is_blacklist'] = $is_blacklist;
                }
            }
            $this->success('', $list);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->error($e->getMessage());
        }
    }


    /**
     * @ApiTitle    (获取动态详情)
     * @ApiMethod   (get)
     * @ApiParams   (name="id", type="int",  required=true, rule="", description="动态id")
     * @param MomentLike $momentLike
     */
    public function info(MomentLike $momentLike)
    {
        try {
            $userId = $this->auth->id;
            $momentId = input('id');
            $info = db('moment m')->field($this->momentFieldData)->where('id', $momentId)->find();
            if ($info) {
                $info = $this->formatResult([$info], $userId)[0];
                $userLike = $momentLike->getUserLike([$info['id']]);
                $info['like_list'] = $userLike[$info['id']] ?? [];
            }
            $this->success('', $info);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->error($e->getMessage());
        }
    }

    /**
     * @ApiTitle    (发布动态)
     * @ApiMethod   (post)
     * @ApiParams   (name="content", type="string",  required=false, rule="", description="文字内容")
     * @ApiParams   (name="image", type="string",  required=false, rule="", description="图片内容")
     * @ApiParams   (name="video", type="string",  required=false, rule="", description="视频内容")
     * @ApiParams   (name="audio", type="string",  required=false, rule="", description="音频内容")
     * @ApiParams   (name="audio_size", type="int",  required=false, rule="", description="音频时长")
     * @ApiParams   (name="publish", type="string",  required=true, rule="in:0,1,2,3,4", description="隐私:0=公开,1=粉丝,2=关注,3=访客,4=自己")
     * @param MomentModel $momentModel
     */
    public function add(MomentModel $momentModel)
    {
        $this->operate_check('moment_lock:' . $this->auth->id, 2);
        if (!(input('image') || input('video') || input('audio') || input('content'))) {
            $this->error(__('Moment cannot be empty'));
        }
        if (input('audio') && (!input('audio_size') || !is_numeric(input('audio_size')))) {
            $this->error('Publish audio updates, audio duration cannot be empty');
        }
//        if (input('content') && mb_strlen(input('content')) >= 500) {
//            $this->error('文字内容不能超过500字');
//        }
        Db::startTrans();
        try {
            $userId = $this->auth->id;
            $data = [
                'user_id'    => $userId,
                'content'    => Shield::sensitive_filter(input('content') ?: ''),
                'images'     => input('image') ?: '',
                'video'      => input('video') ?: '',
                'audio'      => input('audio') ?: '',
                'audio_size' => input('audio_size') ?: 0,
                'publish'    => input('publish'),
                'status'     => MomentModel::StatusAudit,
            ];
            $momentModel->save($data);

            Db::commit();
//            Enigma::send_check_message("会员中心--->广场动态  - 用户: {$userId} 提交了新的记录，需要审核！");
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('success'));
    }

    /**
     * @ApiTitle    (删除动态)
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="int",  required=true, rule="", description="动态id")
     * @param MomentModel   $momentModel
     * @param MomentLike    $momentLike
     * @param MomentComment $momentComment
     */
    public function del(MomentModel $momentModel, MomentLike $momentLike, MomentComment $momentComment)
    {
        $this->operate_check('moment_del_lock:' . $this->auth->id, 2);
        Db::startTrans();
        try {
            $userId = $this->auth->id;
            $momentId = input('id');

            $where = [
                'id'      => $momentId,
                'user_id' => $userId,
            ];
            $info = $momentModel->where($where)->delete();
            if ($info) {
                $momentLike->where('moment_id', $momentId)->delete();
                $momentComment->where('moment_id', $momentId)->delete();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success('Success');
    }

    /**
     * @ApiTitle    (动态点赞/取消点赞)
     * @ApiMethod   (post)
     * @ApiParams   (name="type", type="int",  required=true, rule="", description="1=点赞,2=取消点赞")
     * @ApiParams   (name="moment_id", type="int",  required=true, rule="", description="动态id")
     * @param MomentModel $momentModel
     * @param MomentLike  $momentLike
     */
    public function like(MomentModel $momentModel, MomentLike $momentLike)
    {
        $this->operate_check('moment_like_lock:' . $this->auth->id, 1);
        $userId = $this->auth->id;
        $momentId = input('moment_id');
        if (!$momentModel->find($momentId)) {
            $this->error(__('No results were found'));
        }

        Db::startTrans();
        try {
            $data = ['moment_id' => $momentId, 'user_id' => $userId,];
            if (input('type') == 1) {
                if ($momentLike->where($data)->find()) {
                    Db::commit();
                    $this->success();
                }
                $momentLike->save($data);
            }
            if (input('type') == 2) {
                $momentLike->where($data)->delete();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            dump($e->getLine());
            dump($e->getTraceAsString());
            dump($e->getFile());
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success();
    }


    /**
     * @ApiTitle    (评论点赞/取消点赞)
     * @ApiMethod   (post)
     * @ApiParams   (name="type", type="int",  required=true, rule="in:1,2", description="1=点赞,2=取消点赞")
     * @ApiParams   (name="comment_id", type="int",  required=true, rule="", description="评论id")
     */
    public function comment_like()
    {
        $this->operate_check('moment_comment_like_lock:' . $this->auth->id, 2);
        $userId = $this->auth->id;
        $comment_id = input('comment_id');
        $type = input('type');
        $comment_info = db('moment_comment')->where('id', $comment_id)->find();
        if (!$comment_info) {
            $this->error(__('No results were found'));
        }

        Db::startTrans();
        try {
            $data = ['comment_id' => $comment_id, 'user_id' => $userId];
            if ($type == 1) {
                db('moment_comment_like')->insert($data);
            }
            if ($type == 2) {
                db('moment_comment_like')->where($data)->delete($data);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success();
    }


    /**
     * @ApiTitle    (评论)
     * @ApiMethod   (post)
     * @ApiParams   (name="moment_id", type="int",  required=true, rule="", description="动态id")
     * @ApiParams   (name="content", type="string",  required=true, rule="", description="评论内容")
     * @param MomentComment $momentComment
     */
    public function comment(MomentComment $momentComment)
    {
        $userId = $this->auth->id;
        $momentId = input('moment_id');
        $content = input('content');
        if (!db('moment')->find($momentId)) {
            $this->error(__('No results were found'));
        }
//        if (mb_strlen($content) > 500) {
//            $this->error('评论内容不能超过500字');
//        }
        $lock = locked('comment_lock_' . $userId, 2);
        if ($lock) {
            $this->error(__('Operation too fast'));
        }

        Db::startTrans();
        try {
            $data = [
                'moment_id' => $momentId,
                'user_id'   => $userId,
                'content'   => Shield::sensitive_filter($content),
            ];
            $momentComment->save($data);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success();
    }


    /**
     * 数据结果格式化
     */
    protected function formatResult($list, $userId, $commentLimit = 'all')
    {
        $momentComment = new MomentComment();
        $momentLike = new MomentLike();
        $userFollowModel = new UserFollowModel();
        if (empty($list)) {
            return [];
        }

        $momentIdsAr = [];
        $userIdsAr = [];
        foreach ($list as $v) {
            $momentIdsAr[] = $v['id'];
            $userIdsAr[] = $v['user_id'];
        }
        $userInfo = get_users_info($userIdsAr); //所有用户信息
        $countComment = $momentComment->countMomentCommentByMomentId($momentIdsAr);//统计评论总数
        $countLike = $momentLike->countMomentLikeByMomentId($momentIdsAr);//统计被赞总数

        $userLikeCommentIds = $momentLike->getUserLikeIds($userId); //用户点赞动态id集合

        $userFollowUserIds = $userFollowModel->getUserFollowIdsByIds($userId, $userIdsAr);//用户所有关注用户id集合

        $userComment = $momentComment->getUserComment($momentIdsAr, $commentLimit, $userId);

        $userAdornment = db('user_adornment ua')
            ->join('adornment a', 'ua.adornment_id = a.id')
            ->where([
                'ua.user_id' => ['in', $userIdsAr],
                'ua.is_wear' => 1
            ])->column('a.face_image', 'ua.user_id');
        $date = new Date();
        foreach ($list as &$v) {
            $v['create_time_text'] = $date->human_time(strtotime($v['create_time']), request()->langset()) ?? '';
            $v['like_count'] = isset($countLike[$v['id']]) ? (int)$countLike[$v['id']] : 0;
            $v['comment_count'] = isset($countComment[$v['id']]) ? (int)$countComment[$v['id']] : 0;
            $v['nickname'] = $userInfo[$v['user_id']]['nickname'];
            $v['avatar'] = $userInfo[$v['user_id']]['avatar'];
            $v['level_icon'] = $userInfo[$v['user_id']]['level_icon'];
            $v['gender'] = $userInfo[$v['user_id']]['gender'];
            $v['room_id'] = 0;  // todo
            $v['is_like'] = in_array($v['id'], $userLikeCommentIds) ? 1 : 0;
            $v['is_follow'] = in_array($v['user_id'], $userFollowUserIds) ? 1 : 0;
            $v['comment_list'] = [];
            $v['adornment'] = $userAdornment[$v['user_id']] ?? '';

            if (!empty($userComment) && is_array($userComment)) {
                $v['comment_list'] = $userComment[$v['id']] ?? [];
            }
        }

        return $list;
    }

    /**
     * @ApiInternal
     * 获取非公开动态数据
     */
    public function getFilterUserIds($userId)
    {
        $publishArr = [MomentModel::PublishOnlyMe, MomentModel::PublishOfFans, MomentModel::PublishOfFollow, MomentModel::PublishOfVisitors];
        $momentFilter = db('moment')->where('publish', 'in', $publishArr)->field('id,user_id,publish')->select();
        $filterUserIds = [];
        if ($momentFilter) {
            foreach ($momentFilter as $v) {
                if ($v['publish'] == MomentModel::PublishOnlyMe && $v['user_id'] != $userId) {
                    $filterUserIds[] = $v['id'];
                }
                //我的粉丝
                if ($v['publish'] == MomentModel::PublishOfFans && !UserFollowModel::isFans($userId, $v['user_id'])) {
                    $filterUserIds[] = $v['id'];
                }
                //我的关注
                if ($v['publish'] == MomentModel::PublishOfFollow && !UserFollowModel::isFans($v['user_id'], $userId)) {
                    $filterUserIds[] = $v['id'];
                }
                //访问过我主页的
                if ($v['publish'] == MomentModel::PublishOfVisitors && !UserGuestModel::isGuest($v['user_id'], $userId)) {
                    $filterUserIds[] = $v['id'];
                }
            }
        }

        return $filterUserIds;
    }


    /**
     * @ApiTitle    (推荐用户)
     * @ApiMethod   (get)
     */
    public function commend_user()
    {
        if (!$this->auth->id) {
            $this->error(__('Please login first'));
        }
        $sel = db('moment')->where('status', MomentModel::StatusOn)
            ->group('user_id')
            ->field('user_id,count(1) as count')
            ->order('count desc')
            ->limit(5)
            ->select();

        $users = array_column($sel, 'user_id');
        foreach ($users as $user_id) {
            $userinfo = db('user u')
                ->field('avatar,fans_num,nickname,id as user_id')
                ->where('u.id', $user_id)
                ->where('u.id', '<>', $this->auth->id)
                ->where('u.status', 'normal')
                ->find();
            if ($userinfo) {
                $userinfo['is_follow'] = db('user_follow')->where([
                    'user_id'    => $this->auth->id,
                    'to_user_id' => $user_id
                ])->count();
                $data[] = $userinfo;
            }
        }
        $this->success('', $data);
    }


    /**
     * 添加动态黑名单
     * @ApiMethod   (post)
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="", description="屏蔽對象")
     */
    public function add_blacklist()
    {
        $exist = db('moment_blacklist')->where([
            'user_id'    => $this->auth->id,
            'to_user_id' => input('to_user_id')
        ])->select();
        $exist && $this->error(__('Account has been banned'));
        db('moment_blacklist')->insert(['user_id' => $this->auth->id, 'to_user_id' => input('to_user_id')]);
        $this->success(__('Operation completed'));
    }

    /**
     * 移除动态黑名单
     * @ApiMethod   (post)
     * @ApiParams   (name="to_user_id", type="int",  required=true, rule="", description="移除對象")
     */
    public function remove_blacklist()
    {
        db('moment_blacklist')->where(['user_id' => $this->auth->id, 'to_user_id' => input('to_user_id')])->delete();

        $this->success(__('Operation completed'));
    }

}
