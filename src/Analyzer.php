<?php


namespace PHPFileAnalyzer;

use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

use PhpParser\NodeDumper;

use Composer\Config;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Util\Filesystem;

use Hal\Component\Token\Tokenizer;
use Hal\Component\OOP\Extractor\Extractor;
use Hal\Component\OOP\Reflected\ReflectedArgument;
use Hal\Component\OOP\Reflected\ReflectedInterface;
use Hal\Component\OOP\Reflected\ReflectedClass;
use Hal\Component\OOP\Reflected\ReflectedMethod;
use Hal\Component\OOP\Reflected\ReflectedTrait;

use PHPFileAnalyzer\Data\InformationRegistry;
use PHPFileAnalyzer\Model\Composer\Package as Package;
use PHPFileAnalyzer\Model\Composer\Project;

class Analyzer
{
    const VERSION = '0.0.1';

    protected $tokenizer;

    protected $extractor;

    public function __construct()
    {
        $this->tokenizer = new Tokenizer();
        $this->extractor = new Extractor($this->tokenizer);

        $this->registry = InformationRegistry::getInstance();
    }


    /**
     * @param SplFileInfo[] $files
     * @return array
     */
    public function run($files)
    {
        ini_set('xdebug.max_nesting_level', 3000);

        $code = file_get_contents(__FILE__);

        $parser = new \PhpParser\Parser(new \PhpParser\Lexer());

        try {
            $statements = $parser->parse($code);
            // $stmts is an array of statement nodes
        } catch (\PhpParser\Error $e) {
            echo 'Parse Error: ', $e->getMessage();
        }

        //$nodeDumper = new NodeDumper();
        //return $nodeDumper->dump($statements);

        $cwd = realpath(__DIR__.'/../../../..');

        $io = new BufferIO();
        $factory = new Factory($io, __DIR__.'/../../../../composer.json', true);
        $composer = $factory->createComposer($io, __DIR__.'/../../../../composer.json', true, $cwd, true);

        $dm = $composer->getDownloadManager();
        $im = $composer->getInstallationManager();

        /** @var RootPackage $package */
        $rootPackage = $composer->getPackage();

        ini_set('xdebug.cli_color', 0);
        ini_set('xdebug.var_display_max_depth', 4);

        /** @var InstalledFilesystemRepository $localRepo */
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();

        $packageMap = $composer->getAutoloadGenerator()->buildPackageMap($composer->getInstallationManager(), $rootPackage, $localRepo->getCanonicalPackages());
        $autoloads = $composer->getAutoloadGenerator()->parseAutoloads($packageMap, $rootPackage);

        /** @var CompletePackage[] $composerPackages */
        $composerPackages = $localRepo->getCanonicalPackages();

        $config = $composer->getConfig();
        $filesystem = new Filesystem();
        $vendorPath = $filesystem->normalizePath(realpath($config->get('vendor-dir')));

        $project = new Project();
        $project->name = $rootPackage->getName();
        $project->type = $rootPackage->getType();
        $project->description = $rootPackage->getDescription();
        $project->uniqueName = $rootPackage->getUniqueName();
        $project->vendorDir = $config->get('vendor-dir', Config::RELATIVE_PATHS);
        $project->baseDir = $cwd ;
        $project->baseDir2 = $im->getInstallPath($rootPackage);

        //return var_dump($rootPackage);

        $this->registry->set('composer_project', 'project', $project);

        $result = [];
        $i = 0;

        foreach ($composerPackages as $composerPackage) {
            $i++;

            $targetDir = $im->getInstallPath($composerPackage);
            $downloader = $dm->getDownloaderForInstalledPackage($composerPackage);

            if (empty($replaces = $composerPackage->getReplaces())) {
                /** @var Package $package */
                $package = new Package();
                $package->name = $composerPackage->getName();
                $package->description = $composerPackage->getDescription();
                $package->targetDir = $targetDir;

                $this->registry->set('composer_package', $package->getName(), $package);

                continue;
            }

            foreach ($replaces as $packageName => $packageLink) {
                /** @var Link $packageLink */
                $package = new Package();
                $package->name = $packageLink->getSource();
                $package->description = $packageLink->getPrettyString($composerPackage);

                // @TODO: Expand replaced packages - now I know the downside of subtree splits

                $this->registry->set('composer_package', $package->getName(), $package);


            }
            //$result[] = var_export($replaces, true);

        }

        return $this->registry;

        $result = [
            'files' => [],
        ];

        $processed = 0;

        foreach ($files as $file) {
            $processed++;

            $fileAnalysis = [];

            $fileAnalysis['lint'] = $this->lintFile($file->getRealPath());

            if (!$fileAnalysis['lint']) {
                $result['files'][$file->getRealPath()] = $fileAnalysis;

                continue;
            }

            $fileAnalysis['oop'] = $this->processOop($file);

            $result['files'][$file->getRealPath()] = $fileAnalysis;
        }

        return $result;
    }

