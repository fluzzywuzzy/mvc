<?php
	namespace Webbmaffian\MVC\Model;
	use Webbmaffian\MVC\Helper\Problem;

	abstract class Hierarchical_Collection extends Model_Collection {
		public function get_tree($children_key = 'children', $parent_id = 0) {
			return $this->build_tree($parent_id, $children_key);
		}


		// <- Todo: Add get_flat_tree() method (one-dimensional array with depth attribute).


		protected function build_tree($parent_id, $children_key) {
			$branch = array();

			foreach($this->get() as $model) {
				if($model->get_parent_id() != $parent_id) continue;

				if($children = $this->build_tree($model->get_id(), $children_key)) {
					$model->set($children_key, $children);
				}

				$branch[] = $model;
			}

			return $branch;
		}
	}