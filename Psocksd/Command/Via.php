<?php

namespace Psocksd\Command;

use Psocksd\App;
use Socks\Client;
use ConnectionManager\ConnectionManager;
use ConnectionManager\ConnectionManagerInterface;
use \UnexpectedValueException;
use \Exception;

class Via implements CommandInterface
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function getHelp()
    {
        return 'forward all connections via next SOCKS server';
    }

    public function run($args)
    {
        if (count($args) === 1 && $args[0] === 'list') {
            $this->runList();
        } else if (count($args) === 2 && $args[0] === 'default') {
            $this->runSetDefault($args[1]);
        } else if (count($args) === 3 && $args[0] === 'add') {
            $this->runAdd($args[1], $args[2]);
        } else if (count($args) === 2 && $args[0] === 'remove') {
            $this->runRemove($args[1]);
        } else {
            echo 'error: invalid command arguments ()' . PHP_EOL;
        }
    }

    public function runList()
    {
        $cm = $this->app->getConnectionManager();

        $lenId   = 3;
        $lenHost = 5;
        $lenPort = 5;

        $list = array();
        foreach($cm->getConnectionManagerEntries() as $id=>$entry){
            $list [$id]= $entry;

            if (strlen($id) > $lenId) {
                $lenId = strlen($id);
            }
            if (strlen($entry['host']) > $lenHost) {
                $lenHost = strlen($entry['host']);
            }
            if (strlen($entry['port']) > $lenPort) {
                $lenPort = strlen($entry['port']);
            }
        }

        echo str_pad('Id:', $lenId, ' ') . ' ' . str_pad('Host:', $lenHost, ' ') . ' ' . str_pad('Port:', $lenPort, ' ') . ' ' . 'Target:' . PHP_EOL;
        foreach($list as $id=>$entry){
            echo str_pad($id, $lenId, ' ') . ' ' .
                 str_pad($entry['host'], $lenHost, ' ') . ' ' .
                 str_pad($entry['port'], $lenPort, ' ') . ' ' .
                 $this->dumpConnectionManager($entry['connectionManager']) . PHP_EOL;
        }
    }

    public function runRemove($id)
    {
        $this->app->getConnectionManager()->removeConnectionManagerEntry($id);
    }

    public function runSetDefault($socket)
    {
        // todo: remove all CMs with PRIORITY_DEFAULT

        $via = $this->createConnectionManager($socket);
        $this->app->getConnectionManager()->addConnectionManagerFor($via, '*', '*', App::PRIORITY_DEFAULT);
    }

    public function runAdd($target, $socket)
    {
        $via = $this->createConnectionManager($socket);

        // TODO: support IPv6 addresses
        $parts = explode(':', $target, 2);
        $host = $parts[0];
        $port = isset($parts[1]) ? $parts[1] : '*';

        $this->app->getConnectionManager()->addConnectionManagerFor($via, $host, $port);
    }

    protected function createConnectionManager($socket)
    {
        $direct = new ConnectionManager($this->app->getLoop(), $this->app->getResolver());
        if ($socket === 'none') {
            $via = $direct;

            echo 'use direct connection to target' . PHP_EOL;
        } else {
            try {
                $parsed = $this->app->parseSocksSocket($socket);
            }
            catch (Exception $e) {
                echo 'error: invalid target: ' . $e->getMessage() . PHP_EOL;
                return;
            }

            // TODO: remove hack
            // resolver can not resolve 'localhost' ATM
            if ($parsed['host'] === 'localhost') {
                $parsed['host'] = '127.0.0.1';
            }

            $via = new Client($this->app->getLoop(), $direct, $this->app->getResolver(), $parsed['host'], $parsed['port']);
            if (isset($parsed['protocolVersion'])) {
                try {
                    $via->setProtocolVersion($parsed['protocolVersion']);
                }
                catch (Exception $e) {
                    echo 'error: invalid protocol version: ' . $e->getMessage() . PHP_EOL;
                    return;
                }
            }
            if (isset($parsed['user']) || isset($parsed['pass'])) {
                $parsed += array('user' =>'', 'pass' => '');
                try {
                    $via->setAuth($parsed['user'], $parsed['pass']);
                }
                catch (Exception $e) {
                    echo 'error: invalid authentication info: ' . $e->getMessage() . PHP_EOL;
                    return;
                }
            }

            echo 'use '.$this->app->reverseSocksSocket($parsed) . ' as next hop';

            try {
                $via->setResolveLocal(false);
                echo ' (resolve remotely)';
            }
            catch (UnexpectedValueException $ignore) {
                // ignore in case it's not allowed (SOCKS4 client)
                echo ' (resolve locally)';
            }

            echo PHP_EOL;
        }
        return $via;
    }

    protected function dumpConnectionManager(ConnectionManagerInterface $connectionManager)
    {
        if ($connectionManager instanceof Client) {

        }
        return get_class($connectionManager) . '()';
    }
}
