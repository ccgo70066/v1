<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\exception\UploadException;
use app\common\library\Upload;
use fast\Random;
use think\addons\Service;
use think\Cache;
use think\Config;
use think\Db;
use think\Lang;
use think\Response;
use think\Validate;
use util\Minio;

/**
 * Ajax异步请求接口
 * @internal
 */
class Ajax extends Backend
{

    protected $noNeedLogin = ['lang'];
    protected $noNeedRight = ['*'];
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();

        //设置过滤方法
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);
    }

    /**
     * 加载语言包
     */
    public function lang()
    {
        $this->request->get(['callback' => 'define']);
        $header = ['Content-Type' => 'application/javascript'];
        if (!config('app_debug')) {
            $offset = 30 * 60 * 60 * 24; // 缓存一个月
            $header['Cache-Control'] = 'public';
            $header['Pragma'] = 'cache';
            $header['Expires'] = gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        }

        $controllername = input("controllername");
        //默认只加载了控制器对应的语言名，你还根据控制器名来加载额外的语言包
        $this->loadlang($controllername);
        return jsonp(Lang::get(), 200, $header, ['json_encode_param' => JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 上传文件到minio[后台用]
     * @ApiMethod (POST)
     * @ApiParams   (name="file", type="file", required=false, rule="", description="文件")
     */
    public function upload_to_minio()
    {
        $file = $this->request->file('file');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }

        $upload = Config::get('upload');
        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $fileInfo = $file->getInfo();


        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));

        $suffix = $suffix && preg_match("/^[a-zA-Z0-9]+$/", $suffix) ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //禁止上传PHP和HTML文件
        if (in_array($fileInfo['type'], ['text/x-php', 'text/html']) || in_array($suffix, ['php', 'html', 'htm'])) {
            $this->error(__('Uploaded file format is limited'));
        }
        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array(
                            $fileInfo['type'],
                            $mimetypeArr
                        ) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error("上传文件格式不在{$upload['mimetype']}范围内");
            $this->error(__('Uploaded file format is limited'));
        }
        //验证是否为图片文件
        if (in_array(
                $fileInfo['type'],
                ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']
            ) || in_array(
                $suffix,
                ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp']
            )) {
            $imgInfo = getimagesize($fileInfo['tmp_name']);
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                $this->error(__('Uploaded file is not a valid image'));
            }
        }

        $fileName = date('YmdH') . rand(1, 99999) . '.' . $suffix;
        // =====================start==============================
        if (input('category')) {
            switch (input('category')) {
                case 'app':
                    $fileName = $fileInfo['name'];
                    $uploadDir = $suffix == 'apk' ? "app/android/" : "app/ios/";
                    break;
                case 'plist':
                    $fileName = $fileInfo['name'];
                    $uploadDir = "apk/ios/";
                    break;
                case 'gift':
                    $fileName = md5_file($fileInfo['tmp_name']) . '.' . $suffix;
                    $uploadDir = input('category') . '/';
                    break;
                default:
                    $uploadDir = input('category') . '/';
            }
        } else {
            $uploadDir = 'other/';
        }
        switch ($suffix) {
            case 'apk':
                $contentType = 'application/vnd.android.package-archive';
                break;
            case 'ipa':
                $contentType = 'application/iphone';
                break;
            default:
                $contentType = '';
        }
        $minio = new Minio();
        $url = '/' . trim(trim($uploadDir), '/') . '/' . $fileName;
        $res_url = $minio->putObject($fileInfo['tmp_name'], $url, $contentType);
        echo json_encode([
            'code' => 1,
            'msg'  => __('Operation completed'),
            'data' => ['url' => $url],
        ]);
        //$this->success('', ['url' => $uploadDir . $fileName]);
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        Config::set('default_return_type', 'json');
        //必须设定cdnurl为空,否则cdnurl函数计算错误
        Config::set('upload.cdnurl', '');
        $chunkid = $this->request->post("chunkid");
        if ($chunkid) {
            if (!Config::get('upload.chunking')) {
                $this->error(__('Chunk file disabled'));
            }
            $action = $this->request->post("action");
            $chunkindex = $this->request->post("chunkindex/d");
            $chunkcount = $this->request->post("chunkcount/d");
            $filename = $this->request->post("filename");
            $method = $this->request->method(true);
            if ($action == 'merge') {
                $attachment = null;
                //合并分片文件
                try {
                    $upload = new Upload();
                    $attachment = $upload->merge($chunkid, $chunkcount, $filename);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success(__('Uploaded successful'), '', ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
            } elseif ($method == 'clean') {
                //删除冗余的分片文件
                try {
                    $upload = new Upload();
                    $upload->clean($chunkid);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            } else {
                //上传分片文件
                //默认普通上传文件
                $file = $this->request->file('file');
                try {
                    $upload = new Upload($file);
                    $upload->chunk($chunkid, $chunkindex, $chunkcount);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
        } else {
            $attachment = null;
            //默认普通上传文件
            $file = $this->request->file('file');
            try {
                $upload = new Upload($file);
                $attachment = $upload->upload();
            } catch (UploadException $e) {
                $this->error($e->getMessage());
            }

            $this->success(__('Uploaded successful'), '', ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
        }
    }

    /**
     * 通用排序
     */
    public function weigh()
    {
        //排序的数组
        $ids = $this->request->post("ids");
        //拖动的记录ID
        $changeid = $this->request->post("changeid");
        //操作字段
        $field = $this->request->post("field");
        //操作的数据表
        $table = $this->request->post("table");
        if (!Validate::is($table, "alphaDash")) {
            $this->error();
        }
        //主键
        $pk = $this->request->post("pk");
        //排序的方式
        $orderway = strtolower($this->request->post("orderway", ""));
        $orderway = $orderway == 'asc' ? 'ASC' : 'DESC';
        $sour = $weighdata = [];
        $ids = explode(',', $ids);
        $prikey = $pk && preg_match("/^[a-z0-9\-_]+$/i", $pk) ? $pk : (Db::name($table)->getPk() ?: 'id');
        $pid = $this->request->post("pid", "");
        //限制更新的字段
        $field = in_array($field, ['weigh']) ? $field : 'weigh';

        // 如果设定了pid的值,此时只匹配满足条件的ID,其它忽略
        if ($pid !== '') {
            $hasids = [];
            $list = Db::name($table)->where($prikey, 'in', $ids)->where('pid', 'in', $pid)->field("{$prikey},pid")->select();
            foreach ($list as $k => $v) {
                $hasids[] = $v[$prikey];
            }
            $ids = array_values(array_intersect($ids, $hasids));
        }

        $list = Db::name($table)->field("$prikey,$field")->where($prikey, 'in', $ids)->order($field, $orderway)->select();
        foreach ($list as $k => $v) {
            $sour[] = $v[$prikey];
            $weighdata[$v[$prikey]] = $v[$field];
        }
        $position = array_search($changeid, $ids);
        $desc_id = isset($sour[$position]) ? $sour[$position] : end($sour);    //移动到目标的ID值,取出所处改变前位置的值
        $sour_id = $changeid;
        $weighids = array();
        $temp = array_values(array_diff_assoc($ids, $sour));
        foreach ($temp as $m => $n) {
            if ($n == $sour_id) {
                $offset = $desc_id;
            } else {
                if ($sour_id == $temp[0]) {
                    $offset = isset($temp[$m + 1]) ? $temp[$m + 1] : $sour_id;
                } else {
                    $offset = isset($temp[$m - 1]) ? $temp[$m - 1] : $sour_id;
                }
            }
            if (!isset($weighdata[$offset])) {
                continue;
            }
            $weighids[$n] = $weighdata[$offset];
            Db::name($table)->where($prikey, $n)->update([$field => $weighdata[$offset]]);
        }
        $this->success();
    }

    public function get_yes_or_no()
    {
        $list = [
            ['id' => 1, 'name' => '是'],
            ['id' => 0, 'name' => '否']
        ];
        if (input('?keyValue')) {
            foreach ($list as $item) {
                if ($item['id'] == input('keyValue')) {
                    return json(['list' => [$item]]);
                }
            }
        }
        return json(['list' => $list]);
    }

    /**
     * 清空系统缓存
     */
    public function wipecache()
    {
        try {
            $type = $this->request->request("type");
            switch ($type) {
                case 'all':
                    // no break
                case 'content':
                    //内容缓存
                    rmdirs(CACHE_PATH, false);
                    Cache::clear();
                    if ($type == 'content') {
                        break;
                    }
                case 'template':
                    // 模板缓存
                    rmdirs(TEMP_PATH, false);
                    if ($type == 'template') {
                        break;
                    }
                case 'addons':
                    // 插件缓存
                    Service::refresh();
                    if ($type == 'addons') {
                        break;
                    }
                case 'browser':
                    // 浏览器缓存
                    // 只有生产环境下才修改
                    if (!config('app_debug')) {
                        $version = config('site.version');
                        $newversion = preg_replace_callback("/(.*)\.([0-9]+)\$/", function ($match) {
                            return $match[1] . '.' . ($match[2] + 1);
                        }, $version);
                        if ($newversion && $newversion != $version) {
                            Db::startTrans();
                            try {
                                \app\common\model\Config::where('name', 'version')->update(['value' => $newversion]);
                                \app\common\model\Config::refreshFile();
                                Db::commit();
                            } catch (\Exception $e) {
                                Db::rollback();
                                exception($e->getMessage());
                            }
                        }
                    }
                    if ($type == 'browser') {
                        break;
                    }
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        \think\Hook::listen("wipecache_after");
        $this->success();
    }

    /**
     * 读取分类数据,联动列表
     */
    public function category()
    {
        $type = $this->request->get('type', '');
        $pid = $this->request->get('pid', '');
        $where = ['status' => 'normal'];
        $categorylist = null;
        if ($pid || $pid === '0') {
            $where['pid'] = $pid;
        }
        if ($type) {
            $where['type'] = $type;
        }

        $categorylist = Db::name('category')->where($where)->field('id as value,name')->order('weigh desc,id desc')->select();

        $this->success('', '', $categorylist);
    }

    /**
     * 读取省市区数据,联动列表
     */
    public function area()
    {
        $params = $this->request->get("row/a");
        if (!empty($params)) {
            $province = isset($params['province']) ? $params['province'] : null;
            $city = isset($params['city']) ? $params['city'] : null;
        } else {
            $province = $this->request->get('province');
            $city = $this->request->get('city');
        }
        $where = ['pid' => 0, 'level' => 1];
        $provincelist = null;
        if ($province !== null) {
            $where['pid'] = $province;
            $where['level'] = 2;
            if ($city !== null) {
                $where['pid'] = $city;
                $where['level'] = 3;
            }
        }
        $provincelist = Db::name('area')->where($where)->field('id as value,name')->select();
        $this->success('', '', $provincelist);
    }

    /**
     * 生成后缀图标
     */
    public function icon()
    {
        $suffix = $this->request->request("suffix");
        $suffix = $suffix ? $suffix : "FILE";
        $data = build_suffix_image($suffix);
        $header = ['Content-Type' => 'image/svg+xml'];
        $offset = 30 * 60 * 60 * 24; // 缓存一个月
        $header['Cache-Control'] = 'public';
        $header['Pragma'] = 'cache';
        $header['Expires'] = gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        $response = Response::create($data, '', 200, $header);
        return $response;
    }


    public function channel()
    {
        $where = [];
        if (input('?q_word')) {
            $map = [['neq', ''], ['neq', '1']];
            foreach (input('q_word/a') as $item) {
                $item && $map[] = ['like', '%' . $item . '%'];
            }
            $where[input('showField')] = $map;
        }
        $channel = db('channel')->where($where)->field('appid,name')->select();

        return json(['list' => $channel, 'total' => count($channel)]);
    }


    public function card_group()
    {
        //主键值
        $primaryvalue = $this->request->request("keyValue");
        $data = [];
        $list = explode(',', get_site_config('card_group'));
        foreach ($list as $item) {
            if ($primaryvalue == $item) {
                $data['cate'] = $item;
                break;
            } else {
                $data[]['cate'] = $item;
            }
        }
        return json(['list' => $data, 'total' => count($data)]);
    }


    public function get_gift()
    {
        $where = input('custom/a');
        $where_name = input('name') ? ['g.name' => ['like', '%' . input('name') . '%']] : [];
        $where_key = input('keyValue') ? ['g.id' => ['in', input('keyValue')]] : [];
        $list = db('egg_gift eg')
            ->join('gift g', 'eg.gift_id=g.id', 'left')
            ->where($where)
            ->where($where_name)
            ->where($where_key)
            ->field('g.id,concat(g.name,"[",g.price,"]") as name')
            ->select();
        return json(['list' => $list, 'total' => count($list)]);
    }


    public function search_list($table, $value = 'name', $key = 'id', $order = 'id', $sort = 'asc', $limit = 100)
    {
        $list = db($table)->field("$key as id,$value as name")->order($order, $sort)->limit($limit)->select();
        return json($list);
    }
}
