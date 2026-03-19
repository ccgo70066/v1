<?php

namespace app\admin\controller\apilog;

use addons\apilog\model\Apilog;
use app\common\controller\Backend;

class Data extends Backend
{

    //基础数据
    public function index()
    {
        if (IS_AJAX) {
            $start = input('start', strtotime(date("Y-m-d", time())));
            $end = input('end', $start + 60 * 60 * 24);
            $baseinfo = Apilog::getBaseInfo($start, $end);
            $code = Apilog::getHttpCodePie($start, $end);
            $time = Apilog::getResponseTimePie($start, $end);
            $requesttop = Apilog::getMaxRequestTop($start, $end);
            $errortop = Apilog::getMaxErrorTop($start, $end);
            $fasttop = Apilog::getDoFastTop($start, $end);
            $slowtop = Apilog::getDoSlowTop($start, $end);
            $data['base'] = $baseinfo;
            $data['code'] = $code;
            $data['requesttop'] = $requesttop;
            $data['time'] = $time;
            $data['errortop'] = $errortop;
            $data['fasttop'] = $fasttop;
            $data['slowtop'] = $slowtop;
            return json($data);
        }
        return $this->view->fetch();
    }

    //趋势数据
    public function qushi()
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
