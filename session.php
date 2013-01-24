<?php

if(! defined('IN_WGSL')) {
	header('HTTP/1.1 404 Not Found');
	die();
}

class Session {

	private static $instance = NULL;
	private $expire;
	private $prefix;

	public static function getInstance() {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$conf = Config::getInstance();
		$this -> expire = intval($conf('Session.Expire'));
		$this -> prefix = $conf('Session.Prefix');
		$cookie = Cookie::getInstance();
		if ($cookie('sid') !== NULL) {
			session_id($cookie('sid'));
		}
		session_cache_expire($this -> expire);
		session_start();
		$cookie('sid', session_id());
	}

	public function get($key) {
		if(isset($_SESSION[$this -> prefix . $key])) {
			return $_SESSION[$this -> prefix . $key];
		}
		return NULL;
	}

	public function set($key, $value) {
		$_SESSION[$this -> prefix . $key] = $value;
	}

	public function __get($key) {
		return $this -> get($key);
	}

	public function __set($key, $value) {
		$this -> set($key, $value);
	}

	public function __invoke($key, $value = NULL, $valueisnull = false) {
		if($value !== NULL || $valueisnull)
			$this -> set($key, $value);
		return $this -> get($key);
	}
}