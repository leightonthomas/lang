<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Model\Interpreter\FunctionDefinition;
use RuntimeException;

use function substr;
use function unpack;

final class ByteReader
{
    public int $pointer;

    public function __construct(
        private readonly string $bytecode,
    ) {
        $this->pointer = 0;
    }

    public function getBytes(int $amount): string
    {
        return substr($this->bytecode, $this->pointer, $amount);
    }

    public function readUnsignedShort(): int
    {
        $value = unpack("Sint/", $this->bytecode, $this->pointer);
        if ($value === false) {
            throw new RuntimeException("Failed to read unsigned short");
        }

        $this->pointer += 2;

        return $value['int'];
    }

    public function readUnsignedLongLong(): int
    {
        $value = unpack("Qint/", $this->bytecode, $this->pointer);
        if ($value === false) {
            throw new RuntimeException("Failed to read unsigned long long");
        }

        $this->pointer += 8;

        return $value['int'];
    }

    public function readString(): string
    {
        $lengthOfStringInBytes = $this->readUnsignedLongLong();
        $str = $this->getBytes($lengthOfStringInBytes);

        $this->pointer += $lengthOfStringInBytes;

        return $str;
    }

    public function readFunctionDefinition(): FunctionDefinition
    {
        $name = $this->readString();
        $argCount = $this->readUnsignedShort();

        /** @var list<string> $args */
        $args = [];
        for ($i = 0; $i < $argCount; $i++) {
            $args[] = $this->readString();
        }

        $lengthOfFunctionContentInBytes = $this->readUnsignedLongLong();

        $offset = $this->pointer;
        $this->pointer += $lengthOfFunctionContentInBytes;

        return new FunctionDefinition($name, $offset, $args, $lengthOfFunctionContentInBytes);
    }
}
