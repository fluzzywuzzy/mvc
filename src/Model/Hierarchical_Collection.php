<?php
	namespace Webbmaffian\MVC\Model;
	use Webbmaffian\MVC\Helper\Problem;

	abstract class Hierarchical_Collection extends Model_Collection {
		public function get_tree($children_key = 'children', $parent_id = 0) {
			return $this->build_tree($parent_id, $children_key);
		}


		public function get_flat_tree($parent_id = 0) {
			$flat_tree = array();

			$this->flatten_tree($this->build_tree($parent_id, 'children'), $flat_tree);

			return $flat_tree;
		}


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


		protected function flatten_tree($tree, &$flat_tree, $depth = 0) {
			foreach($tree as $model) {
				$model->set('depth', $depth);

				$flat_tree[] = $model;
				
				if($model->has_children()) {
					$children = $model->get_children();
					$model->unset('children');
					$this->flatten_tree($children, $flat_tree, $depth + 1);
				}
			}
		}
	}