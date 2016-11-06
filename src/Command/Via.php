<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\ConnectionManagerLabeled;
use Clue\Psocksd\App;
use React\SocketClient\ConnectorInterface;
use \InvalidArgumentException;
use \Exception;

class Via
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;

        $that = $this;
        $app->addCommand('via [--help | -h]', function () use ($that) {
            echo 'forward all connections via next SOCKS server' . PHP_EOL;
        });
        $app->addCommand('via list', function () use ($that, $app) {
            $that->runList($app);
        })->shortHelp = 'list all forwarding entries';
        $app->addCommand('via default <target>', function (array $args) use ($that, $app) {
            $that->runSetDefault($args['target'], $app);
        })->shortHelp = 'set given <target> socks proxy as default target';
        $app->addCommand('via reject <host>', function (array $args) use ($that, $app) {
            $that->runAdd($args['host'], 'reject', -1, $app);
        })->shortHelp = 'reject connections to the given host';
        $app->addCommand('via add <host> <target> [<priority>]', function (array $args) use ($that, $app) {
            $that->runAdd($args['host'], $args['target'], isset($args['priority']) ? $args['priority'] : 0, $app);
        })->shortHelp = 'add new <target> socks proxy for connections to given <host>';
        $app->addCommand('via remove <id>', function (array $args) use ($that, $app) {
            $that->runRemove($args['id'], $app);
        })->shortHelp = 'remove forwarding entry with given <id> (see "via list")';
        $app->addCommand('via reset', function (array $args) use ($that, $app) {
            $that->runReset($app);
        })->shortHelp = 'clear and reset all forwarding entries and only connect locally';
    }

    public function runList(App $app)
    {
        $cm = $app->getConnectionManager();

        $lengths = array(
            'id' => 3,
            'host' => 5,
            'port' => 5,
            'priority' => 5
        );

        $pad = '  ';

        $list = array();
        foreach ($cm->getConnectionManagerEntries() as $id => $entry) {
            $list [$id]= $entry;

            $entry['id'] = $id;
            foreach ($lengths as $key => &$value) {
                $l = mb_strlen($entry[$key], 'utf-8');
                if ($l > $value) {
                    $value = $l;
                }
            }
        }

        echo $this->pad('Id:', $lengths['id']) . $pad .
             $this->pad('Host:', $lengths['host']) . $pad .
             $this->pad('Port:', $lengths['port']) . $pad .
             $this->pad('Prio:', $lengths['priority']) . $pad .
             'Target:' . PHP_EOL;
        foreach ($list as $id => $entry) {
            echo $this->pad($id, $lengths['id']) . $pad .
                 $this->pad($entry['host'], $lengths['host']) . $pad .
                 $this->pad($entry['port'], $lengths['port']) . $pad .
                 $this->pad($entry['priority'], $lengths['priority']) . $pad .
                 $this->dumpConnectionManager($entry['connectionManager']) . PHP_EOL;
        }
    }

    public function runRemove($id, App $app)
    {
        $app->getConnectionManager()->removeConnectionManagerEntry($id);
    }

    public function runReset(App $app)
    {
        $cm = $app->getConnectionManager();

        // remove all connection managers
        foreach ($cm->getConnectionManagerEntries() as $id => $entry) {
            $cm->removeConnectionManagerEntry($id);
        }

        // add default connection manager
        $cm->addConnectionManagerFor($app->createConnectionManager('none'), '*', '*', App::PRIORITY_DEFAULT);
    }

    public function runSetDefault($socket, App $app)
    {
        try {
            $via = $app->createConnectionManager($socket);
        }
        catch (Exception $e) {
            echo 'error: invalid target: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        // remove all CMs with PRIORITY_DEFAULT
        $cm = $app->getConnectionManager();
        foreach ($cm->getConnectionManagerEntries() as $id => $entry) {
            if ($entry['priority'] == App::PRIORITY_DEFAULT) {
                $cm->removeConnectionManagerEntry($id);
            }
        }

        $cm->addConnectionManagerFor($via, '*', '*', App::PRIORITY_DEFAULT);
    }

    public function runAdd($target, $socket, $priority, App $app)
    {
        try {
            $via = $app->createConnectionManager($socket);
        }
        catch (Exception $e) {
            echo 'error: invalid target: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        try {
            $priority = $this->coercePriority($priority);
        }
        catch (Exception $e) {
            echo 'error: invalid priority: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        $host = $target;
        $port = '*';

        $colon = strrpos($host, ':');

        // there is a colon and this is the only colon or there's a closing IPv6 bracket right before it
        if ($colon !== false && (strpos($host, ':') === $colon || strpos($host, ']') === ($colon - 1))) {
            $port = (int)substr($host, $colon + 1);
            $host = substr($host, 0, $colon);

            // remove IPv6 square brackets
            if (substr($host, 0, 1) === '[') {
                $host = substr($host, 1, -1);
            }
        }

        $app->getConnectionManager()->addConnectionManagerFor($via, $host, $port, $priority);
    }

    protected function coercePriority($priority)
    {
        $ret = filter_var($priority, FILTER_VALIDATE_FLOAT);
        if ($ret === false) {
            throw new InvalidArgumentException('Invalid priority given');
        }
        return $ret;
    }

    private function pad($str, $len)
    {
        return $str . str_repeat(' ', $len - mb_strlen($str, 'utf-8'));
    }

    protected function dumpConnectionManager(ConnectorInterface $connectionManager)
    {
        if ($connectionManager instanceof ConnectionManagerLabeled) {
            return (string)$connectionManager;
        }
        return get_class($connectionManager) . '(…)';
    }
}
