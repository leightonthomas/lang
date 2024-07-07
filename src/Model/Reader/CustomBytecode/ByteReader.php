<?php

declare(strict_types=1);

namespace App\Model\Reader\CustomBytecode;

use App\Model\Interpreter\FunctionDefinition;
use RuntimeException;

use function fread;
use function fseek;
use function unpack;

use const SEEK_CUR;

final class ByteReader
{
    private int $pointer;

    public function __construct(
        /** @var resource $bytecode */
        private $bytecode,
    ) {
        $this->pointer = 0;
    }

    public function getPointer(): int
    {
        return $this->pointer;
    }

    public function setPointer(int $new): void
    {
        fseek($this->bytecode, $new);
        $this->pointer = $new;
    }

    public function getBytes(int $amount): string
    {
        return fread($this->bytecode, $amount);
    }

    public function readUnsignedShort(): int
    {
        $value = unpack("Sint/", fread($this->bytecode, 2));
        if ($value === false) {
            throw new RuntimeException("Failed to read unsigned short");
        }

        $this->pointer += 2;

        return $value['int'];
    }

    public function readUnsignedLongLong(): int
    {
        $value = unpack("Qint/", fread($this->bytecode, 8));
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
        fseek($this->bytecode, $lengthOfFunctionContentInBytes, SEEK_CUR);

        return new FunctionDefinition($name, $offset, $args, $lengthOfFunctionContentInBytes);
    }
}
