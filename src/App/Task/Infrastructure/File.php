<?php

namespace Monitor\App\Task\Infrastructure;

use Monitor\App\TaskResult\Domain\TaskResult;
use Exception;
use Monitor\App\Shared\Utils;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Throwable;

class File extends Collector
{

    const CONNECTOR_NAME = "file";

    private string $host;
    private string $user;
    private string $password;
    private string $port;
    private int $offset;
    private int $length = 1000;
    private string $file_path = "";

    private SFTP $connection;

    public function setConfig(array $config): void
    {
        list(
            "ssh_host_ip" => $host,
            "ssh_host_port" => $port,
            "ssh_user" => $user,
            "ssh_password" => $pass,
            "offset" => $offset,
            "length" => $length
        ) = $config;

        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $pass;
        $this->offset = $offset;
        $this->length = $length;

        $this->setHash(sha1(json_encode($config) . self::CONNECTOR_NAME));
    }

    public function connect(): void
    {
        if ($this->hasConnection()) {
            $this->connection = $this->getConnection();
            $this->isConnected();
            return;
        }
        try {
            $this->connection = new SFTP($this->host, $this->port);
            $keys = PublicKeyLoader::load($this->password);
            $this->connection->login($this->user, $keys);
            $this->setConnection($this->connection);
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->invalidate();
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->invalidate();
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        }
        return;
    }

    public function isConnected(): void
    {
        $this->connection->ping();
    }

    public function invalidate(): void
    {
        $this->deleteConnection();
    }

    public function run(string $task_id, string $command): TaskResult
    {
        $task_result = new TaskResult($task_id, self::CONNECTOR_NAME);
        try {
            $task_result->startTimer("connection_time_" . self::CONNECTOR_NAME);
            $this->connect();
            $task_result->finishTimer("connection_time_" . self::CONNECTOR_NAME);

            $task_result->startTimer("task_$task_id");
            if (!$this->connection->file_exists($command)) throw new Exception("FILE_NOT_EXISTS ($command)");
            if (!$this->connection->is_readable($command)) throw new Exception("FILE_IS_NOT_READABLE ($command)");

            $filesize = $this->connection->filesize($command);

            if ($filesize < $this->offset) $this->offset = $filesize - $this->length;
            if ($filesize == $this->offset) throw new Exception("END_OF_FILE ($command)");
            if ($this->offset < 0) $this->offset = 0;

            $last_access = $this->connection->fileatime($command);
            $last_modified = $this->connection->filemtime($command);

            $string = $this->connection->get($command, false, $this->offset, $this->length);
            $last_char = substr($string, strlen($string));
            $valid_last = true;
            if ($last_char == "\n" || $last_char == "\r")
                $valid_last = false;
            $str_lines = preg_split("/((\r?\n)|(\r\n?))/", $string);
            $total_lines = count($str_lines);
            $bytes = 0;
            $result = [];
            foreach ($str_lines as $key => $line) {
                if ($key == 0) continue;
                if ($valid_last && $key == $total_lines - 1) break;
                $bytes += strlen($line);
                $result[] = $line;
            }

            $stm = [
                "path" => $command,
                "filesize" => $filesize,
                "last_access" => $last_access,
                "last_modified" => $last_modified,
                "offset" => $this->offset + $bytes + 1,
                "readed" => $bytes + 1,
                "content" => $result
            ];

            $task_result->finishTimer("task_$task_id");
            $task_result->setResult($stm);
            $task_result->setStatus("successfully");
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        }
        $task_result->finish();
        return $task_result;
    }
}
