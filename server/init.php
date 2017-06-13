<?php

define( 'ABSPATH', realpath(dirname(__FILE__) . '/../') . '/' );

require(ABSPATH.'config.php');

require_once(ABSPATH.'class/Console.php');
require_once(ABSPATH.'class/Server.php');
require_once(ABSPATH.'class/Chat.php');
require_once(ABSPATH.'class/DB.php');

$pidfile = $config['server']['pidfile_on'];

if( file_exists($pidfile) ) {

    $pid = file_get_contents($pidfile);

    $status = Server::getProcessInfo($pid);
    if($status['run']) {
        Console::write("Daemon already running");
        Console::end();
        exit;
    }
    else {
        Console::write("There is no process with PID = ".$pid.", last termination was abnormal");
        if(!unlink($pidfile)) {
            Console::write("Error unlinking PID file");
            Console::write("Try to unlink PID file manually and restart server");
            exit(-1);
        }
    }
}

//system config
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
ignore_user_abort(true);
//system config

//stdin/stdout
ini_set('error_log', $config['server']['error_log']);
fclose(STDIN);
fclose(STDOUT);
$STDIN = fopen('/dev/null', 'r');
$STDOUT = fopen($config['server']['log'], 'ab');
//stdin/stdout

$server = new Server($config['server']);
$server->start();
