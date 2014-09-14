<?php
namespace webmanager\models;
use webmanager\components\BaseModel;

/**
 * Created by IntelliJ IDEA.
 * User: jym
 * Date: 14-4-4
 * Time: 上午9:43
 */

class Process extends BaseModel{
    const STATUS_PROC_ERROR=4;
    public $server;

    public function attributeLabels()
    {
        return array(
            'server'=>'选择wolf所在服务器',
        );
    }
}