<?php

namespace App;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Sohris\Core\Utils;

class API
{

    static $key;
    static $api_url;
    static $jwt_token;
    static $client;

    private static function configureAPI()
    {
        if (!self::$key || !self::$api_url || !self::$jwt_token) {
            self::$key = Utils::getConfigFiles('system')['key'];
            self::$api_url = Utils::getConfigFiles('system')['api_url'];
            self::$jwt_token = Utils::getConfigFiles('system')['jwt_token'];
        }

        if (!self::$client) {
            self::$client = new Client([
                "base_uri" => self::$api_url,
            ]);
        }
    }

    private static function doRequest(string $method, string $uri, array $body = [])
    {

        self::configureAPI();
        try {
            if (!empty($body))
                $body = ['data' => JWT::encode($body, self::$key, "HS256")];
            $opt = array(
                "headers" => array(
                    "Content-Type" => "application/json",
                    "Authorization" => "Bearer " . self::$jwt_token,
                ),
                "body" => json_encode($body)
            );
            $response = self::$client->request($method, $uri, $opt);
            $code = $response->getStatusCode();
            switch ($code) {
                case "400":
                    echo "[ERROR]" . PHP_EOL;
                    return false;
                    break;
                case "401":
                    echo "[ERROR][401] - " . PHP_EOL;
                    return false;
                    break;
                case "403":
                    echo "[ERROR][403] - INVALID TOKEN" . PHP_EOL;
                    return false;
                    break;
                case "404":
                    echo "[ERROR][404] - INVALID URL (" . self::$api_url . ") (" . $uri . ")" . PHP_EOL;
                    return false;
                    break;
                case "500":
                    echo "[ERROR]" . PHP_EOL;
                    return false;
                    break;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            try {
                if (empty($data['data'])) return false;
                $result = JWT::decode($data['data'], new Key(self::$key, "HS256"));
                return (array) $result;
            } catch (SignatureInvalidException $e) {
                echo "[ERROR] Signature Invalid!" . PHP_EOL;
                return false;
            }
        } catch (ClientException $e) {
            return false;
        } catch( Exception $e)
        {
            echo "[ERROR] Curl Error (".$e->getMessage().")" . PHP_EOL;
            exit(-1);
        }
    }

    public static function getNextTasks()
    {
        self::configureAPI();

        $tasks = self::doRequest('POST', "worker/next_job");
        return (array) $tasks;
    }

    public static function sendResults($results)
    {
        self::configureAPI();

        self::doRequest('POST', "worker/enqueue_task", $results);
    }

    public static function getServerConfig($server, $service)
    {
        self::configureAPI();
        return self::doRequest('POST', "worker/get_server_config", ['server' => $server, 'service' => $service]);
    }
}
