<?php

namespace Chat;

use Chat\proto\Body;
use Chat\proto\ChatRequest;
use Chat\proto\Info;
use Chat\proto\RequestType;
use Swoole\Process;

class ClientAsync
{
    private $client;
    private $host;
    private $port;

    public function __construct($host = '127.0.0.1', $port = 9501)
    {
        $this->host = $host;
        $this->port = $port;
        $this->client = new \Swoole\Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        swoole_set_process_name('client-async:master');
        $this->signal();
    }

    public function start()
    {
        $this->client->on('connect', [$this, 'onConnect']);
        $this->client->on('receive', [$this, 'onReceive']);
        $this->client->on('close', [$this, 'onClose']);
        $this->client->on('error', [$this, 'onError']);
        $this->client->connect($this->host, $this->port);
    }

    public function onConnect(\Swoole\Client $client)
    {
        $this->login();

        do {
            echo "请输入要发送消息的好友名称: ";
        } while (!$username = $this->input());

        $process = new Process(function(Process $worker) use ($username) {
            swoole_set_process_name('client-async:worker');
            while (true) {
                $msg = $this->input();
                if ($msg) {
                    $this->sendMessage($username, $msg);
                }
            }
        });
        $process->start();
        $GLOBALS['child'] = $process;

        $currentPid = posix_getpid();
        $this->output("Master process pid(1): {$currentPid}\n");
        $this->output("Worker process pid(2): {$process->pid}\n");
    }

    public function onReceive(\Swoole\Client $client, $data)
    {
        echo date('Y-m-d H:i:s', time()), " {$data}\n";
    }

    public function onClose(\Swoole\Client $client)
    {
        $this->output('Client close');
    }

    public function onError(\Swoole\Client $client)
    {
        $this->output('Swoole client error: ' . $client->errCode);
    }

    private function login()
    {
        do {
            echo "请输入用户名: ";
        } while (!$username = $this->input());

        $login = new ChatRequest();
        $login->setType(RequestType::RequestType_LOGIN);
        $info = new Info();
        $info->setUsername($username);
        $login->setInfo($info);
        $login->setSendingTime(time());

        $this->client->send($login->serializeToJsonString());
    }

    private function sendMessage($to, $msg): bool
    {
        $request = new ChatRequest();
        $request->setType(RequestType::RequestType_MESSAGE);
        $body = new Body();
        $body->setTo($to);
        $body->setMsg($msg);
        $request->setBody($body);
        $request->setSendingTime(time());
        $this->client->send($request->serializeToJsonString());

        return true;
    }

    private function input(): string
    {
        return trim(fgets(STDIN));
    }

    private function output($msg)
    {
        echo $this->now(), ' ', $msg;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s', time());
    }

    private function signal()
    {
        Process::signal(SIGTERM, function($signal) {
            global $child;
            Process::kill($child->pid);
            Process::wait();
            exit();
        });
    }
}