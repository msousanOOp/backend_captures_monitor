<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Sohris\Core\Utils;

include __DIR__ . "/../bootstrap.php";

function check_register()
{
    $file_path = __DIR__ . "/../config";
    if (!is_dir($file_path)) {
        mkdir($file_path, 0777, true);
        touch($file_path . "/system.json");
    }
    if (empty(file_get_contents($file_path . "/system.json"))) {
        $default_config = [
            "log_folder" => "/app/storage/log",
            "key" => "",
            "api_url" => "",
            "jwr_token" => "",
            "debug" => true
        ];
        file_put_contents($file_path . "/system.json", json_encode($default_config));
    }

    $configs = json_decode(file_get_contents(__DIR__ . "/../config/system.json"), true);

    return !empty($configs['jwt_token']);
}

function get_api_url()
{
    $input = getenv("SNOOP_API_URL");

    if (empty($input)) {
        echo "The SNOOP_API_URL is not defined! Please check this problem, and try again." . PHP_EOL;
        exit(-1);
    }

    $input = parse_url($input);
    $scheme = array_key_exists('scheme', $input) ? $input['scheme'] : 'http';
    $path = array_key_exists('path', $input) ? $input['path'] : '';
    $port = array_key_exists('port', $input) ? ':' . $input['port'] : '';
    $url = $scheme . '://' . $input['host'] . $port . $path;
    echo "Trying Connection to Server ($url)...";
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $url . "/ping");
    curl_setopt($c, CURLOPT_HEADER, 1); //get the header
    curl_setopt($c, CURLOPT_NOBODY, 1); //and *only* get the header
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1); //get the response as a string from curl_exec(), rather than echoing it
    curl_setopt($c, CURLOPT_FRESH_CONNECT, 1); //don't use a cached version of the url
    if (!curl_exec($c)) {
        echo "\r[ERROR] Cannot connect to $url                 " . PHP_EOL;
        echo "Please verify the url or your connection!" . PHP_EOL;
        exit(-1);
    }
    echo "\r[OK] Connection Successfully! ($url)     " . PHP_EOL;
    return $url;
}


function register()
{

    $key = getenv("SNOOP_KEY");

    if (empty($key)) {
        echo "The SNOOP_KEY is not defined! Please check this problem, and try again." . PHP_EOL;
        return false;
    }

    $uri = get_api_url();
    $client = new Client([
        "base_uri" => $uri
    ]);
    try {
        $response = $client->request('POST', '/worker/register', [
            "body" => json_encode(['key' => $key]),
            "headers" => array(
                "Content-Type" => "application/json"
            )
        ]);
    } catch (ClientException $e) {
        echo $e->getMessage() . PHP_EOL;
        exit(-1);
    }
    if ($response->getStatusCode() != "200") {
        echo "\r[ERROR][" . $response->getStatusCode() . "] The SNOOP_KEY is invalid!" . PHP_EOL;
        exit(-1);
    }

    $data = json_decode($response->getBody()->getContents(), true);
    echo "\r[OK] Colletor is register" . PHP_EOL;
    $jwt_token = $data['data'];
    save_system_file($uri, $key, $jwt_token);
}

function run()
{
    exec("php " . __DIR__ . "/../app.php");
}
function save_system_file(string $api, string $key, string $token)
{
    $file_path = realpath(__DIR__ . "/../config");

    $file = json_decode(file_get_contents($file_path . "/system.json"), true);
    $file['key'] = $key;
    $file['api_url'] = $api;
    $file['jwt_token'] = $token;
    $file['log_folder'] = realpath(__DIR__ . "/storage/log");
    file_put_contents($file_path . "/system.json", json_encode($file));
}
function main()
{

    check_register();

    // register();
    // if (!check_register()) {
    //     echo "Can't register this worker!" . PHP_EOL;
    //     exit(-1);
    // }

    run();
}

main();
