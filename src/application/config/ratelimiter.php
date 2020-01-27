<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
*	Ratelimit Table
*	Name of the table used to log and verify requests
*/
$config['ratelimit_table'] = 'rate_limiter';

/*
*	Ratelimit History
*	Since request logs increases with time, only relevant logs are stored in Ratelimit table,
* 	to keep it light and increase speed of the queries.
*
*	History Backup => Defines if old request logs should be preserved.
*	Ratelimit History Table => Table used for preserving old logs.
*	Insert Chunk Size => Number of rows to be inserted in history table at once.
*/
$config['history_backup'] = TRUE;
$config['ratelimit_history_table'] = 'rate_limiter_history';
$config['insert_chunk_size'] = 50;

/*
*	Rate limit parameters
*	Requests => Number of requests allowed per User/IP on a given resource. 0 for infinite.
*	Duration => (in miuntes) Timeframe for requests (Rate = Requests / Minutes)
*	Block Duration => (in minutes) Time for which the user should be blocked.
*
*	NOTE: Per is used as the maximum time limit. All the requests previous to 'Per' will be sent to History Table.
*/
$config['requests'] = 500;
$config['duration'] = 5*60;
$config['block_duration'] = 60;

/*
*	Resource and User Data
*	Resource => (Key, Track)
*	User Data => (Key, Track)
*
*		Key => Identifier of the resource
*		Track => Whether to track this resource uniquely
*/
$config['resource'] = array('class_name' => TRUE, 'method_name' => TRUE);
$config['user_data'] = array('user_id' => TRUE);

/*
*	Whitelist/Blacklist IPs
*	IP addressed which are white listed/black listed
*/
$config['whitelist_ips'] = array();
$config['blacklist_ips'] = array();

/*
*	Response Type
*	Defines the response type of library
*
*	Possible Values = 'object', 'json', 'array;
*/
$config['response_type'] = 'object';
