<?php

namespace PHPFileAnalyzer\Composer;

use PHPFileAnalyzer\Data\InformationRegistry;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileListGenerator
{
    private $registry;


    public function __construct()
    {
        $this->registry = InformationRegistry::getInstance();
    }

    public function processPackages()
    {
        $files = [];

        foreach ($this->registry->getIndex('composer_package') as $packageName => $package) {
            $packageFiles = [];

            if (!empty($package['autoload_paths'])) {
                $finder = new Finder();
                $finder->files();
                $finder->name('*.php');

                foreach ($package['autoload_paths'] as $autoloadPath) {
                    if (substr($autoloadPath['path'], 0, 1) !== '/') {
                        $autoloadPath['path'] = $package['target_dir'] . '/' . $autoloadPath['path'];
                    }
                    $finder->in($autoloadPath['path']);
                }

                foreach ($finder->getIterator() as $file) {
                    /** @var SplFileInfo $file */

                    $packageFiles[] = $file->getRealPath();
                }
            }

            foreach ($package['autoload_files'] as $autoloadFile) {
                $packageFiles[] = $autoloadFile;
            }

            $this->registry->set('package_files', $packageName, $packageFiles);

            $files = array_merge($files, $packageFiles);
        }

        return $files;
    }

}
