<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;

class Status
{
    public function __construct(App $app)
    {
        $that = $this;
        $app->addCommand('status', function () use ($that) {
            $that->run();
        })->shortHelp = 'show status';
    }

    public function run()
    {
        echo 'status n/a' . PHP_EOL;
    }
}
