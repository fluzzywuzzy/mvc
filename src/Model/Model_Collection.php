<?php

namespace Webbmaffian\MVC\Model;

use \Webbmaffian\MVC\Helper\Problem;

abstract class Model_Collection implements Collection_Interface {
	const TABLE = '';
	
	protected $select = array();
	protected $join = array();
	protected $where = array();
	protected $where_raw = array();
	protected $having = array();
	protected $group_by = array();
	protected $limit = 0;
	protected $offset = 0;
	protected $order_by = array();
	
	protected $results = array();
	protected $rows = null;
	protected $rows_key = null;
	protected $taken_table_names = array();

	
	static public function get_table() {
		return (static::get_model_class())::get_table();
	}


	public function get_real_table() {
		return static::get_table();
	}


	static public function db() {
		$class = static::get_model_class();
		return $class::db();
	}


	public function real_db() {
		return ($this->get_base_class())::db();
	}


	static public function get_model_class() {
		return trim(str_replace('Collection', '', get_called_class()), '_');
	}


	public function get_base_class() {
		return trim(str_replace('Collection', '', get_class($this)), '_');
	}


	public function select() {
		$fields = func_get_args();
		
		foreach($fields as $field) {
			$this->select[] = $this->real_format_key($field);
		}
		
		return $this;
	}
	
	
	public function select_raw() {
		$fields = func_get_args();
		
		foreach($fields as $field) {
			$this->select[] = self::format_raw_value($field);
		}
		
		return $this;
	}


	public function reset_select() {
		$this->select = array();

		return $this;
	}
	
	
	public function join($join) {
		$this->join_raw(preg_replace_callback('/(\=\s*([a-z][^\s\.]*)(\z|\s))|(\s([a-z][^\s\.]*)\s*\=)/i', function($matches) {
			$space = (!empty($matches[3]) ? $matches[3] : ' ');

			return sprintf(($matches[0][0] === '=' ? '= %1$s%2$s' : '%2$s%1$s ='), $this->real_format_key(isset($matches[5]) ? $matches[5] : $matches[2]), $space);
		}, $join));
		
		return $this;
	}


	/*
	 * Usage:
	 * 
	 * left_join('my_table', 'my_optional_alias', ['my_column' => 'someone_elses_column'])
	 * left_join('my_table', 'my_optional_alias', ['my_column' => ['a string', 'or more']])
	 */
	public function left_join() {
		$args = func_get_args();
		$num_args = count($args);

		// With alias
		if($num_args === 3) {
			list($table, $alias, $condition) = $args;
		}

		// Without alias
		elseif($num_args === 2) {
			list($table, $condition) = $args;

			$alias = false;
		}

		else {
			throw new Problem('Invalid number of arguments.');
		}

		if($alias) {
			if(isset($this->taken_table_names[$alias])) return $this;
		}
		else {
			if(isset($this->taken_table_names[$table])) return $this;
		}

		if(!is_array($condition) || empty($condition)) {
			throw new Problem('Condition must be an array.');
		}

		$on = array();

		foreach($condition as $joined_column => $column) {
			if(is_array($column)) {
				if(count($column) === 1) {
					$column = reset($column);
				}
				
				$on[] = $this->real_format_key($joined_column, $alias ?: $table) . ' ' . (is_array($column) ? 'IN' : '=') . ' ' . static::format_value($column);
			}
			else {
				$on[] = $this->real_format_key($joined_column, $alias ?: $table) . ' = ' . $this->real_format_key($column);
			}
		}

		if($alias) {
			$table .= ' AS ' . $alias;
			$this->taken_table_names[$alias] = 1;
		}
		else {
			$this->taken_table_names[$table] = 1;
		}

		$this->join_raw('LEFT JOIN ' . $table . ' ON ' . implode(' AND ', $on));

		return $this;
	}


