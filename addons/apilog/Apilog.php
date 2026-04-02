<?php

namespace addons\apilog;

use app\common\library\ApiEnhance;
use think\Cache;
use think\Addons;
use think\Request;
use think\Response;
use think\addons\Service;
use app\common\library\Menu;
use app\common\library\Auth;
use app\common\library\Email;
use think\exception\HttpResponseException;
use addons\apilog\model\Apilog as ModelApilog;

/**
 * 插件
 */
class Apilog extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        $menu = [
            [
                'name'    => 'apilog',
                'title'   => 'API分析',
                'icon'    => 'fa fa-pie-chart',
                'ismenu'  => 1,
                'sublist' => [
                    [
                        "name"    => "apilog/data",
                        "title"   => "基础数据",
                        "ismenu"  => 1,
                        "icon"    => "fa fa-dashboard",
                        'sublist' => [
                            ['name' => 'apilog/data/index', 'title' => '查看'],
                        ]
                    ],
                    [
                        "name"    => "apilog/trend",
                        "title"   => "趋势数据",
                        "ismenu"  => 1,
                        "icon"    => "fa fa-area-chart",
                        'sublist' => [
                            ['name' => 'apilog/trend/index', 'title' => '查看'],
                        ]
                    ],
                    [
                        "name"    => "apilog/index",
                        "title"   => "请求列表",
                        "ismenu"  => 1,
                        "icon"    => "fa fa-list",
                        'sublist' => [
                            ['name' => 'apilog/index/index', 'title' => '查看'],
                            ['name' => 'apilog/index/del', 'title' => '删除'],
                            ['name' => 'apilog/index/detail', 'title' => '详情'],
                            ['name' => 'apilog/index/banip', 'title' => '禁用IP'],
                        ]
                    ],
                ]
            ]
        ];
        Menu::create($menu);
        Service::refresh();
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        Menu::delete("apilog");
        Service::refresh();
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        Menu::enable('apilog');
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {
        Menu::disable("apilog");
        return true;
    }

    private function is_filter()
    {
        $config = get_addon_config('apilog');
        if (in_array(Request::instance()->controller(), explode(",", $config['base']['filter_controller']))) {
            return true;
        }
        if (in_array(Request::instance()->action(), explode(",", $config['base']['filter_controller']))) {
            return true;
        }
        if (in_array(Request::instance()->baseUrl(), explode("\n", $config['base']['filter_url']))) {
            return true;
        }
        return false;
    }

    public function responseSend(&$params)
    {
        if (Request::instance()->module() == "api") {
            if ($this->is_filter()) {
                return;
            }
            $log['time'] = (microtime(true) - Request::instance()->time(true)) * 1000;
            $auth = Auth::instance();
            $user_id = $auth->isLogin() ? $auth->id : 0;
            $username = $auth->isLogin() ? $auth->username : __('Unknown');
            $log['url'] = Request::instance()->baseUrl();
            $log['method'] = Request::instance()->method();
            $param = Request::instance()->param();
            $response = $param['__response'] ?? [];
            unset($param['raw'], $param['__response']);
            $log['param'] = json_encode($param);
            $log['ip'] = Request::instance()->ip();
            $log['ua'] = Request::instance()->header('user-agent');
            $log['controller'] = Request::instance()->controller();
            $log['action'] = Request::instance()->action();
            $log['code'] = (string)$params->getCode();
            $log['user_id'] = (string)$user_id;
            $log['username'] = $username;
            $log['response'] = json_encode($response);
            (new ModelApilog)->save($log);
            $config = get_addon_config('apilog');


            //状态码记录
            if ($config['error']['open'] == 1) {
                $count_code = Cache::get('countcode', null);
                if (is_null($count_code)) {
                    Cache::set('countcode', 0, $config['error']['pl']);
                    $tagkey = Cache::get('tag_' . md5('code'));
                    $keys = $tagkey ? array_filter(explode(',', $tagkey)) : [];
                    foreach ($keys as $k => $v) {
                        Cache::rm($v);
                    }
                    Cache::rm($tagkey);
                }
                $count_code = Cache::inc('countcode');
                $k_code = 'code:' . $params->getCode();
                $yj_code = Cache::get($k_code, null);
                if (is_null($yj_code)) {
                    Cache::set($k_code, 0, 0);
                    Cache::tag('code', $k_code);
                }
                Cache::inc($k_code);
                $codes = array_filter(explode(',', $config['error']['sj']));
                $now = 0;
                foreach ($codes as $k => $v) {
                    $now += Cache::get('code:' . $v, 0);
                }
                if ($now / $count_code >= $config['error']['zb'] / 100) {
                    // echo '触发错误预警' . $now / $count_code;
                    $this->emailnotify(
                        $config['base']['email'],
                        '请求错误监控',
                        '当前api请求错误率已达到【' . round($now / $count_code * 100, 2) . '%】,请及时关注！'
                    );
                }
            }
            //超时记录数
            if ($config['time']['open'] == 1) {
                $count_time = Cache::get('counttime', null);
                if (is_null($count_time)) {
                    Cache::set('counttime', 0, $config['time']['pl']);
                    Cache::rm('time');
                }
                $tot_time = Cache::inc('counttime');
                if ($log['time'] > $config['time']['sj']) {
                    $yj_time = Cache::get('time', null);
                    if (is_null($yj_time)) {
                        Cache::set('time', 0, 0);
                    }
                    $now_time = Cache::inc('time');
                    if ($now_time / $tot_time >= $config['time']['zb'] / 100) {
                        // echo '触发超时预警' . $now_time / $tot_time;
                        $this->emailnotify(
                            $config['base']['email'],
                            '响应超时监控',
                            '当前api响应超时请求占比已达到【' . round($now_time / $tot_time * 100, 2) . '%】,请及时关注！'
                        );
                    }
                }
            }
        }
    }

    public function moduleInit(&$params)
    {
        if (Request::instance()->module() == "api") {
            $ip = 'banip:' . Request::instance()->ip();
            $cacheIp = Cache::get($ip);
            if ($cacheIp !== false) {
                $this->respone(500, '抱歉，您的IP已被禁止访问');
            }
            $config = get_addon_config('apilog');
            //总请求数
            if ($config['count']['open'] == 1) {
                $yj_count = Cache::get('count', null);
                if (is_null($yj_count)) {
                    Cache::set('count', 0, $config['count']['pl']);
                }
                Cache::inc('count');
                //预警
                if ($yj_count + 1 >= $config['count']['max']) {
                    Cache::rm('count');
                    // $this->respone(500, '触发请求量预警');
                    $this->emailnotify($config['base']['email'], '请求量监控', '当前最大请求数量已达到【' . ++$yj_count . '次】,请及时关注！');
                }
            }
            //IP访问请求数
            if ($config['ip']['open'] == 1) {
                $count_ip = Cache::get('countip', null);
                if (is_null($count_ip)) {
                    Cache::set('countip', 0, $config['ip']['pl']);
                    $tagkey = Cache::get('tag_' . md5('ip'));
                    $keys = $tagkey ? array_filter(explode(',', $tagkey)) : [];
                    foreach ($keys as $k => $v) {
                        Cache::rm($v);
                    }
                    Cache::rm($tagkey);
                }
                $count_ip = Cache::inc('countip');
                $k_ip = 'ip:' . Request::instance()->ip();
                $yj_ip = Cache::get($k_ip, null);
                if (is_null($yj_ip)) {
                    Cache::set($k_ip, 0, 0);
                    Cache::tag('ip', $k_ip);
                }
                $this_ip = Cache::inc($k_ip);
                //白名单
                $white = array_filter(explode(',', $config['ip']['white']));
                //预警
                if (!in_array(Request::instance()->ip(), $white) && $this_ip / $count_ip >= $config['ip']['zb'] / 100) {
                    //$this->respone(500, '触发IP预警');
                    $this->emailnotify(
                        $config['base']['email'],
                        'IP异常监控',
                        'IP【' . Request::instance()->ip()
                        . '】的访问请求占比已达到【' . round($this_ip / $count_ip * 100, 2) . '%】,请及时关注！'
                    );
                }
            }
        }
    }

    protected function respone($code, $msg)
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => null,
        ];
        $type = Request::instance()->param(config('var_jsonp_handler')) ? 'jsonp' : 'json';
        $response = Response::create($result, $type, 500);
        throw new HttpResponseException($response);
    }

    /**
     * 发送邮件预警
     * 同类型预警半小时最多发一次
     */
    protected function emailnotify($receiver, $subject, $content)
    {
        $cache = Cache::get('notify:' . $subject, null);
        if (is_null($cache)) {
            $email = new Email;
            $result = $email
                ->to($receiver)
                ->subject('【API预警】' . $subject)
                ->message('<div style="min-height:550px; padding: 100px 55px 200px;">' . $content . '</div>')
                ->send();
            if ($result) {
                Cache::set('notify:' . $subject, 1, 1800);
            }
        }
    }
}
