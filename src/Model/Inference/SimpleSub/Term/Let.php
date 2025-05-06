<?php

declare(strict_types=1);

namespace App\Model\Inference\SimpleSub\Term;

use function sprintf;

readonly class Let implements Term
{
    public function __construct(
        public string $name,
        public Term $rhs,
        public Term $body,
        public bool $isRecursive,
    ) {
    }

    public function __toString(): string
    {
        return sprintf("let %s%s = %s in %s", $this->name, $this->isRecursive ? ' rec' : '', $this->rhs, $this->body);
    }
}
