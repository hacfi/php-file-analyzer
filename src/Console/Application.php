<?php

namespace PHPFileAnalyzer\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;

use PHPFileAnalyzer\Analyzer;

class Application extends BaseApplication
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        error_reporting(-1);

        parent::__construct('PHP File Analyzer', Analyzer::VERSION);
    }

    protected function getCommandName(InputInterface $input)
    {
        return 'phpfa';
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new Command;

        return $defaultCommands;
    }

    /**
     * Overridden so that the application doesn't expect the command
     * name to be the first argument.
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}
