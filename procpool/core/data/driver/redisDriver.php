<?php
//use data\Adapter;

class redisDriver extends Adapt {
	public $ip;
	public $port;
	public $redis;

	public function __construct($prj='default',$ip="10.10.0.142",$port="6325",$pconn="1")
	{
		parent::__construct('redisDriver');
		$this->ip=$ip;
		$this->port=$port;

		$this->redis=new Redis();
		if($pconn=='1')
		{
			$this->pconnect();
		}elseif($pconn=='0')
		{
			$this->connect();
		}
	}

	public function pconnect()
	{
		try {
			$result=$this->redis->pconnect($this->ip,$this->port);
			if(!$result)
			{
				$this->redis->close();
				$this->redis->pconnect($this->ip,$this->port);//reconnect
				throw new exception("can't pcon redis".$this->ip.$this->port);
			}
		}catch(Exception $e){
			//捕获异常
			$this->dealException($e->getMessage());
		}
	}

	public function connect()
	{
		try {
			$result=$this->redis->connect($this->ip,$this->port);
			if(!$result)
			{
				$this->redis->close();
				$this->redis->connect($this->ip,$this->port);//reconnect
				throw new exception("can't con redis".$this->ip.$this->port);
			}
		}catch(Exception $e){
			//捕获异常
			$this->dealException($e->getMessage());
		}
	}

	public function reconnect()
	{
		$this->redis->close();
		return $this->connect();

	}

	//左进右出
	public function insertItem($key,$param,$paramType='json')
	{
		try {
			$result=$this->redis->lPush($key, $param);
			if($result==false)
			{
				throw new exception("reinsert result:$result   $key list error: $param ");
			}
		}catch(Exception $e){
		    //捕获异常
		    //$result2=$this->retryInsertItem($key,$param);
		    //$this->dealException($e->getMessage());
		}
		//$rtn=$result?$result:$result2;
		$rtn=$result;
		return  $rtn;
	}

	public function insetEx($key,$value,$time="300")
	{
		try {
			$result=$this->redis->setex($key,$time, $value);
			if(!$result)
			{
				$this->redis->close();
				$this->redis->pconnect($this->ip,$this->port);//reconnect
				throw new exception("can't insetEx".$key."value:".$value);
			}
		}catch(Exception $e){
			//捕获异常
			$this->redis->setex($key,$time, $value);
			$this->dealException($e->getMessage());
		}
		return $result;
	}

	public function retryInsertItem($key,$param)
	{
		$this->closeConnect();
		$this->connect();
		$result2=$this->redis->lPush($key, $param);
		if($result2===false)
		{
			textRecord(LOG_PATH.date('Y-m-d')."redis_reinsert.log",date('Y-m-d h:i:s').time()."key:".$key."content:".$param);

		}
		return $result2;
	}

	public function fetchItem($key)
	{
		try {
			$result=$this->redis->rPop($key);
			if($result==false)
			{
				throw new exception("can't pop".$key);
			}
		}catch(Exception $e){
			//$this->dealException($e->getMessage());
			usleep(1000);
		}
		return $result;
	}

	public function moveListItem($src,$dst)
	{
		$return=$this->redis->rpoplpush($src,$dst);
		return $return;
	}

	public function fetchBlockItem($key,$timeout='1')
	{
		try {
			$result=$this->redis->brPop($key,$timeout);
			if($result==false)
			{
				throw new exception("can't brpop".$key);
			}
		}catch(Exception $e){
			//$this->dealException($e->getMessage());
			usleep(1000);
		}
		return $result;
	}

	public function setValue($key,$param)
	{
		return $this->redis->set($key,$param);
	}

	public function setValueIfNo($key,$param)
	{
		return $this->redis->setnx($key,$param);
	}


	public function getValue($key)
	{
		return $this->redis->get($key);
	}

	public function delValue($key)
	{
		return $this->redis->delete($key);
	}

	public function setIncrValue($key,$param='1')
	{
		return $this->redis->incr($key);
	}

	public function setDecrValue($key,$param='1')
	{
		return $this->redis->decr($key);
	}

	public function insertSet($key,$value)
	{
		return $this->redis->sAdd($key,$value);
	}

	public function checkSet($key,$value)
	{
		return $this->redis->sismember($key,$value);
	}

	public function deleteFromSet($key,$value)
	{
		return $this->redis->sRem($key,$value);
	}

	public function getNumSet($key)
	{
		return $this->redis->scard($key);
	}

	public function getAllSet($key)
	{
		return $this->redis->sMembers($key);
	}

	public function listLength($key)
	{
		return $this->redis->lLen($key);
	}

	public function setObjectInfo($key,$hashkey,$value)
	{
		return $this->redis->hSet($key,$hashkey,$value);
	}

	public function getObjectInfo($key)
	{
		return $this->redis->hGetAll($key);
	}

	public function getHashInfo($key,$hashkey)
	{
		return $this->redis->hGet($key,$hashkey);
	}


	public function closeConnect()
	{
		return $this->redis->close();
	}


	private function is_not_json($str)
	{
		return is_null(json_decode($str));
	}

	private  function is_json($string)
	{
		 json_decode($string);
		 return (json_last_error() == JSON_ERROR_NONE);
	}

	private function dealException($content)
	{
		textRecord(LOG_PATH.date('Y-m-d')."redis.log",date('Y-m-d h:i:s').time()."content:".$content);
		$this->connect($this->ip, $this->port);
	}

	public function __destruct()
	{
		$this->closeConnect();
	}
}