<?php


namespace PHPFileAnalyzer\Model\Composer;


class Project extends Package
{
    public $minimumStability;
    public $preferStable;
    public $uniqueName;

    public $basePath;
    public $vendorDir;
}
