<?php
namespace util;

/**
 * 邀请码生成器，算法原理(类似于hashIDS)：<br/>
 * 1) 获取id: 1127738 <br/>
 * 2) 使用自定义进制转为：gpm6 <br/>
 * 3) 转为字符串，并在后面加'o'字符：gpm6o <br/>
 * 4）在后面随机产生若干个随机数字字符：gpm6o7 <br/>
 * 转为自定义进制后就不会出现o这个字符，然后在后面加个'o'，这样就能确定唯一性。最后在后面产生一些随机字符进行补全。<br/>
 */
class IDEncrypt
{
    private static $r = ['q', 'w', 'e', '8', 's', '2', 'd', 'z', 'x', '9', 'c', '7', 'p', '5', 'k', '3', 'm', 'j', 'u', 'f', 'r', '4', 'v', 'y', 't', 'n', '6', 'b', 'g', 'h'];

    /** 定义一个字符用来补全邀请码长度（该字符前面是计算出来的邀请码，后面是用来补全用的） */
    private static $b = 'a';

    /** 进制长度 */
//    private static $a = count(self::$r);

    /** 邀请码长度 */
    private static $s = 6;

    /**
     * 根据ID生成随机码
     *
     * @param id ID
     * @return 随机码
     */
    public static function encrypt($id)
    {
        /**
         * 邀请码生成器，算法原理：<br/>
         * 1) 获取id: 1127738 <br/>
         * 2) 使用自定义进制转为：gpm6 <br/>
         * 3) 转为字符串，并在后面加'o'字符：gpm6o <br/>
         * 4）在后面随机产生若干个随机数字字符：gpm6o7 <br/>
         * 转为自定义进制后就不会出现o这个字符，然后在后面加个'o'，这样就能确定唯一性。最后在后面产生一些随机字符进行补全。<br/>
         */
        $binLen = count(self::$r);
        $str = '';
        while ((int)($id / $binLen) > 0) {
            $ind = (int)($id % $binLen);
            $id = (int)($id / $binLen);
            $str = self::$r[$ind] . $str;
        }
        $str = self::$r[(int)($id % $binLen)] . $str;

        if (strlen($str) < self::$s) {
            $append = self::$b;
            for ($i = 1; $i < self::$s - strlen($str); $i++) {
                $append .= self::$r[rand(0, $binLen - 1)];
            }
            $str .= $append;
        }
        return $str;
    }

    /**
     * 根据随机码生成ID
     *
     * @param 随机码
     * @return ID
     */
    public static function decrypt($code)
    {
        $binLen = count(self::$r);
        $res = 0;
        for ($i = 0; $i < strlen($code); $i++) {
            $ind = 0;
            for ($j = 0; $j < count(self::$r); $j++) {
                if ($code[$i] == self::$r[$j]) {
                    $ind = $j;
                    break;
                }
            }
            if ($code[$i] == self::$b) {
                break;
            }
            if ($i > 0) {
                $res = $res * $binLen + $ind;
            } else {
                $res = $ind;
            }
        }
        return $res;
    }
}
