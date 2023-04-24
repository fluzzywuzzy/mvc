<?php

namespace Webbmaffian\MVC\Model;

interface Authable {
	static public function get_by_email($email = '');

	public function set_password($password, $clear_resets = false);

	public function generate_recovery_hash();

	public function verify_password($password);

	public function add_capability($capability, $tenant_id);

	public function remove_capability($capability, $tenant_id);

	public function get_capabilities();

	public function get_id();

	public function get_name();

	public function get_language();
}
