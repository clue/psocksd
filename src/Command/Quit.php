<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;

class Quit implements CommandInterface
{
    public function __construct(App $app)
    {
        $this->app = $app;

        $that = $this;
        $this->app->addCommand('quit | exit', function () use ($that) {
            $that->run(array());
        })->shortHelp = $this->getHelp();
    }

    public function run($args)
    {
        echo 'exiting...';
        $this->app->getLoop()->stop();
        echo PHP_EOL;
    }

    public function getHelp()
    {
        return 'shutdown this application';
    }
}
