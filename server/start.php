<?php

header('Access-Control-Allow-Origin: *');

define( 'ABSPATH', realpath(dirname(__FILE__) . '/../') . '/' );

require(ABSPATH.'config.php');
require_once(ABSPATH.'class/Server.php');

if( file_exists($config['server']['pidfile_on']) ) {

	$pid = Server::getProcessStatus($config['server']['pidfile_on']);

	if($pid >= 0){
		echo '{run:1}';
		exit;
	}
    elseif($pid == -1){

    }
	elseif($pid == -2){
		unlink($config['server']['pidfile_on']);
		Server::consolemsg('start.php: abnormal process termination');
		Server::consolemsg('start.php: PID file "'.$config['server']['pidfile_on'].'" ulinked');
		Server::consolemsg('start.php: try to run again');
	}
}

exec('php -q '.ABSPATH.'server/init.php &');
echo '{run:2}';
