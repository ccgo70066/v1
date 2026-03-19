<?php

namespace app\admin\controller\user;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\controller\Backend;
use app\common\exception\ApiException;
use app\common\library\Auth;
use GatewayClient\Gateway;
use think\Db;
use think\Exception;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }


    /**
     * 上分
     */
    public function add_amount($ids)
    {
        $row = $this->model->get($ids);
        if (false === $this->request->isPost()) {
            $add_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 1])->sum('amount');
            $sub_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 2])->sum('amount');
            $diff = $add_total - $sub_total;
            $this->assign('info', "总增加: $add_total, 总减少: $sub_total, 差: $diff");
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = input('row/a');
        $params['number'] <= 0 && $this->error('数量必须大于0');
        !$params['comment'] && $this->error('原因必填');
        try {
            Db::startTrans();
            user_money_change($ids, $params['number']);
            $type_arr = ['amount' => '余额'];
            db('admin_option_log')->insert([
                'admin_id' => session('admin.id'),
                'user_id'  => $ids,
                'option'   => 1,
                'comment'  => $params['comment'],
                'params'   => json_encode(input('')),
                'content'  => $type_arr[$params['type']] . '增加,数量:' . $params['number'],
                'amount'   => $params['number'],
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    /**
     * 下分
     */
    public function remove_amount($ids)
    {
        $row = $this->model->get($ids);
        if (false === $this->request->isPost()) {
            $add_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 1])->sum('amount');
            $sub_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 2])->sum('amount');
            $diff = $add_total - $sub_total;
            $this->assign('row', $row);
            $this->assign('info', "总增加: $add_total, 总减少: $sub_total, 差: $diff");
            return $this->view->fetch();
        }
        $params = input('row/a');
        $params['number'] <= 0 && $this->error('数量必须大于0');
        !$params['comment'] && $this->error('原因必填');
        try {
            Db::startTrans();
            user_money_change($ids, -$params['number']);
            $type_arr = ['amount' => '余额'];
            db('admin_option_log')->insert([
                'admin_id' => session('admin.id'),
                'user_id'  => $ids,
                'option'   => 2,
                'comment'  => $params['comment'],
                'params'   => json_encode(input('')),
                'content'  => $type_arr[$params['type']] . '减少,数量:' . $params['number'],
                'amount'   => $params['number'],
            ]);
            Db::commit();
        } catch (ApiException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }


}
