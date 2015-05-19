<?php


namespace PHPFileAnalyzer;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

use Composer\Config;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Json\JsonFile;
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

    const CACHE_FILENAME = 'phpfa.php.cache';

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
        $cache = include $this->getProjectRoot(static::CACHE_FILENAME);

        $stop = 1;
        return ;


        ini_set('xdebug.max_nesting_level', 3000);

        $code = file_get_contents(__FILE__);

        $parser = new \PhpParser\Parser(new \PhpParser\Lexer());

        try {
            $statements = $parser->parse($code);
            // $stmts is an array of statement nodes
        } catch (\PhpParser\Error $e) {
            echo 'Parse Error: ', $e->getMessage();
        }

        /*
        $nodeDumper = new \PhpParser\NodeDumper();
        return $nodeDumper->dump($statements);
        */


        $io = new BufferIO();
        $factory = new Factory($io, $this->getProjectRoot('composer.json'), true);
        $composer = $factory->createComposer($io, $this->getProjectRoot('composer.json'), true, $this->getProjectRoot(), true);

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
        $project->baseDir = $this->getProjectRoot();

        //return var_dump($rootPackage);

        $this->registry->set('composer_project', 'project', $project);

        $result = [];
        $i = 0;

        foreach ($composerPackages as $composerPackage) {
            $i++;

            $targetDir = $im->getInstallPath($composerPackage);
            $downloader = $dm->getDownloaderForInstalledPackage($composerPackage);

            $replaces = $composerPackage->getReplaces();
            if (empty($replaces)) {
                /** @var Package $package */
                $package = new Package();
                $package->name = $composerPackage->getName();
                $package->description = $composerPackage->getDescription();
                $package->targetDir = $targetDir;

                $this->registry->set('composer_package', $package->name, $package);

                continue;
            } else {

                $replacedPackages = array_keys($replaces);

                $finder = new Finder();
                $finder
                    ->files()
                    ->in($targetDir)
                    ->depth('>= 1')
                    ->name('composer.json')
                ;

                try {
                    $individualComposerFiles = $finder->getIterator();
                } catch (\Exception $e) {
                    // @TODO: Log
                    continue;
                }

                /** @var SplFileInfo $individualComposerFile */
                foreach ($individualComposerFiles as $individualComposerFile) {
                    $jsonFile = new JsonFile($individualComposerFile->getRealPath());
                    $packageConfig = $jsonFile->read();

                    if (!is_array($packageConfig) || !isset($packageConfig['name'])) {
                        continue;
                    }
                    $packageName = $packageConfig['name'];

                    $replacedPackageKey = array_search($packageName, $replacedPackages);

                    if (false === $replacedPackageKey) {
                        // @TODO: Log
                        continue;
                    }

                    /** @var Package $package */
                    $package = new Package();
                    $package->name = $packageName;
                    $package->description = isset($packageConfig['description']) ? $packageConfig['description'] : null;
                    $package->targetDir = $individualComposerFile->getPath();
                    $package->replacedBy = $composerPackage->getName();

                    $this->registry->set('composer_package', $package->name, $package);

                    unset($replacedPackages[$replacedPackageKey]);
                }

                if (!empty($replacedPackages)) {
                    // @TODO: Log
                }

                /*

                foreach ($replaces as $packageName => $packageLink) {
                    /** @var Link $packageLink * /
                    $package = new Package();
                    $package->name = $packageLink->getSource();
                    $package->description = $packageLink->getPrettyString($composerPackage);

                    // @TODO: Expand replaced packages - now I know the downside of subtree splits

                    $this->registry->set('composer_package', $package->name, $package);
                }
                */
            }

            //$result[] = var_export($replaces, true);
        }

        $cache = file_put_contents($this->getProjectRoot(static::CACHE_FILENAME), '<?php return unserialize('.var_export(serialize($this->registry), true).');');

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
        $lintProcess = new Process('php -l ' . ProcessUtils::escapeArgument($path));
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

    /**
     * @param string|null $filename
     *
     * @return string
     */
    protected function getProjectRoot($filename = null)
    {
        return realpath(__DIR__ . '/../../../..') . ($filename ? '/' . $filename : '');
    }
}
