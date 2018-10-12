<?php

namespace Chat;

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
        $this->swooleServer->table = $this->initTable();
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

    }

    public function onReceive(\Swoole\Server $serv, $fd, $fromId, $data)
    {

    }

    public function onClose(\Swoole\Server $serv, $fd)
    {

    }

    private function initTable(): Table
    {
        $table = new \Swoole\Table(1024);
        $table->column('id', Table::TYPE_INT);
        $table->column('name', Table::TYPE_STRING, 64);
        $table->column('login_time', Table::TYPE_INT);
        $table->column('is_login', Table::TYPE_INT);
        $table->create();

        return $table;
    }
}