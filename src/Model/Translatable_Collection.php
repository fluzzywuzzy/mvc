<?php
	namespace Webbmaffian\MVC\Model;

	abstract class Translatable_Collection extends Model_Collection {
		protected $lang;


		public function select() {
			$fields = func_get_args();
			$translated_columns = $this->get_translated_columns();
			
			foreach($fields as $field) {
				if(isset($translated_columns[$field])) {
					$this->with_translated($field);
				}
				else {
					$this->select[] = $this->real_format_key($field);
				}
			}
			
			return $this;
		}


		public function lang($lang) {
			$this->lang = $lang;
			$this->rows = null;

			return $this;
		}


		protected function get_translated_columns() {
			return $this->get_base_class()::get_translated_columns();
		}


		protected function with_translated($column) {
			$class = $this->get_base_class();

			if(!$this->lang) {
				$this->lang = $class::DEFAULT_LANG;
			}
	
			return $this
				->select_raw(sprintf('%1$s.text AS %1$s', $column))
				->left_join($class::TRANSLATIONS_TABLE, $column, [$class::TRANSLATIONS_TABLE_ID => $column . $class::COLUMN_SUFFIX, $class::TRANSLATIONS_TABLE_LANG => [$this->lang]]);
		}


		protected function add_row($data, $class_name) {
			$model = new $class_name($data);
			
			if($this->lang) {
				$model->lang = $this->lang;
			}

			if($this->rows_key && isset($data[$this->rows_key])) {
				$this->rows[$data[$this->rows_key]] = $model;
			}
			else {
				$this->rows[] = $model;
			}
		}
	}