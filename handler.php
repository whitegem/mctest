<?php

define('DS', DIRECTORY_SEPARATOR);
define('WEB_ROOT', dirname(__FILE__));

require_once(WEB_ROOT . DS . 'init.php');

Framework\Main::run($request);