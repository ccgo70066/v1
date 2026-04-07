<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\exception\ApiException;
use app\common\library\ApiEnhance;
use ReflectionClass;
use ReflectionException;
use think\Env;
use think\exception\HttpResponseException;
use think\Request;
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
        Request::instance()->post(['__response' => $result]);
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

        if (Env::get('api.response_confuse_switch')) $result = $this->confuse($result);
        if (Env::get('api.response_encode_switch')) {
            $result = $this->encode($result);
            $type = '';
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }

    protected function resultWithoutEncode($msg, $data = null, $code = 0, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ];
        Request::instance()->post(['__response' => $result]);
        // 如果未设置类型则自动判断
        $type = $type ?: ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);

        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            $code = 200;
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
                if ('post' != strtolower($this->request->method()) && !Env::get('app.debug')) $this->error(__('post only'));
            }
            if ($matches['name'][$i] == 'ApiParams') {
                $list = $this->parseArgs($matches['args'][$i]);
                if (empty($list['name'])) continue;

                $lang != 'en' && $validateFiled[$list['name']] = $list['description'];

                $fieldName = $list['name'];
                $validateRules = [];

                if (isset($list['required']) && 'true' == $list['required']) $validateRules[] = 'require';
                if (isset($list['rule']) && $list['rule'] != '') $validateRules[] = $list['rule'];
                if ($validateRules) $validateOption[$fieldName] = implode('|', $validateRules);
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
        if (!Env::get('api.request_encode_switch')) return;
        if ($this->auth->match($this->noNeedSign)) return;

        $vToken = $this->request->header('v-token', '');
        $rs = json_decode(ApiEnhance::instance()->requestDecode($vToken), true);
        if (!$rs) $this->error(__('Request sign failed'));
        if (isset($rs['appid'])) $this->appid = $rs['appid'];
        if (isset($rs['system'])) $this->system = $rs['system'];
        if (isset($rs['version'])) $this->version = $rs['version'];
        if (isset($rs['time']) && Env::get('api.request_encode_switch') && !Env::get('app.debug')) {
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

        foreach ($result as $key => $value) {
            if (isset($diff[$key])) {
                // 需要混淆的键名
                $newKey = $diff[$key];
                $result[$newKey] = is_array($value) ? $this->confuse($value) : $value;
                unset($result[$key]);
            } elseif (is_array($value)) {
                // 不需要混淆键名，但值需要递归处理
                $result[$key] = $this->confuse($value);
            }
        }
        return $result;
    }

    protected function encode(array $result)
    {
        return ApiEnhance::instance()->responseEncode(json_encode($result));
    }

    protected function decodeRequest($string)
    {
        if (!$string) return [];
        $requestDecode = ApiEnhance::instance()->requestDecode($string);
        $rs = json_decode(base64_decode($requestDecode), true);
        return $rs ?? [];
    }


}
