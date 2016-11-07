<?php

namespace Clue\Psocksd;

use Clue\React\Socks\Client;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use ConnectionManager\Extra\Multiple\ConnectionManagerSelective;
use ConnectionManager\Extra\ConnectionManagerReject;
use Clue\Arguments;
use Clue\Commander\Router;
use \InvalidArgumentException;
use \Exception;
use Clue\Commander\NoRouteFoundException;

class App
{
    private $server;
    private $loop;
    private $resolver;
    private $via;
    private $commander;

    const PRIORITY_DEFAULT = 100;

    public function __construct()
    {
        $this->commander = new Router();

        // nothing entered, skip input
        $this->commander->add('', function () { });

        // initialize all available sub-commands
        new Command\Help($this);
        new Command\Status($this);
        new Command\Via($this);
        new Command\Ping($this);
        new Command\Quit($this);
    }

    public function run(array $argv = null)
    {
        $that = $this;
        $commander = new Router();
        $main = $commander->add('[<socket>] [--no-interaction | -n]', function ($args) use ($that) {
            $that->start($args);
        });
        $commander->add('[--help | -h]', function () use ($main) {
            $bin = isset($_SERVER['argv'][0]) ? $_SERVER['argv'][0] : 'psocksd';
            echo 'Welcome to psocksd, the PHP SOCKS server daemon!' . PHP_EOL;
            echo 'Usage: ' . $bin . ' ' . $main . PHP_EOL;
        });

        try {
            $commander->handleArgv($argv);
        } catch (NoRouteFoundException $e) {
            echo 'Invalid command usage. Run with "--help"' . PHP_EOL;
        }
    }

    public function start(array $args)
    {
        // apply default settings for arguments
        $args += array(
            'socket' => 'socks://localhost:9050',
            'measureTraffic' => true,
            'measureTime' => true,
            'interactive' => DIRECTORY_SEPARATOR === '/' && !isset($args['no-interaction']) && !isset($args['n']) && defined('STDIN') && is_resource(STDIN),
        );

        $settings = $this->parseSocksSocket($args['socket']);

        if ($settings['host'] === '*') {
            $settings['host'] = '0.0.0.0';
        }

        $this->loop = $loop = \React\EventLoop\Factory::create();

        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $this->resolver = $dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

        $this->via = new ConnectionManagerSelective();
        $this->via->addConnectionManagerFor($this->createConnectionManager('none'), '*', '*', self::PRIORITY_DEFAULT);

        $socket = new \React\Socket\Server($loop);

        $this->server = new \Clue\React\Socks\Server($loop, $socket, $this->via);

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

        if ($args['measureTraffic']) {
            new Option\MeasureTraffic($this->server);
        }

        if ($args['measureTime']) {
            new Option\MeasureTime($this->server);
        }

        echo 'SOCKS proxy server listening on ' . $settings['host'] . ':' . $settings['port'] . PHP_EOL;

        if ($args['interactive']) {
            $that = $this;
            $loop->addReadStream(STDIN, function() use ($that, $loop) {
                $line = fgets(STDIN, 4096);
                if ($line === false) {
                    echo 'STDIN closed. Exiting program...';
                    $loop->removeReadStream(STDIN);
                    $loop->stop();
                    echo PHP_EOL;

                    return;
                }

                $that->onReadLine($line);
            });
            echo 'Running in interactive mode. Type "help" for more info.' . PHP_EOL;
        } else {
            echo 'Running in non-interactive mode.' . PHP_EOL;
        }

        $loop->run();
    }

    public function onReadLine($line)
    {
        // parse command and its arguments (respect quotes etc.)
        $args = Arguments\split($line);

        try {
            $this->commander->handleArgs($args);
        } catch (NoRouteFoundException $e) {
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

    /**
     *
     * @return React\EventLoop\LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    public function addCommand($expression, $callback)
    {
        return $this->commander->add($expression, $callback);
    }

    public function getCommands()
    {
        return $this->commander->getRoutes();
    }

    /**
     * @return \ConnectionManager\Extra\Multiple\ConnectionManagerSelective
     */
    public function getConnectionManager()
    {
        return $this->via;
    }

    public function createConnectionManager($socket)
    {
        if ($socket === 'reject') {
            echo 'reject' . PHP_EOL;
            return new ConnectionManagerLabeled(new ConnectionManagerReject(), '-reject-');
        }
        $direct = new Connector($this->loop, $this->resolver);
        if ($socket === 'none') {
            $via = new ConnectionManagerLabeled($direct, '-direct-');

            echo 'use direct connection to target' . PHP_EOL;
        } else {
            $parsed = $this->parseSocksSocket($socket);

            // TODO: remove hack
            // resolver can not resolve 'localhost' ATM
            if ($parsed['host'] === 'localhost') {
                $parsed['host'] = '127.0.0.1';
            }

            $via = new Client($parsed['host'] . ':' . $parsed['port'], $this->loop, $direct, $this->resolver);
            if (isset($parsed['protocolVersion'])) {
                try {
                    $via->setProtocolVersion($parsed['protocolVersion']);
                }
                catch (Exception $e) {
                    throw new Exception('invalid protocol version: ' . $e->getMessage());
                }
            }
            if (isset($parsed['user']) || isset($parsed['pass'])) {
                $parsed += array('user' =>'', 'pass' => '');
                try {
                    $via->setAuth($parsed['user'], $parsed['pass']);
                }
                catch (Exception $e) {
                    throw new Exception('invalid authentication info: ' . $e->getMessage());
                }
            }

            echo 'use '.$this->reverseSocksSocket($parsed) . ' as next hop';

            try {
                $via->setResolveLocal(false);
                echo ' (resolve remotely)';
            }
            catch (UnexpectedValueException $ignore) {
                // ignore in case it's not allowed (SOCKS4 client)
                echo ' (resolve locally)';
            }

            $via = new ConnectionManagerLabeled($via->createConnector(), $this->reverseSocksSocket($parsed));

            echo PHP_EOL;
        }
        return $via;
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
