<?php

namespace Magnifico\Cache;

use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Data\ICacheEngine;
use Bitrix\Main\Data\ICacheEngineStat;

class RedisEngine implements ICacheEngine, ICacheEngineStat
{
    /**
     * @var int
     */
    protected $readed = 0;

    /**
     * @var int
     */
    protected $written = 0;

    /**
     * @var string
     */
    protected $prefix = 'bitrix:';

    /**
     * @var string
     */
    protected $suffix = '';

    /**
     * @var string
     */
    protected $maxExpire = 1209600; // 2 weeks

    /**
     * @var \Redis
     */
    protected static $redis;

    /**
     * Constructor.
     */
    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config = Configuration::getValue('cache');
        }

        if (!empty($config['sid'])) {
            $this->prefix .= $config['sid'].':';
        }

        if ($_SESSION['SESS_AUTH']['ADMIN']) {
            $this->suffix .= ':admin';
        }
    }

    /**
     * @return \Redis
     */
    public static function getRedis() : \Redis
    {
        if (null === static::$redis) {
            static::$redis = static::doGetRedis();
        }

        return static::$redis;
    }

    /**
     * @return \Redis
     */
    protected static function doGetRedis() : \Redis
    {
        $factory = function (string $host, string $port) : \Redis {
            $redis = new \Redis();
            $redis->pconnect($host, $port);
            return $redis;
        };

        $config = Configuration::getValue('cache');

        if (!empty($config['redis']) && !empty($config['sentinel'])) {
            throw new \RuntimeException('Keys "redis" and "sentinel" in config cache are mutually exclusive');
        }

        if (!empty($config['redis'])) {
            return $factory((string) $config['redis']['host'], (string) $config['redis']['port']);
        }

        if (!empty($config['sentinel'])) {
            $sentinel = $factory((string) $config['sentinel']['host'], (string) $config['sentinel']['port']);

            $master = $sentinel->rawCommand('SENTINEL', 'get-master-addr-by-name', 'mymaster');

            return $factory((string) $master[0], (string) $master[1]);
        }

        throw new \RuntimeException('Incorrect config detected');
    }

    /**
     * @param \Redis
     */
    public static function setRedis(\Redis $redis)
    {
        static::$redis = $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable()
    {
        return extension_loaded('redis');
    }

    /**
     * {@inheritdoc}
     */
    public function isCacheExpired($path)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getReadBytes()
    {
        return $this->readed;
    }

    /**
     * {@inheritdoc}
     */
    public function getWrittenBytes()
    {
        return $this->written;
    }

    /**
     * {@inheritdoc}
     */
    public function getCachePath()
    {
        return '';
    }

    public function clean($baseDir, $initDir = false, $filename = false)
    {
        $redis = static::getRedis();

        if (!$redis->isConnected()) {
            return false;
        }

        if (strlen($filename)) {
            $baseDirVersion = $redis->get($this->prefix.$baseDir);
            if ($baseDirVersion === false) {
                return true;
            }

            $initDirVersion = $redis->get($this->prefix.$baseDirVersion.':'.$initDir);
            if ($initDirVersion === false) {
                return true;
            }

            $redis->delete($this->prefix.$baseDirVersion.':'.$initDirVersion.':'.$filename.$this->suffix);
        } else {
            if (strlen($initDir)) {
                $baseDirVersion = $redis->get($this->prefix.$baseDir);
                if ($baseDirVersion === false) {
                    return true;
                }

                $redis->delete($this->prefix.$baseDirVersion.':'.$initDir);
            } else {
                $redis->delete($this->prefix.$baseDir);
            }
        }

        return true;
    }

    public function read(&$vars, $baseDir, $initDir, $filename, $TTL)
    {
        $redis = static::getRedis();

        if (!$redis->isConnected()) {
            return false;
        }

        $baseDirVersion = $redis->get($this->prefix.$baseDir);
        if ($baseDirVersion === false) {
            return false;
        }

        if ($initDir !== false) {
            $initDirVersion = $redis->get($this->prefix.$baseDirVersion.':'.$initDir);
            if ($initDirVersion === false) {
                return false;
            }
        } else {
            $initDirVersion = '';
        }

        $serializedVars = $redis->get($this->prefix.$baseDirVersion.':'.$initDirVersion.':'.$filename.$this->suffix);

        if ($serializedVars === false) {
            return false;
        }

        $this->readed = strlen($serializedVars);
        $vars = unserialize($serializedVars);

        return true;
    }

    public function write($vars, $baseDir, $initDir, $filename, $expire)
    {
        $redis = static::getRedis();

        if (!$redis->isConnected()) {
            return false;
        }

        $baseDirVersion = $redis->get($this->prefix.$baseDir);

        if ($baseDirVersion === false) {
            $baseDirVersion = mt_rand();
            if (!$redis->set($this->prefix.$baseDir, $baseDirVersion)) {
                return false;
            }
        }

        if ($initDir !== false) {
            $initDirVersion = $redis->get($this->prefix.$baseDirVersion.':'.$initDir);
            if ($initDirVersion === false) {
                $initDirVersion = mt_rand();
                if (!$redis->set($this->prefix.$baseDirVersion.':'.$initDir, $initDirVersion)) {
                    return false;
                }
            }
        } else {
            $initDirVersion = '';
        }

        $key = $this->prefix.$baseDirVersion.':'.$initDirVersion.':'.$filename.$this->suffix;
        $expire = min($expire, $this->maxExpire);
        $vars = serialize($vars);
        $this->written = strlen($vars);

        $redis->set($key, $vars, $expire);
    }
}
