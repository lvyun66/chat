<?php

namespace Chat;

class Client
{
    private $swooleClient;

    public function __construct($ip = '127.0.0.1', $port = 9502)
    {
        $this->swooleClient = new \Swoole\Client(SWOOLE_SOCK_TCP);
        $this->swooleClient->connect($ip, $port, 10);
    }

    public function sendMessage($str)
    {
        $this->swooleClient->send($str);
        $data = $this->swooleClient->recv();

        echo $data;
    }

    public function close()
    {
        $this->swooleClient->close();
    }
}