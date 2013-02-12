<?php

namespace Framework;

class Config {

	private static $instance = NULL;

	private $callstack = array();
	private $tree = array();

	private function __construct() {
		if (function_exists('yaml_parse_file'))
			$this -> tree = yaml_parse_file(WEB_ROOT . DS . 'config.yml');
		else {
			// No php_yaml extension installed. Use spyc instead.
			// Performace NOTICE: spyc is 20 times slower than php_yaml PECL extension!
			// Strongly suggested that you should install php_yaml extension!
			require_once(WEB_ROOT . DS . 'lib' . DS . 'yaml.lib.php');
			$this -> tree = \Yaml::YAMLLoad(WEB_ROOT . DS . 'config.yml');
		}
	}

	public function init() {
		$this -> parse($this -> tree);
	}

	private function parse(&$array) {
		foreach($array as $key => $value) {
			if(is_array($value)) $this -> parse($array[$key]);
			elseif(is_string($value)) $array[$key] = $this -> parser($value);
		}
	}

	private function parsed($node) {
		if (isset($this -> callstack[$node])) throw new ConfigException('Circular References found!');
		$this -> callstack[$node] = true;
		$val = $this -> get($node);
		$ret = $this -> parser($val);
		unset($this -> callstack[$node]);
		return $ret;
	}

	private function parser($val) {
		$matches = NULL;
		$ret = $this -> parseConstant($val);
		$matches = NULL;
		preg_match_all('/\\{\\%(.+)\\}/U', $val, $matches, PREG_SET_ORDER);
		foreach($matches as $match) {
			$ret = str_replace($match[0], $this -> parsed($match[1]), $ret);
		}
		return $ret;
	}

	private function parseConstant($conf) {
		$matches = NULL;
		$ret = $conf;
		preg_match_all('/\\{\\?(.+)\\}/U', $conf, $matches, PREG_SET_ORDER);
		foreach($matches as $match) {
			if(! defined($match[1])) {
				throw new ConfigException('Constant ' . $match[1] . ' is not defined!');
			}
			$ret = str_replace($match[0], constant($match[1]), $ret);
		}
		return $ret;
	}

	public static function getInstance() {
		if(self::$instance === NULL) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get($node) {
		if ($node == '') return $this -> tree; /* return $this -> asArray() */
		$path = explode('.', $node);
		$done = array('Root');
		$cur = $this -> tree;
		foreach($path as $nodeName) {
			if(! array_key_exists($nodeName, $cur)) {
				throw new ConfigException('No node named ' . $nodeName . ' in path ' . implode('.', $done));
			}
			$done[] = $nodeName;
			$cur = $cur[$nodeName];
		}
		return $cur;
	}

	public function asArray() {
		return $this -> tree;
	}

	public function __get($node) {
		return $this -> get($node);
	}

	public function __invoke($node) {
		return $this -> get($node);
	}
}

class ConfigException extends \Exception{
}

Main::initFramework(function(){$conf = Config::getInstance(); $conf -> init();});
