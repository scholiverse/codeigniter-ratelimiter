# CodeIgniter Ratelimiter

CI Ratelimiter is, as the name suggests, a rate limiting library for CodeIgniter. This library tracks user's requests and allow/deny them based on the parameters set by you. Additionally, all subsequent requests are denied for a time frame defined by you.

## Installation
Merge the contents of the `src\application` folder with the `application` folder of your project. Then you can autoload the library by adding ratelimiter to autoloader like:
```php
$autoload['libraries'] = array('ratelimiter');
```

Alternatively, you can load the library as and when required, like:
```php
$this->load->library('ratelimiter');
```

## Usage
### Verifying requests
```php
// Load library
$this->load->library('ratelimiter');

// Set Paramaters
$params = array(
    'requests' => 50,
    'duration' => 30,
    'block_duration' => 30,
    /* Additional Resource/User Data */
);

// Verify (return_type = 'object' in config)
$allow_request = $this->ratelimiter->allow_request($params);
if(!$allow_request->success) {
    if($allow_request->blocked_on_this_request) {
        // Alert via email/sms
    }
    // Block request
}

```

### Cleaning Logs
```php
$this->load->library('ratelimiter');
$this->ratelimiter->clean_logs();
```

## Response
### allow_request() method
```php
/**
*   Return type = 'object'
*/

// On success (allow request)
$response->success = true;

// On failure
    // IP blacklisted
    $response->success = false;
    $response->blacklisted_ip = true;
    $response->message = 'IP is blacklisted';
    
    // User/IP is already blocked.
    $response->success = false;
    $response->already_blocked = true;
    $response->message = 'User/IP blocked';
    $response->blocked_till = '2020-01-27 00:00:00';
    
    // User/IP blocked on this request
    $response->success = false;
    $response->blocked_on_this_request = true;
    $response->message = 'User/IP blocked';
    $response->blocked_till = '2020-01-27 00:00:00';
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License
[Apache License](http://www.apache.org/licenses/LICENSE-2.0)