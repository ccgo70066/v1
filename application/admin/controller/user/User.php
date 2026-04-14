<?php

namespace app\admin\controller\user;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\api\library\ImService;
use app\api\library\RoomService;
use app\common\controller\Backend;
use app\common\exception\ApiException;
use app\common\library\Auth;
use app\common\library\Yunxin;
use app\common\service\UserBusinessService;
use fast\Random;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Log;
use think\Model;
use app\common\model\UserBusiness;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $noNeedLogin = ['total'];
    protected $noNeedRight = ['total'];
    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';
    protected $date_range = [
        '1'    => '1天',
        '3'    => '3天',
        '7'    => '7天',
        '30'   => '1个月',
        '60'   => '2个月',
        '180'  => '半年',
        '365'  => '1年',
        '3650' => '永久(10年)'
    ];

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
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
            $filter = json_decode($this->request->get('filter'), true);
            $whereOther = [];
            if (isset($filter['business.role']) && $filter['business.role'] == 4) {
                $union_owner = Db::name('union')->where(['status' => 1])->column('owner_id');
                $whereOther = ['user.id' => ['in', $union_owner]];
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $with = [
                'business' => function ($query) {
                    $query->withField('amount,reward_amount,role');
                },
                'group'    => function ($query) {
                    $query->withField('name');
                },
            ];
            $level = db('level')->where('grade', '>', 0)->column('icon', 'grade');
            $list = $this->model
                ->with($with)
                ->where($where)
                ->where($whereOther)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $items = $list->items();
            foreach ($items as &$item) {
                $item['level_icon'] = $level[$item['level']] ?? '';
            }
            $result = [
                "total"  => $list->total(),
                "rows"   => $items,
                'extend' => [
//                    'online_user' => $this->model->where(['is_online' => 1])->count(),
                ]
            ];

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
        $this->view->assign(
            'groupList',
            build_select(
                'row[group_id]',
                \app\admin\model\UserGroup::column('id,name'),
                $row['group_id'],
                ['class' => 'form-control selectpicker']
            )
        );
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
     * 详情
     */
    public function detail()
    {
        $ids = input('ids');
        $baseField = 'id,username,nickname,mobile,gender,birthday,bio,from_unixtime(jointime) as jointime,joinip,from_unixtime(logintime) as logintime,loginip,from_unixtime(createtime) as createtime';
        $operatorField = 'version,imei,system,model';
        $businessField = 'level,integral,recharge_amount,reward_amount,amount,shred';
        $thirdField = 'user_id,platform';

        $row = [];
        $where = [];
        $where['id'] = $ids;
        $baseInfo = db('user')->field($baseField)->where($where)->find() ?? [];

        $operatorInfo = $this->model->field($operatorField)->where($where)->find() ?? [];
        if ($operatorInfo) {
            $operatorInfo = $operatorInfo->toArray();
        }

        $thirdInfo = db('user_third')->field($thirdField)->where(['user_id' => $ids])->select();

        if ($thirdInfo) {
            $thirdInfo = collection($thirdInfo)->toArray();
        }
        if (!model('UserBusiness')->where($where)->find()) {
            model('UserBusiness')->create($where);
        }
        $businessInfo = model('UserBusiness')->field($businessField)->where($where)->find()->toArray();

        $row['base_info'] = $baseInfo;
        $row['operator_info'] = $operatorInfo;
        $row['business_info'] = $businessInfo;
        $row['third_info'] = $thirdInfo;
        $bag_info = db('user_bag b')->join('gift g', 'b.gift_id=g.id', 'left')
            ->where(['b.user_id' => $ids, 'b.count' => ['gt', 0], 'g.price_type' => ['in', [1, 3]]])
            ->field('g.name,g.price,b.count')->select();
        $bag_total = ['name' => '合计', 'value' => 0];
        foreach ($bag_info as &$item) {
            $item['value'] = $item['price'] * $item['count'];
            $bag_total['value'] += $item['value'];
        }
        $bag_info[] = $bag_total;
        $row['bag_info'] = $bag_info;
        $row['nickname'] = db('user_nickname')->where('user_id', $ids)->order('expire_time desc')->select();
        // dump($row);die;
        return $this->view->fetch('detail', ['row' => $row]);
    }


    /**
     * 上分
     */
    public function add_amount($ids)
    {
        $row = $this->model->with('business')->find($ids);
        if (request()->isPost()) {
            $params = input('row/a');
            $params['number'] <= 0 && $this->error('上分数量必须大于0');
            !$params['comment'] && $this->error('上分原因必填');
            try {
                Db::startTrans();
                user_business_change($ids, $params['type'], $params['number'], 'increase', $params['comment'], 0);
                $type_arr = ['amount' => '金幣', 'reward_amount' => '收益'];
                db('admin_option_log')->insert([
                    'admin_id' => session('admin.id'),
                    'user_id'  => $ids,
                    'option'   => 4,
                    'comment'  => $params['comment'],
                    'params'   => json_encode(input('')),
                    'content'  => $type_arr[$params['type']] . '上分,数量:' . $params['number'],
                    'amount'   => $params['number'],
                ]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success();
        }
        $add_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 4])->sum('amount');
        $sub_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 5])->sum('amount');
        $diff = $add_total - $sub_total;
        $this->assign('row', $row);
        $this->assign('info', "总上分: $add_total, 总下分: $sub_total, 差分: $diff");
        return $this->view->fetch();
    }

    /**
     * 下分
     */
    public function remove_amount($ids = null)
    {
        $row = $this->model->with('business')->find($ids);
        if ($this->request->isPost()) {
            $params = input('row/a');
            $params['number'] <= 0 && $this->error('下分数量必须大于0');
            try {
                Db::startTrans();
                $amount = db('user_business')->where('id', $ids)->value($params['type']);
                if ($amount < $params['number']) {
                    throw new Exception('分值不足,请确认后重试');
                }

                user_business_change($ids, $params['type'], $params['number'], 'decrease', $params['comment'], 0);
                $type_arr = ['amount' => '金幣', 'reward_amount' => '收益'];
                db('admin_option_log')->insert([
                    'admin_id' => session('admin.id'),
                    'user_id'  => $ids,
                    'option'   => 5,
                    'comment'  => $params['comment'],
                    'params'   => json_encode(input('')),
                    'content'  => $type_arr[$params['type']] . '下分,数量:' . $params['number'],
                    'amount'   => $params['number'],
                ]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success();
        }
        $add_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 4])->sum('amount');
        $sub_total = db('admin_option_log')->where(['user_id' => $ids, 'option' => 5])->sum('amount');
        $diff = $add_total - $sub_total;
        $this->assign('row', $row);
        $this->assign('info', "总上分: $add_total, 总下分: $sub_total, 差分: $diff");
        return $this->view->fetch();
    }

    /**
     * 设置行为
     * @param null $ids
     * @return string
     * @throws Exception
     */
    public function set_behaviour($ids = null)
    {
        $row = $this->model->find($ids);
        if ($this->request->isPost()) {
            $params = input('row/a');
            if ($params['actor_status'] && $params['actor_status'] <> $row['actor_status']) {
                db('user')->where('id', $ids)->setField('actor_status', $params['actor_status']);
            }
            $this->success();
        }
        $this->assign('row', $row);
        return $this->view->fetch();
    }


    /**
     * 封禁用户
     */
    public function add_blacklist()
    {
        if ($this->request->isAjax()) {
            $user_id = input('uid');
            $days = input('row.days');
            $user = db('user')->field('id,mobile,imei')->find($user_id);
            $type = input('row.type/a');
            $form = input('row.form');
            $disableUserFlag = false;
            foreach ($type as $item) {
                if ($item == 1) {
                    $disableUserFlag = true;
                    $number = $user['id'];
                }
                if ($item == 2) {
                    $number = $user['imei'];
                    // 封禁此设备注册的其它用户
                    $other_user = db('user')->where([
                        'imei' => $user['imei'],
                        'id'   => ['neq', $user['id']]
                    ])->column('id');
                    Db::name('user_token')->where('user_id', 'in', $other_user)->delete();
                    foreach ($other_user as $other) {
                        // user_blacklist_after($other);
                        board_notice(Message::CMD_KICK_USER, ['user_id' => $other]);
                    }
                };
                if ($item == 3) {
                    if (!$user['mobile']) {
                        continue;
                    }
                    $disableUserFlag = true;
                    $number = $user['mobile'];
                }
                $is_exist = db('blacklist')->where([
                    'type'   => $item,
                    'number' => $number,
                ])->count();
                if ($is_exist) {
                    db('blacklist')->where(['type' => $item, 'number' => $number,])
                        ->setField([
                            'break_rule' => input('row.comment'),
                            'end_time'   => datetime(strtotime("+{$days}days")),
                            'creator'    => session('admin.id'),
                            'form'       => $form
                        ]);
                } else {
                    db('blacklist')->insert([
                        'type'       => $item,
                        'number'     => $number,
                        'break_rule' => input('row.comment'),
                        'end_time'   => datetime(strtotime("+{$days}days")),
                        'creator'    => session('admin.id'),
                        'form'       => $form
                    ]);
                }
            }
            $disableUserFlag && user_blacklist_after($user_id);
            board_notice(Message::CMD_KICK_USER, ['user_id' => $user_id]);
            Db::name('user_token')->where('user_id', $user_id)->delete();
            $this->success();
        }

        return $this->view->fetch('', [
            'row'        => $this->model->find(input('ids')),
            'date_range' => $this->date_range,
            'type_range' => [
                1 => '用户ID',
                3 => '手机号',
                2 => '设备IMEI'
            ],
            'form_range' => [
                1 => '封禁提示',
                2 => '网络异常提示'
            ]
        ]);
    }

    /**
     * 解封用户
     */
    public function remove_blacklist($ids)
    {
        if ($this->request->isPost()) {
            $user = db('user')->field('id,mobile,imei')->find($ids);
            if ($user) {
                db('user')->where(['imei' => $user['imei'], 'status' => 'hidden'])->setField(['status' => 'normal']);
                db('blacklist')->where(['type' => 1, 'number' => $ids])->delete();
                db('blacklist')->where(['type' => 2, 'number' => $user['imei']])->delete();
                db('blacklist')->where(['type' => 3, 'number' => $user['mobile']])->delete();
                user_unblacklist_after($ids);
            }
            $this->success();
        }
    }

    /**
     * 清空币收益背包操作
     */
    public function clear_value($ids)
    {
        $user_business = new UserBusiness;
        $row = $this->model->find($ids);
        if ($this->request->isPost()) {
            $params = input('row/a');
            !isset($params['clear_type']) && $this->error('请选择清理选项');
            $user_id = input('ids');
            foreach ($params['clear_type'] as $param) {
                if ($param == 'gift') {
                    $gift = db('user_bag b')->join('gift g', 'b.gift_id=g.id', 'left')
                        ->field('g.name,b.count')
                        ->where(['b.user_id' => $user_id, 'count' => ['gt', 0]])
                        ->select();
                    if ($gift) {
                        $content = '清理背包[礼物]:';
                        foreach ($gift as $item) {
                            $content .= $item['name'] . '(' . $item['count'] . ')';
                        }
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 2,
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'comment'  => $params['comment'],
                            'content'  => $content,
                        ]);
                        db('user_bag')->where('user_id', $user_id)->delete();
                    }
                }
                if ($param == 'adornment') {
                    $adornmen = db('user_adornment ua')
                        ->join('adornment a', 'ua.adornment_id=a.id', 'left')
                        ->field('a.name,ua.expired_days')
                        ->where([
                            'ua.user_id' => $user_id,
                        ])
                        ->select();
                    if ($adornmen) {
                        $content = '清理背包[头像框]:';
                        foreach ($adornmen as $item) {
                            if ($item['expired_days'] == -1) {
                                $content .= $item['name'] . '(永久)';
                            } else {
                                $content .= $item['name'] . '(' . $item['expired_days'] . '天)';
                            }
                        }
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 2,
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'comment'  => $params['comment'],
                            'content'  => $content,
                        ]);
                        db('user_adornment')->where('user_id', $user_id)->delete();
                    }
                }
                if ($param == 'vip') {
                    $vip = db('user_vip vp')
                        ->join('vip n', 'vp.grade=n.grade', 'left')
                        ->field('n.name,vp.expire_time')
                        ->where(['vp.id' => $user_id,])
                        ->find();
                    if ($vip) {
                        $date = floor((strtotime($vip['expire_time']) - time()) / 86400);
                        $content = '清理背包[VIP]:' . $vip['name'] . '(' . $date . '天)';
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 2,
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'comment'  => $params['comment'],
                            'content'  => $content,
                        ]);
                        db('user_vip')->where('id', $user_id)->delete();
                    }
                    db('user_business')->where('id', $user_id)->setField(['level' => 0]);
                }
                if ($param == 'car') {
                    $car = db('user_car uc')
                        ->join('car c', 'uc.car_id=c.id', 'left')
                        ->field('c.name,uc.expired_days')
                        ->where([
                            'uc.user_id' => $user_id,
                        ])
                        ->select();
                    if ($car) {
                        $content = '清理背包[坐骑]:';
                        foreach ($car as $item) {
                            if ($item['expired_days'] == -1) {
                                $content .= $item['name'] . '(永久)';
                            } else {
                                $content .= $item['name'] . '(' . $item['expired_days'] . '天)';
                            }
                        }
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 2,
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'comment'  => $params['comment'],
                            'content'  => $content,
                        ]);
                        db('user_car')->where('user_id', $user_id)->delete();
                    }
                }
                if ($param == 'bubble') {
                    $bubble = db('user_bubble ub')
                        ->join('bubble b', 'ub.bubble_id=b.id', 'left')
                        ->field('b.name,ub.expired_days')
                        ->where([
                            'ub.user_id' => $user_id,
                        ])
                        ->select();
                    if ($bubble) {
                        $content = '清理背包[聊天气泡]:';
                        foreach ($bubble as $item) {
                            if ($item['expired_days'] == -1) {
                                $content .= $item['name'] . '(永久)';
                            } else {
                                $content .= $item['name'] . '(' . $item['expired_days'] . '天)';
                            }
                        }
                        db('admin_option_log')->insert([
                            'admin_id' => session('admin.id'),
                            'user_id'  => $user_id,
                            'option'   => 2,
                            'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                            'comment'  => $params['comment'],
                            'content'  => $content,
                        ]);
                        db('user_bubble')->where('user_id', $user_id)->delete();
                    }
                }
                if ($param == 'tail') {
                    $tail = db('user_tail ut')->join('tail t', 'ut.tail_id=t.id', 'left')
                        ->field('t.name,ut.expired_time,ut.expired_days')
                        ->where('ut.user_id', $user_id)->select();
                    $content = '清理背包[铭牌]:';
                    foreach ($tail as $item) {
                        if ($item['expired_days'] == -1) {
                            $content .= $item['name'] . '(永久)';
                        } else {
                            $content .= $item['name'] . '(' . $item['expired_days'] . '天)';
                        }
                    }
                    db('admin_option_log')->insert([
                        'admin_id' => session('admin.id'),
                        'user_id'  => $user_id,
                        'option'   => 2,
                        'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                        'comment'  => $params['comment'],
                        'content'  => $content,
                    ]);
                    db('user_tail')->where('user_id', $user_id)->delete();
                }
            }
            $user_business->clear_cache($user_id);
            board_notice(Message::CMD_REFRESH_USER, ['user_id' => $user_id]);
            $this->success('');
        }
        return $this->view->fetch('', ['row' => $row]);
    }


    /**
     * 修改渠道
     */
    public function update_appid($ids)
    {
        $row = $this->model->get($ids);
        if ($this->request->isPost()) {
            $change_log = input('row/a');
            db('channel_change_log')->insert($change_log);
            db('user')->where('id', $ids)->setField('appid', $change_log['appid']);
            $this->success();
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }


    /**
     * 修改靓号
     */
    public function update_beautiful_id($ids)
    {
        $row = $this->model->get($ids);
        if ($this->request->isPost()) {
            $data = input('row/a');
            $beautiful_id = (int)$data['beautiful_id'];
            if (!$beautiful_id || $beautiful_id < 100 || $beautiful_id > 9999999) {
                $this->error('靓号位数为3~7位');
            }
            $exist = Db::name('user')->where('beautiful_id', $beautiful_id)->find();
            if ($exist) {
                $this->error('该靓号已存在');
            }
            $update = db('user')->where('id', $ids)->setField('beautiful_id', $beautiful_id);
            $update ? $this->success() : $this->error('更新失败,请刷新重试');
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 违规设置
     */
    public function set_illegal($ids)
    {
        $row = $this->model->get($ids);
        $illegal_name = '违规昵称';
        $illegal_bio = '这家伙很懒，什么都没有留下~';
        if ($this->request->isPost()) {
            $options = input('row.checkbox/a');
            $update = ['id' => $ids];
            if ($options) {
                $im = new ImService();
                foreach ($options as $key => $option) {
                    if ($option == 'nickname') {
                        $update['nickname'] = $illegal_name . Random::alnum(4);
                        $im->updateUser($ids, $update['nickname'], '');
                        db('user_nickname')->insert(['user_id' => $row['id'], 'nickname' => $row['nickname']]);
                    }
                    if ($option == 'avatar') {
                        $avatar = array_filter(config('app.default_avatar_gender'), function ($item) use ($row) {
                            return $item['gender'] == $row['gender'] ? $item : [];
                        });
                        $update['avatar'] = $avatar[array_rand($avatar)]['avatar'];
                        $im->updateUser($ids, '', $update['avatar']);
                        db('user_audit_image')->where('user_id', $ids)->where('img_type', 'avatar')->delete();
                    }
                    $option == 'bio' && $update['bio'] = $illegal_bio;
                    if ($option == 'image') {
                        $update['image'] = '';
                    }
                }

                db('user')->update($update);
                board_notice(Message::CMD_REFRESH_USER, ['user_id' => $row['id']]);
            }
            $this->success();
        }
        $images = [];
        /*for ($i = 1; $i <= 5; $i++) {
            $row['image' . $i] && $images[$i] = $row['image' . $i];
        }*/
        $this->view->assign("row", $row);
        $this->view->assign("images", $images);
        return $this->view->fetch();
    }

    /**
     * 重置密码
     */
    public function update_password($ids)
    {
        $row = $this->model->get($ids);
        if ($this->request->isPost()) {
            $data = input('row/a');
            $salt = Random::alnum();
            $new_password = (new Auth)->getEncryptPassword($data['password'], $salt);
            $res = db('user')->where('id', $ids)->setField(['password' => $new_password, 'salt' => $salt]);
            if ($res) {
                db('admin_option_log')
                    ->insert([
                        'option'   => 9,
                        'admin_id' => session('admin.id'),
                        'user_id'  => $ids,
                        'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                        'comment'  => '重置密码',
                        'content'  => '用户ID' . $ids . '重置密码',
                    ]);

                $this->success();
            } else {
                $this->error('更新失败，请重试');
            }
        }
        $row['new_password'] = Random::alnum();
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }


    public function clear_login_limit()
    {
        $id = input('ids');
        $user = Db::name('user')->where('id', $id)->find();
        if ($user) {
            redis()->del('login_fail' . $user['mobile']);
            redis()->del('login_fail' . $user['id']);
        }
        $this->success();
    }

    /**
     * 关闭防顶号
     */
    public function allow_other_imei_login($ids)
    {
        $row = $this->model->get($ids);
        $switch = input('switch');  //1=允许顶号，0=禁止顶号
        $switch_text = [0 => '解除', 1 => '开启'];
        if ($this->request->isAjax()) {
            $res = db('user')->where('id', $ids)->setField('allow_other_imei_login', $switch);
            if ($res) {
                db('admin_option_log')->insert([
                    'option'   => 6,
                    'admin_id' => session('admin.id'),
                    'user_id'  => $ids,
                    'params'   => json_encode(input(''), JSON_UNESCAPED_UNICODE),
                    'content'  => $switch_text[$switch] . '防顶号',
                    'comment'  => '',
                ]);

                $this->success();
            } else {
                $this->error('更新失败，请重试');
            }
        }
        $this->view->assign("text", $switch_text[$switch] . '防顶号');
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }


    /**
     * 更新备注
     */
    public function update_comment($ids)
    {
        $row = $this->model->get($ids);
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
     * 统计查询
     */
    public function total()
    {
        $online_user = $this->model->where(['is_online' => 1])->count();
        $result = [
            'online_user' => $online_user, //在线人数
        ];
        return json($result);
    }

    /**
     * 设置/取消运营身份
     */
    public function set_role($ids, $type)
    {
        $user_id = $ids;
        UserBusinessService::set_user_role($user_id, $type ? 4 : 1);
        board_notice(Message::CMD_REFRESH_USER, ['user_id' => $user_id]);
        send_im_msg_by_system_with_lang($user_id, $type ? '您的身份已被设置为运营身份' : '您的身份已被重置为普通用户');

        $this->success();


        $user = Db::name('user_business')->where('id', $ids)
            ->field('union_id,id as user_id')->field("CASE role WHEN 4 THEN '族长' WHEN 3 THEN '家族成员' WHEN 2 THEN '主播'  ELSE '普通用户' END as role")
            ->find();
        if (!$user) {
            $this->error(__('No Results were found'));
        }
        $user['nickname'] = Db::name('user')->where('id', $user_id)->value('nickname');
        $room_admin = Db::name('room_admin')->alias('ra')->where('ra.user_id', $user_id)
            ->join('room r', 'ra.room_id = r.id')
            ->field('ra.room_id,r.name,r.beautiful_id,ra.role as admin_role')
            ->field("CASE ra.role WHEN 1 THEN '房主' WHEN 2 THEN '房管' ELSE '陪陪' END AS role ")
            ->select();


        if ($this->request->isPost()) {
            try {
                $current_role = db('user_business')->where('id', $user_id)->where(
                    'role',
                    'in',
                    [2, 3, 4]
                )->value('role');
                if (!$current_role) {
                    throw new \Exception('数据有更新,请刷新');
                }
                if ($current_role == 4) {
                    throw new ApiException('取消族长身份需通过<解散家族>或者<转移族长>来取消');
                }
                db()->startTrans();

                $update1 = db('anchor')->where('user_id', $user_id)->where('status', 2)
                    ->setField(['status' => 0, 'audit_admin' => session('admin.id'), 'audit_time' => datetime()]);
                $update2 = db('user_business')->where('id', $user_id)->setField(['role' => 1, 'union_id' => 0]);
                Db::name('room_admin')->where('user_id', $user_id)->delete();
                Db::name('union_user')->where(['user_id' => $user_id])->delete();
                $roomService = new RoomService();
                foreach ($room_admin as $value) {
                    $roomService->roomRoleRemove($value['room_id'], $user_id);
                    //删除房主后,将新设置家族族长为房主
                    if ($value['admin_role'] == 1) {
                        $union_master = Db::name('union')->where('id', $user['union_id'])->value('owner_id');
                        db('room_admin')
                            ->insert([
                                'room_id' => $value['room_id'],
                                'role'    => 1,
                                'user_id' => $union_master
                            ]);


                        db('room_master_log')->insert([
                            'room_id'    => $value['room_id'],
                            'user_id'    => $union_master,
                            'to_user_id' => $union_master
                        ]);
                        //在云信中将新的房主设置为管理员
                        $imService = new ImService();
                        $imService->roomSetAuth($value['room_id'], $union_master, true);
                    }
                }
                db()->commit();
                board_notice(Message::CMD_REFRESH_USER, ['user_id' => $user_id]);
                send_im_msg_by_system_with_lang($user_id, '您的身份已被重置为普通用户');
                !empty($union_master) && board_notice(Message::CMD_REFRESH_USER, ['user_id' => $union_master]);
            } catch (\Throwable $e) {
                db()->rollback();
                $this->error($e->getMessage());
            }
            $this->success();
        }
        $this->assign('user', $user);
        $this->assign('room_admin', $room_admin);
        return $this->view->fetch();
    }


}
