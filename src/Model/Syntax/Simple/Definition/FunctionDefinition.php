<?php

declare(strict_types=1);

namespace App\Model\Syntax\Simple\Definition;

use App\Lexer\Token\Identifier;
use App\Lexer\Token\Keyword;
use App\Model\Syntax\Simple\CodeBlock;
use App\Model\Syntax\Simple\SimpleSyntax;
use App\Model\Syntax\Simple\TypeAssignment;

/**
 * Represents a top-level function definition in a scope (e.g. in a file, or on an object).
 */
readonly class FunctionDefinition implements SimpleSyntax
{
    public function __construct(
        public Keyword $functionToken,
        public TypeAssignment $assignedType,
        public Identifier $name,
        public CodeBlock $codeBlock,
        /** @var list<array{name: string, type: TypeAssignment}> */
        public array $arguments = [],
    ) {
    }
}
