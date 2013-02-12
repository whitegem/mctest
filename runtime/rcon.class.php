<?php

class RCon {

	const LOGIN = 3;
	const EXECUTE = 2;

	private $socket = NULL;
	private $id = 0;
	private $ready = false;

	public function __construct($host, $port, $pass) {
		$this -> socket = fsockopen($host, $port, $errno, $errstr, 2); // Timeout = 1 sec.
		if(! $this -> socket) {
			throw new RConException("Unable to establish a connection to [$host:$port].");
		}
		$this -> id = mt_rand(0, 2147483647); // Generate a random client ID.
		$this -> Auth($pass);
	}

	public function __destruct() {
		if($this -> socket) fclose($this -> socket);
	}

	private function packInt($int) { // Generate packed signed little-endian int.
		if (! is_int($int)) return "\x00\x00\x00\x00";
		$c1 = chr($int & 255); // Low-bit
		$c2 = chr(($int >> 8) & 255);
		$c3 = chr(($int >> 16) & 255);
		$c4 = chr(($int >> 24) & 255); //High-bit
		return $c1 . $c2 . $c3 . $c4;
	}

	private function unpackInt($raw) { // Generate int from packed signed little-endian string.
		if(! is_string($raw) && strlen($raw) != 4) return 0;
		$ret = 0;
		for ($i = 0; $i != 4; ++ $i) {
			$ret |= (ord($raw[$i]) << ($i << 3));
		}
		return $ret;
	}

	private function makePacket($type, $data) {
		$content = $this -> packInt($this -> id) . $this -> packInt($type) . $data . "\x00\x00";
		$len = $this -> packInt(strlen($content));
		return $len . $content;
	}

	private function readPacket($packet) {
		if(substr($packet, -2) != "\x00\x00") {
			throw new RConException('Not a valid packet. No padding bytes found!');
		}
		$len = $this -> unpackInt(substr($packet, 0, 4));
		$packet = substr($packet, 4);
		if($len != strlen($packet)) {
			throw new RConException('Not a valid packet. Length mismatch!');
		}
		if($len < 10) {
			throw new RConException('Not a valid packet. Packet is too short!');
		}
		$packet = substr($packet, 0, -2);
		$id = $this -> unpackInt(substr($packet, 0, 4));
		$type = $this -> unpackInt(substr($packet, 4, 4));
		$data = substr($packet, 8);
		return array(
			'id' => $id,
			'type' => $type,
			'data' => $data,
		);
	}

	private function getResponse($type, $data) {
		fwrite($this -> socket, $this -> makePacket($type, $data));
		fflush($this -> socket);
		$nextPacket = fread($this -> socket, 4096);
		if ($nextPacket == '') {
			throw new RConException('Connection aborted.');
		}
		return $this -> readPacket($nextPacket);
	}

	private function Auth($pass) {
		$response = $this -> getResponse(self::LOGIN, $pass);
		if($response['id'] == -1) {
			throw new RConException('Connection refused: password not matched!');
		} elseif($response['id'] != $this -> id) {
			throw new RConException("Protocol error: server replied with unmatched client ID. Client: {$this -> id}, response: {$response['id']}");
		}
		$this -> ready = true;
	}

	public function runCommand($command) {
		if(!$this -> ready) {
			throw new RConException('Unable to run command: connection not ready.');
		}
		$response = $this -> getResponse(self::EXECUTE, $command);
		if($response['id'] != $this -> id) {
			throw new RConException("Protocol error: server replied with unmatched client ID. Client: {$this -> id}, response: {$response['id']}");
		}
		return $response['data'];
	}
}

class RConException extends Exception {
}

