<?php
require_once __DIR__ . '/../vendor/autoload.php';

use CymaticSecurity\Client;
use CymaticSecurity\Cache;

try {
    $client = new Client(
        'cymatic',
        'f7f94820-a709-44ab-b675-f9cec080d58a',
        '29557331-dc1f-483b-8436-470047a6f9bd'
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
    $sdkJWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJjZDMyODI2ZC0xZTc0LTQxMDEtODIyOS1mOTY4OThiNzM2YjgiLCJpYXQiOjE1ODM4MzQwMjc3NzIsImRhdGEiOnsiY29tcGFueV9pZCI6IjhiOTAwOWFmLWIwODItNDkwOS05OTYzLTQ3Y2ZjMDg4ZjdhOCIsInNka191dWlkIjoiZTQzZGRmYjYtOGVhMy00ZWYyLThmYWItYWZhYTU3ZWQzZDcwIiwiaXAiOiIxODUuMjQuNzYuMTc2Iiwic29ja2V0X2lkIjoiL2NsaWVudCNCaU1sOElaUDlxUEIxMkxUQUFGZSIsImRldmljZV9pZCI6IjVkZTVjYzQyMjY4Y2I5MDAxN2E4NWVmYyIsInRlbmFudCI6ImN5bWF0aWMiLCJsb2dpbl9pZCI6IjVlNjc2M2FiZTRiZmE3MDAxOTNjZDU3ZiIsImNfdXVpZCI6IjFiMzhhZmVhLThjNTItNDc1Yy1hZWEwLWQzNDdjMWRhYjA5MyIsInNlc3Npb25faWQiOiI1ZTYyNzJiMWVlNTVhYjAwMDEyYjBlYWMifX0.8cjSpEeGUTgV_B0p8w6If7cKtKrp6DOt4KuL9VkBceY';
    $alias = 'dmitri.meshin-test-php-client@cymatic.io';

    $registration = $client->register($sdkJWT, $alias);
    echo 'Registration: ' . json_encode($registration);

    $c_uuid = $registration['c_uuid'];
    $verification = $client->verify($sdkJWT, $c_uuid);
    echo 'Verification: ' . json_encode($verification);

    $login = $client->login($sdkJWT, $c_uuid);
    echo 'Login: ' . json_encode($login);

    $session_id = $login['session_id'];
    $client->logout($sdkJWT, $session_id, $c_uuid);
} catch (Exception $exception) {
    echo "ERROR: " . $exception->getMessage() . "\n";
    echo "TRACE: " . $exception->getTraceAsString() . "\n";
}
