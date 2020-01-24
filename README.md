# CodeIgniter Ratelimiter

CI Ratelimiter is a, as the name suggests, a rate limiting library for CodeIgniter. This library tracks user's requests and allow/deny them based on the parameters set by you. Additionally, all subsequent requests are denied for a time frame defined by you.

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

// Verify
$this->ratelimiter->allow_request($params);
```

### Cleaning Logs
```php
$this->load->library('ratelimiter');
$this->ratelimiter->clean_logs();
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License
[Apache License](http://www.apache.org/licenses/LICENSE-2.0)