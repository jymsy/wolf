<?php
use webmanager\components\MagApplication;

require_once(__DIR__.'/framework/sky.php');
require_once(__DIR__.'/components/MagApplication.php');
$config=__DIR__.'/config/main.php';
// \Sky\Sky::createWebApplication($config)->run();
$app=new MagApplication($config);
$app->run();