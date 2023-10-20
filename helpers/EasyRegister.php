<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Sohris\Core\Utils;


include __DIR__ . "/../bootstrap.php";

function get_token(string $api, string $key)
{
    echo "Validate Key...";
    $client = new Client([
        "base_uri" => $api
    ]);
    $hostname = gethostname();

    $response = $client->request('POST', '/worker/register', [
        "body" => json_encode(['key' => $key, 'hostname' => $hostname]),
        "headers" => array(
            "Content-Type" => "application/json"
        )
    ]);

    if ($response->getStatusCode() != "200") {
        echo "\r[ERROR][" . $response->getStatusCode() . "] The key is invalid!" . PHP_EOL;
        exit(-1);
    }

    $data = json_decode($response->getBody()->getContents(), true);
    echo "\r[OK] Colletor is register ($hostname)" . PHP_EOL;
    return $data['data'];
}

function save_system_file(string $api, string $key, string $token)
{
    $file_path = __DIR__ . "/../config/system.json";
    $file = json_decode(file_get_contents($file_path), true);
    $file['key'] = $key;
    $file['api_url'] = $api;
    $file['jwt_token'] = $token;
    $file['log_folder'] = realpath(__DIR__ . "/storage/log");
    file_put_contents($file_path, json_encode($file));
}

function save_database_info()
{

    echo "Updating Servers...";
    $file_path = __DIR__ . "/../config/system.json";
    $file = json_decode(file_get_contents($file_path), true);

    $client = new Client([
        "base_uri" => $file['api_url']
    ]);

    $response = $client->request('POST', '/worker/get_servers_config', [
        "headers" => array(
            "Content-Type" => "application/JSON",
            "Authorization" => "Bearer " . $file['jwt_token'],
        )
    ]);

    $info = json_decode($response->getBody()->getContents(), true);
    $count = 0;
    if (!empty($info['data'])) {
        $decoder = JWT::decode($info['data'], new Key($file['key'], "HS256"));
        Utils::checkFolder(__DIR__ . "/../storage/cache/", "create");
        foreach ($decoder as $key => $server) {
            $code_key =  sha1($key);
            $encode = JWT::encode((array)$server, $file['key'], "HS256");
            file_put_contents(__DIR__ . "/../storage/cache/" . $code_key, $encode);
            $count++;
        }
    }
    echo "\r[OK] Updated Servers (" . $count . ")" . PHP_EOL;
}

function main($argv)
{
    //define API and Key access
    $api = $argv[1];
    $key = $argv[2];
    $token = get_token($api, $key);
    save_system_file($api, $key, $token);
    save_database_info();
    file_put_contents(__DIR__ . "/REG_INFO", "ok");
}


main($argv);
