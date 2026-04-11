<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\Loader;


class ErrorLog extends Backend
{


    protected $noNeedRight = '*';
    protected $model = null;
    protected $content = '';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ErrorLog();
    }

    public function index()
    {
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->count();

            $list = $this->model
                ->where($where)
                ->order('create_time', 'desc')
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


    public function detail($ids)
    {
        $row = $this->model->get(['_id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // dump(Db::connect('mongodb')->table('aa_error_log')->where('_id',$ids)->find());
        $row = $row->toArray();

        $this->recursive_array($row);

        $this->view->assign("row", $row);
        $this->view->assign("all", $this->content);
        return $this->view->fetch();
    }

    public function recursive_array($arr)
    {
        foreach ($arr as $key => $value) {
            // 如果当前元素是一个数组，调用递归函数
            if (is_array($value)) {
                $this->content .= "<ul style='font-size: 20px;'><b>$key</b>";
                $this->recursive_array($value);
                $this->content .= '</ul>';
            } else {
                if (is_string($value) || is_numeric($value)) {
                    $this->content .= "<ul style='font-size: 16px;'><b>$key</b> :  $value</ul>";
                }
            }
        }
    }



    public function del_all()
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $pk = $this->model->getPk();
        $count = 0;
        Db::startTrans();
        try {
            $count = $this->model->where($pk, '<>', '')->delete();
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }

    /**
     * 兼容mongodb的版本
     * @param $searchfields
     * @param $relationSearch
     * @return array
     */
    protected function buildparams($searchfields = null, $relationSearch = null)
    {
        $searchfields = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->get("search", '');
        $filter = $this->request->get("filter", '');
        $op = $this->request->get("op", '', 'trim');
        $sort = $this->request->get(
            "sort",
            !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id'
        );
        $order = $this->request->get("order", "DESC");
        $offset = $this->request->get("offset", 0);
        $limit = $this->request->get("limit", 0);
        $filter = (array)json_decode($filter, true);
        $op = (array)json_decode($op, true);
        $filter = $filter ? $filter : [];
        $where = [];
        $tableName = '';
        if ($relationSearch) {
            if (!empty($this->model)) {
                $name = Loader::parseName(basename(str_replace('\\', '/', get_class($this->model))));
                $name = $this->model->getTable();
                $tableName = $name . '.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => & $item) {
                $item = stripos($item, ".") === false ? $tableName . trim($item) : $item;
            }
            unset($item);
            $sort = implode(',', $sortArr);
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $where[] = [$tableName . $this->dataLimitField, 'in', $adminIds];
        }
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        trace($filter, 'm');
        foreach ($filter as $k => $v) {
            trace($v, 'm');
            $sym = isset($op[$k]) ? $op[$k] : '=';
            if (stripos($k, ".") === false) {
                $k = $tableName . $k;
            }
            $v = !is_array($v) ? trim($v) : $v;
            is_numeric($v) && $v = (float)$v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            switch ($sym) {
                case '=':
                    $where[$k] = $v;
                    break;
                case '<>':
                    $where[$k] = [$sym, (string)$v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[$k] = [trim(str_replace('%...%', '', $sym)), "{$v}"];
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where[$k] = [$sym, intval($v)];
                    break;
                // case 'FINDIN':
                // case 'FINDINSET':
                // case 'FIND_IN_SET':
                //     $where[] = "FIND_IN_SET('{$v}', " . ($relationSearch ? $k : '`' . str_replace('.', '`.`',
                //                 $k) . '`') . ")";
                //     break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[$k] = [str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[$k] = [$sym, $arr];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    foreach ($arr as &$item) {
                        strtotime($item) && $item = strtotime($item);
                    }
                    $where[$k] = [str_replace('RANGE', 'BETWEEN', $sym), $arr];
                    break;
                case 'LIKE':
                case 'LIKE %...%':
                    $where[] = [$k, 'LIKE', "%{$v}%"];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                default:
                    break;
            }
        }
        // dump($where); die;
        return [$where, $sort, $order, $offset, $limit];
    }

}
