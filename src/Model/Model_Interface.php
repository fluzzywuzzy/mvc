<?php

namespace Webbmaffian\MVC\Model;

interface Model_Interface extends \JsonSerializable {
	static public function get_table();

	static public function get_column_names();

	static public function db();
	
	static public function get_by_id($id = 0);
	
	static public function create($data);

	static public function upsert($data, $unique_keys = null, $dont_update_keys = null);
	
	static public function get_class_name();
	
	static public function collection(): Collection_Interface;

	static public function stmt();

	public function reload();
	
	public function update($data = array());
	
	public function delete();
	
	public function get_data();
	
	public function audit();
}
