<?php

declare(strict_types=1);

namespace App\Interpreter;

use App\Compiler\CustomBytecode\ProgramCompiler;
use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Structure;
use App\Model\Interpreter\FunctionDefinition;
use App\Model\Interpreter\StackFrame;
use App\Model\Interpreter\StackValue\IntegerValue;
use App\Model\Interpreter\StackValue\StringValue;
use App\Model\Reader\CustomBytecode\ByteReader;
use RuntimeException;

use function array_key_exists;
use function array_key_last;
use function array_pop;
use function array_reverse;

final class CustomBytecodeInterpreter
{
    /** @var list<StackFrame> */
    private array $stack;
    /** @var array<string, FunctionDefinition> indexed by name */
    private array $functions;
    private StackFrame $currentFrame;
    private ByteReader $byteReader;

    /**
     * @param resource $bytecodeResource
     */
    public function interpret($bytecodeResource): int
    {
        $this->stack = [];
        $this->functions = [];
        $this->byteReader = new ByteReader($bytecodeResource);

        // parse the structure
        while (true) {
            $rawStruct = $this->byteReader->readUnsignedShort();

            $struct = Structure::tryFrom($rawStruct);
            if ($struct === Structure::END) {
                break;
            }

            match ($struct) {
                Structure::FN => $this->addFunction(),
                default => throw new RuntimeException('Unhandled structure opcode: ' . $rawStruct),
            };
        }

        /**
         * we need to always have a frame, so create a global one which will be used for handling the return value
         * of the hardcoded main function - {@see ProgramCompiler::compile()}
         */
        $globalFrame = new StackFrame('', $this->byteReader->getPointer());
        $this->stack[] = $globalFrame;
        $this->currentFrame = $globalFrame;

        // now we can execute the actual code
        while (true) {
            $rawOpcode = $this->byteReader->readUnsignedShort();

            $opcode = Opcode::tryFrom($rawOpcode);
            if ($opcode === Opcode::END) {
                $returnCode = $this->currentFrame->pop();
                if (! ($returnCode instanceof IntegerValue)) {
                    throw new RuntimeException("Cannot end on a non-integer value");
                }

                return $returnCode->value;
            }

            match ($opcode) {
                Opcode::PUSH_INT => $this->pushInt(),
                Opcode::PUSH_STRING => $this->pushString(),
                Opcode::LET => $this->let(),
                Opcode::LOAD => $this->load(),
                Opcode::CALL => $this->call(),
                Opcode::RET => $this->ret(),
                Opcode::SUB => $this->sub(),
                Opcode::ADD => $this->add(),
                Opcode::NEG => $this->neg(),
                Opcode::ECHO => $this->echo(),
                null => throw new RuntimeException('Unhandled opcode: ' . $rawOpcode),
                default => throw new RuntimeException('Unhandled opcode: ' . $opcode->name),
            };
        }
    }

    private function ret(): void
    {
        $returnValue = $this->currentFrame->pop();
        $this->byteReader->setPointer($this->currentFrame->returnPointer);

        array_pop($this->stack);

        $lastFrameKey = array_key_last($this->stack);
        if (array_key_exists($lastFrameKey, $this->stack)) {
            $previousFrame = $this->stack[$lastFrameKey];

            $this->currentFrame = $previousFrame;
            $this->currentFrame->push($returnValue);
        }
    }

    private function echo(): void
    {
        $value = $this->currentFrame->get();
        if (! ($value instanceof StringValue)) {
            throw new RuntimeException("Cannot echo a non-string value");
        }

        echo $value->value;
    }

    private function sub(): void
    {
        $right = $this->currentFrame->pop();
        if (! ($right instanceof IntegerValue)) {
            throw new RuntimeException("Cannot subtract non-integer values");
        }

        $left = $this->currentFrame->pop();
        if (! ($left instanceof IntegerValue)) {
            throw new RuntimeException("Cannot subtract non-integer values");
        }

        $this->currentFrame->push(new IntegerValue($left->value - $right->value));
    }

    private function neg(): void
    {
        $operand = $this->currentFrame->pop();
        if (! ($operand instanceof IntegerValue)) {
            throw new RuntimeException("Cannot negate a non-integer value");
        }

        $this->currentFrame->push(new IntegerValue($operand->value * -1));
    }

    private function add(): void
    {
        $right = $this->currentFrame->pop();
        if (! ($right instanceof IntegerValue)) {
            throw new RuntimeException("Cannot subtract non-integer values");
        }

        $left = $this->currentFrame->pop();
        if (! ($left instanceof IntegerValue)) {
            throw new RuntimeException("Cannot subtract non-integer values");
        }

        $this->currentFrame->push(new IntegerValue($left->value + $right->value));
    }

    private function let(): void
    {
        $this->currentFrame->setNamedValue($this->byteReader->readString(), $this->currentFrame->pop());
    }

    private function load(): void
    {
        $this->currentFrame->push($this->currentFrame->getNamedValue($this->byteReader->readString()));
    }

    private function pushInt(): void
    {
        $value = $this->byteReader->readUnsignedLongLong();

        $this->currentFrame->push(new IntegerValue($value));
    }

    private function pushString(): void
    {
        $value = $this->byteReader->readString();

        $this->currentFrame->push(new StringValue($value));
    }

    private function call(): void
    {
        $functionName = $this->byteReader->readString();
        $returnPointer = $this->byteReader->getPointer();

        $definition = $this->functions[$functionName];

        $this->byteReader->setPointer($definition->offset);

        $oldFrame = $this->currentFrame;
        $stackFrame = new StackFrame($functionName, $returnPointer);

        $this->stack[] = $stackFrame;
        $this->currentFrame = $stackFrame;

        foreach (array_reverse($definition->arguments) as $argumentName) {
            $this->currentFrame->setNamedValue($argumentName, $oldFrame->pop());
        }
    }

    private function addFunction(): void
    {
        $fn = $this->byteReader->readFunctionDefinition();

        $this->functions[$fn->name] = $fn;
    }
}
