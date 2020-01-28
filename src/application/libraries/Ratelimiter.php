<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once('Ratelimiter/Ratelimiter_Abstract.php');

class Ratelimiter extends Ratelimiter_Abstract {
	public function allow_request(Array $data = array()) {
		if($this->ip_is_blacklisted()) {
			$response = array('success' => false, 'blacklisted_ip' => true, 'message' => 'IP is blacklisted');
			return $this->send_response($response);
		}

		if(strtolower($_SERVER['REQUEST_METHOD']) !== 'post' || $this->ip_is_whitelisted()) {
			$response = array('success' => true);
			return $this->send_response($response);
		}

		foreach($this->resource as $key => $resource) {
			if($resource && !isset($data[$key])) {
				throw new \Exception("Resource not set.");
				exit;
			}
		}

		// Replace config variables if variables are passed in data.
		if(isset($data['requests']))
			$this->requests = $data['requests'];
		if(isset($data['duration']) && $data['duration'] !== 0)
			$this->duration = $data['duration'];
		if(isset($data['block_duration']) && $data['block_duration'] !== 0)
			$this->block_duration = $data['block_duration'];

		// Verify if user is already blocked
		$already_blocked = $this->verify_if_already_blocked($data);
		if($already_blocked) {
			$response = array('success' => false, 'already_blocked' => true, 'message' => 'User/IP blocked', 'blocked_till' => $already_blocked);
			return $this->send_response($response);
		}

		// Check and log if the should be blocked.
		$should_be_blocked = $this->verify_if_should_be_blocked($data);
		$request_log = $this->log_request($data, $should_be_blocked);

		// Before returning the response, reset library variables from config.
		$configuration = $this->CI->config;
		foreach($this->configurable as $config)
			$this->{$config} = $configuration->item($config);

		// Return response if request log is built successfully, else throw an error.
		if($request_log && $request_log->success) {
			if($should_be_blocked) {
				$response = array(
					'success' => false,
					'message' => 'User/IP blocked',
					'blocked_on_this_request' => $request_log->blocked_on_this_request,
					'blocked_till' => $request_log->blocked_till,
				);
			} else {
				$response = array('success' => true);
			}

			return $this->send_response($response);
		}

		throw new \Exception("Error Processing Request");
		exit;
	}

	public function clean_logs() {
		$duration = $this->CI->config->item('duration');
		if(!$duration)
			throw new \Exception("Error Processing Request");

		$insert_chunk_size = $this->CI->config->item('insert_chunk_size');
		if(!$insert_chunk_size)
			throw new \Exception("Error Processing Request");
			

		$timestamp = date('Y-m-d H:i:s', strtotime("- {$duration} minutes"));
		if($this->history_backup) {
			$fetch_old_sql = "SELECT * FROM `{$this->table}` WHERE `created_at` < ?";
			$result = $this->database->query($fetch_old_sql, array('created_at' => $timestamp))->result_array();

			if($result) {
				$chunked_array = array_chunk($result, $insert_chunk_size);

				foreach($chunked_array as $chunk) {
					$this->database->insert_batch($this->history_table, $chunk);
				}
			}

			$this->database->query("DELETE FROM `{$this->table}` WHERE `created_at` < ?", array('created_at' => $timestamp));
		}
	}
}
