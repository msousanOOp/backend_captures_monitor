<?php

namespace App;

use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\Neo4jAura;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;

class Factory
{

    public static function getConnector(string $type, array $config)
    {
        $connector = null;
        switch ($type) {
            case 'mysql':
                    $connector = new Mysql((array)$config);
                break;
            case 'mssql':
                $connector =  new Mssql((array)$config);
                break;
            case 'postgresql':
                $connector = new PostgreSql((array)$config);
                break;
            case 'neo4j_aura':
                $connector =  new Neo4jAura((array)$config);
                break;
            case 'ssh':
                $connector =  new Ssh((array)$config);
                break;
            default:
                return false;
        }
        $connector->openConnection();
        return $connector;
    }
}
