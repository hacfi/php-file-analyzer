<?php

namespace PHPFileAnalyzer\Composer;

use Composer\Config;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\CompletePackage;
use Composer\Package\Dumper\ArrayDumper;

use Composer\Package\RootPackage;
use PHPFileAnalyzer\Data\InformationRegistry;

class ComposerParser
{
    private $registry;

    private $projectRoot;

    private $composer;

    private $dumper;

    public function __construct($projectRoot)
    {
        $this->registry = InformationRegistry::getInstance();

        $this->projectRoot = $projectRoot;

        $this->composer = (new Factory())->createComposer(new BufferIO(), $projectRoot . '/composer.json', true, $projectRoot, true);

        $this->dumper = new ArrayDumper();
    }

    public function parse()
    {
        /** @var RootPackage $package */
        $rootPackage = $this->composer->getPackage();
        $vendorPath = $this->composer->getConfig()->get('vendor-dir', Config::RELATIVE_PATHS);

        $autoload = $this->composer->getAutoloadGenerator()->parseAutoloads($this->composer->getAutoloadGenerator()->buildPackageMap($this->composer->getInstallationManager(), $rootPackage, []), $rootPackage);
        $autoloadPaths = [];

        foreach (['psr-0', 'psr-4'] as $type) {
            foreach ($autoload[$type] as $ns => $paths) {
                foreach ($paths as $path) {
                    if (substr($path, -2) === '/.') {
                        $path = substr($path, 0, -1);
                    }
                    $autoloadPaths[] = [
                        'path' => $path,
                        'type' => $type,
                        'ns' => $ns,
                    ];
                }
            }
        }
        foreach ($autoload['classmap'] as $path) {
            $autoloadPaths[] = [
                'path' => $path,
                'type' => 'classmap',
            ];
        }

        $project = [
            'root' => $this->projectRoot,
            'composer_dump' => $this->dumper->dump($rootPackage),
            'autoload' => $autoload,
            'autoload_paths' => $autoloadPaths,
            'autoload_files' => $autoload['files'],
            'target_dir' => $this->projectRoot,
        ];
        $this->registry->set('composer_package', '__root__', $project);

        $rootPackageWithoutAutoloads = clone $rootPackage;
        $rootPackageWithoutAutoloads->setAutoload([]);
        $rootPackageWithoutAutoloads->setDevAutoload([]);

        $packages = [];

        foreach ($this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages() as $composerPackage) {
            if ($composerPackage === 'hacfi/php-file-analyzer') {
                continue;
            }

            /** @var CompletePackage $composerPackage */
            $installPath = $this->composer->getInstallationManager()->getInstallPath($composerPackage);
            $autoload = $this->composer->getAutoloadGenerator()->parseAutoloads($this->composer->getAutoloadGenerator()->buildPackageMap($this->composer->getInstallationManager(), $rootPackageWithoutAutoloads, [$composerPackage]), $rootPackageWithoutAutoloads);

            $autoloadPaths = [];

            foreach (['psr-0', 'psr-4'] as $type) {
                foreach ($autoload[$type] as $ns => $paths) {
                    foreach ($paths as $path) {
                        if (substr($path, -2) === '/.') {
                            $path = substr($path, 0, -1);
                        }
                        $autoloadPaths[] = [
                            'path' => $path,
                            'type' => $type,
                            'ns' => $ns,
                        ];
                    }
                }
            }
            foreach ($autoload['classmap'] as $path) {
                $autoloadPaths[] = [
                    'path' => $path,
                    'type' => 'classmap',
                ];
            }

            $packages[$composerPackage->getName()] = [
                'composer_dump' => $this->dumper->dump($composerPackage),
                'autoload' => $autoload,
                'autoload_paths' => $autoloadPaths,
                'autoload_files' => $autoload['files'],
                'target_path' => $vendorPath . '/' . $composerPackage->getPrettyName(),
                'target_dir' => $installPath,
            ];
            $this->registry->set('composer_package', $composerPackage->getName(), $packages[$composerPackage->getName()]);
        }

    }

}
