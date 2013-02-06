<?php

class Hashing {

	private function __construct() {
	}

	public static function generateKeypair($algo = 'tiger128,3') {
		$ak = substr(base64_encode(md5(uniqid(), true)), 0, -2); // Remove padding character (=).
		$sk = hash($algo, $ak . hash($algo, $ak));
		return array(
			'ak' => $ak,
			'sk' => $sk
		);
	}

	public static function authKeypair($ak, $sk, $algo = 'tiger128,3') {
		return $sk == hash($algo, $ak . hash($algo, $ak));
	}

	public static function generatePassword($raw, $algo = 'tiger192,4') {
		$salt = substr(base64_encode(md5(uniqid(), true)), 6, 15); // Salt length = 15
		$hashed = base64_encode(hash($algo, hash($algo, $salt) . $raw, true)); // Hashed length = 32
		return $salt . '|' . $hashed; // Total length = 48
	}

	public static function authPassword($raw, $hashed, $algo = 'tiger192,4') {
		list($salt, $hashed) = explode('|', $hashed);
		$regen = base64_encode(hash($algo, hash($algo, $salt) . $raw, true));
		return $regen == $hashed;
	}
}