<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Standard\Function\StandardFunction;
use App\Model\Compiler\CustomBytecode\Structure;
use App\Parser\ParsedOutput;
use RuntimeException;

use function array_keys;
use function array_map;
use function bin2hex;
use function count;
use function mb_strlen;
use function pack;

final class ProgramCompiler
{
    public function compile(ParsedOutput $parsed): string
    {
        $mainFn = $parsed->functions['main'] ?? null;
        if ($mainFn === null) {
            // TODO temporary
            throw new RuntimeException("no main function");
        }

        $program = "";

        /** @var class-string<StandardFunction> $fnClass */
        foreach (StandardFunction::FUNCTIONS as $fnClass) {
            $program .= $this->packFunction(
                $fnClass::getName(),
                array_keys($fnClass::getArguments()),
                $fnClass::getBytecode(),
            );
        }

        foreach ($parsed->functions as $function) {
            $compiledFunction = (new FunctionCompiler())->compile($function);
            $fnName = $function->name->identifier;

            $program .= $this->packFunction(
                $fnName,
                array_map(
                    fn (array $arg): string => $arg['name']->identifier,
                    $function->arguments,
                ),
                $compiledFunction,
            );
        }

        $program .= pack("S", Structure::END->value);

        // now that all structure is done, hardcode calling the main function & returning its value as end of execution
        $program .= pack(
            "SQH*",
            Opcode::CALL->value,
            mb_strlen($mainFn->name->identifier),
            bin2hex($mainFn->name->identifier),
        );
        $program .= pack("S", Opcode::END->value);

        return $program;
    }

    /**
     * @param list<string> $arguments
     */
    private function packFunction(string $name, array $arguments, string $bytecode): string
    {
        $output = pack("SQH*", Structure::FN->value, mb_strlen($name), bin2hex($name));

        $output .= pack("S", count($arguments));
        foreach ($arguments as $argumentName) {
            $output .= pack("QH*", mb_strlen($argumentName), bin2hex($argumentName));
        }

        $output .= pack("Q", mb_strlen($bytecode));
        $output .= $bytecode;

        return $output;
    }
}
