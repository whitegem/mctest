<?php

namespace Framework;

class Language {

	private static $instance = NULL;

	private $isRoot = false;
	private $tree = array();
	private static $lang = NULL;
	private $callstack = array();

	private function __construct() {
		$lang = self::getLang();
		$langf = WEB_ROOT . DS . 'lang' . DS . "$lang.yml";
		if(! file_exists($langf)) {
			throw new LangException('No language file found!');
		}
		if(function_exists('yaml_parse_file'))
			$this -> tree = yaml_parse_file($langf);
		else {
			// No php_yaml extension installed. Use spyc instead.
			// Performace NOTICE: spyc is 20 times slower than php_yaml PECL extension!
			// Strongly suggested that you should install php_yaml extension!
			require_once(WEB_ROOT . DS . 'lib' . DS . 'yaml.lib.php');
			$this -> tree = \Yaml::YAMLLoad($langf);
		}
		//$this -> parse($this -> tree);
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

	private function parsed($node) {
		if (isset($this -> callstack[$node])) throw new LangException('Circular References found!');
		$this -> callstack[$node] = true;
		$val = $this -> get($node);
		$ret = $this -> parser($val);
		unset($this -> callstack[$node]);
		return $ret;
	}

	private function parseConstant($conf) {
		$matches = NULL;
		$ret = $conf;
		preg_match_all('/\\{\\?(.+)\\}/U', $conf, $matches, PREG_SET_ORDER);
		foreach($matches as $match) {
			if(! defined($match[1])) {
				throw new LangException('Constant ' . $match[1] . ' is not defined!');
			}
			$ret = str_replace($match[0], constant($match[1]), $ret);
		}
		return $ret;
	}

	public static function getLang() {
		$conf = Config::getInstance();
		if(self::$lang === NULL) {
			self::$lang = '';
			if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
				return $conf('Lang.Default');
			}
			$aclang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			$langs = explode(',', trim($aclang));
			$weight = array();
			foreach($langs as $lang) {
				if (preg_match('/(\*|[a-zA-Z]{1,8}(?:-[a-zA-Z]{1,8})?)(?:\s*;\s*q\s*=\s*(0(?:\.\d{0,3})|1(?:\.0{0,3})))?/', trim($lang), $match)) {
					if (!isset($match[2])) {
						$match[2] = '1.0';
					} else {
						$match[2] = (string) floatval($match[2]);
					}
					$weight[$match[1]] = $match[2];
				}
			}
			arsort($weight);
			foreach($weight as $lang) {
				if(file_exists(WEB_ROOT . DS . 'lang' . DS . "$lang.yml")) {
					self::$lang = $lang;
					break;
				}
			}
		}
		return self::$lang === '' ? $conf('Lang.Default') : self::$lang;
	}

	public static function getInstance() {
		if(self::$instance === NULL) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get($node) {
		if (isset($this -> callstack[$node])) throw new LangException('Circular References found!');
		$this -> callstack[$node] = true;
		if ($node == '') return $this -> tree; /* return $this -> asArray() */
		$path = explode('.', $node);
		$done = array('Root');
		$cur = $this -> tree;
		foreach($path as $nodeName) {
			if(! array_key_exists($nodeName, $cur)) {
				throw new LangException('No node named ' . $nodeName . ' in path ' . implode('.', $done));
			}
			$done[] = $nodeName;
			$cur = $cur[$nodeName];
		}
		$ret = $cur;
		unset($this -> callstack[$node]);
		return $ret;
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

class LangException extends \Exception{
}

Main::initFramework(function(){$lang = Language::getInstance(); $lang -> init();});
