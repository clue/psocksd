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

$factory = new Socks\Factory($loop, $dns);
$server = $factory->createServer();

if (preg_match('/^socks(\d\w?)?$/', $settings['scheme'], $match)) {
    if (isset($match[1])) {
        $server->setProtocolVersion($match[1]);
    }
} else {
    throw new InvalidArgumentException('Invalid socket scheme given');
}

$server->listen($settings['port'], $settings['host']);

if (isset($settings['user']) || isset($settings['pass'])) {
    $settings += array('user' => '', 'pass' => '');
    $server->setAuthArray(array(
        $settings['user'] => $settings['pass']
    ));
}

echo 'SOCKS proxy server listening on ' . $settings['host'] . ':' . $settings['port'] . PHP_EOL;

$loop->run();
