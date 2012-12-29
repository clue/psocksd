<?php

include_once __DIR__.'/vendor/autoload.php';

function parse($socket)
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

// $socket = null;
// $socket = 'socks://me@localhost:9050';
// $socket = 'localhost:9050';
$socket = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null;

$settings = (($socket === null) ? array() : parse($socket)) + array('scheme' => 'socks', 'host' => 'localhost', 'port' => 1050);

if ($settings['host'] === '*') {
    $settings['host'] = '0.0.0.0';
}


$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

$socket = new React\Socket\Server($loop);

$factory = new Socks\Factory($loop, $dns);
$server = $factory->createServer($socket);

if (preg_match('/^socks(\d\w?)?$/', $settings['scheme'], $match)) {
    if (isset($match[1])) {
        $server->setProtocolVersion($match[1]);
    }
} else {
    throw new InvalidArgumentException('Invalid socket scheme given');
}

$socket->listen($settings['port'], $settings['host']);

if (isset($settings['user']) || isset($settings['pass'])) {
    $settings += array('user' => '', 'pass' => '');
    $server->setAuthArray(array(
        $settings['user'] => $settings['pass']
    ));
}

$server->on('connection', function(React\Socket\Connection $client) {
    $name = '#'.(int)$client->stream;
    $log = function($msg) use ($client, &$name) {
        echo date('Y-m-d H:i:s') . ' ' . $name . ' ' . $msg . PHP_EOL;
    };

    $log('connected');

    $client->on('error', function($error) use ($log) {
        $msg = $error->getMessage();
        while ($error->getPrevious() !== null) {
            $error = $error->getPrevious();
            $msg .= ' - ' . $error->getMessage();
        }

        $log('error: ' . $msg);
    });

    $client->on('target', function ($host, $port) use ($log) {
        $log('tunnel target: ' . $host . ':' . $port);
    });

    $client->on('auth', function($username) use ($log, &$name) {
        $name .= '(' . $username . ')';
        $log('client authenticated');
    });

    $client->on('ready', function(React\Stream\Stream $remote) use($log) {
        $log('tunnel to remote stream #' . (int)$remote->stream . ' successfully established');
    });

    $client->on('close', function () use ($log) {
        $log('disconnected');
    });
});

echo 'SOCKS proxy server listening on ' . $settings['host'] . ':' . $settings['port'] . PHP_EOL;

$loop->run();
