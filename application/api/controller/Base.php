<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\exception\ApiException;
use ReflectionClass;
use ReflectionException;
use think\Env;
use think\exception\HttpResponseException;
use think\Response;
use util\OpenSSL3DES;

/**
 * API控制器基类
 * @ApiInternal
 */
class Base extends Api
{
    protected $service;
    protected $noNeedSign = '';

    protected $appid = 'a1';
    protected $system = 1;
    protected $version = 1;


    protected function _initialize()
    {
        parent::_initialize();

        $this->sign_decode();
        $this->request->post($this->decodeRequest(input('raw')));

        try {
            $rc = new ReflectionClass($this);
            $this->validateParams($rc->getMethod(strtolower($this->request->action()))->getDocComment());
        } catch (ReflectionException $exception) {
        }
    }


    protected function result($msg, $data = null, $code = 0, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
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

        if (Env::get('api.api_confuse_switch')) $result = $this->confuse($result);
        if (Env::get('api.api_encode_switch')) {
            $result = $this->encode($result);
            $type = '';
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }


    protected function validateParams($docblock): void
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
            if (!$validate->check(input())) $this->error($validate->getError());
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

    protected function operate_check(string $operate, int $second = 5)
    {
        if (Env::get('app.debug')) return;
        $redis = redis();
        if (!$redis->set('operate_check:' . $operate, 1, ['nx', 'ex' => $second]))
            throw new ApiException(__('Operation too fast'));
    }

    protected function sign_decode(): void
    {
        //if (Env::get('app.debug')) return;
        if (!Env::get('api.api_request_sign_switch')) return;
        if ($this->auth->match($this->noNeedSign)) return;

        $des = new OpenSSL3DES('7iLs8KF08pVL222PHegRxLny', 'xK4M5ph1');
        $vToken = $this->request->header('v-token', '');
        $rs = json_decode(base64_decode($des->decrypt($vToken)), true);
        if (!$rs) $this->error(__('Request sign failed'));
        if (isset($rs['appid'])) $this->appid = $rs['appid'];
        if (isset($rs['system'])) $this->system = $rs['system'];
        if (isset($rs['version'])) $this->version = $rs['version'];
        if (isset($rs['time']) && !Env::get('api.api_request_sign_switch')) {
            $time = $rs['time'];
            $i = 15;
            if ((time() - $i) > $time || (time() + $i) < $time) $this->error(__('Request timeout'));
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
        $key = Env::get('api.api_encode_key');
        $vi = Env::get('api.api_encode_vi');
        $des = new OpenSSL3DES($key, $vi);
        return $des->encrypt(json_encode($result));
    }

    protected function decodeRequest($string)
    {
        if (!$string) return [];
        $des = new OpenSSL3DES('7iLs8KF08pVL222PHegRxLny', 'xK4M5ph1');
        $rs = json_decode(base64_decode($des->decrypt($string)), true);
        return $rs ?? [];
    }


}
