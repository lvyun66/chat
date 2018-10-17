<?php

namespace Chat;

use Chat\proto\Body;
use Chat\proto\ChatRequest;
use Chat\proto\Info;
use Chat\proto\RequestType;
use Swoole\Table;

class Server
{
    private $swooleServer;

    public function __construct($ip = '127.0.0.1', $port = 9501)
    {
        $this->swooleServer = new \Swoole\Server($ip, $port);
        $this->swooleServer->set([
            'worker_num' => 2,
            'daemonize' => false,
        ]);
        $this->swooleServer->connectTable = $this->connectTable();
        $this->swooleServer->onlineTable = $this->onlineTable();
    }

    public function start()
    {
        $this->swooleServer->on('connect', [$this, 'onConnect']);
        $this->swooleServer->on('receive', [$this, 'onReceive']);
        $this->swooleServer->on('close', [$this, 'onClose']);
        $this->swooleServer->start();
    }


    public function onConnect(\Swoole\Server $serv, $fd)
    {
        /** @var Table $connect */
        $this->swooleServer->connectTable->set($fd, [
            'id' => $fd,
            'name' => 'default',
            'login_time' => time(),
            'is_login' => 0,
        ]);
        /** @var Table $online */
        print_r("Current online count: {$this->swooleServer->onlineTable->count()}");

    }

    public function onReceive(\Swoole\Server $serv, $fd, $fromId, $data)
    {
        if (!trim($data)) {
            return;
        }
        if (!$this->isJson($data)) {
            $serv->send($fd, "incorrect data format: $data\n");
            return;
        }
        print_r("Receive $fd data: $data \n");
        $request = new ChatRequest();
        $request->mergeFromJsonString($data);
        switch ($request->getType()) {
            case RequestType::RequestType_LOGIN:
                $this->login($request->getInfo(), $fd);
                break;
            case RequestType::RequestType_MESSAGE:
                $this->sendMessage($request->getBody(), $serv);
                break;
            default:
                break;
        }
    }

    public function onClose(\Swoole\Server $serv, $fd)
    {
        /** @var Table $connect */
        $connect = $this->swooleServer->connectTable;
        $loginInfo = $connect->get($fd);
        $connect->del($fd);
        if ($loginInfo['is_login']) {
            /** @var Table $online */
            $online = $this->swooleServer->onlineTable;
            $online->del($loginInfo['name']);
        }
    }

    private function login(Info $info, $fd): bool
    {
        if (!$info->getUsername()) {
            return false;
        }
        print_r("Logging in user: {$info->getUsername()}\n");
        $userInfo = [
            'id' => uniqid(),
            'name' => $info->getUsername(),
            'login_time' => time(),
            'client_id' => $fd,
        ];
        return $this->swooleServer->onlineTable->set($info->getUsername(), $userInfo);
    }

    private function sendMessage(Body $body, \Swoole\Server $serv): bool
    {
        /** @var Table $online */
        $online = $this->swooleServer->onlineTable;
        if (!$online->exist($body->getTo())) {
            print_r("User {$body->getTo()} is not exist\n");
            return false;
        }
        $info = $online->get($body->getTo());
        print_r("Send to user info: " . json_encode($info) . "\n");
        return $serv->send($info['client_id'], $body->getMsg());
    }

    private function isJson(string $data): bool
    {
        json_decode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return true;
    }

    private function connectTable(): Table
    {
        $table = new \Swoole\Table(1024);
        $table->column('id', Table::TYPE_INT);
        $table->column('name', Table::TYPE_STRING, 64);
        $table->column('login_time', Table::TYPE_INT);
        $table->column('is_login', Table::TYPE_INT);
        $table->create();

        return $table;
    }

    private function onlineTable(): Table
    {
        $table = new Table(1024);
        $table->column('id', Table::TYPE_INT);
        $table->column('name', Table::TYPE_STRING, 64);
        $table->column('login_time', Table::TYPE_INT);
        $table->column('client_id', Table::TYPE_INT);
        $table->create();

        return $table;
    }
}