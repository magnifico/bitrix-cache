<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

class ConfigurationMock
{
    public static function getValue(string $section)
    {
        if ('cache' === $section) {
            return [
                'sid' => 'v3',
                'redis' => [
                    'port' => '6379',
                    'host' => 'redis',
                ],
            ];
        }
    }
}

class_alias('ConfigurationMock', 'Bitrix\Main\Config\Configuration');

interface ICacheEngineMock
{

}

class_alias('ICacheEngineMock', 'Bitrix\Main\Data\ICacheEngine');


interface ICacheEngineStatMock
{

}

class_alias('ICacheEngineStatMock', 'Bitrix\Main\Data\ICacheEngineStat');
