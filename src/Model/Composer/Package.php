<?php


namespace PHPFileAnalyzer\Model\Composer;


class Package
{
    public $name;
    public $type;
    public $description;
    public $targetDir;
    public $autoload;
    public $autoloadDev;
    public $requiredPackages;
    public $requiredDevPackages;
    public $replacedBy;

    public $files;

}
