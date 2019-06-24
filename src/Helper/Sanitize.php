<?php

namespace Webbmaffian\MVC\Helper;
use Webbmaffian\ORM\DB;

class Sanitize {

	static public function mime_type($mime_type) {
		if(preg_match('/^([a-z0-9-\/]+)/', strtolower(str_replace(' ', '', $mime_type)), $matches)) {
			return $matches[1];
		}
		
		return false;
	}
	
	
	static public function key($key) {
		$key = str_replace(' ', '_', trim(strtolower($key)));
		
		return preg_replace('/[^a-z0-9_]+/', '', $key);
	}


	static public function slug($text, $separator = '-', $reset = true) {
		if($reset) {
			$text = str_replace(['_', ' '], '-', $text);
		}

		if(function_exists('transliterator_transliterate')) {
			return str_replace(' ', $separator, transliterator_transliterate("Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();", trim($text)));
		}

		return preg_replace('/[^a-z0-9-]+/', $separator, trim(strtolower($text)));
	}
	
	
	static public function email($email = '') {
		$email = strtolower(trim($email));
		
		if(!preg_match('/^[^\s,]+\@[^\s,]+\.[^\s,]+$/', $email)) {
			return '';
		}
		
		return $email;
	}


	static public function name($name = '') {
		$name = filter_var($name, FILTER_SANITIZE_STRING);
		$name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

		return $name;
	}


	static public function text($text) {
		return filter_var($text, FILTER_SANITIZE_STRING);
	}
	
	
	static public function phone($phone) {
		$phone = preg_replace('/[^\d\+]+/', '', (string)$phone);

		if($phone[0] !== '+') {
			if(strncmp($phone, '00', 2) === 0) {
				$phone = substr($phone, 2);
			}
			else {
				$phone = '46' . ltrim($phone, '0');
			}

			$phone = '+' . $phone;
		}

		return $phone;
	}
	
	
	static public function ssn($ssn, $with_dash = true, $short = false) {
		$ssn = preg_replace('/[\D]+/', '', (string)$ssn);

		if($short) {
			if(strlen($ssn) === 12) {
				$ssn = substr($ssn, 2);
			}
			elseif(strlen($ssn) !== 10) {
				throw new Problem('Invalid SSN.');
			}
		}
		else {
			if(strlen($ssn) === 10) {
				$current_year = (string)date('Y');
				$current_century = substr($current_year, 0, 2);
				$ssn_year = (string)substr($ssn, 0, 2);

				$maybe_year = intval($current_century . $ssn_year);

				if((int)$current_year - $maybe_year < 18) {
					$maybe_year -= 100;
				}

				$ssn = $maybe_year . substr($ssn, 2);
			}
			elseif(strlen($ssn) !== 12) {
				throw new Problem('Invalid SSN.');
			}
		}
		
		if($with_dash) {
			return implode('-', str_split($ssn, ($short ? 6 : 8)));
		}
		else {
			return $ssn;
		}
	}
	
	
	static public function zipcode($zipcode) {
		$zipcode = preg_replace('/[\D]+/', '', (string)$zipcode);
		
		if(strlen($zipcode) !== 5) {
			return '';
		}
		
		return $zipcode;
	}
	
	
	static public function price($price) {
		if(is_string($price)) {
			$price = (float)str_replace(',', '.', trim((string)$price));
		}
		
		// Price in cents
		return round($price * 100);
	}


	static public function date($date, $format = 'Y-m-d') {
		$date = \DateTime::createFromFormat($format, $date, Helper::get_timezone());
		if($date && \DateTime::getLastErrors()['warning_count'] == 0 && \DateTime::getLastErrors()["error_count"] == 0) {
			return $date->format($format);
		} else {
			return '';
		}
	}
	
	
	static public function order($order) {
		$order = strtolower($order);
		
		return ($order === 'desc' ? 'desc' : 'asc');
	}


	static public function string($string, $add_quotes = true) {
		return DB::instance()->escape_string($string, $add_quotes);
	}


	static public function integer($int) {
		return (int)preg_replace('/[^\d\.]+/', '', (string)$int);
	}


	static public function float($float) {
		return (float)preg_replace('/[^\d\.]+/', '', str_replace(',', '.', (string)$float));
	}
}