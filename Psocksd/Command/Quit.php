<?php

namespace Psocksd\Command;

use Psocksd\App;

class Quit implements CommandInterface
{
    public function __construct(App $app)
    {
        $this->app = $app;
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
