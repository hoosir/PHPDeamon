<?php
class db {
	public $dataDriver;
	public $prj;


	public function __construct($driver,$prj='default',$ip="127.0.0.1",$port="8888")
	{
		$this->prj=$prj;
		$prjConfig=pathRoute($prj);
		$this->dataDriver = new $driver($prj,$ip,$port);
		textRecord(LOG_PATH.date('Y-m-d')."connect.log",$ip." port:".$port);
	}


	public function outPut($query) {
		return $this->dataDriver->fetchItem($query);
	}
}