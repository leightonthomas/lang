<?php

declare(strict_types=1);

namespace App\Parser;

use App\Model\Exception\Parser\ParseFailure;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;

use function array_key_exists;

final class ParsedOutput
{
    /** @var array<string, FunctionDefinition> */
    public array $functions = [];

    /**
     * @throws ParseFailure
     */
    public function addFunction(FunctionDefinition $function): void
    {
        if (array_key_exists($function->name->identifier, $this->functions)) {
            throw new ParseFailure(
                'There is already a function with this name in the file.',
                $function->name,
            );
        }

        $this->functions[$function->name->identifier] = $function;
    }
}

