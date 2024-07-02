<?php

declare(strict_types=1);

namespace App\Model\Inference\Type;

interface Monotype extends Polytype
{
    public function contains(Variable $variable): bool;
    public function equals(Monotype $b): bool;
}
