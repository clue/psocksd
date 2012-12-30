<?php

namespace Psocksd;

use ConnectionManager\ConnectionManagerInterface;

class ConnectionManagerWrapper implements ConnectionManagerInterface
{
    protected $connectionManager;

    public function __construct(ConnectionManagerInterface $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    public function getConnection($host, $port)
    {
        return $this->connectionManager->getConnection($host, $port);
    }

    public function setConnectionManager(ConnectionManagerInterface $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }
}
