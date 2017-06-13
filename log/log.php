<?php

header('Access-Control-Allow-Origin: *');

if($_GET['f'] == 'server'){
    print file_get_contents('server_log.html');
}

if($_GET['f'] == 'error'){
    print file_get_contents('error_log.txt');
}