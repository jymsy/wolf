<?php
namespace webmanager\components;

use Sky\web\WebApplication;
use Sky\base\HttpException;
use Sky\Sky;
class MagApplication extends WebApplication{
	
	public function beforeControllerAction($controller, $action)
	{
		$route=$controller->id.'/'.$action->id;
		$publicPages=array(
			'default/Login',
			'default/Error',
		);

		if(Sky::$app->user->getIsGuest() && !in_array($route,$publicPages))
			Sky::$app->user->loginRequired();
		else
			return true;
		return false;
	}
}