	public function join_raw($join) {
		$this->join[] = $join;

		return $this;
	}
	
	
	public function where() {
		$args = func_get_args();
		
		$this->where[] = $this->get_where($args);

		return $this;
	}


	// This function takes any number of arrays as OR condition
	public function where_any() {
		$arrays = func_get_args();
		$ors = array();

		foreach($arrays as $args) {
			if(!is_array($args)) {
				throw new Problem('All arguments must be arrays.');
			}

			$ors[] = $this->get_where($args);
		}

		$this->where[] = '(' . implode(' OR ', $ors) . ')';

		return $this;
	}


	protected function get_where($args = array(), $format_key = true) {
		$num_args = sizeof($args);

		$a = $args[0];

		if(isset($args[2])) {
			$b = self::format_value($args[2]);
			$comparison = $args[1];
		}
		elseif(isset($args[1]) || is_null($args[1])) {
			$b = self::format_value($args[1]);
			$comparison = (is_array($args[1]) ? 'IN' : '=');

			if(is_array($args[1]) && empty($args[1])) {
				throw new Problem('Empty array sent to where().');
			}
		}
		else {
			throw new Problem('Invalid number of arguments.');
		}

		if($b === 'NULL') {
			if($comparison === '=') $comparison = 'IS';
			elseif($comparison === '!=') $comparison = 'IS NOT';
		}

		return ($format_key ? $this->real_format_key($a) : $a) . ' ' . $comparison . ' ' . $b;
	}


	public function having() {
		$args = func_get_args();
		
		$this->having[] = $this->get_where($args, false);

		return $this;
	}


	public function where_raw() {
		$fields = func_get_args();
		foreach($fields as $field) {
			$this->where_raw[] = self::format_raw_value($field);
		}
		return $this;
	}
	
	
	public function where_match($match, $against) {
		$this->where[] = 'MATCH(' . $match . ') AGAINST("' . $against . '" IN BOOLEAN MODE)';
		
		return $this;
	}
	
	
	public function group_by($group_by) {
		$this->group_by[] = $this->real_format_key($group_by);
		
		return $this;
	}
	
	
	public function order_by($order_by, $order = 'ASC') {
		$this->order_by[] = $order_by . ' ' . $order;
		
		return $this;
	}
	
	
	public function order_by_relevance($match, $against) {
		$this->order_by[] = '(MATCH(' . $match . ') AGAINST("' . $against . '" IN BOOLEAN MODE)) DESC';
		
		return $this;
	}


	public function order_by_custom($column, $values = array()) {
		$values = self::format_value($values, true);

		array_unshift($values, self::format_key($column));

		$this->order_by[] = 'FIELD(' . implode(', ', $values) . ')';

		return $this;
	}
	
	
	public function limit($limit) {
		$this->limit = $limit;
		
		return $this;
	}


	public function offset($offset) {
		$this->offset = $offset;
		
		return $this;
	}
	
	
	public function set_rows_key($key) {
		$this->rows_key = $key;
		
		return $this;
	}
	
	
	public function get() {
		if(is_null($this->rows)) {
			$this->rows = array();
			$this->run();
			$class_name = $this->get_base_class();
			
			foreach($this->results as $data) {
				$this->add_row($data, $class_name);
			}
			
			$this->results = null;
		}
		
		return $this->rows;
	}


	protected function add_row($data, $class_name) {
		if($this->rows_key && isset($data[$this->rows_key])) {
			$this->rows[$data[$this->rows_key]] = new $class_name($data);
		}
		else {
			$this->rows[] = new $class_name($data);
		}
	}


	public function get_single() {
		$this->limit(1)->get();

		return reset($this->rows);
	}
	
	
	public function num_rows() {
		return (is_array($this->rows) ? sizeof($this->rows) : 0);
	}


