<?php

namespace CymaticSecurity;

/**
 * Minimal standalone library to work with Cymatic Security
 * Register to get your credentials here: https://cymatic.io
 *
 * @license PRIVATE
 * @url https://cymatic.io
 * @author Cymatic Security
 * @package CymaticSecurity
 * @copyright Cymatic Security
 * @version 1.0
 */
class Client
{
    /**
     * @var int
     */
    public static $HTTP_RESPONSE_CODE_CREATED = 201;

    /**
     * @var int
     */
    public static $HTTP_RESPONSE_CODE_OK = 200;
    /**
     * @var int
     */
    protected $requestTimeout = 10;

    /**
     * @var string
     */
    protected $userAgent = 'CymaticSecurity\Client v1.0';

    /**
     * Cloud Instance Name
     * @var string
     */
    protected $tenant;

    /**
     * Client ID
     * @var string
     */
    protected $clientId;

    /**
     * Client secret
     * @var string
     */
    protected $clientSecret;

    /**
     * Single sign on endpoint
     * @var string
     */
    protected $ssoUrl = 'https://sso.cymatic.info/';

    /**
     * Cymatic Security API endpoint
     * @var string
     */
    protected $apiUrl = 'https://api.cymaticsecurity.com/';

    /**
     * Key used to store token in any cache
     *
     * @var string
     */
    protected $tokenCacheKey = "cymatic_security_access_token";

    /**
     * Caching framework with __get and __set interfaces
     *
     * @var null|Cache
     */
    protected $cache = null;

