<?php

namespace app\admin\controller\channel;

use app\common\controller\Backend;
use think\Db;
use think\exception\DbException;
use think\response\Json;

/**
 * 充值卡
 *
 * @icon fa fa-circle-o
 */
class Card extends Backend
{

    /**
     * ChannelCard模型对象
     * @var \app\admin\model\ChannelCard
     */
    protected $model = null;
    protected $noNeedLogin = ['get_card_by_system'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ChannelCard;
        $this->view->assign("unitList", $this->model->getUnitList());
        $this->view->assign("systemList", $this->model->getSystemList());
        $this->view->assign("bageList", $this->model->getBageList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        if (input('option') == 'search_list') {
            $key = input('key') ?? 'id';
            $name = input('name') ?? 'name';
            return json($this->model->column($name, $key));
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


    public function get_card_by_system()
    {
        $system_ids = (input('system_ids') ?? input('custom.system')) ?: [];
        if (!$system_ids) {
            $this->success('无数据');
        }
        $where = [];
        if (input('searchKey') && input('searchValue')) {
            $where = [input('searchKey') => ['in', input('searchValue')]];
        }

        $data = Db::name('channel_card')
            ->where('system', 'in', $system_ids)
            ->where('status', 1)
            ->where($where)
            ->field('id,system,code,price')->order('weigh desc')->select();
        $system_Text = $this->model->getSystemList();
        foreach ($data as &$value) {
            $value['price'] = $value['code'] . ' ' . ($system_Text[$value['system']] ?? $value['system']) . ' ' . $value['price'] . '元';
        }
        return json(['list' => $data, 'total' => count($data)]);
    }


}
