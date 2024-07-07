<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Structure;
use App\Model\Reader\CustomBytecode\ByteReader;
use RuntimeException;

final class Disassembler
{
    private ByteReader $byteReader;
    private string $output;

    /**
     * @param resource $bytecodeResource
     */
    public function disassemble($bytecodeResource): string
    {
        $this->byteReader = new ByteReader($bytecodeResource);
        $this->output = "";

        // parse the structure
        while (true) {
            $rawStruct = $this->byteReader->readUnsignedShort();

            $struct = Structure::tryFrom($rawStruct);
            if ($struct === Structure::END) {
                break;
            }

            match ($struct) {
                Structure::FN => $this->addFunction(),
                default => throw new RuntimeException('Unhandled structure opcode: ' . $struct->name),
            };
        }

        do {
            $opcode = $this->disassembleOpcode('');
        } while ($opcode !== Opcode::END);

        return $this->output;
    }

    private function addFunction(): void
    {
        $function = $this->byteReader->readFunctionDefinition();

        // as opposed to execution, we immediately went to print the opcodes for the function
        $this->byteReader->setPointer($function->offset);

        $targetPointer = $this->byteReader->getPointer() + $function->lengthOfContentInBytes;

        $this->output .= "$function->name:\n";

        while ($this->byteReader->getPointer() < $targetPointer) {
            $this->disassembleOpcode('    ');
        }
    }

    private function disassembleOpcode(string $prefix): Opcode
    {
        $rawOpcode = $this->byteReader->readUnsignedShort();

        $opcode = Opcode::tryFrom($rawOpcode);
        if ($opcode === null) {
            throw new RuntimeException("Unhandled opcode for disassembly '$rawOpcode'");
        }

        $this->output .= "$prefix$opcode->name";
        if ($opcode === Opcode::PUSH) {
            $value = $this->byteReader->readUnsignedLongLong();

            $this->output .= " $value";
        } elseif (
            ($opcode === Opcode::LOAD)
            || ($opcode === Opcode::CALL)
            || ($opcode === Opcode::LET)
        ) {
            $name = $this->byteReader->readString();

            $this->output .= " $name";
        }

        $this->output .= "\n";

        return $opcode;
    }
}
