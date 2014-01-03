<?php
namespace ConstructionsIncongrues\Incongrupack\Cli;

use ConstructionsIncongrues\Incongrupack\Cli\Command\ArchiveCommand;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct()
    {
        parent::__construct('incongrupack', '0.1.0');
    }

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new ArchiveCommand();
        return $commands;
    }
}
