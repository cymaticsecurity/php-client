<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cymatic\Client;
use Cymatic\Cache;

try {
    $client = new Client(
        'tenant',
        'client id',
        'client secret'
    );

    $client
        ->setAPIUrl('https://spv2.dev.cymatic.info/')
        ->setSSOUrl('https://sso.dev.cymatic.info/')
        ->setTimeout(5);

    // Cache is optional
//    $client->setCache(new Cache(Cache::$CACHE_TYPE_MEMCACHED, array('127.0.0.1', 11211)));
//    $client->setCache(new Cache(Cache::$CACHE_TYPE_REDIS, array('127.0.0.1', 6379)));
//    $client->setCache(new Cache(Cache::$CACHE_TYPE_APC));

    // Params for registration
    $sdkJWT = 'value from submitted form';
    $alias = 'email or username';

    $registration = $client->register($sdkJWT, $alias);
    echo 'Registration: ' . json_encode($registration);

    $c_uuid = $registration['c_uuid'];
    $verification = $client->verify($sdkJWT, $c_uuid);
    echo 'Verification: ' . json_encode($verification);

    $login = $client->login($sdkJWT, $c_uuid);
    echo 'Login: ' . json_encode($login);

    $session_id = $login['session_id'];
    $client->logout($session_id, $c_uuid);
} catch (Exception $exception) {
    echo "ERROR: " . $exception->getMessage() . "\n";
    echo "TRACE: " . $exception->getTraceAsString() . "\n";
}
