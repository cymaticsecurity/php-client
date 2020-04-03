# Cymatic Security PHP Client

This library has been developed to interact with <a href="https://cymatic.io" target="_blank">cymatic.io</a> backend.
This includes such operations as:
 
 - Get access token from SSO
 - Register a user on Cymatic
 - Verify a user on Cymatic
 - Open a session for a user on Cymatic
 - Close a session for a user on Cymatic

## Installation

### Basic requirements

There's few steps that you need to perform before start using API:

 - Make sure user database has field `c_uuid` on table
 - Create new table or environment variables to store cymatic credentials with fields 
   - `tenant`: `String`
   - `clientId`: `String`
   - `secret`: `String`
 - Make sure server allows cymaticsecurity origins ( CORS ) 
   - `sdk.cymaticsecurity.com`
   - `rtp.cymaticsecurity.com`
 - Make sure to have php installed at least version 7.x
 - Make sure you have composer installed

### Optional requirements

It is very recommended to store your access token in cache rather than request it every time for making further calls.

You can use caching library of your choice:  

 - <a href="https://www.php.net/manual/en/book.curl.php" target="_blank">php_curl</a>
 - <a href="https://www.php.net/manual/en/book.memcached.php" target="_blank">php_memcached</a>
 - <a href="https://redislabs.com/lp/php-redis/" target="_blank">php_redis</a>
  
### Installing client

`composer.json`
```json
{
    "require": {
        "cymaticsecurity/php-client": "*"
    }
}
```

Run installation in your project folder:

```sh
php composer.phar install
```

## Usage

If you did not setup autoload in your php project yet, you can do it as follows:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

Use library from namespace:

```php
use Cymatic\Client;
use Cymatic\Cache;
```

Instantiate client:

```php
$client = new Client(
    'your tenant',
    'your clientId',
    'your clientSecret'
);
```

### URLs

You can change SSO and API urls using these methods:

```php
$client->setAPIUrl('api url');
$client->setSSOUrl('sso url');
```

### Timeouts

In different systems timeouts can be painful, so you have method to set default timeouts:

```php
// 10 seconds timeout
$client->setTimeout(10);
```

### Caching

These are brief examples of how to setup caching framework:

```php
$client->setCache(new Cache(Cache::$CACHE_TYPE_MEMCACHED, array('127.0.0.1', 11211)));
$client->setCache(new Cache(Cache::$CACHE_TYPE_REDIS, array('127.0.0.1', 6379)));
$client->setCache(new Cache(Cache::$CACHE_TYPE_APC));
```

There is no option to setup multiply servers for caching, if you need this feature please request it or make pull request to our official repository.

### Getting access token from SSO

To ask for an access token against Cymatic SSO the requirements are:
 - `tenant` on the URI
 - `clientId` and `clientSecret` as Basic Base64 encoded on the Header
 - `grant_type` as `client_credentials` on the form payload
 
#### Abstract example
 
`Request`
```
POST https://sso.cymaticsecurity.com/auth/realms/{{tenant}}/protocol/openid-connect/token/
```

`Headers`
```
"Authorization": "Basic base64(${clientId}:${clientSecret})"
```

`Body`
```
{
    "grant_type": "client_credentials"
}
```
 
It is done automatically once you doing any API request, so you don't need to care about this step yourself.

### Registration

To register a user on Cymatic the requirements are: 
 - `Access JWT token` on the header as `Authorization Bearer` 
 - `Content-Type` as `application/json` on the header
 - `Alias` for new user on the body payload
 - `Identity JWT` from the Browser on the body payload
 
#### Abstract example

`Request`
```
POST https://api.cymaticsecurity.com/profiles
```

`Headers`
```
"Authorization": "Bearer JWT.from.SSO"
"Content-Type": "application/json"
```

`Body`
```
{
  "alias": "some alias",
  "jwt": "JWT.from.Browser"
}
```

#### PHP example

```php
$sdkJWT = $_POST['cy/jwt'];
$alias = $_POST['email'];
$registration = $client->register($sdkJWT, $alias);
echo 'Registration: ' . json_encode($registration);
```

When registration is done, save `$registration['c_uuid']` in your database.
This is user unique identifier in cymatic which you will use for all future calls.

### Verification

To verify a user against Cymatic the requirements are:
 - `Access JWT token` on the header as `Authorization Bearer` 
 - `Content-Type` as `application/json` on the header
 - `c_uuid` from the user attempting to log in
 - `Identity JWT` from the Browser on the body payload

#### Abstract example

`Request`
```
POST https://api.cymaticsecurity.com/verify
```

`Headers`
```
"Authorization": "Bearer JWT.from.SSO"
"Content-Type": "application/json"
```

`Body`
```
{
  "c_uuid": "Id provided by cymatic",
  "jwt": "JWT.from.Browser"
}
```

#### PHP example

```php
$c_uuid = $registration['c_uuid'];
$verification = $client->verify($sdkJWT, $c_uuid);
echo 'Verification: ' . json_encode($verification);
```

Verification response contains all necessary information for you to make decision about further user login behavior.
You can block user, allow him access or make challenge if you have such recommendations in response.

This is typical verification response:

```json
{
  "TODO": "TODO"
}
```

### Opening session

To open a session for user against Cymatic the requirements are:
 - `Access JWT` token on the header as `Authorization Bearer` 
 - `Content-Type` as `application/json` on the header
 - `c_uuid` from the user attempting to log in
 - `Identity JWT` from the Browser on the body payload


#### Abstract example

`Request`
```
POST https://api.cymaticsecurity.com/login
```

`Headers`
```
"Authorization": "Bearer JWT.from.SSO"
"Content-Type": application/json"
```

`Body`
```
{
  "c_uuid": "Id provided by cymatic",
  "jwt": "JWT.from.Browser"
}
```

#### PHP example

```php
$session = $client->login($sdkJWT, $c_uuid);
echo 'Session: ' . json_encode($session);
```

### Closing session

To close a session for user against Cymatic the requirements are: 
 - `Access JWT token` on the header as `Authorization Bearer` 
 - `Content-Type` as `application/json` on the header
 - `c_uuid` from the user attempting to log in

#### Abstract example

`Request`
```
POST https://api.cymaticsecurity.com/logout
```

`Headers`
```
"Authorization": "Bearer JWT.from.SSO"
"Content-Type": "application/json"
```

`Body`
```
{
  "c_uuid": "Id provided by cymatic",
  "session_id": "Session ID"
}
```

#### PHP example

```php
$session_id = $login['session_id'];
$client->logout($session_id, $c_uuid);
```

# LICENCE

MIT

# Author

© Cymatic Team
