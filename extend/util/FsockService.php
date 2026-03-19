<?php

namespace util;

/*
 * usage
 $url   = 'http://' . $_SERVER['HTTP_HOST'] . '/api/gift/randomBoxOpenCoroutine';
            $param = [
                'uid'     => $uid,
                'roomId'  => $roomId,
                'giftid'  => $broadcast['id'],
                'count'   => $broadcast['count'],
            ];

            FsockService::get($url, $param);
 */

/**
 * Class FsockService
 * @package util
 *
 *
 */
class FsockService
{

    public static function post($url, $param)
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = 80;
        $errno = '';
        $errstr = '';
        $timeout = 30;

        $data = http_build_query($param);

        $fp = fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$fp) {
            return false;
        }

        $out = "POST $url HTTP/1.1\r\n";
        $out .= "Host:$host\r\n";
        $out .= "Content-type:application/x-www-form-urlencoded\r\n";
        $out .= "Content-length:" . strlen($data) . "\r\n";
        $out .= "Connection:close\r\n\r\n";
        $out .= "$data";

        fwrite($fp, $out);

        if (false) {
            $ret = '';
            while (!feof($fp)) {
                $ret .= fgets($fp, 128);
            }
        }

        usleep(20000);

        fclose($fp);
    }

    public static function get($url, $param)
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = 80;
        $errno = '';
        $errstr = '';
        $timeout = 30;

        $url = $url . '?' . http_build_query($param ?: []);

        $fp = fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$fp) {
            return false;
        }

        $out = "GET $url HTTP/1.1\r\n";
        $out .= "Host:$host\r\n";
        $out .= "Connection:close\r\n\r\n";

        fwrite($fp, $out);

        if (false) {
            $ret = '';
            while (!feof($fp)) {
                $ret .= fgets($fp, 128);
            }
        }

        usleep(20000);

        fclose($fp);
    }

}

