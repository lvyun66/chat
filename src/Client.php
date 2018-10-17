<?php

namespace Chat;

use Chat\proto\Body;
use Chat\proto\ChatRequest;
use Chat\proto\Info;
use Chat\proto\RequestType;
use Swoole\Process;

class Client
{
    private $swooleClient;

    public function __construct($ip = '127.0.0.1', $port = 9501)
    {
        $this->swooleClient = new \Swoole\Client(SWOOLE_SOCK_TCP);
        $this->swooleClient->connect($ip, $port, 10);
        $this->login();
    }

    public function start()
    {
        echo "请输入要发送消息的好友名称: ";
        $username = $this->input();

        $process = new Process(function(Process $worker) use ($username) {
            while (true) {
                $msg = @$this->swooleClient->recv();
                if (trim($msg)) {
                    echo date('Y-m-d H:i:s', time()), " {$msg}\n";
                }
            }
        });
        $process->start();

        while (true) {
            $msg = $this->input();
            if ($msg) {
                $this->sendMessage($username, $msg);
            }
        }
        $this->close();
    }

    private function login()
    {
        echo "请输入用户名: ";
        $username = $this->input();
        $login = new ChatRequest();
        $login->setType(RequestType::RequestType_LOGIN);
        $info = new Info();
        $info->setUsername($username);
        $login->setInfo($info);
        $login->setSendingTime(time());

        $this->swooleClient->send($login->serializeToJsonString());
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
        $this->swooleClient->send($request->serializeToJsonString());

        return true;
    }

    private function input(): string
    {
        return trim(fgets(STDIN));
    }

    private function close()
    {
        $this->swooleClient->close();
    }
}