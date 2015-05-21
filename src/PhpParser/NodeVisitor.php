<?php

namespace PHPFileAnalyzer\PhpParser;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract
{
    public function __construct()
    {
    }

    public function beforeTraverse(array $nodes) {
        $stop = 1;
    }

    public function enterNode(Node $node) {
        $stop = 2;
    }

    public function leaveNode(Node $node) {
        $stop = 3;
    }

    public function afterTraverse(array $nodes) {
        $stop = 4;
    }
}
