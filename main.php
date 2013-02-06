<?php

if(! defined('IN_WGSL')) {
	header('HTTP/1.1 404 Not Found');
	die();
}

class Main {

	private static $init = array();
	private static $framework = array();
	private static $recursive = false;
	private static $gzip = false;

	public static function initFramework($callable = NULL) {
		if($callable === NULL) {
			foreach(self::$framework as $func) {
				call_user_func($func);
			}
			return ;
		}
		if(! is_callable($callable)) {
			throw new CoreException('Framework init function is not callable!');
		}
		self::$framework[] = $callable;
	}

	public static function loadRuntime() {
		$conf = Config::getInstance();
		$files = $conf('Runtime.Require');
		try {
			self::$recursive = $conf('Runtime.Recursive');
		} catch(ConfigException $e) {
		}
		foreach($files as $file) {
			if(DS !== '/') {
				$file = str_replace('/', DS, $file);
			}
			self::loadDir($file);
		}
		return true;
	}

	private static function loadDir($dir) {
		$dir = str_replace('/', DS, $dir);
		if( is_dir($dir)) {
			if($dir[strlen($dir) - 1] != DS) {
				$dir .= DS;
			}
			$dir .= '*';
		}
		$result = glob($dir);
		foreach($result as $file) {
			if(is_file($file)) {
				require_once($file);
			} elseif(is_dir($file) && self::$recursive) {
				self::loadDir($file . DS . '*');
			}
		}
	}

	public static function initRuntime($callable = NULL) {
		if($callable === NULL) {
			foreach(self::$init as $loader) {
				$loader();
			}
			return ;
		}
		if(is_callable($callable)) {
			self::$init[] = $callable;
			return ;
		}
		throw new CoreException('Runtime init function is not callable!');
	}

	public static function run($request) {
		$conf = Config::getInstance();
		try {
			if(! file_exists(WEB_ROOT . DS . $conf('System.InstallLock'))) {
				$callable = explode('.', $conf('System.InstallScript'));
				if(! is_callable($callable)) {
					throw new CoreException('System is not installed, but install script is not callable!');
				}
				call_user_func_array($callable, array($request));
				return ;
			}
		} catch (ConfigException $e) {
		}
		try {
			$fwheader = $conf('System.Headers.FrameworkInfo');
			if($fwheader) {
				header('X-Powered-By: SimplePHPFramework');
			}
		} catch (ConfigException $e) {
		}
		try {
			$sysheader = $conf('System.Headers.SystemInfo');
			if($sysheader) {
				header('X-System-Name: ' . $conf('System.Name'));
			}
		} catch (ConfigException $e) {
		}
		$rules = $conf('URI.Rules');
		$prefix = $conf('URI.Prefix');
		if($prefix != ''){
			$length = strlen($prefix);
			if(substr($request['uri'], 0, $length) == $prefix) {
				$request['uri'] = substr($request['uri'], $length);
			}
		}
		foreach($rules as $rule) {
			$uri = str_replace('/', '\\/', $rule['Pattern']);
			if(preg_match('/^' . $uri . '$/', $request['uri'])) {
				$callable = explode('.', $rule['Backend']);
				if(is_callable($callable)) {
					call_user_func_array($callable, array($request));
					die();
				} else {
					throw new CoreException($rule['Backend'] . ' is not callable!');
				}
			}
		}
		$default = explode('.', $conf('URI.Default'));
		if(is_callable($default)) {
			call_user_func_array($default, array($request));
		} else {
			throw new CoreException('Default Method ' . $conf('URI.Default') . 'doesn\'t exist!');
		}
	}

	public static function NotMatched($request) {
		$temp = Template::getInstance();
		$temp -> assign('request', $request);
		$temp -> display('default.tpl');
	}

	public static function Redirect($location) {
		header('HTTP/1.1 302 Found');
		header('Location: '. $location);
	}

	public static function startgzip() {
		if (headers_sent()) return false;
		$encoding = $_SERVER['HTTP_ACCEPT_ENCODING'];
		if (! function_exists('gzencode')) return false;
		if ( strpos($encoding, 'x-gzip') !== false) {
			header('Content-Encoding: x-gzip');
			self::$gzip = true;
			return true;
		}
		if( strpos($encoding, 'gzip') !== false) {
			header('Content-Encoding: gzip');
			self::$gzip = true;
			return true;
		}
		return false;
	}

