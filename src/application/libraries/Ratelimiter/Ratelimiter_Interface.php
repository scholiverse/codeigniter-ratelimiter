<?php

interface Ratelimiter_Interface {

	/**
	*	Returns whether the request should be blocked or allowed.
	*
	*	@param array $data
	*	@return boolean
	*/
	public function allow_request(Array $data);

	/**
	*	Clear (and backup) old logs, not necessary for future checks.
	*/
	public function clean_logs();
}
