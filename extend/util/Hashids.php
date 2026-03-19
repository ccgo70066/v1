<?php
namespace util;


class Hashids
{
    private static $salt = 'Michael Jackson';
    private static $alphabet = 'abcdefghijkmnpqrstuvwxyz1234567890';


    public function __construct()
    {
    }

    public static function encode($id, $length = 6)
    {
        $hashids = new \Hashids\Hashids(self::$salt, $length, self::$alphabet);
        return $hashids->encodeHex($id);
    }

    public static function decode($string, $length = 6)
    {
        $hashids = new \Hashids\Hashids(self::$salt, $length, self::$alphabet);
        return $hashids->decodeHex($string);
    }
}