<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use util\Date;

/**
 * 会员游戏时长
 *
 * @icon fa fa-circle-o
 */
class PlayLog extends Backend
{

    /**
     * UserPlayLog模型对象
     * @var \app\admin\model\UserPlayLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserPlayLog;

    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $room = db('user_play_log l')
                ->cache(true, 0, 'small_data_room')
                ->join('room r', 'l.room_id=r.id', 'left')
                ->where('r.name', 'not null')
                ->group('l.room_id')->column('r.name', 'l.room_id');
            if (input('option') == 'get_room') {
                return json($room);
            }

            if (input('option') == 'get_channel') {
                $channel = db('channel')
                    ->column('name', 'appid');
                return json($channel);
            }

            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $with = [
                'user' => function ($query) {
                    $query->withField("id,nickname,appid");
                }
            ];
            $total = $this->model
                ->with($with)
                ->where($where)
//                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $channel = db('channel')
                ->column('name', 'appid');

            foreach ($list as &$item) {
                $item['room_name'] = $room[$item['room_id']] ?? '';
                $item['second'] = Date::format_second($item['second']);
                $item['channel'] = $channel[$item['user']['appid']];
            }
            $app_online = $this->model->with([
                'user' => function ($query) {
                    $query->withField("id,appid");
                }
            ])->where($where)->where('room_id', 0)->sum('second');

            $room_online = $this->model->with([
                'user' => function ($query) {
                    $query->withField("id,appid");
                }
            ])->where($where)->where('room_id', 'neq', 0)->sum('second');

            $result = array(
                "total"  => $total,
                "rows"   => $list,
                'extend' => [
                    'app_online'  => Date::format_second($app_online),
                    'room_online' => Date::format_second($room_online),
                ]
            );

            return json($result);
        }

        if (input('?param.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        return $this->view->fetch();
    }


}
