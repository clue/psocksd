psocksd
=======

Extensible SOCKS tunnel / proxy server daemon written in PHP

## Features

The SOCKS protocol family can be used to easily tunnel TCP connections independent
of the actual application level protocol, such as HTTP, SMTP, IMAP, Telnet, etc.
In this mode, a SOCKS server acts as a generic proxy allowing higher level application protocols to work through it.

*   SOCKS proxy server with support for SOCKS4, SOCKS4a and SOCKS5 protocol versions (all at the same time)
*   Optionally require username / password authentication (SOCKS5 only)
*   Zero configuration, easy to use command line interface (CLI) to change settings without restarting server
*   Incoming SOCKS requests can be forwarded to another SOCKS server to act as a tunnel gateway,
perform transparent protocol translation or add SOCKS authentication for clients not capable of doing it themselves.
    *   Tunnel endpoint can be changed during runtime (`via` CLI command).
    *   Particularly useful when used as an intermediary server and using ever-changing public SOCKS tunnel end points.
*   Using an async event-loop, it is capable of handling multiple concurrent connections in a non-blocking fashion
*   Built upon the shoulders of [reactphp/react](https://github.com/reactphp/react) and
[clue/socks](https://github.com/clue/socks), it uses well-tested dependencies instead of reinventing the wheel.

## Usage

Once [installed](#install), you can start `psocksd` and listen for SOCKS connections on localhost:9050 by running:

`php ./psocksd.php`

You can optionally supply an additional listen-address like this:

```
php ./psocksd.php 9051 # start SOCKS daemon on port 9051 instead
php ./psocksd.php 192.168.1.2:9050 # explicitly listen on the given interface
php ./psocksd.php *:9050 # listen on all interfaces (allow access to SOCKS server from the outside)
php ./psocksd.php socks5://localhost:9050 # explicitly only support SOCKS5 and reject other protocol versions
php ./psocksd.php socks5://username:password@localhost:9051 # require client to send the given authentication information
```

## Install

Currently, the recommended way to install `psocksd` is to clone (or download) this repository
and use [composer](http://getcomposer.org) to download its dependencies.
Obviously, for this to work, you'll need PHP, git and curl installed:

```bash
sudo apt-get install php5-cli git curl
git clone https://github.com/clue/psocksd.git
curl -s https://getcomposer.org/installer | php
php composer.phar install
```

### Updating

If you have followed the above install instructions, you can update `psocksd` by issuing the following two commands:

```bash
git pull
php composer.phar update
```

## License

MIT-licensed
