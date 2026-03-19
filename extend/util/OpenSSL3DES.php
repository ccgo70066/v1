<?php

namespace util;

/**
 * 兼容java的3des加密
 * Class OpenSSL3DES
 * @package app\extra\util
 */
class OpenSSL3DES
{
    /*密钥*/
    private $key;
    /*向量，8个或10个字符*/
    private $iv;

    /**
     * 兼容java的3des加密
     * @param string $key 密钥
     * @param string $iv 向量
     */
    public function __construct($key, $iv)
    {
        if (empty($key) || empty($iv)) {
            echo 'key and iv is not valid';
            exit();
        }
        $this->key = $key;
        $this->iv = $iv;
    }

    /**
     *加密
     * @param $value
     * @return mixed
     */
    public function encrypt($value)
    {
        return base64_encode(openssl_encrypt($value, 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv));

    }

    /**
     *解密
     * @param $value
     * @return false|string
     */
    public function decrypt($value)
    {
        return openssl_decrypt(base64_decode($value), 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);

    }


}