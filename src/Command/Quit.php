<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;

class Quit
{
    public function __construct(App $app)
    {
        $that = $this;
        $app->addCommand('quit | exit', function () use ($that, $app) {
            $that->run($app);
        })->shortHelp = 'shutdown this application';
    }

    public function run(App $app)
    {
        echo 'exiting...';
        $app->getLoop()->stop();
        echo PHP_EOL;
    }
}
