<?php

namespace app\admin\controller\wheel;

use app\common\controller\Backend;
use think\Cache;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 钓鱼记录
 *
 * @icon fa fa-circle-o
 */
class Log extends Backend
{

    /**
     * WheelLog模型对象
     * @var \app\admin\model\WheelLog
     */
    protected $model = null;
    protected $searchFields = 'id';
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\WheelLog;
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
                $level = db('wheel_level')->order('box_type asc')->column('name', 'id');
                return json($level);
            }
            if (input('option') == 'load_weigh') {
                return Cache::remember('wheel:log:load_weigh', function () {
                    Cache::tag('small_data_wheel', 'wheel:log:load_weigh');
                    $tables = [
                        'wheel_config_sys',
                        'wheel_config_base',
                        'wheel_config_def',
                        'wheel_config_per',
                        'wheel_config_pub',
                        'wheel_config_single',
                        'wheel_config_back',
                    ];
                    $weigh = [];
                    foreach ($tables as $table) {
                        $weigh = array_merge($weigh, db($table)->order('box_type asc')->column('title as name'));
                    }
                    return json($weigh);
                });
            }
            if (input('option') == 'load_gift') {
                return Cache::remember('wheel:log:load_gift', function () {
                    Cache::tag('small_data_wheel', 'wheel:log:load_gift');
                    $gift = db('gift')
                        ->where(['type' => \app\common\model\Gift::GIFT_TYPE_BOOMERANG])
                        ->order('price asc')
                        ->field('id,name,price')
                        ->select();
                    foreach ($gift as &$item) {
                        $item['name'] = $item['name'] . '(' . (float)$item['price'] . ')';
                    }
                    return json($gift);
                });
            }
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $with = [
                'user' => function ($query) {
                    $query->withField('nickname,actor_status');
                }
            ];
            $total = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
//            $user = db('user')->where('id', 'in', array_column($list, 'user_id'))->column('nickname，actor_status', 'id');
            $gift = db('gift')->cache('wheel:backend:gift_info', 0, 'small_data_wheel')->column('name,price', 'id');
            $level = db('wheel_level')->column('name', 'id');
            foreach ($list as &$item) {
//                $item['nickname'] = $user[$item['user_id']]['nickname'];
//                $item['actor_status'] = $user[$item['user_id']]['actor_status'];
                $item['gift_name'] = $gift[$item['gift_id']]['name'];
                $item['gift_price'] = (float)$gift[$item['gift_id']]['price'];
                $item['level_name'] = $level[$item['level_id']];
                foreach ($item as $k => $v) {
                    if (strpos($k, 'pool') !== false) {
                        $item[$k] = (float)$v;
                    }
                }
            }
            // $extend['index_count'] = $total;
            // $sum = $this->model->join('gift g', 'gift_id=g.id', 'left')
            //     ->where($where)->field('sum(g.price*count) as amount,sum(used_amount) as used_amount')
            //     ->find();
            // $extend['amount'] = (float)$sum['amount'];
            // $extend['used_amount'] = (float)$sum['used_amount'];
            // $extend['percent'] = 0;
            // $extend['used_amount'] && $extend['percent'] = bcdiv($extend['amount'], $extend['used_amount'], 4);

            $result = array("total" => $total, "rows" => $list, 'extend' => []);

            return json($result);
        }

        if (input('?param.ids') && !input('?get.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        return $this->view->fetch();
    }


    /**
     * 添加
     */
    public function add()
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
    public function edit($ids = null)
    {
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
    public function del($ids = "")
    {
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
    public function destroy($ids = "")
    {
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


    public function sum()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $sum = db('wheel_log')->alias('wheel_log')
            ->join('gift gift', 'wheel_log.gift_id=gift.id', 'left')
            ->where($where)
            ->field('sum(gift.price*count) as amount,sum(used_amount) as used_amount')
            ->find();
        $total_used_amount = $sum['used_amount'];
        $total_gain_amount = $sum['amount'];
        $result = [
            'total_used_amount' => number_format($total_used_amount, 2),
            'total_gian_amount' => number_format($total_gain_amount, 2),
            'total_our_amount'  => number_format(($total_gain_amount * 0.85 - $total_used_amount) / 10, 2),
            'percent'           => bcdiv($total_gain_amount, $total_used_amount ?: 1, 4),
        ];

        return json($result);
    }

}
