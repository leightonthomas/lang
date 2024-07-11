<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\Compiler\CustomBytecode\Structure;
use App\Model\Reader\CustomBytecode\ByteReader;
use RuntimeException;

use function max;
use function str_repeat;

final class Disassembler
{
    private ByteReader $byteReader;
    private string $output;
    private int $depth;

    /**
     * @param resource $bytecodeResource
     */
    public function disassemble($bytecodeResource): string
    {
        $this->byteReader = new ByteReader($bytecodeResource);
        $this->output = "";
        $this->depth = 0;

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
        $this->depth = 1;

        $function = $this->byteReader->readFunctionDefinition();

        // as opposed to execution, we immediately went to print the opcodes for the function
        $this->byteReader->setPointer($function->offset);

        $targetPointer = $this->byteReader->getPointer() + $function->lengthOfContentInBytes;

        $this->output .= "$function->name:\n";

        while ($this->byteReader->getPointer() < $targetPointer) {
            $this->disassembleOpcode(str_repeat('    ', max(1, $this->depth)));
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
        if ($opcode === Opcode::START_FRAME) {
            $this->depth += 1;
        } elseif ($opcode === Opcode::RET) {
            $this->depth -= 1;
        }

        if ($opcode === Opcode::PUSH_INT) {
            $value = $this->byteReader->readUnsignedLongLong();

            $this->output .= " $value";
        } elseif ($opcode === Opcode::PUSH_STRING) {
            $value = $this->byteReader->readString();

            $this->output .= " \"$value\"";
        } elseif ($opcode === Opcode::PUSH_BOOL) {
            $value = ($this->byteReader->readUnsignedShort() === 1) ? 'true' : 'false';

            $this->output .= " $value";
        } elseif ($opcode === Opcode::JUMP) {
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
