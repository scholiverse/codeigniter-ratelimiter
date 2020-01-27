<?php

require_once('Ratelimiter_Interface.php');

abstract class Ratelimiter_Abstract implements Ratelimiter_Interface {

	protected $CI;
	protected $configurable;
	protected $sql;

	public function __construct() {
		$this->CI = &get_instance();
		$this->CI->config->load('ratelimiter');

		$this->configurable = array('requests','duration','block_duration','resource','user_data', 'whitelist_ips', 'blacklist_ips', 'response_type');
		$configuration = $this->CI->config;

		if(
			!$configuration->item('ratelimit_table') ||
			$configuration->item('requests') === NULL ||
			!$configuration->item('duration') ||
			!$configuration->item('block_duration') ||
			!is_array($configuration->item('resource')) ||
			!is_array($configuration->item('user_data')) ||
			!$configuration->item('response_type')
		) {
			throw new \Exception("Invalid Configuration");
			exit;
		}

		$this->table = $configuration->item('ratelimit_table');
		$this->history_backup = $configuration->item('history_backup');
		$this->history_table = $configuration->item('ratelimit_history_table');
		$this->sql = array(
			'already_blocked' => "SELECT `blocked_till` FROM RATE_LIMIT_TABLE",
			'request_count' => "SELECT COUNT(*) AS `count` FROM RATE_LIMIT_TABLE",
			'logging' => "INSERT INTO RATE_LIMIT_TABLE (FIELDS_PLACEHOLDER) VALUES (VALUES_PLACEHOLDER)",
		);
		foreach($this->sql as $key => $sql) {
			$this->sql[$key] = str_replace("RATE_LIMIT_TABLE", "`{$this->table}`", $sql);
		}

		// Setting Configurable
		foreach($this->configurable as $config)
			$this->{$config} = $configuration->item($config);
	}

	/**
	*	Returns TRUE if already blocked
	*
	*	@param array $data
	*	@return boolean
	*/
	protected function verify_if_already_blocked(Array $data) {
		$sql = $this->sql['already_blocked'] . " WHERE `blocked_till` > ?";
		$sql_data['blocked_till'] = date('Y-m-d H:i:s');

		$this->prepare_blocking_sqls($sql, $sql_data, $data);

		$result = $this->CI->db->query($sql, $sql_data)->row();
		if($result && $result->blocked_till)
			return $result->blocked_till;
		return FALSE;
	}

	/**
	*	Returns TRUE if request should be blocked.
	*
	*	@param array $data
	*	@return boolean
	*/
	protected function verify_if_should_be_blocked(Array $data) {
		if($this->requests == 0)
			return FALSE;

		$sql = $this->sql['request_count'] . " WHERE `created_at` > ?";
		$sql_data['created_at'] = date('Y-m-d H:i:s', strtotime("- {$this->duration} minutes"));

		$this->prepare_blocking_sqls($sql, $sql_data, $data);

		$result = $this->CI->db->query($sql, $sql_data)->row();
		return (int)$result->count >= $this->requests;
	}

	/**
	*	Logs current request into the database.
	*	Return TRUE if logged successfully, else FALSE.
	*
	*	@param array $data
	*	@param boolean $should_be_blocked
	*	@return boolean
	*/
	protected function log_request(Array $data, bool $should_be_blocked) {
		if($should_be_blocked) {
			$blocked_till = date('Y-m-d H:i:s', strtotime("+ {$this->block_duration} minutes"));
		}

		$sql = $this->sql['logging'];
		$sql_data = array(
			'request_url' => $_SERVER['REQUEST_URI'],
			'ip_address' => $this->get_client_ip(),
			'blocked_till' => isset($blocked_till) ? $blocked_till : NULL
		);

		$fields_placeholder = "`request_url`, `ip_address`, `blocked_till`";
		$values_placeholder = "?,?,?";

		foreach(array_merge($this->resource, $this->user_data) as $key => $resource) {
			$fields_placeholder .= ", `$key`";
			$values_placeholder .= ",?";

			$sql_data[$key] = isset($data[$key]) ? $data[$key] : NULL; 
		}

		$sql = str_replace("FIELDS_PLACEHOLDER", $fields_placeholder, $sql);
		$sql = str_replace("VALUES_PLACEHOLDER", $values_placeholder, $sql);

		$response = new \stdClass();
		$response->success = (bool)$this->CI->db->query($sql, $sql_data);
		$response->blocked_on_this_request = $should_be_blocked;
		$response->blocked_till = $should_be_blocked ? $blocked_till : NULL;

		return $response;
	}

	/**
	*	Build SQLs for verify_if_already_blocked() and verify_if_should_be_blocked() functions.
	*
	*	@param string $sql
	*	@param array $sql_data
	*	@param array $data
	*/
	protected function prepare_blocking_sqls(String &$sql, Array &$sql_data, Array $data) {
		foreach($this->resource as $key => $resource) {
			if($resource) {
				$sql .= " AND `$key` = ?";
				$sql_data[$key] = $data[$key];
			}
		}

		$track_by_user_data = FALSE;
		foreach($this->user_data as $key => $user_data) {
			if($user_data && isset($data[$key])) {
				$track_by_user_data = TRUE;
				$sql .= " AND `$key` = ?";
				$sql_data[$key] = $data[$key];
			}
		}

		if(!$track_by_user_data) {
			$sql .= " AND `ip_address` = ?";
			$sql_data['ip_address'] = $this->get_client_ip();
		}
	}

	/**
	*	Returns client's IP address
	*
	*	@return string
	*/
	protected function get_client_ip() {
		$ip_address = '';
		if (isset($_SERVER['HTTP_CLIENT_IP']))
			$ip_address = $_SERVER['HTTP_CLIENT_IP'];
		else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if(isset($_SERVER['HTTP_X_FORWARDED']))
			$ip_address = $_SERVER['HTTP_X_FORWARDED'];
		else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
			$ip_address = $_SERVER['HTTP_FORWARDED_FOR'];
		else if(isset($_SERVER['HTTP_FORWARDED']))
			$ip_address = $_SERVER['HTTP_FORWARDED'];
		else if(isset($_SERVER['REMOTE_ADDR']))
			$ip_address = $_SERVER['REMOTE_ADDR'];
		else
			$ip_address = 'UNKNOWN';
		return $ip_address;
	}

	/**
	*	Returns TRUE if client's IP address is in whitelisted IPs array
	*
	*	@return boolean
	*/
	protected function ip_is_whitelisted() {
		return in_array($this->get_client_ip(), $this->whitelist_ips);
	}

	/**
	*	Returns TRUE if client's IP address is in blacklisted IPs array
	*
	*	@return boolean
	*/
	protected function ip_is_blacklisted() {
		return in_array($this->get_client_ip(), $this->blacklist_ips);
	}

	/**
	*	Returns response from the library in desired format
	*
	*	@param array
	*	@return mixed
	*/
	protected function send_response($data) {
		switch (strtolower($this->response_type)) {
			case 'object':
				$response = new \stdClass();
				foreach($data as $key => $value)
					$response->{$key} = $value;

				return $response;
				break;

			case 'json':
				header('Content-Type: application/json');
				return json_encode($data);
				break;

			case 'array':
			default:
				return $data;
				break;
		}
	}
}
