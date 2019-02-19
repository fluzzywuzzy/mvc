<?php
/*
	USAGE:
	
	Create a file (e.g. cli.php) where you initialize this Cli class with your namespace as first arguent, and $argv as second. Example:

	new \Webbmaffian\MVC\Cli\Cli(__NAMESPACE__, $argv);

	After that, open your terminal and type "php cli.php" for further instructions.
*/

namespace Webbmaffian\MVC\Cli;
use Webbmaffian\MVC\Helper\Problem;

class Cli {
	protected $namespace = null;
	protected $model = null;
	protected $method = null;
	protected $data = array();
	protected $flags = array();
	protected $args = array();


	public function __construct($namespace, $args) {
		try {
			if(php_sapi_name() !== 'cli') {
				throw new Problem('Must be runned from cli.');
			}

			$this->namespace = $namespace;
			$this->prepare_args($args);
			$this->maybe_prompt();
			$this->run_command();
		}
		catch(\Exception $e) {
			echo $e->getMessage() . "\n";
		}
	}


	protected function run_command() {
		if(is_null($this->model) || is_null($this->method)) {
			$this->help();
			return;
		}

		if($this->method === 'list') {
			$this->list_collection();
		}
		elseif(in_array($this->method, ['update', 'delete'])) {
			$this->update_model();
		}
		else {
			$this->static_method();
		}
	}


	protected function list_collection() {
		$class = $this->get_model_class();
		$collection = $class::collection();
		$collection->select('*');

		foreach($this->data as $key => $value) {
			if(method_exists($collection, $key)) {
				if(is_null($value)) {
					$collection->$key();
				}
				else {
					$collection->$key($value);
				}
			}
			else {
				if(is_string($value) && $value[0] === '~') {
					$comparison = 'LIKE';
					$value = '%' . $value . '%';
				}
				else {
					$comparison = '=';
				}

				$collection->where($key, $comparison, $value);
			}
		}

		$models = $collection->get();

		if(empty($models)) {
			throw new Problem('No rows found.');
		}

		$builder = new \AsciiTable\Builder();
		$builder->addRows($models);

		echo $builder->renderTable() . "\n";
	}


	protected function update_model() {
		if(!isset($this->args[0]) || !is_numeric($this->args[0])) {
			throw new Problem('Missing or invalid ' . $this->model . ' ID.');
		}

		$class = $this->get_model_class();
		$model = $class::get_by_id($this->args[0]);

		if($this->method === 'update') {
			$model->update($this->data);

			echo 'Updated row:' . "\n";

			$builder = new \AsciiTable\Builder();
			$builder->addRow($model);

			echo $builder->renderTable() . "\n";
		}
		elseif($this->method === 'delete') {
			$model->delete();

			echo 'Deleted ' . $this->model . ' #' . $this->args[0] . "\n";
		}
	}


	protected function static_method() {
		$class = $this->get_model_class();

		if(!method_exists($class, $this->method)) {
			throw new Problem('No ' . $this->model . ' method of "' . $this->method . '" exists.');
		}

		if(!empty($this->data)) {
			$result = $class::{$this->method}($this->data);
		}
		else {
			$result = $class::{$this->method}();
		}

		if(is_string($result) || is_numeric($result)) {
			echo $result . "\n";
		}
		elseif(is_array($result) || (is_object($result) && $result instanceof \JsonSerializable)) {
			echo 'Result:' . "\n";

			$builder = new \AsciiTable\Builder();
			$builder->addRow($result);

			echo $builder->renderTable() . "\n";
		}
		else {
			var_dump($result);
		}
	}


	protected function prepare_args($args) {
		array_shift($args);
		
		foreach($args as $arg) {
			if($arg === '') continue;

			if(substr($arg, 0, 2) === '--') {
				$arg = ltrim($arg, '-');
				
				if(strpos($arg, '=') !== false) {
					list($key, $value) = explode('=', $arg, 2);
				}
				else {
					$key = $arg;
					$value = null;
				}

				$key = str_replace('-', '_', $key);
				$this->data[$key] = $value;
				$this->lock_method();
			}
			elseif($arg[0] === '-') {
				$arg = ltrim($arg, '-');

				$this->flags[$arg] = true;
				$this->lock_method();
			}
			else {
				if(is_null($this->model)) {
					$this->model = strtolower($arg);
				}
				elseif(is_null($this->method)) {
					$this->method = strtolower($arg);
				}
				elseif(!empty($this->data)) {
					$arg_keys = array_keys($this->data);
					$last_key = array_pop($arg_keys);

					if(is_null($this->data[$last_key])) {
						$this->data[$last_key] = $arg;
					}
					else {
						$this->args[] = $arg;
					}
				}
				else {
					$this->args[] = $arg;
				}
			}
		}
	}


	protected function maybe_prompt() {
		if(!isset($this->flags['p'])) return;

		foreach($this->data as $key => $value) {
			if(!is_null($value)) continue;

			$new_value = readline('Set ' . str_replace('_', ' ', $key) . ': ');

			if(!empty($new_value)) {
				$this->data[$key] = $new_value;
			}
		}
	}


	protected function lock_method() {
		if(is_null($this->model)) {
			$this->model = true;
		}

		if(is_null($this->method)) {
			$this->method = true;
		}
	}


	protected function get_model_class() {
		return $this->namespace . '\\' . ucwords(str_replace('-', '_', $this->model));
	}


	protected function help() {
		echo "\n";
		echo 'Handle MVC models via cli. Example usage:' . "\n\n";

		echo 'List User Collection ordered by firstname:' . "\n";
		echo 'user list --order-by=firstname' . "\n\n";

		echo 'Create a User model:' . "\n";
		echo 'user create --firstname=John --lastname=Smith' . "\n\n";

		echo 'Update firstname and lastname for the User model with ID 123:' . "\n";
		echo 'user update 123 --firstname=John --lastname=Smith' . "\n\n";

		echo 'Update password for the User model with ID 123, using prompt:' . "\n";
		echo 'user update 123 -p --password' . "\n\n";

		echo 'Delete User model with ID 123:' . "\n";
		echo 'user delete 123' . "\n\n";
	}
}
