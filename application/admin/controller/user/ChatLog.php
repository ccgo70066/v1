<?php

namespace app\admin\controller\user;

use app\admin\model\Mongo;
use app\common\service\RedisService;
use app\common\controller\Backend;
use MongoDB\Driver\Command;
use think\Loader;

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

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

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
    /**
     * 兼容mongodb的版本
     * @param $searchfields
     * @param $relationSearch
     * @return array
     */
    protected function buildparams($searchfields = null, $relationSearch = null)
    {
        $searchfields = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->get("search", '');
        $filter = $this->request->get("filter", '');
        $op = $this->request->get("op", '', 'trim');
        $sort = $this->request->get(
            "sort",
            !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id'
        );
        $order = $this->request->get("order", "DESC");
        $offset = $this->request->get("offset", 0);
        $limit = $this->request->get("limit", 0);
        $filter = (array)json_decode($filter, true);
        $op = (array)json_decode($op, true);
        $filter = $filter ? $filter : [];
        $where = [];
        $tableName = '';
        if ($relationSearch) {
            if (!empty($this->model)) {
                $name = Loader::parseName(basename(str_replace('\\', '/', get_class($this->model))));
                $name = $this->model->getTable();
                $tableName = $name . '.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => & $item) {
                $item = stripos($item, ".") === false ? $tableName . trim($item) : $item;
            }
            unset($item);
            $sort = implode(',', $sortArr);
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $where[] = [$tableName . $this->dataLimitField, 'in', $adminIds];
        }
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        trace($filter, 'm');
        foreach ($filter as $k => $v) {
            trace($v, 'm');
            $sym = isset($op[$k]) ? $op[$k] : '=';
            if (stripos($k, ".") === false) {
                $k = $tableName . $k;
            }
            $v = !is_array($v) ? trim($v) : $v;
            is_numeric($v) && $v = (float)$v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            switch ($sym) {
                case '=':
                    $where[$k] = $v;
                    break;
                case '<>':
                    $where[$k] = [$sym, (string)$v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[$k] = [trim(str_replace('%...%', '', $sym)), "{$v}"];
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where[$k] = [$sym, intval($v)];
                    break;
                // case 'FINDIN':
                // case 'FINDINSET':
                // case 'FIND_IN_SET':
                //     $where[] = "FIND_IN_SET('{$v}', " . ($relationSearch ? $k : '`' . str_replace('.', '`.`',
                //                 $k) . '`') . ")";
                //     break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[$k] = [str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[$k] = [$sym, $arr];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    foreach ($arr as &$item) {
                        strtotime($item) && $item = strtotime($item);
                    }
                    $where[$k] = [str_replace('RANGE', 'BETWEEN', $sym), $arr];
                    break;
                case 'LIKE':
                case 'LIKE %...%':
                    $where[] = [$k, 'LIKE', "%{$v}%"];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                default:
                    break;
            }
        }
        // dump($where); die;
        return [$where, $sort, $order, $offset, $limit];
    }

}
