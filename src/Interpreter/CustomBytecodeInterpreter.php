<?php

declare(strict_types=1);

namespace App\Interpreter;

use App\Model\Compiler\CustomBytecode\Opcode;
use RuntimeException;

use function array_key_last;
use function array_pop;
use function substr;
use function unpack;

final class CustomBytecodeInterpreter
{
    private int $pointer;
    private string $bytecode;
    /** @var list<int> */
    private array $stack;
    /** @var array<string, int> the name -> value */
    private array $namedValues;

    public function interpret(string $bytecode): int
    {
        $this->pointer = 0;
        $this->bytecode = $bytecode;
        $this->stack = [];
        $this->namedValues = [];

        while (true) {
            $rawOpcode = $this->readUnsignedShort();

            $opcode = Opcode::tryFrom($rawOpcode);
            if ($opcode === Opcode::END) {
                return array_pop($this->stack);
            }

            match ($opcode) {
                Opcode::PUSH => $this->push(),
                Opcode::POP => $this->pop(),
                Opcode::LET => $this->let(),
                Opcode::ECHO => $this->echo(),
                Opcode::LOAD => $this->load(),
            };
        }
    }

    private function echo(): void
    {
        echo $this->stack[array_key_last($this->stack)];
    }

    private function let(): void
    {
        $lengthOfStringInBytes = $this->readUnsignedLongLong();
        $name = $this->getBytes($lengthOfStringInBytes);

        $this->pointer += $lengthOfStringInBytes;
        $this->namedValues[$name] = array_pop($this->stack);
    }

    private function load(): void
    {
        $lengthOfStringInBytes = $this->readUnsignedLongLong();
        $name = $this->getBytes($lengthOfStringInBytes);

        $this->pointer += $lengthOfStringInBytes;
        $this->stack[] = $this->namedValues[$name];
    }

    private function push(): void
    {
        $value = $this->readUnsignedLongLong();
        $this->stack[] = $value;
    }

    private function pop(): void
    {
        array_pop($this->stack);
    }

    private function getBytes(int $amount): string
    {
        return substr($this->bytecode, $this->pointer, $amount);
    }

    private function readUnsignedShort(): int
    {
        $value = unpack("Sint/", $this->bytecode, $this->pointer);
        if ($value === false) {
            throw new RuntimeException("Failed to read unsigned short");
        }

        $this->pointer += 2;

        return $value['int'];
    }

    private function readUnsignedLongLong(): int
    {
        $value = unpack("Pint/", $this->bytecode, $this->pointer);
        if ($value === false) {
            throw new RuntimeException("Failed to read unsigned long long");
        }

        $this->pointer += 8;

        return $value['int'];
    }
}
