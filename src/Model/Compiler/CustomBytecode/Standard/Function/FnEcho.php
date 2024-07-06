<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode\Standard\Function;

use App\Model\Compiler\CustomBytecode\Opcode;
use App\Model\StandardType;

use function bin2hex;
use function mb_strlen;

final readonly class FnEcho implements StandardFunction
{
    private const string ARG_NAME = 'value';

    public static function getBytecode(): string
    {
        $bytecode = pack("SQH*", Opcode::LOAD->value, mb_strlen(self::ARG_NAME), bin2hex(self::ARG_NAME));
        $bytecode .= pack("S", Opcode::ECHO->value);

        return $bytecode;
    }

    public static function getName(): string
    {
        return 'echo';
    }

    public static function getArguments(): array
    {
        return [self::ARG_NAME => StandardType::ANY];
    }
}
