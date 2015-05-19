<?php


namespace PHPFileAnalyzer\Model\Composer;


class Project
{
    public $name;
    public $minimumStability;
    public $preferStable;
    public $type;
    public $uniqueName;
    public $description;
    public $autoload;
    public $requiredPackages;
    public $requiredDevPackages;

    public $targetDir;
    public $vendorDir;
    public $baseDir;
    public $baseDir2;

    public $files;

}
