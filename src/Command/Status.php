<?php

namespace Clue\Psocksd\Command;

class Status implements CommandInterface
{
    public function __construct($app)
    {
        $that = $this;
        $app->addCommand('status', function () use ($that) {
            $that->run(array());
        })->shortHelp = $this->getHelp();
    }

    public function run($args)
    {
        echo 'status n/a' . PHP_EOL;
    }

    public function getHelp()
    {
        return 'show status';
    }
}
