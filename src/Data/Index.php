<?php


namespace PHPFileAnalyzer\Data;

use Doctrine\Common\Collections\ArrayCollection;

class Index extends ArrayCollection
{
    /**
     * @var string
     */
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function add($value)
    {
        throw new \BadMethodCallException('You cannot add values to an index without a key.');
    }

    public function getName()
    {
        return $this->name;
    }

    public function __toString()
    {
        return $this->name;
    }
}
