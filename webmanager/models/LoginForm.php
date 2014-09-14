<?php
namespace webmanager\models;

use Sky\base\Model;
use Sky\Sky;
class LoginForm extends Model{
	public $password;
	
	public function rules()
	{
		return array(
				array('password', 'required','skipOnEmpty'=>false,'message'=>'密码不能为空'),
				array('password', 'authenticate'),
		);
	}
	
	/**
	 * 验证密码。
	 */
	public function authenticate($attribute,$params)
	{
		if($this->password!==Sky::$app->params['password'])
		{
			$this->addError('password','密码错误.');
		}
	}
	
	public function login()
	{
		if ($this->validate()) {
			$user=User::model();
			$user->setName('manager');
			Sky::$app->getUser()->login(User::model());
			return true;
		}else
			return false;
	}
}