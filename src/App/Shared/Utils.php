<?php

namespace Monitor\App\Shared;

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

    public static function objectToArray($obj)
    {
        //only process if it's an object or array being passed to the function
        if (is_object($obj) || is_array($obj)) {
            $ret = (array) $obj;
            foreach ($ret as &$item) {
                //recursively process EACH element regardless of type
                $item = self::objectToArray($item);
            }
            return $ret;
        }
        //otherwise (i.e. for scalar values) return without modification
        else {
            return $obj;
        }
    }

    public static function trimQuery($query)
    {
        $query = preg_replace('/--(.+)/', ' ', $query);
        $query = preg_replace('/(\/\*(.+)\*\/)/', ' ', $query);
        $query = preg_replace('/, /', ',', $query);
        $query = preg_replace('/([\n\t])/', ' ', $query);
        $query = preg_replace('/(\s\s+)/', ' ', $query);
        return $query;
    }

    public static function isSelect($query)
    {
        return preg_match('/^(SELECT\s+?[^\s]+?\s+?FROM.*)/', $query, $output_array) !== false;
    }

    public static function convertTextArray($info = '', $explode = "|")
    {

        $result = explode("\n", trim($info));
        return array_map(function ($el) use ($explode) {
            $r = explode($explode, $el);
            if (empty($r[array_key_last($r)]))
                array_pop($r);
            return $r;
        }, $result);
    }

    public static function rrmdir($dir)
    {

        if (is_dir($dir)) {

            $objects = scandir($dir);

            foreach ($objects as $object) {

                if ($object != "." && $object != "..") {

                    if (filetype($dir . "/" . $object) == "dir") self::rrmdir($dir . "/" . $object);
                    else unlink($dir . "/" . $object);
                }
            }

            reset($objects);

            rmdir($dir);
        }
    }
}
