<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Sohris\Core\Utils;


include __DIR__ . "/../bootstrap.php";


function get_input(string $entry = "", string $regex = "", string $tip = "", string $env = "")
{
    $valid = false;
    if (!empty($env))
        $input = getenv($env);
    do {
        if (empty($input))
            $input = readline($entry);
        if (empty($regex) || preg_match($regex, $input))
            $valid = true;
        else {
            $input = "";
            echo "Invalid Input! " . (!empty($tip) ? "($tip)" : "") . PHP_EOL;
        }
    } while (!$valid);

    return $input;
}

function get_api_url()
{
    $valid = false;
    $input = getenv("SNOOP_API");
    do {
        if (empty($input))
            $input = readline("URL: ");
        if (!filter_var($input, FILTER_VALIDATE_URL)) {
            echo "Invalid URL format" . PHP_EOL;
            $input = false;
        } else $valid = true;
    } while (!$valid);

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


function main()
{
    //define API and Key access
    echo "Entry the dbsnOOp colletor api (e.q: http://api.dbsnoop.com):" . PHP_EOL;
    $api = get_api_url();
    $key = get_input("Key: ", "/[a-zA-Z0-9]{40}/");
    $token = get_token($api, $key);
    save_system_file($api, $key, $token);
}


main();
