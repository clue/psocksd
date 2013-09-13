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

```bash
$ php psocksd.phar
```

You can optionally supply an additional listen-address like this:

```bash
$ php psocksd.phar 9051 # start SOCKS daemon on port 9051 instead
$ php psocksd.phar 192.168.1.2:9050 # explicitly listen on the given interface
$ php psocksd.phar *:9050 # listen on all interfaces (allow access to SOCKS server from the outside)
$ php psocksd.phar socks5://localhost:9050 # explicitly only support SOCKS5 and reject other protocol versions
$ php psocksd.phar socks5://username:password@localhost:9051 # require client to send the given authentication information
```

## Install

You can grab a copy of clue/psocksd in either of the following ways.

### As a phar (recommended)

You can simply download a pre-compiled and ready-to-use version as a Phar
to any directory:

```bash
$ wget http://www.lueck.tv/psocksd/psocksd.phar
```

> If you prefer a global (system-wide) installation without having to type the `.phar` extension
each time, you may invoke:
> 
> ```bash
> $ chmod 0755 psocksd.phar
> $ sudo mv psocksd.phar /usr/local/bin/psocksd
> ```
>
> You can verify everything works by running:
> 
> ```bash
> $ psocksd
> ```

#### Updating phar

There's no separate `update` procedure, simply overwrite the existing phar with the new version downloaded.

### Manual Installation from Source

The manual way to install `psocksd` is to clone (or download) this repository
and use [composer](http://getcomposer.org) to download its dependencies.
Obviously, for this to work, you'll need PHP, git and curl installed:

```bash
$ sudo apt-get install php5-cli git curl
$ git clone https://github.com/clue/psocksd.git
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install
```

> If you want to build the above mentioned `psocksd.phar` yourself, you have
to install [clue/phar-composer](https://github.com/clue/phar-composer#install)
and can simply invoke:
>
> ```bash
> $ php phar-composer.phar build ~/workspace/psocksd
> ```

#### Updating manually

If you have followed the above install instructions, you can update `psocksd` by issuing the following two commands:

```bash
$ git pull
$ php composer.phar install
```

## License

MIT-licensed
