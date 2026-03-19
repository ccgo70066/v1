<?php

namespace app\admin\controller\apilog;

use addons\apilog\model\Apilog;
use app\common\controller\Backend;

class Trend extends Backend
{

    //趋势数据
    public function index()
    {
        if (IS_AJAX) {
            $count_m = Apilog::getRequestCountLine(0);
            $count_h = Apilog::getRequestCountLine(1);
            $count_d = Apilog::getRequestCountLine(2);
            $time_m = Apilog::getDoTimeLine(0);
            $time_h = Apilog::getDoTimeLine(1);
            return json([
                'count_m' => $count_m,
                'count_h' => $count_h,
                'count_d' => $count_d,
                'time_m' => $time_m,
                'time_h' => $time_h
            ]);
        }
        return $this->view->fetch();
    }
}
