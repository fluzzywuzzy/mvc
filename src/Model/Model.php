<?php

namespace Webbmaffian\MVC\Model;

use Webbmaffian\MVC\Helper\Problem;
use Webbmaffian\ORM\DB;
use Webbmaffian\MVC\Helper\Audit_Log;

abstract class Model implements \JsonSerializable {
	const DB = 'app';
	const TABLE = '';
	const PRIMARY_KEY = 'id';
	const IS_PRIMARY_NUMERIC = true;
	const IS_AUTO_INCREMENT = true;
	
	// Hidden columns should be defined as an assoc. array: column_name => boolean
	const HIDDEN_COLUMNS = array();

	protected $data;

	
	static public function get_table() {
		return static::TABLE;
	}


	static public function get_column_names() {
		return static::db()->get_column_names(static::get_table());
	}


	static public function db() {
		return DB::instance(static::DB);
	}

	
	static public function get_by_id($id = 0) {
		$data = self::get_model_data($id);
		
		return new static($data);
	}


	static protected function get_model_data($id) {
		if(static::IS_PRIMARY_NUMERIC && !is_numeric($id)) {
			throw new Problem(get_called_class() . ' ID must be numeric');
		}
		
		$db = self::db();
		
		$data = $db->get_row('SELECT * FROM ' . static::get_table() . ' WHERE ' . static::PRIMARY_KEY . ' = ?', $id);
		
		if(!$data) {
			throw new Problem('Could not find ' . get_called_class() . ' with ID ' . $id);
		}

		return $data;
	}
	
	
	static public function create($data) {
		if(!is_array($data)) {
			throw new Problem('Input must be an array');
		}
		
		if(static::IS_AUTO_INCREMENT && isset($data[static::PRIMARY_KEY])) {
			throw new Problem(get_called_class() . ' ID must not be set');
		}
		
		$db = self::db();
		$data = static::alter_data($data);
		
		if(!$db->insert(static::get_table(), $data)) {
			throw new Problem(get_called_class() . ' could not be created.');
		}
		if($id = $db->get_last_id()) $data[static::PRIMARY_KEY] = $id;
		
		return new static($data);
	}


	static public function create_update($data, $unique_keys = array(), $dont_update_keys = array()) {
		if(!is_array($data)) {
			throw new Problem('Input must be an array');
		}
		
		if(static::IS_AUTO_INCREMENT && isset($data[static::PRIMARY_KEY])) {
			throw new Problem(get_called_class() . ' ID must not be set');
		}

		$db = self::db();
		$data = static::alter_data($data);
		$result = $db->insert_update(self::get_table(), $data, $unique_keys, $dont_update_keys, static::PRIMARY_KEY);
		
		if(!$result) {
			throw new Problem(get_called_class() . ' could not be created.');
		}

		if($id = $result->fetch_value()) $data[static::PRIMARY_KEY] = $id;
		
		return new static($data);
	}


	static protected function alter_data($data, $id = null, $current_data = null) {
		return array_map(function($value) {
			if($value instanceof \DateTime) {
				return $value->format('Y-m-d H:i:s');
			}
			
			return $value;
		}, $data);
	}
	
	
	static public function get_class_name() {
		return (new \ReflectionClass(get_called_class()))->getShortName();
	}
	
	
	static public function collection() {
		$class_name = get_called_class() . '_Collection';
		
		if(class_exists($class_name)) {
			return new $class_name;
		}

		return ($class_name instanceof Translatable ? new General_Translatable_Collection(get_called_class()) : new General_Collection(get_called_class()));
	}


	static public function stmt() {
		return new Model_Stmt(get_called_class());
	}
	
	
	public function __construct($data = array()) {
		if(!is_array($data)) {
			throw new Problem('Input must be an array');
		}
		
		if(!isset($data[static::PRIMARY_KEY])) {
			throw new Problem(get_class($this) . ' ID must be set');
		}
		
		if(static::IS_PRIMARY_NUMERIC && !is_numeric($data[static::PRIMARY_KEY])) {
			throw new Problem(get_class($this) . ' ID must be numeric');
		}
		
		$this->data = $data;
	}
	
	
	public function __call($name, $args = array()) {
		if(strpos($name, '_') === false) return;
		
		list($type, $name) = explode('_', $name, 2);
		
		if($type === 'get') {
			return $this->data[$name];
		}
		elseif($type === 'has') {
			return !empty($this->data[$name]);
		}
	}


	public function reload() {
		$this->data = self::get_model_data($this->data[static::PRIMARY_KEY]);
	}

	
	public function update($data = array()) {
		$db = self::db();
		
		$this->data = array_merge($this->data, $data);
		
		return $db->update(static::get_table(), $data, array(
			static::PRIMARY_KEY => $this->data[static::PRIMARY_KEY]
		));
	}


	// Shorthand
	public function put($key, $value) {
		return $this->update([$key => $value]);
	}
	
	
	public function delete() {
		$db = self::db();
		
		return $db->delete(static::get_table(), array(
			static::PRIMARY_KEY => $this->data[static::PRIMARY_KEY]
		));
	}
	
	
	public function get_data() {
		return array_diff_key($this->data, static::HIDDEN_COLUMNS);
	}
	
	
	public function jsonSerialize() {
		return $this->get_data();
	}


	public function audit() {
		$args = func_get_args();
		
		if(empty($args)) {
			throw new Problem('Missing audit note.');
		}
		
		$note = array_shift($args);
		
		if(!empty($args)) {
			$note = vsprintf($note, $args);
		}
		
		return Audit_Log::add($note, static::get_class_name(), $this->data[static::PRIMARY_KEY]);
	}


	public function set($key, $value) {
		$this->data[$key] = $value;
	}
}
