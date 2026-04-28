<?php

namespace app\admin\controller\lucky;

use app\common\controller\Backend;
use app\common\exception\ApiException;
use app\common\library\rabbitmq\LuckyMoneyMQ;
use Exception;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 红包列管理
 *
 * @icon fa fa-circle-o
 */
class Money extends Backend
{

    /**
     * Money模型对象
     * @var \app\admin\model\lucky\Money
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\LuckyMoney;
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将 application/admin/library/traits/Backend.php 中对应的方法复制到当前控制器,然后进行修改
     * @link  \app\admin\library\traits\Backend
     */


    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            $this->assign('row', null);
            return $this->view->fetch('edit');
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
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
                $this->model->validateFailException()->validate($validate);
            }
            if ($params['open_time'] && $params['open_time'] < datetime(strtotime('+300 seconds'))) {
                throw new ApiException('此红包雨开始时间必须大于当前时间5分钟以上');
            }
            if ($params['end_time'] && $params['end_time'] < datetime(strtotime($params['open_time']))) {
                throw new ApiException('此红包雨结束时间必须大于开始时间');
            }
            if ((int)$params['min_amount'] < 1 ||
                (int)$params['max_amount'] < 1 ||
                (int)$params['max_amount'] < (int)$params['min_amount'] ||
                $params['amount'] < (int)$params['max_amount']) {
                throw new ApiException('参数有误');
            }
            $params['remain_amount'] = $params['amount'];
            // 检查时间是否与现有记录重合
            $overlapCount = Db::name('lucky_money')
                ->where(function ($query) use ($params) {
                    // 新活动的开始时间在已有活动的时间范围内
                    $query->where('open_time', '<=', $params['end_time'])
                        ->where('end_time', '>=', $params['open_time']);
                })
                ->where('id', '<>', isset($params['id']) ? $params['id'] : 0) // 排除当前编辑的记录（如果是编辑操作）
                ->count();
            if ($overlapCount > 0) {
                throw new ApiException('该时间段与已有的红包雨活动时间重合，请调整时间');
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
            mq_publish(LuckyMoneyMQ::instance(), ['id' => $this->model->getLastInsID(), 'type' => 'push'], strtotime($params['open_time']) - time());
            mq_publish(LuckyMoneyMQ::instance(), ['id' => $this->model->getLastInsID(), 'type' => 'timeout'], strtotime($params['end_time']) - time());
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

}
