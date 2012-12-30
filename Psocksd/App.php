<?php

namespace Psocksd;

use ConnectionManager\ConnectionManagerInterface;

class App
{
    private $server;
    private $via;
    private $commands;

    public function __construct()
    {
        $this->commands = array(
            'help' => new Command\Help($this),
            'status' => new Command\Status($this)
        );
    }

    public function run()
    {
        $measureTraffic = true;
        $measureTime = true;

        // $socket = null;
        // $socket = 'socks://me@localhost:9050';
        // $socket = 'localhost:9050';
        $socket = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null;

        $settings = (($socket === null) ? array() : $this->parse($socket)) + array('scheme' => 'socks', 'host' => 'localhost', 'port' => 1050);

        if ($settings['host'] === '*') {
            $settings['host'] = '0.0.0.0';
        }


        $loop = \React\EventLoop\Factory::create();

        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

        $this->via = new ConnectionManagerWrapper(new \ConnectionManager\ConnectionManager($loop, $dns));

        $socket = new \React\Socket\Server($loop);

        $this->server = new \Socks\Server($socket, $loop, $this->via);

        if (preg_match('/^socks(\d\w?)?$/', $settings['scheme'], $match)) {
            if (isset($match[1])) {
                $this->server->setProtocolVersion($match[1]);
            }
        } else {
            throw new \InvalidArgumentException('Invalid socket scheme given');
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

        $args = explode(' ', $line);
        $command = array_shift($args);

        if (isset($this->commands[$command])) {
            $this->commands[$command]->run($args);
        } else {
            echo 'invalid command. type "help"?' . PHP_EOL;
        }
    }

    public function getServer(){
        return $this->server;
    }

    private function setConnectionManager(ConnectionManagerInterface $connectionManager)
    {
        $this->via->setConnectionManager($connectionManager);
    }

    private function parse($socket)
    {
        // workaround parsing plain port numbers
        if (preg_match('/^\d+$/', $socket)) {
            return array('port' => (int)$socket);
        }

        // workaround for incorrect parsing when scheme is missing
        $parts = parse_url((strpos($socket, '://') === false ? 'socks://' : '') . $socket);
        if (!$parts) {
            throw new InvalidArgumentException('Invalid/unparsable socket given');
        }
        if (isset($parts['path']) || isset($parts['query']) || isset($parts['frament'])) {
            throw new InvalidArgumentException('Invalid socket given');
        }
        return $parts;
    }
}
