<?php

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Sohris\Core\Utils as CoreUtils;

final class Utils
{
    private static $key;

    public static function getConfigs($server): array
    {

        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }

        $key = sha1($server);
        $path = __DIR__ . "/../storage/cache/";
        if (file_exists($path . $key)) {

            $content = file_get_contents($path . $key);
            $decoder = JWT::decode($content, new Key(self::$key, "HS256"));
            return (array) $decoder;
        }

        return [];
    }
    public static function saveServerConfig($server, $content)
    {

        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }

        $key = sha1($server);
        $path = __DIR__ . "/../storage/cache/";

        CoreUtils::checkFolder($path, 'create');
        file_put_contents($path . $key, JWT::encode($content, self::$key, "HS256"));

    }

    public static function saveServers($servers = [])
    {

        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }
        $key = "servers";
        $path = __DIR__ . "/../storage/";

        CoreUtils::checkFolder($path, 'create');
        file_put_contents($path . $key, JWT::encode($servers, self::$key, "HS256"));

    }

    public static function getServers(): array
    {

        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }

        $key = "servers";
        $path = __DIR__ . "/../storage/";
        if (file_exists($path . $key)) {
            $content = file_get_contents($path . $key);
            $decoder = JWT::decode($content, new Key(self::$key, "HS256"));
            return (array) $decoder;
        }

        return [];
    }
}
