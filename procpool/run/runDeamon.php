<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
set_time_limit(0);

include dirname(__FILE__).'/../core/config/config.php';

if (empty($argv[1])) {
	die("Usage: php " . __FILE__ . ' deamonName tag start|stop');
}

$deamonName =  $argv[1];
$tagName =  $argv[2];

$deamon=new $deamonName($tagName);

switch ($argv[3]) {
    case 'stop':
        $deamon->stop();
        break;
    case 'start':
	   $deamon->run();
	   break;
     default:
     	$deamon->run();
     	break;
}