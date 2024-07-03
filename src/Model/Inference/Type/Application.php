<?php

declare(strict_types=1);

namespace App\Model\Inference\Type;

use App\Model\StandardType;

use function array_merge;
use function count;

final readonly class Application implements Monotype
{
    public string $constructor;

    public function __construct(
        /** e.g. -> (for functions), Bool, Array<T> */
        string|StandardType $constructor,
        /** @var list<Monotype> $arguments */
        public array $arguments,
    ) {
        $this->constructor = ($constructor instanceof StandardType) ? $constructor->value : $constructor;
    }

    public function getFreeVariables(): array
    {
        /** @var list<string> $output */
        $output = [];

        foreach ($this->arguments as $monotype) {
            $output = array_merge($output, $monotype->getFreeVariables());
        }

        return $output;
    }

    public function contains(Variable $variable): bool
    {
        foreach ($this->arguments as $argument) {
            if ($argument->contains($variable)) {
                return true;
            }
        }

        return false;
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'application',
            'constructor' => $this->constructor,
            'arguments' => $this->arguments,
        ];
    }

    public function equals(Monotype $b): bool
    {
        if (! ($b instanceof Application)) {
            return false;
        }

        foreach ($this->arguments as $index => $argument) {
            $other = $b->arguments[$index] ?? null;
            if ($other === null) {
                return false;
            }

            if (! $argument->equals($other)) {
                return false;
            }
        }

        return (
            ($this->constructor === $b->constructor)
            && (count($this->arguments) === count($b->arguments))
        );
    }
}
