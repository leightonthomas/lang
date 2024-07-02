<?php

declare(strict_types=1);

namespace App\Model\Inference\Type;

use JsonSerializable;

interface Polytype extends JsonSerializable
{
    /**
     * @return list<string> the free variable **names**
     */
    public function getFreeVariables(): array;
}
