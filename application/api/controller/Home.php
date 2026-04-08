<?php

namespace app\api\controller;

use app\common\model\Room as RoomModel;
use app\common\model\UserBusiness;
use app\common\service\HomeService;
use think\Db;

/**
 * 首页
 * @ApiWeigh    (901)
 */
class Home extends Base
{
    protected $noNeedLogin = ['room_list', 'anchor_list'];
    protected $noNeedRight = '*';
    protected $service;

    public function __construct(HomeService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * @ApiTitle    (房间列表[精选,派对])
     * @ApiMethod   (get)
     * @ApiParams   (name="theme_id",type="int",  required=true, rule="", description="主题ID:0=热门,-1=收藏.-2=全部")
     * @ApiParams   (name="page",type="int",  required=true, rule="", description="页码")
     * @ApiReturnParams    (name="theme_name", type="str", description="派对分类名称")
     * @ApiReturnParams    (name="theme_color", type="str", description="派对分类名称-颜色")
     **/
    public function room_list()
    {
        $page = (int)input('page');
        $theme_id = (int)input('theme_id') ?: 0;

        if (!$this->auth->id && $page > 1) $this->success('', []);

        if ($this->auth->id) {
            if ($page == 1) {
                redis()->del('room_list:' . $theme_id . $this->auth->id);
            } else {
                $already_show_ids = redis()->sMembers('room_list:' . $theme_id . $this->auth->id) ?: [];
                if (!$already_show_ids && !redis()->exists('room_list:' . $theme_id . $this->auth->id)) {
                    $this->success('', []);
                }
                $where['id'] = ['not in', $already_show_ids];
            }
        }
        $where['is_show'] = 1;
        if ($theme_id == -1) {
            if ($page > 1) {
                $this->success('', []);
            }
            $room_ids = db('room_collect c')->where('c.user_id', $this->auth->id)->column('c.room_id');
            $where['id'] = ['in', $room_ids];
            unset($where['is_show'], $where['type']);
        } elseif ($theme_id <> -2 && $theme_id) {
            $theme_id && $where['theme_id'] = $theme_id;
        }
        if ($theme_id == 0) $where['type'] = ['<>', 2];
        $result = $this->service->roomList($where, $limit = 30);
        if ($result && $this->auth->id) {
            redis()->sAddArray('room_list:' . $theme_id . $this->auth->id, array_column($result, 'id'));
            redis()->expire('room_list:' . $theme_id . $this->auth->id, 60 * 30);
        }
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (随机匹配一个用户)
     * @ApiMethod   (get)
     **/
    public function match_user()
    {
        $userId = $this->auth->id;
        $user = Db::name('room_admin')->alias('r')->join('user u', 'r.user_id = u.id')
            ->field('id,avatar,nickname,age,constellation,gender,voice')
            ->where('u.id', 'neq', $userId)
            ->order('u.is_online desc')->orderRaw('rand()')->find();
        if ($user) {
            $user['is_follow'] = db('user_follow')->where(['user_id' => $userId, 'to_user_id' => $user['id']])->count() > 0;
            $user['current_room'] = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $user['id']) ?: 0;
            if ($user['current_room']) {
                $user['current_room'] = Db::name('room')
                    ->where('type', 1)
                    ->where('status', 'in', [\app\common\model\Room::ROOM_STATUS_IDLE, RoomModel::ROOM_STATUS_PLAYING])
                    ->where('is_show', 1)->where('id', $user['current_room'])->value('id') ?: 0;
            }
        }
        $this->success('', $user);
    }

    /**
     * 搜索
     * @ApiMethod   (get)
     * @ApiParams   (name="keyword", type="string",  required=true, rule="", description="关键字")
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="页码大小")
     * @ApiReturnParams   (name="follow", type="int",  required=false, rule="", description="類型:1=已加入/已关注,0=未关注")
     */
    public function search()
    {
        $keyword = trim(input('keyword', 'htmlentities'), "\\");
        $page = input('page') ?: 1;
        $size = input('size') ?: 30;
        $list = [];

        $where = [];
        if (is_numeric($keyword)) {
            $where['u.id|u.beautiful_id'] = $keyword;
        } elseif ($keyword) {
            $where['u.nickname'] = ['like', "%{$keyword}%"];
        }
        //搜索用户
        $list['user'] = db('user u')
            ->join('blacklist b', 'b.type=1 and b.number=u.id and b.end_time > ' . time(), 'left')
            ->field('u.id,u.nickname as name,u.avatar as cover,gender,age,u.beautiful_id')
            ->where($where)
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->orderRaw("u.id desc")
            ->page($page, $size)
            ->select();

        foreach ($list['user'] as &$user) {
            $user['room_id'] = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $user['id']) ?: 0;
        }

        $where = [
        ];
        $keyword && $where['beautiful_id|name'] = ['like', "%{$keyword}%"];

        $list['room'] = $this->service->roomList($where, $limit = 30);

        if ($this->auth->id) {
            foreach ($list['user'] as &$item) {
                $item['follow'] = db('user_follow')
                    ->where(['user_id' => $this->auth->id, 'to_user_id' => $item['id']])
                    ->find() ? 1 : 0;
            }
        }

        $this->success('', $list);
    }

