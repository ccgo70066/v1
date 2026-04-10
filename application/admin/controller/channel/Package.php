<?php

namespace app\admin\controller\channel;

use app\common\controller\Backend;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;
use util\Minio;

/**
 * 渠道包
 *
 * @icon fa fa-circle-o
 */
class Package extends Backend
{

    /**
     * ChannelPackage模型对象
     * @var \app\admin\model\ChannelPackage
     */
    protected $model = null;
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ChannelPackage;
        $this->view->assign("systemList", $this->model->getSystemList());
        $this->view->assign("forceList", $this->model->getForceList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        $with = 'channel';
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with($with)
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            $this->view->assign("row", null);
            return $this->view->fetch('edit');
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if($params['appid'] == 'coco_008' && $params['system'] != 'IOS'){
            $this->error('选择的平台有误');
        }

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try{
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            if ($params['system'] == 'IOS' && $params['download'] != '') {
                $this->generate_plist($params['download'], $params['version']);
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        }catch (ValidateException|PDOException|Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if($params['appid'] == 'coco_008' && $params['system'] != 'IOS'){
            $this->error('选择的平台有误');
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    private function generate_plist($url, $version)
    {
        $str = <<<str
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>items</key>
  <array>
    <dict>
      <key>assets</key>
      <array>
        <dict>
          <key>kind</key>
          <string>software-package</string>
          <key>url</key>
          <string>%s</string>
        </dict>
        <dict>
          <key>kind</key>
          <string>display-image</string>
          <key>url</key>
          <string>https://img.cdn.statschannel.xyz/app_icon/nxt6v8/292181467293976640-1662547051535.png</string>
        </dict>
        <dict>
          <key>kind</key>
          <string>full-size-image</string>
          <key>url</key>
          <string>https://img.cdn.statschannel.xyz/app_icon/nxt6v8/292181467293976640-1662547051535.png</string>
        </dict>
      </array>
      <key>metadata</key>
      <dict>
        <key>bundle-identifier</key>
        <string>%s</string>
        <key>bundle-version</key>
        <string>%s</string>
        <key>kind</key>
        <string>software</string>
        <key>title</key>
        <string>%s</string>
      </dict>
    </dict>
  </array>
</dict>
</plist>
str;
        $str = sprintf($str, str_replace('http://', 'https://', cdnurl($url)), 'com.coco.enterprise', $version,
            '呆呆星球');
        $pathinfo = pathinfo($url);
        $file = './' . $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.plist';

        $temp = RUNTIME_PATH . 'temp/' . $pathinfo['filename'] . '.plist';
        if (!file_exists(pathinfo($temp, PATHINFO_DIRNAME))) {
            mkdir(pathinfo($temp, PATHINFO_DIRNAME));
        }
        file_put_contents($temp, $str);
        $minio = new Minio();
        $minio->putObject($temp, $file);
        unlink($temp);
    }

}
