<?php
namespace webmanager\controllers;
use Sky\base\Controller;
use webmanager\models\LoginForm;
use Sky\Sky;
class DefaultController extends Controller{
	public $layout='column1';
	
	public function getPageTitle()
	{
        return 'Wolf';
	}
	
	public function actionError()
	{
		if($error=Sky::$app->getErrorHandler()->error)
		{
			if(Sky::$app->getRequest()->getIsAjaxRequest())
				echo $error['message'];
			else
				$this->render('error', $error);
		}
	}
	
	public function actionLogin()
	{
		$model=new LoginForm();
		if(isset($_POST['webmanager_models_LoginForm']))
		{
			$model->attributes=$_POST['webmanager_models_LoginForm'];
			if($model->login())
			{
				$this->redirect(array('process/index'));
			}
		}
		
		$this->render('login',array('model'=>$model));
	}
	
	public function actionLogout()
	{
		Sky::$app->getUser()->logout();
		$this->redirect(array('login'));
	}
	
}