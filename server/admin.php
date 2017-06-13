<?php
header('Access-Control-Allow-Origin: *');
ini_set('display_errors', 1);
error_reporting(E_ALL); //Выводим все ошибки и предупреждения

session_start();

define( 'ABSPATH', realpath(dirname(__FILE__) . '/../') . '/' );

require(ABSPATH.'config.php');
require_once(ABSPATH.'class/Server.php');
require_once(ABSPATH.'class/DB.php');

$db = new DB($config['server']);
$db->connect();

// AUTHENTICATION
if(isset($_POST['action'])){

    if($_POST['action'] == 'register'){
        $row = $db->selectRow('mychat_users', 'client_id="'.$_POST['mychatcookid'].'"');
        if($row){
            echo "{msg:3}";
            exit();
        }

        $base64 = $_POST['image'];
        if($base64) {

            preg_match('/data\:image\/(.*)\;/', $_POST['image'], $matches); // get extention

            $filepath = ABSPATH.'upload/'.time() . '.jpg';// . $matches[1];
            $file = fopen( $filepath, "wb" );
            $data = explode(',', $_POST['image']);
            fwrite($file, base64_decode($data[1]));
            fclose($file);

        }

        if(isset($filepath)){
            $fileurl = '//' . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', $filepath);
        }
        else {
            $fileurl = '//' . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', ABSPATH.'upload/no-image.jpg');
        }

        //$client_id = uniqid();
        $result = $db->insert('mychat_users', array(
            'client_id' => $_POST['mychatcookid'],
            'name' => $_POST['name'],
            'image'=> $fileurl,
            'role' => 'admin',
            'created' => time()
        ));
        if($result) {
            echo "{msg:1}";
            exit();
        }
        else {
            echo "{msg:2}";
            exit();
        }
    }
}
// END AUTHENTICATION

if(isset($_GET['act'])) {
    $act = $_GET['act'];
}
else {
	echo "{msg:-1}";
	exit();
}

if($act == 'start') {

    exec('php -q '.ABSPATH.'server/init.php &');

	usleep(300000);
	jsonOutput();
	exit();
}
elseif($act == 'stop'){
	
	$pid = Server::getProcessStatus($config['server']['pidfile_on']);
	if($pid < 0){
        jsonOutput();
		exit();
	} 
	//создаём offfile только зная что процесс запущен, чтобы избежать глюков при следующем запуске процесса
	file_put_contents($config['server']['pidfile_off'], $pid);//СОХРАНЯЕМ PID в OFF файле

	usleep(300000);

	//Для того, чтобы полностью отключить сервер, нужно отправить ему сообщение, чтобы у него сработал read
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket < 0){

    }
	$connect = socket_connect($socket, $config['server']['host'], $config['server']['port']);
	if($connect === false) {

    }
	else {

    }

	if(isset($socket)) {
	    socket_close($socket);
    }

	usleep(500000);

    jsonOutput ();
	exit();

}
elseif($act == 'status') {

    jsonOutput ();
	exit();

}
elseif($act == 'exit') {

	unset($_SESSION['admin']);
	echo "{msg:-1}";
	exit();

}

function jsonOutput (){

    global $config;

    if( file_exists($config['server']['pidfile_on']) ) {

        $pid = file_get_contents($config['server']['pidfile_on']);

        $status = Server::getProcessInfo($pid);

        if ($status['run']) {
            echo "{color:\"green\",msg:\"[<b>" . date("Y.m.d-H:i:s") . "</b>] WebSocket server is running with PID =" . $pid . "<br />";
            echo $status['info'][0] . "<br />";
            echo $status['info'][1] . "\"}";
            return;
        }
        else {
            echo "{color:\"red\",msg:\"[<b>" . date("Y.m.d-H:i:s") . "</b>] WebSocket server is down cause abnormal reason with PID =" . $pid . "<br />\"}";
            return;
        }
    }

    echo "{color:\"grey\",msg:\"[<b>".date("Y.m.d-H:i:s")."</b>] WebSocket server is off, press start to run<br /\"}";
}