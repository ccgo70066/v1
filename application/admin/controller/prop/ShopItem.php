<?php

namespace app\admin\controller\prop;

use app\common\controller\Backend;

/**
 * 商城物品管理
 *
 * @icon fa fa-circle-o
 */
class ShopItem extends Backend
{

    /**
     * ShopItem模型对象
     * @var \app\admin\model\ShopItem
     */
    protected $model = null;
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ShopItem;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("cateList", $this->model->getCateList());
        $this->view->assign("showList", $this->model->getShowList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    public function get_item()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'htmlspecialchars']);

        //搜索关键词,客户端输入以空格分开,这里接收为数组
        $word = (array)$this->request->request("q_word/a");
        //当前页
        $page = $this->request->request("pageNumber");
        //分页大小
        $pagesize = $this->request->request("pageSize");
        //搜索条件
        $andor = $this->request->request("andOr", "and", "strtoupper");
        //排序方式
        $orderby = (array)$this->request->request("orderBy/a");
        //显示的字段
        $field = $this->request->request("showField");
        //主键
        $primarykey = $this->request->request("keyField");
        //主键值
        $primaryvalue = $this->request->request("keyValue");
        //搜索字段
        $searchfield = (array)$this->request->request("searchField/a");
        //自定义搜索条件
        $custom = (array)$this->request->request("custom/a");

        $where = [];
        $logic = $andor == 'AND' ? '&' : '|';
        foreach ($word as $k => $v) {
            if ($v) {
                $where[$searchfield[$k]] = ["like", "%{$v}%"];
            }
        }
        if ($primaryvalue) {
            $where[$primarykey] = $primaryvalue;
        }

        //类型:1=礼物,2=头像框,3=坐骑,4=贵族,5=守护,6=气泡
        switch ($custom['type']) {
            case '1': // 碎片礼物
                // $where['type'] = 1;
                $data = db('gift')->field('id,name')->where($where)->where(['status' => 1])->order('weigh asc')->page($page,
                    $pagesize)->select();
                $total = db('gift')->field('id,name')->where($where)->where(['status' => 1])->count();
                return json(['list' => $data, 'total' => $total]);
            case '2':
                $data = db('adornment')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->order('weigh asc')->page($page, $pagesize)->select();
                $total = db('adornment')->field('id,name')->where($where)->where(['status' => 1])->count();
                return json(['list' => $data, 'total' => $total]);
            case '3':
                $data = db('car')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->order('weigh asc')->page($page, $pagesize)->select();
                $total = db('car')->field('id,name')->where($where)->where(['status' => 1])->count();
                return json(['list' => $data, 'total' => $total]);
            case '4':
                return json([
                    'list' => db('noble')->field('id,name')->where($where)->order('weigh asc')->select(),
                ]);
            case '5':
                $data = db('guard')->field('id,name')->where($where)->order('weigh asc')->select();
                return json(['list' => $data]);
            case '6':
                $data = db('bubble')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->order('weigh asc')->page($page, $pagesize)->select();
                $total = db('bubble')->field('id,name')->where($where)->where(['status' => 1])->count();
                return json(['list' => $data, 'total' => $total]);
            case '8':
                $data = db('tail')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->order('weigh asc')->page($page, $pagesize)->select();
                $total = db('tail')->field('id,name')->where($where)->where(['status' => 1])->count();
                return json(['list' => $data, 'total' => $total]);
        }
    }


    public function get_item_name()
    {
        $type = input('type');
        $where['id'] = input('id');

        //类型:1=礼物,2=头像框,3=坐骑,4=贵族,5=守护,6=气泡
        switch ($type) {
            case '1': // 碎片礼物
                $data = db('gift')->field('id,name')->where($where)->where(['status' => 1])->find();
                break;
            case '2':
                $data = db('adornment')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->field('name')->find();
                break;

            case '3':
                $data = db('car')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->field('name')->find();
                break;

            case '4':
                return db('noble')->field('id,name')->where($where)->field('name')->find();
                break;

            case '5':
                $data = db('guard')->field('id,name')->where($where)->field('name')->find();
                break;

            case '6':
                $data = db('bubble')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->field('name')->find();
                break;
            case '8':
                $data = db('tail')->field('id,name')->where($where)->where([
                    'status' => 1,
                ])->field('name')->find();
                break;

        }
        return $data ?? false;
    }


}
