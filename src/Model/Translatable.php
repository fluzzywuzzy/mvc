<?php
	namespace Webbmaffian\MVC\Model;

	abstract class Translatable extends Model {
		const DEFAULT_LANG = 'sv';
		const TRANSLATIONS_TABLE = 'translation_strings';
		const TRANSLATIONS_TABLE_ID = 'translation_id';
		const TRANSLATIONS_TABLE_LANG = 'lang';
		const TRANSLATIONS_TABLE_TEXT = 'text';
		const COLUMN_SUFFIX = '_trans_id';

		public $lang;


		static public function create($data) {
			return static::update_default_translation($data, function($data) {
				return parent::create($data);
			});
		}


		static public function create_update($data, $unique_keys = array(), $dont_update_keys = array()) {
			return static::update_default_translation($data, function($data) use ($unique_keys, $dont_update_keys) {
				return parent::create_update($data, $unique_keys, $dont_update_keys);
			});
		}


		public function update($data = array()) {
			return static::update_default_translation($data, function($data) {
				return parent::update($data);
			});
		}


		public function delete() {
			$db = self::db();
			
			foreach(static::get_translated_columns() as $column) {
				$db->delete(static::TRANSLATIONS_TABLE, array(
					static::TRANSLATIONS_TABLE_ID => $this->data[$column]
				));
			}

			return parent::delete();
		}


		public function __call($name, $args = array()) {
			list($type, $key) = explode('_', $name, 2);

			if(!$key) return;

			$translated_columns = static::get_translated_columns();

			// If the requested data is translatable
			if(isset($translated_columns[$key]) && isset($this->data[$translated_columns[$key]])) {
				$lang = (isset($args[0]) ? $args[0] : null);

				// If the requested data already exists in the correct language
				if(isset($this->data[$key]) && (!$lang || $lang === $this->lang)) {
					$value = $this->data[$key];
				}
				else {
					$value = static::get_translated_string($this->data[$translated_columns[$key]], $lang ?: static::DEFAULT_LANG);
				}

				return ($type === 'get' ? $value : !empty($value));
			}

			return parent::__call($name, $args);
		}


		public function put($key, $value) {
			static $update, $delete;

			$translated_columns = static::get_translated_columns();

			if(!isset($translated_columns[$key])) {
				return parent::put($key, $value);
			}

			$args = func_get_args();
			$lang = (isset($args[2]) ? $args[2] : static::DEFAULT_LANG);

			// Update
			if($value) {
				if(!$update) {
					$update = static::db()->prepare(sprintf(
						'INSERT INTO %1$s (%2$s, %3$s, %4$s) VALUES(:translation_id, :lang, :text) ON DUPLICATE KEY UPDATE %4$s = VALUES(%4$s)',
						static::TRANSLATIONS_TABLE, static::TRANSLATIONS_TABLE_ID, static::TRANSLATIONS_TABLE_LANG, static::TRANSLATIONS_TABLE_TEXT
					));
				}

				if(!$this->data[$translated_columns[$key]]) {
					$this->put($translated_columns[$key], static::next_translation_id());
				}

				$update->execute(array(
					'translation_id' => $this->data[$translated_columns[$key]],
					'lang' => $lang,
					'text' => $value
				));
			}

			// Delete
			else {
				if(!$delete) {
					$delete = static::db()->prepare(sprintf(
						'DELETE FROM %1$s WHERE %2$s = :translation_id AND %3$s = :lang',
						static::TRANSLATIONS_TABLE, static::TRANSLATIONS_TABLE_ID, static::TRANSLATIONS_TABLE_LANG
					));
				}

				$update->execute(array(
					'translation_id' => $this->data[$translated_columns[$key]],
					'lang' => $lang
				));
			}
		}


		static public function get_translated_columns() {
			static $cache;

			if(is_null($cache)) {
				$cache = array();
			}

			$table = static::get_table();

			if(!isset($cache[$table])) {
				$cache[$table] = array();
				$columns = static::get_column_names();
				$compare_pos = -strlen(static::COLUMN_SUFFIX);

				foreach($columns as $column) {
					if(substr_compare($column, static::COLUMN_SUFFIX, $compare_pos) === 0) {
						$cache[$table][substr($column, 0, $compare_pos)] = $column;
					}
				}
			}

			return $cache[$table];
		}


		static protected function get_translated_string($translation_id, $lang) {
			static $stmt;

			if(!$stmt) {
				$stmt = static::db()->prepare(sprintf('SELECT %4$s FROM %1$s WHERE %2$s = :translation_id AND %3$s = :lang', static::TRANSLATIONS_TABLE, static::TRANSLATIONS_TABLE_ID, static::TRANSLATIONS_TABLE_LANG, static::TRANSLATIONS_TABLE_TEXT));
			}

			return $stmt->execute(['translation_id' => $translation_id, 'lang' => $lang])->fetch_value();
		}


		static protected function update_default_translation($data, $model) {
			$translated_columns = static::get_translated_columns();
			$strings = array();

			foreach($translated_columns as $key => $column) {
				if(isset($data[$key])) {
					$strings[$data] = $data[$key];
					unset($data[$key]);
				}

				$data[$column] = 0;
			}

			if(is_callable($model)) {
				$model = call_user_func($model, $data);
			}

			foreach($strings as $key => $value) {
				$model->put($key, $value, static::DEFAULT_LANG);
			}

			return $model;
		}


		static protected function next_translation_id() {
			static $stmt;

			if(!$stmt) {
				$stmt = static::db()->prepare(sprintf(
					'SELECT %1$s FROM %2$s ORDER BY %1$s DESC LIMIT 1',
					static::TRANSLATIONS_TABLE_ID, static::TRANSLATIONS_TABLE
				));
			}

			return (int)($stmt->execute()->fetch_value() ?: 0) + 1;
		}
	}
