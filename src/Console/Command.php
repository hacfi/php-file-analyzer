<?php

namespace PHPFileAnalyzer\Console;

use Symfony\Component\Console\Command\Command as AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use PHPFileAnalyzer\Analyzer;

class Command extends AbstractCommand
{
    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setName('phpfa')
             ->setDefinition(
                 [
                   new InputArgument(
                       'paths',
                       InputArgument::IS_ARRAY
                   )
                 ]
             )
        ;
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $paths = $input->getArgument('paths');

        $finder = new Finder();
        $finder->files();
        $finder->name('*.php');

        foreach ($paths as $path) {
            $finder->in($path);
        }

        try {
            $files = $finder->getIterator();
        } catch (\Exception $ex) {
            return 1;
        }

        if (empty($files)) {
            $output->writeln('No files to analyze');

            return 1;
        }

        $analyzer = new Analyzer();

        $result = $analyzer->run($files);

        $output->writeln('<html><body><pre>');
        $output->writeln(print_r($result, true));
        $output->writeln('</pre></body></html>');

        return 0;
    }
}
