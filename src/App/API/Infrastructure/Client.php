<?php

namespace Monitor\App\API\Infrastructure;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\ClientException;
use Monitor\App\API\Domain\Api;
use Monitor\App\API\Domain\Exceptions\EmptyCommands;
use Monitor\App\API\Domain\Interfaces\Repository;
use Monitor\App\Shared\TasksServerHash;
use Monitor\App\Shared\Timer;
use Monitor\App\Task\Domain\Task;
use Monitor\App\TaskResult\Domain\TaskResult;

class Client implements Repository
{
    private function doRequest(Api $api, string $method, string $uri, array $body = [])
    {
        try {
            if (!empty($body))
                $body = ['data' => JWT::encode($body, $api->key(), "HS256")];
            $opt = array(
                "headers" => array(
                    "Content-Type" => "application/json",
                    "Authorization" => "Bearer " . $api->token(),
                ),
                "body" => json_encode($body)
            );
            $client = new GuzzleHttpClient([
                "base_uri" => $api->url(),
            ]);

            $response = $client->request($method, $uri, $opt);

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
                    echo "[ERROR][404] - INVALID URL (" . $api->url() . ") (" . $uri . ")" . PHP_EOL;
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
                $result = JWT::decode($data['data'], new Key($api->key(), "HS256"));
                return (array) $result;
            } catch (SignatureInvalidException $e) {
                echo "[ERROR] Signature Invalid!" . PHP_EOL;
                return false;
            }
        } catch (ClientException $e) {
            return false;
        } catch (Exception $e) {
            echo "[ERROR] Curl Error (" . $e->getMessage() . ")" . PHP_EOL;
            // exit(-1);
            return false;
        }
    }

    public function getServerHashs(Api $api): array
    {
        $result = $this->doRequest($api, 'POST', "v2/worker/task/get_hash");
        if (empty($result)) return [];
        return array_map(fn ($el) => new TasksServerHash($el), $result);
    }

    public function getServerHashsScheduler(Api $api): array
    {
        $result = $this->doRequest($api, 'POST', "v2/worker/scheduler/get_hash");
        if (empty($result)) return [];
        return array_map(fn ($el) => new TasksServerHash($el), $result);
    }

    public function getCommands(Api $api): Task
    {
        $result = $this->doRequest($api, 'POST', "v2/worker/command/next");
        if (empty($result)) throw new EmptyCommands;

        return new Task(
            $result['id'],
            0,
            "",
            $result['connection'],
            (array)$result['connection_config'],
            $result['command'],
            new Timer(0, Timer::INSTANTE),
            "command"
        );
    }

    public function sendTaskResult(Api $api, TaskResult $result): void
    {
        $variables = $result->toArray();
        $variables['client_version'] = $api->version();
        $this->doRequest($api, 'POST', "v2/worker/control/enqueue", $variables);
    }

    public function sendStatistics(Api $api, array $stats): void
    {
        $stats['client_version'] = $api->version();
        $this->doRequest($api, 'POST', "v2/worker/control/save_stats", $stats);
    }

    public function getTasksConfiguration(Api $api, TasksServerHash $hash): array
    {

        $result = $this->doRequest($api, 'POST', "v2/worker/control/get_config", ['hash' => $hash->hash()]);
        return $result;
    }

    public function getTaskConfig(Api $api, int $instance, string $service, int $task): array
    {
        return $this->doRequest($api, 'POST', "v2/worker/control/get_task_config", ['instance' => $instance, 'service' => $service, 'task' => $task]);
    }

    public function sendLog(Api $api, string $id, string $level, int $timestamp, array $params): void
    {
        $params = [
            "ref_id" => $id,
            "level" => $level,
            "timestamp" => $timestamp,
            "params" => $params
        ];

        $this->doRequest($api, "POST", "v2/worker/control/log", $params);
    }
}
