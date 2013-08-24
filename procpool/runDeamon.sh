#!/bin/sh
PHPPath='/usr/bin/php'
LogPath='/data/log/'
ScriptPath='xx/runDeamon.php'

function startAll
{
	$PHPPath $ScriptPath weatherDeamon a start
	$PHPPath $ScriptPath weatherDeamon b start
	 $PHPPath $ScriptPath weatherDeamon c start
}

function stopAll
{
	$PHPPath $ScriptPath weatherDeamon a stop
	$PHPPath $ScriptPath weatherDeamon b stop
	$PHPPath $ScriptPath weatherDeamon c stop
}

function killAll
{
	 systempids=`ps -ef | grep 'runDeamon.php' | grep -v 'grep' | awk '{print $2}'`
	 for spid in $systempids
        do
                echo $spid
                kill -9 $spid
        done
   rm -rf $LogPath
   mkdir $LogPath
   chown -R root.root $LogPath
   chmod -R 777 $LogPath
}

function downAll
{
	stopAll
	killAll
}

function restart
{
	stopAll;
	killAll;
	startAll;
}


case $1 in
        startall)
        startAll
        ;;
        killall)
        killAll
        ;;
        restart)
        restart
        ;;
        stopall)
        stopAll
        ;;
        downall)
        downAll
        ;;
        *)
        echo done
        exit 1
        ;;

esac

