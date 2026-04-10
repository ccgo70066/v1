<?php

namespace app\admin\model;


use think\Model;


class ErrorLog extends Model
{

    protected $connection = 'mongodb';
    protected $table = 'aa_error_log';
    protected $autoWriteTimestamp = 'datetime';

    protected $createTime = 'create_time';

    protected $pk = '_id';


}
