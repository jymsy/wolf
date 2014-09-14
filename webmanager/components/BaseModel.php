<?php
namespace webmanager\components;

use Sky\base\Model;
class BaseModel extends Model{
	const STATUS_SUCCESS=3;
	const STATUS_ERROR=2;
	public $status;
}