	private static function response($fulldata) {
		$len = strlen($fulldata);
		if(isset($_SERVER['HTTP_RANGE'])) {
			$checkint = (function($str) {
				return $str == strval(intval($str));
			});
			try{
				list($_, $range) = explode('=', $_SERVER['HTTP_RANGE']);
				list($range) = explode(',', $range);
				$range = trim($range);
				if(empty($range) || $range == '-') {
					throw new Exception();
				}
				// Check range format, in RFC2616 14.35.1
				// Try: FirstPos -
				if(strpos($range, '-') == strlen($range) - 1) {
					$startpos = substr($range, 0, -1);
					if(! $checkint($startpos)) {
						throw new Exception();
					}
					$startpos = intval($startpos);
					if($startpos >= strlen($fulldata) || $startpos < 0) {
						throw new Exception();
					}
					$lastpos = strlen($fulldata) - 1;
					header('HTTP/1.1 206 Partial Content');
					header("Content-Range: bytes $startpos-$lastpos/$len");
					header('Content-Length: ' . $lastpos - $startpos + 1);
					echo substr($fulldata, $startpos);
					return ;
				}
				if(strpos($range, '-') === 0) {
					$suffixLength = substr($range, 1);
					if(!$checkint($suffixLength)) {
						throw new Exception();
					}
					$suffixLength = intval($suffixLength);
					if($suffixLength > $len || $suffixLength < 0) {
						$suffixLength = $len;
					}
					$startpos = $len - $suffixLength;
					$lastpos = $len - 1;
					header('HTTP/1.1 206 Partial Content');
					header("Content-Range: bytes $startpos-$lastpos/$len");
					header('Content-Length: ' . $suffixLength);
					echo substr($fulldata, -$suffixLength);
					return ;
				}
				if(strpos($range, '-') === false || substr_count($range, '-') > 1) {
					throw new Exception();
				}
				list($startpos, $endpos) = explode('-', $range, 2);
				if(!($checkint($startpos) && $checkint($endpos))) {
					throw new Exception();
				}
				$startpos = intval($startpos); $endpos = intval($endpos);
				if($startpos < 0 || $startpos >= $len) {
					throw new Exception();
				}
				$endpos = min($endpos, $len - 1);
				header('HTTP/1.1 206 Partial Content');
				header("Content-Range: bytes $startpos-$endpos/$len");
				header('Content-Length: ' . $endpos - $startpos + 1);
				echo substr($fulldata, $startpos, $endpos - $startpos + 1);
			} catch (Exception $e) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header('Content-Range: bytes */' . $len);
				return ;
			}
		} else {
			header('HTTP/1.1 200 OK');
			header('Content-Length: ' . $len);
			echo $fulldata;
		}
	}

	public static function Resource($request) {
		$path = WEB_ROOT . str_replace('/', DS, $request['uri']);
		if(! (is_file($path) && is_readable($path))) {
			header('HTTP/1.1 404 Not Found');
			header('Content-Length: 0');
			return ;
		}
		$mime_types = array(
			'txt' => 'text/plain',
			'htm' => 'text/html',
			'html' => 'text/html',
			'php' => 'text/html',
			'css' => 'text/css',
			'js' => 'application/javascript',
			'json' => 'application/json',
			'xml' => 'application/xml',
			'swf' => 'application/x-shockwave-flash',
			'flv' => 'video/x-flv',
			'png' => 'image/png',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'ico' => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif' => 'image/tiff',
			'svg' => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			'zip' => 'application/zip',
			'rar' => 'application/x-rar-compressed',
			'exe' => 'application/x-msdownload',
			'msi' => 'application/x-msdownload',
			'cab' => 'application/vnd.ms-cab-compressed',
			'mp3' => 'audio/mpeg',
			'qt' => 'video/quicktime',
			'mov' => 'video/quicktime',
			'pdf' => 'application/pdf',
			'psd' => 'image/vnd.adobe.photoshop',
			'ai' => 'application/postscript',
			'eps' => 'application/postscript',
			'ps' => 'application/postscript',
			'doc' => 'application/msword',
			'rtf' => 'application/rtf',
			'xls' => 'application/vnd.ms-excel',
			'ppt' => 'application/vnd.ms-powerpoint',
			'odt' => 'application/vnd.oasis.opendocument.text',
			'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
			'bin' => 'application/octet-stream'
		);
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		$mtime = filemtime($path);
		if(!$mtime) $mtime = time(); // Force refresh the file.
		$ctime = @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if($ctime === false) $ctime = 0;
		if($ctime >= $mtime) {
			header('HTTP/1.1 304 Not Modified');
			header('Content-Length: 0');
			return ;
		}
		$conf = Config::getInstance();
		$cache = NULL;
		try {
			$cache = $conf('URI.GZip.Cache');
			$cachedPath = substr($cache, 0, -1) . str_replace(WEB_ROOT . '/static', '', $path) . '.gz';
			if(is_file($cachedPath) && is_readable($cachedPath)) {
				$cctime = filectime($cachedPath);
				if($cctime < $mtime) throw new Exception();
				if(! self::startgzip()) throw new Exception();
				header('Last-Modified: ' . date('r', $mtime));
				header('Content-Type: ' . ( array_key_exists($ext, $mime_types) ? $mime_types[$ext] : 'application/octet-stream'));
				//header('Content-Length: ' . filesize($cachedPath));
				//echo file_get_contents($cachedPath);
				self::response(file_get_contents($cachedPath));
				return ;
			}
		} catch(ConfigException $e) {
		} catch(Exception $e) {
		}
		header('Last-Modified: ' . date('r', $mtime));
		if(! ($f = fopen($path, 'rb'))) {
			header('HTTP/1.1 403 Forbidden');
			header('Content-Length: 0');
			return ;
		}
		$exts = array();
		$size = 0;
		try{
			$exts = $conf('URI.GZip.Extensions');
		} catch (ConfigException $e) {
		}
		try {
			$size = $conf('URI.GZip.Size');
		} catch (ConfigException $e) {
		}
		$willGZip = (array_search($ext, $exts) !== false);
		flock($f, LOCK_SH);
		header('Content-Type: ' . ( array_key_exists($ext, $mime_types) ? $mime_types[$ext] : 'application/octet-stream'));
		$fsize = filesize($path);
		if ($willGZip && $fsize >= $size && self::startgzip() )
			$data = gzencode(fread($f, $fsize));
		else
			$data = fread($f, $fsize);
		//header('Content-Length: ' . strlen($data));
		//echo $data;
		self::response($data);
		flock($f, LOCK_UN);
		fclose($f);
		if(function_exists('fastcgi_finish_request')) fastcgi_finish_request();
		if($cache !== NULL && self::$gzip) { // Save cached file.
			$dat = pathinfo($cachedPath);
			if(mkdir($dat['dirname'], 0777, true)); {
				file_put_contents($cachedPath, $data);
				touch($cachedPath, $mtime);
			}
		}
		return ;
	}
}

class CoreException extends Exception{
}
