<?php

if(! defined('IN_WGSL')) {
	header('HTTP/1.1 404 Not Found');
	die();
}

error_reporting(0);


$request = array(
	'uri'       => $_SERVER['REQUEST_URI'],
	'referer'   => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
	'time'      => microtime(true),
);

$qString = strpos($request['uri'], '?');
if($qString !== false) {
	$request['uri'] = substr($request['uri'], 0, $qString);
}


// Load Framework Modules.

require_once(WEB_ROOT . DS . 'template.php');
require_once(WEB_ROOT . DS . 'error.php');
require_once(WEB_ROOT . DS . 'main.php');
require_once(WEB_ROOT . DS . 'config.php');
require_once(WEB_ROOT . DS . 'mysql.php');
require_once(WEB_ROOT . DS . 'session.php');
require_once(WEB_ROOT . DS . 'cookie.php');
require_once(WEB_ROOT . DS . 'lang.php');

Main::initFramework();
Main::loadRuntime();
Main::initRuntime();
