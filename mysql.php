<?php

namespace Framework;

class MySQL {

	private static $instance = NULL;
	private $cnt = 0;
	private $sql = NULL;
	private $prefix = '';

	public static function getInstance() {
		if(self::$instance === NULL) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function loaded() {
		return self::$instance !== NULL;
	}

	private function __construct() {
		$conf = Config::getInstance();
		$this -> sql = @new \mysqli($conf('MySQL.Host'), $conf('MySQL.User'), $conf('MySQL.Pass'), $conf('MySQL.Database'));
		if($this -> sql -> connect_errno) {
			throw new MySQLException('Connect Failed with message: ' . $this -> sql -> connect_error);
		}
		if(! $this -> sql -> set_charset($conf('MySQL.Charset'))) {
			throw new MySQLException('Unable to set charset to ' . $conf('MySQL.Charset'));
		}
		$this -> prefix = $conf('MySQL.Prefix');
	}

	public function escape($str) {
		return $this -> sql -> real_escape_string($str);
	}

	public function query($query) {
		// WARNING: No more security escape to query!
		++ $this -> cnt;
		$result = @$this -> sql -> query($query);
		if($result === false) {
			throw new MySQLException('Query Error with message: ' . $this -> sql -> error);
		}
		if($result === true) {
			return true;
		}
		$ret = array();
		while($res = $result -> fetch_array()) {
			$ret[] = $res;
		}
		$result -> free();
		return $ret;
	}

	public function cnt() {
		return $this -> cnt;
	}

	public function count($table, $where = '1') {
		$ret = $this -> query("SELECT COUNT(*) FROM {$this -> prefix}{$table} WHERE {$where}");
		return $ret[0][0];
	}

	public function getRows ($table, $colnames = array('*'), $where = '1') {
		if(! is_array($colnames)) {
			throw new MySQLException('Colnames must be an array!');
		}
		if(empty($colnames)) $colnames = array('*');
		$cols = implode(',', $colnames);
		return $this -> query("SELECT {$cols} FROM {$this -> prefix}{$table} WHERE {$where}");
	}

	public function getPartialRows($table, $colnames = array('*'), $where = '1', $firstrow = 0, $lastrow = 0) {
		if(! is_array($colnames)) {
			throw new MySQLException('Colnames must be an array!');
		}
		if(empty($colnames)) $colnames = array('*');
		$cols = implode(',', $colnames);
		if(!(is_int($firstrow) && is_int($lastrow))) {
			throw new MySQLException('First row and last row must be an integer!');
		}
		$cnt = $lastrow - $firstrow + 1;
		return $this -> query("SELECT {$cols} FROM {$this -> prefix}{$table} WHERE {$where} LIMIT {$firstrow}, {$cnt}");
	}

	public function getRow($table, $colnames = array('*'), $where = '1') {
		if(! is_array($colnames)) {
			throw new MySQLException('Colnames must be an array!');
		}
		if(empty($colnames)) $colnames = array('*');
		$cols = implode(',', $colnames);
		$res = $this -> query("SELECT {$cols} FROM {$this -> prefix}{$table} WHERE {$where} LIMIT 0,1");
		if(array_key_exists(0, $res))
			return $res[0];
		return NULL;
	}

	public function updateRow($table, $data, $where) {
		if(empty($data) || (! is_array($data))) {
			throw new MySQLException('Invalid data type!');
		}
		$cells = array();
		foreach($data as $key => $value) {
			if(is_int($value)) {
				$cells[] = "`$key`=$value";
			} else {
				$value = $this -> escape($value);
				$cells[] = "`$key`='$value'";
			}
		}
		$set = implode(',', $cells);
		return $this -> query("UPDATE {$this -> prefix}{$table} SET {$set} WHERE {$where} LIMIT 1");
	}

	public function insertRow($table, $data) {
		if(! is_array($data)) {
			throw new MySQLException('Data must be an array!');
		}
		$rol = array();
		$dat = array();
		foreach($data as $r => $d) {
			$rol[] = "`$r`";
			if(is_int($d)) {
				$dat[] = $d;
			} else {
				$d = $this -> escape($d);
				$dat[] = "'$d'";
			}
		}
		$rol = implode(',', $rol);
		$dat = implode(',', $dat);
		return $this -> query("INSERT INTO {$this -> prefix}{$table} ($rol) VALUES ($dat)");
	}

	public function insertRows($table, $dataarr) {
		if(! is_array(($dataarr))) throw new MySQLException('Data must be an array!');
		foreach($dataarr as $data) {
			$this -> insertRow($table, $data);
		}
		return true;
	}
}

class MySQLException extends \Exception{
}
