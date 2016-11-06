<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;

class Help implements CommandInterface
{
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;

        $that = $this;
        $this->app->addCommand('help', function () use ($that) {
            $that->run(array());
        })->shortHelp = $this->getHelp();
    }

    public function run($args)
    {
        echo 'psocksd help:' . PHP_EOL;

        $commands = $this->app->getCommands();
        $first = true;

        foreach ($commands as $command) {
            if (!isset($command->shortHelp)) {
                continue;
            }

            if ($first) {
                $first = false;
            } else {
                echo PHP_EOL;
            }

            echo '    ' . (string)$command . PHP_EOL;
            echo '        ' . $command->shortHelp . PHP_EOL;
        }
    }

    public function getHelp()
    {
        return 'show this very help';
    }
}