    /**
     * 搜索-推荐页
     * @ApiMethod   (get)
     * @ApiParams   (name="limit", type="int",  required=true, rule="", description="显示数量，默认5条")
     *
     * @ApiReturnParams    (name="theme_name", type="str", description="派对分类名称")
     * @ApiReturnParams    (name="theme_color", type="str", description="派对分类名称-颜色")
     */
    public function search_page()
    {
        //todo::搜索检索页：1-推荐派对（排序规则：后台排序asc>热度desc>注册时间asc(show_sort asc,hot desc,create_time)）
        //todo::搜索检索页：2-推荐主播（排序规则：取在线>id desc 50条-随机5条)）
        $limit = input('limit') ?: 5;
        $rooms = RoomModel::getRoomList([], $limit);
        //获取房间类似数组
        $roomCateArr = $this->service->getRoomCate();
        foreach ($rooms as $k => &$v) {
            $cateData = $roomCateArr[$v['theme_id']];
            $rooms[$k]['theme_name'] = $cateData['name'];
            $rooms[$k]['theme_color'] = $cateData['color'];
        }

        //获取推荐主播
        $roleStr = UserBusiness::ROLE_ANCHOR . ',' . UserBusiness::ROLE_UNION . ',' . UserBusiness::ROLE_UNION_MASTER;
        $anchors = db('user u')
            ->join('blacklist b', 'b.type=1 and b.number=u.id and b.end_time > ' . time(), 'left')
            ->join('user_business ub', 'ub.id=u.id and ub.role in (' . $roleStr . ')', 'left')
            ->field('u.id,u.beautiful_id,u.nickname as name,u.avatar as cover,gender,age,is_online')
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->order('is_online desc,u.id asc')
            ->limit(30)
            ->select();
        if (count($anchors) > $limit) {
            $lastUserKeys = array_rand($anchors, $limit);
            foreach ($lastUserKeys as $key => $value) {
                $newAnchors[$key] = $anchors[$value];
            }
        } else {
            $newAnchors = $anchors;
        }
        if (count($newAnchors)) {
            foreach ($newAnchors as &$item) {
                $item['current_room'] = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $item['id']) ?: 0;
            }
        }
        $list['room'] = $rooms;
        $list['user'] = $newAnchors;

        $this->success('', $list);
    }

    /**
     * 主播列表
     * @ApiMethod   (get)
     * @ApiParams   (name="type", type="int",  required=true, rule="", description="type:0=全部,1=已关注")
     * @ApiParams   (name="page", type="int",  required=true, rule="", description="页码")
     */
    public function anchor_list()
    {
        $page = input('page');
        $type = (int)input('type');
        $query = Db::name('anchor')->alias('a')->join('user u', 'a.user_id=u.id');
        if ($type == 1) {
            if (!$this->auth->id) {
                $this->success('', []);
            }
            $collect = Db::name('user_follow')->where(['user_id' => $this->auth->id])->column('to_user_id');
            $query->where('a.user_id', 'in', $collect);
        }
        if (!$this->auth->id) {
            if ($page > 1) {
                $this->success('', []);
            }
        } else {
            if ($page == 1) {
                redis()->del('anchor_list:' . $type . $this->auth->id);
            } else {
                $already_show_ids = redis()->sMembers('anchor_list:' . $type . $this->auth->id) ?: [];
                $query->where('a.user_id', 'not in', $already_show_ids);
            }
        }

        $list = $query
            ->where('a.status', \app\common\model\Anchor::STATUS_PASSED)
            ->field('u.id,u.avatar,u.is_online,nickname')
            ->order('is_online = 1')
            ->limit(30)
            ->select();
        if ($list && $this->auth->id) {
            redis()->sAddArray('anchor_list:' . $type . $this->auth->id, array_column($list, 'id'));
            redis()->expire('anchor_list:' . $type . $this->auth->id, 60 * 30);
        }
        foreach ($list as &$user) {
            $user['current_room'] = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $user['id']) ?: 0;
        }
        $this->success('', $list);
    }

    /**
     * 首购礼包
     */
    public function get_first_parcel()
    {
        $list = db('parcel')->field('recharge_amount,reward_data')->where('type', 2)->select();
        foreach ($list as &$item) {
            $item['reward_data'] = format_reward_json(json_decode($item['reward_data'], true));
        }

        $this->success('', $list);
    }
}
