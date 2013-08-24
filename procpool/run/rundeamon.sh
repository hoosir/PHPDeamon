#!/bin/sh
PHPPath='/usr/bin/php'
LogPath='/deamon/flag/'
ScriptPath='/deamon/run/runDeamon.php'

function startAll
{
		$PHPPath $ScriptPath defaultDeamon a start
}

function stopAll
{
	$PHPPath $ScriptPath defaultDeamon a stop
}

case $1 in
        startall)
        startAll
        ;;

        stopall)
        stopAll
        ;;

        *)
        echo done
        exit 1
        ;;

esac

