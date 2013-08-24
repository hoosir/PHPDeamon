<?php
/*
 * 用于配置项目及路由的文件，减小对服务器rewrite的依赖
 */
define(CONFIG_PATH,dirname(__FILE__).'/');
define(DIRECTORY_PATH,dirname(__FILE__).'/../../');
define(CLASS_PATH,dirname(__FILE__). '/../class/');
define(API_PATH,dirname(__FILE__). '/../../api/');
define(RUN_PATH,dirname(__FILE__). '/../../run/');
define(DEAMON_PATH,dirname(__FILE__). '/../../deamon/');
define(DRIVER_PATH,dirname(__FILE__). '/../data/driver/');
define(LOG_PATH,dirname(__FILE__).'/../../log/');

define(DAEMON_FLAG_DIR,dirname(__FILE__).'/../../flag/');

ini_set('default_socket_timeout', -1);  //不超时

$inputApiUrl="http://localhost/api/";

function pathRoute($prj)
{
	include DEAMON_PATH.$prj.'conf/config.php';

	return $config;
}

//运行日志
function textRecord($logFile,$text)
{
	$pathInfo=pathinfo($logFile);
	if($pathInfo['dirname']=='')
	{
		$dir=LOG_PATH;
		$logFile=LOG_PATH."/".$logFile;
	}

	$oldMask = umask(0);
     if (!$handle = fopen($logFile, 'ab')) {
         echo "can't open : $logFile";
         return false;
     }
     if (fwrite($handle, $text."\n") === FALSE) {
        echo "can't wrt into :  $logFile";
        exit;
     }
     fclose($handle);

	umask($oldMask);
}

function curl_file_get_contents($durl){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $durl);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_USERAGENT, _USERAGENT_);
	curl_setopt($ch, CURLOPT_REFERER,_REFERER_);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$r = curl_exec($ch);
	curl_close($ch);
	return $r;
}

 function curlPostUrlData($url,$data)
{
	$timeout=5;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	if( ! $result = curl_exec($ch))
    	{
        textRecord(LOG_PATH.date('Y-m-d')."curl_error.log",curl_error($ch));
    	}
	curl_close($ch);
	return $result;
}

interface config
{
	public function getConfig($prj);
}

function loader($class)
{
	//require  CLASS_PATH."queue.php";

	$type=strtolower(substr($class, -6));
	$baseArr=array("fork","db","watch","queue","curl","deamon","Adapt");
	if(in_array($class, $baseArr))
	{
		$type="base";
	}
	switch ($type) {
	    case 'deamon':
	    	   $className=str_ireplace($type,'',$class);
	        require  DEAMON_PATH.$className."/task/".$class.".php";
	        break;
	    case 'base':
	    	   require CLASS_PATH.$class.".php";
	        break;
	    case 'driver':
	    	   require DRIVER_PATH.$class.".php";
	    	   break;
	    default:
	    		//require CLASS_PATH.$class.".php";
	    	   return false;
	}

}
spl_autoload_register('loader');
