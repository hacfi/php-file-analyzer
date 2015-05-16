<?php


namespace PHPFileAnalyzer\Data;


class InformationRegistry implements \Countable
{
    /**
     * An array of indizes.
     *
     * @var Index[]|array
     */
    private $map = [];

    private static $instance;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();

            // For autoload
            class_exists('PHPFileAnalyzer\\Data\\Index');
        }

        return self::$instance;
    }

    final private function __construct()
    {
    }

    final private function __clone()
    {
    }

    public function toArray()
    {
        return $this->map;
    }

    /**
     * @param Index[]|array $indexes
     *
     * @return $this
     */
    public function setIndexes(array $indexes = [])
    {
        if ($indexes instanceof Index) {
            $indexes = [$indexes];
        }

        if (count(array_filter($indexes, function ($index) {
            return !$index instanceof Index;
        }))) {
            throw new \InvalidArgumentException('Indexes have to be instances of PHPFileAnalyzer\\Data\\Index');
        }

        $this->map = $indexes;

        return $this;
    }

    public function getIndexes()
    {
        return array_keys($this->map);
    }

    public function getIndex($name)
    {
        return $this->map[$name];
    }

    public function hasIndex($name)
    {
        return isset($this->map[$name]);
    }

    public function removeIndex($name)
    {
        $removedIndex = $this->map[$name];

        unset($this->map[$name]);

        return $removedIndex;
    }


    public function containsKey($index, $key)
    {
        return isset($this->map[$index]) && $this->map[$index]->containsKey($key);
    }

    public function contains($index, $element)
    {
        return isset($this->map[$index]) && $this->map[$index]->contains($element);
    }

    public function exists($index, \Closure $checkFunc)
    {
        return isset($this->map[$index]) && $this->map[$index]->exists($checkFunc);
    }

    public function indexOf($index, $element)
    {
        return isset($this->map[$index]) && $this->map[$index]->indexOf($element);
    }

    public function get($index, $key)
    {
        return isset($this->map[$index]) && $this->map[$index]->get($key);
    }

    public function getKeys($index)
    {
        return isset($this->map[$index]) && $this->map[$index]->getKeys();
    }

    public function getValues($index)
    {
        return isset($this->map[$index]) && $this->map[$index]->getValues();
    }

    public function count()
    {
        return count($this->map);
    }

    public function set($index, $key, $value)
    {
        if (!isset($this->map[$index])) {
            $this->map[$index] = new Index($index);
        }

        return $this->map[$index]->set($key, $value);
    }

    public function isEmpty()
    {
        return empty($this->elements);
    }

    public function remove($index, $key)
    {
        if (!isset($this->map[$index], $this->map[$index][$key])
            || (!isset($this->map[$index]) && !array_key_exists($key, $this->map[$index]))
        ) {
            return null;
        }

        $removedElement = $this->map[$index][$key];

        unset($this->map[$index][$key]);

        return $removedElement;
    }

    public function removeElement($index, $element)
    {
        $key = array_search($element, $this->map[$index], true);

        if ($key === false) {
            return false;
        }

        unset($this->map[$key]);

        return true;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->map);
    }

}
