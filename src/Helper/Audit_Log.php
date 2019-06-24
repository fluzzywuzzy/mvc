<?php

namespace Webbmaffian\MVC\Helper;

use Webbmaffian\MVC\Model\Audit;

class Audit_Log {

	static public function add($note = '', $object_type = '', $object_id = 0) {
		if(empty($_ENV['AUDIT_LOG'])) return;

		if (!is_numeric($object_id)) {
			throw new Problem('Object ID must be numeric.');
		}

		try {
			Audit::create(array(
				'user_id' => (int)Auth::get_id(),
				'object_type' => $object_type,
				'object_id' => $object_id,
				'time_done' => Helper::date('now')->format('Y-m-d H:i:s'),
				'note' => $note
			));
		} catch(Problem $p) {
			Log::error('Unable to create audit log entry. ' . $p->getMessage());
		}
	}


	static public function general() {
		$args = func_get_args();

		if (empty($args)) {
			throw new Problem('Missing audit note.');
		}

		$note = array_shift($args);

		if (!empty($args)) {
			$note = vsprintf($note, $args);
		}

		return self::add($note);
	}
}
