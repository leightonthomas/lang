<?php

declare(strict_types=1);

namespace App\Model\Inference\Type;

use Stringable;

interface Monotype extends Polytype, Stringable
{
    public function contains(Variable $variable): bool;
    public function equals(Monotype $b): bool;
}
