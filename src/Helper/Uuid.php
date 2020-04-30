<?php

/*
	Credit to https://github.com/oittaa/uuid-php/blob/master/uuid.php
*/

namespace Webbmaffian\MVC\Helper;

class Uuid {
	static private function uuid_from_hash($hash, $version) {
		return sprintf(
			'%08s-%04s-%04x-%04x-%12s',
			substr($hash, 0, 8), // 32 bits for "time_low"
			substr($hash, 8, 4), // 16 bits for "time_mid"
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | $version << 12, // 16 bits for "time_hi_and_version", four most significant bits holds version number
			(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000, // 16 bits, 8 bits for "clk_seq_hi_res", 8 bits for "clk_seq_low", two most significant bits holds zero and one for variant DCE1.1
			substr($hash, 20, 12) // 48 bits for "node"
		);
	}


	static public function is_valid($uuid) {
		return preg_match('/^(urn:)?(uuid:)?(\{)?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}(?(3)\}|)$/i', $uuid) === 1;
	}
	

	static public function equals($uuid1, $uuid2) {
		return self::get_bytes($uuid1) === self::get_bytes($uuid2);
	}
	

	static public function get() {
		return self::v4();
	}
	

	static public function v4() {
		$bytes = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
		$hash = bin2hex($bytes);
		return self::uuid_from_hash($hash, 4);
	}


	static public function v3($namespace, $name) {
		$hash = md5(self::get_bytes($namespace) . $name);

		return self::uuid_from_hash($hash, 3);
	}
	

	static private function get_bytes($uuid) {
		if(!self::is_valid($uuid)) {
			throw new Problem('Invalid UUID string: ' . $uuid);
		}
		
		// Get hexadecimal components of UUID
		$uhex = str_replace(array(
			'urn:',
			'uuid:',
			'-',
			'{',
			'}'
		), '', $uuid);

		// Binary Value
		$ustr = '';

		// Convert UUID to bits
		for($i = 0; $i < strlen($uhex); $i += 2) {
			$ustr .= chr(hexdec($uhex[$i] . $uhex[$i + 1]));
		}
		
		return $ustr;
	}
}