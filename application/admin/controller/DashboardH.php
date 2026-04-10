<?php

namespace app\admin\controller;

use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Cache;
use think\Db;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class DashboardH extends Backend
{

    protected $noNeedRight = ['*'];
    /**
     * 查看
     */
    public function index()
    {
        return $this->view->fetch();
    }

}
