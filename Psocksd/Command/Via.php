<?php

namespace Psocksd\Command;

use Psocksd\App;
use Socks\Client;
use ConnectionManager\ConnectionManager;
use \UnexpectedValueException;
use \Exception;

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
        $this->app->setConnectionManager($via);
    }

    public function getHelp()
    {
        return 'forward all connections via next SOCKS server';
    }
}
