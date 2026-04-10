<?php

namespace app\admin\controller\egg;

use app\admin\controller\room\Log;
use app\common\service\RedisService;
use app\common\controller\Backend;
use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Command;
use think\Cache;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 钓鱼记录
 *
 * @icon fa fa-circle-o
 */
class LogMongo extends Backend
{

    /**
     * EggLog模型对象
     * @var \app\admin\model\EggLogMongo
     */
    protected $model = null;
    protected $relationSearch = false;
    protected $searchFields = 'id';
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\EggLogMongo;
        $this->view->assign("boxTypeList", $this->model->getBoxTypeList());
        $this->view->assign("countTypeList", $this->model->getCountTypeList());
        $this->view->assign("jumpStatusList", $this->model->getJumpStatusList());
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
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $t = microtime(true);
            if (input('option') == 'load_level') {
                $level = db('egg_level')->order('box_type asc')->column('name', 'id');
                return json($level);
            }
            if (input('option') == 'load_weigh') {
                return Cache::remember('egg:log:load_weigh', function () {
                    Cache::tag('small_data_egg', 'egg:log:load_weigh');
                    $tables = [
                        'egg_config_sys',
                        'egg_config_base',
                        'egg_config_def',
                        'egg_config_per',
                        'egg_config_pub',
                        'egg_config_single',
                        'egg_config_back',
                    ];
                    $weigh = ['运营指定'];
                    foreach ($tables as $table) {
                        $weigh = array_merge($weigh, db($table)->order('box_type asc')->column('title as name'));
                    }
                    return json($weigh);
                });
            }
            if (input('option') == 'load_gift') {
                // return Cache::remember('egg:log:load_gift', function () {
                //     Cache::tag('small_data_egg', 'egg:log:load_gift');
                $gift = db('gift')
                    ->where(['type' => 3])
                    ->order('price asc')
                    ->field('id,name,price')
                    ->select();
                foreach ($gift as &$item) {
                    $item['name'] = $item['name'] . '(' . $item['price'] . ')';
                }
                return json($gift);
                // });
            }

            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->mongoDBbuildparams();
            $pipeLine = [
                ['$match' => []],
                ['$sort' => ['_id' => -1]],
                ['$unwind' => '$log'],
            ];
            // dump($where);
            foreach ($pipeLine as $key => $value) {
                if (array_key_exists('$match', $value)) {
                    foreach ($where as $whereField => $whereValue) {
                        if (is_string($whereField) && !is_array($whereValue) && strpos($whereField, 'log.') !== 0) {
                            if (is_numeric($whereValue)) {
                                $whereValue = (int)$whereValue;
                            }
                            $pipeLine[$key]['$match'] = array_merge(
                                $pipeLine[$key]['$match'],
                                [$whereField => $whereValue]
                            );
                        }
                    }
                    if (!empty($where['create_time']) && $where['create_time']['0'] == 'BETWEEN') {
                        $pipeLine[$key]['$match'] = array_merge(
                            $pipeLine[$key]['$match'],
                            [
                                'create_time' => [
                                    '$gte' => strtotime($where['create_time'][1][0]),
                                    '$lte' => strtotime($where['create_time'][1][1])
                                ]
                            ]
                        );
                    }
                    if ($pipeLine[$key]['$match'] == []) {
                        unset($pipeLine[$key]);
                    }
                }
            }

            $newWhere = [];
            foreach ($pipeLine as $key => $value) {
                if (array_key_exists('$unwind', $value)) {
                    foreach ($where as $whereField => $whereValue) {
                        if (strpos($whereField, 'log.') === 0) {
                            $newWhere[$whereField] = $whereValue;
                        }
                    }
                    if ($newWhere) {
                        $data = ['$match' => $newWhere];
                        array_push($pipeLine, $data);
                        // array_splice($pipeLine,$key,0,$data);
                    }
                }
            }

            $pipeLine = array_values($pipeLine);
            \think\Log::error($pipeLine);

            $queryPipeLine = $pipeLine;
            $totalPipeLine = $pipeLine;
            array_push($queryPipeLine, ['$skip' => (int)$offset], ['$limit' => (int)$limit]);
            array_push($totalPipeLine, ['$group' => ['_id' => null, 'total' => ['$sum' => 1]]]);

            if (!$where) {
                $totalPipeLine = [
                    [
                        '$group' => [
                            '_id'   => null,
                            'total' => ['$sum' => '$count_type']
                        ]
                    ]
                ];
            }

            $command = new Command([
                'aggregate' => 'aa_egg_log',
                'pipeline'  => $totalPipeLine,
                'cursor'    => new \stdClass()// 指定返回类型为文档游标
            ]);

            if ($where) {
                $total = $this->model->command($command)[0]['total'] ?? 0;
            } else {
                $total = 10000;
            }
            \think\Log::error(__LINE__ . '查询耗时' . (microtime(true) - $t));
            \think\Log::error($totalPipeLine);

