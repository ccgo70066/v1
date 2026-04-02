<?php

namespace app\common\library;

use think\Env;
use util\OpenSSL3DES;

class ApiEnhance
{
    protected static $instance;
    protected $requestKey = '';
    protected $requestVi = '';
    protected $responseKey = '';
    protected $responseVi = '';

    protected function __construct($options = [])
    {
        $this->requestKey = Env::get('api.request_encode_key');
        $this->requestVi = Env::get('api.request_encode_vi');
        $this->responseKey = Env::get('api.response_encode_key');
        $this->responseVi = Env::get('api.response_encode_vi');

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
        $des = new OpenSSL3DES($this->requestKey, $this->requestVi);
        return $des->encrypt(base64_encode($string));
    }

    public function requestDecode($string)
    {
        $des = new OpenSSL3DES($this->requestKey, $this->requestVi);
        return base64_decode($des->decrypt($string));
    }

    public function responseEncode($string)
    {
        $des = new OpenSSL3DES($this->responseKey, $this->responseVi);
        return $des->encrypt(base64_encode($string));
    }

    public function responseDecode($string)
    {
        $des = new OpenSSL3DES($this->responseKey, $this->responseVi);
        return base64_decode($des->decrypt($string));
    }
}
