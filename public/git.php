<?php

echo shell_exec('whoami');
// 本地仓库路径
$local = pathinfo(__FILE__, PATHINFO_DIRNAME);

// 安全验证字符串，为空则不验证
$token = '123456';

//  payload为字符串，需要经过解析
$payload = file_get_contents('php://input');
if (!$payload) {
    //header('HTTP/1.1 400 Bad Request');
    //die('HTTP HEADER or POST is missing.');
}
$content = json_decode($payload, true);

// 如果启用验证，并且验证失败，返回错误
if ($token && $content['password'] != $token) {
    //header('HTTP/1.1 403 Permission Denied');
    //die('Permission denied.');
}

//最后会执行一个脚本编译代码，然后再push代码到远程
//所以会重复触发WebHooks，因此此处判断是否是本地的推送
if ($content['commits'][0]['author']['name'] == 'handsomeTaoTao') {
    header('HTTP/1.1 403 Permission Denied');
    die('self push.');
}

$shell_exec = shell_exec("cd {$local} && sudo  git pull");
$shell_exec = shell_exec("cd {$local} && sudo  sh /root/.pull.sh");
echo $shell_exec;

die("done " . date('Y-m-d H:i:s', time()));
