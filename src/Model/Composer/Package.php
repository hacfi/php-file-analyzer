<?php


namespace PHPFileAnalyzer\Model\Composer;


class Package
{
    public $name;
    public $description;
    public $targetDir;
    public $autoload;
    public $replacedBy;

    public $files;

}
