<?php

namespace app\admin\controller\user;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\service\RedisService;
use app\api\library\RoomService;
use app\common\controller\Backend;
use app\common\exception\ApiException;
use http\Env;
use think\Db;
use think\Exception;

/**
 * 主播
 *
 * @icon fa fa-anchor
 */
class Anchor extends Backend
{

    /**
     * Anchor模型对象
     * @var \app\admin\model\Anchor
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Anchor;
        $this->view->assign("statusList", $this->model->getStatusList());
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->with([
                    'admin' => function ($query) {
                        $query->withField('username');
                    },
                ])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            //缓存中取用户昵称
            foreach ($list->items() as $k => $v) {
                $list->items()[$k]['user']['nickname'] = RedisService::getUserCache($v['user_id'], 'nickname');
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 通过
     * @param $ids
     * @return string
     * @throws
     */
    public function checker($ids)
    {
        $row = db('anchor')->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        try {
            db()->startTrans();

            $update1 = db('anchor')->where('id', $ids)->where('status', 1)
                ->setField(['status' => 2, 'audit_admin' => session('admin.id'), 'audit_time' => datetime()]);
            $update2 = db('user_business')->where('role', 1)->where('id', $row['user_id'])->setField('role', 2);
            if (!$update1 || !$update2) {
                $this->error('更新失败');
            }
            db()->commit();
            board_notice(Message::CMD_REFRESH_USER, ['user_id' => $row['user_id']]);
            send_im_msg_by_system_with_lang($row['user_id'], '您的主播签约申请已审核通过，如有需要可选择心仪的家族申请加入。');
        } catch (Exception $e) {
            db()->rollback();
            $this->error('操作失败');
        }
        $this->success();
    }


    /**
     * 驳回
     * @param $ids
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function rebut($ids)
    {
        $row = db('anchor')->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            try {
                db()->startTrans();
                $update1 = db('anchor')->where('id', $ids)->where('status', 1)
                    ->setField(['status' => 0, 'audit_admin' => session('admin.id'), 'audit_time' => datetime()]);
                if (!$update1) {
                    throw new \Exception('操作失败,请刷新');
                }
                db()->commit();
                send_im_msg_by_system_with_lang($row['user_id'], '您的主播签约申请审核未通过，原因为：%s', input('message'));
            } catch (Exception $e) {
                db()->rollback();
                $this->error('操作失败,请刷新');
            }
            $this->success();
        }
        return $this->view->fetch();
    }


    //查看协议
    public function pact_view($ids)
    {
        try {
            $row = db('anchor')->where('id', $ids)->find();
            if (!db('anchor')->where('user_id', $row['user_id'])->value('sign_img')) {
                $this->error('无数据');
            }
        } catch (\Exception $e) {
            $this->error('无法查看' . $e->getMessage());
        }
        $this->success('', '', \think\Env::get('app.page_url') . '/Anchor.html?type=1&userid=' . $row['user_id']);
    }
}
