<?php

namespace Psocksd\Command;

use Psocksd\App;

class Help implements CommandInterface
{
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function run($args)
    {
        echo 'psocksd help:' . PHP_EOL;
        foreach ($this->app->getCommands() as $name => $command) {
            echo '  ' . $name . ' - ' . $command->getHelp() . PHP_EOL;
        }
    }

    public function getHelp()
    {
        return 'show this very help';
    }
}