    /**
     * Client constructor.
     *
     * @param $tenant
     * @param $clientId
     * @param $clientSecret
     * @throws \Exception
     */
    public function __construct($tenant, $clientId, $clientSecret)
    {
        if (!$tenant) {
            throw new \Exception("Tenant is required");
        }
        if (!$clientId) {
            throw new \Exception("Client ID is required");
        }
        if (!$clientSecret) {
            throw new \Exception("Client Secret is required");
        }

        $this->tenant = $tenant;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param $cache
     * @return $this
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Set default timeout for all requests
     *
     * @param $timeout
     * @return Client
     */
    public function setTimeout($timeout)
    {
        $this->requestTimeout = intval($timeout);
        return $this;
    }

    /**
     * Setup API url, commonly used for development purposes
     *
     * @param $apiUrl
     * @return Client
     * @throws \Exception
     */
    public function setAPIUrl($apiUrl)
    {
        if (!$apiUrl) {
            throw new \Exception("API Url is required");
        }
        $this->apiUrl = $apiUrl;
        return $this;
    }

    /**
     * Setup API url, commonly used for development purposes
     *
     * @param $ssoUrl
     * @return Client
     * @throws \Exception
     */
    public function setSSOUrl($ssoUrl)
    {
        if (!$ssoUrl) {
            throw new \Exception("SSO Url is required");
        }
        $this->ssoUrl = $ssoUrl;
        return $this;
    }

    /**
     * Return final API endpoint regarding API which needs to be called
     *
     * @param $apiName
     * @return string
     * @throws \Exception
     */
    protected function getAPIUrl($apiName)
    {
        if (!$apiName) {
            throw new \Exception("API name can not be empty");
        }

        switch ($apiName) {
            case "register":
                return $this->apiUrl . "profiles";

            case "verify":
                return $this->apiUrl . "verify";

            case "login":
                return $this->apiUrl . "login";

            case "logout":
                return $this->apiUrl . "logout";

            case "token":
                return str_replace("{{tenant}}", $this->tenant, $this->ssoUrl . "auth/realms/{{tenant}}/protocol/openid-connect/token");

            default:
                throw new \Exception("API is not supported: " . $apiName);
        }
    }

    /**
     * Return basic auth header to use in token retrieval request
     *
     * @return string
     */
    protected function getBasicAuthHeader()
    {
        return "Basic " . base64_encode($this->clientId . ":" . $this->clientSecret);
    }

    /**
     * Return bearer auth header to use in Authorization header
     *
     * @return string
     * @throws \Exception
     */
    protected function getBearerAuthHeader()
    {
        return "Bearer " . $this->retrieveToken();
    }

    /**
     * Return cached token
     *
     * @return string
     */
    protected function getCachedToken()
    {
        if ($this->cache) {
            return $this->cache->{$this->tokenCacheKey . str_replace('-', '_', $this->clientId)};
        }
        return '';
    }

    /**
     * Save token in apc cache
     * If no apc is available then it doesn't do anything and
     * proceed silently as non-critical behavior
     *
     * @param $token
     * @throws \Exception
     */
    protected function saveTokenInCache($token)
    {
        if (!$token) {
            throw new \Exception("Token is required");
        }
        if ($this->cache) {
            $this->cache->{$this->tokenCacheKey . str_replace('-', '_', $this->clientId)} = $token;
        }
    }

    /**
     * Retrieve token
     *
     * Method: POST
     * URL: {{SSO URL}}/auth/realms/{{tenant}}/protocol/openid-connect/token
     * Headers: ["Authorization": "Basic " + access_token]
     * Body: {"grant_type": "client_credentials"}
     *
     * @return string
     * @throws \Exception
     */
    protected function retrieveToken()
    {
        try {
            // Get cached token
            $token = $this->getCachedToken();
            if ($token) {
                $tokenData = $this->decodeToken($token);
                if (time() < $tokenData->exp) {
                    return $token;
                }
            }

            // Retrieve fresh token
            $body = array(
                "grant_type" => "client_credentials"
            );
            $headers = array(
                "Authorization: " . $this->getBasicAuthHeader()
            );
            $options = array(
                'isJsonRequest' => false,
                'responseCode' => Client::$HTTP_RESPONSE_CODE_OK
            );

            $response = $this->request('token', $body, $headers, $options);
            if (isset($response->access_token)) {
                $this->saveTokenInCache($response->access_token);
                return $response->access_token;
            }
            throw new \Exception("Token is empty in SSO response: " . json_encode($response));
        } catch (\Exception $e) {
            throw new \Exception("Retrieve token error: " . $e->getMessage());
        }
    }

    /**
     * Register user in Cymatic
     *
     * Method: POST
     * URL: https://{{API URL}}/profiles
     * Headers: ["Authorization": "Bearer " + access_token]
     * Body: {"jwt": jwt_from_sdk, "alias": any_alias}
     *
     * @param $sdkJWT
     * @param $alias
     * @return array|string
     * @throws \Exception
     */
    public function register($sdkJWT, $alias)
    {
        try {
            if (!$sdkJWT) {
                throw new \Exception("SDK JWT should be provided");
            }
            if (!$alias) {
                throw new \Exception("Alias (email) should be provided");
            }
            $body = array(
                "jwt" => $sdkJWT,
                "alias" => $alias
            );
            $headers = array(
                "Authorization: " . $this->getBearerAuthHeader()
            );
            $options = array(
                'isJsonRequest' => true,
                'responseCode' => Client::$HTTP_RESPONSE_CODE_CREATED
            );
            return $this->request('register', $body, $headers, $options);
        } catch (\Exception $registerException) {
            throw new \Exception("Registration error: " . $registerException->getMessage());
        }
    }

    /**
     * Verify registered user in Cymatic
     *
     * Method: POST
     * URL: https://{{API URL}}/verify
     * Headers: ["Authorization": "Bearer " + access_token]
     * Body: {"jwt": jwt_from_sdk, "c_uuid": c_uuid}
     *
     * @param $sdkJWT
     * @param $c_uuid
     * @return array|string
     * @throws \Exception
     */
    public function verify($sdkJWT, $c_uuid)
    {
        try {
            if (!$sdkJWT) {
                throw new \Exception("SDK JWT should be provided");
            }
            if (!$c_uuid) {
                throw new \Exception("c_uuid should be provided");
            }
            $body = array(
                "jwt" => $sdkJWT,
                "c_uuid" => $c_uuid
            );
            $headers = array(
                "Authorization: " . $this->getBearerAuthHeader()
            );
            $options = array(
                'isJsonRequest' => true,
                'responseCode' => Client::$HTTP_RESPONSE_CODE_CREATED
            );
            return $this->request('verify', $body, $headers, $options);
        } catch (\Exception $verifyException) {
            throw new \Exception("Verification error: " . $verifyException->getMessage());
        }
    }

    /**
     * Login registered and verified user in Cymatic and create session
     *
     * Method: POST
     * URL: https://{{API URL}}/login
     * Headers: ["Authorization": "Bearer " + access_token]
     * Body: {"jwt": jwt_from_sdk, "c_uuid": c_uuid}
     *
     * @param $sdkJWT
     * @param $c_uuid
     * @return array|string
     * @throws \Exception
     */
    public function login($sdkJWT, $c_uuid)
    {
        try {
            if (!$sdkJWT) {
                throw new \Exception("SDK JWT should be provided");
            }
            if (!$c_uuid) {
                throw new \Exception("c_uuid should be provided");
            }
            $body = array(
                "jwt" => $sdkJWT,
                "c_uuid" => $c_uuid
            );
            $headers = array(
                "Authorization: " . $this->getBearerAuthHeader()
            );
            $options = array(
                'isJsonRequest' => true,
                'responseCode' => Client::$HTTP_RESPONSE_CODE_OK
            );
            return $this->request('login', $body, $headers, $options);
        } catch (\Exception $verifyException) {
            throw new \Exception("Login error: " . $verifyException->getMessage());
        }
    }

    /**
     * Logout user from Cymatic
     *
     * Method: POST
     * URL: https://{{API URL}}/logout
     * Headers: ["Authorization": "Bearer " + access_token]
     * Body: {"jwt": jwt_from_sdk, "session_id": session_id }
     *
     * @param $sdkJWT
     * @param $session_id
     * @param $c_uuid
     * @return array|string
     * @throws \Exception
     */
    public function logout($sdkJWT, $session_id, $c_uuid)
    {
        try {
            if (!$sdkJWT) {
                throw new \Exception("SDK JWT should be provided");
            }
            if (!$c_uuid) {
                throw new \Exception("c_uuid should be provided");
            }
            if (!$session_id) {
                throw new \Exception("session_id should be provided");
            }
            $body = array(
                "jwt" => $sdkJWT,
                "c_uuid" => $c_uuid,
                "session_id" => $session_id
            );
            $headers = array(
                "Authorization: " . $this->getBearerAuthHeader()
            );
            $options = array(
                'isJsonRequest' => true,
                'responseCode' => Client::$HTTP_RESPONSE_CODE_OK
            );
            return $this->request('logout', $body, $headers, $options);
        } catch (\Exception $verifyException) {
            throw new \Exception("Logout error: " . $verifyException->getMessage());
        }
    }

    /**
     * Make actual request to SSO/API endpoints and return data
     * Compare returned HTTP code with provided in $options or with 200
     * Throw exception if HTTP code does not match or request failed
     *
     * @param $api
     * @param $headers
     * @param $body
     * @param array $options
     * @return array|string
     * @throws \Exception
     */
    protected function request($api, $body, $headers = array(), $options = array())
    {
        $ch = null;
        try {
            // Options
            $defaultOptions = array(
                'isPostRequest' => true,
                'isJsonResponse' => true,
                'isJsonRequest' => true,
                'responseCode' => Client::$HTTP_RESPONSE_CODE_OK
            );
            $options = array_merge($defaultOptions, $options);

            // Initialize curl
            $ch = curl_init($this->getAPIUrl($api));
            if ($options['isPostRequest']) {
                curl_setopt($ch, CURLOPT_POST, 1);
            }

            // Body
            if (!empty($body)) {
                if ($options['isJsonRequest']) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
                }
            }

            // Headers
            $defaultHeaders = array(
                "Accept: application/json"
            );
            if ($options['isJsonRequest']) {
                array_push($defaultHeaders, "Content-type: application/json; charset=utf-8");
            } else {
                array_push($defaultHeaders, "Content-type: application/x-www-form-urlencoded; charset=utf-8");
            }
            $headers = array_merge($defaultHeaders, $headers);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Other important options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->requestTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception("Error during request: " . curl_error($ch));
            }

            // Decode JSON
            if ($options['isJsonResponse'] && !empty($response)) {
                $decodedResponse = json_decode($response);
                if (!empty($decodedResponse)) {
                    $response = $decodedResponse;
                }
            }

            // Check response status code
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($options['responseCode'] !== $httpCode) {
                $errorDescription = '';
                if (isset($response->error_description)) {
                    $errorDescription = $response->error_description;
                }
                if (!$errorDescription && isset($response->error)) {
                    $errorDescription = $response->error;
                }
                $errorDescription = $errorDescription ? $errorDescription : ($response ? $response : 'Unknown Error Occurred: ' . $httpCode);
                throw new \Exception(json_encode($errorDescription));
            }

            // Close connection
            curl_close($ch);
            return $response;
        } catch (\Exception $exception) {
            throw $exception;
        } finally {
            // Cleanup things
            if ($ch && is_resource($ch)) {
                curl_close($ch);
            }
        }
    }

    /**
     * Decode token and return it's data as associated array
     *
     * @param $token
     * @return mixed
     * @throws \Exception
     */
    protected function decodeToken($token)
    {
        if (!$token) {
            throw new \Exception("Token can not be empty");
        }
        try {
            $accessToken = explode('.', $token)[1];
            return json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $accessToken))));
        } catch (\Exception $exception) {
            throw new \Exception("Provided token have wrong format");
        }
    }
}
