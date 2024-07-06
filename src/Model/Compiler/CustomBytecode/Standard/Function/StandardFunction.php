<?php

declare(strict_types=1);

namespace App\Model\Compiler\CustomBytecode\Standard\Function;

use App\Model\StandardType;

interface StandardFunction
{
    public static function getName(): string;
    public static function getBytecode(): string;
    /** @return array<string, StandardType> */
    public static function getArguments(): array;
}
