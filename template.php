<?php

namespace Framework;

require_once(WEB_ROOT . DS . 'Smarty' . DS . 'Smarty.class.php');

class Template {
	private static $instance = NULL;

	private function __construct() {
	}

	public static function getInstance() {
		if(self::$instance === NULL) {
			self::$instance = new \Smarty();
			self::$instance -> left_delimiter = '{{';
			self::$instance -> right_delimiter = '}}';
			self::$instance -> debugging = false;
			$conf = Config::getInstance();
			self::$instance -> setCompileDir($conf('Template.Compiled'));
			self::$instance -> setCacheDir($conf('Template.Cached'));
			self::$instance -> setTemplateDir($conf('Template.Template'));
			self::$instance -> assign('conf', $conf -> asArray());
			self::$instance -> assign('lang', Language::getInstance() -> asArray());
			self::$instance -> assign('session', Session::getInstance());
			self::$instance -> assign('cookie', Cookie::getInstance());
			\Smarty::muteExpectedErrors();
		}
		return self::$instance;
	}
}