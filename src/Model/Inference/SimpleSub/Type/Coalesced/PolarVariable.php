<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Type\Coalesced;

use App\Model\Inference\SimpleSub\Type\VariableState;

readonly class PolarVariable
{
    public function __construct(
        public VariableState $state,
        public bool $polar,
    ) {
    }
}
