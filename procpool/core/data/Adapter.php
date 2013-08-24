<?php
//namespace data;

//use resultSet;

class Adapter //implements model
{
	public $prj;
	public $driver;

	public function __construct($driver, $prj='default')
	{
		$this->driver=$driver;
	}

	public function instance($dirver,$config=array()) {
		;
	}

	/*
	 * 队列操作
	 */
	public function fetchItem($param) {
		;
	}

	public function insertItem($key,$param) {
		;
	}

	/*
	 * key值操作
	 */
	public function setValue($param) {
		;
	}

	public function getValue($param) {
		;
	}

	public function delValue($param) {
	    ;
	}

	public function function_name($param) {
		;
	}

	public function _remap($method,$param) {

		$obj = self::driver;

		if (!method_exists($obj, $method)) {
			return false;
		}


		$rt = call_user_func(array($obj, $method), $param);

		if(!$rt) {
			return false;
		}

		return $rt;
	}
}
?>