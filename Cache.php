<?php


namespace Cymatic;


class Cache
{
    // Types of supported cache frameworks
    public static $CACHE_TYPE_APC = 'CACHE_TYPE_APC';
    public static $CACHE_TYPE_MEMCACHED = 'CACHE_TYPE_MEMCACHED';
    public static $CACHE_TYPE_REDIS = 'CACHE_TYPE_REDIS';

    /**
     * User selected cache type
     *
     * @var string
     */
    protected $cacheType;

    /**
     * @var object
     */
    protected $cacheInstance = null;

    /**
     * Cache constructor.
     *
     * @param $cacheType
     * @param array $cacheSettings
     * @throws \Exception
     */
    public function __construct($cacheType, $cacheSettings = array())
    {
        $this->cacheType = $cacheType;
        switch ($this->cacheType) {
            case Cache::$CACHE_TYPE_APC:
                if (!function_exists('apc_fetch')) {
                    throw new \Exception("php_apc extension is not installed");
                }
                break;

            case Cache::$CACHE_TYPE_MEMCACHED:
                if (!class_exists('Memcached')) {
                    throw new \Exception("Memcached class is not available, please install php_memcached extension");
                }
                $this->cacheInstance = new Memcached();
                $this->cacheInstance->addServer(...$cacheSettings);
                break;

            case Cache::$CACHE_TYPE_REDIS:
                if (!class_exists('Redis')) {
                    throw new \Exception("Redis class is not available, please install php_redis extension");
                }
                $this->cacheInstance = new Redis();
                $this->cacheInstance->connect(...$cacheSettings);
                break;
        }
    }

    /**
     * Set cache
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        switch ($this->cacheType) {
            case Cache::$CACHE_TYPE_APC:
                apc_add($name, $value);
                break;

            case Cache::$CACHE_TYPE_MEMCACHED:
            case Cache::$CACHE_TYPE_REDIS:
                $this->cacheInstance->set($name, $value);
                break;
        }
    }

    /**
     * Return cache if exists
     *
     * @param $name
     * @return string
     */
    public function __get($name)
    {
        switch ($this->cacheType) {
            case Cache::$CACHE_TYPE_APC:
                return apc_fetch($name);

            case Cache::$CACHE_TYPE_MEMCACHED:
            case Cache::$CACHE_TYPE_REDIS:
                return $this->cacheInstance->get($name);
        }
        return '';
    }
}
