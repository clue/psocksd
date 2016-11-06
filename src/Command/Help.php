<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;

class Help
{
    public function __construct(App $app)
    {
        $that = $this;
        $app->addCommand('help', function () use ($that, $app) {
            $that->run($app);
        })->shortHelp = 'show this very help';
    }

    public function run(App $app)
    {
        echo 'psocksd help:' . PHP_EOL;

        $commands = $app->getCommands();
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
}
