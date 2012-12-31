<?php

namespace Psocksd;

use ConnectionManager\ConnectionManager;
use Socks\Client;
use ConnectionManager\ConnectionManagerInterface;
use \InvalidArgumentException;

class App
{
    private $server;
    private $loop;
    private $resolver;
    private $via;
    private $commands;

    public function __construct()
    {
        $this->commands = array(
            'help' => new Command\Help($this),
            'status' => new Command\Status($this),
            'via'    => new Command\Via($this)
        );
    }

    public function run()
    {
        $measureTraffic = true;
        $measureTime = true;

        $socket = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'socks://localhost:9050';

        $settings = $this->parseSocksSocket($socket);

        if ($settings['host'] === '*') {
            $settings['host'] = '0.0.0.0';
        }


        $this->loop = $loop = \React\EventLoop\Factory::create();

        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $this->resolver = $dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

        $this->via = new ConnectionManagerWrapper(new \ConnectionManager\ConnectionManager($loop, $dns));

        $socket = new \React\Socket\Server($loop);

        $this->server = new \Socks\Server($socket, $loop, $this->via);

        if (isset($settings['protocolVersion'])) {
            $this->server->setProtocolVersion($settings['protocolVersion']);
        }

        $socket->listen($settings['port'], $settings['host']);

        if (isset($settings['user']) || isset($settings['pass'])) {
            $settings += array('user' => '', 'pass' => '');
            $this->server->setAuthArray(array(
                $settings['user'] => $settings['pass']
            ));
        }

        new Option\Log($this->server);

        if ($measureTraffic) {
            new Option\MeasureTraffic($this->server);
        }

        if ($measureTime) {
            new Option\MeasureTime($this->server);
        }

        echo 'SOCKS proxy server listening on ' . $settings['host'] . ':' . $settings['port'] . PHP_EOL;

        if (defined('STDIN') && is_resource(STDIN)) {
            $that = $this;
            $loop->addReadStream(STDIN, function() use ($that) {
                $line = trim(fgets(STDIN, 4096));
                $that->onReadLine($line);
            });
        }

        $loop->run();
    }

    public function onReadLine($line)
    {
        // nothing entered => skip input
        if ($line === '') {
            return;
        }

        // TODO: properly parse command and its arguments (respect quotes, etc.)
        $args = explode(' ', $line);
        $command = array_shift($args);

        if (isset($this->commands[$command])) {
            $this->commands[$command]->run($args);
        } else {
            echo 'invalid command. type "help"?' . PHP_EOL;
        }
    }

    public function getServer()
    {
        return $this->server;
    }

    public function getResolver()
    {
        return $this->resolver;
    }

    public function getLoop()
    {
        return $this->loop;
    }

    public function getCommands()
    {
        return $this->commands;
    }

    public function setConnectionManager(ConnectionManagerInterface $connectionManager)
    {
        $this->via->setConnectionManager($connectionManager);
    }

    // $socket = 9050;
    // $socket = 'socks://me@localhost:9050';
    // $socket = 'localhost:9050';
    public function parseSocksSocket($socket)
    {
        // workaround parsing plain port numbers
        if (preg_match('/^\d+$/', $socket)) {
            $parts = array('port' => (int)$socket);
        } else {
            // workaround for incorrect parsing when scheme is missing
            $parts = parse_url((strpos($socket, '://') === false ? 'socks://' : '') . $socket);
            if (!$parts) {
                throw new InvalidArgumentException('Invalid/unparsable socket given');
            }
        }
        if (isset($parts['path']) || isset($parts['query']) || isset($parts['frament'])) {
            throw new InvalidArgumentException('Invalid socket given');
        }

        $parts += array('scheme' => 'socks', 'host' => 'localhost', 'port' => 9050);

        if (preg_match('/^socks(\d\w?)?$/', $parts['scheme'], $match)) {
            if (isset($match[1])) {
                $parts['protocolVersion'] = $match[1];
            }
        } else {
            throw new InvalidArgumentException('Invalid socket scheme given');
        }

        return $parts;
    }

    public function reverseSocksSocket($parts)
    {
        $ret = $parts['scheme'] . '://';
        if (isset($parts['user']) || isset($parts['pass'])) {
            $parts += array('user' => '', 'pass' => '');
            $ret .= $parts['user'] . ':' . $parts['pass'] . '@';
        }
        $ret .= $parts['host'] . ':' . $parts['port'];
        return $ret;
    }
}
