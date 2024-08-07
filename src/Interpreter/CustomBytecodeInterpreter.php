<?php

declare(strict_types=1);

namespace App\Interpreter;

use App\Compiler\CustomBytecode\ProgramCompiler;
use App\Model\Compiler\CustomBytecode\JumpMode;
use App\Model\Compiler\CustomBytecode\JumpType;
use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Structure;
use App\Model\Interpreter\FunctionDefinition;
use App\Model\Interpreter\StackFrame;
use App\Model\Interpreter\StackValue\BooleanValue;
use App\Model\Interpreter\StackValue\IntegerValue;
use App\Model\Interpreter\StackValue\StringValue;
use App\Model\Interpreter\StackValue\UnitValue;
use App\Model\Reader\CustomBytecode\ByteReader;
use App\Model\StandardType;
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
        $globalFrame = new StackFrame(
            '_global',
            returnPointer: $this->byteReader->getPointer(),
            previous: null,
            parent: null,
        );
        $globalFrame->setNamedValue(StandardType::UNIT->value, new UnitValue());

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
                Opcode::PUSH_BOOL => $this->pushBool(),
                Opcode::PUSH_UNIT => $this->pushUnit(),
                Opcode::LET => $this->let(),
                Opcode::MARK => $this->mark(),
                Opcode::LOAD => $this->load(),
                Opcode::START_FRAME => $this->startFrame(returnPointer: null),
                Opcode::CALL => $this->call(),
                Opcode::JUMP => $this->jump(),
                Opcode::RET => $this->ret(),
                Opcode::POP => $this->pop(),
                Opcode::SUB => $this->sub(),
                Opcode::ADD => $this->add(),
                Opcode::NEGATE_INT => $this->negateInt(),
                Opcode::NEGATE_BOOL => $this->negateBool(),
                Opcode::LESS_THAN => $this->lessThan(),
                Opcode::LESS_THAN_EQ => $this->lessThanEq(),
                Opcode::GREATER_THAN => $this->greaterThan(),
                Opcode::GREATER_THAN_EQ => $this->greaterThanEq(),
                Opcode::ECHO => $this->echo(),
                Opcode::EQUALITY => $this->equality(),
                null => throw new RuntimeException('Unhandled opcode: ' . $rawOpcode),
                default => throw new RuntimeException('Unhandled opcode: ' . $opcode->name),
            };
        }
    }

    private function lessThan(): void
    {
        $right = $this->currentFrame->pop();
        if (! ($right instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $left = $this->currentFrame->pop();
        if (! ($left instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $this->currentFrame->push(new BooleanValue($left->value < $right->value));
    }

    private function lessThanEq(): void
    {
        $right = $this->currentFrame->pop();
        if (! ($right instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $left = $this->currentFrame->pop();
        if (! ($left instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $this->currentFrame->push(new BooleanValue($left->value <= $right->value));
    }

    private function greaterThan(): void
    {
        $right = $this->currentFrame->pop();
        if (! ($right instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $left = $this->currentFrame->pop();
        if (! ($left instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $this->currentFrame->push(new BooleanValue($left->value > $right->value));
    }

    private function greaterThanEq(): void
    {
        $right = $this->currentFrame->pop();
        if (! ($right instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $left = $this->currentFrame->pop();
        if (! ($left instanceof IntegerValue)) {
            throw new RuntimeException("Cannot compare non-integer values");
        }

        $this->currentFrame->push(new BooleanValue($left->value >= $right->value));
    }

    private function equality(): void
    {
        $right = $this->currentFrame->pop();
        $left = $this->currentFrame->pop();

        $this->currentFrame->push(new BooleanValue($left->equals($right)));
    }

    private function ret(): void
    {
        $returnValue = $this->currentFrame->pop();
        $returnPointer = $this->currentFrame->returnPointer;

        if ($returnPointer !== null) {
            $this->byteReader->setPointer($this->currentFrame->returnPointer);
        }

        array_pop($this->stack);

        $lastFrameKey = array_key_last($this->stack);
        if (array_key_exists($lastFrameKey, $this->stack)) {
            $previousFrame = $this->stack[$lastFrameKey];

            $this->currentFrame = $previousFrame;

            $this->currentFrame->push($returnValue);
        }
    }

    private function jump(): void
    {
        $jumpFlag = $this->currentFrame->pop();
        if (! ($jumpFlag instanceof IntegerValue)) {
            throw new RuntimeException("JUMP expects stack item to be integer");
        }

        $jumpType = $this->byteReader->readUnsignedShort();
        if ($jumpType === JumpType::RELATIVE_BYTES->value) {
            $amountToJump = $this->byteReader->readUnsignedLongLong();

            $newPointer = $this->byteReader->getPointer() + $amountToJump;
        } elseif ($jumpType === JumpType::MARKER->value) {
            $markerToJumpTo = $this->byteReader->readString();

            $newPointer = $this->currentFrame->getMarker($markerToJumpTo);
        } else {
            throw new RuntimeException("Unrecognised jump type");
        }

        if ($jumpFlag->value === JumpMode::IF_FALSE->value) {
            $value = $this->currentFrame->pop();
            if (! ($value instanceof BooleanValue)) {
                throw new RuntimeException("JUMP expects stack item (-1) to be boolean");
            }

            if ($value->value === false) {
                $this->byteReader->setPointer($newPointer);
            }
        } elseif ($jumpFlag->value === JumpMode::ALWAYS->value) {
            $this->byteReader->setPointer($newPointer);
        } else {
            throw new RuntimeException("Unrecognised jump mode");
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

    private function pop(): void
    {
        $this->currentFrame->pop();
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

    private function negateInt(): void
    {
        $operand = $this->currentFrame->pop();
        if (! ($operand instanceof IntegerValue)) {
            throw new RuntimeException("Cannot NEGATE_INT a non-integer value");
        }

        $this->currentFrame->push(new IntegerValue($operand->value * -1));
    }

    private function negateBool(): void
    {
        $operand = $this->currentFrame->pop();
        if (! ($operand instanceof BooleanValue)) {
            throw new RuntimeException("Cannot NEGATE_BOOL a non-boolean value");
        }

        $this->currentFrame->push(new BooleanValue(! $operand->value));
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
        $name = $this->byteReader->readString();
        $value = $this->currentFrame->pop();

        $this->currentFrame->setNamedValue($name, $value);
    }

    private function mark(): void
    {
        $this->currentFrame->mark($this->byteReader->readString(), $this->byteReader->getPointer());
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

    private function pushBool(): void
    {
        $value = $this->byteReader->readUnsignedShort();

        $this->currentFrame->push(new BooleanValue($value === 1));
    }

    private function pushUnit(): void
    {
        $this->currentFrame->push(new UnitValue());
    }

    private function startFrame(?int $returnPointer, ?StackFrame $parent = null): void
    {
        $oldFrame = $this->currentFrame;
        $stackFrame = new StackFrame('', $returnPointer, $oldFrame, $parent ?? $oldFrame);

        $this->stack[] = $stackFrame;
        $this->currentFrame = $stackFrame;
    }

    private function call(): void
    {
        $functionName = $this->byteReader->readString();
        $returnPointer = $this->byteReader->getPointer();
        $oldFrame = $this->currentFrame;

        $definition = $this->functions[$functionName];

        $this->byteReader->setPointer($definition->offset);

        $this->startFrame($returnPointer, $oldFrame->parent);

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
