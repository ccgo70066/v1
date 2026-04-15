<?php


namespace app\admin\controller\general;


use app\common\controller\Backend;

/**
 * 奖励物品数据--二级联动组件使用
 * Class RewardJson
 * @package app\admin\controller\general
 */
class RewardJson extends Backend
{
    protected $model = null;
    protected $noNeedRight = '*';
    protected $noNeedLogin = '*';

    public function _initialize()
    {
        parent::_initialize();
    }


    public function type()
    {
        $data = [
            'amount'    => '金幣',
            'gift'      => '礼物',
            'adornment' => '头像框',
            'car'       => '坐骑',
            'bubble'    => '聊天气泡',
        ];
        $list = [];
        $key_word = input('keyValue', '');
        $exclude = input('exclude', '');
        $data = array_index_filter($data, $exclude, true);
        foreach ($data as $k => $v) {
            $temp = [];
            $temp['id'] = $k;
            $temp['name'] = $v;
            if ($key_word) {
                if ($key_word == $k) {
                    $list[] = $temp;
                }
            } else {
                $list[] = $temp;
            }
        }
        $total = count($list);
        $result = array("total" => $total, "list" => $list);
        return json($result);
    }

    public function type_sign()
    {
        $data = [
            'amount' => '金幣',

            'adornment' => '头像框',
            'car'       => '坐骑',
            'bubble'    => '聊天气泡',
        ];
        $list = [];
        $key_word = input('keyValue');
        foreach ($data as $k => $v) {
            $temp = [];
            $temp['id'] = $k;
            $temp['name'] = $v;
            if ($key_word) {
                if ($key_word == $k) {
                    $list[] = $temp;
                }
            } else {
                $list[] = $temp;
            }
        }
        $total = count($list);
        $result = array("total" => $total, "list" => $list);
        return json($result);
    }

    public function index()
    {
        if ($this->request->isAjax()) {
            //当前页
            $page = $this->request->request("pageNumber");
            //分页大小
            $pagesize = $this->request->request("pageSize");
            switch ($this->request->request('type')) {
                case 'amount':
                    $list = [['id' => 0, 'name' => '金幣']];
                    $total = count($list);
                    break;
                case 'gift':
                    $where = ['status' => 1];
                    input('?keyValue') && $where['id'] = input('keyValue');
                    $total = db('gift')->where($where)->count();
                    $list = db('gift')->field('id,name')
                        ->where($where)
                        ->page($page, $pagesize)
                        ->select();
                    break;
                case 'adornment':
                    $where = ['status' => 1];
                    input('?keyValue') && $where['id'] = input('keyValue');
                    $total = db('adornment')->where($where)->count();
                    $list = db('adornment')->field('id,name')
                        ->where($where)
                        ->page($page, $pagesize)
                        ->select();
                    break;
                case 'car':
                    $where = ['status' => 1];
                    input('?keyValue') && $where['id'] = input('keyValue');
                    $total = db('car')->where($where)->count();
                    $list = db('car')->field('id,name')
                        ->where($where)
                        ->page($page, $pagesize)
                        ->select();
                    break;
                case 'bubble':
                    $where = ['status' => 1];
                    input('?keyValue') && $where['id'] = input('keyValue');
                    $total = db('bubble')->where($where)->count();
                    $list = db('bubble')->field('id,name')
                        ->where($where)
                        ->page($page, $pagesize)
                        ->select();
                    break;
                case 'noble':
                    $where = [];
                    input('?keyValue') && $where['id'] = input('keyValue');
                    $total = db('noble')->count();
                    $list = db('noble')->field('id,name')
                        ->page($page, $pagesize)
                        ->where($where)
                        ->order('weigh asc')
                        ->select();
                    break;
                case 'tail':
                    $where = [];
                    input('?keyValue') && $where['id'] = input('keyValue');
                    $total = db('tail')->count();
                    $list = db('tail')->field('id,name')
                        ->page($page, $pagesize)
                        ->where($where)
                        ->order('weigh asc')
                        ->select();
                    break;
            }
            $result = array("total" => $total ?? 0, "list" => $list ?? []);
            return json($result);
        }
    }

}
