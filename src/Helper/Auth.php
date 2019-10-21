<?php

namespace Webbmaffian\MVC\Helper;

use Webbmaffian\ORM\DB;
use Webbmaffian\MVC\Model\Authable;
use Exception;

class Auth {
	static public function is_signed_in() {
		return isset($_SESSION['user']);
	}
	
	
	static public function maybe_sign_out() {
		if(!static::is_signed_in()) return;

		$timeout = isset($_ENV['SESSION_TIMEOUT']) && is_numeric($_ENV['SESSION_TIMEOUT']) ? (int)$_ENV['SESSION_TIMEOUT'] : 3600;
		
		if(time() - $_SESSION['user']['last_active'] > $timeout) {
			static::sign_out();
		}
	}
	
	
	static public function sign_out() {
		if(!static::is_signed_in()) return;
		
		unset($_SESSION['user']);

		if(ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
		}

		session_destroy();
	}


	static public function start_session() {
		if(session_status() == PHP_SESSION_NONE) {
			session_start();
		}
	}
	
	
	static public function sign_in($user) {
		if(static::is_signed_in()) return;
		
		if(!$user instanceof Authable) {
			throw new Problem('Sign in object must implement interface Authable.');
		}
		
		$_SESSION['user'] = array(
			'id' => $user->get_id(),
			'name' => $user->get_name(),
			'lang' => $user->get_language(),
			'signed_in' => time(),
			'last_active' => time()
		);

		$_SESSION['notices'] = array();
		
		try {
			static::load_capabilities();
			static::load_available_customers();
		} catch(Exception $e) {
			static::sign_out();
			throw new Problem('Unable to load capabilities');
		}
	}
	
	
	static public function register_activity() {
		if(!static::is_signed_in()) return;
		
		$_SESSION['user']['last_active'] = time();
	}
	
	
	static public function get_id() {
		if(!static::is_signed_in()) return false;
		
		return (int)$_SESSION['user']['id'];
	}
	
	
	static public function get_name() {
		if(!static::is_signed_in()) return false;
		
		return $_SESSION['user']['name'];
	}
	

	static public function get_lang() {
		if(!static::is_signed_in()) return false;

		return $_SESSION['user']['lang'];
	}


	static public function set_lang($lang) {
		$_SESSION['user']['lang'] = $lang;
	}

	
	static public function get_user() {
		Helper::deprecated();

		return static::get_id();
	}
	
	
	static public function load_capabilities() {
		if(!static::is_signed_in()) return false;
		
		$db = DB::instance();
		$capabilities = $db->get_result('SELECT customer_id, capability FROM user_capabilities WHERE user_id = ?', static::get_id());
		
		$_SESSION['user']['caps'] = array();
		
		foreach($capabilities as $cap) {
			$customer_id = (int)$cap['customer_id'];
			$cap_parts = explode(':', $cap['capability']);
			
			if(!isset($cap_parts[1])) {
				$cap_parts[1] = 'general';
			}
			
			list($capability, $capability_group) = $cap_parts;
			
			if(!isset($_SESSION['user']['caps'][$customer_id])) {
				$_SESSION['user']['caps'][$customer_id] = array();
			}
			
			if(!isset($_SESSION['user']['caps'][$customer_id][$capability_group])) {
				$_SESSION['user']['caps'][$customer_id][$capability_group] = array();
			}
			
			$_SESSION['user']['caps'][$customer_id][$capability_group][$capability] = 1;
		}
	}
	
	
	static public function load_available_customers() {
		if(!static::is_signed_in() || !isset($_SESSION['user']['caps'])) return false;

		$db = DB::instance();

		if(isset($_SESSION['user']['caps'][0]['general']['do_anything'])) {
			$customers = $db->get_result('SELECT id, name FROM customers WHERE status = ? ORDER BY name', 'active');
		} elseif($customer_ids = array_filter(array_keys($_SESSION['user']['caps']))) {
			$customers = $db->get_result('SELECT id, name FROM customers WHERE id IN (' . implode(',', $customer_ids) . ') AND status = ? ORDER BY name', 'acti');
		}

		$_SESSION['user']['available_customers'] = array();
		
		if(!empty($customers)) {
			foreach($customers as $customer) {
				$_SESSION['user']['available_customers'][(int)$customer['id']] = $customer['name'];
			}
		}
		
		// Set current customer ID to the first one, if there is none set
		if(defined('ENDPOINT') && ENDPOINT === 'admin') {
			static::set_customer_id(0);
		} elseif(!empty($customers) && !static::get_customer_id()) {
			static::set_customer_id($customers[0]->get_id());
		}
	}
	
	
	// Returns array with: id => name
	static public function get_available_customers() {
		if(!static::is_signed_in() || !isset($_SESSION['user']['available_customers'])) return array();
		
		return $_SESSION['user']['available_customers'];
	}
	
	
	static public function get_customer_id() {
		if(!static::is_signed_in()) return null;
		if(!isset($_SESSION['user']['customer_id'])) return null;
		
		return (int)$_SESSION['user']['customer_id'];
	}


	static public function get_customer_name() {
		if(!isset($_SESSION['user']['customer_name'])) {
			if(!($customer_id = static::get_customer_id())) return null;

			$customer = Customer::get_by_id($customer_id);
			$_SESSION['user']['customer_name'] = $customer->get_name();
		}

		return $_SESSION['user']['customer_name'];
	}
	
	
	static public function set_customer_id($customer_id) {
		if(!static::is_signed_in()) return false;
		
		$customer_id = (int)$customer_id;
		
		if($customer_id < 0) {
			throw new Problem('Invalid customer ID provided.');
		}
		
		$_SESSION['user']['customer_name'] = null;
		$_SESSION['user']['customer_id'] = $customer_id;
	}
	
	
	static public function can($capability, $capability_group = 'general', $customer_id = null) {
		if(!static::is_signed_in()) return false;
		
		// If null, get customer ID from current session
		if(is_null($customer_id)) {
			$customer_id = static::get_customer_id();
		}
		
		// If still unset or invalid
		if(!is_numeric($customer_id)) {
			throw new Problem('No valid customer ID provided.');
		}
		
		// If the capability is set, or if the user can do anything
		$can_do = isset($_SESSION['user']['caps'][$customer_id]['general']['do_anything']) || isset($_SESSION['user']['caps'][0]['general']['do_anything']);

		if(is_array($capability)) {
			foreach($capability as $cap) {
				if(isset($_SESSION['user']['caps'][$customer_id][$capability_group][$cap])) {
					$can_do = true;
				}
			}

			return $can_do;
		}

		return isset($_SESSION['user']['caps'][$customer_id][$capability_group][$capability]) || $can_do;
	}
	
	
	static public function has_capability_group($capability_group, $customer_id = null) {
		if(!static::is_signed_in()) return false;
		
		// If null, get customer ID from current session
		if(is_null($customer_id)) {
			$customer_id = static::get_customer_id();
		}
		
		// If still unset or invalid
		if(!is_numeric($customer_id)) {
			throw new Problem('No valid customer ID provided.');
		}
		
		// If the capability is set, or if the user can do anything
		return isset($_SESSION['user']['caps'][$customer_id][$capability_group]) || isset($_SESSION['user']['caps'][$customer_id]['general']['do_anything']) || isset($_SESSION['user']['caps'][0]['general']['do_anything']);
	}
}
