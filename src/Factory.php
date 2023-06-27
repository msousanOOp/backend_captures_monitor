<?php

namespace App;

use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\Neo4jAura;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;

class Factory
{
    private static $connectors = [];

    public static function getConnector(string $type, array $config)
    {
        $hash = sha1(json_encode($config));
        switch ($type) {
            case 'mysql':
                if (!array_key_exists('mysql', self::$connectors) || !array_key_exists($hash, self::$connectors['mysql']))
                    self::$connectors['mysql'][$hash] = new Mysql((array)$config);
                break;
            case 'mssql':

                if (!array_key_exists('mssql', self::$connectors) || !array_key_exists($hash, self::$connectors['mssql']))
                    self::$connectors['mssql'][$hash] =  new Mssql((array)$config);
                break;
            case 'postgresql':

                if (!array_key_exists('postgresql', self::$connectors) || !array_key_exists($hash, self::$connectors['postgresql']))
                    self::$connectors['postgresql'][$hash] = new PostgreSql((array)$config);
                break;
            case 'neo4j_aura':

                if (!array_key_exists('neo4j_aura', self::$connectors) || !array_key_exists($hash, self::$connectors['neo4j_aura']))
                    self::$connectors['neo4j_aura'][$hash] =  new Neo4jAura((array)$config);
                break;
            case 'ssh':
                if (!array_key_exists('ssh', self::$connectors) || !array_key_exists($hash, self::$connectors['ssh']))
                    self::$connectors['ssh'][$hash] =  new Ssh((array)$config);
                break;
            default:
                return false;
        }
        self::$connectors[$type][$hash]->openConnection();
        return self::$connectors[$type][$hash];
    }
}
