<?php

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Sohris\Core\Utils as CoreUtils;

final class Utils
{
    private static $key;

    public static function getConfigs($server, $service): array
    {

        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }

        $key = sha1($server . "_" . $service);
        $path = __DIR__ . "/../storage/cache/";
        if (file_exists($path . $key)) {

            $content = file_get_contents($path . $key);
            $decoder = JWT::decode($content, new Key(self::$key, "HS256"));
            return (array) $decoder;
        }

        $external = (array)API::getServerConfig($server, $service);
        CoreUtils::checkFolder($path, 'create');
        file_put_contents($path . $key, JWT::encode($external, self::$key, "HS256"));

        return (array) $external;
    }
}
