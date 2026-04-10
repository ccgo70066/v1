<?php

namespace app\admin\controller;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\service\RedisService;
use app\common\controller\Backend;
use app\common\model\UserBusiness;
use Form;


/**
 * 运营日志
 *
 * @icon fa fa-circle-o
 */
class AdminOptionLog extends Backend
{

    /**
     * AdminOptionLog模型对象
     * @var \app\admin\model\AdminOptionLog
     */
    protected $model = null;
    protected $searchFields = 'id';
    protected $date_range = [
        '1'   => '1天',
        '3'   => '3天',
        '7'   => '7天',
        '30'  => '1个月',
        '60'  => '2个月',
        '90'  => '3个月',
        '180' => '半年',
        '365' => '1年',
        '-1'  => '永久'
    ];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\AdminOptionLog;
        $this->view->assign("optionList", $this->model->getOptionList());
        $this->view->assign('date_range', $this->date_range);
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
            if (input('option') == 'load_beautiful_id') {
                return db('user')->where('id', input('user_id'))->value('beautiful_id');
            }
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $with = 'admin';
            $total = $this->model
                ->with($with)
                ->where($where)
//                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            //获取用户昵称
            if (count($list)) {
                foreach ($list as $key => $value) {
                    $list[$key]['user']['nickname'] = RedisService::getUserCache($value['user_id'], 'nickname');
                }
            }

            $sum_up_amount = $this->model
                ->with($with)
                ->where($where)
                ->where('option', 4)
                ->sum('amount');
            $sum_down_amount = $this->model
                ->with($with)
                ->where($where)
                ->where('option', 5)
                ->sum('amount');

            $result = array(
                "total"  => $total,
                "rows"   => $list,
                "extend" => [
                    'sum_up_amount'     => (float)$sum_up_amount, //上分合计
                    'sum_down_amount'   => (float)$sum_down_amount, //上分合计
                    'sum_profit_amount' => bcsub($sum_up_amount, $sum_down_amount, 2),
                ],
            );

            return json($result);
        }

        if (input('?param.ids') && !input('?get.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        return $this->view->fetch();
    }


    /**
     * 发放道具
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
                $user_id = input('uid');
                $user = db('user')->find($user_id);
                !$user && $this->error('找不到该用户,请确认用户ID后再试');
                if ($params['adornment_id'] && !$params['adornment_days']) {
                    $this->error('请选择头像框时限');
                }
                if ($params['vip_id'] && !$params['vip_days']) {
                    $this->error('请选择VIP时限');
                }
                if ($params['bubble_id'] && !$params['bubble_days']) {
                    $this->error('请选择聊天气泡时限');
                }
                if ($params['car_id'] && !$params['car_days']) {
                    $this->error('请选择坐骑时限');
                }
                if ($params['tail_id'] && !$params['tail_days']) {
                    $this->error('请选择铭牌时限');
                }
                try {
                    db()->startTrans();
                    if ($params['adornment_id']) {
                        user_adornment_add($user_id, $params['adornment_id'], $params['adornment_days'], 2);
                        $item = db('adornment')->find($params['adornment_id']);
                        $adornment_days = $params['adornment_days'] == -1 ? '永久' : $params['adornment_days'];
                        $content = '头像框[' . $item['name'] . '](' . $adornment_days . '天)';
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 1,
                            'comment'  => input('row.comment'),
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'content'  => $content,
                        ]);
                    }
                    if ($params['vip_id']) {
                        user_vip_add($user_id, $params['vip_id'], $params['vip_days']);
                        $item = db('vip')->find($params['vip_id']);
                        $vip_days = $params['vip_days'];
                        $content = 'VIP[' . $item['name'] . '](' . $vip_days . '天)';
                        board_notice(Message::CMD_REFRESH_USER, ['user_id' => $user_id]);
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 1,
                            'comment'  => input('row.comment'),
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'content'  => $content,
                        ]);
                        trace(getLastSql());
                    }
                    if ($params['bubble_id']) {
                        user_bubble_add($user_id, $params['bubble_id'], $params['bubble_days'], 2);
                        $item = db('bubble')->find($params['bubble_id']);
                        $bubble_days = $params['bubble_days'] == -1 ? '永久' : $params['bubble_days'];
                        $content = '聊天气泡[' . $item['name'] . '](' . $bubble_days . '天)';
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 1,
                            'comment'  => input('row.comment'),
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'content'  => $content,
                        ]);
                    }
                    if ($params['car_id']) {
                        user_car_add($user_id, $params['car_id'], $params['car_days'], 2);
                        $item = db('car')->find($params['car_id']);
                        $car_days = $params['car_days'] == -1 ? '永久' : $params['car_days'];
                        $content = '坐骑[' . $item['name'] . '](' . $car_days . '天)';
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 1,
                            'comment'  => input('row.comment'),
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'content'  => $content,
                        ]);
                    }
                    if ($params['tail_id']) {
                        user_tail_add($user_id, $params['tail_id'], $params['tail_days'], 3);
                        $item = db('tail')->find($params['tail_id']);
                        $tail_days = $params['tail_days'] == -1 ? '永久' : $params['tail_days'];
                        $content = '铭牌[' . $item['name'] . '](' . $tail_days . '天)';
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 1,
                            'comment'  => input('row.comment'),
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'content'  => $content,
                        ]);
                    }
                    \app\common\model\UserBusiness::clear_cache($user_id);
//                send_im_msg_by_system($user_id, $params['notice']);
                    db()->commit();
                } catch (\Throwable $e) {
                    error_log_out($e);
                    db()->rollback();
                    $this->error('操作失败');
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", null);
        return $this->view->fetch('edit');
    }


}
