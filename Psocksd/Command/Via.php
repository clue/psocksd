<?php

namespace Psocksd\Command;

use Psocksd\App;
use Socks\Client;
use ConnectionManager\ConnectionManager;
use \UnexpectedValueException;

class Via implements CommandInterface
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function run($args)
    {
        if (count($args) !== 1) {
            echo 'error: command requires one argument (target socks server or "none")'.PHP_EOL;
            return;
        }

        $socket = $args[0];

        $direct = new ConnectionManager($this->app->getLoop(), $this->app->getResolver());
        if ($socket === 'none') {
            $via = $direct;

            echo 'use direct connection to target' . PHP_EOL;
        } else {
            $parsed = $this->app->parseSocksSocket($socket);

            // TODO: remove hack
            // resolver can not resolve 'localhost' ATM
            if ($parsed['host'] === 'localhost') {
                $parsed['host'] = '127.0.0.1';
            }

            $via = new Client($this->app->getLoop(), $direct, $this->app->getResolver(), $parsed['host'], $parsed['port']);
            if (isset($parsed['protocolVersion'])) {
                $via->setProtocolVersion($parsed['protocolVersion']);
            }
            if (isset($parsed['user']) || isset($parsed['pass'])) {
                $parsed += array('user' =>'', 'pass' => '');
                $via->setAuth($parsed['user'], $parsed['pass']);
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

            $this->pingEcho($via, 'www.google.com', 80);
        }
        $this->app->setConnectionManager($via);
    }

    public function getHelp()
    {
        return 'forward all connections via next SOCKS server';
    }

    public function pingEcho($via, $host, $port)
    {
        return $this->ping($via, $host, $port)->then(function ($time) {
            echo 'ping test OK (⌚ ' . round($time, 3).'s)' . PHP_EOL;
            return $time;
        }, function ($error) {
            $msg = $error->getMessage();
            echo 'ping test FAILED: ' . $msg . PHP_EOL;
            throw $error;
        });
    }

    public function ping($via, $host, $port)
    {
        $start = microtime(true);
        return $via->getConnection($host, $port)->then(function ($stream) use ($start) {
            $stop = microtime(true);
            $stream->close();
            return ($stop - $start);
        });
    }
}
