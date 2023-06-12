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
        switch ($type) {
            case 'mysql':
                if (!array_key_exists('mysql', self::$connectors))
                    self::$connectors['mysql'] = new Mysql((array)$config);
                break;
            case 'mssql':
                if (!array_key_exists('mssql', self::$connectors))
                    self::$connectors['mssql'] = new Mssql((array)$config);
                break;
            case 'postgresql':
                if (!array_key_exists('postgresql', self::$connectors))
                    self::$connectors['postgresql'] = new PostgreSql((array)$config);
                break;
            case 'neo4j_aura':
                if (!array_key_exists('neo4j_aura', self::$connectors))
                    self::$connectors['neo4j_aura'] = new Neo4jAura((array)$config);
                break;
            case 'ssh':
                if (!array_key_exists('ssh', self::$connectors))
                    self::$connectors['ssh'] = new Ssh((array)$config);
                break;
            default:
                return false;
        }
        self::$connectors[$type]->openConnection();
        return self::$connectors[$type];
    }
}
