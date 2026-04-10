<?php

namespace app\admin\controller\user;

use app\admin\model\Mongo;
use app\common\service\RedisService;
use app\common\controller\Backend;
use MongoDB\Driver\Command;

/**
 * 聊天记录
 *
 * @icon fa fa-circle-o
 */
class ChatLog extends Backend
{

    /**
     * ChatLog模型对象
     * @var \app\admin\model\ChatLog
     */
    protected $model = null;
    protected $noNeedRight = ['chat_list'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ChatLog;

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
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            list($where, $sort, $order, $offset, $limit) = $this->mongoDBbuildparams();

            $filter = json_decode($this->request->get('filter'), true);
//            $with = ['user' => function ($query) {
//                $query->withField('nickname');
//            }, 'touser' => function ($query) {
//                $query->withField('nickname');
//            }];
//
//            if (!isset($filter['user.nickname']) && !isset($filter['touser.nickname'])) {
//                $with = [];
//            }
            if (!empty($where['user_id'])) {
                $where['user_id'] = (string)$where['user_id'];
            }
            if (!empty($where['to_user_id'])) {
                $where['to_user_id'] = (string)$where['to_user_id'];
            }
            if (!empty($where['create_time'][1][0] )) {
                $where['create_time'][1][0] = strtotime($where['create_time'][1][0]);
            }
            if (!empty($where['create_time'][1][1] )) {
                $where['create_time'][1][1] = strtotime($where['create_time'][1][1]);
            }

            $total = $this->model
//                ->with($with)
                ->order('_id', 'desc')
                ->where($where)
                ->count();

            $list = $this->model
//                ->with($with)
                ->where($where)
                ->order('_id', 'desc')
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            if (count($list)) {
                //缓存中取用户昵称
                foreach ($list as $k => $value) {
                    if (strpos($value['content'], 'chat') !== false) {
                        $list[$k]['content'] = cdnurl($value['content']);
                    }
                    $list[$k]['user']['nickname'] = RedisService::getUserCache($value['user_id'], 'nickname');
                    $list[$k]['touser']['nickname'] = RedisService::getUserCache($value['to_user_id'], 'nickname');
                    $list[$k]['create_time'] =datetime($list[$k]['create_time'] );
                    // if (in_array($value['type'], ['image','audit'])) {
                    //     $list[$k]['content'] = cdnurl($value['content']);
                    // }
                }

            }

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


    public function chat_list()
    {

        $user_id = input('user_id');
        $to_user_id = input('to_user_id');
        //主播接收消息数
        $query = [
            '$or'   => [
                ['user_id' => $user_id, 'to_user_id' => $to_user_id],
                ['user_id' => $to_user_id, 'to_user_id' => $user_id]
            ],
        ];


        $command = new Command(
            ['find' => 'aa_chat_log', 'filter' => $query,'sort'=>['_id'=>-1], 'limit' => 300,
             'projection' => ['_id' => 0]]
        );
        $model = new Mongo();

        $list = $model->command($command);
        $row = collection($list)->toArray();
        foreach ($row as $k => $value) {
            if (strpos($value['content'], 'chat') !== false) {
                $row[$k]['content'] = cdnurl($value['content']);
            }
            $row[$k]['create_time'] =datetime($row[$k]['create_time'] );
            $row[$k]['user']['nickname'] = RedisService::getUserCache($value['user_id'], 'nickname');
            $row[$k]['touser']['nickname'] = RedisService::getUserCache($value['to_user_id'], 'nickname');

        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

}
