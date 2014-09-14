<?php
namespace webmanager\models;

use Sky\db\ActiveRecord;
use Sky\base\IUserIdentity;
class User extends ActiveRecord implements IUserIdentity{
	public $_username;
// 	private $_id;
	/**
	 * @param system $className
	 * @return User
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
	
	public function getId(){
		return $this->_username;
	}
	
	public function setName($name)
	{
		$this->_username=$name;
	}
	
	public function getName(){
		return $this->_username;
	}
	
	public function authenticate(){
	
	}
}