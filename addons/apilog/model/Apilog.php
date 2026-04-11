<?php

namespace addons\apilog\model;

use think\Model;


class Apilog extends Model
{

     protected $connection = 'mongodb';
    protected $table = 'fa_apilog';
    protected $name = 'apilog';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $append = [
        'method_text',
        'time_text'
    ];

    public function getMethodList()
    {
        return ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'DELETE' => 'DELETE'];
    }


    public function getMethodTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['method']) ? $data['method'] : '');
        $list = $this->getMethodList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['time']) ? $data['time'] : '');
        return $value;
        //return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    /**
     * 基本数据
     *
     * @param [type] $start
     * @param [type] $end
     * @return void
     */
    public static function getBaseInfo($start, $end)
    {
        //请求次数
        $count_request = Apilog::whereTime('createtime', 'between', [$start, $end])->count();
        //平均处理时间
        $avg_time = Apilog::whereTime('createtime', 'between', [$start, $end])->avg('time');
        //404
        $count_404 = Apilog::whereTime('createtime', 'between', [$start, $end])->where('code', 404)->count();
        //500
        $count_500 = Apilog::whereTime('createtime', 'between', [$start, $end])->where('code', 500)->count();
        //错误率占比
        $error_rank = $count_request > 0 ? $count_500 / $count_request : 0;
        //接口总数(已请求)
        $count_api = Apilog::whereTime('createtime', 'between', [$start, $end])->group('controller,action')->count();
        //echo Apilog::getLastSql();
        return [
            'count_request' => $count_request,
            'avg_time'      => $avg_time,
            'count_404'     => $count_404,
            'count_500'     => $count_500,
            'error_rank'    => $error_rank,
            'count_api'     => $count_api
        ];
    }

    /**
     * 请求状态码 饼图
     *
     * @return void
     */
    public static function getHttpCodePie($start, $end)
    {
        $list = Apilog::whereTime('createtime', 'between', [$start, $end])->group('code')->field('count(1) num,code')->select();
        $data['x'] = [];
        $data['y'] = [];
        foreach ($list as $k => $v) {
            $data['x'][] = $v['code'];
            $data['y'][] = $v['num'];
            $data['kv'][] = ['name' => $v['code'], 'value' => $v['num']];
        }
        return $data;
    }

    /**
     * 请求处理时间（ms）饼图
     * 按0-100 100-500，500-1000，1000-3000，3000-5000，5000以上划分
     *
     * @return void
     */
    public static function getResponseTimePie($start, $end)
    {
        $row = Apilog::whereTime('createtime', 'between', [$start, $end])
            ->field(
                "sum(CASE WHEN TIME<100 THEN 1 ELSE 0 END) AS '0-100' ,
            sum(CASE WHEN TIME>=100 and TIME<500 THEN 1 ELSE 0 END) AS '100-500' ,
            sum(CASE WHEN TIME>=500 and TIME<1000 THEN 1 ELSE 0 END) AS '500-1000' ,
            sum(CASE WHEN TIME>=1000 and TIME<3000 THEN 1 ELSE 0 END) AS '1000-3000' ,
            sum(CASE WHEN TIME>=3000 and TIME<5000 THEN 1 ELSE 0 END) AS '3000-5000' ,
            sum(CASE WHEN TIME>=5000  THEN 1 ELSE 0 END) AS '5000以上' 
            "
            )
            ->find();
        // echo Apilog::getLastSql();
        $data['x'] = ['0-100', '100-500', '500-1000', '1000-3000', '3000-5000', '5000以上'];
        $data['y'] = [$row['0-100'], $row['100-500'], $row['500-1000'], $row['1000-3000'], $row['3000-5000'], $row['5000以上']];
        foreach ($data['x'] as $k => $v) {
            $data['kv'][] = ['name' => $v, 'value' => $data['y'][$k]];
        }
        return $data;
    }

    /**
     * 最多请求 Top n，展现接口名称
     *
     * @return void
     */
    public static function getMaxRequestTop($start, $end)
    {
        $list = Apilog::whereTime('createtime', 'between', [$start, $end])
            ->group('url')->field('count(1) num, url')->order('num desc')->limit(0, 15)->select();
        // echo Apilog::getLastSql();
        $data['x'] = [];
        $data['y'] = [];
        foreach ($list as $k => $v) {
            $data['x'][] = $v['url'];
            $data['y'][] = $v['num'];
        }
        return $data;
    }

    /**
     * 请求错误 Top n
     *
     * @return void
     */
    public static function getMaxErrorTop($start, $end)
    {
        $list = Apilog::whereTime('createtime', 'between', [$start, $end])
            ->where('code', 500)
            ->group('url')->field('count(1) num, url')->order('num desc')->limit(0, 15)->select();
        // echo Apilog::getLastSql();
        $data['x'] = [];
        $data['y'] = [];
        foreach ($list as $k => $v) {
            $data['x'][] = $v['url'];
            $data['y'][] = $v['num'];
        }
        return $data;
    }

    /**
     * 平均处理时间最快  Top n
     *
     * @return void
     */
    public static function getDoFastTop($start, $end)
    {
        $list = Apilog::whereTime('createtime', 'between', [$start, $end])
            ->group('url')->field('avg(time) num, url')->order('num')->limit(0, 15)->select();
        // echo Apilog::getLastSql();
        $data['x'] = [];
        $data['y'] = [];
        foreach ($list as $k => $v) {
            $data['x'][] = $v['url'];
            $data['y'][] = $v['num'];
        }
        return $data;
    }

    /**
     * 平均处理时间最慢 Top n
     *
     * @return void
     */
    public static function getDoSlowTop($start, $end)
    {
        $list = Apilog::whereTime('createtime', 'between', [$start, $end])
            ->group('url')->field('avg(time) num, url')->order('num desc')->limit(0, 15)->select();
        // echo Apilog::getLastSql();
        $data['x'] = [];
        $data['y'] = [];
        foreach ($list as $k => $v) {
            $data['x'][] = $v['url'];
            $data['y'][] = $v['num'];
        }
        return $data;
    }


    /**
     * 请求次数 近一个小时，按分钟
     *
     * @param int $type 0:每分钟 1:每小时 2:每天
     * @return void
     */
    public static function getRequestCountLine($type)
    {
        $now = time();
        $where = $type == 0 ? [$now - 3600, $now] : ($type == 1 ? [$now - 3600 * 24, $now] : 'month');
        $format = $type == 0 ? 'i' : ($type == 1 ? 'H' : 'd');
        $group = "FROM_UNIXTIME(createtime,'%" . $format . "')";
        $list = Apilog::whereTime('createtime', $where)->group($group)->field('count(1) num,' . $group . ' as time')->select();
        $data['x'] = [];
        $data['y'] = [];
        foreach ($list as $k => $v) {
            $data['x'][] = $v['time'];
            $data['y'][] = $v['num'];
        }
        if ($type == 2) {
            return $data;
        }
        $max = $type == 0 ? 60 : ($type == 1 ? 24 : 0);
        $s = $type == 0 ? getdate()['minutes'] + 1 : ($type == 1 ? getdate()['hours'] + 1 : 0);
        $tmp = null;
        for ($i = 0; $i < $max; $i++) {
            $k = $s + $i >= $max ? $s + $i - $max : $s + $i;
            $tmp['x'][] = $k;
            if (($idx = array_search($k, $data['x'])) !== false) {
                $tmp['y'][] = $data['y'][$idx];
            } else {
                $tmp['y'][] = 0;
            }
        }
        return $tmp;
    }

    /**
     * 平均处理时间 近一个小时，按分钟
     *
     * @param int $type 0:每分钟 1:每小时 2:每天
     * @return void
     */
    public static function getDoTimeLine($type)
    {
        $now = time();
        $where = $type == 0 ? [$now - 3600, $now] : ($type == 1 ? [$now - 3600 * 24, $now] : 'month');
        $format = $type == 0 ? 'i' : ($type == 1 ? 'H' : 'd');
        $group = "FROM_UNIXTIME(createtime,'%" . $format . "')";
        $list = Apilog::whereTime('createtime', $where)->group($group)->field('avg(time) num,' . $group . ' as time')->select();
        $data['x'] = [];
        $data['y'] = [];
        foreach ($list as $k => $v) {
            $data['x'][] = $v['time'];
            $data['y'][] = $v['num'];
        }
        if ($type == 2) {
            return $data;
        }
        $max = $type == 0 ? 60 : ($type == 1 ? 24 : 0);
        $s = $type == 0 ? getdate()['minutes'] + 1 : ($type == 1 ? getdate()['hours'] + 1 : 0);
        $tmp = null;
        for ($i = 0; $i < $max; $i++) {
            $k = $s + $i >= $max ? $s + $i - $max : $s + $i;
            $tmp['x'][] = $k;
            if (($idx = array_search($k, $data['x'])) !== false) {
                $tmp['y'][] = $data['y'][$idx];
            } else {
                $tmp['y'][] = 0;
            }
        }
        return $tmp;
    }
}
