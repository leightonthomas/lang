<?php

declare(strict_types=1);

namespace App\Parser;

use App\Model\Exception\Parser\ParseFailure;
use App\Model\Inference\Type\Monotype;
use App\Model\Syntax\Expression;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use WeakMap;

use function array_key_exists;

final class ParsedOutput
{
    /** @var WeakMap<Expression, Monotype> */
    private WeakMap $types;

    /** @var array<string, FunctionDefinition> */
    public array $functions;

    public function __construct()
    {
        $this->types = new WeakMap();
        $this->functions = [];
    }

    public function addType(Expression $to, Monotype $type): void
    {
        $this->types[$to] = $type;
    }

    public function getType(Expression $to): ?Monotype
    {
        return $this->types[$to] ?? null;
    }

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

