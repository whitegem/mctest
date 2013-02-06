<?php

class User {

	private static $instance = NULL;
	private $uid = -1;
	private $name = '';

	private function __construct() {
		$cookie = Cookie::getInstance();
		$session = Session::getInstance();
		if ($session -> login) {
			$this -> uid = $session -> uid;
			$this -> name = $session -> name;
			return ;
		}
		if ($cookie -> login) {
			if($cookie -> uid === NULL) {
				$cookie -> login = null;
				return ;
			}
			$id = intval($cookie -> uid);
			$mysql = MySQL::getInstance();
			$dat = $mysql -> getRow('user', array('*'), 'uid=' . $id);
			if($dat === null) return ;
			$ak = $cookie -> login;
			$sk = $dat['login'];
			if(! Hashing::authKeypair($ak, $sk)) { // Auth Failed.
				$cookie -> login = null;
				return ;
			}
			$this -> uid = $dat['uid'];
			$this -> name = $dat['name'];
			$cookie -> login = $ak;
		}
	}

	public static function getInstance() {
		if(self::$instance === NULL) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function login($user, $pass, $rememberme = true) {
		if($this -> uid !== -1) throw new UserException('error.alreadyLogin');
		$mysql = MySQL::getInstance();
		$user = $mysql -> escape($user);
		$dat = $mysql -> getRow('user', array('*'), "name='$user'");
		if($dat === null) throw new UserException('error.noUserFound');
		$hashed = $dat['pass'];
		if(Hashing::authPassword($pass, $hashed)) { // Login success.
			$cookie = Cookie::getInstance();
			$session = Session::getInstance();
			$this -> uid = $dat['uid'];
			$this -> name = $dat['name'];
			list($ak, $sk) = Hashing::generateKeypair();
			$mysql -> updateRow('user', array(
				'lastlogin' => time(),
				'login'     => $ak,
			), 'uid=' . $dat['uid']);
			if(!$rememberme) $cookie -> setExpire(-1);
			$cookie -> login = $sk;
			$cookie -> uid = $this -> uid;
			$session -> login = 1;
			$session -> uid = $this -> uid;
			$session -> name = $this -> name;
			return ;
		}
		throw new UserException('error.LoginFailed');
	}

	public function logout() {
		if($this -> uid === -1) throw new UserException('error.notLogin');
		$this -> uid = -1;
		$this -> name = '';
		$session = Session::getInstance();
		$session -> reset();
		$cookie = Cookie::getInstance();
		$cookie -> login = null;
		$cookie -> uid = null;
	}

	public function name() {
		return $this -> name;
	}

	public function uid() {
		return $this -> uid;
	}

}

class UserException extends Exception{
}