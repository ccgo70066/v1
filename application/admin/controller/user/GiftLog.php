<?php

namespace app\admin\controller\user;

use app\admin\library\GiftService;
use app\common\service\RedisService;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
use app\admin\model\GiftLog as GiftLogModel;

/**
 * 礼物日志
 *
 * @icon fa fa-circle-o
 */
class GiftLog extends Backend
{

    /**
     * GiftLog模型对象
     * @var GiftLog
     */
    protected $noNeedLogin = ['total'];
    protected $noNeedRight = ['total'];
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new GiftLogModel;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("priceTypeList", $this->model->getPriceTypeList());
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
            $filter = json_decode($this->request->get('filter'), true);
            //这个是符号 类似这样['game_id'=>'=','name'=>'like']
            $op = json_decode($this->request->get('op'), true);
            //如果你想修改这个，比如去掉name这个条件,像下面这样操作
            if (isset($filter['pk_id']) && $filter['pk_id'] > 0) {
                $op['pk_id'] = '>';
            }
            $this->request->get(['filter' => json_encode($filter)]);
            $this->request->get(['op' => json_encode($op)]);


            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->count(1);

            $list = $this->model
                ->where($where)
                ->order('id', 'desc')
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->union_profit = 0;
                if ($row->room_id) {
                    $row->union_profit = ($row->gift_val) * 10 / 100;
                }
            }
            $list = collection($list)->toArray();
//            $channel = db('channel')->cache('channel_name_by_appid')->column('appid,name');
            if (count($list)) {
                //查出礼物所有数据-数组
                foreach ($list as &$item) {
//                $item['givers']['appid_name'] = $channel[$item['givers']['appid']];
                    $item['gift']['name'] = RedisService::getGiftCache($item['gift_id'], 'name');
                    $item['gift']['image'] = RedisService::getGiftCache($item['gift_id'], 'image');

                    $item['givers']['nickname'] = RedisService::getUserCache($item['user_id'], 'nickname');
                    $item['receivers']['nickname'] = RedisService::getUserCache($item['to_user_id'], 'nickname');
                }
            }

            $result = array(
                "total"  => $total,
                "rows"   => $list,
                "extend" => [],
            );

            return json($result);
        }
        if (input('?param.ids') && !input('?param.union_id')) {
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
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
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
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
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
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
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
        try {
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $k => $v) {
                $count += $v->delete(true);
            }
            Db::commit();
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        } else {
            $this->error(__('No rows were deleted'));
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 统计查询
     */
    public function total()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $sum = $this->model
            ->join('gift gift', 'gift_id=gift.id', 'left')
            ->where($where)
            ->field('sum(gift_val) as gift_val,sum(gift_val*union_reward_rate) as union_reward')
            ->find();

        //$diff = 1000;
        $diff = 1;
        $result = [
            'price_sum'    => (float)$sum['gift_val'] / $diff,   //币礼物收益
            'union_reward' => (float)$sum['union_reward'] / $diff,   //家族收益合计
        ];

        return json($result);
    }

}
