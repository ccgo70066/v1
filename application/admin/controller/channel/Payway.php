<?php

namespace app\admin\controller\channel;

use app\admin\model\ChannelCard;
use app\common\controller\Backend;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;
use think\Validate;

/**
 * 支付路径
 *
 * @icon fa fa-circle-o
 */
class Payway extends Backend
{

    protected $relationSearch = true;
    /**
     * ChannelPayway模型对象
     * @var \app\admin\model\ChannelPayway
     */
    protected $noNeedLogin = ['get_payway_by_company'];
    protected $model = null;


    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ChannelPayway;
        $this->loadlang('Channel.card');
        $this->view->assign("systemList", (new ChannelCard())->getSystemList());
        $this->view->assign("payWayList", $this->model->getPayWayList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("openWayList", $this->model->getOpenWayList());
    }


    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 列表
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $cards = db('channel_card')->column('price,system,code,status', 'id');
        $with = [
            'company' => function ($query) {
                $query->withField('name');
            },
            'payway'  => function ($query) {
                $query->withField('name');
            },
        ];

        $list = $this->model
            ->with($with)
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as &$item) {
            $card_ids = explode(',', $item['card_ids']);
            $item['card_name'] = '';
            foreach ($card_ids as $v) {
                $item['card_name'] .= $cards[$v]['code'].' '.__('System '.$cards[$v]['system']).' '.$cards[$v]['price'] .($cards[$v]['status'] ==0 ?'(禁)' : '') . ',';
            }
        }

        $result = [
            'total' => $list->total(),
            'rows'  => $list->items()
        ];
        return json($result);
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            $this->view->assign("row", null);
            return $this->view->fetch('edit');
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if ($params['card_ids'] && is_array($params['card_ids'])) {
            $params['card_ids'] = implode(',', $params['card_ids']);
        }

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
                $this->model->validateFailException()->validate($validate);
            }
            $validate =  new Validate([
                'pay_name' => 'require|unique:channel_payway',
            ],[
                'pay_name.unique' => '渠道名称已存在',
            ]);
            if (!$validate->check($params)) {
                $this->error($validate->getError());
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        }catch (ValidateException|PDOException|Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            //渲染已选充值卡平台
            $selected_system = Db::name('channel_card')->where('id', 'in',
                $row['card_ids'])->group('system')->column('system');
            $this->view->assign('selected_system', $selected_system);
            //渲染已选充值卡
            $selected_card = $row['card_ids'] ?: 0;//防报错
            $cardList = Db::name('channel_card')
                ->fieldRaw('id,`system`,code,price,if(id in (' . $selected_card . '),1,0) as `is_checked`')
                ->orderRaw('id in (' . $selected_card . ') desc')
                ->order('weigh desc')->select();
            $system_Text = (new ChannelCard())->getSystemList();
            foreach ($cardList as &$value) {
                $value['price'] = $value['code'].' '.($system_Text[$value['system']] ?? $value['system']) . ' ' . $value['price'] . '元';
            }
            $this->view->assign('cardList', $cardList);
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if ($params['card_ids'] && is_array($params['card_ids'])) {
            $params['card_ids'] = implode(',', $params['card_ids']);
        }
        $result = false;
        Db::startTrans();
        try{
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $validate =  new Validate([
                'pay_name' => 'require|unique:channel_payway,pay_name,'.$row->id,
            ],[
                'pay_name.unique' => '渠道名称已存在',
            ]);
            if (!$validate->check($params)) {
                $this->error($validate->getError());
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        }catch (ValidateException|PDOException|Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }


    public function get_payway_by_company()
    {
        $company_id = input('company_ids') ?? [];
        if (!$company_id) {
            $this->success('无数据');
        }
        $data = Db::name('channel_payway')
            ->where('company_id', 'in', $company_id)
            ->field('id,pay_name,company_id')->order('weigh desc')->select();
        $this->success('', '', $data);
    }

}
