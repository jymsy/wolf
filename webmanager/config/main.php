<?php
$config=array(
		'basePath'=>__DIR__.DIRECTORY_SEPARATOR.'..',
		'name'=>'webmanager',
		'defaultController'=>'default',
		'components'=>array(
				'errorHandler'=>array(
						'class'=>'Sky\base\ErrorHandler',
						'errorAction'=>'default/error',
				),
				'user' => array(
						'class' => 'Sky\web\User',
						'identityClass' => 'webmanager\models\User',
						'loginUrl'=>Sky\Sky::$app->createUrl('default/login'),
				),
				'urlManager'=>array(
// 						'urlFormat'=>'path',
						'useParamName'=>true,
// 						'needCompatibility'=>true,
				),
				'session'=>array(
					'class'=>'Sky\web\Session',
				),
		),

		// 可以使用 \Sky\Sky::$app->params['paramName'] 访问的应用级别的参数
		'params'=>array(
				'password'=>'guest',
                'wolf'=>array(
                    'localhost'=>array('127.0.0.1',3839),
                ),
		),
		'modules'=>array(),
);
return $config;