	protected function get_query_parts() {
		$q = array();
		
		// Ensure we have no duplications
		$this->select = array_unique($this->select);
		$this->join = array_unique($this->join);
		$this->where = array_unique($this->where);
		$this->where_raw = array_unique($this->where_raw);
		$this->group_by = array_unique($this->group_by);
		$this->having = array_unique($this->having);
		
		if(!empty($this->select)) {
			$q['select'] = 'SELECT ' . implode(', ', $this->select);
		}

		else {
			$q['select'] = 'SELECT ' . $this->get_real_table() . '.*';
		}
		
		$q['from'] = 'FROM ' . $this->get_real_table();
		
		if(!empty($this->join)) {
			$q['join'] = implode("\n", $this->join);
		}
		
		if(!empty($this->where)) {
			$q['where'] = 'WHERE ' . implode(' AND ', $this->where);
		}

		if(!empty($this->where_raw)) {
			if(empty($q['where'])) {
				$q['where'] = 'WHERE ' . implode(' AND ', $this->where_raw);
			} else {
				$q['where'] .= ' AND (' . implode(') AND (', $this->where_raw) . ')';
			}
		}
		
		if(!empty($this->group_by)) {
			$q['group_by'] = 'GROUP BY ' . implode(', ', $this->group_by);
		}

		if(!empty($this->having)) {
			$q['having'] = 'HAVING ' . implode(' AND ', $this->having);
		}
		
		if(!empty($this->order_by)) {
			$q['order_by'] = 'ORDER BY ' . implode(', ', $this->order_by);
		}
		
		if($this->limit) {
			$q['limit'] = 'LIMIT ' . $this->limit;
		}

		if($this->offset) {
			$q['offset'] = 'OFFSET ' . $this->offset;
		}

		return $q;
	}
	
	
	public function get_query() {
		return implode("\n", $this->get_query_parts());
	}


	public function get_count($column = '*') {
		// Reset everything but joins and conditions
		$this->select = array();
		$this->order_by = array();
		$this->group_by = array();
		$this->rows = array();
		$this->having = array();
		$this->limit = 0;
		$this->offset = 0;

		$this->select_raw('COUNT(' . $column . ') AS count');
		$this->run();

		return isset($this->results[0]['count']) ? $this->results[0]['count'] : false;
	}


	public function get_column() {
		return $this->db()->get_column($this->get_query());
	}
	
	
	protected function run() {
		$db = $this->db();

		if(!$this->results = $db->query($this->get_query())->fetch_all()) {
			$this->results = array();
		}
	}
	
	
	static protected function format_key($key, $table = null) {
		if(strpos($key, '.') === false) {
			if(is_null($table)) {
				$table = self::get_table();
			}
			
			$key = $table . '.' . $key;
		}
		
		return $key;
	}


	protected function real_format_key($key, $table = null) {
		if(strpos($key, '.') === false) {
			if(is_null($table)) {
				$table = $this->get_real_table();
			}
			
			$key = $table . '.' . $key;
		}
		
		return $key;
	}


	static protected function format_value($value, $keep_arrays = false) {
		if($value instanceof \DateTime) {
			$value = $value->format('Y-m-d H:i:s');
		}

		if($value === 'null') {
			$value = null;
		}
		elseif(is_string($value)) {
			return static::db()->escape_string($value, true);
		}
		elseif(is_bool($value)) {
			return (int)$value;
		}
		elseif(is_array($value)) {
			$value = array_map([__CLASS__, 'format_value'], $value);

			return ($keep_arrays ? $value : ('(' . implode(', ', $value) . ')'));
		}

		if(is_null($value)) {
			return 'NULL';
		}

		return $value;
	}


	static protected function format_raw_value($value) {
		return str_replace('"', '\'', $value);
	}


	// Interface for json_encode()
	public function jsonSerialize(): mixed {
		return $this->get();
	}


	// Interface for count()
	public function count(): int {

		// We don't want to run any query here, so we use $this->rows directly
		// instead of $this->get().
		return count($this->rows);
	}


	public function get_prop($prop) {
		if(isset($this->{$prop})) {
			return $this->{$prop};
		}

		return null;
	}
}
