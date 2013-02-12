<?php

namespace Framework;

class Cookie {

	private static $instance = NULL;
	private $expire;
	private $prefix;

	public static function getInstance() {
		if(self::$instance === NULL) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$conf = Config::getInstance();
		$this -> expire = intval($conf('Cookie.Expire'));
		if($this -> expire < 0) $this -> expire = 0;
		else $this -> expire = time() + 60 * $this -> expire;
		$this -> prefix = $conf('Cookie.Prefix');
	}

	public function get($key) {
		if(isset($_COOKIE[$this -> prefix . $key])) {
			return $_COOKIE[$this -> prefix . $key];
		}
		return NULL;
	}

	public function setExpire($expire) {
		$expire = intval($expire);
		if($expire < 0) $this -> expire = 0;
		else $this -> expire = time() + 60 * $expire;
	}

	public function set($key, $value) {
		if($value === NULL)
			setcookie($this -> prefix . $key, false, time() - 86400, '/');
		else
			setcookie($this -> prefix . $key, $value, time() + $this -> expire, '/');
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