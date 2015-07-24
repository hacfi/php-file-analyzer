<?php

namespace PHPFileAnalyzer\PhpParser;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

class NodeVisitor extends NodeVisitorAbstract
{
    /** @var null|Name Current namespace */
    protected $namespace;

    /** @var array Map of format [aliasType => [aliasName => originalName]] */
    protected $aliases;

    const SCOPE_NAMESPACE = 0;
    const SCOPE_CLASS = 1;
    const SCOPE_METHOD = 2;

    protected $scope = [];

    public function __construct()
    {

    }

    public function beforeTraverse(array $nodes)
    {
        $this->resetNamespace();

        $this->scope = [];
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Stmt\Namespace_) {
            $stop = 2; //
            $this->scope[self::SCOPE_NAMESPACE] = $node;
        } elseif ($node instanceof Stmt\Class_) {
            $this->scope[self::SCOPE_CLASS] = $node;
            $stop = 2; //
        } elseif ($node instanceof Stmt\Interface_) {
            $parents = [];
            foreach ($node->extends as &$interface) {
                $parents[] = $this->resolveClassName($interface);
            }
            $this->scope[self::SCOPE_CLASS] = $node;
            $stop = 2; //
        } elseif ($node instanceof Stmt\Trait_) {
            $this->scope[self::SCOPE_CLASS] = $node;
            $stop = 2; //
        } elseif ($node instanceof Stmt\Function_) {
            $stop = 2; //
        } elseif ($node instanceof Stmt\ClassMethod
            //|| $node instanceof Expr\Closure
        ) {
            $this->scope[self::SCOPE_METHOD] = $node;
            $stop = 2; //$this->resolveSignature($node);
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->scope = [];
        } elseif ($node instanceof Stmt\Class_) {
            $this->scope[self::SCOPE_CLASS] = $node;
            unset($this->scope[self::SCOPE_CLASS], $this->scope[self::SCOPE_METHOD]);
        } elseif ($node instanceof Stmt\Interface_) {
            unset($this->scope[self::SCOPE_CLASS], $this->scope[self::SCOPE_METHOD]);
        } elseif ($node instanceof Stmt\Trait_) {
            $this->scope[self::SCOPE_CLASS] = $node;
        } elseif ($node instanceof Stmt\ClassMethod
            //|| $node instanceof Expr\Closure
        ) {
            unset($this->scope[self::SCOPE_METHOD]);
            $stop = 2; //$this->resolveSignature($node);
        }
    }

    public function afterTraverse(array $nodes)
    {
        $stop = 4;
    }


    protected function resetNamespace(Name $namespace = null)
    {
        $this->namespace = $namespace;
        $this->aliases = [
            Stmt\Use_::TYPE_NORMAL => [],
            Stmt\Use_::TYPE_FUNCTION => [],
            Stmt\Use_::TYPE_CONSTANT => [],
        ];
    }

    protected function resolveClassName(Name $name)
    {
        // don't resolve special class names
        if (in_array(strtolower($name->toString()), array('self', 'parent', 'static'))) {
            if (!$name->isUnqualified()) {
                throw new \Exception(
                    sprintf("'\\%s' is an invalid class name", $name->toString()),
                    $name->getLine()
                );
            }

            return $name;
        }

        // fully qualified names are already resolved
        if ($name->isFullyQualified()) {
            return $name;
        }

        $aliasName = strtolower($name->getFirst());
        if (!$name->isRelative() && isset($this->aliases[Stmt\Use_::TYPE_NORMAL][$aliasName])) {
            // resolve aliases (for non-relative names)
            $alias = $this->aliases[Stmt\Use_::TYPE_NORMAL][$aliasName];
            return FullyQualified::concat($alias, $name->slice(1), $name->getAttributes());
        }

        if (null !== $this->namespace) {
            // if no alias exists prepend current namespace
            return FullyQualified::concat($this->namespace, $name, $name->getAttributes());
        }

        return new FullyQualified($name->parts, $name->getAttributes());
    }
}
