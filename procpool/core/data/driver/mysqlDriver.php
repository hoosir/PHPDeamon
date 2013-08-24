<?php
//use data\Adapter;

class mysqlDriver extends Adapt {

	public function __construct($prj='default')
	{
		parent::__construct('mysqlDriver');

		$config['mode'] = 'none';
		$config['type'] = 'mysql';
		$config['host'] = 'localhost';
		$config['port'] = '3306';
		$config['user'] = 'root';
		$config['pass'] = '';
		$config['name'] = 'db';
		$config['charset'] = 'utf8';

		$this->link = mysql_connect($config['host'] . ($config['port'] ? ':' . $config['port'] : ''), $config['user'], $config['pass'], TRUE);
		mysql_select_db($config['name'], $this->link);
		mysql_set_charset($config['charset'], $this->link);
	}

	public function functionHandle($function)
	{

		if(function_exists(pdo::$function))
		return pdo::$function();
	}

	public function connect($ip,$port)
	{
		return mysql_connect($ip,$port);
	}

	public function  query($sql) {
		try{
			$result = mysql_query($sql, $this->link);
			throw new exception("can't query sql:".$sql);
		}catch(Exception $e){
			//捕获异常
			$this->dealException($e->getMessage());
		}

		return $result;
	}

	public function fetchItem($sql)
	{

	}

	private function dealException($content)
	{
		textRecord(LOG_PATH.date('Y-m-d')."mysql.log",date('Y-m-d h:i:s').time()."content:".$content);
		$this->connect($this->ip, $this->port);
	}
}