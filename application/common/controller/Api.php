<?php

namespace app\common\controller;

use app\common\exception\ApiException;
use app\common\library\Auth;
use ReflectionClass;
use ReflectionException;
use think\Config;
use think\Cookie;
use think\Env;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Hook;
use think\Lang;
use think\Loader;
use think\Request;
use think\Response;
use util\OpenSSL3DES;

/**
 * API控制器基类
 * @ApiInternal
 */
class Api
{

    /**
     * @var Request Request 实例
     */
    protected $request;

    /**
     * @var bool 验证失败是否抛出异常
     */
    protected $failException = false;

    /**
     * @var bool 是否批量验证
     */
    protected $batchValidate = false;

    /**
     * @var array 前置操作方法列表
     */
    protected $beforeActionList = [];

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = [];

    protected $noNeedSign = [];

    /**
     * 权限Auth
     * @var Auth
     */
    protected $auth = null;

    /**
     * 默认响应输出类型,支持json/xml
     * @var string
     */
    protected $responseType = 'json';

    protected $system, $version, $appid;

    /**
     * 构造方法
     * @access public
     * @param Request $request Request 对象
     */
    public function __construct(Request $request)
    {
        $this->request = is_null($request) ? Request::instance() : $request;

        // 控制器初始化
        $this->_initialize();

        // 前置操作方法
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ? $this->beforeAction($options) : $this->beforeAction($method, $options);
            }
        }
    }

    /**
     * 初始化操作
     * @access protected
     */
    protected function _initialize()
    {
        //跨域请求检测
        check_cors_request();

        // 检测IP是否允许
        check_ip_allowed();

        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');

        $this->auth = Auth::instance();

        $modulename = $this->request->module();
        $controllername = strtolower($this->request->controller());
        $actionname = strtolower($this->request->action());

        // 参数校验
        try {
            $rc = new ReflectionClass($this);
            $this->validateParams($rc->getMethod($actionname)->getDocComment());
        } catch (ReflectionException $exception) {
        }

        // token
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', Cookie::get('token')));
        $this->system = $this->request->server('HTTP_SYSTEM', $this->request->request('system'));
        $this->version = $this->request->server('HTTP_VERSION', $this->request->request('version'));
        $this->appid = $this->request->server('HTTP_APPID', $this->request->request('appid'));

        $path = str_replace('.', '/', $controllername) . '/' . $actionname;
        // 设置当前请求的URI
        $this->auth->setRequestUri($path);
        // 检测是否需要验证登录
        if (!$this->auth->match($this->noNeedLogin)) {
            //初始化
            $this->auth->init($token);
            //检测是否登录
            if (!$this->auth->isLogin()) {
                $this->error(__('Please login first'), null, 401);
            }
            // 判断是否需要验证权限
            if (!$this->auth->match($this->noNeedRight)) {
                // 判断控制器和方法判断是否有对应权限
                if (!$this->auth->check($path)) {
                    $this->error(__('You have no permission'), null, 403);
                }
            }
        } else {
            // 如果有传递token才验证是否登录状态
            if ($token) {
                $this->auth->init($token);
            }
        }

        $upload = \app\common\model\Config::upload();

        // 上传信息配置后
        Hook::listen("upload_config_init", $upload);

        Config::set('upload', array_merge(Config::get('upload'), $upload));

        // 加载当前控制器语言包
        $this->loadlang($controllername);
        //$this->sign_check();
    }

    /**
     * 加载语言文件
     * @param string $name
     */
    protected function loadlang($name)
    {
        $name = Loader::parseName($name);
        $name = preg_match("/^([a-zA-Z0-9_\.\/]+)\$/i", $name) ? $name : 'index';
        $lang = $this->request->langset();
        $lang = preg_match("/^([a-zA-Z\-_]{2,10})\$/i", $lang) ? $lang : 'zh-cn';
        Lang::load(APP_PATH . $this->request->module() . '/lang/' . $lang . '/' . str_replace('.', '/', $name) . '.php');
    }

    /**
     * 操作成功返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为1
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function success($msg = '', $data = null, $code = 1, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 操作失败返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function error($msg = '', $data = null, $code = 0, $type = null, array $header = [])
    {
        $msg == '' && $msg = __('Operation failed');
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @access protected
     * @param mixed  $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型，支持json/xml/jsonp
     * @param array  $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function result($msg, $data = null, $code = 0, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            //            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);

        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            //未设置状态码,根据code值判断
            //$code = $code >= 1000 || $code < 200 ? 200 : $code;
            $code = 200;
        }

        if (!Env::get('app.debug')) {
            $result = $this->confuse($result);
            $result = $this->encode($result);
            $type = '';
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * 前置操作
     * @access protected
     * @param string $method  前置操作方法名
     * @param array  $options 调用参数 ['only'=>[...]] 或者 ['except'=>[...]]
     * @return void
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = explode(',', $options['only']);
            }

            if (!in_array($this->request->action(), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = explode(',', $options['except']);
            }

            if (in_array($this->request->action(), $options['except'])) {
                return;
            }
        }

        call_user_func([$this, $method]);
    }

    /**
     * 设置验证失败后是否抛出异常
     * @access protected
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    protected function validateFailException($fail = true)
    {
        $this->failException = $fail;

        return $this;
    }

    /**
     * 验证数据
     * @access protected
     * @param array        $data     数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array        $message  提示信息
     * @param bool         $batch    是否批量验证
     * @param mixed        $callback 回调方法（闭包）
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate($data, $validate, $message = [], $batch = false, $callback = null)
    {
        if (is_array($validate)) {
            $v = Loader::validate();
            $v->rule($validate);
        } else {
            // 支持场景
            if (strpos($validate, '.')) {
                list($validate, $scene) = explode('.', $validate);
            }

            $v = Loader::validate($validate);

            !empty($scene) && $v->scene($scene);
        }

        // 批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }
        // 设置错误信息
        if (is_array($message)) {
            $v->message($message);
        }
        // 使用回调验证
        if ($callback && is_callable($callback)) {
            call_user_func_array($callback, [$v, &$data]);
        }

        if (!$v->check($data)) {
            if ($this->failException) {
                throw new ValidateException($v->getError());
            }

            return $v->getError();
        }

        return true;
    }


    /**
     * 参数校验
     * @param $docblock string  ApiParams example: (name="system", type="string", required=true, rule="in:1,2", description="平台:1=IOS,2=ANDROID")
     */
    protected function validateParams($docblock)
    {
        $lang = $this->request->langset();
        $validateOption = [];
        $validateFiled = [];
        $docblock = substr($docblock, 3, -2);
        preg_match_all('/@(?<name>[A-Za-z_-]+)[\s\t]*\((?<args>(?:(?!\)).)*)\)\r?/s', $docblock, $matches);
        $numMatches = count($matches[0]);
        for ($i = 0; $i < $numMatches; ++$i) {
            if ($matches['name'][$i] == 'ApiMethod') {
                if (
                    //'post' == strtolower($matches['args'][$i]) &&
                    'post' != strtolower($this->request->method()) &&
                    !Env::get('app.debug')) {
                    $this->error(__('post only'));
                }
            }
            if ($matches['name'][$i] == 'ApiParams') {
                $list = $this->parseArgs($matches['args'][$i]);
                $lang != 'en' && $validateFiled[$list['name']] = $list['description'];

                if (isset($list['required']) && 'true' == $list['required']) {
                    $validateOption[$list['name']] = 'require|';
                } else {
                    $validateOption[$list['name']] = '';
                }
                if (isset($list['rule']) && $list['rule'] != '') {
                    $validateOption[$list['name']] .= $list['rule'];
                }
                if (isset($validateOption[$list['name']])
                    && strpos($validateOption[$list['name']], '|') == (strlen($validateOption[$list['name']]) - 1)) {
                    $validateOption[$list['name']] = substr(
                        $validateOption[$list['name']],
                        0,
                        strlen($validateOption[$list['name']]) - 1
                    );
                }
                if ($validateOption[$list['name']] == '') {
                    unset($validateOption[$list['name']]);
                }
            }
        }
        if (!empty($validateOption)) {
            $validate = new \think\Validate($validateOption, [], $validateFiled);
            if (!$validate->check(input())) {
                $this->error($validate->getError());
            }
        }
    }


    protected function parseArgs($content)
    {
        $arr = [];
        $content = preg_split('/".*?"(*SKIP)(*FAIL)|,/', $content);
        foreach ($content as $item) {
            list($key, $value) = explode('=', $item);
            if ('description' == trim($key) && strpos($value, ':')) {
                list($value) = explode(':', $value);
            }
            $arr[trim($key)] = str_replace('\'', '', str_replace('"', '', trim($value)));
        }

        return $arr;
    }


    /**
     * 频率检测
     * 防止用户点击太快重复提交
     * @param string $operate
     * @param int    $second
     * @return void
     * @throws
     */
    protected function operate_check(string $operate, int $second = 5)
    {
        $redis = redis();
        if (!$redis->set('operate_check:' . $operate, 1, ['nx', 'ex' => $second])) {
            throw new ApiException(__('Operation too fast'));
        }
    }

    /**
     * 签名校验
     * api_sign_key: CTasUzg0bbTP1qRi
     * step1: 获取请求参数，按参数名称ASCII码排序
     * step2: 拼接字符串，key1=v1&key2=v2&key3=v3
     * step3: 追加time和api_sign_key, key1=v1&key2=v2&key3=v3&time=1744014428&x=CTasUzg0bbTP1qRi
     * step4: md5并大写
     * step5: 将time和sign放到请求头中
     * @return void
     * @throws ApiException
     */
    protected function sign_check()
    {
        if (Env::get('app.debug')) {
            return;
        }
        if ($this->auth->match($this->noNeedSign)) {
            return;
        }

        $time = $this->request->header('time');
        $sign = $this->request->header('sign');

        $i = 30;
        if ((time() - $i) > $time || (time() + $i) < $time) {
            throw new ApiException(__('Request timeout'));
        }

        $params = input();
        ksort($params);
        $params['time'] = $time;
        $params['x'] = Env::get('app.api_sign_key');

        $check = strtoupper(md5(urldecode(http_build_query($params))));
        if ($sign != $check) {
            throw new ApiException('404 not found: ' . $check);
        }
    }

    protected function confuse(array $result)
    {
        $diff = [
            'code'     => 'c',
            'msg'      => 'm',
            'data'     => 'd',
            'username' => 'ue',
            'image'    => 'ie',
            'test'     => 'tt',
            'list'     => 'l',
            'id'       => 'i',
            'name'     => 'n'
        ];
        foreach ($result as $key => &$value) {
            if (isset($diff[$key])) {
                $newKey = $diff[$key];
                $result[$newKey] = $value;
                unset($result[$key]);
                if (is_array($result[$newKey])) {
                    $temp = [$newKey => $result[$newKey]];
                    $temp = $this->confuse($temp);
                    $result[$newKey] = $temp[$newKey];
                }
            } elseif (is_array($value)) {
                $value = $this->confuse($value);
            }
        }
        return $result;
    }

    protected function encode(array $result)
    {
        $key = Env::get('app.api_encode_key');
        $vi = Env::get('app.api_encode_vi');
        $des = new OpenSSL3DES($key, $vi);
        return $des->encrypt(json_encode($result));
    }


}
