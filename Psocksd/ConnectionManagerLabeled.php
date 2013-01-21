<?php

namespace Psocksd;

use ConnectionManager\ConnectionManagerInterface;

class ConnectionManagerLabeled implements ConnectionManagerInterface
{
    private $connectionManager;
    private $label;

    public function __construct(ConnectionManagerInterface $connectionManager, $label)
    {
        $this->connectionManager = $connectionManager;
        $this->label = $label;
    }

    public function getConnection($host, $port)
    {
        return $this->connectionManager->getConnection($host, $port);
    }

    public function __toString()
    {
        return $this->label;
    }
}
