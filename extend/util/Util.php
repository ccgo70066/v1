<?php

namespace util;


class Util
{

    /**
     * 经典的权重概率算法，
     * @param array $proArr 概率数组, ex: ['index1' => '权重1', 'index2' => '权重2']
     * @return int|string
     */
    public static function get_rand($proArr)
    {
        $result = 0;
        //概率数组的总概率精度
        $proSum = array_sum($proArr);
        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }

        return $result;
    }


    /**
     * 将utf-16的emoji表情转为utf8文字形
     * @param string $str 需要转的字符串
     * @return string      转完成后的字符串
     */
    function escape_sequence_decode($str)
    {
        $regex = '/\\\u([dD][89abAB][\da-fA-F]{2})\\\u([dD][c-fC-F][\da-fA-F]{2})|\\\u([\da-fA-F]{4})/sx';
        return preg_replace_callback($regex, function ($matches) {
            if (isset($matches[3])) {
                $cp = hexdec($matches[3]);
            } else {
                $lead = hexdec($matches[1]);
                $trail = hexdec($matches[2]);
                $cp = ($lead << 10) + $trail + 0x10000 - (0xD800 << 10) - 0xDC00;
            }

            if ($cp > 0xD7FF && 0xE000 > $cp) {
                $cp = 0xFFFD;
            }
            if ($cp < 0x80) {
                return chr($cp);
            } else {
                if ($cp < 0xA0) {
                    return chr(0xC0 | $cp >> 6) . chr(0x80 | $cp & 0x3F);
                }
            }
            $result = html_entity_decode('&#' . $cp . ';');
            return $result;
        }, $str);
    }


    /**
     * 字符串简单加密
     * @param        $string
     * @param        $key
     * @param string $action
     * @return string
     */
    static function str_code($string, $key, $action = 'ENCODE')
    {
        $action != 'ENCODE' && $string = base64_decode($string);
        $code = '';
        $key = substr(md5($key), 8, 18);
        $keyLen = strlen($key);
        $strLen = strlen($string);
        for ($i = 0; $i < $strLen; $i++) {
            $k = $i % $keyLen;
            $code .= $string[$i] ^ $key[$k];
        }
        return ($action != 'DECODE' ? base64_encode($code) : $code);
    }


    /**
     * @desc   还原科学计数法数字
     * @param $num
     * @return float|string
     * @author cooper
     */
    public static function ScToNum($num)
    {
        $num = floatval($num);
        $parts = explode('E', $num);
        if (count($parts) != 2) {
            return $num;
        }
        $exp = abs(end($parts)) + 3;
        $decimal = number_format($num, $exp);
        $decimal = rtrim($decimal, '0');
        return rtrim($decimal, '.');
    }

    static function curl_post($url, $data = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public static function have_emoji_char($str): bool
    {
        $mbLen = mb_strlen($str);
        $strArr = [];
        for ($i = 0; $i < $mbLen; $i++) {
            $strArr[] = mb_substr($str, $i, 1, 'utf-8');
            if (strlen($strArr[$i]) >= 4) {
                return true;
            }
        }
        return false;
    }

    public static function replace_emoji_char($str, $replace = ''): string
    {
        $mbLen = mb_strlen($str);
        $strArr = [];
        for ($i = 0; $i < $mbLen; $i++) {
            $mbSubstr = mb_substr($str, $i, 1, 'utf-8');
            if (strlen($mbSubstr) >= 4) {
                if ($replace) {
                    $strArr[] = $replace;
                }
                continue;
            }
            $strArr[] = $mbSubstr;
        }
        return implode('', $strArr);
    }


    /**
     * 将数值金额转换为中文大写金额
     * @param $amount float 金额(支持到分)
     * @param $type   int   补整类型,0:到角补整;1:到元补整
     * @return mixed 中文大写金额
     */
    function convertAmountToCn($amount, $type = 1)
    {
        // 判断输出的金额是否为数字或数字字符串
        if (!is_numeric($amount)) {
            return "要转换的金额只能为数字!";
        }

        // 金额为0,则直接输出"零元整"
        if ($amount == 0) {
            return "人民币零元整";
        }

        // 金额不能为负数
        if ($amount < 0) {
            return "要转换的金额不能为负数!";
        }

        // 金额不能超过万亿,即12位
        if (strlen($amount) > 12) {
            return "要转换的金额不能为万亿及更高金额!";
        }

        // 预定义中文转换的数组
        $digital = array('零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
        // 预定义单位转换的数组
        $position = array('仟', '佰', '拾', '亿', '仟', '佰', '拾', '万', '仟', '佰', '拾', '元');

        // 将金额的数值字符串拆分成数组
        $amountArr = explode('.', $amount);

        // 将整数位的数值字符串拆分成数组
        $integerArr = str_split($amountArr[0], 1);

        // 将整数部分替换成大写汉字
        $result = '人民币';
        $integerArrLength = count($integerArr);     // 整数位数组的长度
        $positionLength = count($position);         // 单位数组的长度
        $zeroCount = 0;                             // 连续为0数量
        for ($i = 0; $i < $integerArrLength; $i++) {
            // 如果数值不为0,则正常转换
            if ($integerArr[$i] != 0) {
                // 如果前面数字为0需要增加一个零
                if ($zeroCount >= 1) {
                    $result .= $digital[0];
                }
                $result .= $digital[$integerArr[$i]] . $position[$positionLength - $integerArrLength + $i];
                $zeroCount = 0;
            } else {
                $zeroCount += 1;
                // 如果数值为0, 且单位是亿,万,元这三个的时候,则直接显示单位
                if (($positionLength - $integerArrLength + $i + 1) % 4 == 0) {
                    $result = $result . $position[$positionLength - $integerArrLength + $i];
                }
            }
        }

        // 如果小数位也要转换
        if ($type == 0) {
            // 将小数位的数值字符串拆分成数组
            $decimalArr = str_split($amountArr[1], 1);
            // 将角替换成大写汉字. 如果为0,则不替换
            if ($decimalArr[0] != 0) {
                $result = $result . $digital[$decimalArr[0]] . '角';
            }
            // 将分替换成大写汉字. 如果为0,则不替换
            if ($decimalArr[1] != 0) {
                $result = $result . $digital[$decimalArr[1]] . '分';
            }
        } else {
            $result = $result . '整';
        }
        return $result;
    }

    /**
     * 大数值转短数值加单位
     * @param $number
     * @return string
     */
    public static function short_number($number)
    {
        if (empty($number) || !is_numeric($number)) {
            return $number;
        }
        if ($number < 10000) {
            return $number;
        }
        $unit = '';
        if ($number > 100000000) {
            $unit = '亿';
            $number = number_format($number / 100000000, 2, '.', '');
        }
        if ($number > 10000) {
            $unit = '万';
            $number = number_format($number / 10000, 2, '.', '');
        }

        return (string)$number . $unit;
    }


    public static function get_range($ends, $number)
    {
        $ends = array_filter($ends, function ($v) {
            return $v != '-';
        });
        $ends[] = $number;
        sort($ends);
        return array_search($number, $ends);
    }

    public static function mongodb_convert($data)
    {
        foreach ($data as $k => $datum) {
            if (is_array($datum)) {
                foreach ($datum as $key => $value) {
                    if (is_numeric($value) && strpos($key, 'time') === false) {
                        $datum[$k][$key] = (string)$value;
                    }
                }
            } elseif (is_numeric($datum) && strpos($k, 'time') === false) {
                $data[$k] = (string)$datum;
            }
        }
        return $data;
    }

    /**
     * 支持小数的random_int
     */
    public static function random($min, $max = 1)
    {
        $diff_mul = 0;
        $min_mul = (int)str_replace('.', '', $min) / $min;
        $min_mul > $diff_mul && $diff_mul = $min_mul;
        $max_mul = (int)str_replace('.', '', $max) / $max;
        $max_mul > $diff_mul && $diff_mul = $max_mul;

        return random_int($min * $diff_mul, $max * $diff_mul) / $diff_mul;
    }


    /**
     * 随机百分比
     * @param $percent
     * @return boolean  随机的百分比小于传入的返回true, 否则false
     */
    public static function jump($percent)
    {
        if ($percent > 1) {
            return true;
        }
        $percent *= 10000;
        $result = random_int(1, 10000);
        return $result <= $percent;
    }

    /**
     * 根据概率进行随机roll点
     * @param float $percent 0~1
     * @return bool 随机的值小于$percent则返回true, 大于$percent则返回false
     * @throws \Exception
     */
    public static function roll($percent)
    {
        if ($percent > 1) return true;
        if ($percent == 0 || $percent == null) return false;
        $int_percent = (int)str_replace('.', '', (float)$percent);
        $divisor = $int_percent / $percent;
        return random_int(1, $divisor) <= $int_percent;
    }


    /**
     * 数组下标过滤
     * @param array        $array
     * @param array|string $filter  ['id','name'] |'id,name'
     * @param bool         $exclude true=排除,false=保留
     * @return array
     */
    public static function array_index_filter($array, $filter, $exclude = false)
    {
        if (!is_array($filter) && is_string($filter)) {
            $filter = explode(',', $filter);
        }
        if ($exclude) {
            return array_diff_key($array, array_flip($filter));
        } else {
            return array_intersect_key($array, array_flip($filter));
        }
    }

    public static function exceptionFormat(\Exception $e): array
    {
        return [
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString()
        ];
    }

}
