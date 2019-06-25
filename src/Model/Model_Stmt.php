<?php
	namespace Webbmaffian\MVC\Model;
	use Webbmaffian\MVC\Helper\Problem;
	use Webbmaffian\ORM\DB;

	class Model_Stmt {
		protected $model_class;


		public function __construct($model_class) {
			if(!class_exists($model_class)) {
				throw new Problem('Class does not exist: ' . $model_class);
			}

			if(!is_subclass_of($model_class, __NAMESPACE__ . '\Model')) {
				throw new Problem('Class does not extend Model.');
			}

			$this->model_class = $model_class;
		}


		public function create($columns = array()) {
			$model_class = $this->model_class;

			if(!is_array($columns)) {
				throw new Problem('Input must be an array');
			}
			
			if($model_class::IS_AUTO_INCREMENT && in_array($model_class::PRIMARY_KEY, $columns)) {
				throw new Problem($this->model_class . ' ID must not be set');
			}

			return $this->db()->prepare_insert($model_class::get_table(), $columns);
		}


		public function update($columns = array(), $condition_columns = array()) {
			$model_class = $this->model_class;

			if(!is_array($columns)) {
				throw new Problem('Input must be an array');
			}
			
			if($model_class::IS_AUTO_INCREMENT && in_array($model_class::PRIMARY_KEY, $columns)) {
				throw new Problem($this->model_class . ' ID must not be set');
			}

			return $this->db()->prepare_update($model_class::get_table(), $columns, $condition_columns);
		}


		public function upsert($columns = array(), $unique_keys = array(), $dont_update_keys = array()) {
			$model_class = $this->model_class;

			if(!is_array($columns)) {
				throw new Problem('Input must be an array');
			}
			
			if($model_class::IS_AUTO_INCREMENT && in_array($model_class::PRIMARY_KEY, $columns)) {
				throw new Problem($model_class . ' ID must not be set');
			}

			$auto_increment = ($model_class::IS_AUTO_INCREMENT ? $model_class::PRIMARY_KEY : null);

			return $this->db()->prepare_upsert($model_class::get_table(), $columns, $unique_keys, $dont_update_keys, $auto_increment);
		}


		protected function db() {
			return $this->model_class::db();
		}
	}