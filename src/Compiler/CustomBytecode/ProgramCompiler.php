<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Compiler\Program;
use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Standard\Function\StandardFunction;
use App\Model\Compiler\CustomBytecode\Structure;
use App\Model\Syntax\Simple\Definition\FunctionDefinition;
use RuntimeException;

use function array_keys;
use function array_map;
use function bin2hex;
use function count;
use function mb_strlen;
use function pack;

final class ProgramCompiler
{
    public function compile(Program $program): string
    {
        $mainFn = $program->getFunction('main');
        if ($mainFn === null) {
            // TODO temporary
            throw new RuntimeException("no main function");
        }

        $output = "";

        foreach ($program->getFunctions() as $function) {
            $rawFunction = $function->rawFunction;

            if ($rawFunction instanceof FunctionDefinition) {
                $compiledFunction = (new FunctionCompiler())->compile($rawFunction);
                $fnName = $rawFunction->name->identifier;

                $output .= $this->packFunction(
                    $fnName,
                    array_map(
                        fn (array $arg): string => $arg['name']->identifier,
                        $rawFunction->arguments,
                    ),
                    $compiledFunction,
                );

                continue;
            }

            /**
             * it must be a standard function if not a definition
             *
             * @var class-string<StandardFunction> $rawFunction
             */
            $output .= $this->packFunction(
                $rawFunction::getName(),
                array_keys($rawFunction::getArguments()),
                $rawFunction::getBytecode(),
            );
        }

        $output .= pack("S", Structure::END->value);

        // now that all structure is done, hardcode calling the main function & returning its value as end of execution
        $output .= pack(
            "SQH*",
            Opcode::CALL->value,
            mb_strlen($mainFn->name),
            bin2hex($mainFn->name),
        );
        $output .= pack("S", Opcode::END->value);

        return $output;
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
