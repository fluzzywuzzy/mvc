<?php

namespace Webbmaffian\MVC\Helper;

class Format {
	static public function key_number($key_number) {
		return implode(' ', str_split((string)$key_number, 4));
	}
	
	
	static public function meta_key($meta_key) {
		return ucwords(str_replace('_', ' ', $meta_key));
	}
	
	
	static public function price($price, $with_thousand_separator = false, $decimals = 2) {
		return number_format((int)$price / 100, $decimals, ',', ($with_thousand_separator ? ' ' : ''));
	}
	
	
	static public function url($path, $params = array()) {
		if(empty($params)) return $path;
		
		return $path . '?' . http_build_query($params);
	}
	
	
	static public function encode($code) {
		return addslashes(htmlentities($code, ENT_NOQUOTES | ENT_HTML5));
	}


	static public function decode($code, $leave_slashes = false) {
		if($leave_slashes) {
			return html_entity_decode($code, ENT_NOQUOTES | ENT_HTML5);
		}

		return stripslashes(html_entity_decode($code, ENT_QUOTES | ENT_HTML5));
	}
}
