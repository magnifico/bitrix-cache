<?php

namespace Magnifico\Cache\Test;

use Prophecy\Argument;
use Magnifico\Cache\RedisEngine;

class RedisEngineTest extends \PHPUnit_Framework_TestCase
{
    protected $savedRedis;

    protected function setUp()
    {
        parent::setUp();
        $this->savedRedis = RedisEngine::getRedis();
    }

    protected function tearDown()
    {
        RedisEngine::setRedis($this->savedRedis);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function setRedis()
    {
        $redis = $this->prophesize('Redis');
        $return = RedisEngine::setRedis($redis->reveal());
        $this->assertNull($return);
    }

    /**
     * @test
     */
    public function read()
    {
        $vars = null;
        $engine = new RedisEngine(['sid' => 'v1']);

        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(false);
        RedisEngine::setRedis($redis->reveal());
        $this->assertFalse($engine->read($vars, 'base/', 'init/', 'filename', 700));
        $this->assertNull($vars);

        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(true);
        $redis->get('bitrix:v1:base/')->willReturn(false);
        RedisEngine::setRedis($redis->reveal());
        $this->assertFalse($engine->read($vars, 'base/', 'init/', 'filename', 700));
        $this->assertNull($vars);

        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(true);
        $redis->get('bitrix:v1:base/')->willReturn('base_version');
        $redis->get('bitrix:v1:base_version:init/')->willReturn(false);
        RedisEngine::setRedis($redis->reveal());
        $this->assertFalse($engine->read($vars, 'base/', 'init/', 'filename', 700));
        $this->assertNull($vars);

        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(true);
        $redis->get('bitrix:v1:base/')->willReturn('base_version');
        $redis->get('bitrix:v1:base_version:init/')->willReturn('init_version');
        $redis->get('bitrix:v1:base_version:init_version:filename')->willReturn(false);
        RedisEngine::setRedis($redis->reveal());
        $this->assertFalse($engine->read($vars, 'base/', 'init/', 'filename', 700));
        $this->assertNull($vars);

        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(true);
        $redis->get('bitrix:v1:base/')->willReturn('base_version');
        $redis->get('bitrix:v1:base_version:init/')->willReturn('init_version');
        $redis->get('bitrix:v1:base_version:init_version:filename')->willReturn('a:1:{s:3:"foo";s:3:"bar";}');
        RedisEngine::setRedis($redis->reveal());
        $this->assertTrue($engine->read($vars, 'base/', 'init/', 'filename', 700));
        $this->assertEquals(['foo' => 'bar'], $vars);
    }

    /**
     * @test
     */
    public function write()
    {
        $vars = ['foo' => 'bar'];
        $engine = new RedisEngine(['sid' => 'v1']);

        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(false);
        RedisEngine::setRedis($redis->reveal());
        $this->assertFalse($engine->write($vars, 'base/', 'init/', 'filename', 700));

        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(true);
        $redis->get('bitrix:v1:base/')->willReturn(false);
        $redis->set('bitrix:v1:base/', Argument::any())->willReturn(false);
        RedisEngine::setRedis($redis->reveal());
        $this->assertFalse($engine->write($vars, 'base/', 'init/', 'filename', 700));

        // can add more tests because of mt_rand() in baseDir\initDir versions
    }

    /**
     * @test
     */
    public function ttlReducedIfItsExceedLimit()
    {
        $redis = $this->prophesize('Redis');
        $redis->isConnected()->willReturn(true);
        $redis->get('bitrix:v1:base/')->willReturn('base_version');
        $redis->get('bitrix:v1:base_version:init/')->willReturn('init_version');
        $redis->set('bitrix:v1:base_version:init_version:original_ttl', 'i:42;', 700)->shouldBeCalled();
        $redis->set('bitrix:v1:base_version:init_version:reduced_ttl', 'i:42;', 1209600)->shouldBeCalled();

        $engine = new RedisEngine(['sid' => 'v1']);
        RedisEngine::setRedis($redis->reveal());

        $engine->write(42, 'base/', 'init/', 'original_ttl', 700);
        $engine->write(42, 'base/', 'init/', 'reduced_ttl', 3600 * 24 * 365);
    }
}