    /**
     * @param $path
     * @return Process
     */
    protected function lintFile($path)
    {
        $lintProcess = new Process('php -l '.ProcessUtils::escapeArgument($path));
        $lintProcess->setTimeout(null);
        $lintProcess->run();

        return strpos($lintProcess->getOutput(), 'No syntax errors detected in') === 0;
    }

    /**
     * @param $file
     * @return array
     */
    protected function processOop(\SplFileInfo $file)
    {
        $result = $this->extractor->extract($file->getRealPath());

        $data = [
            'classes' => [],
            'abstract_classes' => [],
            'interfaces' => [],
            'traits' => [],
        ];

        /** @var ReflectedClass $class */
        foreach ($result->getClasses() as $class) {

            if ($class instanceof ReflectedInterface) {
                $type = 'interfaces';
            } elseif ($class instanceof ReflectedTrait) {
                $type = 'traits';
            } elseif ($class->isAbstract()) {
                $type = 'abstract_classes';
            } else { // if (!$class->isAbstract() && !$class instanceof ReflectedInterface) {
                $type = 'classes';
            }

            $data[$type][] = $this->processClassData($class, $file->getRealPath());

        }

        return $data;
    }

    /**
     * @param ReflectedClass|ReflectedInterface|ReflectedTrait $class
     * @return array
     */
    protected function processClassData($class, $includeFile = false)
    {
        $data = [];

        // $data['namespace'] = $class->getNamespace();
        $data['namespace'] = ltrim($class->getNamespace(), '\\');
        $data['name'] = $class->getName();

        $data['extends'] = $class->getParent();
        $data['dependencies'] = $class->getDependencies();
        $data['methods'] = $this->processMethods($class);

        try {
            if ($includeFile) {
                include_once $includeFile;
            }

            $reflection = new \ReflectionClass($class->getFullname());
            $data['interfaces'] = $reflection->getInterfaceNames();
            $data['constants'] = $this->processConstants($reflection);
            $data['properties'] = $this->processProperties($reflection);
        } catch (\Exception $e) {

        }

        return $data;
    }

    /**
     * @param \ReflectionClass $class
     * @return array
     */
    protected function processConstants(\ReflectionClass $class)
    {
        $constants = [];

        foreach ($class->getConstants() as $constant => $value) {
            $constants[$constant] = ['value' => $value];
        }

        return $constants;
    }

    /**
     * @param \ReflectionClass $class
     * @return array
     */
    protected function processProperties(\ReflectionClass $class)
    {
        $properties = [];

        /** @var ReflectedMethod $method */
        foreach ($class->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }

        return $properties;
    }

    /**
     * @param $class
     * @return array
     */
    protected function processMethods($class)
    {
        $methods = [];

        /** @var ReflectedMethod $method */
        foreach ($class->getMethods() as $method) {

            $arguments = [];

            /** @var ReflectedArgument $argument */
            foreach ($method->getArguments() as $argument) {
                $arguments[substr($argument->getName(), 1)] = [
                    'type' => $argument->getType() ?: null,
                    'required' => $argument->isRequired(),
                ];
            }

            $methods[$method->getName()] = [
                'arguments' => $arguments,
                'dependencies' => $method->getDependencies(),
                //$method->getArguments()
            ];

            //$methods[$method->getName()] = '';
        }

        return $methods;
    }
}
