<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\controller\Frontend;

/**
 * @link https://ask.fastadmin.net/article/47716.html
 */
class Sse extends Backend
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
     * 前台 SSE 消息推送接口
     * 支持匿名访问（也可根据业务要求强制登录）
     */
    public function sse()
    {
        // 1. 清理并禁用输出缓存，确保消息实时性
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        // 关闭PHP执行超时，维持长连接
        set_time_limit(0);

        // 2. 设置SSE核心响应头（FastAdmin/TP5.1通用）
        header('Content-Type: text/event-stream');       // SSE专属MIME类型
        header('Cache-Control: no-cache');               // 禁止缓存
        header('Connection: keep-alive');                // 保持长连接
        header('X-Accel-Buffering: no');                 // 禁用Nginx缓冲（生产必加）
        header('Access-Control-Allow-Origin: *');        // 跨域支持（生产替换为具体域名）
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');

        // 3. 发送初始化事件（告知客户端连接成功）
        echo "event: sse_init\ndata: " . json_encode(['status' => 'success', 'msg' => '连接成功'], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();

        // 4. 循环推送消息（核心逻辑）
        $count = 0;
        $maxCount = 50; // 最大推送次数，避免无限循环
        while (true) {
            // 检测客户端断开连接或达到最大次数，终止循环
            if (connection_aborted() || $count >= $maxCount) {
                break;
            }

            // 模拟业务数据（可替换为数据库/Redis/MQ查询）
            $data = [
                'id'      => $count + 1,
                'title'   => 'FastAdmin实时通知',
                'content' => '新消息：' . date('Y-m-d H:i:s'),
                'time'    => date('H:i:s'),
                'url'     => '/index/sse/detail'
            ];

            // 按SSE标准格式输出（event指定事件名，data为消息体）
            echo "event: my_event\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            // 强制刷新缓冲区，确保消息立即推送
            flush();

            // 控制推送频率（每2秒1条，可根据业务调整）
            sleep(2);
            $count++;
        }

        // 5. 清理资源
        ob_clean();
        return;
    }

    /**
     * SSE测试页面渲染方法
     */
    public function test()
    {
        return $this->view->fetch();
    }
}
