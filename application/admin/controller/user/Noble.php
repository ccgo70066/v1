<?php

namespace app\admin\controller\user;

use app\common\service\RedisService;
use app\common\controller\Backend;
use think\exception\DbException;
use think\response\Json;

/**
 * 用户贵族管理
 *
 * @icon fa fa-circle-o
 */
class Noble extends Backend
{

    /**
     * UserNoble模型对象
     * @var \app\admin\model\UserNoble
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserNoble;

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
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $filter = json_decode($this->request->get('filter'), true);
//这个是符号 类似这样['game_id'=>'=','name'=>'like']
            $op = json_decode($this->request->get('op'), true);
//如果你想修改这个，比如去掉name这个条件,像下面这样操作

            $where_1 = 1;
            if (isset($filter['is_expire']) && $filter['is_expire'] == 1) {
                unset($op['is_expire']);
                unset($filter['is_expire']);
                $where_1 = ['user_noble.end_time' => ['<', date('Y-m-d H:i:s', time() - config('app.noble_protection_time'))]];
            }
            if (isset($filter['is_expire']) && $filter['is_expire'] == 2) {
                unset($op['is_expire']);
                unset($filter['is_expire']);

                $where_1 = ['user_noble.end_time' => ['>', date('Y-m-d H:i:s', time() - config('app.noble_protection_time'))]];
            }
            $this->request->get(['filter' => json_encode($filter)]);
            $this->request->get(['op' => json_encode($op)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['noble'])
                ->where($where)
                ->where($where_1)
//                ->order($sort, $order)
                ->count();

            $list = $this->model
                //->with(['user','noble','userbusiness'])
                ->with(['noble'])
                ->where($where)
                ->where($where_1)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            foreach ($list as &$item) {
                $item['user']['nickname'] = RedisService::getUserCache($item['user_id'], 'nickname');
                if ($item['end_time'] < date('Y-m-d H:i:s', time() - config('app.noble_protection_time'))) {
                    $item['is_expire'] = 1;
                }
                if ($item['end_time'] > date('Y-m-d H:i:s', time() - config('app.noble_protection_time'))) {
                    $item['is_expire'] = 2;
                }
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


}
