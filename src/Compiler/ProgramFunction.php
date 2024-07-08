<?php

declare(strict_types=1);

namespace App\Compiler;

use App\Model\Compiler\CustomBytecode\Standard\Function\StandardFunction;
use App\Model\Inference\Type\Monotype;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;

readonly class ProgramFunction
{
    public function __construct(
        public string $name,
        /** @var array<string, Monotype> */
        public array $arguments,
        public Monotype $returnType,
        /** @var class-string<StandardFunction>|FunctionDefinition $rawFunction */
        public FunctionDefinition|string $rawFunction,
    ) {
    }
}
