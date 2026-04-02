<?php

namespace app\common\library;

use think\Env;
use util\OpenSSL3DES;

class ApiEnhance
{
    protected static $instance;
    protected $requestKey = '';
    protected $responseKey = '';

    protected function __construct($options = [])
    {
        $this->requestKey = Env::get('api.request_encode_key');
        $this->responseKey = Env::get('api.response_encode_key');

        foreach ($options as $name => $item) {
            if (property_exists($this, $name)) {
                $this->$name = $item;
            }
        }
    }

    public static function instance($options = []): static
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }

    public function requestEncode($string)
    {
        return $this->encode($string, $this->requestKey);
    }

    public function requestDecode($string)
    {
        return $this->decode($string, $this->requestKey);
    }

    public function responseEncode($string)
    {
        return $this->encode($string, $this->responseKey);
    }

    public function responseDecode($string)
    {
        return $this->decode($string, $this->responseKey);
    }

    public function encode($plaintext, $key)
    {
        $key = substr(hash('sha256', $key, true), 0, 32);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $ciphertext . $tag);
    }

    public function decode($encrypted, $key)
    {
        $data = base64_decode($encrypted);
        $key = substr(hash('sha256', $key, true), 0, 32);
        $iv = substr($data, 0, 12);
        $tag = substr($data, -16);
        $ciphertext = substr($data, 12, -16);

        return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    }
}
