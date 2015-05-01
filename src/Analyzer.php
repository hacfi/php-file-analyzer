<?php


namespace PHPFileAnalyzer;


use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

use Hal\Component\Token\Tokenizer;
use Hal\Component\OOP\Extractor\Extractor;
use Hal\Component\OOP\Extractor\Result;
use Hal\Component\OOP\Reflected\ReflectedArgument;
use Hal\Component\OOP\Reflected\ReflectedInterface;
use Hal\Component\OOP\Reflected\ReflectedClass;
use Hal\Component\OOP\Reflected\ReflectedMethod;
use Hal\Component\OOP\Reflected\ReflectedTrait;

class Analyzer
{
    const VERSION = '0.0.1';

    protected $tokenizer;

    protected $extractor;

    public function __construct()
    {
        $this->tokenizer = new Tokenizer();
        $this->extractor = new Extractor($this->tokenizer);
    }


    /**
     * @param SplFileInfo[] $files
     * @return array
     */
    public function run($files)
    {
        $result = [
            'files' => [],
        ];

        $processed = 0;

        foreach ($files as $file) {
            $processed++;
            if ($processed == 1) {
//                continue;
            }

            $fileAnalysis = [];

            $fileAnalysis['lint'] = $this->lintFile($file->getRealPath());

            if (!$fileAnalysis['lint']) {
                $result['files'][$file->getRealPath()] = $fileAnalysis;

                continue;
            }

            $fileAnalysis['oop'] = $this->processOop($file);

            $result['files'][$file->getRealPath()] = $fileAnalysis;

            if ($processed > 1) {
//                break;
            }
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
            //$data['constants'] = $this->processConstants($reflection);
            $data['constants'] = $reflection->getConstants();
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

        foreach ($class->getConstants() as $constant) {

            $constants = [];

            /** @var ReflectedArgument $argument */
            foreach ($method->getArguments() as $argument) {
                $arguments[substr($argument->getName(), 1)] = [
                    'type' => $argument->getType() ?: null,
                    'required' => $argument->isRequired(),
                ];
            }

            $constants[$constants->ge()] = [
                'arguments' => $arguments,
                'dependencies' => $method->getDependencies(),
                //$method->getArguments()
            ];

            //$methods[$method->getName()] = '';
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
