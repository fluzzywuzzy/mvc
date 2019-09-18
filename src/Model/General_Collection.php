<?php
	namespace Webbmaffian\MVC\Model;
	use Webbmaffian\MVC\Helper\Problem;
	use Webbmaffian\ORM\DB;

	final class General_Collection extends Model_Collection {
		protected $base_class = null;


		static public function get_table() {
			throw new Problem('Tried to get table from a static General Collection.');
		}


		static protected function format_key($key, $table = null) {
			throw new Problem('Tried to format key for a static General Collection.');
		}


		static public function db() {
			return DB::instance(Model::DB);
		}


		public function get_real_table() {
			return ($this->get_base_class())::get_table();
		}


		public function __construct($base_class) {
			$this->base_class = $base_class;
		}


		public function get_base_class() {
			return $this->base_class;
		}
	}