<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Structure;
use RuntimeException;

final class Disassembler
{
    private ByteReader $byteReader;
    private string $output;

    public function disassemble(string $bytecode): string
    {
        $this->byteReader = new ByteReader($bytecode);
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
        $this->byteReader->pointer = $function->offset;

        $targetPointer = $this->byteReader->pointer + $function->lengthOfContentInBytes;

        $this->output .= "$function->name:\n";

        while ($this->byteReader->pointer < $targetPointer) {
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
