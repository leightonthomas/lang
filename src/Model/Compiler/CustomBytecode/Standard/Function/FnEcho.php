<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode\Standard\Function;

use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\StandardType;

use function bin2hex;
use function mb_strlen;
use function pack;

final readonly class FnEcho implements StandardFunction
{
    /** @const list<class-string<StandardFunction>> */
    private const string ARG_NAME = 'value';

    public static function getBytecode(): string
    {
        $bytecode = pack("SQH*", Opcode::LOAD->value, mb_strlen(self::ARG_NAME), bin2hex(self::ARG_NAME));
        $bytecode .= pack("S", Opcode::ECHO->value);
        $bytecode .= pack("SS", Opcode::PUSH_UNIT->value, Opcode::RET->value);

        return $bytecode;
    }

    public static function getName(): string
    {
        return 'echo';
    }

    public static function getArguments(): array
    {
        return [self::ARG_NAME => StandardType::STRING];
    }

    public static function getReturnType(): StandardType
    {
        return StandardType::UNIT;
    }
}
