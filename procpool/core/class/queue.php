<?php
class queue {
	public $dataDriver;
	public $prj;

	public function __construct($driver,$prj='default',$ip="127.0.0.1",$port="8888")
	{
		$prjConfig=pathRoute($prj);
		//$this->dataDriver = new namespace\data\Adapter($driver,$prjConfig);
		$this->dataDriver = new $driver($prj,$ip,$port,1);//queue use pcon
	}

	public function inPut($queueName,$param) {
		return $this->dataDriver->insertItem($queueName,$param);
	}

	public function outPut($queueName) {
		return $this->dataDriver->fetchItem($queueName);
		//return $this->dataDriver->fetchBlockItem($queueName);
	}

	public function moveQueue($srckey,$dstkey)
	{
		return $this->dataDriver->moveListItem($srckey,$dstkey);
	}

	public function outBlockPut($queueName,$timewait='1') {
		return $this->dataDriver->fetchBlockItem($queueName,$timewait);
	}


}