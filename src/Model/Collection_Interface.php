<?php

namespace Webbmaffian\MVC\Model;

interface Collection_Interface extends \JsonSerializable, \Countable {
	static public function get_table();

	public function get_real_table();

	static public function db();

	public function real_db();

	static public function get_model_class();

	public function get_base_class();

	public function select();
	
	public function select_raw();

	public function reset_select();
	
	public function join($join);

	public function left_join();

	public function join_raw($join);
	
	public function where();

	public function where_any();

	public function having();

	public function where_raw();
	
	public function where_match($match, $against);
	
	public function group_by($group_by);
	
	public function order_by($order_by, $order = 'ASC');
	
	public function order_by_relevance($match, $against);

	public function order_by_custom($column, $values = array());
	
	public function limit($limit);

	public function offset($offset);
	
	public function set_rows_key($key);
	
	public function get();

	public function get_single();
	
	public function num_rows();
	
	public function get_query();

	public function get_count($column = '*');

	public function get_column();

	public function get_prop($prop);
}