<?php

class website {
	private function __construct() {
	}

	public static function main($request) {
		$temp = Template::getInstance();
		$temp -> assign('request', $request);
		$temp -> display('main.tpl');
		return ;
	}

	public static function add($request) {
		$display = function($request){
			$temp = Template::getInstance();
			$temp -> assign('request', $request);
			$temp -> display('add.tpl');
		};
		if(! isset($request['post']['submit'])) {
			$display($request);
			return ;
		}
		$mysql = MySQL::getInstance();
		$name = $mysql -> escape(trim($request['post']['name']));
		$length = trim($request['post']['length']);
		$reason = $mysql -> escape(trim($request['post']['reason']));
		$session = Session::getInstance();
		$lang = Language::getInstance();
		$conf = Config::getInstance();
		if(empty($name)) {
			$session('add_error', $lang('error.name_is_empty'));
			$display($request);
			return ;
		}
		if(strlen($name) > 32) {
			$session('add_error', $lang('error.name_too_long'));
			$display($request);
			return ;
		}
		if(strval(intval($length)) != $length) {
			$session('add_error', $lang('error.length_not_int'));
			$display($request);
			return ;
		}
		$length = intval($length);
		if($length <= 0) {
			$session('add_error', $lang('error.length_not_positive'));
			$display($request);
			return ;
		}
		$rcon = new RCon($conf('RCon.Host'), $conf('RCon.Port'), $conf('RCon.Password'));
		$jailname = $conf('Jail.Name');
		$rcon -> runCommand("jail $name $jailname $length");
		$mysql -> insertRow('list', array(
			'name' => $name,
			'length' => $length,
			'reason' => $reason,
			'logtime' => time(),
		));
		$session('add_error', $lang('error.success'));
		$display($request);
	}
}
