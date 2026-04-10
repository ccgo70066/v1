<?php

namespace app\admin\controller\egg;

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
class LogMongoH extends Backend
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
                    ->where(['type' => 5])
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
            if (empty($where['user_id'])) {
                    if ($this->isFirstRequest()) {
                        $result = array(
                            "total"  => 0,
                            "rows"   => [],
                            "extend" => [],
                        );
                        return json($result);
                    }else{
                        $this->error('请输入要查询的用户ID');
                    }
                }

            $pipeLine = [
                ['$match' => []],
                ['$sort' => ['_id' => -1]],
                ['$unwind' => '$log'],
            ];

            foreach ($pipeLine as $key => $value) {
                if (array_key_exists('$match', $value)) {
                    foreach ($where as $whereField => $whereValue) {
                        if (is_string($whereField) && !is_array($whereValue) && strpos($whereField, 'log.') !== 0) {
                            if (is_numeric($whereValue)) {
                                $whereValue = (int)$whereValue;
                            }
                            $pipeLine[$key]['$match'] = array_merge($pipeLine[$key]['$match'],
                                [$whereField =>$whereValue]);
                        }
                    }
                    if (!empty($where['create_time']) && $where['create_time']['0'] == 'BETWEEN') {
                        $pipeLine[$key]['$match'] = array_merge($pipeLine[$key]['$match'],
                            [
                                'create_time' => [
                                    '$gte' => strtotime($where['create_time'][1][0]),
                                    '$lte' => strtotime($where['create_time'][1][1])
                                ]
                            ]);
                    }
                    if ($pipeLine[$key]['$match'] == []) {
                        unset($pipeLine[$key]);
                    }
                }
            }

            $newWhere =[];
            foreach ($pipeLine as $key => $value) {
                if (array_key_exists('$unwind', $value)) {
                    foreach ($where as $whereField => $whereValue) {
                        if (strpos($whereField, 'log.') === 0) {
                            $newWhere[$whereField] = $whereValue;
                        }
                    }
                    if ($newWhere) {
                        // $data = [['$match' => $newWhere]];
                        // array_splice($pipeLine,$key,0,$data);
                        $data = ['$match' => $newWhere];
                        array_push($pipeLine,$data);
                    }
                }
            }

            $pipeLine = array_values($pipeLine);
            $queryPipeLine =$pipeLine;
            $totalPipeLine = $pipeLine;
            array_push($queryPipeLine, ['$skip' => (int)$offset], ['$limit' => (int)$limit]);
            array_push($totalPipeLine, ['$group' =>['_id'=>null,'total'=>['$sum'=>1]]]);
            $command = new Command([
                'aggregate' => 'aa_egg_log',
                'pipeline'  => $totalPipeLine,
                'cursor'    => new \stdClass()// 指定返回类型为文档游标
            ]);

            $total = $this->model->command($command)[0]['total'] ?? 0;
            $command = new Command([
                'aggregate' => 'aa_egg_log',
                'pipeline'  => $queryPipeLine,
                'cursor'    => new \stdClass()// 指定返回类型为文档游标
            ]);
            $list = $this->model->command($command);

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
        $this->setFirstRequest();
        if (input('?param.ids') && !input('?get.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }

        return $this->view->fetch();
    }



    /**
     * 添加
     */
    public
    function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try{
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                }catch (ValidateException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (PDOException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (Exception $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                }else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", null);
        return $this->view->fetch('edit');
    }

    /**
     * 编辑
     */
    public
    function edit(
        $ids = null
    ) {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try{
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                }catch (ValidateException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (PDOException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (Exception $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                }else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public
    function del(
        $ids = ""
    ) {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try{
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
            }catch (PDOException $e){
                Db::rollback();
                $this->error($e->getMessage());
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            }else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 真实删除
     */
    public
    function destroy(
        $ids = ""
    ) {
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $this->model->where($pk, 'in', $ids);
        }
        $count = 0;
        Db::startTrans();
        try{
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $k => $v) {
                $count += $v->delete(true);
            }
            Db::commit();
        }catch (PDOException $e){
            Db::rollback();
            $this->error($e->getMessage());
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }else {
            $this->error(__('No rows were deleted'));
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }
}
