<?php

namespace app\common\model;

use think\Model;

/**
 * 主播
 */
class Anchor extends Model
{

    // 表名
    protected $name = 'anchor';
    const STATUS_REJECT = 0;     //签约驳回
    const STATUS_AUDIT = 1;     //签约审核中
    const STATUS_PASSED= 2;    //已签约

}
