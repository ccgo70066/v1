<?php

namespace addons\betterform;

use app\common\library\Menu;
use think\Addons;
use think\Loader;

/**
 * 插件
 */
class Betterform extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {

        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {

        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {

        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {

        return true;
    }

    public function viewFilter(&$content)
    {
        $request = \think\Request::instance();
        $dispatch = $request->dispatch();
        if (!$dispatch) {
            return;
        }

        if (!$request->module() || $request->module() !== 'admin') {
            return;
        }

        $config = get_addon_config('betterform');

        //在head前引入CSS
        $content = preg_replace("/<\/head>/i", "<link href='/assets/addons/betterform/css/common.css' rel='stylesheet' />" . "\n\$0", $content);

        //如果不存在表单
        if (!preg_match('/<form (.*?)data-toggle="validator"/i', $content)) {
            return;
        }
        // 避免栈空间不足
        ini_set('pcre.jit', false);

        // 匹配<div class="form-group">标签
        $regex = '/<div[^>]*class\s*=\s*"[^"]*\bform-group\b[^"]*"[^>]*>(?:(?!<div[^>]*class\s*=\s*"[^"]*\bform-group\b[^"]*").)*?data-rule="[^"]*?(required|checked)[^"]*?"[^>]*>/si';
        $result = preg_replace_callback($regex, function ($matches) use ($config) {
            return str_replace("form-group", "form-group required-{$config['asteriskposition']}", $matches[0]);
        }, $content);

        $content = is_null($result) ? $content : $result;

        // 匹配<tr>
        $pattern = '/(<tr[^>]*>)\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>.*?<input[^>]*data-rule="[^"]*required[^"]*"[^>]*>.*?<\/td>\s*<\/tr>/si';
        $result = preg_replace_callback($pattern, function ($matches) use ($config) {
            if (preg_match('/(<tr[^>]*)class\s*=\s*"[^"]*"/i', $matches[1])) {
                return preg_replace('/(<tr[^>]*)class\s*=\s*"([^"]*)"/i', '$1class="$2 required-' . $config['asteriskposition'] . '"', $matches[0]);
            } else {
                return str_replace("<tr", "<tr class=\"required-{$config['asteriskposition']}\"", $matches[0]);
            }
        }, $content);

        $content = is_null($result) ? $content : $result;
    }

    /**
     * @param $params
     */
    public function configInit(&$params)
    {
        $config = $this->getConfig();

        $config['area'] = preg_match("/\[(.*?)\]/i", $config['area']) ? array_slice(array_values((array)json_decode($config['area'], true)), 0, 2) : $config['area'];
        $config['shade'] = floatval($config['shade']);
        $config['shadeClose'] = boolval($config['shadeClose']);
        $params['betterform'] = $config;
    }

}