            $command = new Command([
                'aggregate' => 'aa_egg_log',
                'pipeline'  => $queryPipeLine,
                'cursor'    => new \stdClass()// 指定返回类型为文档游标
            ]);
            $list = $this->model->command($command);
            \think\Log::error(__LINE__ . '查询耗时' . (microtime(true) - $t));
            \think\Log::error($queryPipeLine);

            $list = collection($list)->toArray();
            $gift = db('gift')->column('name,price', 'id');
            $level = db('egg_level')->column('name', 'id');
            foreach ($list as &$item) {
                $item['nickname'] = RedisService::getUserCache($item['user_id'], 'nickname');
                $item['gift_name'] = $gift[$item['log']['gift_id']]['name'];
                $item['level_name'] = $level[$item['log']['level_id']];
                foreach ($item as $k => $v) {
                    if (strpos($k, 'pool') !== false) {
                        $item[$k] = $v;
                    }
                }
            }

            $extend['index_count'] = $total;
            $result = array("total" => $total, "rows" => $list, 'extend' => $extend);
            return json($result);
        }

        if (input('?param.ids') && !input('?get.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }

        return $this->view->fetch('egg/log/index');
    }

    public function sum()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        list($where, $sort, $order, $offset, $limit) = $this->mongoDBbuildparams();
        $pipeLine = [
            ['$match' => []],
            ['$unwind' => '$log'],
        ];
        foreach ($pipeLine as $key => $value) {
            if (array_key_exists('$match', $value)) {
                foreach ($where as $whereField => $whereValue) {
                    if (is_string($whereField) && !is_array($whereValue)) {
                        if (is_numeric($whereValue)) {
                            $whereValue = (int)$whereValue;
                        }
                        $pipeLine[$key]['$match'] = array_merge(
                            $pipeLine[$key]['$match'],
                            [$whereField => $whereValue]
                        );
                    }
                }
                if (!empty($where['create_time']) && $where['create_time']['0'] == 'BETWEEN') {
                    $pipeLine[$key]['$match'] = array_merge(
                        $pipeLine[$key]['$match'],
                        [
                            'create_time' => [
                                '$gte' => strtotime($where['create_time'][1][0]),
                                '$lte' => strtotime($where['create_time'][1][1])
                            ]
                        ]
                    );
                }
                if ($pipeLine[$key]['$match'] == []) {
                    unset($pipeLine[$key]);
                }
            }
        }
        $newWhere = [];
        foreach ($pipeLine as $key => $value) {
            if (array_key_exists('$unwind', $value)) {
                foreach ($where as $whereField => $whereValue) {
                    if (strpos($whereField, 'log.') === 0) {
                        $newWhere[$whereField] = $whereValue;
                    }
                }
                if ($newWhere) {
                    $data = ['$match' => $newWhere];
                    array_push($pipeLine, $data);

                    // array_splice($pipeLine,$key+1,0,$data);
                    // \think\Log::error($pipeLine);
                    $pipeLine[] = [
                        '$group' => [
                            '_id'         => null,
                            'amount'      => ['$sum' => '$log.gift_value'],
                            'used_amount' => ['$sum' => '$used_amount'],
                        ]
                    ];
                } else {
                    unset($pipeLine[$key]);

                    $pipeLine[] = [
                        '$group' => [
                            '_id'         => null,
                            'amount'      => ['$sum' => '$total_value'],
                            'used_amount' => ['$sum' => '$used_amount'],
                        ]
                    ];
                }
            }
        }

        $pipeLine = array_values($pipeLine);
        // dump($pipeLine);
        $command = new Command([
            'aggregate' => 'aa_egg_log',
            'pipeline'  => $pipeLine,
            'cursor'    => new \stdClass()// 指定返回类型为文档游标
        ]);
        $sum = $this->model->command($command);
        $sum = $sum[0] ?? [];

        //
        // $sum = db('egg_log')->alias('egg_log')
        //     ->join('gift gift', 'egg_log.gift_id=gift.id', 'left')
        //     ->join('user user', 'egg_log.user_id = user.id')
        //     ->where($where)
        //     ->field('sum(gift.price*count) as amount,sum(used_amount) as used_amount')
        //     ->find();
        $total_used_amount = $sum['used_amount'] ?? 0;
        $total_gain_amount = $sum['amount'] ?? 0;
        $result = [
            'total_used_amount' => number_format($total_used_amount, 2),
            'total_gian_amount' => number_format($total_gain_amount, 2),
            'total_our_amount'  => number_format(($total_gain_amount * 0.85 - $total_used_amount) / 10, 2),
            'percent'           => bcdiv($total_gain_amount, $total_used_amount ?: 1, 4),
        ];

        return json($result);
    }


}
