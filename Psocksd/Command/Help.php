<?php

namespace Psocksd\Command;

class Help
{
    public function __construct($app)
    {

    }

    public function run($args)
    {
        echo 'psocksd help:' . PHP_EOL .
              '  status - show status' . PHP_EOL .
              '  help - show this very help' . PHP_EOL;
    }
}
